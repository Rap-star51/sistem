<?php
define('DB_PATH', __DIR__ . '/../database.sqlite');
define('APP_NAME', 'Sistem Kulit Garaman');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/documents/');
define('UPLOAD_URL', '/assets/uploads/documents/');
define('BASE_URL', '/tps-v2-updated');

function getDB(): PDO
{
    static $db = null;
    if (!$db) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec("PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;");
    }
    return $db;
}

function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: /tps-v2/index.php');
        exit;
    }
}
function requireRole(string ...$roles): void
{
    requireLogin();
    if (!in_array($_SESSION['user']['role'], $roles)) {
        http_response_code(403);
        echo '<div style="padding:40px;text-align:center;font-family:sans-serif"><h2>403 - Akses Ditolak</h2><p>Anda tidak memiliki izin halaman ini.</p><a href="javascript:history.back()">Kembali</a></div>';
        exit;
    }
}
function user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function uid(): int
{
    return (int)($_SESSION['user']['id'] ?? 0);
}
function isRole(string ...$roles): bool
{
    $u = user();
    return $u && in_array($u['role'], $roles);
}

function generateBookingNo(): string
{
    $db = getDB();
    $y = date('Y');
    $n = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE booking_no LIKE 'BK-{$y}-%'")->fetchColumn();
    return 'BK-' . $y . '-' . str_pad($n + 1, 3, '0', STR_PAD_LEFT);
}
function generateInvoiceNo(): string
{
    $db = getDB();
    $y = date('Y');
    $n = (int)$db->query("SELECT COUNT(*) FROM invoices WHERE invoice_no LIKE 'INV-{$y}-%'")->fetchColumn();
    return 'INV-' . $y . '-' . str_pad($n + 1, 4, '0', STR_PAD_LEFT);
}
function generateVA(string $bank): string
{
    $prefix = [
        'BCA'     => '8808',
        'Mandiri' => '8889',
        'BNI'     => '9889',
        'BRI'     => '8889',
        'BSI'     => '9347',
        'CIMB'    => '7080',
        'Permata' => '8215',
        'Danamon' => '9900',
    ][$bank] ?? '9990';
    $uid = str_pad((string)rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    return $prefix . '0' . $uid;
}
function generateVerifCode(): string
{
    return str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function calcInvoice(float $berat, string $jenis, string $container, int $qty): array
{
    $rate = ['Kulit Sapi' => 3000, 'Kulit Kambing' => 2500, 'Kulit Domba' => 2200];
    $cfee = ['20 Feet' => 600000, '40 Feet' => 950000, '40 HC' => 1100000];
    $sub = ($berat * ($rate[$jenis] ?? 2500)) + (($cfee[$container] ?? 600000) * $qty);
    $tax = round($sub * 0.12);
    return ['subtotal' => $sub, 'tax' => $tax, 'total' => $sub + $tax];
}

function addNotif(int $userId, string $title, string $msg, string $type = 'info', ?string $link = null): void
{
    getDB()->prepare("INSERT INTO notifications(user_id,title,message,type,link) VALUES(?,?,?,?,?)")->execute([$userId, $title, $msg, $type, $link]);
}
function countUnread(int $uid): int
{
    $s = getDB()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $s->execute([$uid]);
    return (int)$s->fetchColumn();
}
function countPending(): int
{
    return (int)getDB()->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
}

function badge(string $status): string
{
    $map = [
        'pending' => 'badge-pending',
        'approved' => 'badge-approved',
        'rejected' => 'badge-rejected',
        'customs' => 'badge-customs',
        'quarantine' => 'badge-quarantine',
        'completed' => 'badge-completed',
        'unpaid' => 'badge-unpaid',
        'paid' => 'badge-paid',
        'clear' => 'badge-clear',
        'hold' => 'badge-hold'
    ];
    $cls = $map[$status] ?? 'badge-pending';
    return "<span class='badge {$cls}'>" . ucfirst($status) . "</span>";
}
function roleLabel(string $role): string
{
    return ['admin' => 'Admin TPS', 'operator' => 'Operator', 'customs' => 'Bea Cukai', 'quarantine' => 'Karantina', 'user' => 'Pengguna Jasa'][$role] ?? ucfirst($role);
}
function fileIcon(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'pdf') return '<i class="fas fa-file-pdf" style="color:#ef4444;font-size:22px"></i>';
    if (in_array($ext, ['doc', 'docx'])) return '<i class="fas fa-file-word" style="color:#2563eb;font-size:22px"></i>';
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) return '<i class="fas fa-file-image" style="color:#059669;font-size:22px"></i>';
    return '<i class="fas fa-file" style="color:#64748b;font-size:22px"></i>';
}
function fmtRp(float $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}
function fmtDate(string $d): string
{
    if (!$d || $d === '0000-00-00') return '-';
    $m = [
        '01' => 'Jan',
        '02' => 'Feb',
        '03' => 'Mar',
        '04' => 'Apr',
        '05' => 'Mei',
        '06' => 'Jun',
        '07' => 'Jul',
        '08' => 'Agu',
        '09' => 'Sep',
        '10' => 'Okt',
        '11' => 'Nov',
        '12' => 'Des'
    ];
    $p = explode('-', $d);
    return ($p[2] ?? '') . ' ' . ($m[$p[1] ?? ''] ?? '') . ' ' . ($p[0] ?? '');
}
function timeAgo(string $dt): string
{
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return date('d/m/Y', strtotime($dt));
}

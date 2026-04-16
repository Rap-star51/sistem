<?php
session_start();
require_once 'includes/config.php';
if (isset($_SESSION['user'])) {
  header('Location: /tps-v2/pages/dashboard.php');
  exit;
}

$error = '';
$success = '';
$view = 'login'; // login | forgot | verify | reset

/* ── FORGOT PASSWORD FLOW ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'login';
  $db = getDB();

  if ($action === 'login') {
    $un = trim($_POST['username'] ?? '');
    $pw = $_POST['password'] ?? '';
    if (!$un || !$pw) {
      $error = 'Username dan password wajib diisi.';
    } else {
      $s = $db->prepare("SELECT * FROM users WHERE username=? AND is_active=1");
      $s->execute([$un]);
      $u = $s->fetch();
      if ($u && password_verify($pw, $u['password'])) {
        $_SESSION['user'] = $u;
        $db->prepare("UPDATE users SET last_login=CURRENT_TIMESTAMP WHERE id=?")->execute([$u['id']]);
        header('Location: pages/dashboard.php');
        exit;
      } else {
        $error = 'Username atau password salah.';
      }
    }
  } elseif ($action === 'forgot') {
    $view = 'forgot';
    $un = trim($_POST['username_forgot'] ?? '');
    $em = trim($_POST['email_forgot'] ?? '');
    if (!$un || !$em) {
      $error = 'Harap isi username dan email.';
    } else {
      $s = $db->prepare("SELECT * FROM users WHERE username=? AND email=?");
      $s->execute([$un, $em]);
      $u = $s->fetch();
      if ($u) {
        $code = generateVerifCode();
        $db->prepare("INSERT INTO verification_codes(user_id,email,code,type,expires_at) VALUES(?,?,?,?,datetime('now','+1 hour'))")->execute([$u['id'], $em, $code, 'password_reset']);
        // In production: send email. For demo, store in session.
        $_SESSION['reset_uid'] = $u['id'];
        $_SESSION['reset_email'] = $em;
        $_SESSION['demo_code'] = $code; // DEMO ONLY
        $success = "Kode verifikasi 6-digit telah dikirim ke <strong>$em</strong>. (Demo: kode = <strong>$code</strong>)";
        $view = 'verify';
      } else {
        $error = 'Username atau email tidak ditemukan.';
      }
    }
  } elseif ($action === 'verify') {
    $view = 'verify';
    $code = trim($_POST['verif_code'] ?? '');
    $uid = (int)($_SESSION['reset_uid'] ?? 0);
    if (!$uid || !$code) {
      $error = 'Sesi tidak valid. Mulai dari awal.';
      $view = 'forgot';
    } else {
      $s = $db->prepare("SELECT * FROM verification_codes WHERE user_id=? AND code=? AND used=0 AND expires_at > CURRENT_TIMESTAMP ORDER BY id DESC LIMIT 1");
      $s->execute([$uid, $code]);
      $vc = $s->fetch();
      if ($vc) {
        $_SESSION['reset_verified'] = $vc['id'];
        $view = 'reset';
      } else {
        $error = 'Kode verifikasi salah atau sudah kedaluwarsa.';
      }
    }
  } elseif ($action === 'reset') {
    $uid = (int)($_SESSION['reset_uid'] ?? 0);
    $vcid = (int)($_SESSION['reset_verified'] ?? 0);
    $pw1 = $_POST['new_password'] ?? '';
    $pw2 = $_POST['confirm_password'] ?? '';
    if (!$uid || !$vcid) {
      $error = 'Sesi tidak valid.';
      $view = 'login';
    } elseif (strlen($pw1) < 6) {
      $error = 'Password minimal 6 karakter.';
      $view = 'reset';
    } elseif ($pw1 !== $pw2) {
      $error = 'Konfirmasi password tidak sesuai.';
      $view = 'reset';
    } else {
      $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($pw1, PASSWORD_DEFAULT), $uid]);
      $db->prepare("UPDATE verification_codes SET used=1 WHERE id=?")->execute([$vcid]);
      unset($_SESSION['reset_uid'], $_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['demo_code']);
      $success = 'Password berhasil direset! Silakan login.';
      $view = 'login';
    }
  }
} elseif (isset($_GET['view'])) {
  $view = $_GET['view'];
  if (!isset($_SESSION['reset_uid']) && in_array($view, ['verify', 'reset'])) $view = 'forgot';
}
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="theme-color" content="#0f1d3a">
  <title>Login — Sistem Kulit Garaman TPS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    body {
      background: var(--body-bg);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }

    [data-theme="dark"] body {
      background: #07101f;
    }

    .login-outer {
      display: flex;
      width: 100%;
      max-width: 880px;
      min-height: 560px;
      background: var(--surface);
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--border);
      overflow: hidden;
    }

    .login-panel {
      flex: 1;
      background: linear-gradient(160deg, #0a1628 0%, #0f2d5c 50%, #0b4a9e 100%);
      padding: 48px 44px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }

    .login-panel::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: rgba(59, 130, 246, 0.1);
      right: -80px;
      top: -80px;
    }

    .login-panel::after {
      content: '';
      position: absolute;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: rgba(59, 130, 246, 0.06);
      left: -60px;
      bottom: -60px;
    }

    .login-panel .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      position: relative;
      z-index: 1;
    }

    .login-panel .brand img {
      height: 40px;
    }

    .login-panel .brand-text span:first-child {
      display: block;
      color: #fff;
      font-weight: 800;
      font-size: 16px;
    }

    .login-panel .brand-text span:last-child {
      display: block;
      color: rgba(255, 255, 255, 0.5);
      font-size: 11px;
    }

    .login-panel h1 {
      font-size: 30px;
      font-weight: 800;
      color: #fff;
      line-height: 1.3;
      position: relative;
      z-index: 1;
    }

    .login-panel h1 span {
      color: #60a5fa;
    }

    .login-panel p {
      color: rgba(255, 255, 255, 0.65);
      font-size: 14px;
      line-height: 1.6;
      position: relative;
      z-index: 1;
    }

    .features {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .feat-item {
      display: flex;
      align-items: center;
      gap: 10px;
      color: rgba(255, 255, 255, 0.75);
      font-size: 13px;
    }

    .feat-item i {
      width: 28px;
      height: 28px;
      background: rgba(59, 130, 246, 0.2);
      border-radius: 7px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      color: #60a5fa;
    }

    .login-form-side {
      width: 400px;
      padding: 44px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .form-head {
      margin-bottom: 28px;
    }

    .form-head h2 {
      font-size: 22px;
      font-weight: 800;
      color: var(--text-primary);
    }

    .form-head p {
      font-size: 13px;
      color: var(--text-secondary);
      margin-top: 4px;
    }

    .dark-toggle-wrap {
      position: absolute;
      top: 20px;
      right: 20px;
    }

    .demo-block {
      margin-top: 20px;
      padding: 14px;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
    }

    .demo-block h5 {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--text-muted);
      margin-bottom: 8px;
    }

    .demo-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 6px 8px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.15s;
    }

    .demo-row:hover {
      background: var(--surface-3);
    }

    .demo-row .role {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-primary);
    }

    .demo-row .cred {
      font-size: 11px;
      color: var(--text-muted);
      font-family: var(--font-mono);
    }

    .step-indicator {
      display: flex;
      gap: 6px;
      margin-bottom: 20px;
    }

    .step-dot {
      flex: 1;
      height: 3px;
      border-radius: 99px;
      background: var(--border);
    }

    .step-dot.active {
      background: var(--primary);
    }

    .step-dot.done {
      background: var(--success);
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      color: var(--text-secondary);
      cursor: pointer;
      margin-bottom: 12px;
      background: none;
      border: none;
      padding: 0;
    }

    .back-link:hover {
      color: var(--primary);
    }

    @media(max-width:700px) {
      .login-outer {
        flex-direction: column;
        max-width: 440px;
        min-height: auto;
      }

      .login-panel {
        padding: 28px 24px;
      }

      .login-panel h1 {
        font-size: 22px;
      }

      .login-form-side {
        width: 100%;
        padding: 28px 24px;
      }
    }
  </style>
</head>

<body>

  <div class="login-outer" style="position:relative">
    <!-- LEFT PANEL -->
    <div class="login-panel">
      <div class="brand">
        <img src="assets/tps.jpg" alt="TPS">
        <div class="brand-text"><span>TPS Webaccess</span><span>Sistem Informasi Lini 2</span></div>
      </div>
      <div>
        <h1>Layanan<br>Ekspor <span>Limbah Kulit</span><br>Lini 2 TPS</h1>
        <p style="margin-top:12px">Platform digital untuk pengelolaan booking ekspor limbah kulit garaman secara terintegrasi di PT Terminal Petikemas Surabaya.</p>
      </div>
      <div class="features">
        <div class="feat-item"><i class="fas fa-ship"></i> Manajemen Booking Petikemas</div>
        <div class="feat-item"><i class="fas fa-check-double"></i> Approval Multi-Level</div>
        <div class="feat-item"><i class="fas fa-passport"></i> Integrasi Bea Cukai & Karantina</div>
      </div>
    </div>

    <!-- DARK TOGGLE -->
    <div class="dark-toggle-wrap">
      <button class="dark-toggle" onclick="toggleDarkLogin()" title="Toggle Mode" style="margin:0"><i class="fas fa-moon" id="loginDarkIcon"></i></button>
    </div>

    <!-- RIGHT: FORMS -->
    <div class="login-form-side">

      <?php if ($view === 'login'): ?>
        <!-- ── LOGIN FORM ── -->
        <div class="form-head">
          <h2>Selamat Datang 👋</h2>
          <p>Masuk menggunakan akun Anda</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= $success ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="login">
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Username</label>
            <div class="input-icon-wrap">
              <i class="fas fa-user input-icon"></i>
              <input type="text" name="username" class="form-control" placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <label class="form-label" style="margin:0">Password</label>
              <button type="button" class="back-link" style="margin:0" onclick="setView('forgot')"><i class="fas fa-question-circle"></i> Lupa Password?</button>
            </div>
            <div class="input-icon-wrap">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" id="pw" name="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password" style="padding-right:42px">
              <span class="input-icon-right" onclick="togglePw()"><i class="fas fa-eye" id="eyeIco"></i></span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center"><i class="fas fa-sign-in-alt"></i> Masuk</button>
        </form>
        <div class="demo-block" style="display:none">
          <h5>Akun Demo</h5>
          <div class="demo-row" onclick="fill('admin','password')"><span class="role">🔴 Admin</span><span class="cred">admin / password</span></div>
          <div class="demo-row" onclick="fill('operator','password')"><span class="role">🟢 Operator</span><span class="cred">operator / password</span></div>
          <div class="demo-row" onclick="fill('customs','password')"><span class="role">🟣 Bea Cukai</span><span class="cred">customs / password</span></div>
          <div class="demo-row" onclick="fill('quarantine','password')"><span class="role">🟠 Karantina</span><span class="cred">quarantine / password</span></div>
          <div class="demo-row" onclick="fill('ptmakmur','password')"><span class="role">🔵 User PT</span><span class="cred">ptmakmur / password</span></div>
        </div>

      <?php elseif ($view === 'forgot'): ?>
        <!-- ── FORGOT FORM ── -->
        <div class="step-indicator">
          <div class="step-dot active"></div>
          <div class="step-dot"></div>
          <div class="step-dot"></div>
        </div>
        <div class="form-head">
          <h2>Lupa Password 🔑</h2>
          <p>Masukkan username dan email terdaftar untuk mendapatkan kode verifikasi</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="forgot">
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Username</label>
            <div class="input-icon-wrap"><i class="fas fa-user input-icon"></i><input type="text" name="username_forgot" class="form-control" placeholder="Username Anda" required></div>
          </div>
          <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">Email Terdaftar</label>
            <div class="input-icon-wrap"><i class="fas fa-envelope input-icon"></i><input type="email" name="email_forgot" class="form-control" placeholder="email@perusahaan.com" required></div>
          </div>
          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center"><i class="fas fa-paper-plane"></i> Kirim Kode Verifikasi</button>
        </form>
        <div style="margin-top:20px;text-align:center">
          <button class="back-link" onclick="setView('login')"><i class="fas fa-arrow-left"></i> Kembali ke Login</button>
        </div>

      <?php elseif ($view === 'verify'): ?>
        <!-- ── VERIFY CODE FORM ── -->
        <div class="step-indicator">
          <div class="step-dot done"></div>
          <div class="step-dot active"></div>
          <div class="step-dot"></div>
        </div>
        <div class="form-head">
          <h2>Kode Verifikasi 📧</h2>
          <p>Masukkan 6-digit kode yang dikirim ke <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong></p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-info"><i class="fas fa-info-circle"></i><?= $success ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="verify">
          <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">Kode Verifikasi (6 digit)</label>
            <input type="text" name="verif_code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required style="font-size:28px;letter-spacing:12px;text-align:center;font-family:var(--font-mono)">
            <span class="form-hint">Kode berlaku selama 1 jam</span>
          </div>
          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center"><i class="fas fa-check"></i> Verifikasi</button>
        </form>
        <?php if (isset($_SESSION['demo_code'])): ?>
          <div class="alert alert-warning" style="margin-top:12px"><i class="fas fa-exclamation-triangle"></i>
            <div><strong>Demo Mode:</strong> Kode verifikasi adalah <strong style="font-family:var(--font-mono);font-size:18px"><?= $_SESSION['demo_code'] ?></strong></div>
          </div>
        <?php endif; ?>

      <?php elseif ($view === 'reset'): ?>
        <!-- ── RESET PASSWORD FORM ── -->
        <div class="step-indicator">
          <div class="step-dot done"></div>
          <div class="step-dot done"></div>
          <div class="step-dot active"></div>
        </div>
        <div class="form-head">
          <h2>Reset Password 🔒</h2>
          <p>Buat password baru untuk akun Anda</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="reset">
          <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Password Baru (min. 6 karakter)</label>
            <div class="input-icon-wrap"><i class="fas fa-lock input-icon"></i><input type="password" name="new_password" class="form-control" placeholder="Password baru" required minlength="6"></div>
          </div>
          <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">Konfirmasi Password</label>
            <div class="input-icon-wrap"><i class="fas fa-lock input-icon"></i><input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required minlength="6"></div>
          </div>
          <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Simpan Password Baru</button>
        </form>
      <?php endif; ?>

    </div>
  </div>

  <script>
    function togglePw() {
      const p = document.getElementById('pw'),
        i = document.getElementById('eyeIco');
      p.type = p.type === 'password' ? 'text' : 'password';
      i.className = p.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function fill(u, p) {
      document.querySelector('[name=username]').value = u;
      document.getElementById('pw').value = p;
    }

    function setView(v) {
      window.location.href = '?view=' + v;
    }

    function toggleDarkLogin() {
      const h = document.documentElement,
        d = h.getAttribute('data-theme') === 'dark';
      h.setAttribute('data-theme', d ? 'light' : 'dark');
      localStorage.setItem('tps_dm', d ? '0' : '1');
      updateLoginIcon();
    }

    function updateLoginIcon() {
      const d = document.documentElement.getAttribute('data-theme') === 'dark';
      const ic = document.getElementById('loginDarkIcon');
      if (ic) {
        ic.className = d ? 'fas fa-sun' : 'fas fa-moon';
      }
    }
    (function() {
      const s = localStorage.getItem('tps_dm');
      if (s === '1') document.documentElement.setAttribute('data-theme', 'dark');
      if (s === '0') document.documentElement.setAttribute('data-theme', 'light');
      updateLoginIcon();
    })();
  </script>
</body>

</html>
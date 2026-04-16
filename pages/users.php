<?php
session_start();
require_once '../includes/config.php';
requireRole('admin');
$pageTitle='Manajemen User'; $activePage='users';
$db=getDB(); $msg=''; $mt='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['action']??'';
    if($act==='add') {
        $un=trim($_POST['username']??''); $pw=$_POST['password']??'';
        $fn=trim($_POST['full_name']??''); $role=$_POST['role']??'';
        $co=trim($_POST['company']??''); $em=trim($_POST['email']??'');
        $ph=trim($_POST['phone']??'');
        $colors=['#1a56db','#0e9f6e','#6875f5','#f05252','#ff5a1f','#0694a2','#7e3af2','#c27803'];
        $ac=$colors[array_rand($colors)];
        if(!$un||!$pw||!$fn||!$role){$msg='Field wajib tidak boleh kosong.';$mt='danger';}
        else {
            try {
                $db->prepare("INSERT INTO users(username,password,full_name,role,company,email,phone,avatar_color) VALUES(?,?,?,?,?,?,?,?)")
                   ->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$fn,$role,$co,$em,$ph,$ac]);
                $msg="User <strong>$un</strong> berhasil ditambahkan."; $mt='success';
            } catch(Exception $e) { $msg='Username sudah digunakan.'; $mt='danger'; }
        }
    } elseif($act==='delete') {
        $uid2=(int)($_POST['user_id']??0);
        if($uid2 && $uid2!==uid()) { $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid2]); $msg='User berhasil dihapus.'; $mt='success'; }
        else { $msg='Tidak dapat menghapus akun sendiri.'; $mt='danger'; }
    } elseif($act==='toggle') {
        $uid2=(int)($_POST['user_id']??0);
        if($uid2) { $db->prepare("UPDATE users SET is_active=1-is_active WHERE id=?")->execute([$uid2]); $msg='Status user diperbarui.'; $mt='success'; }
    }
}

$users=$db->query("SELECT * FROM users ORDER BY role,full_name")->fetchAll();
include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-users-cog" style="color:var(--primary)"></i> Manajemen User</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Users</span></div>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')"><i class="fas fa-plus"></i> Tambah User</button>
  </div>
</div>
<?php if($msg): ?><div class="alert alert-<?= $mt ?> fade-in"><i class="fas fa-check-circle"></i><?= $msg ?></div><?php endif; ?>

<!-- Role legend -->
<div class="card fade-in" style="padding:14px 18px;margin-bottom:16px">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Role Akses:</span>
    <?php $roles=['admin'=>['Admin','#dc2626'],'operator'=>['Operator','#0b5ede'],'customs'=>['Bea Cukai','#7c3aed'],'quarantine'=>['Karantina','#d97706'],'user'=>['Pengguna Jasa','#059669']];
    foreach($roles as $r=>[$lbl,$clr]): ?>
    <span style="background:<?= $clr ?>1a;color:<?= $clr ?>;border:1px solid <?= $clr ?>33;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700"><?= $lbl ?></span>
    <?php endforeach; ?>
  </div>
</div>

<div class="card fade-in fade-in-1">
  <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Daftar User (<?= count($users) ?>)</div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Avatar</th><th>Username</th><th>Nama Lengkap</th><th>Role</th><th>Perusahaan</th><th>Email</th><th>Terakhir Login</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach($users as $ur): ?>
      <tr>
        <td><div class="avatar" style="background:<?= htmlspecialchars($ur['avatar_color']) ?>;width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff"><?= strtoupper(substr($ur['full_name'],0,1)) ?></div></td>
        <td><strong style="font-family:var(--font-mono)"><?= htmlspecialchars($ur['username']) ?></strong></td>
        <td><?= htmlspecialchars($ur['full_name']) ?></td>
        <td>
          <?php $rc=['admin'=>'#dc2626','operator'=>'#0b5ede','customs'=>'#7c3aed','quarantine'=>'#d97706','user'=>'#059669'][$ur['role']]??'#64748b'; ?>
          <span style="background:<?= $rc ?>1a;color:<?= $rc ?>;border:1px solid <?= $rc ?>33;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700"><?= roleLabel($ur['role']) ?></span>
        </td>
        <td><?= htmlspecialchars($ur['company']??'—') ?></td>
        <td style="font-size:12px"><?= htmlspecialchars($ur['email']??'—') ?></td>
        <td style="font-size:12px;color:var(--text-muted)"><?= $ur['last_login']?date('d/m/Y H:i',strtotime($ur['last_login'])):'Belum pernah' ?></td>
        <td>
          <?php if($ur['is_active']): ?><span class="badge badge-approved">Aktif</span><?php else: ?><span class="badge badge-rejected">Non-aktif</span><?php endif; ?>
        </td>
        <td>
          <div style="display:flex;gap:5px">
            <?php if($ur['id']!==uid()): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="user_id" value="<?= $ur['id'] ?>">
              <button type="submit" class="btn btn-xs btn-secondary" title="Toggle Status"><i class="fas fa-toggle-<?= $ur['is_active']?'on':'off' ?>"></i></button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus user ini?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= $ur['id'] ?>">
              <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
            </form>
            <?php else: ?><span style="font-size:11px;color:var(--text-muted)">Akun Anda</span><?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box modal-lg">
    <div class="modal-head"><h3><i class="fas fa-user-plus"></i> Tambah User Baru</h3><button class="modal-close" onclick="document.getElementById('addModal').classList.remove('show')">×</button></div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Username <span style="color:red">*</span></label><input type="text" name="username" class="form-control" required placeholder="Username unik"></div>
          <div class="form-group"><label class="form-label">Password <span style="color:red">*</span></label><input type="password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 karakter"></div>
          <div class="form-group"><label class="form-label">Nama Lengkap <span style="color:red">*</span></label><input type="text" name="full_name" class="form-control" required placeholder="Nama lengkap"></div>
          <div class="form-group">
            <label class="form-label">Role <span style="color:red">*</span></label>
            <select name="role" class="form-select" required>
              <option value="">-- Pilih Role --</option>
              <option value="admin">Admin</option>
              <option value="operator">Operator</option>
              <option value="customs">Customs (Bea Cukai)</option>
              <option value="quarantine">Quarantine (Karantina)</option>
              <option value="user">User (Pengguna Jasa)</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Perusahaan/Instansi</label><input type="text" name="company" class="form-control" placeholder="PT / Instansi"></div>
          <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" placeholder="email@domain.com"></div>
          <div class="form-group"><label class="form-label">No. Telepon</label><input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxx"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php include '../includes/layout_footer.php'; ?>

<?php
session_start();
require_once '../includes/config.php';
requireLogin();
$pageTitle='Profil Saya'; $activePage='';
$db=getDB(); $u=user();
$msg=''; $mt='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['action']??'profile';
    if($act==='profile') {
        $fn=trim($_POST['full_name']??''); $em=trim($_POST['email']??'');
        $co=trim($_POST['company']??''); $ph=trim($_POST['phone']??'');
        $ac=trim($_POST['avatar_color']??$u['avatar_color']);
        if($fn) {
            $db->prepare("UPDATE users SET full_name=?,email=?,company=?,phone=?,avatar_color=? WHERE id=?")->execute([$fn,$em,$co,$ph,$ac,$u['id']]);
            $_SESSION['user']['full_name']=$fn; $_SESSION['user']['email']=$em;
            $_SESSION['user']['company']=$co; $_SESSION['user']['avatar_color']=$ac;
            $msg='Profil berhasil diperbarui.'; $mt='success';
        }
    } elseif($act==='password') {
        $old=$_POST['old_pw']??''; $new1=$_POST['new_pw']??''; $new2=$_POST['confirm_pw']??'';
        $dbPw=$db->prepare("SELECT password FROM users WHERE id=?"); $dbPw->execute([$u['id']]); $dbPw=$dbPw->fetchColumn();
        if(!password_verify($old,$dbPw)){$msg='Password lama salah.';$mt='danger';}
        elseif(strlen($new1)<6){$msg='Password baru minimal 6 karakter.';$mt='danger';}
        elseif($new1!==$new2){$msg='Konfirmasi password tidak sesuai.';$mt='danger';}
        else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new1,PASSWORD_DEFAULT),$u['id']]);
            $msg='Password berhasil diubah.'; $mt='success';
        }
    }
    $u=user(); // refresh
}

$myBookings=$db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=?"); $myBookings->execute([$u['id']]); $myBookings=(int)$myBookings->fetchColumn();

include '../includes/layout.php';
$colors=['#1a56db','#0e9f6e','#6875f5','#f05252','#ff5a1f','#0694a2','#7e3af2','#c27803','#1c64f2','#e02424'];
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-user-circle" style="color:var(--primary)"></i> Profil Saya</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Profil</span></div>
    </div>
  </div>
</div>
<?php if($msg): ?><div class="alert alert-<?= $mt ?> fade-in"><i class="fas fa-<?= $mt==='success'?'check-circle':'exclamation-circle' ?>"></i><?= $msg ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start">
  <!-- Profile Card -->
  <div class="card fade-in" style="text-align:center;padding:28px">
    <div class="avatar" style="width:80px;height:80px;border-radius:50%;background:<?= htmlspecialchars($u['avatar_color']??'#005baa') ?>;font-size:30px;font-weight:800;color:#fff;margin:0 auto 14px;display:flex;align-items:center;justify-content:center">
      <?= strtoupper(substr($u['full_name'],0,1)) ?>
    </div>
    <div style="font-size:18px;font-weight:800;color:var(--text-primary)"><?= htmlspecialchars($u['full_name']) ?></div>
    <?php $rc=['admin'=>'#dc2626','operator'=>'#0b5ede','customs'=>'#7c3aed','quarantine'=>'#d97706','user'=>'#059669'][$u['role']]??'#64748b'; ?>
    <div style="margin:8px 0"><span style="background:<?= $rc ?>1a;color:<?= $rc ?>;padding:3px 12px;border-radius:99px;font-size:12px;font-weight:700"><?= roleLabel($u['role']) ?></span></div>
    <div style="font-size:13px;color:var(--text-secondary)"><?= htmlspecialchars($u['company']??'') ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= htmlspecialchars($u['email']??'') ?></div>
    <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
    <div style="display:flex;justify-content:center;gap:24px;font-size:12px">
      <div><div style="font-size:22px;font-weight:800;color:var(--primary)"><?= $myBookings ?></div><div style="color:var(--text-muted)">Booking</div></div>
    </div>
    <div style="margin-top:16px;font-size:11px;color:var(--text-muted)">
      <i class="fas fa-clock"></i> Login: <?= $u['last_login']?date('d/m/Y H:i',strtotime($u['last_login'])):'—' ?>
    </div>
    <!-- Color picker -->
    <div style="margin-top:16px">
      <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px">Warna Avatar</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center" id="colorPicker">
        <?php foreach($colors as $c): ?>
        <div onclick="pickColor('<?= $c ?>')" style="width:26px;height:26px;border-radius:50%;background:<?= $c ?>;cursor:pointer;border:3px solid <?= $c===$u['avatar_color']?'var(--text-primary)':'transparent' ?>;transition:transform 0.15s" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Forms -->
  <div>
    <div class="card fade-in fade-in-1">
      <div class="card-header"><div class="card-title"><i class="fas fa-edit"></i> Edit Profil</div></div>
      <form method="POST">
        <input type="hidden" name="action" value="profile">
        <input type="hidden" name="avatar_color" id="colorInput" value="<?= htmlspecialchars($u['avatar_color']??'#005baa') ?>">
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Nama Lengkap <span style="color:red">*</span></label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($u['full_name']) ?>" required></div>
          <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']??'') ?>"></div>
          <div class="form-group"><label class="form-label">Perusahaan</label><input type="text" name="company" class="form-control" value="<?= htmlspecialchars($u['company']??'') ?>"></div>
          <div class="form-group"><label class="form-label">No. Telepon</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone']??'') ?>"></div>
        </div>
        <div style="margin-top:16px"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Profil</button></div>
      </form>
    </div>

    <div class="card fade-in fade-in-2">
      <div class="card-header"><div class="card-title"><i class="fas fa-lock"></i> Ganti Password</div></div>
      <form method="POST">
        <input type="hidden" name="action" value="password">
        <div class="form-grid">
          <div class="form-group"><label class="form-label">Password Lama</label><input type="password" name="old_pw" class="form-control" required placeholder="Password saat ini"></div>
          <div class="form-group"><label class="form-label">Password Baru</label><input type="password" name="new_pw" class="form-control" required minlength="6" placeholder="Min. 6 karakter"></div>
          <div class="form-group"><label class="form-label">Konfirmasi Password</label><input type="password" name="confirm_pw" class="form-control" required placeholder="Ulangi password baru"></div>
        </div>
        <div style="margin-top:16px"><button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Ubah Password</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function pickColor(c){
  document.getElementById('colorInput').value=c;
  document.querySelector('.avatar').style.background=c;
  document.querySelectorAll('#colorPicker div').forEach(d=>{d.style.border='3px solid '+(d.style.background===c?'var(--text-primary)':'transparent');});
}
</script>
<?php include '../includes/layout_footer.php'; ?>

<?php
session_start();
require_once '../includes/config.php';
requireLogin();
$pageTitle='Notifikasi'; $activePage='';
$db=getDB();

// Mark all as read on page load
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([uid()]);

$notifs=$db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([uid()]); $notifs=$notifs->fetchAll();

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-bell" style="color:var(--primary)"></i> Semua Notifikasi</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Notifikasi</span></div>
    </div>
  </div>
</div>

<div class="card fade-in">
  <?php if(empty($notifs)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--text-muted)">
    <i class="fas fa-bell-slash" style="font-size:48px;display:block;margin-bottom:12px"></i>
    <div style="font-size:16px;font-weight:700">Tidak ada notifikasi</div>
  </div>
  <?php else: ?>
  <?php foreach($notifs as $n):
    $dotColors=['success'=>'#059669','danger'=>'#dc2626','warning'=>'#d97706','info'=>'#0284c7','invoice'=>'#7c3aed'];
    $bgColors=['success'=>'var(--success-bg)','danger'=>'var(--danger-bg)','warning'=>'var(--warning-bg)','info'=>'var(--info-bg)','invoice'=>'var(--purple-bg)'];
    $dc=$dotColors[$n['type']]??'#64748b';
    $bg=$bgColors[$n['type']]??'var(--surface-2)';
  ?>
  <div style="display:flex;gap:14px;align-items:flex-start;padding:16px;border-bottom:1px solid var(--border-light);transition:background 0.15s" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
    <div style="width:40px;height:40px;border-radius:50%;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <div style="width:10px;height:10px;border-radius:50%;background:<?= $dc ?>"></div>
    </div>
    <div style="flex:1">
      <div style="font-size:14px;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($n['title']) ?></div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:3px;line-height:1.5"><?= htmlspecialchars($n['message']) ?></div>
      <?php if($n['link']): ?><a href="<?= BASE_URL.htmlspecialchars($n['link']) ?>" class="btn btn-xs btn-outline" style="margin-top:8px">Lihat Detail</a><?php endif; ?>
    </div>
    <div style="font-size:11px;color:var(--text-muted);white-space:nowrap"><?= timeAgo($n['created_at']) ?></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include '../includes/layout_footer.php'; ?>

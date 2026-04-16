<?php
session_start();
require_once '../includes/config.php';
requireLogin();
$pageTitle='Detail Booking'; $activePage='';
$db=getDB(); $u=user();
$id=(int)($_GET['id']??0);

$stmt=$db->prepare("SELECT b.*,u.full_name as uname,u.email as uemail,u.company FROM bookings b LEFT JOIN users u ON b.user_id=u.id WHERE b.id=?");
$stmt->execute([$id]); $b=$stmt->fetch();
if(!$b){header('Location: dashboard.php');exit;}

// Access check: user can only see own bookings
if(isRole('user') && $b['user_id']!==uid()){header('Location: my_bookings.php');exit;}

$docs  = $db->prepare("SELECT * FROM booking_documents WHERE booking_id=? ORDER BY created_at"); $docs->execute([$id]); $docs=$docs->fetchAll();
$alog  = $db->prepare("SELECT a.*,u.full_name FROM approvals a LEFT JOIN users u ON a.approved_by=u.id WHERE a.booking_id=? ORDER BY a.created_at"); $alog->execute([$id]); $alog=$alog->fetchAll();
$cust  = $db->prepare("SELECT c.*,u.full_name as pname FROM customs c LEFT JOIN users u ON c.petugas_id=u.id WHERE c.booking_id=?"); $cust->execute([$id]); $cust=$cust->fetch();
$quar  = $db->prepare("SELECT q.*,u.full_name as pname FROM quarantine q LEFT JOIN users u ON q.petugas_id=u.id WHERE q.booking_id=?"); $quar->execute([$id]); $quar=$quar->fetch();
$inv   = $db->prepare("SELECT * FROM invoices WHERE booking_id=?"); $inv->execute([$id]); $inv=$inv->fetch();

$stepsMap=['pending'=>0,'approved'=>1,'customs'=>2,'quarantine'=>3,'completed'=>4];
$curStep=isset($stepsMap[$b['status']])?$stepsMap[$b['status']]:-1;
$isRej=$b['status']==='rejected';

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-file-search" style="color:var(--primary)"></i> Detail Booking</div>
      <div class="breadcrumb">
        <a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span>
        <?php if(isRole('user')): ?><a href="my_bookings.php">Status Booking</a><?php else: ?><a href="approvals.php">Approvals</a><?php endif; ?>
        <span class="breadcrumb-sep">/</span><span class="breadcrumb-current"><?= htmlspecialchars($b['booking_no']) ?></span>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <?php if(isRole('user')): ?>
      <a href="my_bookings.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      <?php else: ?>
      <a href="approvals.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- STEP BAR -->
<div class="card fade-in" style="padding:20px 24px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
    <span style="font-size:18px;font-weight:800;color:var(--primary);font-family:var(--font-mono)"><?= htmlspecialchars($b['booking_no']) ?></span>
    <?= badge($b['status']) ?>
  </div>
  <?php if(!$isRej): ?>
  <div class="step-bar">
    <?php $steps=[['Booking','fa-file-alt'],['Approved','fa-check-double'],['Customs','fa-passport'],['Karantina','fa-shield-virus'],['Selesai','fa-trophy']];
    foreach($steps as $si=>$st): $isDone=$curStep>$si; $isCur=$curStep===$si; ?>
    <div class="step-item <?= $isDone?'done':($isCur?'active':'') ?>" style="flex:1">
      <div class="step-circle <?= $isDone?'done':($isCur?'active':'pending') ?>">
        <?php if($isDone) echo '<i class="fas fa-check" style="font-size:11px"></i>'; else echo ($si+1); ?>
      </div>
      <div class="step-lbl"><?= $st[0] ?></div>
    </div>
    <?php if($si<count($steps)-1): ?><div class="step-line <?= $isDone?'done':'' ?>"></div><?php endif; endforeach; ?>
  </div>
  <?php else: ?>
  <div class="alert alert-danger" style="margin:0"><i class="fas fa-times-circle"></i><div><strong>Booking Ditolak</strong><?php $lr=end($alog); if($lr&&$lr['action']==='reject') echo ' — '.htmlspecialchars($lr['catatan']); ?></div></div>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;align-items:start">

  <!-- LEFT -->
  <div>
    <!-- Booking Info -->
    <div class="card fade-in fade-in-1">
      <div class="card-header"><div class="card-title"><i class="fas fa-file-alt"></i> Informasi Booking</div></div>
      <div class="info-row"><div class="info-lbl">Nama PT</div><div class="info-val"><strong><?= htmlspecialchars($b['nama_pt']) ?></strong></div></div>
      <div class="info-row"><div class="info-lbl">User</div><div class="info-val"><?= htmlspecialchars($b['uname']??'-') ?> (<?= htmlspecialchars($b['uemail']??'-') ?>)</div></div>
      <div class="info-row"><div class="info-lbl">Jenis Kulit</div><div class="info-val"><?= htmlspecialchars($b['jenis_kulit']) ?></div></div>
      <div class="info-row"><div class="info-lbl">Berat</div><div class="info-val"><strong><?= number_format($b['berat']) ?> kg</strong></div></div>
      <div class="info-row"><div class="info-lbl">Ukuran Kontainer</div><div class="info-val"><?= htmlspecialchars($b['ukuran_kontainer']) ?> × <?= $b['jumlah_kontainer'] ?> unit</div></div>
      <div class="info-row"><div class="info-lbl">Tanggal Kirim</div><div class="info-val"><?= fmtDate($b['tanggal_kirim']) ?></div></div>
      <div class="info-row"><div class="info-lbl">Asal Negara</div><div class="info-val"><?= htmlspecialchars($b['asal_negara']??'-') ?></div></div>
      <div class="info-row"><div class="info-lbl">Negara Tujuan</div><div class="info-val"><?= htmlspecialchars($b['tujuan_negara']??'-') ?></div></div>
      <?php if($b['catatan']): ?><div class="info-row"><div class="info-lbl">Catatan</div><div class="info-val"><?= nl2br(htmlspecialchars($b['catatan'])) ?></div></div><?php endif; ?>
      <div class="info-row"><div class="info-lbl">Dibuat</div><div class="info-val"><?= date('d/m/Y H:i',strtotime($b['created_at'])) ?></div></div>
    </div>

    <!-- Documents -->
    <div class="card fade-in fade-in-2">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-paperclip"></i> Dokumen Pendukung (<?= count($docs) ?>)</div>
      </div>
      <?php if(empty($docs)): ?>
      <div style="text-align:center;padding:24px;color:var(--text-muted)"><i class="fas fa-folder-open" style="font-size:32px;display:block;margin-bottom:8px"></i>Tidak ada dokumen</div>
      <?php else: ?>
      <div class="file-list">
        <?php foreach($docs as $d): ?>
        <div class="file-item">
          <?= fileIcon($d['original_name']) ?>
          <div class="file-item-info">
            <div class="file-item-name"><?= htmlspecialchars($d['original_name']) ?></div>
            <div class="file-item-size"><?= round($d['file_size']/1024,1) ?> KB · <?= date('d/m/Y H:i',strtotime($d['created_at'])) ?></div>
          </div>
          <a href="/assets/uploads/documents/<?= htmlspecialchars($d['filename']) ?>" target="_blank" class="btn btn-xs btn-primary"><i class="fas fa-external-link-alt"></i> Lihat</a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Customs & Quarantine Details -->
    <?php if($cust): ?>
    <div class="card fade-in fade-in-3">
      <div class="card-header"><div class="card-title"><i class="fas fa-passport"></i> Customs — Bea Cukai</div><?= badge($cust['status']) ?></div>
      <div class="info-row"><div class="info-lbl">No Dokumen</div><div class="info-val"><strong><?= htmlspecialchars($cust['no_dokumen']??'-') ?></strong></div></div>
      <div class="info-row"><div class="info-lbl">Petugas</div><div class="info-val"><?= htmlspecialchars($cust['pname']??'-') ?></div></div>
      <?php if($cust['catatan']): ?><div class="info-row"><div class="info-lbl">Catatan</div><div class="info-val"><?= htmlspecialchars($cust['catatan']) ?></div></div><?php endif; ?>
      <div class="info-row"><div class="info-lbl">Tanggal</div><div class="info-val"><?= date('d/m/Y H:i',strtotime($cust['created_at'])) ?></div></div>
    </div>
    <?php endif; ?>

    <?php if($quar): ?>
    <div class="card fade-in fade-in-4">
      <div class="card-header"><div class="card-title"><i class="fas fa-shield-virus"></i> Quarantine — Karantina</div><?= badge($quar['status']) ?></div>
      <div class="info-row"><div class="info-lbl">No Sertifikat</div><div class="info-val"><strong><?= htmlspecialchars($quar['no_sertifikat']??'-') ?></strong></div></div>
      <div class="info-row"><div class="info-lbl">Petugas</div><div class="info-val"><?= htmlspecialchars($quar['pname']??'-') ?></div></div>
      <?php if($quar['catatan']): ?><div class="info-row"><div class="info-lbl">Catatan</div><div class="info-val"><?= htmlspecialchars($quar['catatan']) ?></div></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- RIGHT -->
  <div>
    <!-- Invoice -->
    <?php if($inv): ?>
    <div class="card fade-in fade-in-1">
      <div class="card-header"><div class="card-title"><i class="fas fa-file-invoice-dollar"></i> Invoice</div><?= badge($inv['status']) ?></div>
      <?php if($inv['status']==='unpaid'): ?>
      <div class="va-card" style="margin-bottom:14px">
        <div class="va-bank"><?= htmlspecialchars($inv['payment_bank']) ?></div>
        <div class="va-label">Virtual Account Number</div>
        <div class="va-number"><?= implode(' ',str_split($inv['payment_va_number'],4)) ?></div>
        <div class="va-amount"><div class="label">Total Tagihan (incl. PPN 12%)</div><div class="value"><?= fmtRp($inv['total_amount']) ?></div></div>
        <div class="va-expire"><i class="fas fa-calendar-times"></i> Jatuh Tempo: <?= fmtDate($inv['due_date']) ?></div>
      </div>
      <?php endif; ?>
      <div class="info-row"><div class="info-lbl">Invoice No</div><div class="info-val"><strong style="font-family:var(--font-mono)"><?= htmlspecialchars($inv['invoice_no']) ?></strong></div></div>
      <div class="info-row"><div class="info-lbl">Subtotal</div><div class="info-val"><?= fmtRp($inv['subtotal']) ?></div></div>
      <div class="info-row"><div class="info-lbl">PPN 12%</div><div class="info-val"><?= fmtRp($inv['tax']) ?></div></div>
      <div class="info-row"><div class="info-lbl">Total</div><div class="info-val"><strong style="font-size:16px;color:var(--primary)"><?= fmtRp($inv['total_amount']) ?></strong></div></div>
      <?php if($inv['paid_at']): ?><div class="info-row"><div class="info-lbl">Dibayar</div><div class="info-val" style="color:var(--success)"><i class="fas fa-check-circle"></i> <?= date('d/m/Y H:i',strtotime($inv['paid_at'])) ?></div></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Approval Log Timeline -->
    <div class="card fade-in fade-in-2">
      <div class="card-header"><div class="card-title"><i class="fas fa-history"></i> Riwayat Aktivitas</div></div>
      <?php if(empty($alog)): ?>
      <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px">Belum ada aktivitas</div>
      <?php else: ?>
      <div class="timeline">
        <?php foreach(array_reverse($alog) as $al): ?>
        <div class="tl-item <?= $al['action']==='approve'?'done':($al['action']==='reject'?'rejected':'current') ?>">
          <div class="tl-title"><?= htmlspecialchars($al['full_name']??'System') ?> — <?= $al['action']==='approve'?'<span style="color:var(--success)">Approved</span>':'<span style="color:var(--danger)">Rejected</span>' ?></div>
          <?php if($al['catatan']): ?><div class="tl-sub"><?= htmlspecialchars($al['catatan']) ?></div><?php endif; ?>
          <div class="tl-sub"><?= date('d/m/Y H:i',strtotime($al['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/layout_footer.php'; ?>

<?php
session_start();
require_once '../includes/config.php';
requireRole('user');

$pageTitle='Status Booking Saya'; $activePage='my_bookings';
$db=getDB(); $u=user();

$bookings=$db->prepare("SELECT b.*,i.id as inv_id,i.invoice_no,i.total_amount,i.status as inv_status,i.payment_bank,i.payment_va_number,i.due_date FROM bookings b LEFT JOIN invoices i ON b.id=i.booking_id WHERE b.user_id=? ORDER BY b.created_at DESC");
$bookings->execute([$u['id']]); $bookings=$bookings->fetchAll();

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-box-open" style="color:var(--primary)"></i> Status Booking Saya</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Status Booking</span></div>
    </div>
    <a href="new_booking.php" class="btn btn-primary"><i class="fas fa-plus"></i> Booking Baru</a>
  </div>
</div>

<!-- SUMMARY STATS -->
<?php
$total=count($bookings);
$pending=count(array_filter($bookings,fn($b)=>$b['status']==='pending'));
$inProgress=count(array_filter($bookings,fn($b)=>in_array($b['status'],['approved','customs','quarantine'])));
$done=count(array_filter($bookings,fn($b)=>$b['status']==='completed'));
$unpaidInv=count(array_filter($bookings,fn($b)=>($b['inv_status']??'')==='unpaid'));
?>
<div class="stat-grid fade-in fade-in-1" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
  <div class="stat-card blue"><div class="stat-icon" style="background:var(--info-bg);color:var(--info)"><i class="fas fa-clipboard-list"></i></div><div class="stat-info"><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total Booking</div></div></div>
  <div class="stat-card orange"><div class="stat-icon" style="background:var(--warning-bg);color:var(--warning)"><i class="fas fa-clock"></i></div><div class="stat-info"><div class="stat-val"><?= $pending ?></div><div class="stat-lbl">Menunggu Review</div></div></div>
  <div class="stat-card purple"><div class="stat-icon" style="background:var(--purple-bg);color:var(--purple)"><i class="fas fa-spinner"></i></div><div class="stat-info"><div class="stat-val"><?= $inProgress ?></div><div class="stat-lbl">Sedang Diproses</div></div></div>
  <div class="stat-card green"><div class="stat-icon" style="background:var(--success-bg);color:var(--success)"><i class="fas fa-trophy"></i></div><div class="stat-info"><div class="stat-val"><?= $done ?></div><div class="stat-lbl">Selesai</div></div></div>
  <?php if($unpaidInv>0): ?>
  <div class="stat-card red"><div class="stat-icon" style="background:var(--danger-bg);color:var(--danger)"><i class="fas fa-file-invoice-dollar"></i></div><div class="stat-info"><div class="stat-val"><?= $unpaidInv ?></div><div class="stat-lbl">Tagihan Belum Bayar</div></div></div>
  <?php endif; ?>
</div>

<!-- BOOKING CARDS -->
<?php if(empty($bookings)): ?>
<div class="card fade-in" style="text-align:center;padding:60px 20px">
  <i class="fas fa-box-open" style="font-size:48px;color:var(--text-muted);display:block;margin-bottom:12px"></i>
  <div style="font-size:16px;font-weight:700;color:var(--text-secondary)">Belum Ada Booking</div>
  <div style="font-size:13px;color:var(--text-muted);margin:8px 0 20px">Buat booking pertama Anda sekarang</div>
  <a href="new_booking.php" class="btn btn-primary"><i class="fas fa-plus"></i> Booking Sekarang</a>
</div>
<?php endif; ?>

<?php foreach($bookings as $idx=>$b):
$stepsMap=['pending'=>0,'approved'=>1,'customs'=>2,'quarantine'=>3,'completed'=>4];
$curStep=isset($stepsMap[$b['status']])?$stepsMap[$b['status']]:-1;
$isRejected=$b['status']==='rejected';

// Get docs
$docs=$db->prepare("SELECT * FROM booking_documents WHERE booking_id=? ORDER BY created_at");
$docs->execute([$b['id']]); $docs=$docs->fetchAll();

// Get approvals log
$alog=$db->prepare("SELECT a.*,u.full_name FROM approvals a LEFT JOIN users u ON a.approved_by=u.id WHERE a.booking_id=? ORDER BY a.created_at");
$alog->execute([$b['id']]); $alog=$alog->fetchAll();
?>
<div class="card fade-in fade-in-<?= min($idx+1,6) ?>" style="border-left:4px solid <?= $isRejected?'var(--danger)':($b['status']==='completed'?'var(--success)':($b['status']==='pending'?'var(--warning)':'var(--primary)')) ?>">
  <!-- Header -->
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:18px;font-weight:800;color:var(--primary);font-family:var(--font-mono)"><?= htmlspecialchars($b['booking_no']) ?></span>
        <?= badge($b['status']) ?>
        <?php if($b['inv_status']): echo badge($b['inv_status']); endif; ?>
      </div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:3px">Dibuat: <?= date('d M Y H:i',strtotime($b['created_at'])) ?></div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> Detail</a>
      <?php if($b['status']==='rejected'): ?>
      <a href="new_booking.php" class="btn btn-primary btn-sm"><i class="fas fa-redo"></i> Booking Ulang</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Step Progress Bar -->
  <?php if(!$isRejected): ?>
  <div class="step-bar" style="margin-bottom:18px">
    <?php $steps=[['Booking','fa-file-alt'],['Approved','fa-check-double'],['Customs','fa-passport'],['Karantina','fa-shield-virus'],['Selesai','fa-trophy']];
    foreach($steps as $si=>$st): $isDone=$curStep>$si; $isCur=$curStep===$si; ?>
    <div class="step-item <?= $isDone?'done':($isCur?'active':'') ?>" style="min-width:54px">
      <div class="step-circle <?= $isDone?'done':($isCur?'active':'pending') ?>">
        <?php if($isDone) echo '<i class="fas fa-check" style="font-size:12px"></i>'; else echo '<i class="fas '.$st[1].'" style="font-size:12px"></i>'; ?>
      </div>
      <div class="step-lbl"><?= $st[0] ?></div>
    </div>
    <?php if($si<count($steps)-1): ?>
    <div class="step-line <?= $isDone?'done':'' ?>"></div>
    <?php endif; endforeach; ?>
  </div>
  <?php else: ?>
  <div class="alert alert-danger" style="margin-bottom:16px">
    <i class="fas fa-times-circle"></i>
    <div><strong>Booking Ditolak</strong>
    <?php $lastRej=end($alog); if($lastRej&&$lastRej['action']==='reject') echo ' — '.htmlspecialchars($lastRej['catatan']); ?></div>
  </div>
  <?php endif; ?>

  <!-- Info Grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;padding:14px;background:var(--surface-2);border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px">
    <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Jenis Kulit</div><div style="font-weight:600;margin-top:2px"><?= htmlspecialchars($b['jenis_kulit']) ?></div></div>
    <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Berat</div><div style="font-weight:600;margin-top:2px"><?= number_format($b['berat']) ?> kg</div></div>
    <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Kontainer</div><div style="font-weight:600;margin-top:2px"><?= htmlspecialchars($b['ukuran_kontainer']) ?> ×<?= $b['jumlah_kontainer'] ?></div></div>
    <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Tujuan</div><div style="font-weight:600;margin-top:2px"><?= htmlspecialchars($b['tujuan_negara']??'-') ?></div></div>
    <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Tanggal Kirim</div><div style="font-weight:600;margin-top:2px"><?= fmtDate($b['tanggal_kirim']) ?></div></div>
  </div>

  <!-- Docs uploaded -->
  <?php if(!empty($docs)): ?>
  <div style="margin-bottom:14px">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:8px"><i class="fas fa-paperclip"></i> Dokumen Terupload (<?= count($docs) ?>)</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach($docs as $d): ?>
      <a href="/assets/uploads/documents/<?= htmlspecialchars($d['filename']) ?>" target="_blank" style="display:flex;align-items:center;gap:6px;padding:6px 12px;background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-weight:600;color:var(--text-primary);text-decoration:none">
        <?= fileIcon($d['original_name']) ?> <?= htmlspecialchars($d['original_name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- INVOICE CARD (shown when invoice exists) -->
  <?php if($b['inv_id'] && $b['inv_status']): ?>
  <div style="border-top:1px solid var(--border);padding-top:14px">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--primary);margin-bottom:12px"><i class="fas fa-file-invoice-dollar"></i> Tagihan Invoice</div>
    <?php if($b['inv_status']==='unpaid'): ?>
    <div class="va-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div>
          <div style="font-size:10px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:1px">Invoice</div>
          <div style="font-size:15px;font-weight:800;color:#fff"><?= htmlspecialchars($b['invoice_no']) ?></div>
        </div>
        <span class="badge" style="background:rgba(239,68,68,0.2);color:#fca5a5;border:1px solid rgba(239,68,68,0.3)">BELUM BAYAR</span>
      </div>
      <div class="va-label">Nomor Virtual Account — <?= htmlspecialchars($b['payment_bank']) ?></div>
      <div class="va-number"><?= chunk_split(htmlspecialchars($b['payment_va_number']),4,' ') ?></div>
      <div class="va-amount">
        <div class="label">Total Tagihan</div>
        <div class="value"><?= fmtRp($b['total_amount']) ?></div>
      </div>
      <div class="va-expire"><i class="fas fa-calendar-times"></i> Jatuh tempo: <?= fmtDate($b['due_date']) ?></div>
      <div style="margin-top:14px;padding:10px;background:rgba(255,255,255,0.06);border-radius:8px;font-size:11px;color:rgba(255,255,255,0.6)">
        <strong style="color:#fff">Cara Bayar via ATM <?= htmlspecialchars($b['payment_bank']) ?>:</strong><br>
        Transfer → Ke Rekening Lain → Masukkan nomor VA → Konfirmasi pembayaran
      </div>
    </div>
    <?php else: ?>
    <div style="display:flex;align-items:center;gap:12px;padding:14px;background:var(--success-bg);border:1px solid var(--success-border);border-radius:var(--radius-sm)">
      <i class="fas fa-check-circle" style="font-size:24px;color:var(--success)"></i>
      <div style="flex:1"><div style="font-weight:700;color:var(--success)"><?= htmlspecialchars($b['invoice_no']) ?> — LUNAS</div>
      <div style="font-size:12px;color:var(--text-secondary)"><?= fmtRp($b['total_amount']) ?></div></div>
    </div>
    <?php if($b['status']==='completed'): ?>
    <div style="margin-top:10px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:2px solid #22c55e;border-radius:var(--radius-sm);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div style="display:flex;align-items:center;gap:10px">
        <i class="fas fa-certificate" style="font-size:22px;color:#059669"></i>
        <div>
          <div style="font-weight:800;color:#059669;font-size:13px">🎉 Surat Keterangan Ekspor Tersedia!</div>
          <div style="font-size:11px;color:#166534">Booking selesai — SKE siap diunduh sebagai PDF resmi</div>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/pages/export_clearance.php?bid=<?= $b['id'] ?>" target="_blank" class="btn btn-success btn-sm" style="flex-shrink:0"><i class="fas fa-download"></i> Download SKE</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php include '../includes/layout_footer.php'; ?>

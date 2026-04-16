<?php
session_start();
require_once '../includes/config.php';
requireLogin();
$db=getDB(); $u=user();

$bid=(int)($_GET['bid']??0);
if(!$bid){header('Location: dashboard.php');exit;}

// Get booking
$bk=$db->prepare("SELECT b.*,u.full_name as pic_name,u.email as pic_email,u.phone as pic_phone FROM bookings b LEFT JOIN users u ON b.user_id=u.id WHERE b.id=?");
$bk->execute([$bid]); $bk=$bk->fetch();
if(!$bk){echo '<p style="padding:40px;text-align:center">Booking tidak ditemukan.</p>';exit;}

// Access control: admin/operator can view all; user PT only their own
if(isRole('user') && $bk['user_id']!==uid()){http_response_code(403);echo '<p style="padding:40px;text-align:center">Akses ditolak.</p>';exit;}

// Must be completed
if($bk['status']!=='completed' && !isRole('admin','operator')){
    header('Location: my_bookings.php');exit;
}

// Get invoice (paid)
$inv=$db->prepare("SELECT * FROM invoices WHERE booking_id=? AND status='paid' ORDER BY id DESC LIMIT 1");
$inv->execute([$bid]); $inv=$inv->fetch();

// Get customs
$cu=$db->prepare("SELECT c.*,u.full_name as pname FROM customs c LEFT JOIN users u ON c.petugas_id=u.id WHERE c.booking_id=? AND c.status='clear' LIMIT 1");
$cu->execute([$bid]); $cu=$cu->fetch();

// Get quarantine
$qa=$db->prepare("SELECT q.*,u.full_name as pname FROM quarantine q LEFT JOIN users u ON q.petugas_id=u.id WHERE q.booking_id=? AND q.status='clear' LIMIT 1");
$qa->execute([$bid]); $qa=$qa->fetch();

// Get approval
$ap=$db->prepare("SELECT a.*,u.full_name as aname FROM approvals a LEFT JOIN users u ON a.approved_by=u.id WHERE a.booking_id=? AND a.action='approve' ORDER BY a.id DESC LIMIT 1");
$ap->execute([$bid]); $ap=$ap->fetch();

$certNo='SKE-'.date('Y').'-'.str_pad($bid,5,'0',STR_PAD_LEFT);
$issuedDate=$inv?($inv['paid_at']?date('Y-m-d',strtotime($inv['paid_at'])):date('Y-m-d')):date('Y-m-d');
$validUntil=date('Y-m-d',strtotime($issuedDate.'+7 days'));

$pageTitle='Surat Keterangan Ekspor';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SKE — <?= htmlspecialchars($certNo) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Times New Roman',Times,serif;background:#e8edf5;color:#1a1a2e;min-height:100vh}
.toolbar{position:fixed;top:0;left:0;right:0;background:#0f1d3a;padding:10px 24px;display:flex;align-items:center;gap:12px;z-index:999;box-shadow:0 2px 12px rgba(0,0,0,0.3)}
.toolbar h4{color:#fff;font-family:sans-serif;font-size:14px;font-weight:700;flex:1}
.btn-tool{padding:7px 16px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;border:none;display:flex;align-items:center;gap:6px;font-family:sans-serif;transition:all 0.15s}
.btn-dl{background:#059669;color:#fff}.btn-dl:hover{background:#047857}
.btn-print{background:#1d4ed8;color:#fff}.btn-print:hover{background:#1e40af}
.btn-back{background:rgba(255,255,255,0.1);color:#94b3d9;border:1px solid rgba(255,255,255,0.15)}.btn-back:hover{background:rgba(255,255,255,0.18);color:#fff}
.page-wrap{padding:70px 20px 40px;display:flex;justify-content:center}

/* F4 paper: 215mm x 330mm */
.ske-doc{width:215mm;min-height:330mm;background:#fff;box-shadow:0 4px 40px rgba(0,0,0,0.2);padding:16mm 18mm 14mm;position:relative;overflow:hidden}
.ske-doc::before{content:'TERMINAL PETIKEMAS SURABAYA';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-35deg);font-size:52px;font-weight:900;color:rgba(0,91,170,0.04);white-space:nowrap;pointer-events:none;font-family:'Arial Black',Arial,sans-serif;letter-spacing:2px}

/* Header */
.doc-header{display:flex;align-items:flex-start;gap:18px;padding-bottom:12px;border-bottom:3px double #003d7a;margin-bottom:12px}
.doc-logo{width:60px;height:60px;background:linear-gradient(135deg,#003d7a,#005baa);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;font-weight:900;text-align:center;line-height:1.3;flex-shrink:0;font-family:Arial,sans-serif;letter-spacing:0.5px;padding:6px}
.doc-org{flex:1}
.doc-org h1{font-size:16px;font-weight:900;color:#003d7a;letter-spacing:0.5px;font-family:Arial,sans-serif}
.doc-org p{font-size:9px;color:#4a5568;margin-top:2px;font-family:Arial,sans-serif}
.doc-cert-box{text-align:right;font-family:Arial,sans-serif}
.doc-cert-box .cert-no{font-size:11px;font-weight:700;color:#003d7a;letter-spacing:0.5px}
.doc-cert-box .cert-status{margin-top:4px;display:inline-block;padding:3px 10px;background:#059669;color:#fff;border-radius:4px;font-size:10px;font-weight:700;font-family:Arial,sans-serif}

/* Title */
.doc-title{text-align:center;margin:10px 0 12px}
.doc-title h2{font-size:15px;font-weight:900;color:#003d7a;text-transform:uppercase;letter-spacing:2px;border-bottom:1.5px solid #003d7a;display:inline-block;padding-bottom:3px}
.doc-title p{font-size:9.5px;color:#64748b;margin-top:4px;font-style:italic}

/* Section blocks */
.section-title{font-size:10px;font-weight:700;color:#003d7a;text-transform:uppercase;letter-spacing:1px;border-left:3px solid #003d7a;padding-left:7px;margin:10px 0 7px;font-family:Arial,sans-serif}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.info-row{display:flex;padding:3px 6px;border-bottom:1px dotted #e2e8f0;font-size:9.5px}
.info-row .lbl{width:130px;color:#64748b;flex-shrink:0}
.info-row .val{font-weight:700;color:#1a1a2e;flex:1}
.info-grid .info-row .lbl{width:110px}

/* Approval chain */
.approval-chain{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:10px 0}
.apv-box{border:1.5px solid #e2e8f0;border-radius:6px;padding:8px 6px;text-align:center;background:#f8fafc}
.apv-box.done{border-color:#059669;background:#f0fdf4}
.apv-box .apv-icon{font-size:18px;margin-bottom:3px}
.apv-box .apv-label{font-size:9px;font-weight:700;color:#374151;font-family:Arial,sans-serif;text-transform:uppercase;letter-spacing:0.5px}
.apv-box .apv-status{font-size:8px;color:#059669;font-weight:700;margin-top:2px;font-family:Arial,sans-serif}
.apv-box .apv-name{font-size:8px;color:#64748b;margin-top:1px}
.apv-box .apv-date{font-size:8px;color:#9ca3af}

/* Payment proof */
.payment-table{width:100%;border-collapse:collapse;font-size:9.5px;margin:6px 0}
.payment-table td{padding:4px 8px;border:1px solid #e2e8f0}
.payment-table .head-row td{background:#003d7a;color:#fff;font-weight:700;font-family:Arial,sans-serif}
.payment-table .total-row td{background:#f0fdf4;font-weight:700;color:#059669;font-size:11px}

/* Validity */
.validity-box{background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1.5px solid #93c5fd;border-radius:6px;padding:8px 12px;margin:8px 0;display:flex;align-items:center;gap:12px}
.validity-box i{font-size:20px;color:#1d4ed8}
.validity-box .dates{font-size:9.5px}
.validity-box .dates strong{color:#1d4ed8;font-weight:700}

/* Declaration */
.declaration{background:#fff9f0;border:1.5px solid #fed7aa;border-radius:6px;padding:9px 12px;margin:8px 0;font-size:9.5px;color:#431407;line-height:1.5;font-style:italic}

/* Signatures */
.sig-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:14px}
.sig-box{text-align:center;font-size:9px;font-family:Arial,sans-serif}
.sig-box .sig-title{font-weight:700;color:#003d7a;font-size:9.5px;margin-bottom:30px;border-bottom:1px solid #e2e8f0;padding-bottom:4px}
.sig-box .sig-line{border-bottom:1.5px solid #1a1a2e;margin:0 8px 4px}
.sig-box .sig-name{font-weight:700;color:#1a1a2e;font-size:9.5px}
.sig-box .sig-pos{color:#64748b;font-size:8.5px}
.stamp-area{position:relative;display:inline-block;margin-top:-20px}
.stamp-circle{width:70px;height:70px;border-radius:50%;border:3px double #003d7a;display:flex;align-items:center;justify-content:center;text-align:center;font-size:6px;font-weight:900;color:#003d7a;line-height:1.3;padding:4px;font-family:Arial,sans-serif;background:rgba(0,61,122,0.04);margin:0 auto}

/* Footer */
.doc-footer{margin-top:14px;padding-top:8px;border-top:1px solid #e2e8f0;text-align:center;font-size:8px;color:#9ca3af;font-family:Arial,sans-serif}
.cleared-banner{background:linear-gradient(135deg,#059669,#047857);color:#fff;text-align:center;padding:6px;border-radius:6px;margin:8px 0;font-size:10px;font-weight:700;letter-spacing:1px;font-family:Arial,sans-serif}

@media print {
  .toolbar{display:none!important}
  .page-wrap{padding:0}
  body{background:#fff}
  .ske-doc{box-shadow:none;width:215mm;min-height:330mm}
}
</style>
</head>
<body>

<div class="toolbar">
  <a href="<?= isRole('user')?BASE_URL.'/pages/my_bookings.php':BASE_URL.'/pages/invoices.php' ?>" class="btn-tool btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
  <h4>Surat Keterangan Ekspor — <?= htmlspecialchars($certNo) ?></h4>
  <?php if(isRole('admin','operator')): ?>
  <button class="btn-tool btn-back" onclick="openEditModal()"><i class="fas fa-edit"></i> Edit SKE</button>
  <?php endif; ?>
  <button class="btn-tool btn-print" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
  <button class="btn-tool btn-dl" onclick="downloadPDF()"><i class="fas fa-download"></i> Download PDF (F4)</button>
</div>

<div class="page-wrap">
<div class="ske-doc" id="skeDoc">

  <!-- HEADER -->
  <div class="doc-header">
    <div class="doc-logo">PT<br>TPS<br><span style="font-size:7px">TERMINAL<br>PETIKEMAS<br>SURABAYA</span></div>
    <div class="doc-org">
      <h1>PT Terminal Petikemas Surabaya</h1>
      <p>Jl. Tanjung Mutiara No.1, Pelabuhan Tanjung Perak, Surabaya 60177, Jawa Timur</p>
      <p>Telp: (031) 3291234 · Fax: (031) 3291235 · Email: info@tps.co.id</p>
      <p style="margin-top:2px;font-weight:700;color:#003d7a">Divisi Ekspor Lini 2 — Komoditas Kulit Garaman</p>
    </div>
    <div class="doc-cert-box">
      <div class="cert-no">No: <?= htmlspecialchars($certNo) ?></div>
      <div class="cert-status">✅ CLEARED &amp; PAID</div>
    </div>
  </div>

  <!-- TITLE -->
  <div class="doc-title">
    <h2>Surat Keterangan Ekspor</h2>
    <p>Export Clearance Certificate — Komoditas Kulit Hewan Garaman Lini 2</p>
  </div>

  <div class="cleared-banner">🛳 KOMODITAS TELAH MEMENUHI SEMUA PERSYARATAN EKSPOR DAN SIAP DIKIRIM KE TERMINAL TPS LINI 2</div>

  <!-- IDENTITAS PT -->
  <div class="section-title">Identitas Perusahaan Eksportir</div>
  <div class="info-grid">
    <div>
      <div class="info-row"><span class="lbl">Nama Perusahaan</span><span class="val"><?= htmlspecialchars($bk['nama_pt']) ?></span></div>
      <div class="info-row"><span class="lbl">Penanggungjawab</span><span class="val"><?= htmlspecialchars($bk['pic_name']??'—') ?></span></div>
      <div class="info-row"><span class="lbl">Email</span><span class="val"><?= htmlspecialchars($bk['pic_email']??'—') ?></span></div>
    </div>
    <div>
      <div class="info-row"><span class="lbl">No. Booking</span><span class="val" style="color:#003d7a;font-family:monospace"><?= htmlspecialchars($bk['booking_no']) ?></span></div>
      <div class="info-row"><span class="lbl">No. Invoice</span><span class="val" style="font-family:monospace"><?= htmlspecialchars($inv['invoice_no']??'—') ?></span></div>
      <div class="info-row"><span class="lbl">No. Telepon</span><span class="val"><?= htmlspecialchars($bk['pic_phone']??'—') ?></span></div>
    </div>
  </div>

  <!-- DETAIL KOMODITAS -->
  <div class="section-title">Detail Komoditas & Pengiriman</div>
  <div class="info-grid">
    <div>
      <div class="info-row"><span class="lbl">Jenis Kulit</span><span class="val"><?= htmlspecialchars($bk['jenis_kulit']) ?></span></div>
      <div class="info-row"><span class="lbl">Berat Total</span><span class="val"><?= number_format($bk['berat']) ?> Kilogram</span></div>
      <div class="info-row"><span class="lbl">Kontainer</span><span class="val"><?= htmlspecialchars($bk['ukuran_kontainer']) ?> × <?= $bk['jumlah_kontainer'] ?> unit</span></div>
      <div class="info-row"><span class="lbl">Tanggal Kirim</span><span class="val"><?= fmtDate($bk['tanggal_kirim']) ?></span></div>
    </div>
    <div>
      <div class="info-row"><span class="lbl">Asal Negara</span><span class="val"><?= htmlspecialchars($bk['asal_negara']??'Indonesia') ?></span></div>
      <div class="info-row"><span class="lbl">Negara Tujuan</span><span class="val"><?= htmlspecialchars($bk['tujuan_negara']??'—') ?></span></div>
      <div class="info-row"><span class="lbl">No. Dok Customs</span><span class="val" style="font-family:monospace"><?= htmlspecialchars($cu['no_dokumen']??'—') ?></span></div>
      <div class="info-row"><span class="lbl">No. Sertifikat QC</span><span class="val" style="font-family:monospace"><?= htmlspecialchars($qa['no_sertifikat']??'—') ?></span></div>
    </div>
  </div>

  <!-- APPROVAL CHAIN -->
  <div class="section-title">Rantai Persetujuan</div>
  <div class="approval-chain">
    <div class="apv-box done">
      <div class="apv-icon">✅</div>
      <div class="apv-label">Admin TPS</div>
      <div class="apv-status">APPROVED</div>
      <div class="apv-name"><?= htmlspecialchars($ap['aname']??'Administrator TPS') ?></div>
      <div class="apv-date"><?= $ap?date('d/m/Y',strtotime($ap['created_at'])):'-' ?></div>
    </div>
    <div class="apv-box done">
      <div class="apv-icon">🛂</div>
      <div class="apv-label">Bea Cukai</div>
      <div class="apv-status">CLEARED</div>
      <div class="apv-name"><?= htmlspecialchars($cu['pname']??'Petugas Bea Cukai') ?></div>
      <div class="apv-date"><?= $cu?date('d/m/Y',strtotime($cu['created_at'])):'-' ?></div>
    </div>
    <div class="apv-box done">
      <div class="apv-icon">🛡️</div>
      <div class="apv-label">Karantina</div>
      <div class="apv-status">CLEARED</div>
      <div class="apv-name"><?= htmlspecialchars($qa['pname']??'Petugas Karantina') ?></div>
      <div class="apv-date"><?= $qa?date('d/m/Y',strtotime($qa['created_at'])):'-' ?></div>
    </div>
    <div class="apv-box done">
      <div class="apv-icon">💳</div>
      <div class="apv-label">Pembayaran</div>
      <div class="apv-status">PAID</div>
      <div class="apv-name">Bank <?= htmlspecialchars($inv['payment_bank']??'—') ?></div>
      <div class="apv-date"><?= $inv&&$inv['paid_at']?date('d/m/Y',strtotime($inv['paid_at'])):'-' ?></div>
    </div>
  </div>

  <!-- BUKTI PEMBAYARAN -->
  <div class="section-title">Bukti Pembayaran</div>
  <table class="payment-table">
    <tr class="head-row"><td>Keterangan</td><td style="text-align:right">Jumlah</td></tr>
    <tr><td>Biaya Komoditas (<?= htmlspecialchars($bk['jenis_kulit']) ?> × <?= number_format($bk['berat']) ?> kg)</td><td style="text-align:right"><?= fmtRp($inv['subtotal']??0) ?></td></tr>
    <tr><td>PPN 12%</td><td style="text-align:right"><?= fmtRp($inv['tax']??0) ?></td></tr>
    <tr class="total-row"><td><strong>TOTAL PEMBAYARAN</strong></td><td style="text-align:right"><strong><?= fmtRp($inv['total_amount']??0) ?></strong></td></tr>
    <tr><td>Metode Pembayaran</td><td style="text-align:right">Virtual Account <?= htmlspecialchars($inv['payment_bank']??'—') ?> · <?= htmlspecialchars($inv['payment_va_number']??'—') ?></td></tr>
    <tr><td>Tanggal Pembayaran</td><td style="text-align:right"><?= $inv&&$inv['paid_at']?date('d F Y H:i',strtotime($inv['paid_at'])):'-' ?> WIB</td></tr>
  </table>

  <!-- VALIDITY -->
  <div class="validity-box">
    <i class="fas fa-calendar-check"></i>
    <div class="dates">
      <div>Tanggal Terbit: <strong><?= date('d F Y', strtotime($issuedDate)) ?></strong></div>
      <div>Berlaku Sampai: <strong><?= date('d F Y', strtotime($validUntil)) ?></strong> (7 hari kalender)</div>
      <div style="color:#dc2626;font-size:8.5px;margin-top:2px">⚠ Surat ini tidak berlaku setelah tanggal <?= date('d F Y', strtotime($validUntil)) ?></div>
    </div>
  </div>

  <!-- DECLARATION -->
  <div class="declaration">
    Dengan diterbitkannya Surat Keterangan Ekspor ini, menyatakan bahwa komoditas kulit hewan garaman
    dengan nomor booking <strong><?= htmlspecialchars($bk['booking_no']) ?></strong> atas nama
    <strong><?= htmlspecialchars($bk['nama_pt']) ?></strong> telah <strong>memenuhi seluruh persyaratan
    administrasi, kepabeanan, dan karantina</strong> yang berlaku, serta dinyatakan bebas dan sah untuk
    dikirim ke Terminal Petikemas Surabaya Lini 2 guna proses pengapalan ke
    <strong><?= htmlspecialchars($bk['tujuan_negara']??'negara tujuan') ?></strong>.
  </div>

  <!-- SIGNATURES -->
  <div class="sig-row">
    <div class="sig-box">
      <div class="sig-title">Penanggungjawab PT</div>
      <div class="sig-line"></div>
      <div class="sig-name"><?= htmlspecialchars($bk['pic_name']??'...') ?></div>
      <div class="sig-pos"><?= htmlspecialchars($bk['nama_pt']) ?></div>
    </div>
    <div class="sig-box">
      <div class="sig-title">Petugas Bea Cukai / Karantina</div>
      <div class="sig-line"></div>
      <div class="sig-name"><?= htmlspecialchars($cu['pname']??'Siti Rahayu') ?></div>
      <div class="sig-pos">Bea & Cukai Surabaya / Karantina Pertanian</div>
    </div>
    <div class="sig-box">
      <div class="sig-title">Operator TPS</div>
      <div class="stamp-area">
        <div class="stamp-circle">PT TERMINAL<br>PETIKEMAS<br>SURABAYA<br>⚓<br>LINI 2</div>
      </div>
      <div class="sig-line"></div>
      <div class="sig-name">Administrator TPS</div>
      <div class="sig-pos">PT Terminal Petikemas Surabaya</div>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="doc-footer">
    Dokumen ini diterbitkan secara digital oleh Sistem TPS Webaccess · <?= date('d/m/Y H:i') ?> WIB ·
    Berlaku tanpa tanda tangan basah sebagai dokumen resmi ekspor ·
    Nomor Sertifikat: <?= htmlspecialchars($certNo) ?>
  </div>

</div><!-- end ske-doc -->
</div><!-- end page-wrap -->

<?php if(isRole('admin','operator')): ?>
<!-- Edit Modal -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;display:none;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:500px;max-height:80vh;overflow-y:auto;font-family:Arial,sans-serif">
    <h3 style="margin-bottom:16px;color:#003d7a">Edit SKE — <?= htmlspecialchars($certNo) ?></h3>
    <p style="font-size:12px;color:#64748b;margin-bottom:16px">Perubahan akan diterapkan saat halaman di-reload. Untuk perubahan permanen, gunakan panel database.</p>
    <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
      <label>Nama PT <input id="ePT" value="<?= htmlspecialchars($bk['nama_pt']) ?>" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;margin-top:3px"></label>
      <label>Tanggal Kirim <input type="date" id="eTgl" value="<?= htmlspecialchars($bk['tanggal_kirim']) ?>" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;margin-top:3px"></label>
      <label>Negara Tujuan <input id="eDest" value="<?= htmlspecialchars($bk['tujuan_negara']??'') ?>" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;margin-top:3px"></label>
    </div>
    <div style="display:flex;gap:8px;margin-top:16px">
      <button onclick="applyEdit()" style="padding:8px 18px;background:#059669;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700">Terapkan (Preview)</button>
      <button onclick="closeEdit()" style="padding:8px 18px;background:#e2e8f0;color:#374151;border:none;border-radius:6px;cursor:pointer">Batal</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function downloadPDF(){
  const el=document.getElementById('skeDoc');
  const opt={
    margin:[5,5,5,5],
    filename:'SKE-<?= htmlspecialchars($bk['booking_no']) ?>.pdf',
    image:{type:'jpeg',quality:0.98},
    html2canvas:{scale:2,useCORS:true},
    jsPDF:{unit:'mm',format:[215,330],orientation:'portrait'}
  };
  html2pdf().set(opt).from(el).save();
}
<?php if(isRole('admin','operator')): ?>
function openEditModal(){document.getElementById('editModal').style.display='flex';}
function closeEdit(){document.getElementById('editModal').style.display='none';}
function applyEdit(){
  const pt=document.getElementById('ePT').value;
  const dest=document.getElementById('eDest').value;
  const rows=document.querySelectorAll('.info-row .val');
  rows.forEach(r=>{if(r.previousElementSibling?.innerText.includes('Nama Perusahaan'))r.innerText=pt;});
  rows.forEach(r=>{if(r.previousElementSibling?.innerText.includes('Negara Tujuan'))r.innerText=dest;});
  closeEdit();
}
<?php endif; ?>
</script>
</body>
</html>

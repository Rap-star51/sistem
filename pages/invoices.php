<?php
session_start();
require_once '../includes/config.php';
requireRole('admin','operator');
$pageTitle='Invoices'; $activePage='invoices';
$db=getDB(); $u=user();
$msg=''; $mt='';

$BANKS=['BCA','Mandiri','BNI','BRI','BSI','CIMB','Permata','Danamon'];
$VA_PREFIX=['BCA'=>'8808','Mandiri'=>'8889','BNI'=>'9889','BRI'=>'8882','BSI'=>'9347','CIMB'=>'7080','Permata'=>'8215','Danamon'=>'9900'];

if($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';

    if($action==='pay') {
        $iid=(int)($_POST['invoice_id']??0);
        if($iid) {
            $db->prepare("UPDATE invoices SET status='paid',paid_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$iid]);
            $inv=$db->prepare("SELECT i.*,b.user_id,b.booking_no,b.id as bid FROM invoices i JOIN bookings b ON i.booking_id=b.id WHERE i.id=?");
            $inv->execute([$iid]); $inv=$inv->fetch();
            if($inv) {
                // Auto-complete booking
                $db->prepare("UPDATE bookings SET status='completed',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$inv['bid']]);
                // Notify PT
                addNotif($inv['user_id'],'🎉 Pembayaran Dikonfirmasi & Booking Selesai',
                    "Invoice {$inv['invoice_no']} sebesar ".fmtRp($inv['total_amount'])." telah lunas. Surat Keterangan Ekspor tersedia.",'success','/tps-v2/pages/my_bookings.php');
                addNotif($inv['user_id'],'📄 Surat Keterangan Ekspor Tersedia',
                    "Booking {$inv['booking_no']} telah selesai. SKE dapat diunduh sekarang.",'success','/tps-v2/pages/my_bookings.php');
            }
            $msg='Invoice berhasil ditandai lunas. Booking otomatis selesai & notifikasi terkirim ke PT.'; $mt='success';
        }
    } elseif($action==='change_bank') {
        $iid=(int)($_POST['invoice_id']??0);
        $newBank=$_POST['bank']??'';
        if($iid && in_array($newBank,$BANKS)) {
            $prefix=$VA_PREFIX[$newBank];
            $vaNum=$prefix.'0'.str_pad((string)(rand(1000000,9999999)),7,'0',STR_PAD_LEFT);
            $db->prepare("UPDATE invoices SET payment_bank=?,payment_va_number=? WHERE id=?")->execute([$newBank,$vaNum,$iid]);
            $inv=$db->prepare("SELECT i.*,b.user_id,b.booking_no FROM invoices i JOIN bookings b ON i.booking_id=b.id WHERE i.id=?");
            $inv->execute([$iid]); $inv=$inv->fetch();
            if($inv) addNotif($inv['user_id'],'🏦 Nomor VA Diperbarui',
                "VA pembayaran {$inv['invoice_no']} diperbarui ke bank $newBank. No VA: $vaNum",'info','/tps-v2/pages/my_bookings.php');
            $msg="Virtual Account bank $newBank berhasil di-generate."; $mt='success';
        }
    }
}

$fs=trim($_GET['status']??''); $fb=trim($_GET['bank']??'');
$w=['1=1']; $p=[];
if($fs){$w[]="i.status=?";$p[]=$fs;}
if($fb){$w[]="i.payment_bank=?";$p[]=$fb;}
$ws=implode(' AND ',$w);
$stmt=$db->prepare("SELECT i.*,b.booking_no,b.nama_pt,b.jenis_kulit,b.berat,b.ukuran_kontainer,b.tujuan_negara,b.status as bk_status FROM invoices i JOIN bookings b ON i.booking_id=b.id WHERE $ws ORDER BY i.created_at DESC");
$stmt->execute($p); $list=$stmt->fetchAll();

$totPaid=(float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status='paid'")->fetchColumn();
$totUnpaid=(float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status='unpaid'")->fetchColumn();
$cntPaid=(int)$db->query("SELECT COUNT(*) FROM invoices WHERE status='paid'")->fetchColumn();
$cntUnpaid=(int)$db->query("SELECT COUNT(*) FROM invoices WHERE status='unpaid'")->fetchColumn();

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-file-invoice-dollar" style="color:var(--primary)"></i> Invoices</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Invoices</span></div>
    </div>
  </div>
</div>

<?php if($msg): ?><div class="alert alert-<?= $mt ?> fade-in"><i class="fas fa-<?= $mt==='success'?'check-circle':'exclamation-circle' ?>"></i><?= $msg ?></div><?php endif; ?>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card fade-in fade-in-1"><div class="stat-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-file-invoice"></i></div><div class="stat-info"><div class="stat-val"><?= $cntPaid+$cntUnpaid ?></div><div class="stat-lbl">Total Invoice</div></div></div>
  <div class="stat-card fade-in fade-in-2"><div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-val" style="font-size:17px"><?= fmtRp($totPaid) ?></div><div class="stat-lbl">Terkumpul (Lunas)</div></div></div>
  <div class="stat-card fade-in fade-in-3"><div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-hourglass-half"></i></div><div class="stat-info"><div class="stat-val" style="font-size:17px"><?= fmtRp($totUnpaid) ?></div><div class="stat-lbl">Belum Dibayar</div></div></div>
  <div class="stat-card fade-in fade-in-4"><div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-calendar-times"></i></div><div class="stat-info"><div class="stat-val"><?= $cntUnpaid ?></div><div class="stat-lbl">Invoice Pending</div></div></div>
</div>

<div class="card fade-in fade-in-2">
  <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Daftar Invoice</div></div>

  <form method="GET" style="margin-bottom:16px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:12px 14px;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border)">
      <select name="status" class="form-select" style="width:140px">
        <option value="">Semua Status</option>
        <option value="unpaid" <?= $fs==='unpaid'?'selected':'' ?>>Belum Bayar</option>
        <option value="paid" <?= $fs==='paid'?'selected':'' ?>>Lunas</option>
      </select>
      <select name="bank" class="form-select" style="width:130px">
        <option value="">Semua Bank</option>
        <?php foreach($BANKS as $bn): ?><option value="<?= $bn ?>" <?= $fb===$bn?'selected':'' ?>><?= $bn ?></option><?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
      <a href="invoices.php" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i></a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>No</th><th>Invoice No</th><th>Booking No</th><th>Perusahaan</th><th>Komoditas</th><th>Total</th><th>Bank/VA</th><th>Jatuh Tempo</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if(empty($list)): ?><tr><td colspan="10" style="text-align:center;padding:32px;color:var(--text-muted)">Tidak ada data</td></tr><?php endif; ?>
      <?php foreach($list as $i=>$inv):
        $overdue=$inv['status']==='unpaid'&&strtotime($inv['due_date'])<time();
      ?>
      <tr <?= $overdue?'style="background:rgba(220,38,38,0.04)"':'' ?>>
        <td style="color:var(--text-muted)"><?= $i+1 ?></td>
        <td><span style="font-weight:800;font-family:var(--font-mono);font-size:12px;color:var(--primary)"><?= htmlspecialchars($inv['invoice_no']) ?></span></td>
        <td><span style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($inv['booking_no']) ?></span></td>
        <td style="font-weight:600"><?= htmlspecialchars($inv['nama_pt']) ?></td>
        <td><?= htmlspecialchars($inv['jenis_kulit']) ?><br><span style="font-size:11px;color:var(--text-muted)"><?= number_format($inv['berat']) ?> kg</span></td>
        <td><strong style="color:var(--primary)"><?= fmtRp($inv['total_amount']) ?></strong></td>
        <td>
          <?php if($inv['payment_bank']): ?>
          <div style="font-weight:700;font-size:12px;display:flex;align-items:center;gap:5px">
            <span style="padding:2px 6px;background:var(--primary);color:#fff;border-radius:4px;font-size:10px"><?= htmlspecialchars($inv['payment_bank']) ?></span>
          </div>
          <div style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);margin-top:2px"><?= wordwrap(htmlspecialchars($inv['payment_va_number']??''),4,' ',true) ?></div>
          <?php else: ?><span style="color:var(--text-muted);font-size:12px">— Belum ada VA</span><?php endif; ?>
        </td>
        <td>
          <?= fmtDate($inv['due_date']) ?>
          <?php if($overdue): ?><br><span style="font-size:10px;font-weight:700;color:var(--danger)">OVERDUE</span><?php endif; ?>
        </td>
        <td><?= badge($inv['status']) ?></td>
        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
          <?php if($inv['status']==='unpaid'): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Tandai invoice ini sebagai LUNAS?\nBooking akan otomatis selesai.')">
              <input type="hidden" name="action" value="pay">
              <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
              <button type="submit" class="btn btn-success btn-xs"><i class="fas fa-check"></i> Lunas</button>
            </form>
            <button class="btn btn-xs btn-outline" onclick="openVAModal(<?= $inv['id'] ?>,'<?= htmlspecialchars(addslashes($inv['payment_bank']??'')) ?>','<?= htmlspecialchars(addslashes($inv['payment_va_number']??'')) ?>','<?= fmtRp($inv['total_amount']) ?>','<?= fmtDate($inv['due_date']) ?>')"><i class="fas fa-qrcode"></i></button>
            <button class="btn btn-xs btn-secondary" onclick="openBankModal(<?= $inv['id'] ?>)"><i class="fas fa-university"></i> Ganti Bank</button>
          <?php else: ?>
            <span style="font-size:11px;color:var(--success)"><i class="fas fa-check-circle"></i> <?= $inv['paid_at']?date('d/m/Y',strtotime($inv['paid_at'])):'-' ?></span>
            <?php if($inv['bk_status']==='completed'): ?>
            <a href="<?= BASE_URL ?>/pages/export_clearance.php?bid=<?= $inv['booking_id'] ?>" target="_blank" class="btn btn-xs btn-primary"><i class="fas fa-certificate"></i> SKE</a>
            <?php endif; ?>
          <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- VA Modal -->
<div class="modal-overlay" id="vaModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-credit-card"></i> Virtual Account</h3><button class="modal-close" onclick="closeModal('vaModal')">×</button></div>
    <div class="modal-body">
      <div class="va-card">
        <div class="va-bank" id="vaBank"></div>
        <div class="va-label">Nomor Virtual Account</div>
        <div class="va-number" id="vaNum" style="cursor:pointer" onclick="copyVA()" title="Klik untuk copy"></div>
        <div class="va-amount"><div class="label">Total Pembayaran</div><div class="value" id="vaAmt"></div></div>
        <div class="va-expire"><i class="fas fa-calendar-times"></i> Jatuh Tempo: <span id="vaExp"></span></div>
      </div>
      <div style="margin-top:14px;padding:14px;background:var(--surface-2);border-radius:var(--radius-sm);font-size:12px;color:var(--text-secondary)">
        <strong style="color:var(--text-primary)">Cara Pembayaran:</strong><br>
        1. Pilih menu Transfer / Bayar Tagihan di ATM/Mobile Banking<br>
        2. Pilih Virtual Account → <span id="vaBankStep"></span><br>
        3. Masukkan Nomor VA → Konfirmasi<br>
        4. Pembayaran otomatis terverifikasi dalam 5–10 menit
      </div>
    </div>
    <div class="modal-foot"><button class="btn btn-secondary" onclick="closeModal('vaModal')">Tutup</button></div>
  </div>
</div>

<!-- Ganti Bank Modal -->
<div class="modal-overlay" id="bankModal">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-head"><h3><i class="fas fa-university"></i> Ganti Bank VA</h3><button class="modal-close" onclick="closeModal('bankModal')">×</button></div>
    <div class="modal-body">
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">Pilih bank untuk men-generate nomor Virtual Account baru:</p>
      <form method="POST" id="bankForm">
        <input type="hidden" name="action" value="change_bank">
        <input type="hidden" name="invoice_id" id="bankInvId">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <?php foreach($BANKS as $bk):
            $colors=['BCA'=>'#0045a3','Mandiri'=>'#007b5f','BNI'=>'#f68b1e','BRI'=>'#003f7f','BSI'=>'#2d6a4f','CIMB'=>'#c0392b','Permata'=>'#e74c3c','Danamon'=>'#e67e22'];
            $color=$colors[$bk]??'#374151';
          ?>
          <label style="display:flex;align-items:center;gap:10px;padding:12px;border:2px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;transition:all 0.15s" class="bank-option-label" onclick="selectBank(this,'<?= $bk ?>')">
            <input type="radio" name="bank" value="<?= $bk ?>" style="display:none">
            <span style="width:36px;height:36px;border-radius:8px;background:<?= $color ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0"><?= $bk ?></span>
            <span style="font-size:13px;font-weight:600"><?= $bk ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px;justify-content:center"><i class="fas fa-sync-alt"></i> Generate VA Baru</button>
      </form>
    </div>
  </div>
</div>

<script>
function closeModal(id){document.getElementById(id).classList.remove('show');}
function openVAModal(id,bank,num,amt,exp){
  document.getElementById('vaBank').innerText=bank||'—';
  document.getElementById('vaNum').innerText=(num||'').match(/.{1,4}/g)?.join(' ')||'—';
  document.getElementById('vaAmt').innerText=amt;
  document.getElementById('vaExp').innerText=exp;
  document.getElementById('vaBankStep').innerText=bank||'—';
  document.getElementById('vaModal').classList.add('show');
}
function copyVA(){
  const t=document.getElementById('vaNum').innerText.replace(/\s/g,'');
  navigator.clipboard?.writeText(t).then(()=>{
    document.getElementById('vaNum').innerText='✅ Tersalin!';
    setTimeout(()=>{document.getElementById('vaNum').innerText=t.match(/.{1,4}/g).join(' ')},1500);
  });
}
function openBankModal(iid){
  document.getElementById('bankInvId').value=iid;
  document.querySelectorAll('.bank-option-label').forEach(l=>{l.style.borderColor='var(--border)';l.style.background='';});
  document.getElementById('bankModal').classList.add('show');
}
function selectBank(label,bank){
  document.querySelectorAll('.bank-option-label').forEach(l=>{l.style.borderColor='var(--border)';l.style.background='';});
  label.style.borderColor='var(--primary)';
  label.style.background='var(--primary-glow)';
  label.querySelector('input').checked=true;
}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('show');}));
</script>
<?php include '../includes/layout_footer.php'; ?>

<?php
session_start();
require_once '../includes/config.php';
requireRole('admin','operator');
$pageTitle='Approvals'; $activePage='approvals';
$db=getDB(); $u=user();
$msg=''; $msgType='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    $bid=(int)($_POST['booking_id']??0);
    $action=$_POST['action']??'';
    $cat=trim($_POST['catatan']??'');

    if($bid && in_array($action,['approve','reject','request_reupload'])) {
        if($action==='approve') {
            $db->prepare("UPDATE bookings SET status='approved',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$bid]);
            $db->prepare("INSERT INTO approvals(booking_id,approved_by,action,catatan) VALUES(?,?,?,?)")->execute([$bid,$u['id'],'approve',$cat]);
            // Create invoice
            $bk=$db->prepare("SELECT * FROM bookings WHERE id=?"); $bk->execute([$bid]); $bk=$bk->fetch();
            if($bk) {
                $ci=calcInvoice($bk['berat'],$bk['jenis_kulit'],$bk['ukuran_kontainer'],$bk['jumlah_kontainer']);
                $invNo=generateInvoiceNo(); $bank=['BCA','Mandiri','BNI','BRI'][array_rand(['BCA','Mandiri','BNI','BRI'])];
                $va=generateVA($bank); $due=date('Y-m-d',strtotime('+14 days'));
                $db->prepare("INSERT INTO invoices(booking_id,invoice_no,subtotal,tax,total_amount,payment_method,payment_va_number,payment_bank,due_date) VALUES(?,?,?,?,?,?,?,?,?)")
                   ->execute([$bid,$invNo,$ci['subtotal'],$ci['tax'],$ci['total'],'virtual_account',$va,$bank,$due]);
                addNotif($bk['user_id'],'✅ Booking Disetujui',"Booking {$bk['booking_no']} telah diapprove. Invoice {$invNo} senilai ".fmtRp($ci['total'])." telah diterbitkan.",'success','/pages/my_bookings.php');
                addNotif($bk['user_id'],'🧾 Invoice Tersedia',"Invoice {$invNo} senilai ".fmtRp($ci['total'])." siap dibayar via Virtual Account {$bank}",'invoice','/pages/my_bookings.php');
            }
            $msg='Booking berhasil diapprove. Invoice otomatis dibuat.'; $msgType='success';
        }
        elseif($action==='reject') {
            $db->prepare("UPDATE bookings SET status='rejected',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$bid]);
            $db->prepare("INSERT INTO approvals(booking_id,approved_by,action,catatan) VALUES(?,?,?,?)")->execute([$bid,$u['id'],'reject',$cat]);
            $bk=$db->prepare("SELECT * FROM bookings WHERE id=?"); $bk->execute([$bid]); $bk=$bk->fetch();
            if($bk) addNotif($bk['user_id'],'❌ Booking Ditolak',"Booking {$bk['booking_no']} ditolak: $cat",'danger','/pages/my_bookings.php');
            $msg='Booking ditolak.'; $msgType='danger';
        }
        elseif($action==='request_reupload') {
            $db->prepare("UPDATE bookings SET status='rejected',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$bid]);
            $db->prepare("INSERT INTO approvals(booking_id,approved_by,action,catatan) VALUES(?,?,?,?)")->execute([$bid,$u['id'],'reject',"[UPLOAD ULANG] $cat"]);
            $bk=$db->prepare("SELECT * FROM bookings WHERE id=?"); $bk->execute([$bid]); $bk=$bk->fetch();
            if($bk) addNotif($bk['user_id'],'📤 Upload Dokumen Ulang',"Booking {$bk['booking_no']}: $cat",'warning','/pages/new_booking.php');
            $msg='Permintaan upload ulang dikirim ke user.'; $msgType='warning';
        }
    }
}

$search=trim($_GET['s']??''); $fs=trim($_GET['status']??''); $df=$_GET['df']??''; $dt=$_GET['dt']??'';
$w=['1=1']; $p=[];
if($search){$w[]="(b.nama_pt LIKE ? OR b.booking_no LIKE ?)";$p[]="%$search%";$p[]="%$search%";}
if($fs){$w[]="b.status=?";$p[]=$fs;}
if($df){$w[]="b.tanggal_kirim>=?";$p[]=$df;}
if($dt){$w[]="b.tanggal_kirim<=?";$p[]=$dt;}
$ws=implode(' AND ',$w);
$stmt=$db->prepare("SELECT b.*,u.full_name as uname,u.company,(SELECT COUNT(*) FROM booking_documents WHERE booking_id=b.id) as doc_count FROM bookings b LEFT JOIN users u ON b.user_id=u.id WHERE $ws ORDER BY CASE WHEN b.status='pending' THEN 0 ELSE 1 END,b.created_at DESC");
$stmt->execute($p); $list=$stmt->fetchAll();

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-check-double" style="color:var(--primary)"></i> Approvals</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Approvals</span></div>
    </div>
    <?php $pc=countPending(); if($pc>0): ?>
    <div class="alert alert-warning" style="margin:0;padding:8px 14px"><i class="fas fa-exclamation-triangle"></i><?= $pc ?> booking menunggu persetujuan</div>
    <?php endif; ?>
  </div>
</div>

<?php if($msg): ?><div class="alert alert-<?= $msgType ?> fade-in"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i><?= $msg ?></div><?php endif; ?>

<div class="card fade-in">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-list"></i> Daftar Booking </div>
  </div>
  <form method="GET" style="margin-bottom:16px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:12px 14px;background:var(--surface-2);border-radius:var(--radius-sm);border:1px solid var(--border)">
      <div class="input-icon-wrap" style="flex:1;min-width:180px"><i class="fas fa-search input-icon"></i><input type="text" name="s" class="form-control" placeholder="Cari booking, PT..." value="<?= htmlspecialchars($search) ?>" style="padding-left:36px"></div>
      <select name="status" class="form-select" style="width:140px">
        <option value="">Semua Status</option>
        <?php foreach(['pending','approved','rejected','customs','quarantine','completed'] as $ss): ?>
        <option value="<?= $ss ?>" <?= $fs===$ss?'selected':'' ?>><?= ucfirst($ss) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="df" class="form-control" style="width:140px" value="<?= $df ?>">
      <input type="date" name="dt" class="form-control" style="width:140px" value="<?= $dt ?>">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
      <a href="approvals.php" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i></a>
    </div>
  </form>

  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>No</th><th>Booking No</th><th>Perusahaan</th><th>Komoditas</th><th>Berat</th><th>Kontainer</th><th>Tgl Kirim</th><th>Dok</th><th>Status</th><th>Aksi</th>
      </tr></thead>
      <tbody>
      <?php if(empty($list)): ?>
      <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:8px"></i>Tidak ada data</td></tr>
      <?php endif; ?>
      <?php foreach($list as $i=>$b): ?>
      <tr>
        <td style="color:var(--text-muted)"><?= $i+1 ?></td>
        <td><span style="font-weight:800;color:var(--primary);font-family:var(--font-mono);font-size:13px"><?= htmlspecialchars($b['booking_no']) ?></span><br><span style="font-size:11px;color:var(--text-muted)"><?= date('d/m/Y',strtotime($b['created_at'])) ?></span></td>
        <td><div style="font-weight:600"><?= htmlspecialchars($b['nama_pt']) ?></div><div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($b['uname']??'') ?></div></td>
        <td><?= htmlspecialchars($b['jenis_kulit']) ?></td>
        <td><?= number_format($b['berat']) ?> kg</td>
        <td><?= htmlspecialchars($b['ukuran_kontainer']) ?> ×<?= $b['jumlah_kontainer'] ?></td>
        <td><?= fmtDate($b['tanggal_kirim']) ?></td>
        <td>
          <?php if($b['doc_count']>0): ?>
          <button class="btn btn-xs btn-outline" onclick="viewDocs(<?= $b['id'] ?>,'<?= htmlspecialchars($b['booking_no']) ?>')"><i class="fas fa-paperclip"></i> <?= $b['doc_count'] ?></button>
          <?php else: ?><span style="font-size:11px;color:var(--text-muted)">—</span><?php endif; ?>
        </td>
        <td><?= badge($b['status']) ?></td>
        <td>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            <?php if($b['status']==='pending'): ?>
            <button class="btn btn-success btn-xs" onclick="openAction(<?= $b['id'] ?>,'approve','<?= htmlspecialchars($b['booking_no']) ?>')"><i class="fas fa-check"></i></button>
            <button class="btn btn-danger btn-xs" onclick="openAction(<?= $b['id'] ?>,'reject','<?= htmlspecialchars($b['booking_no']) ?>')"><i class="fas fa-times"></i></button>
            <button class="btn btn-warning btn-xs" onclick="openAction(<?= $b['id'] ?>,'request_reupload','<?= htmlspecialchars($b['booking_no']) ?>')" title="Minta Upload Ulang"><i class="fas fa-upload"></i></button>
            <?php else: ?>
            <button class="btn btn-xs btn-secondary" disabled><i class="fas fa-check"></i></button>
            <button class="btn btn-xs btn-secondary" disabled><i class="fas fa-times"></i></button>
            <?php endif; ?>
            <a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-ghost"><i class="fas fa-eye"></i></a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
  <div class="modal-box">
    <div class="modal-head"><h3 id="mTitle">Konfirmasi</h3><button class="modal-close" onclick="closeModal('actionModal')">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="booking_id" id="mBid">
        <input type="hidden" name="action" id="mAction">
        <p id="mMsg" style="margin-bottom:16px;font-size:14px;color:var(--text-secondary)"></p>
        <div class="form-group">
          <label class="form-label">Catatan <span id="mCatReq"></span></label>
          <textarea name="catatan" id="mCat" class="form-textarea" placeholder="Tuliskan alasan atau catatan..." rows="3"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" onclick="closeModal('actionModal')"><i class="fas fa-times"></i> Batal</button>
        <button type="submit" class="btn" id="mSubmit"><i class="fas fa-check"></i> Konfirmasi</button>
      </div>
    </form>
  </div>
</div>

<!-- Docs Modal -->
<div class="modal-overlay" id="docsModal">
  <div class="modal-box modal-lg">
    <div class="modal-head"><h3 id="docsTitle">Dokumen Pendukung</h3><button class="modal-close" onclick="closeModal('docsModal')">×</button></div>
    <div class="modal-body" id="docsList">Loading...</div>
  </div>
</div>

<script>
function openAction(id,action,bno){
  document.getElementById('mBid').value=id;
  document.getElementById('mAction').value=action;
  const cfg={
    approve:{t:'Approve Booking',msg:`Setujui booking <strong>${bno}</strong>? Invoice akan otomatis dibuat.`,btn:'btn-success',ico:'fa-check',req:''},
    reject:{t:'Reject Booking',msg:`Tolak booking <strong>${bno}</strong>?`,btn:'btn-danger',ico:'fa-times',req:'(wajib)'},
    request_reupload:{t:'Minta Upload Ulang',msg:`Minta user untuk mengupload ulang dokumen booking <strong>${bno}</strong>`,btn:'btn-warning',ico:'fa-upload',req:'(wajib)'}
  }[action];
  document.getElementById('mTitle').innerText=cfg.t;
  document.getElementById('mMsg').innerHTML=cfg.msg;
  document.getElementById('mCatReq').innerText=cfg.req;
  const btn=document.getElementById('mSubmit');
  btn.className=`btn ${cfg.btn}`;btn.innerHTML=`<i class="fas ${cfg.ico}"></i> Konfirmasi`;
  document.getElementById('mCat').value='';
  document.getElementById('actionModal').classList.add('show');
}
function closeModal(id){document.getElementById(id).classList.remove('show');}
function viewDocs(bid,bno){
  document.getElementById('docsTitle').innerText='Dokumen — '+bno;
  document.getElementById('docsModal').classList.add('show');
  document.getElementById('docsList').innerHTML='<div style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  fetch(`<?= BASE_URL ?>/api/docs.php?bid=${bid}`)
    .then(r=>r.json()).then(data=>{
      if(!data.docs||data.docs.length===0){document.getElementById('docsList').innerHTML='<div style="text-align:center;padding:30px;color:var(--text-muted)">Tidak ada dokumen</div>';return;}
      let html='<div class="file-list">';
      data.docs.forEach(d=>{
        html+=`<div class="file-item">
          <div style="font-size:22px">${getDocIcon(d.original_name)}</div>
          <div class="file-item-info"><div class="file-item-name">${d.original_name}</div><div class="file-item-size">${(d.file_size/1024/1024).toFixed(2)} MB · Upload: ${d.created_at}</div></div>
          <a href="/assets/uploads/documents/${d.filename}" target="_blank" class="btn btn-xs btn-primary"><i class="fas fa-external-link-alt"></i> Lihat</a>
        </div>`;
      });
      html+='</div>';
      document.getElementById('docsList').innerHTML=html;
    }).catch(()=>document.getElementById('docsList').innerHTML='<div class="alert alert-danger">Gagal memuat dokumen</div>');
}
function getDocIcon(name){const e=name.split('.').pop().toLowerCase();return{pdf:'📄',doc:'📝',docx:'📝',jpg:'🖼️',jpeg:'🖼️',png:'🖼️'}[e]||'📎';}
</script>
<?php include '../includes/layout_footer.php'; ?>

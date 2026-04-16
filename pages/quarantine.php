<?php
session_start();
require_once '../includes/config.php';
requireRole('admin','quarantine');
$pageTitle='Quarantine — Karantina'; $activePage='quarantine';
$db=getDB(); $u=user();
$msg=''; $mt='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    $bid=(int)($_POST['booking_id']??0);
    $nos=trim($_POST['no_sertifikat']??'');
    $st=$_POST['qstatus']??'pending';
    $cat=trim($_POST['catatan']??'');
    if($bid) {
        $ex=$db->prepare("SELECT id FROM quarantine WHERE booking_id=?"); $ex->execute([$bid]); $ex=$ex->fetch();
        if($ex) $db->prepare("UPDATE quarantine SET no_sertifikat=?,status=?,catatan=?,petugas_id=?,updated_at=CURRENT_TIMESTAMP WHERE booking_id=?")->execute([$nos,$st,$cat,$u['id'],$bid]);
        else $db->prepare("INSERT INTO quarantine(booking_id,no_sertifikat,status,petugas_id,catatan) VALUES(?,?,?,?,?)")->execute([$bid,$nos,$st,$u['id'],$cat]);

        $bk=$db->prepare("SELECT * FROM bookings WHERE id=?"); $bk->execute([$bid]); $bk=$bk->fetch();
        if($st==='clear') {
            $db->prepare("UPDATE bookings SET status='completed',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$bid]);
            if($bk) addNotif($bk['user_id'],'🏆 Booking Selesai',"Booking {$bk['booking_no']} telah lulus semua pemeriksaan dan dinyatakan selesai!",'success','/pages/my_bookings.php');
        } elseif($st==='hold') {
            $db->prepare("UPDATE bookings SET status='rejected',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$bid]);
            if($bk) addNotif($bk['user_id'],'❌ Booking Ditahan Karantina',"Booking {$bk['booking_no']} ditahan oleh Karantina: $cat",'danger','/pages/my_bookings.php');
        }
        $msg='Status karantina berhasil diperbarui.'; $mt='success';
    }
}

$list=$db->query("SELECT b.*,q.id as qid,q.no_sertifikat,q.status as qstatus,q.catatan as qcat,(SELECT COUNT(*) FROM booking_documents WHERE booking_id=b.id) as doc_count FROM bookings b LEFT JOIN quarantine q ON b.id=q.booking_id WHERE b.status IN('quarantine','completed') ORDER BY CASE WHEN b.status='quarantine' THEN 0 ELSE 1 END,b.updated_at DESC")->fetchAll();

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-shield-virus" style="color:var(--primary)"></i> Quarantine — Karantina</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Quarantine</span></div>
    </div>
  </div>
</div>
<?php if($msg): ?><div class="alert alert-<?= $mt ?> fade-in"><i class="fas fa-check-circle"></i><?= $msg ?></div><?php endif; ?>

<div class="card fade-in">
  <div class="card-header"><div class="card-title"><i class="fas fa-list"></i> Daftar Pemeriksaan Karantina </div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>No</th><th>Booking No</th><th>Perusahaan</th><th>Komoditas</th><th>Berat</th><th>Dok</th><th>No Sertifikat</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if(empty($list)): ?><tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text-muted)">Tidak ada data</td></tr><?php endif; ?>
      <?php foreach($list as $i=>$b): ?>
      <tr>
        <td style="color:var(--text-muted)"><?= $i+1 ?></td>
        <td><span style="font-weight:800;color:var(--primary);font-family:var(--font-mono);font-size:13px"><?= htmlspecialchars($b['booking_no']) ?></span></td>
        <td><?= htmlspecialchars($b['nama_pt']) ?></td>
        <td><?= htmlspecialchars($b['jenis_kulit']) ?></td>
        <td><?= number_format($b['berat']) ?> kg</td>
        <td><?php if($b['doc_count']>0): ?><button class="btn btn-xs btn-outline" onclick="viewDocs(<?= $b['id'] ?>,'<?= htmlspecialchars($b['booking_no']) ?>')"><i class="fas fa-paperclip"></i><?= $b['doc_count'] ?></button><?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?></td>
        <td><code style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($b['no_sertifikat']??'—') ?></code></td>
        <td><?= badge($b['qstatus']??'pending') ?></td>
        <td>
          <button class="btn btn-primary btn-xs" onclick="openEdit(<?= $b['id'] ?>,'<?= htmlspecialchars(addslashes($b['booking_no'])) ?>','<?= htmlspecialchars(addslashes($b['no_sertifikat']??'')) ?>','<?= $b['qstatus']??'pending' ?>','<?= htmlspecialchars(addslashes($b['qcat']??'')) ?>')"><i class="fas fa-edit"></i> Update</button>
          <a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-xs"><i class="fas fa-eye"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-head"><h3>Update Karantina</h3><button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">×</button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="booking_id" id="eBid">
        <div style="margin-bottom:14px;padding:10px 12px;background:var(--surface-2);border-radius:var(--radius-sm);font-size:13px" id="eBno"></div>
        <div class="form-group" style="margin-bottom:14px"><label class="form-label">Nomor Sertifikat Karantina</label><input type="text" name="no_sertifikat" id="eNos" class="form-control" placeholder="KT-2026-001"></div>
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label">Status Pemeriksaan</label>
          <select name="qstatus" id="eSt" class="form-select">
            <option value="pending">Pending — Menunggu</option>
            <option value="clear">Clear — Lulus Karantina</option>
            <option value="hold">Hold — Ditahan</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Catatan Pemeriksaan</label><textarea name="catatan" id="eCat" class="form-textarea" rows="3" placeholder="Hasil pemeriksaan karantina..."></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('show')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="docsModal">
  <div class="modal-box modal-lg"><div class="modal-head"><h3 id="docsTitle">Dokumen</h3><button class="modal-close" onclick="document.getElementById('docsModal').classList.remove('show')">×</button></div><div class="modal-body" id="docsList"></div></div>
</div>

<script>
function openEdit(id,bno,nos,st,cat){
  document.getElementById('eBid').value=id;
  document.getElementById('eBno').innerHTML='<i class="fas fa-tag"></i> Booking: <strong>'+bno+'</strong>';
  document.getElementById('eNos').value=nos;
  document.getElementById('eSt').value=st;
  document.getElementById('eCat').value=cat;
  document.getElementById('editModal').classList.add('show');
}
function viewDocs(bid,bno){
  document.getElementById('docsTitle').innerText='Dokumen — '+bno;
  document.getElementById('docsModal').classList.add('show');
  document.getElementById('docsList').innerHTML='<div style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin"></i></div>';
  fetch(`<?= BASE_URL ?>/api/docs.php?bid=${bid}`).then(r=>r.json()).then(d=>{
    if(!d.docs||!d.docs.length){document.getElementById('docsList').innerHTML='<div style="text-align:center;padding:30px;color:var(--text-muted)">Tidak ada dokumen</div>';return;}
    let h='<div class="file-list">';
    d.docs.forEach(f=>{h+=`<div class="file-item"><span style="font-size:22px">${{pdf:'📄',doc:'📝',docx:'📝',jpg:'🖼️',jpeg:'🖼️',png:'🖼️'}[f.original_name.split('.').pop().toLowerCase()]||'📎'}</span><div class="file-item-info"><div class="file-item-name">${f.original_name}</div><div class="file-item-size">${(f.file_size/1024/1024).toFixed(2)} MB</div></div><a href="/assets/uploads/documents/${f.filename}" target="_blank" class="btn btn-xs btn-primary"><i class="fas fa-external-link-alt"></i> Lihat</a></div>`;});
    document.getElementById('docsList').innerHTML=h+'</div>';
  });
}
</script>
<?php include '../includes/layout_footer.php'; ?>

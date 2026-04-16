<?php
session_start();
require_once '../includes/config.php';
requireLogin();
if(!isRole('admin','operator','user')) { header('Location: dashboard.php'); exit; }

$pageTitle='New Booking'; $activePage='new_booking';
$db=getDB(); $u=user();
$msg=''; $msgType='';

if($_SERVER['REQUEST_METHOD']==='POST') {
    $np=trim($_POST['nama_pt']??'');
    $jk=$_POST['jenis_kulit']??'';
    $br=floatval($_POST['berat']??0);
    $uk=$_POST['ukuran_kontainer']??'';
    $jml=max(1,(int)($_POST['jumlah_kontainer']??1));
    $tgl=$_POST['tanggal_kirim']??'';
    $asal=trim($_POST['asal_negara']??'Indonesia');
    $tuju=trim($_POST['tujuan_negara']??'');
    $cat=trim($_POST['catatan']??'');

    if(!$np||!$jk||!$br||!$uk||!$tgl) { $msg='Semua field wajib harus diisi.'; $msgType='danger'; }
    elseif($br<=0) { $msg='Berat harus lebih dari 0.'; $msgType='danger'; }
    else {
        $bno=generateBookingNo();
        $db->prepare("INSERT INTO bookings(booking_no,user_id,nama_pt,jenis_kulit,berat,ukuran_kontainer,jumlah_kontainer,tanggal_kirim,asal_negara,tujuan_negara,catatan,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,'pending')")
           ->execute([$bno,$u['id'],$np,$jk,$br,$uk,$jml,$tgl,$asal,$tuju,$cat]);
        $bid=(int)$db->lastInsertId();

        // Handle file uploads - 3 zones
        $docTypes = ['admin' => 'Untuk Admin', 'karantina' => 'Untuk Karantina', 'beacukai' => 'Untuk Bea Cukai'];
        if(!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR,0755,true);
        $allowed=['pdf','doc','docx','jpg','jpeg','png'];
        foreach($docTypes as $zone => $label) {
            if(!empty($_FILES['doc_'.$zone]['name'][0])) {
                foreach($_FILES['doc_'.$zone]['name'] as $i=>$fname) {
                    if($_FILES['doc_'.$zone]['error'][$i]!==0) continue;
                    $ext=strtolower(pathinfo($fname,PATHINFO_EXTENSION));
                    if(!in_array($ext,$allowed)) continue;
                    $newName='doc_'.$bid.'_'.$zone.'_'.time().'_'.$i.'.'.$ext;
                    $dest=UPLOAD_DIR.$newName;
                    if(move_uploaded_file($_FILES['doc_'.$zone]['tmp_name'][$i],$dest)) {
                        $db->prepare("INSERT INTO booking_documents(booking_id,filename,original_name,file_size,file_type,uploaded_by,doc_type) VALUES(?,?,?,?,?,?,?)")
                           ->execute([$bid,$newName,$fname,$_FILES['doc_'.$zone]['size'][$i],$ext,$u['id'],$zone]);
                    }
                }
            }
        }

        // Notify admin/operator
        $admins=$db->query("SELECT id FROM users WHERE role IN ('admin','operator')")->fetchAll();
        foreach($admins as $a) addNotif($a['id'],'📋 Booking Baru Masuk',"$bno dari {$np} menunggu approval",'info','/pages/approvals.php');

        // Notify user
        addNotif($u['id'],'✅ Booking Berhasil Dibuat',"Booking $bno telah dibuat dan menunggu review admin",'success','/pages/my_bookings.php');

        $msg="Booking <strong>$bno</strong> berhasil dibuat! Dokumen Anda sedang menunggu review."; $msgType='success';
    }
}

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> New Booking</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">New Booking</span></div>
    </div>
  </div>
</div>

<?php if($msg): ?><div class="alert alert-<?= $msgType ?> fade-in"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i><div><?= $msg ?></div></div><?php endif; ?>

<div class="card fade-in fade-in-1">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-file-alt"></i> Form Input Data Kulit Garaman</div>
    <span class="badge badge-pending">Baru</span>
  </div>
  <form method="POST" enctype="multipart/form-data" id="bookingForm">
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Nama Perusahaan (PT) <span style="color:var(--danger)">*</span></label>
        <div class="input-icon-wrap"><i class="fas fa-building input-icon"></i>
          <input type="text" name="nama_pt" class="form-control" placeholder="Contoh: PT Makmur Jaya" value="<?= htmlspecialchars($_POST['nama_pt']??($u['company']??'')) ?>" required></div>
      </div>
      <div class="form-group">
        <label class="form-label">Jenis Kulit <span style="color:var(--danger)">*</span></label>
        <select name="jenis_kulit" class="form-select" required onchange="calcEst()">
          <option value="">-- Pilih Jenis Kulit --</option>
          <?php foreach(['Kulit Sapi'=>'Rp 3.000/kg','Kulit Kambing'=>'Rp 2.500/kg','Kulit Domba'=>'Rp 2.200/kg'] as $j=>$rate): ?>
          <option value="<?= $j ?>" <?= ($_POST['jenis_kulit']??'')===$j?'selected':'' ?>><?= $j ?> (<?= $rate ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Berat Kulit (Kg) <span style="color:var(--danger)">*</span></label>
        <div class="input-icon-wrap"><i class="fas fa-weight input-icon"></i>
          <input type="number" name="berat" class="form-control" placeholder="Contoh: 1200" min="1" step="0.1" value="<?= htmlspecialchars($_POST['berat']??'') ?>" required oninput="calcEst()"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Ukuran Kontainer <span style="color:var(--danger)">*</span></label>
        <select name="ukuran_kontainer" class="form-select" required onchange="calcEst()">
          <option value="">-- Pilih Ukuran --</option>
          <option value="20 Feet" <?= ($_POST['ukuran_kontainer']??'')==='20 Feet'?'selected':'' ?>>20 Feet (max ~21 ton)</option>
          <option value="40 Feet" <?= ($_POST['ukuran_kontainer']??'')==='40 Feet'?'selected':'' ?>>40 Feet (max ~26 ton)</option>
          <option value="40 HC"   <?= ($_POST['ukuran_kontainer']??'')==='40 HC'?'selected':'' ?>>40 HC / High Cube</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Jumlah Kontainer <span style="color:var(--danger)">*</span></label>
        <div class="input-icon-wrap"><i class="fas fa-boxes input-icon"></i>
          <input type="number" name="jumlah_kontainer" class="form-control" min="1" max="20" value="<?= htmlspecialchars($_POST['jumlah_kontainer']??'1') ?>" required oninput="calcEst()"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Tanggal Kirim <span style="color:var(--danger)">*</span></label>
        <input type="date" name="tanggal_kirim" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['tanggal_kirim']??'') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Asal Negara</label>
        <div class="country-select-wrap" style="position:relative">
          <input type="text" name="asal_negara" id="asalInput" class="form-control" value="<?= htmlspecialchars($_POST['asal_negara']??'Indonesia') ?>" autocomplete="off" placeholder="Cari negara..." oninput="filterCountry('asal',this.value)" onfocus="showCountryDrop('asal')" onblur="hideCountryDrop('asal',500)" style="padding-left:38px">
          <span id="asalFlag" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px">🇮🇩</span>
          <div id="asalDrop" class="country-drop" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:999;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);max-height:220px;overflow-y:auto;box-shadow:var(--shadow-lg)"></div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Negara Tujuan Ekspor <span style="color:var(--danger)">*</span></label>
        <div class="country-select-wrap" style="position:relative">
          <input type="text" name="tujuan_negara" id="tujuanInput" class="form-control" value="<?= htmlspecialchars($_POST['tujuan_negara']??'') ?>" autocomplete="off" placeholder="Cari negara tujuan..." oninput="filterCountry('tujuan',this.value)" onfocus="showCountryDrop('tujuan')" onblur="hideCountryDrop('tujuan',500)" style="padding-left:38px">
          <span id="tujuanFlag" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px">🌍</span>
          <div id="tujuanDrop" class="country-drop" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:999;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);max-height:220px;overflow-y:auto;box-shadow:var(--shadow-lg)"></div>
        </div>
      </div>
    </div>

    <div class="form-group" style="margin-top:8px">
      <label class="form-label">Catatan Tambahan</label>
      <textarea name="catatan" class="form-textarea" placeholder="Informasi tambahan, instruksi khusus..."><?= htmlspecialchars($_POST['catatan']??'') ?></textarea>
    </div>

    <!-- DOCUMENT UPLOAD - 3 ZONES -->
    <div class="form-group" style="margin-top:16px">
      <label class="form-label"><i class="fas fa-paperclip" style="color:var(--primary)"></i> Dokumen Pendukung</label>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px" class="doc-upload-grid">

        <!-- Zone 1: Admin -->
        <div class="doc-zone-wrap">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--primary);margin-bottom:8px;display:flex;align-items:center;gap:6px"><span style="width:8px;height:8px;background:#1d4ed8;border-radius:50%;display:inline-block"></span> Untuk Admin TPS</div>
          <div class="file-drop-zone" id="dropZoneAdmin" onclick="document.getElementById('docAdmin').click()" ondragover="handleDrag(event,'Admin',true)" ondragleave="handleDrag(event,'Admin',false)" ondrop="handleDrop(event,'admin')" style="min-height:100px;padding:14px">
            <i class="fas fa-user-shield" style="font-size:24px;margin-bottom:6px"></i>
            <p style="font-size:12px"><strong>Upload Dokumen Admin</strong></p>
            <small>PDF, DOC, JPG · Maks 5MB</small>
          </div>
          <input type="file" id="docAdmin" name="doc_admin[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none" onchange="showFilesZone(this.files,'fileListAdmin')">
          <div class="file-list" id="fileListAdmin"></div>
        </div>

        <!-- Zone 2: Karantina -->
        <div class="doc-zone-wrap">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#d97706;margin-bottom:8px;display:flex;align-items:center;gap:6px"><span style="width:8px;height:8px;background:#d97706;border-radius:50%;display:inline-block"></span> Untuk Karantina</div>
          <div class="file-drop-zone" id="dropZoneKarantina" onclick="document.getElementById('docKarantina').click()" ondragover="handleDrag(event,'Karantina',true)" ondragleave="handleDrag(event,'Karantina',false)" ondrop="handleDrop(event,'karantina')" style="min-height:100px;padding:14px;border-color:rgba(217,119,6,0.4)">
            <i class="fas fa-shield-virus" style="font-size:24px;margin-bottom:6px;color:#d97706"></i>
            <p style="font-size:12px"><strong>Upload Dokumen Karantina</strong></p>
            <small>PDF, DOC, JPG · Maks 5MB</small>
          </div>
          <input type="file" id="docKarantina" name="doc_karantina[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none" onchange="showFilesZone(this.files,'fileListKarantina')">
          <div class="file-list" id="fileListKarantina"></div>
        </div>

        <!-- Zone 3: Bea Cukai -->
        <div class="doc-zone-wrap">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#7c3aed;margin-bottom:8px;display:flex;align-items:center;gap:6px"><span style="width:8px;height:8px;background:#7c3aed;border-radius:50%;display:inline-block"></span> Untuk Bea Cukai</div>
          <div class="file-drop-zone" id="dropZoneBeacukai" onclick="document.getElementById('docBeacukai').click()" ondragover="handleDrag(event,'Beacukai',true)" ondragleave="handleDrag(event,'Beacukai',false)" ondrop="handleDrop(event,'beacukai')" style="min-height:100px;padding:14px;border-color:rgba(124,58,237,0.4)">
            <i class="fas fa-passport" style="font-size:24px;margin-bottom:6px;color:#7c3aed"></i>
            <p style="font-size:12px"><strong>Upload Dokumen Bea Cukai</strong></p>
            <small>PDF, DOC, JPG · Maks 5MB</small>
          </div>
          <input type="file" id="docBeacukai" name="doc_beacukai[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none" onchange="showFilesZone(this.files,'fileListBeacukai')">
          <div class="file-list" id="fileListBeacukai"></div>
        </div>

      </div>
      <style>@media(max-width:768px){.doc-upload-grid{grid-template-columns:1fr!important;}}</style>
    </div>

    <!-- ESTIMATE BOX -->
    <div id="estBox" style="display:none;margin-top:16px;padding:18px 20px;background:var(--primary-glow);border:1.5px solid rgba(11,94,222,0.2);border-radius:var(--radius);backdrop-filter:blur(4px)">
      <div style="font-size:12px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px"><i class="fas fa-calculator"></i> Estimasi Biaya</div>
      <div style="font-size:28px;font-weight:800;color:var(--primary)" id="estAmt">Rp 0</div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:4px">*Termasuk PPN 12%. Tagihan final setelah proses selesai.</div>
    </div>

    <div style="display:flex;gap:12px;margin-top:22px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Kirim Booking</button>
      <button type="reset" class="btn btn-secondary" onclick="document.getElementById('fileList').innerHTML='';document.getElementById('estBox').style.display='none'"><i class="fas fa-undo"></i> Reset</button>
      <a href="<?= isRole('user')?'my_bookings.php':'dashboard.php' ?>" class="btn btn-ghost"><i class="fas fa-times"></i> Batal</a>
    </div>
  </form>
</div>

<!-- Process info -->
<div class="card fade-in fade-in-2" style="background:linear-gradient(135deg,var(--surface-2),var(--surface))">
  <div class="card-title"><i class="fas fa-route"></i> Alur Proses Booking</div>
  <div style="display:flex;align-items:flex-start;gap:0;overflow-x:auto;padding:8px 0">
    <?php $steps=[['fa-file-alt','New Booking','Isi form'],['fa-check-double','Approval','Review admin'],['fa-passport','Customs','Bea Cukai'],['fa-shield-virus','Karantina','Pemeriksaan'],['fa-file-invoice-dollar','Invoice','Pembayaran'],['fa-trophy','Selesai','Completed']];
    $colors=['#0b5ede','#059669','#7c3aed','#d97706','#0284c7','#f59e0b'];
    foreach($steps as $i=>$s): ?>
    <div style="display:flex;align-items:center;min-width:<?= $i===count($steps)-1?'80':'110' ?>px">
      <div style="text-align:center;width:80px">
        <div style="width:44px;height:44px;border-radius:50%;background:<?= $colors[$i] ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;margin:0 auto 6px;box-shadow:0 3px 10px <?= $colors[$i] ?>40"><i class="fas <?= $s[0] ?>"></i></div>
        <div style="font-size:11px;font-weight:700;color:var(--text-primary)"><?= $s[1] ?></div>
        <div style="font-size:10px;color:var(--text-muted)"><?= $s[2] ?></div>
      </div>
      <?php if($i<count($steps)-1): ?>
      <div style="flex:1;height:2px;background:linear-gradient(90deg,<?= $colors[$i] ?>,<?= $colors[$i+1] ?>);min-width:24px;margin-bottom:20px"></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const RATES={'Kulit Sapi':3000,'Kulit Kambing':2500,'Kulit Domba':2200};
const CFEE={'20 Feet':600000,'40 Feet':950000,'40 HC':1100000};
function calcEst(){
  const b=parseFloat(document.querySelector('[name=berat]').value)||0;
  const j=document.querySelector('[name=jenis_kulit]').value;
  const k=document.querySelector('[name=ukuran_kontainer]').value;
  const n=parseInt(document.querySelector('[name=jumlah_kontainer]').value)||1;
  if(b>0&&j&&k){
    const sub=(b*(RATES[j]||2500))+((CFEE[k]||600000)*n);
    const total=sub*1.12;
    document.getElementById('estBox').style.display='block';
    document.getElementById('estAmt').innerText='Rp '+Math.round(total).toLocaleString('id-ID');
  }
}

// 3-Zone upload handlers
function handleDrag(e,zone,on){e.preventDefault();document.getElementById('dropZone'+zone).classList.toggle('dragover',on);}
function handleDrop(e,zone){
  e.preventDefault();
  document.getElementById('dropZone'+zone.charAt(0).toUpperCase()+zone.slice(1)).classList.remove('dragover');
  const dt=new DataTransfer();
  Array.from(e.dataTransfer.files).forEach(f=>dt.items.add(f));
  const inputId='doc'+zone.charAt(0).toUpperCase()+zone.slice(1);
  const listId='fileList'+zone.charAt(0).toUpperCase()+zone.slice(1);
  document.getElementById(inputId).files=dt.files;
  showFilesZone(dt.files,listId);
}
function showFilesZone(files,listId){
  const list=document.getElementById(listId);list.innerHTML='';
  Array.from(files).forEach(f=>{
    const ext=f.name.split('.').pop().toLowerCase();
    const icons={pdf:'fa-file-pdf pdf',doc:'fa-file-word word',docx:'fa-file-word word',jpg:'fa-file-image img',jpeg:'fa-file-image img',png:'fa-file-image img'};
    const ic=icons[ext]||'fa-file other';
    const sz=(f.size/1024/1024).toFixed(2);
    list.innerHTML+=`<div class="file-item"><i class="fas ${ic} file-item-icon"></i><div class="file-item-info"><div class="file-item-name">${f.name}</div><div class="file-item-size">${sz} MB</div></div></div>`;
  });
}

// Country dropdown data
const COUNTRIES=[
  {name:'Indonesia',flag:'🇮🇩'},
  {name:'China',flag:'🇨🇳'},
  {name:'Japan',flag:'🇯🇵'},
  {name:'South Korea',flag:'🇰🇷'},
  {name:'India',flag:'🇮🇳'},
  {name:'United States',flag:'🇺🇸'},
  {name:'Germany',flag:'🇩🇪'},
  {name:'United Kingdom',flag:'🇬🇧'},
  {name:'France',flag:'🇫🇷'},
  {name:'Italy',flag:'🇮🇹'},
  {name:'Spain',flag:'🇪🇸'},
  {name:'Netherlands',flag:'🇳🇱'},
  {name:'Belgium',flag:'🇧🇪'},
  {name:'Australia',flag:'🇦🇺'},
  {name:'New Zealand',flag:'🇳🇿'},
  {name:'Canada',flag:'🇨🇦'},
  {name:'Brazil',flag:'🇧🇷'},
  {name:'Mexico',flag:'🇲🇽'},
  {name:'Argentina',flag:'🇦🇷'},
  {name:'Malaysia',flag:'🇲🇾'},
  {name:'Singapore',flag:'🇸🇬'},
  {name:'Thailand',flag:'🇹🇭'},
  {name:'Vietnam',flag:'🇻🇳'},
  {name:'Philippines',flag:'🇵🇭'},
  {name:'Myanmar',flag:'🇲🇲'},
  {name:'Bangladesh',flag:'🇧🇩'},
  {name:'Pakistan',flag:'🇵🇰'},
  {name:'Sri Lanka',flag:'🇱🇰'},
  {name:'Saudi Arabia',flag:'🇸🇦'},
  {name:'UAE',flag:'🇦🇪'},
  {name:'Turkey',flag:'🇹🇷'},
  {name:'Egypt',flag:'🇪🇬'},
  {name:'Nigeria',flag:'🇳🇬'},
  {name:'South Africa',flag:'🇿🇦'},
  {name:'Morocco',flag:'🇲🇦'},
  {name:'Russia',flag:'🇷🇺'},
  {name:'Poland',flag:'🇵🇱'},
  {name:'Sweden',flag:'🇸🇪'},
  {name:'Norway',flag:'🇳🇴'},
  {name:'Denmark',flag:'🇩🇰'},
  {name:'Finland',flag:'🇫🇮'},
  {name:'Switzerland',flag:'🇨🇭'},
  {name:'Austria',flag:'🇦🇹'},
  {name:'Portugal',flag:'🇵🇹'},
  {name:'Greece',flag:'🇬🇷'},
  {name:'Czech Republic',flag:'🇨🇿'},
  {name:'Hungary',flag:'🇭🇺'},
  {name:'Romania',flag:'🇷🇴'},
  {name:'Ukraine',flag:'🇺🇦'},
  {name:'Israel',flag:'🇮🇱'},
  {name:'Iraq',flag:'🇮🇶'},
  {name:'Iran',flag:'🇮🇷'},
  {name:'Afghanistan',flag:'🇦🇫'},
  {name:'Kazakhstan',flag:'🇰🇿'},
  {name:'Uzbekistan',flag:'🇺🇿'},
  {name:'Taiwan',flag:'🇹🇼'},
  {name:'Hong Kong',flag:'🇭🇰'},
  {name:'Cambodia',flag:'🇰🇭'},
  {name:'Laos',flag:'🇱🇦'},
  {name:'Mongolia',flag:'🇲🇳'},
  {name:'Nepal',flag:'🇳🇵'},
  {name:'Bhutan',flag:'🇧🇹'},
  {name:'Maldives',flag:'🇲🇻'},
  {name:'East Timor',flag:'🇹🇱'},
  {name:'Papua New Guinea',flag:'🇵🇬'},
  {name:'Fiji',flag:'🇫🇯'},
  {name:'Brunei',flag:'🇧🇳'},
  {name:'Chile',flag:'🇨🇱'},
  {name:'Colombia',flag:'🇨🇴'},
  {name:'Peru',flag:'🇵🇪'},
  {name:'Venezuela',flag:'🇻🇪'},
  {name:'Kenya',flag:'🇰🇪'},
  {name:'Ethiopia',flag:'🇪🇹'},
  {name:'Tanzania',flag:'🇹🇿'},
  {name:'Ghana',flag:'🇬🇭'},
  {name:'Algeria',flag:'🇩🇿'},
  {name:'Sudan',flag:'🇸🇩'},
  {name:'Angola',flag:'🇦🇴'},
];

const countryFlagMap={};
COUNTRIES.forEach(c=>countryFlagMap[c.name.toLowerCase()]=c.flag);

function getFlagFor(name){
  const k=name.trim().toLowerCase();
  for(const c of COUNTRIES){if(c.name.toLowerCase()===k)return c.flag;}
  return '🌍';
}

function renderDrop(id,filtered,fieldId,flagId){
  const drop=document.getElementById(id);
  if(!filtered.length){drop.style.display='none';return;}
  drop.innerHTML=filtered.slice(0,15).map(c=>`<div onclick="selectCountry('${fieldId}','${flagId}','${id}','${c.name}','${c.flag}')" style="padding:9px 14px;cursor:pointer;display:flex;align-items:center;gap:10px;font-size:13px;transition:background 0.1s" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">${c.flag} <span>${c.name}</span></div>`).join('');
  drop.style.display='block';
}

function filterCountry(which,val){
  const {inputId,flagId,dropId}=getIds(which);
  const q=val.toLowerCase();
  const filtered=COUNTRIES.filter(c=>c.name.toLowerCase().includes(q));
  renderDrop(dropId,filtered,inputId,flagId);
  const exact=COUNTRIES.find(c=>c.name.toLowerCase()===q);
  document.getElementById(flagId).innerText=exact?exact.flag:'🌍';
}

function showCountryDrop(which){
  const {inputId,flagId,dropId}=getIds(which);
  const val=document.getElementById(inputId).value;
  const q=val.toLowerCase();
  const filtered=q?COUNTRIES.filter(c=>c.name.toLowerCase().includes(q)):COUNTRIES;
  renderDrop(dropId,filtered,inputId,flagId);
}

function hideCountryDrop(which,delay){
  setTimeout(()=>{
    const {dropId}=getIds(which);
    document.getElementById(dropId).style.display='none';
  },delay);
}

function selectCountry(inputId,flagId,dropId,name,flag){
  document.getElementById(inputId).value=name;
  document.getElementById(flagId).innerText=flag;
  document.getElementById(dropId).style.display='none';
}

function getIds(which){
  if(which==='asal') return {inputId:'asalInput',flagId:'asalFlag',dropId:'asalDrop'};
  return {inputId:'tujuanInput',flagId:'tujuanFlag',dropId:'tujuanDrop'};
}

// Init flags for pre-filled values
(function(){
  const av=document.getElementById('asalInput').value;
  if(av) document.getElementById('asalFlag').innerText=getFlagFor(av);
  const tv=document.getElementById('tujuanInput').value;
  if(tv) document.getElementById('tujuanFlag').innerText=getFlagFor(tv);
})();
</script>
<?php include '../includes/layout_footer.php'; ?>

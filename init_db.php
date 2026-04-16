<?php
require_once 'includes/config.php';
$db = getDB();

$db->exec("
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS quarantine;
DROP TABLE IF EXISTS customs;
DROP TABLE IF EXISTS approvals;
DROP TABLE IF EXISTS booking_documents;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS users;
");

$db->exec("
CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password TEXT NOT NULL, full_name TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'user', company TEXT, email TEXT, phone TEXT, avatar_color TEXT DEFAULT '#0b5ede', dark_mode INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, reset_token TEXT, reset_token_expiry DATETIME, last_login DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_no TEXT UNIQUE NOT NULL, user_id INTEGER, nama_pt TEXT NOT NULL, jenis_kulit TEXT NOT NULL, berat REAL NOT NULL, ukuran_kontainer TEXT NOT NULL, jumlah_kontainer INTEGER DEFAULT 1, tanggal_kirim DATE NOT NULL, asal_negara TEXT DEFAULT 'Indonesia', tujuan_negara TEXT, catatan TEXT, status TEXT DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE booking_documents (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL, filename TEXT NOT NULL, original_name TEXT NOT NULL, file_size INTEGER DEFAULT 0, file_type TEXT, uploaded_by INTEGER, doc_type TEXT DEFAULT 'pendukung', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE approvals (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL, approved_by INTEGER, action TEXT NOT NULL, catatan TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE customs (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL, no_dokumen TEXT, status TEXT DEFAULT 'pending', petugas_id INTEGER, catatan TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE quarantine (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL, no_sertifikat TEXT, status TEXT DEFAULT 'pending', petugas_id INTEGER, catatan TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE invoices (id INTEGER PRIMARY KEY AUTOINCREMENT, booking_id INTEGER NOT NULL, invoice_no TEXT UNIQUE NOT NULL, subtotal REAL NOT NULL, tax REAL NOT NULL DEFAULT 0, total_amount REAL NOT NULL, status TEXT DEFAULT 'unpaid', payment_method TEXT, payment_va_number TEXT, payment_bank TEXT, due_date DATE, paid_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, type TEXT DEFAULT 'info', is_read INTEGER DEFAULT 0, link TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE verification_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, email TEXT NOT NULL, code TEXT NOT NULL, type TEXT DEFAULT 'password_reset', used INTEGER DEFAULT 0, expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
");

$pw = password_hash('password', PASSWORD_DEFAULT);
$stmt=$db->prepare("INSERT INTO users(id,username,password,full_name,role,company,email,phone,avatar_color) VALUES(?,?,?,?,?,?,?,?,?)");
$stmt->execute([1,'admin',$pw,'Administrator TPS','admin','PT TPS','admin@tps.co.id','08123456789','#1a56db']);
$stmt->execute([2,'operator',$pw,'Budi Santoso','operator','PT TPS','operator@tps.co.id','08123456780','#0e9f6e']);
$stmt->execute([3,'customs',$pw,'Siti Rahayu','customs','Bea & Cukai Surabaya','customs@beacukai.go.id','08123456781','#6875f5']);
$stmt->execute([4,'quarantine',$pw,'Drs. Ahmad Fauzi','quarantine','Karantina Pertanian','karantina@pertanian.go.id','08123456782','#f05252']);
$stmt->execute([5,'ptmakmur',$pw,'PT Makmur Jaya Abadi','user','PT Makmur Jaya Abadi','info@makmurjaya.co.id','08123456783','#ff5a1f']);
$stmt->execute([6,'ptsumber',$pw,'PT Sumber Rejeki Utama','user','PT Sumber Rejeki Utama','info@sumberrejeki.co.id','08123456784','#0694a2']);
$stmt->execute([7,'ptalam',$pw,'PT Alam Raya Nusantara','user','PT Alam Raya Nusantara','info@alamraya.co.id','08123456785','#7e3af2']);

$jenisList=['Kulit Sapi','Kulit Kambing','Kulit Domba'];
$kontList=['20 Feet','40 Feet','40 HC'];
$destList=['China','Japan','South Korea','Germany','USA','Netherlands','Italy','France','Australia','Malaysia','Singapore','United Kingdom','India','Taiwan','Belgium','Spain'];
$rateMap=['Kulit Sapi'=>3000,'Kulit Kambing'=>2500,'Kulit Domba'=>2200];
$cfeeMap=['20 Feet'=>600000,'40 Feet'=>950000,'40 HC'=>1100000];
$banks=['BCA','Mandiri','BNI','BRI','BSI','CIMB','Permata','Danamon'];
$vaPrefix=['BCA'=>'8808','Mandiri'=>'8889','BNI'=>'9889','BRI'=>'8882','BSI'=>'9347','CIMB'=>'7080','Permata'=>'8215','Danamon'=>'9900'];
$ptList=[5=>['PT Makmur Jaya Abadi'],6=>['PT Sumber Rejeki Utama'],7=>['PT Alam Raya Nusantara']];
$beratOptions=[700,800,900,1000,1100,1200,1300,1400,1500,1600,1800,2000,2200,2400];
$catatanList=['Pengiriman sesuai jadwal.','Perlu penanganan khusus container.','Kondisi kulit sudah diproses garaman.','Dokumen lengkap terlampir.','Pengiriman batch reguler bulanan.'];

$bkStmt=$db->prepare("INSERT INTO bookings(booking_no,user_id,nama_pt,jenis_kulit,berat,ukuran_kontainer,jumlah_kontainer,tanggal_kirim,asal_negara,tujuan_negara,catatan,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$apStmt=$db->prepare("INSERT INTO approvals(booking_id,approved_by,action,catatan,created_at) VALUES(?,?,?,?,?)");
$cuStmt=$db->prepare("INSERT INTO customs(booking_id,no_dokumen,status,petugas_id,catatan,created_at,updated_at) VALUES(?,?,?,?,?,?,?)");
$qaStmt=$db->prepare("INSERT INTO quarantine(booking_id,no_sertifikat,status,petugas_id,catatan,created_at,updated_at) VALUES(?,?,?,?,?,?,?)");
$invStmt=$db->prepare("INSERT INTO invoices(booking_id,invoice_no,subtotal,tax,total_amount,status,payment_method,payment_va_number,payment_bank,due_date,paid_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");

// Per company per month booking counts
$monthCounts=[
    5=>[9,10,8,11,10,12,9,13,11,10,8,7],
    6=>[7,8,10,9,12,11,9,12,10,8,9,7],
    7=>[8,7,11,10,9,12,10,11,13,9,8,8],
];

$bkSeq=0; $invSeq=0; $cuSeq=0; $qaSeq=0;
$insertedBkIds=[];

foreach($ptList as $uid=>$ptInfo) {
    $ptName=$ptInfo[0];
    for($month=1;$month<=12;$month++) {
        $count=$monthCounts[$uid][$month-1];
        for($i=0;$i<$count;$i++) {
            $bkSeq++;
            $day=min(27,1+$i*2);
            $hour=8+($i%9);
            $created=sprintf('2025-%02d-%02d %02d:00:00',$month,$day,$hour);
            $jenis=$jenisList[$i%3];
            $berat=$beratOptions[$i%count($beratOptions)];
            $kont=$kontList[$i%3];
            $jumlah=($i%3)+1;
            $dest=$destList[$i%count($destList)];
            $kirim=sprintf('2025-%02d-%02d',$month,min(28,$day+8));
            $cat=$catatanList[$i%count($catatanList)];
            $bkNo='BK-2025-'.str_pad($bkSeq,4,'0',STR_PAD_LEFT);

            // Status distribution: older months mostly completed
            if($month<=8){
                $statusPool=['completed','completed','completed','completed','completed','completed','rejected','completed','completed'];
            } elseif($month<=10){
                $statusPool=['completed','completed','completed','invoice','quarantine','customs','approved','completed','rejected'];
            } else {
                $statusPool=['completed','invoice','quarantine','customs','approved','pending','completed','pending','rejected'];
            }
            $st=$statusPool[$i%count($statusPool)];

            $bkStmt->execute([$bkNo,$uid,$ptName,$jenis,$berat,$kont,$jumlah,$kirim,'Indonesia',$dest,$cat,$st,$created,$created]);
            $bid=(int)$db->lastInsertId();
            $insertedBkIds[]=['id'=>$bid,'status'=>$st,'bno'=>$bkNo,'uid'=>$uid,'ptName'=>$ptName,'jenis'=>$jenis,'berat'=>$berat,'kont'=>$kont,'jumlah'=>$jumlah,'month'=>$month,'day'=>$day,'hour'=>$hour];
        }
    }
}

foreach($insertedBkIds as $bk) {
    $bid=$bk['id']; $st=$bk['status']; $m=$bk['month']; $d=$bk['day']; $h=$bk['hour'];
    $jenis=$bk['jenis']; $berat=$bk['berat']; $kont=$bk['kont']; $jumlah=$bk['jumlah'];
    $sub=($berat*$rateMap[$jenis])+($cfeeMap[$kont]*$jumlah);
    $tax=round($sub*0.12); $total=$sub+$tax;
    $apDate=sprintf('2025-%02d-%02d %02d:30:00',$m,min(28,$d+1),$h);
    $cuDate=sprintf('2025-%02d-%02d %02d:00:00',$m,min(28,$d+3),$h);
    $qaDate=sprintf('2025-%02d-%02d %02d:30:00',$m,min(28,$d+5),$h);
    $invDate=sprintf('2025-%02d-%02d %02d:00:00',$m,min(28,$d+7),$h);
    $paidDate=sprintf('2025-%02d-%02d %02d:00:00',$m,min(28,$d+9),$h);

    if(in_array($st,['approved','customs','quarantine','invoice','completed'])) {
        $apStmt->execute([$bid,1,'approve','Dokumen lengkap dan valid, disetujui.',$apDate]);
    } elseif($st==='rejected') {
        $apStmt->execute([$bid,1,'reject','Dokumen tidak memenuhi syarat, harap upload ulang.',$apDate]);
    }

    if(in_array($st,['customs','quarantine','invoice','completed'])) {
        $cuSeq++; $cuDoc='BC-2025-'.str_pad($cuSeq,4,'0',STR_PAD_LEFT);
        $cuStatus=in_array($st,['quarantine','invoice','completed'])?'clear':'pending';
        $cuCat=$cuStatus==='clear'?'Dokumen bea cukai diperiksa dan dinyatakan clear.':null;
        $cuStmt->execute([$bid,$cuDoc,$cuStatus,3,$cuCat,$cuDate,$cuDate]);
    }

    if(in_array($st,['quarantine','invoice','completed'])) {
        $qaSeq++; $qaSert='KT-2025-'.str_pad($qaSeq,4,'0',STR_PAD_LEFT);
        $qaStatus=in_array($st,['invoice','completed'])?'clear':'pending';
        $qaCat=$qaStatus==='clear'?'Komoditas bebas hama dan penyakit. Sertifikat diterbitkan.':null;
        $qaStmt->execute([$bid,$qaSert,$qaStatus,4,$qaCat,$qaDate,$qaDate]);
    }

    if(in_array($st,['invoice','completed'])) {
        $invSeq++;
        $bank=$banks[$invSeq%count($banks)];
        $vaNum=$vaPrefix[$bank].'0'.str_pad((string)(1000000+$invSeq),7,'0',STR_PAD_LEFT);
        $invNo2='INV-2025-'.str_pad($invSeq,4,'0',STR_PAD_LEFT);
        $dueDate=sprintf('2025-%02d-%02d',$m,min(28,$d+14));
        $invSt=$st==='completed'?'paid':'unpaid';
        $paidAt=$st==='completed'?$paidDate:null;
        $invStmt->execute([$bid,$invNo2,$sub,$tax,$total,$invSt,'virtual_account',$vaNum,$bank,$dueDate,$paidAt,$invDate]);
    }
}

// 2026 bookings
$bk2026=[
    ['BK-2026-001',5,'PT Makmur Jaya Abadi','Kulit Sapi',1200,'40 Feet',1,'2026-01-15','Indonesia','China','completed','2026-01-01 08:00:00'],
    ['BK-2026-002',5,'PT Makmur Jaya Abadi','Kulit Kambing',800,'20 Feet',1,'2026-01-28','Indonesia','South Korea','completed','2026-01-10 09:00:00'],
    ['BK-2026-003',6,'PT Sumber Rejeki Utama','Kulit Sapi',1800,'40 Feet',2,'2026-02-10','Indonesia','Japan','completed','2026-02-01 10:00:00'],
    ['BK-2026-004',7,'PT Alam Raya Nusantara','Kulit Domba',600,'20 Feet',1,'2026-02-20','Indonesia','USA','completed','2026-02-08 11:00:00'],
    ['BK-2026-005',5,'PT Makmur Jaya Abadi','Kulit Sapi',2000,'40 HC',2,'2026-03-05','Indonesia','Germany','completed','2026-02-20 08:00:00'],
    ['BK-2026-006',6,'PT Sumber Rejeki Utama','Kulit Kambing',950,'20 Feet',2,'2026-03-10','Indonesia','Malaysia','quarantine','2026-03-01 09:00:00'],
    ['BK-2026-007',7,'PT Alam Raya Nusantara','Kulit Sapi',1600,'40 Feet',1,'2026-03-15','Indonesia','China','customs','2026-03-05 10:00:00'],
    ['BK-2026-008',5,'PT Makmur Jaya Abadi','Kulit Domba',750,'20 Feet',1,'2026-03-20','Indonesia','Japan','approved','2026-03-08 08:00:00'],
    ['BK-2026-009',6,'PT Sumber Rejeki Utama','Kulit Sapi',2200,'40 HC',3,'2026-03-25','Indonesia','South Korea','pending','2026-03-10 09:00:00'],
    ['BK-2026-010',7,'PT Alam Raya Nusantara','Kulit Kambing',880,'20 Feet',1,'2026-03-28','Indonesia','China','rejected','2026-03-12 10:00:00'],
    ['BK-2026-011',5,'PT Makmur Jaya Abadi','Kulit Sapi',1400,'40 Feet',1,'2026-04-05','Indonesia','USA','pending','2026-03-14 08:00:00'],
    ['BK-2026-012',6,'PT Sumber Rejeki Utama','Kulit Domba',1100,'20 Feet',2,'2026-04-08','Indonesia','Netherlands','invoice','2026-03-20 09:00:00'],
    ['BK-2026-013',7,'PT Alam Raya Nusantara','Kulit Sapi',1700,'40 Feet',1,'2026-04-12','Indonesia','Taiwan','approved','2026-03-22 10:00:00'],
];
$s2=$db->prepare("INSERT INTO bookings(booking_no,user_id,nama_pt,jenis_kulit,berat,ukuran_kontainer,jumlah_kontainer,tanggal_kirim,asal_negara,tujuan_negara,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
foreach($bk2026 as $b) $s2->execute($b);

$rows26=$db->query("SELECT id,booking_no,jenis_kulit,berat,ukuran_kontainer,jumlah_kontainer FROM bookings WHERE booking_no LIKE 'BK-2026-%' ORDER BY id")->fetchAll();
$m26=['BK-2026-001'=>'completed','BK-2026-002'=>'completed','BK-2026-003'=>'completed','BK-2026-004'=>'completed','BK-2026-005'=>'completed','BK-2026-006'=>'quarantine','BK-2026-007'=>'customs','BK-2026-008'=>'approved','BK-2026-009'=>'pending','BK-2026-010'=>'rejected','BK-2026-011'=>'pending','BK-2026-012'=>'invoice','BK-2026-013'=>'approved'];
$invC2=0;$cuC2=900;$qaC2=900;
foreach($rows26 as $r) {
    $bid2=$r['id']; $bno=$r['booking_no']; $st=$m26[$bno]??'pending';
    $sub2=($r['berat']*$rateMap[$r['jenis_kulit']])+($cfeeMap[$r['ukuran_kontainer']]*$r['jumlah_kontainer']);
    $tax2=round($sub2*0.12); $total2=$sub2+$tax2;
    if(in_array($st,['approved','customs','quarantine','invoice','completed'])) $apStmt->execute([$bid2,1,'approve','Dokumen lengkap.','2026-01-15 09:00:00']);
    if($st==='rejected') $apStmt->execute([$bid2,1,'reject','Dokumen tidak valid.','2026-03-14 10:00:00']);
    if(in_array($st,['customs','quarantine','invoice','completed'])) { $cuC2++; $cuStmt->execute([$bid2,'BC-2026-'.str_pad($cuC2,4,'0',STR_PAD_LEFT),in_array($st,['quarantine','invoice','completed'])?'clear':'pending',3,in_array($st,['quarantine','invoice','completed'])?'Clear.':null,'2026-01-20 10:00:00','2026-01-20 10:00:00']); }
    if(in_array($st,['quarantine','invoice','completed'])) { $qaC2++; $qaStmt->execute([$bid2,'KT-2026-'.str_pad($qaC2,4,'0',STR_PAD_LEFT),in_array($st,['invoice','completed'])?'clear':'pending',4,in_array($st,['invoice','completed'])?'Bebas hama.':null,'2026-01-22 14:00:00','2026-01-22 14:00:00']); }
    if(in_array($st,['invoice','completed'])) {
        $invC2++; $bank2=$banks[$invC2%count($banks)];
        $va2=$vaPrefix[$bank2].'0'.str_pad((string)(2000000+$invC2),7,'0',STR_PAD_LEFT);
        $invStmt->execute([$bid2,'INV-2026-'.str_pad($invC2,4,'0',STR_PAD_LEFT),$sub2,$tax2,$total2,$st==='completed'?'paid':'unpaid','virtual_account',$va2,$bank2,'2026-03-30',$st==='completed'?'2026-02-10 10:00:00':null,'2026-02-01 09:00:00']);
    }
}

// Notifikasi
$nStmt=$db->prepare("INSERT INTO notifications(user_id,title,message,type,is_read,link,created_at) VALUES(?,?,?,?,?,?,?)");
$nStmt->execute([5,'✅ Booking Disetujui','BK-2026-008 telah diapprove oleh Admin TPS.','success',0,'/tps-v2/pages/my_bookings.php','2026-03-10 09:05:00']);
$nStmt->execute([5,'❌ Booking Ditolak','BK-2026-010 ditolak: Dokumen tidak lengkap, harap upload ulang.','danger',0,'/tps-v2/pages/my_bookings.php','2026-03-14 10:05:00']);
$nStmt->execute([6,'🛡️ Proses Karantina','BK-2026-006 lulus Customs dan masuk proses Karantina.','warning',0,'/tps-v2/pages/my_bookings.php','2026-03-05 08:05:00']);
$nStmt->execute([7,'🛂 Proses Customs','BK-2026-007 sedang dalam pemeriksaan Bea Cukai.','info',0,'/tps-v2/pages/my_bookings.php','2026-03-06 09:05:00']);
$nStmt->execute([1,'📋 Booking Baru','BK-2026-009 dari PT Sumber Rejeki menunggu approval.','info',0,'/tps-v2/pages/approvals.php','2026-03-10 09:00:00']);
$nStmt->execute([1,'📋 Booking Baru','BK-2026-011 dari PT Makmur Jaya menunggu approval.','info',0,'/tps-v2/pages/approvals.php','2026-03-14 08:05:00']);
$nStmt->execute([6,'🧾 Invoice Tersedia','Invoice INV-2026-0001 sudah siap dibayar via Virtual Account.','info',0,'/tps-v2/pages/my_bookings.php','2026-03-20 10:00:00']);
$db->prepare("INSERT INTO verification_codes(user_id,email,code,type,expires_at) VALUES(5,'info@makmurjaya.co.id','123456','password_reset','2099-12-31 23:59:59')")->execute();

$totalBk=$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalInv=$db->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$completedBk=$db->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
$paidInv=$db->query("SELECT COUNT(*) FROM invoices WHERE status='paid'")->fetchColumn();
$bk2025=$db->query("SELECT COUNT(*) FROM bookings WHERE booking_no LIKE 'BK-2025-%'")->fetchColumn();
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;max-width:700px;margin:60px auto;padding:20px;background:#f8fafc}.ok{color:#059669;font-size:48px;text-align:center}.card{background:#fff;padding:20px;border-radius:12px;margin:16px 0;box-shadow:0 1px 6px rgba(0,0,0,0.06)}.stat{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:13px}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #e2e8f0;font-size:13px}th{font-weight:700;background:#f1f5f9}h2{text-align:center}a.btn{display:inline-block;padding:12px 24px;background:#0b5ede;color:#fff;text-decoration:none;border-radius:8px;font-weight:700}</style></head><body>';
echo '<div class="ok">✅</div><h2>Database 2025 Berhasil Diinisialisasi!</h2>';
echo '<div class="card"><h3>📊 Statistik</h3>';
echo '<div class="stat"><span>Total Booking (2025+2026)</span><strong>'.$totalBk.'</strong></div>';
echo '<div class="stat"><span>Booking 2025</span><strong>'.$bk2025.'</strong></div>';
echo '<div class="stat"><span>Booking Completed</span><strong>'.$completedBk.'</strong></div>';
echo '<div class="stat"><span>Total Invoice</span><strong>'.$totalInv.'</strong></div>';
echo '<div class="stat"><span>Invoice Paid</span><strong>'.$paidInv.'</strong></div>';
echo '</div>';
echo '<div class="card"><h3>👤 Akun Demo (password: <code>password</code>)</h3><table><tr><th>Username</th><th>Role</th><th>Perusahaan</th></tr>';
foreach([['admin','Admin TPS','PT TPS'],['operator','Operator','PT TPS'],['customs','Bea Cukai','Bea & Cukai Surabaya'],['quarantine','Karantina','Karantina Pertanian'],['ptmakmur','User PT','PT Makmur Jaya Abadi'],['ptsumber','User PT','PT Sumber Rejeki Utama'],['ptalam','User PT','PT Alam Raya Nusantara']] as [$u,$r,$c])
    echo "<tr><td><strong>$u</strong></td><td>$r</td><td>$c</td></tr>";
echo '</table></div>';
echo '<p style="text-align:center"><a class="btn" href="index.php">Ke Halaman Login →</a></p></body></html>';
?>

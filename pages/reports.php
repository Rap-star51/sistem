<?php
session_start();
require_once '../includes/config.php';
requireRole('admin');
$pageTitle='Laporan'; $activePage='reports';
$db=getDB();
$year=(int)($_GET['year']??date('Y'));
$filterMonth=(int)($_GET['month']??0);
$page=max(1,(int)($_GET['page']??1));
$perPage=10;

$monthly=[]; $revenue=[];
for($m=1;$m<=12;$m++){
    $ym=sprintf('%04d-%02d',$year,$m);
    $s=$db->prepare("SELECT COUNT(*) FROM bookings WHERE strftime('%Y-%m',created_at)=?"); $s->execute([$ym]);
    $r=$db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status='paid' AND strftime('%Y-%m',paid_at)=?"); $r->execute([$ym]);
    $monthly[$m]=(int)$s->fetchColumn();
    $revenue[$m]=(float)$r->fetchColumn();
}
$jenisStats=$db->query("SELECT jenis_kulit,COUNT(*) as c,COALESCE(SUM(berat),0) as tb FROM bookings GROUP BY jenis_kulit")->fetchAll();
$topPT=$db->query("SELECT nama_pt,COUNT(*) as c,COALESCE(SUM(berat),0) as tb FROM bookings GROUP BY nama_pt ORDER BY c DESC LIMIT 10")->fetchAll();
$months=['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

// Latest bookings with filter + paging
$whereClause="strftime('%Y',b.created_at)=?";
$params=[(string)$year];
if($filterMonth>0){
    $whereClause="strftime('%Y-%m',b.created_at)=?";
    $params=[sprintf('%04d-%02d',$year,$filterMonth)];
}
$cStmt=$db->prepare("SELECT COUNT(*) FROM bookings b WHERE $whereClause"); $cStmt->execute($params); $totalRows=(int)$cStmt->fetchColumn();
$totalPages=max(1,(int)ceil($totalRows/$perPage));
$offset=($page-1)*$perPage;
$rStmt=$db->prepare("SELECT b.*,u.full_name as uname,i.invoice_no,i.status as inv_status,i.total_amount FROM bookings b LEFT JOIN users u ON b.user_id=u.id LEFT JOIN invoices i ON b.id=i.booking_id WHERE $whereClause ORDER BY b.created_at DESC LIMIT ? OFFSET ?");
$rStmt->execute(array_merge($params,[$perPage,$offset]));
$recentBookings=$rStmt->fetchAll();

include '../includes/layout.php';
?>
<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title"><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Laporan & Statistik</div>
      <div class="breadcrumb"><a href="dashboard.php">Home</a><span class="breadcrumb-sep">/</span><span class="breadcrumb-current">Laporan</span></div>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <label class="form-label" style="margin:0">Tahun:</label>
      <select name="year" class="form-select" style="width:90px" onchange="this.form.submit()">
        <?php foreach([2026,2025,2024] as $y): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endforeach; ?>
      </select>
      <label class="form-label" style="margin:0">Bulan:</label>
      <select name="month" class="form-select" style="width:110px" onchange="this.form.submit()">
        <option value="0" <?= $filterMonth===0?'selected':'' ?>>Semua Bulan</option>
        <?php for($mm=1;$mm<=12;$mm++): ?><option value="<?= $mm ?>" <?= $filterMonth===$mm?'selected':'' ?>><?= $months[$mm] ?></option><?php endfor; ?>
      </select>
    </form>
  </div>
</div>

<div class="card fade-in">
  <div class="card-header"><div class="card-title"><i class="fas fa-chart-line"></i> Booking & Pendapatan Bulanan <?= $year ?></div></div>
  <canvas id="repChart" height="80"></canvas>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card fade-in fade-in-1">
    <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> Distribusi Jenis Kulit</div></div>
    <canvas id="jenisChart" height="180"></canvas>
  </div>
  <div class="card fade-in fade-in-2">
    <div class="card-header"><div class="card-title"><i class="fas fa-building"></i> Top 10 Perusahaan</div></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>#</th><th>Perusahaan</th><th>Booking</th><th>Berat</th></tr></thead>
        <tbody>
        <?php foreach($topPT as $i=>$p): ?>
        <tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($p['nama_pt']) ?></td><td><strong style="color:var(--primary)"><?= $p['c'] ?></strong></td><td style="color:var(--text-secondary)"><?= number_format($p['tb']) ?> kg</td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card fade-in fade-in-3">
  <div class="card-header"><div class="card-title"><i class="fas fa-table"></i> Rekap Bulanan <?= $year ?></div></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Bulan</th><th>Jumlah Booking</th><th>Pendapatan</th><th>Akumulasi Pendapatan</th></tr></thead>
      <tbody>
      <?php $cumul=0; for($m=1;$m<=12;$m++): $cumul+=$revenue[$m]; ?>
      <tr <?= $monthly[$m]===0?'style="color:var(--text-muted)"':'' ?>>
        <td><?= $months[$m].' '.$year ?></td>
        <td><strong style="color:var(--primary)"><?= $monthly[$m] ?></strong></td>
        <td><?= fmtRp($revenue[$m]) ?></td>
        <td><?= fmtRp($cumul) ?></td>
      </tr>
      <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--surface-2);font-weight:700">
          <td>TOTAL <?= $year ?></td>
          <td><?= array_sum($monthly) ?></td>
          <td><?= fmtRp(array_sum($revenue)) ?></td>
          <td>—</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- REKAP BOOKING TERBARU WITH PAGING -->
<div class="card fade-in fade-in-4">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-clock-rotate-left"></i> Rekap Booking Terbaru
      <span style="font-size:12px;font-weight:400;color:var(--text-muted)">
        — <?= $filterMonth>0?$months[$filterMonth].' ':'Semua Bulan ' ?><?= $year ?>
        (<?= $totalRows ?> data)
      </span>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>No</th><th>Booking No</th><th>Perusahaan</th><th>Jenis</th><th>Berat</th><th>Kontainer</th><th>Tgl Buat</th><th>Status</th><th>Invoice</th><th></th></tr></thead>
      <tbody>
      <?php if(empty($recentBookings)): ?>
        <tr><td colspan="10" style="text-align:center;padding:32px;color:var(--text-muted)">Tidak ada data untuk filter ini</td></tr>
      <?php endif; ?>
      <?php foreach($recentBookings as $i=>$b): ?>
      <tr>
        <td style="color:var(--text-muted)"><?= ($offset+$i+1) ?></td>
        <td><span style="font-weight:800;color:var(--primary);font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($b['booking_no']) ?></span></td>
        <td><div style="font-weight:600"><?= htmlspecialchars($b['nama_pt']) ?></div><div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($b['uname']??'') ?></div></td>
        <td><?= htmlspecialchars($b['jenis_kulit']) ?></td>
        <td><?= number_format($b['berat']) ?> kg</td>
        <td><?= htmlspecialchars($b['ukuran_kontainer']) ?> ×<?= $b['jumlah_kontainer'] ?></td>
        <td style="font-size:12px"><?= fmtDate(substr($b['created_at'],0,10)) ?></td>
        <td><?= badge($b['status']) ?></td>
        <td><?php if($b['invoice_no']): ?><span style="font-size:11px;font-family:var(--font-mono)"><?= htmlspecialchars($b['invoice_no']) ?></span><br><?= badge($b['inv_status']??'unpaid') ?><?php else: ?><span style="color:var(--text-muted);font-size:11px">—</span><?php endif; ?></td>
        <td><a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-xs"><i class="fas fa-eye"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- PAGING -->
  <?php if($totalPages>1): ?>
  <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0 4px;flex-wrap:wrap;gap:8px">
    <div style="font-size:12px;color:var(--text-muted)">
      Halaman <?= $page ?> dari <?= $totalPages ?> (<?= $totalRows ?> data)
    </div>
    <div style="display:flex;gap:4px;flex-wrap:wrap">
      <?php
      $baseUrl="reports.php?year=$year&month=$filterMonth";
      $start=max(1,$page-2); $end=min($totalPages,$page+2);
      ?>
      <?php if($page>1): ?><a href="<?= $baseUrl ?>&page=1" class="btn btn-ghost btn-xs"><i class="fas fa-angle-double-left"></i></a><a href="<?= $baseUrl ?>&page=<?= $page-1 ?>" class="btn btn-ghost btn-xs"><i class="fas fa-angle-left"></i></a><?php endif; ?>
      <?php for($pg=$start;$pg<=$end;$pg++): ?>
        <a href="<?= $baseUrl ?>&page=<?= $pg ?>" class="btn btn-xs <?= $pg===$page?'btn-primary':'btn-ghost' ?>"><?= $pg ?></a>
      <?php endfor; ?>
      <?php if($page<$totalPages): ?><a href="<?= $baseUrl ?>&page=<?= $page+1 ?>" class="btn btn-ghost btn-xs"><i class="fas fa-angle-right"></i></a><a href="<?= $baseUrl ?>&page=<?= $totalPages ?>" class="btn btn-ghost btn-xs"><i class="fas fa-angle-double-right"></i></a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
const months=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
const mData=<?= json_encode(array_values($monthly)) ?>;
const rData=<?= json_encode(array_values($revenue)) ?>;
const isDark=()=>document.documentElement.getAttribute('data-theme')==='dark';
const gc=()=>isDark()?'rgba(255,255,255,0.06)':'rgba(0,0,0,0.05)';
const tc=()=>isDark()?'#94a3b8':'#64748b';
Chart.defaults.font.family="'Plus Jakarta Sans',sans-serif";
new Chart(document.getElementById('repChart'),{
  data:{labels:months,datasets:[
    {type:'bar',label:'Booking',data:mData,backgroundColor:'rgba(11,94,222,0.18)',borderColor:'#0b5ede',borderWidth:2,borderRadius:6,yAxisID:'y'},
    {type:'line',label:'Pendapatan (Rp)',data:rData,borderColor:'#059669',backgroundColor:'rgba(5,150,105,0.08)',fill:true,tension:0.4,pointBackgroundColor:'#059669',pointRadius:4,borderWidth:2,yAxisID:'y1'}
  ]},
  options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{color:tc()}}},scales:{
    y:{beginAtZero:true,position:'left',grid:{color:gc()},ticks:{color:tc()},title:{display:true,text:'Jumlah Booking',color:tc()}},
    y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},ticks:{callback:v=>'Rp '+(v/1000000).toFixed(1)+'M',color:tc()},title:{display:true,text:'Pendapatan',color:tc()}}
  }}
});
const jl=<?= json_encode(array_column($jenisStats,'jenis_kulit')) ?>;
const jd=<?= json_encode(array_column($jenisStats,'c')) ?>;
new Chart(document.getElementById('jenisChart'),{type:'doughnut',data:{labels:jl.length?jl:['No Data'],datasets:[{data:jd.length?jd:[1],backgroundColor:['#0b5ede','#f59e0b','#059669'],borderWidth:3,borderColor:'var(--surface)'}]},options:{responsive:true,cutout:'60%',plugins:{legend:{position:'right',labels:{color:tc()}}}}});
</script>
<?php include '../includes/layout_footer.php'; ?>

<?php
session_start();
require_once '../includes/config.php';
requireLogin();
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$db = getDB();
$u = user();

// Stats
$totalBookings  = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pendingB       = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$approvedB      = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='approved'")->fetchColumn();
$completedB     = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
$customsB       = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='customs'")->fetchColumn();
$quarantineB    = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='quarantine'")->fetchColumn();
$totalRevPaid   = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status='paid'")->fetchColumn();
$unpaidInvCount = (int)$db->query("SELECT COUNT(*) FROM invoices WHERE status='unpaid'")->fetchColumn();
$totalUsers     = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Monthly bookings (last 12 months)
$monthlyLabels = [];
$monthlyData = [];
$monthlyRevData = [];
for ($i = 11; $i >= 0; $i--) {
  $ym = date('Y-m', strtotime("-$i months"));
  $lbl = date('M', strtotime("-$i months"));
  $s = $db->prepare("SELECT COUNT(*) FROM bookings WHERE strftime('%Y-%m',created_at)=?");
  $s->execute([$ym]);
  $r = $db->prepare("SELECT COALESCE(SUM(i.total_amount),0) FROM invoices i WHERE i.status='paid' AND strftime('%Y-%m',i.paid_at)=?");
  $r->execute([$ym]);
  $monthlyLabels[] = $lbl;
  $monthlyData[]   = (int)$s->fetchColumn();
  $monthlyRevData[] = (float)$r->fetchColumn();
}

// Status distribution
$statusDist = $db->query("SELECT status,COUNT(*) as c FROM bookings GROUP BY status")->fetchAll();
$jenisStats = $db->query("SELECT jenis_kulit,COUNT(*) as c,COALESCE(SUM(berat),0) as tb FROM bookings GROUP BY jenis_kulit")->fetchAll();
$topPT      = $db->query("SELECT nama_pt,COUNT(*) as c,COALESCE(SUM(berat),0) as tb FROM bookings GROUP BY nama_pt ORDER BY c DESC LIMIT 5")->fetchAll();
$recent     = $db->query("SELECT b.*,u.full_name as uname FROM bookings b LEFT JOIN users u ON b.user_id=u.id ORDER BY b.created_at DESC LIMIT 7")->fetchAll();

// Quick role-based stats
if (isRole('customs')) {
  $myStat = (int)$db->prepare("SELECT COUNT(*) FROM customs WHERE petugas_id=?")->execute([uid()]) ? (int)$db->prepare("SELECT COUNT(*) FROM customs WHERE petugas_id=?")->execute([uid()]) : 0;
  $s = $db->prepare("SELECT COUNT(*) FROM customs WHERE petugas_id=?");
  $s->execute([uid()]);
  $myStatVal = (int)$s->fetchColumn();
}
if (isRole('quarantine')) {
  $s = $db->prepare("SELECT COUNT(*) FROM quarantine WHERE petugas_id=?");
  $s->execute([uid()]);
  $myStatVal = (int)$s->fetchColumn();
}

include '../includes/layout.php';
?>

<div class="page-hero fade-in">
  <div class="page-hero-inner">
    <div>
      <div class="page-title">Dashboard <span style="font-size:14px;font-weight:500;color:var(--text-secondary)">— <?= date('l, d F Y') ?></span></div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:2px">
        Selamat datang, <strong><?= htmlspecialchars($u['full_name']) ?></strong> · <?= roleLabel($u['role']) ?>
      </div>
    </div>
  </div>
</div>

<!-- ── STAT CARDS ── -->
<div class="stat-grid">
  <div class="stat-card blue fade-in fade-in-1">
    <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8"><i class="fas fa-clipboard-list"></i></div>
    <div class="stat-info">
      <div class="stat-val"><?= number_format($totalBookings) ?></div>
      <div class="stat-lbl">Total Booking</div>
      <div class="stat-delta up"><i class="fas fa-arrow-up"></i> +3 bulan ini</div>
    </div>
  </div>
  <div class="stat-card orange fade-in fade-in-2">
    <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-hourglass-half"></i></div>
    <div class="stat-info">
      <div class="stat-val"><?= $pendingB ?></div>
      <div class="stat-lbl">Menunggu Approval</div>
      <?php if ($pendingB > 0): ?><div class="stat-delta" style="color:var(--warning)"><i class="fas fa-exclamation"></i> Perlu tindakan</div><?php endif; ?>
    </div>
  </div>
  <div class="stat-card purple fade-in fade-in-3">
    <div class="stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="fas fa-spinner"></i></div>
    <div class="stat-info">
      <div class="stat-val"><?= $customsB + $quarantineB ?></div>
      <div class="stat-lbl">Sedang Diproses</div>
      <div class="stat-delta" style="color:var(--purple)">Customs:<?= $customsB ?> / QC:<?= $quarantineB ?></div>
    </div>
  </div>
  <div class="stat-card green fade-in fade-in-4">
    <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-trophy"></i></div>
    <div class="stat-info">
      <div class="stat-val"><?= $completedB ?></div>
      <div class="stat-lbl">Selesai</div>
      <div class="stat-delta up"><i class="fas fa-arrow-up"></i> On track</div>
    </div>
  </div>
  <div class="stat-card green fade-in fade-in-5">
    <div class="stat-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-money-bill-wave"></i></div>
    <div class="stat-info">
      <div class="stat-val" style="font-size:18px"><?= 'Rp ' . number_format($totalRevPaid / 1000000, 1) . 'M' ?></div>
      <div class="stat-lbl">Pendapatan Terkumpul</div>
      <div class="stat-delta up"><i class="fas fa-arrow-up"></i> Lunas</div>
    </div>
  </div>
  <div class="stat-card red fade-in fade-in-6">
    <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-file-invoice-dollar"></i></div>
    <div class="stat-info">
      <div class="stat-val"><?= $unpaidInvCount ?></div>
      <div class="stat-lbl">Invoice Belum Bayar</div>
      <?php if ($unpaidInvCount > 0): ?><div class="stat-delta down"><i class="fas fa-exclamation-circle"></i> Perlu ditagih</div><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── CHARTS ROW 1 ── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px" class="fade-in fade-in-2">

  <!-- Monthly Booking Chart -->
  <div class="card" style="margin:0">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-chart-bar"></i> Statistik Booking & Pendapatan</div>
      <select id="chartMode" class="form-select" style="width:130px;padding:5px 10px;font-size:12px" onchange="updateMainChart()">
        <option value="booking">Jumlah Booking</option>
        <option value="revenue">Pendapatan</option>
      </select>
    </div>
    <canvas id="mainChart" height="110"></canvas>
  </div>

  <!-- Donut Status -->
  <div class="card" style="margin:0">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-chart-pie"></i> Distribusi Status</div>
    </div>
    <canvas id="donutChart" height="180"></canvas>
    <div id="donutLegend" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px;justify-content:center;font-size:11px"></div>
  </div>
</div>

<!-- ── CHARTS ROW 2 ── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="fade-in fade-in-3">

  <!-- Jenis Kulit -->
  <div class="card" style="margin:0">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-chart-line"></i> Volume per Jenis Kulit</div>
    </div>
    <canvas id="jenisChart" height="150"></canvas>
  </div>

  <!-- Top PT -->
  <div class="card" style="margin:0">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-building"></i> Top Perusahaan</div>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="border-bottom:1px solid var(--border)">
          <th style="padding:8px 4px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">#</th>
          <th style="padding:8px 4px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Perusahaan</th>
          <th style="padding:8px 4px;text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Booking</th>
          <th style="padding:8px 4px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);font-weight:700">Total Berat</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topPT as $ri => $pt): ?>
          <tr style="border-bottom:1px solid var(--border-light)">
            <td style="padding:10px 4px">
              <span style="width:22px;height:22px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800"><?= $ri + 1 ?></span>
            </td>
            <td style="padding:10px 4px;font-weight:600"><?= htmlspecialchars($pt['nama_pt']) ?></td>
            <td style="padding:10px 4px;text-align:center"><span style="font-weight:800;color:var(--primary)"><?= $pt['c'] ?></span></td>
            <td style="padding:10px 4px;text-align:right;color:var(--text-secondary)"><?= number_format($pt['tb']) ?> kg</td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($topPT)): ?><tr>
            <td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Belum ada data</td>
          </tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── RECENT BOOKINGS ── -->
<div class="card fade-in fade-in-4">
  <div class="card-header">
    <div class="card-title"><i class="fas fa-clock-rotate-left"></i> Booking Terbaru</div>
    <a href="approvals.php" class="btn btn-outline btn-sm"><i class="fas fa-list"></i> Lihat Semua</a>
  </div>
  <div style="margin-bottom:12px">
    <input type="text" class="form-control" placeholder="Cari booking..." id="srch" oninput="filterRecent()" style="max-width:280px">
  </div>
  <div class="table-wrap">
    <table class="data-table" id="recentTbl">
      <thead>
        <tr>
          <th>Booking No</th>
          <th>Perusahaan</th>
          <th>Jenis</th>
          <th>Berat</th>
          <th>Kontainer</th>
          <th>Tgl Kirim</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $b): ?>
          <tr>
            <td><span style="font-weight:800;color:var(--primary);font-family:var(--font-mono)"><?= htmlspecialchars($b['booking_no']) ?></span></td>
            <td>
              <div style="font-weight:600"><?= htmlspecialchars($b['nama_pt']) ?></div>
              <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($b['uname'] ?? '') ?></div>
            </td>
            <td><?= htmlspecialchars($b['jenis_kulit']) ?></td>
            <td><?= number_format($b['berat']) ?> kg</td>
            <td><?= htmlspecialchars($b['ukuran_kontainer']) ?> ×<?= $b['jumlah_kontainer'] ?></td>
            <td><?= fmtDate($b['tanggal_kirim']) ?></td>
            <td><?= badge($b['status']) ?></td>
            <td><a href="booking_detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-xs"><i class="fas fa-eye"></i></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── PROCESS FLOW ── -->
<div class="card fade-in fade-in-5" style="background:var(--surface-2)">
  <div class="card-title" style="margin-bottom:16px"><i class="fas fa-route"></i> Alur Proses Booking</div>
  <div style="display:flex;align-items:flex-start;overflow-x:auto;padding-bottom:8px;gap:0">
    <?php
    $flow = [
      ['fa-file-alt', 'New Booking', 'User input', '#1d4ed8'],
      ['fa-check-double', 'Approval', 'Admin review', '#059669'],
      ['fa-passport', 'Customs', 'Bea Cukai', '#7c3aed'],
      ['fa-shield-virus', 'Karantina', 'Pemeriksaan', '#d97706'],
      ['fa-file-invoice-dollar', 'Invoice', 'Pembayaran VA', '#0284c7'],
      ['fa-trophy', 'Selesai', 'Completed', '#f59e0b'],
    ];
    foreach ($flow as $fi => $f): ?>
      <div style="display:flex;align-items:center;min-width:<?= $fi === count($flow) - 1 ? '80' : '120' ?>px">
        <div style="text-align:center;width:80px">
          <div style="width:48px;height:48px;border-radius:14px;background:<?= $f[3] ?>1a;color:<?= $f[3] ?>;display:flex;align-items:center;justify-content:center;font-size:20px;margin:0 auto 6px;border:1.5px solid <?= $f[3] ?>33"><i class="fas <?= $f[0] ?>"></i></div>
          <div style="font-size:11px;font-weight:700;color:var(--text-primary)"><?= $f[1] ?></div>
          <div style="font-size:10px;color:var(--text-muted)"><?= $f[2] ?></div>
        </div>
        <?php if ($fi < count($flow) - 1): ?>
          <div style="flex:1;height:2px;background:linear-gradient(90deg,<?= $f[3] ?>,<?= $flow[$fi + 1][3] ?>);min-width:28px;margin-bottom:20px;opacity:0.5"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
  const ML = <?= json_encode($monthlyLabels) ?>;
  const MD = <?= json_encode($monthlyData) ?>;
  const MR = <?= json_encode($monthlyRevData) ?>;

  const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
  const gridColor = () => isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
  const textColor = () => isDark() ? '#94a3b8' : '#64748b';

  Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

  // Main bar+line chart
  let mainChart = new Chart(document.getElementById('mainChart'), {
    data: {
      labels: ML,
      datasets: [{
        type: 'bar',
        label: 'Booking',
        data: MD,
        backgroundColor: 'rgba(11,94,222,0.18)',
        borderColor: '#0b5ede',
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false
      }, {
        type: 'line',
        label: 'Trend',
        data: MD,
        borderColor: '#f59e0b',
        backgroundColor: 'transparent',
        tension: 0.4,
        pointBackgroundColor: '#f59e0b',
        pointRadius: 4,
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          position: 'top',
          labels: {
            font: {
              size: 11
            },
            color: textColor()
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            color: gridColor()
          },
          ticks: {
            color: textColor()
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            color: textColor()
          }
        }
      }
    }
  });

  function updateMainChart() {
    const mode = document.getElementById('chartMode').value;
    if (mode === 'revenue') {
      mainChart.data.datasets[0].data = MR;
      mainChart.data.datasets[1].data = MR;
      mainChart.data.datasets[0].label = 'Pendapatan';
      mainChart.options.scales.y.ticks.callback = v => 'Rp ' + (v / 1000000).toFixed(1) + 'M';
    } else {
      mainChart.data.datasets[0].data = MD;
      mainChart.data.datasets[1].data = MD;
      mainChart.data.datasets[0].label = 'Booking';
      mainChart.options.scales.y.ticks.callback = v => v;
    }
    mainChart.update();
  }

  // Donut
  const SD = <?= json_encode(array_column($statusDist, 'c')) ?>;
  const SL = <?= json_encode(array_column($statusDist, 'status')) ?>.map(s => s.charAt(0).toUpperCase() + s.slice(1));
  const SC = ['#f59e0b', '#059669', '#dc2626', '#7c3aed', '#0284c7', '#10b981', '#6b7280'];
  new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
      labels: SL,
      datasets: [{
        data: SD,
        backgroundColor: SC,
        borderWidth: 3,
        borderColor: isDark() ? '#111827' : '#ffffff',
        hoverBorderColor: isDark() ? '#1f2937' : '#f1f5f9'
      }]
    },
    options: {
      responsive: true,
      cutout: '68%',
      plugins: {
        legend: {
          display: false
        }
      }
    }
  });
  const dl = document.getElementById('donutLegend');
  SL.forEach((l, i) => {
    dl.innerHTML += `<span style="display:flex;align-items:center;gap:4px;color:var(--text-secondary)"><span style="width:9px;height:9px;border-radius:50%;background:${SC[i]};flex-shrink:0"></span>${l}: <strong>${SD[i]}</strong></span>`;
  });

  // Jenis Bar
  const JL = <?= json_encode(array_column($jenisStats, 'jenis_kulit')) ?>;
  const JD = <?= json_encode(array_column($jenisStats, 'c')) ?>;
  new Chart(document.getElementById('jenisChart'), {
    type: 'bar',
    data: {
      labels: JL.length ? JL : ['No Data'],
      datasets: [{
        data: JD.length ? JD : [0],
        backgroundColor: ['rgba(11,94,222,0.75)', 'rgba(245,158,11,0.75)', 'rgba(5,150,105,0.75)'],
        borderRadius: 8,
        borderWidth: 0
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: {
            color: gridColor()
          },
          ticks: {
            color: textColor()
          }
        },
        y: {
          grid: {
            display: false
          },
          ticks: {
            color: textColor()
          }
        }
      }
    }
  });

  function filterRecent() {
    const q = document.getElementById('srch').value.toLowerCase();
    document.querySelectorAll('#recentTbl tbody tr').forEach(r => {
      r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
  }

  // Redraw charts on theme change
  const obs = new MutationObserver(() => {
    mainChart.update();
  });
  obs.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme']
  });
</script>
<?php include '../includes/layout_footer.php'; ?>
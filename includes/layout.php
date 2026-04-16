<?php
$u = user();
$unread = countUnread(uid());
$pendingCount = isRole('admin', 'operator') ? countPending() : 0;
$isDark = (int)($u['dark_mode'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= $isDark ? 'dark' : 'light' ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="theme-color" content="#0f1d3a">
  <title><?= htmlspecialchars($pageTitle ?? 'TPS') ?> — Sistem Kulit Garaman</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="/tps-v2-updated/assets/css/theme.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <?php if (isset($extraHead)) echo $extraHead; ?>
</head>

<body>
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <header class="app-header">
    <a class="header-brand" href="<?= BASE_URL ?>/pages/dashboard.php">
      <img src="<?= BASE_URL ?>/assets/tps.jpg" class="brand-logo" alt="TPS">
      <div class="brand-title"><span>TPS Webaccess</span><span>Kulit Garaman Lini 2</span></div>
    </a>
    <div class="header-actions">
      <button class="burger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    </div>
    <div class="header-right">
      <button class="dark-toggle" id="darkToggle" onclick="toggleDark()" title="Toggle Dark Mode"><i class="fas fa-moon" id="darkIcon"></i></button>
      <div class="header-divider"></div>
      <div class="dropdown-wrap" id="notifWrap" style="position:relative">
        <button class="header-icon-btn" onclick="togglePanel(event,'notifPanel')">
          <i class="fas fa-bell"></i>
          <?php if ($unread > 0): ?><span class="notif-badge"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </button>
        <div class="notif-panel" id="notifPanel">
          <div class="notif-panel-head">
            <h4><i class="fas fa-bell" style="color:var(--primary);margin-right:6px"></i>Notifikasi</h4>
            <button class="btn btn-xs btn-ghost" onclick="markAllRead()">Tandai Semua</button>
          </div>
          <div class="notif-list">
            <?php
            $ns = $db ?? getDB();
            $nstmt = $ns->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 12");
            $nstmt->execute([uid()]);
            $nlist = $nstmt->fetchAll();
            if (empty($nlist)): ?>
              <div class="notif-empty"><i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:8px"></i>Tidak ada notifikasi</div>
              <?php else: foreach ($nlist as $n): ?>
                <a class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= BASE_URL . htmlspecialchars($n['link'] ?? '#') ?>" onclick="readNotif(<?= $n['id'] ?>)">
                  <div class="notif-dot <?= htmlspecialchars($n['type']) ?>"></div>
                  <div class="notif-content">
                    <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
                    <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                  </div>
                </a>
            <?php endforeach;
            endif; ?>
          </div>
          <div style="padding:12px 16px;border-top:1px solid var(--border)">
            <a href="<?= BASE_URL ?>/pages/notifications.php" class="btn btn-outline btn-sm" style="width:100%;justify-content:center">Lihat Semua Notifikasi</a>
          </div>
        </div>
      </div>
      <div class="header-divider"></div>
      <div class="dropdown-wrap" id="userWrap" style="position:relative">
        <button class="avatar-btn" onclick="togglePanel(event,'userMenu')">
          <div class="avatar" style="background:<?= htmlspecialchars($u['avatar_color'] ?? '#005baa') ?>"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
          <div class="avatar-info">
            <div class="avatar-name"><?= htmlspecialchars($u['full_name']) ?></div>
            <div class="avatar-role"><?= roleLabel($u['role']) ?></div>
          </div>
          <div class="avatar-chevron"><i class="fas fa-chevron-down"></i></div>
        </button>
        <div class="dropdown-menu" id="userMenu">
          <div class="dropdown-header">
            <div class="dh-name"><?= htmlspecialchars($u['full_name']) ?></div>
            <div class="dh-sub"><?= htmlspecialchars($u['company'] ?? '') ?> · <?= roleLabel($u['role']) ?></div>
          </div>
          <a href="<?= BASE_URL ?>/pages/profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i>Profil Saya</a>
          <a href="<?= BASE_URL ?>/pages/notifications.php" class="dropdown-item"><i class="fas fa-bell"></i>Notifikasi <?php if ($unread) echo "<span class='nav-badge'>$unread</span>"; ?></a>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item danger"><i class="fas fa-sign-out-alt"></i>Keluar</a>
        </div>
      </div>
    </div>
  </header>

  <aside class="app-sidebar" id="sidebar">
    <div class="sidebar-section">
      <div class="sidebar-section-label">Menu Utama</div>
    </div>
    <ul class="nav-menu">
      <li><a href="<?= BASE_URL ?>/pages/dashboard.php" class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>" data-tooltip="Dashboard"><span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span><span class="nav-label">Dashboard</span></a></li>
      <?php if (isRole('admin', 'operator', 'user')): ?>
        <li><a href="<?= BASE_URL ?>/pages/new_booking.php" class="nav-link <?= ($activePage ?? '') === 'new_booking' ? 'active' : '' ?>" data-tooltip="New Booking"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span><span class="nav-label">New Booking</span></a></li>
      <?php endif; ?>
      <?php if (isRole('admin', 'operator')): ?>
        <li><a href="<?= BASE_URL ?>/pages/approvals.php" class="nav-link <?= ($activePage ?? '') === 'approvals' ? 'active' : '' ?>" data-tooltip="Approvals"><span class="nav-icon"><i class="fas fa-check-double"></i></span><span class="nav-label">Approvals</span><?php if ($pendingCount > 0) echo "<span class='nav-badge'>$pendingCount</span>"; ?></a></li>
      <?php endif; ?>
      <?php if (isRole('admin', 'customs')): ?>
        <li><a href="<?= BASE_URL ?>/pages/customs.php" class="nav-link <?= ($activePage ?? '') === 'customs' ? 'active' : '' ?>" data-tooltip="Customs"><span class="nav-icon"><i class="fas fa-passport"></i></span><span class="nav-label">Customs</span></a></li>
      <?php endif; ?>
      <?php if (isRole('admin', 'quarantine')): ?>
        <li><a href="<?= BASE_URL ?>/pages/quarantine.php" class="nav-link <?= ($activePage ?? '') === 'quarantine' ? 'active' : '' ?>" data-tooltip="Quarantine"><span class="nav-icon"><i class="fas fa-shield-virus"></i></span><span class="nav-label">Quarantine</span></a></li>
      <?php endif; ?>
      <?php if (isRole('admin', 'operator')): ?>
        <li><a href="<?= BASE_URL ?>/pages/invoices.php" class="nav-link <?= ($activePage ?? '') === 'invoices' ? 'active' : '' ?>" data-tooltip="Invoices"><span class="nav-icon"><i class="fas fa-file-invoice-dollar"></i></span><span class="nav-label">Invoices</span></a></li>
      <?php endif; ?>
      <?php if (isRole('user')): ?>
        <li><a href="<?= BASE_URL ?>/pages/my_bookings.php" class="nav-link <?= ($activePage ?? '') === 'my_bookings' ? 'active' : '' ?>" data-tooltip="Status Booking"><span class="nav-icon"><i class="fas fa-box-open"></i></span><span class="nav-label">Status Booking</span></a></li>
      <?php endif; ?>
    </ul>
    <?php if (isRole('admin')): ?>
      <div class="nav-divider"></div>
      <div class="sidebar-section">
        <div class="sidebar-section-label">Administrasi</div>
      </div>
      <ul class="nav-menu">
        <li><a href="<?= BASE_URL ?>/pages/users.php" class="nav-link <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>" data-tooltip="Users"><span class="nav-icon"><i class="fas fa-users-cog"></i></span><span class="nav-label">Manajemen User</span></a></li>
        <li><a href="<?= BASE_URL ?>/pages/reports.php" class="nav-link <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>" data-tooltip="Laporan"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span><span class="nav-label">Laporan</span></a></li>
      </ul>
    <?php endif; ?>
    <div class="nav-divider"></div>
    <ul class="nav-menu">
      <li><a href="<?= BASE_URL ?>/logout.php" class="nav-link" data-tooltip="Keluar"><span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span><span class="nav-label">Keluar</span></a></li>
    </ul>
  </aside>

  <main class="main-wrap" id="mainWrap">
    <script>
      const SIDEBAR_KEY = 'tps_sb';
      const DARK_KEY = 'tps_dm';
      let sbCollapsed = localStorage.getItem(SIDEBAR_KEY) === '1';

      function applySidebar() {
        const s = document.getElementById('sidebar'),
          m = document.getElementById('mainWrap');
        if (window.innerWidth <= 768) {
          s.classList.remove('collapsed');
          m.classList.remove('sidebar-collapsed');
        } else {
          s.classList.toggle('collapsed', sbCollapsed);
          m.classList.toggle('sidebar-collapsed', sbCollapsed);
        }
      }

      function toggleSidebar() {
        if (window.innerWidth <= 768) {
          document.getElementById('sidebar').classList.toggle('mobile-open');
          document.getElementById('sidebarOverlay').classList.toggle('show');
        } else {
          sbCollapsed = !sbCollapsed;
          localStorage.setItem(SIDEBAR_KEY, sbCollapsed ? '1' : '0');
          applySidebar();
        }
      }

      function closeSidebar() {
        document.getElementById('sidebar').classList.remove('mobile-open');
        document.getElementById('sidebarOverlay').classList.remove('show');
      }
      applySidebar();

      function toggleDark() {
        const h = document.documentElement,
          d = h.getAttribute('data-theme') === 'dark';
        h.setAttribute('data-theme', d ? 'light' : 'dark');
        localStorage.setItem(DARK_KEY, d ? '0' : '1');
        fetch('<?= BASE_URL ?>/api/dark_mode.php?v=' + (d ? 0 : 1));
        updateDarkIcon();
      }

      function updateDarkIcon() {
        const d = document.documentElement.getAttribute('data-theme') === 'dark';
        const ic = document.getElementById('darkIcon');
        if (ic) {
          ic.className = d ? 'fas fa-sun' : 'fas fa-moon';
        }
      }
      (function() {
        const s = localStorage.getItem(DARK_KEY);
        if (s === '1') document.documentElement.setAttribute('data-theme', 'dark');
        if (s === '0') document.documentElement.setAttribute('data-theme', 'light');
        updateDarkIcon();
      })();

      function togglePanel(e, id) {
        e.stopPropagation();
        const el = document.getElementById(id);
        const was = el.classList.contains('show');
        document.querySelectorAll('.notif-panel,.dropdown-menu').forEach(p => p.classList.remove('show'));
        if (!was) el.classList.add('show');
      }
      document.addEventListener('click', () => document.querySelectorAll('.notif-panel,.dropdown-menu').forEach(p => p.classList.remove('show')));

      function markAllRead() {
        fetch('<?= BASE_URL ?>/api/notif.php?action=read_all').then(() => {
          document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
          document.querySelectorAll('.notif-badge').forEach(el => el.remove());
        });
      }

      function readNotif(id) {
        fetch('<?= BASE_URL ?>/api/notif.php?action=read&id=' + id);
      }
      setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
          a.style.transition = 'opacity 0.5s';
          a.style.opacity = '0';
          setTimeout(() => a.remove(), 500);
        });
      }, 5000);
    </script>
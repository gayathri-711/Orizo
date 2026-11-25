<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle ?? 'Orixo Operations') ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.svg') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= asset('css/tokens.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/custom-bootstrap.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/admin.css') ?>" rel="stylesheet">
</head>
<body>
<?php
$isAdmin = ($authUser['role_slug'] ?? '') === 'admin';
$showTeamOnly = !$isAdmin || !empty($isTeamWorkspace);
?>
<div class="admin-shell" data-role="<?= e($authUser['role_slug'] ?? '') ?>">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
      <a href="<?= $isAdmin ? '/admin' : '/admin/workspace' ?>" class="text-decoration-none text-white fw-bold fs-5 d-flex align-items-center gap-2">
        <img src="<?= asset('img/orixo-mark.svg') ?>" alt="Orixo" width="28" height="28">
        <span>Orixo <small class="text-secondary fw-normal fs-6"><?= $showTeamOnly ? 'Team' : 'Admin' ?></small></span>
      </a>
      <button class="btn btn-sm text-white d-lg-none" onclick="$('#adminSidebar').removeClass('show')"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <nav class="nav flex-column pb-3 flex-grow-1 overflow-auto">
      <?php if (!$showTeamOnly && $isAdmin): ?>
        <div class="sidebar-group-label">Core Operations</div>
        <a class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="/admin"><i class="fa-solid fa-chart-pie fa-fw"></i> Dashboard</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'inquiries' ? 'active' : '' ?>" href="/admin/inquiries"><i class="fa-solid fa-inbox fa-fw"></i> Inquiries &amp; Contact</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'clients' ? 'active' : '' ?>" href="/admin/clients"><i class="fa-solid fa-building fa-fw"></i> Clients</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'projects' ? 'active' : '' ?>" href="/admin/projects"><i class="fa-solid fa-diagram-project fa-fw"></i> Projects</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'team' ? 'active' : '' ?>" href="/admin/team"><i class="fa-solid fa-users fa-fw"></i> Team Members</a>

        <div class="sidebar-group-label">Finance</div>
        <a class="nav-link <?= ($activeNav ?? '') === 'invoices' ? 'active' : '' ?>" href="/admin/invoices"><i class="fa-solid fa-file-invoice-dollar fa-fw"></i> Invoices &amp; Payments</a>

        <div class="sidebar-group-label">Agency CMS</div>
        <a class="nav-link <?= ($activeNav ?? '') === 'cms' ? 'active' : '' ?>" href="/admin/cms"><i class="fa-solid fa-layer-group fa-fw"></i> Content &amp; Catalog</a>

        <div class="sidebar-group-label">System &amp; Security</div>
        <a class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>" href="/admin/users"><i class="fa-solid fa-user-shield fa-fw"></i> Users &amp; Roles</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>" href="/admin/settings"><i class="fa-solid fa-sliders fa-fw"></i> Site &amp; Page Settings</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'email_queue' ? 'active' : '' ?>" href="/admin/email-queue"><i class="fa-solid fa-envelope-open-text fa-fw"></i> Email Queue &amp; Templates</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'activity_logs' ? 'active' : '' ?>" href="/admin/activity-logs"><i class="fa-solid fa-clock-rotate-left fa-fw"></i> Activity Logs</a>
      <?php else: ?>
        <div class="sidebar-group-label">Restricted Team Workspace</div>
        <a class="nav-link <?= ($activeNav ?? '') === 'ws_dashboard' ? 'active' : '' ?>" href="/admin/workspace"><i class="fa-solid fa-gauge fa-fw"></i> My Workspace</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'ws_projects' ? 'active' : '' ?>" href="/admin/workspace/projects"><i class="fa-solid fa-folder-tree fa-fw"></i> Assigned Projects</a>
        <a class="nav-link <?= ($activeNav ?? '') === 'ws_profile' ? 'active' : '' ?>" href="/admin/workspace/profile"><i class="fa-regular fa-id-badge fa-fw"></i> My Profile</a>
        <?php if ($isAdmin): ?>
          <div class="sidebar-group-label">Switch View</div>
          <a class="nav-link" href="/admin"><i class="fa-solid fa-arrow-left fa-fw"></i> Full Admin Dashboard</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>

    <div class="p-3 border-top border-secondary border-opacity-25">
      <div class="small text-white fw-semibold text-truncate"><?= e($authUser['full_name'] ?? '') ?></div>
      <div class="small text-secondary text-truncate mb-2"><?= e($authUser['role_name'] ?? '') ?></div>
      <button class="btn btn-outline-light btn-sm w-100 js-orixo-logout"><i class="fa-solid fa-right-from-bracket me-1"></i> Sign Out</button>
    </div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary btn-sm d-lg-none" onclick="$('#adminSidebar').addClass('show')"><i class="fa-solid fa-bars"></i></button>
        <?php if ($showTeamOnly): ?>
          <span class="status-pill status-info"><i class="fa-solid fa-shield-halved"></i> Restricted Team Workspace — Assigned Projects Only</span>
        <?php else: ?>
          <span class="small fw-semibold text-muted">Orixo Enterprise Operations</span>
        <?php endif; ?>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php if ($isAdmin && !$showTeamOnly): ?>
          <a href="/admin/workspace" class="btn btn-light btn-sm">Team View</a>
        <?php endif; ?>
        <a href="/" target="_blank" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Public Site</a>
      </div>
    </header>
    <div class="p-3 p-lg-4 flex-grow-1"><?= $content ?></div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/api-client.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle ?? 'Orixo Client Portal') ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.svg') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= asset('css/tokens.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/custom-bootstrap.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/portal.css') ?>" rel="stylesheet">
</head>
<body>
<div class="portal-shell">
  <aside class="portal-sidebar" id="portalSidebar">
    <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
      <a href="/portal" class="text-decoration-none fw-bold fs-5 text-dark d-flex align-items-center gap-2">
        <img src="<?= asset('img/orixo-mark.svg') ?>" alt="Orixo" width="28" height="28">
        <span>Orixo <small class="text-muted fw-normal fs-6">Portal</small></span>
      </a>
      <button class="btn btn-sm d-lg-none" onclick="$('#portalSidebar').removeClass('show')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <nav class="nav flex-column py-3 flex-grow-1">
      <a class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" href="/portal"><i class="fa-solid fa-gauge-high fa-fw"></i> Dashboard</a>
      <a class="nav-link <?= ($activeNav ?? '') === 'projects' ? 'active' : '' ?>" href="/portal/projects"><i class="fa-solid fa-folder-open fa-fw"></i> My Projects</a>
      <a class="nav-link <?= ($activeNav ?? '') === 'deliverables' ? 'active' : '' ?>" href="/portal/deliverables"><i class="fa-solid fa-box-archive fa-fw"></i> Deliverables</a>
      <a class="nav-link <?= ($activeNav ?? '') === 'documents' ? 'active' : '' ?>" href="/portal/documents"><i class="fa-solid fa-file-arrow-up fa-fw"></i> Documents &amp; Uploads</a>
      <a class="nav-link <?= ($activeNav ?? '') === 'messages' ? 'active' : '' ?>" href="/portal/messages"><i class="fa-regular fa-comments fa-fw"></i> Messages</a>
      <a class="nav-link <?= ($activeNav ?? '') === 'invoices' ? 'active' : '' ?>" href="/portal/invoices"><i class="fa-solid fa-file-invoice-dollar fa-fw"></i> Invoices &amp; Payments</a>
      <a class="nav-link <?= ($activeNav ?? '') === 'notifications' ? 'active' : '' ?>" href="/portal/notifications"><i class="fa-regular fa-bell fa-fw"></i> Notifications</a>
      <a class="nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>" href="/portal/profile"><i class="fa-regular fa-user fa-fw"></i> Profile &amp; Security</a>
    </nav>
    <div class="p-3 border-top">
      <div class="small fw-semibold text-truncate"><?= e($authUser['company_name'] ?? '') ?></div>
      <div class="small text-muted text-truncate mb-2"><?= e($authUser['full_name'] ?? '') ?></div>
      <button class="btn btn-outline-secondary btn-sm w-100 js-orixo-logout"><i class="fa-solid fa-right-from-bracket me-1"></i> Sign Out</button>
    </div>
  </aside>

  <div class="portal-main">
    <header class="portal-topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary btn-sm d-lg-none" onclick="$('#portalSidebar').addClass('show')"><i class="fa-solid fa-bars"></i></button>
        <span class="fw-semibold text-muted small">Client Portal</span>
      </div>
      <div class="d-flex align-items-center gap-3">
        <a href="/portal/notifications" class="btn btn-light btn-sm position-relative" title="Notifications">
          <i class="fa-regular fa-bell"></i>
        </a>
        <a href="/" class="btn btn-light btn-sm" target="_blank" title="Agency Website"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Website</a>
      </div>
    </header>
    <div class="p-3 p-lg-4 flex-grow-1"><?= $content ?></div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/api-client.js') ?>"></script>
<script src="<?= asset('js/portal.js') ?>"></script>
</body>
</html>

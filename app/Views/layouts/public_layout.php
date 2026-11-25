<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle ?? 'Orixo — Creative Digital Solutions & Innovation Studio') ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.svg') ?>">
  <meta name="description" content="<?= e($metaDescription ?? ($settings['seo.default_description'] ?? '')) ?>">
  <meta property="og:title" content="<?= e($pageTitle ?? 'Orixo') ?>">
  <meta property="og:type" content="website">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link href="<?= asset('css/tokens.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/custom-bootstrap.css') ?>" rel="stylesheet">
  <link href="<?= asset('css/public.css') ?>" rel="stylesheet">
</head>
<body>
<?php $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<nav class="navbar navbar-expand-lg orixo-navbar sticky-top">
  <div class="container">
    <a class="orixo-brand" href="/"><img src="<?= asset('img/orixo-mark.svg') ?>" alt="<?= e($settings['site.name'] ?? 'Orixo') ?>" width="30" height="30"><span><?= e($settings['site.name'] ?? 'Orixo') ?></span></a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#orixoNav">
      <i class="fa-solid fa-bars"></i>
    </button>
    <div class="collapse navbar-collapse" id="orixoNav">
      <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= $cur === '/' ? 'active' : '' ?>" href="/">Home</a></li>
        <li class="nav-item"><a class="nav-link <?= $cur === '/about' ? 'active' : '' ?>" href="/about">About</a></li>
        <li class="nav-item"><a class="nav-link <?= str_starts_with($cur, '/services') ? 'active' : '' ?>" href="/services">Services</a></li>
        <li class="nav-item"><a class="nav-link <?= str_starts_with($cur, '/portfolio') ? 'active' : '' ?>" href="/portfolio">Portfolio</a></li>
        <li class="nav-item"><a class="nav-link <?= str_starts_with($cur, '/industries') ? 'active' : '' ?>" href="/industries">Industries</a></li>
        <li class="nav-item"><a class="nav-link <?= $cur === '/our-process' ? 'active' : '' ?>" href="/our-process">Our Process</a></li>
        <li class="nav-item"><a class="nav-link <?= $cur === '/contact' ? 'active' : '' ?>" href="/contact">Contact</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a href="/login" class="btn btn-orixo-outline btn-sm px-3 py-2">Client Login</a>
        <a href="/project-inquiry" class="btn btn-orixo btn-sm px-3 py-2">Start a Project</a>
      </div>
    </div>
  </div>
</nav>

<main><?= $content ?></main>

<footer class="orixo-footer mt-auto">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="orixo-brand text-white mb-3"><img src="<?= asset('img/orixo-mark.svg') ?>" alt="<?= e($settings['site.name'] ?? 'Orixo') ?>" width="30" height="30"><span><?= e($settings['site.name'] ?? 'Orixo') ?></span></div>
        <p class="small text-secondary mb-4"><?= e($settings['site.footer_note'] ?? 'Creative Digital Solutions & Innovation Studio.') ?></p>
        <div class="d-flex gap-3">
          <a href="<?= e($settings['site.social_linkedin'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin fa-lg"></i></a>
          <a href="<?= e($settings['site.social_github'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="GitHub"><i class="fa-brands fa-github fa-lg"></i></a>
          <a href="<?= e($settings['site.social_dribbble'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Dribbble"><i class="fa-brands fa-dribbble fa-lg"></i></a>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="text-white small text-uppercase fw-bold mb-3">Studio</h6>
        <ul class="list-unstyled small d-grid gap-2">
          <li><a href="/about">About Us</a></li>
          <li><a href="/team">Our Team</a></li>
          <li><a href="/our-process">Our Process</a></li>
          <li><a href="/technologies">Technology Stack</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-3">
        <h6 class="text-white small text-uppercase fw-bold mb-3">Work & Solutions</h6>
        <ul class="list-unstyled small d-grid gap-2">
          <li><a href="/services">All Services</a></li>
          <li><a href="/portfolio">Portfolio</a></li>
          <li><a href="/case-studies">Case Studies</a></li>
          <li><a href="/industries">Industries Served</a></li>
          <li><a href="/project-inquiry">Project Inquiry</a></li>
        </ul>
      </div>
      <div class="col-lg-3">
        <h6 class="text-white small text-uppercase fw-bold mb-3">Contact & Portal</h6>
        <p class="small mb-1"><i class="fa-regular fa-envelope me-2 text-secondary"></i><?= e($settings['site.email'] ?? 'hello@orixo.studio') ?></p>
        <p class="small mb-1"><i class="fa-solid fa-phone me-2 text-secondary"></i><?= e($settings['site.phone'] ?? '') ?></p>
        <p class="small text-secondary mb-3"><?= e($settings['site.address'] ?? '') ?></p>
        <div class="small d-flex gap-3">
          <a href="/privacy-policy">Privacy Policy</a>
          <a href="/terms-and-conditions">Terms</a>
        </div>
      </div>
    </div>
    <hr class="border-secondary opacity-25 my-4">
    <div class="d-flex flex-wrap justify-content-between small text-secondary">
      <span>&copy; <?= date('Y') ?> <?= e($settings['site.name'] ?? 'Orixo') ?> Digital Studio. All rights reserved.</span>
      <a href="/login">Client &amp; Team Portal</a>
    </div>
  </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/api-client.js') ?>"></script>
<script src="<?= asset('js/public.js') ?>"></script>
</body>
</html>

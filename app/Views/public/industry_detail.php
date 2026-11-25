<section class="section-pad">
  <div class="container">
    <a href="/industries" class="small text-decoration-none text-muted d-inline-block mb-3"><i class="fa-solid fa-arrow-left me-1"></i> All Industries</a>
    <span class="editorial-eyebrow d-block">Industry Solutions</span>
    <h1 class="hero-headline mb-4"><?= e($industry['name']) ?></h1>
    <p class="hero-lead mb-5"><?= e($industry['introduction']) ?></p>

    <div class="row g-4 mb-5">
      <div class="col-md-6"><div class="editorial-card"><h4 class="h5 fw-bold">Digital Challenges</h4><p class="small text-muted mb-0"><?= nl2br(e($industry['digital_challenges'])) ?></p></div></div>
      <div class="col-md-6"><div class="editorial-card"><h4 class="h5 fw-bold">Available Solutions</h4><p class="small text-muted mb-0"><?= nl2br(e($industry['available_solutions'])) ?></p></div></div>
    </div>

    <?php if (!empty($services)): ?>
    <div class="mb-5">
      <h3 class="h4 fw-bold mb-3">Relevant Services</h3>
      <div class="row g-3">
        <?php foreach ($services as $svc): ?>
        <div class="col-md-4"><a href="/services/<?= e($svc['slug']) ?>" class="editorial-card p-3 d-block text-decoration-none"><div class="fw-bold small"><?= e($svc['title']) ?></div><div class="small text-muted"><?= e($svc['short_summary']) ?></div></a></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <a href="/project-inquiry" class="btn btn-orixo">Schedule Industry Consultation</a>
  </div>
</section>

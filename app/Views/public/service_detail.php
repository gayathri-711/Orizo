<section class="section-pad">
  <div class="container">
    <a href="/services" class="small text-decoration-none text-muted d-inline-block mb-3"><i class="fa-solid fa-arrow-left me-1"></i> All Services</a>
    <span class="editorial-eyebrow d-block"><?= e($service['category_name']) ?></span>
    <h1 class="hero-headline mb-4"><?= e($service['title']) ?></h1>
    <p class="hero-lead mb-5"><?= e($service['introduction']) ?></p>

    <div class="row g-4 mb-5">
      <div class="col-md-6">
        <div class="editorial-card">
          <h3 class="h5 fw-bold mb-3">Problems We Address</h3>
          <p class="small text-muted mb-0"><?= nl2br(e($service['problems_addressed'])) ?></p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="editorial-card">
          <h3 class="h5 fw-bold mb-3">Solutions We Deliver</h3>
          <p class="small text-muted mb-0"><?= nl2br(e($service['solutions_offered'])) ?></p>
        </div>
      </div>
    </div>

    <?php if (!empty($features)): ?>
    <div class="mb-5">
      <h3 class="h4 fw-bold mb-3">Key Deliverables &amp; Features</h3>
      <div class="row g-3">
        <?php foreach ($features as $f): ?>
        <div class="col-md-6"><div class="bg-white border rounded-2 p-3 small fw-medium"><i class="fa-solid fa-check me-2" style="color:var(--orixo-accent);"></i><?= e($f) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($technologies)): ?>
    <div class="mb-5">
      <h3 class="h4 fw-bold mb-3">Technology Stack</h3>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($technologies as $t): ?>
          <span class="badge bg-white text-dark border px-3 py-2"><?= e($t['name']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($relatedPortfolio)): ?>
    <div class="mb-5">
      <h3 class="h4 fw-bold mb-3">Related Portfolio Work</h3>
      <div class="row g-4">
        <?php foreach ($relatedPortfolio as $rp): ?>
        <div class="col-md-4">
          <div class="editorial-card p-3">
            <div class="small text-muted mb-1"><?= e($rp['category_name']) ?></div>
            <h4 class="h6 fw-bold mb-2"><?= e($rp['title']) ?></h4>
            <a href="/portfolio/<?= e($rp['slug']) ?>" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">View Project →</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="p-4 bg-white border rounded-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h4 class="h5 fw-bold mb-1">Need <?= e($service['title']) ?>?</h4>
        <p class="small text-muted mb-0">Share your scope and timeline to receive a tailored technical proposal.</p>
      </div>
      <a href="/project-inquiry?service=<?= (int) $service['id'] ?>" class="btn btn-orixo">Request Proposal</a>
    </div>
  </div>
</section>

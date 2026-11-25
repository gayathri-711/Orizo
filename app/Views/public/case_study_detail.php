<section class="section-pad">
  <div class="container">
    <a href="/case-studies" class="small text-decoration-none text-muted d-inline-block mb-3"><i class="fa-solid fa-arrow-left me-1"></i> All Case Studies</a>
    <span class="editorial-eyebrow d-block"><?= e($study['industry_name'] ?? 'Case Study') ?></span>
    <h1 class="hero-headline mb-4"><?= e($study['title']) ?></h1>
    <?php if (!empty($study['cover_image_path'])): ?>
      <div class="hero-visual-frame mb-5" style="max-height:460px;"><img src="<?= e($study['cover_image_path']) ?>" alt="<?= e($study['title']) ?>"></div>
    <?php endif; ?>

    <?php if (!empty($metrics)): ?>
    <div class="row g-3 mb-5">
      <?php foreach ($metrics as $m): ?>
      <div class="col-md-4">
        <div class="editorial-card p-3">
          <div class="h3 fw-bold mb-0" style="color:var(--orixo-accent);"><?= e($m['value'] ?? '') ?></div>
          <div class="small text-muted"><?= e($m['label'] ?? '') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
      <div class="col-md-6"><div class="editorial-card"><h4 class="h5 fw-bold">Client Overview</h4><p class="small text-muted mb-0"><?= nl2br(e($study['client_overview'])) ?></p></div></div>
      <div class="col-md-6"><div class="editorial-card"><h4 class="h5 fw-bold">Business Challenge</h4><p class="small text-muted mb-0"><?= nl2br(e($study['business_challenge'])) ?></p></div></div>
      <div class="col-md-6"><div class="editorial-card"><h4 class="h5 fw-bold">Proposed Solution</h4><p class="small text-muted mb-0"><?= nl2br(e($study['proposed_solution'])) ?></p></div></div>
      <div class="col-md-6"><div class="editorial-card"><h4 class="h5 fw-bold">Implementation Approach</h4><p class="small text-muted mb-0"><?= nl2br(e($study['implementation_approach'])) ?></p></div></div>
    </div>

    <div class="p-4 bg-white border rounded-3 mb-4">
      <h4 class="h5 fw-bold">Final Outcome</h4>
      <p class="text-muted mb-0"><?= nl2br(e($study['final_outcome'])) ?></p>
    </div>

    <a href="/project-inquiry" class="btn btn-orixo">Discuss Your Challenge</a>
  </div>
</section>

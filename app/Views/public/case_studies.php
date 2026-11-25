<section class="section-pad">
  <div class="container">
    <span class="editorial-eyebrow">Research &amp; Outcomes</span>
    <h1 class="hero-headline mb-3">Case Studies</h1>
    <p class="hero-lead mb-5">In-depth looks at how Orixo resolves operational bottlenecks and elevates digital experiences.</p>

    <div class="row g-4">
      <?php foreach ($caseStudies as $cs): ?>
      <div class="col-lg-6">
        <div class="editorial-card d-flex flex-column">
          <?php if (!empty($cs['cover_image_path'])): ?>
            <div class="hero-visual-frame mb-3"><img src="<?= e($cs['cover_image_path']) ?>" alt="<?= e($cs['title']) ?>"></div>
          <?php endif; ?>
          <div class="small text-muted mb-2"><?= e($cs['industry_name'] ?? 'Enterprise') ?> · <?= e($cs['service_title'] ?? '') ?></div>
          <h2 class="h4 fw-bold mb-3"><?= e($cs['title']) ?></h2>
          <p class="small text-muted flex-grow-1"><?= e($cs['client_overview']) ?></p>
          <div class="p-3 bg-light rounded-2 small mb-3"><strong>Outcome:</strong> <?= e($cs['final_outcome']) ?></div>
          <a href="/case-studies/<?= e($cs['slug']) ?>" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">Read Full Case Study →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

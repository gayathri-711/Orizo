<section class="section-pad">
  <div class="container">
    <span class="editorial-eyebrow">Selected Work</span>
    <h1 class="hero-headline mb-3">Portfolio</h1>
    <p class="hero-lead mb-4">Commercial digital platforms, enterprise portals, and editorial brand experiences built by Orixo.</p>

    <div class="d-flex flex-wrap gap-2 mb-5">
      <button class="btn btn-orixo btn-sm js-filter-pill" data-group="portfolio" data-filter="all">All Work</button>
      <?php foreach ($categories as $cat): ?>
        <button class="btn btn-orixo-outline btn-sm js-filter-pill" data-group="portfolio" data-filter="<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <?php foreach ($projects as $proj): ?>
      <div class="col-md-6 js-filter-item" data-group="portfolio" data-category="<?= e($proj['category_slug']) ?>">
        <div class="editorial-card p-3 p-md-4 d-flex flex-column">
          <div class="portfolio-thumb mb-3">
            <?php if (!empty($proj['cover_image_path'])): ?><img src="<?= e($proj['cover_image_path']) ?>" alt="<?= e($proj['title']) ?>"><?php endif; ?>
            <div class="position-relative z-1">
              <span class="badge bg-light text-dark mb-2"><?= e($proj['category_name']) ?> · <?= e((string) $proj['completed_year']) ?></span>
              <h2 class="h4 text-white mb-0"><?= e($proj['title']) ?></h2>
            </div>
          </div>
          <p class="small text-muted flex-grow-1"><?= e($proj['summary']) ?></p>
          <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="small fw-semibold"><?= e($proj['outcomes_summary'] ?: ($proj['client_display_name'] ?? '')) ?></span>
            <a href="/portfolio/<?= e($proj['slug']) ?>" class="small fw-semibold text-decoration-none ms-2" style="color:var(--orixo-accent);">Details →</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-pad">
  <div class="container">
    <span class="editorial-eyebrow">Capabilities</span>
    <h1 class="hero-headline mb-3">Digital Agency Services</h1>
    <p class="hero-lead mb-4">End-to-end strategy, design, engineering, and cloud transformation built for measurable enterprise reliability.</p>

    <div class="d-flex flex-wrap gap-2 mb-5">
      <button class="btn btn-orixo btn-sm js-filter-pill" data-group="services" data-filter="all">All Categories</button>
      <?php foreach ($categories as $cat): ?>
        <button class="btn btn-orixo-outline btn-sm js-filter-pill" data-group="services" data-filter="<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <?php foreach ($services as $svc): ?>
      <div class="col-md-6 col-lg-4 js-filter-item" data-group="services" data-category="<?= e($svc['category_slug']) ?>">
        <div class="editorial-card d-flex flex-column">
          <div class="d-flex justify-content-between mb-3">
            <span class="small text-muted"><?= e($svc['category_name']) ?></span>
            <i class="fa-solid <?= e($svc['icon_class'] ?: 'fa-layer-group') ?>" style="color:var(--orixo-accent);"></i>
          </div>
          <h2 class="h5 fw-bold mb-2"><?= e($svc['title']) ?></h2>
          <p class="small text-muted flex-grow-1"><?= e($svc['short_summary']) ?></p>
          <a href="/services/<?= e($svc['slug']) ?>" class="small fw-semibold text-decoration-none mt-3" style="color:var(--orixo-accent);">Service Details <i class="fa-solid fa-arrow-right ms-1"></i></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

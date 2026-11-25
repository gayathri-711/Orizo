<section class="section-pad">
  <div class="container">
    <span class="editorial-eyebrow">Engineering Stack</span>
    <h1 class="hero-headline mb-3">Technology Expertise</h1>
    <p class="hero-lead mb-5">Pragmatic, maintainable technology choices backed by strong security and performance fundamentals.</p>
    <?php foreach ($groupedTech as $cat => $items): ?>
    <div class="mb-5">
      <h3 class="h5 fw-bold text-uppercase text-muted mb-3"><?= e(str_replace('_', ' & ', $cat)) ?></h3>
      <div class="row g-3">
        <?php foreach ($items as $t): ?>
        <div class="col-md-4 col-lg-3">
          <div class="editorial-card p-3">
            <i class="<?= e($t['icon_class_or_path'] ?: 'fa-solid fa-code') ?> fa-lg mb-2" style="color:var(--orixo-accent);"></i>
            <h4 class="h6 fw-bold mb-1"><?= e($t['name']) ?></h4>
            <p class="small text-muted mb-0"><?= e($t['description'] ?? '') ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

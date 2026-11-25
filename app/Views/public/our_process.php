<section class="section-pad">
  <div class="container">
    <span class="editorial-eyebrow">Methodology</span>
    <h1 class="hero-headline mb-3"><?= e($page['title'] ?? 'Our Delivery Process') ?></h1>
    <div class="hero-lead mb-5"><?= $page['content_body'] ?? '' ?></div>
    <div class="row g-4">
      <?php foreach ($steps as $st): ?>
      <div class="col-md-6 col-lg-3">
        <div class="editorial-card">
          <div class="font-mono-code fw-bold mb-2" style="color:var(--orixo-accent);">STAGE <?= e($st['step']) ?></div>
          <h3 class="h5 fw-bold mb-2"><?= e($st['title']) ?></h3>
          <p class="small text-muted mb-0"><?= e($st['desc']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

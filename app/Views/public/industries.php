<section class="section-pad">
  <div class="container">
    <span class="editorial-eyebrow">Sector Expertise</span>
    <h1 class="hero-headline mb-3">Industries We Serve</h1>
    <p class="hero-lead mb-5">Tailored digital architecture designed around real regulatory, operational, and customer expectations.</p>

    <div class="row g-4">
      <?php foreach ($industries as $ind): ?>
      <div class="col-md-6">
        <div class="editorial-card d-flex flex-column">
          <i class="fa-solid <?= e($ind['icon_class'] ?: 'fa-building') ?> fa-lg mb-3" style="color:var(--orixo-accent);"></i>
          <h2 class="h4 fw-bold mb-2"><?= e($ind['name']) ?></h2>
          <p class="small text-muted flex-grow-1"><?= e($ind['short_summary']) ?></p>
          <a href="/industries/<?= e($ind['slug']) ?>" class="small fw-semibold text-decoration-none mt-2" style="color:var(--orixo-accent);">Explore Sector Solutions →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

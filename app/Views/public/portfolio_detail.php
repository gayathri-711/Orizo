<section class="section-pad">
  <div class="container">
    <a href="/portfolio" class="small text-decoration-none text-muted d-inline-block mb-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to Portfolio</a>
    <span class="editorial-eyebrow d-block"><?= e($project['category_name']) ?> · <?= e((string) $project['completed_year']) ?></span>
    <h1 class="hero-headline mb-3"><?= e($project['title']) ?></h1>
    <p class="hero-lead mb-4"><?= e($project['summary']) ?></p>
    <?php if (!empty($project['cover_image_path'])): ?>
      <div class="hero-visual-frame mb-5" style="max-height:480px;"><img src="<?= e($project['cover_image_path']) ?>" alt="<?= e($project['title']) ?>"></div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
      <div class="col-md-4"><div class="editorial-card"><h4 class="h6 fw-bold">Challenge</h4><p class="small text-muted mb-0"><?= nl2br(e($project['challenge_summary'] ?? '')) ?></p></div></div>
      <div class="col-md-4"><div class="editorial-card"><h4 class="h6 fw-bold">Solution</h4><p class="small text-muted mb-0"><?= nl2br(e($project['solution_summary'] ?? '')) ?></p></div></div>
      <div class="col-md-4"><div class="editorial-card"><h4 class="h6 fw-bold">Outcomes</h4><p class="small text-muted mb-0"><?= nl2br(e($project['outcomes_summary'] ?? '')) ?></p></div></div>
    </div>

    <?php if (!empty($technologies)): ?>
    <div class="mb-5">
      <h4 class="h5 fw-bold mb-3">Technologies Used</h4>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($technologies as $t): ?><span class="badge bg-white text-dark border px-3 py-2"><?= e($t['name']) ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <a href="/project-inquiry" class="btn btn-orixo">Start a Similar Project</a>
  </div>
</section>

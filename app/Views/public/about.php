<section class="section-pad border-bottom">
  <div class="container">
    <span class="editorial-eyebrow">About Orixo</span>
    <h1 class="hero-headline mb-4"><?= e($page['title'] ?? 'About Orixo Studio') ?></h1>
    <div class="hero-visual-frame mb-5" style="max-height:420px;">
      <img src="/assets/img/studio-hero.jpg" alt="Orixo Studio Workspace">
    </div>
    <div class="row g-5">
      <div class="col-lg-8 fs-5 text-muted lh-lg">
        <?= $page['content_body'] ?? '' ?>
      </div>
      <div class="col-lg-4">
        <div class="editorial-card">
          <h5 class="fw-bold mb-2">Vision</h5>
          <p class="small text-muted mb-4"><?= e($sections['vision'] ?? '') ?></p>
          <h5 class="fw-bold mb-2">Mission</h5>
          <p class="small text-muted mb-0"><?= e($sections['mission'] ?? '') ?></p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($sections['values'])): ?>
<section class="section-pad section-alt">
  <div class="container">
    <span class="editorial-eyebrow">Core Principles</span>
    <h2 class="h1 fw-bold mb-4">How We Work</h2>
    <div class="row g-4">
      <?php foreach ($sections['values'] as $val): ?>
      <div class="col-md-4">
        <div class="editorial-card">
          <h4 class="h5 fw-bold mb-2"><?= e($val['title']) ?></h4>
          <p class="small text-muted mb-0"><?= e($val['desc']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section-pad">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4">
      <div><span class="editorial-eyebrow">Studio Leadership</span><h2 class="h2 fw-bold mb-0">Multidisciplinary Team</h2></div>
      <a href="/team" class="btn btn-orixo-outline btn-sm">View Full Team</a>
    </div>
    <div class="row g-4">
      <?php foreach ($team as $member): ?>
      <div class="col-md-4">
        <div class="editorial-card">
          <?php if (!empty($member['avatar_path'])): ?>
            <div class="team-avatar-frame"><img src="<?= e($member['avatar_path']) ?>" alt="<?= e($member['full_name']) ?>"></div>
          <?php endif; ?>
          <h4 class="h5 fw-bold mb-1"><?= e($member['full_name']) ?></h4>
          <div class="small fw-semibold mb-2" style="color:var(--orixo-accent);"><?= e($member['designation']) ?></div>
          <p class="small text-muted mb-0"><?= e($member['bio']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

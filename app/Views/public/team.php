<section class="section-pad">
  <div class="container">
    <span class="editorial-eyebrow">People</span>
    <h1 class="hero-headline mb-3">Our Team</h1>
    <p class="hero-lead mb-5">Architects, designers, and engineers dedicated to delivering resilient digital products.</p>
    <div class="row g-4">
      <?php foreach ($team as $member): ?>
      <?php $skills = $member['skills_json'] ? json_decode((string) $member['skills_json'], true) : []; ?>
      <div class="col-md-6 col-lg-4">
        <div class="editorial-card d-flex flex-column">
          <?php if (!empty($member['avatar_path'])): ?>
            <div class="team-avatar-frame"><img src="<?= e($member['avatar_path']) ?>" alt="<?= e($member['full_name']) ?>"></div>
          <?php endif; ?>
          <h3 class="h4 fw-bold mb-1"><?= e($member['full_name']) ?></h3>
          <div class="small fw-semibold mb-3" style="color:var(--orixo-accent);"><?= e($member['designation']) ?></div>
          <p class="small text-muted flex-grow-1"><?= e($member['bio']) ?></p>
          <?php if (!empty($skills)): ?>
          <div class="d-flex flex-wrap gap-1 pt-3 border-top">
            <?php foreach ($skills as $sk): ?><span class="badge bg-light text-dark border"><?= e($sk) ?></span><?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

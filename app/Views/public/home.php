<!-- 1. Hero Section -->
<section class="hero-editorial">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="editorial-eyebrow">Orixo — Creative Digital Solutions &amp; Innovation Studio</span>
        <h1 class="hero-headline">Digital platforms engineered with architectural clarity.</h1>
        <p class="hero-lead mb-4">We design and build high-trust web applications, editorial digital flagships, enterprise client portals, and applied AI workflows for organizations that refuse fragile templates.</p>
        <div class="d-flex flex-wrap gap-3">
          <a href="/project-inquiry" class="btn btn-orixo">Start a Project <i class="fa-solid fa-arrow-right ms-2"></i></a>
          <a href="/portfolio" class="btn btn-orixo-outline">Explore Selected Work</a>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="editorial-card bg-white p-3 p-md-4">
          <div class="hero-visual-frame mb-3">
            <img src="/assets/img/studio-hero.jpg" alt="Orixo Creative Technology Studio">
          </div>
          <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
            <span class="font-mono-code text-muted">STUDIO METRICS</span>
            <span class="status-pill status-success">Enterprise Delivery</span>
          </div>
          <div class="row g-3 text-start">
            <div class="col-6">
              <div class="h2 fw-bold tabular-nums mb-0">98%</div>
              <div class="small text-muted">On-time milestone delivery</div>
            </div>
            <div class="col-6">
              <div class="h2 fw-bold tabular-nums mb-0">64%</div>
              <div class="small text-muted">Faster client onboarding</div>
            </div>
            <div class="col-12 pt-2 border-top">
              <div class="small fw-semibold mb-1">Transparent Portal Governance</div>
              <div class="small text-muted">Private Client Portal for milestones, deliverables, and INR billing.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Agency Introduction -->
<section class="section-pad">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-4">
        <span class="editorial-eyebrow">01 / Studio Ethos</span>
        <h2 class="h1 fw-bold">Strategy, editorial craft, and resilient code under one roof.</h2>
      </div>
      <div class="col-lg-8">
        <p class="fs-5 text-muted lh-lg mb-4">Most agencies separate design thinking from backend engineering—resulting in slow sites and disconnected operational tools. Orixo unites product strategists, UI/UX architects, and PHP/cloud engineers in dedicated project squads.</p>
        <div class="row g-4">
          <div class="col-md-4"><div class="border-top pt-3"><h6>Zero Template Bloat</h6><p class="small text-muted mb-0">Purpose-built relational schemas and lightweight frontend execution.</p></div></div>
          <div class="col-md-4"><div class="border-top pt-3"><h6>Security by Default</h6><p class="small text-muted mb-0">Server-verified RBAC, prepared SQL statements, and private non-webroot file vaults.</p></div></div>
          <div class="col-md-4"><div class="border-top pt-3"><h6>Commercial Outcomes</h6><p class="small text-muted mb-0">Every interface is measured against conversion, speed, and operational clarity.</p></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Featured Services -->
<section class="section-pad section-alt">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-5">
      <div>
        <span class="editorial-eyebrow">02 / Capabilities</span>
        <h2 class="h1 fw-bold mb-0">Core Digital Services</h2>
      </div>
      <a href="/services" class="btn btn-orixo-outline btn-sm mt-3 mt-md-0">View All Services <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-4">
      <?php foreach ($services as $idx => $svc): ?>
      <div class="col-md-6 col-lg-4">
        <div class="editorial-card d-flex flex-column">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="font-mono-code text-muted">0<?= $idx + 1 ?></span>
            <i class="fa-solid <?= e($svc['icon_class'] ?: 'fa-layer-group') ?> text-danger"></i>
          </div>
          <span class="small text-muted mb-1"><?= e($svc['category_name']) ?></span>
          <h3 class="h5 fw-bold mb-2"><?= e($svc['title']) ?></h3>
          <p class="small text-muted flex-grow-1"><?= e($svc['short_summary']) ?></p>
          <a href="/services/<?= e($svc['slug']) ?>" class="text-decoration-none fw-semibold small mt-3" style="color:var(--orixo-accent);">Explore Service <i class="fa-solid fa-arrow-right ms-1"></i></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 4. Selected Portfolio -->
<section class="section-pad">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-5">
      <div>
        <span class="editorial-eyebrow">03 / Selected Work</span>
        <h2 class="h1 fw-bold mb-0">Featured Portfolio</h2>
      </div>
      <a href="/portfolio" class="btn btn-orixo-outline btn-sm mt-3 mt-md-0">All Projects <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-4">
      <?php foreach ($portfolio as $proj): ?>
      <div class="col-md-6">
        <div class="editorial-card p-3 p-md-4">
          <div class="portfolio-thumb mb-3">
            <?php if (!empty($proj['cover_image_path'])): ?><img src="<?= e($proj['cover_image_path']) ?>" alt="<?= e($proj['title']) ?>"><?php endif; ?>
            <div class="position-relative z-1">
              <span class="badge bg-light text-dark mb-2"><?= e($proj['category_name']) ?> · <?= e((string) $proj['completed_year']) ?></span>
              <h3 class="h4 text-white mb-0"><?= e($proj['title']) ?></h3>
            </div>
          </div>
          <p class="small text-muted mb-3"><?= e($proj['summary']) ?></p>
          <div class="d-flex justify-content-between align-items-center">
            <span class="small fw-semibold"><?= e($proj['client_display_name'] ?? 'Confidential Client') ?></span>
            <a href="/portfolio/<?= e($proj['slug']) ?>" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">View Project <i class="fa-solid fa-arrow-right ms-1"></i></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 5. Why Choose Orixo & Case Studies -->
<section class="section-pad section-dark">
  <div class="container">
    <div class="row g-5 align-items-center mb-5">
      <div class="col-lg-6">
        <span class="editorial-eyebrow">04 / Proven Impact</span>
        <h2 class="h1 fw-bold">Selected Case Studies</h2>
      </div>
      <div class="col-lg-6 text-lg-end">
        <a href="/case-studies" class="btn btn-orixo btn-sm">Read All Case Studies</a>
      </div>
    </div>
    <div class="row g-4">
      <?php foreach ($caseStudies as $cs): ?>
      <div class="col-lg-6">
        <div class="p-4 rounded-3 border border-secondary border-opacity-25 h-100 d-flex flex-column" style="background:#1C1C20;">
          <?php if (!empty($cs['cover_image_path'])): ?>
            <div class="hero-visual-frame mb-3 border-secondary border-opacity-25"><img src="<?= e($cs['cover_image_path']) ?>" alt="<?= e($cs['title']) ?>"></div>
          <?php endif; ?>
          <div class="small text-secondary mb-2"><?= e($cs['industry_name'] ?? 'Enterprise') ?> · <?= e($cs['service_title'] ?? '') ?></div>
          <h3 class="h4 mb-3"><?= e($cs['title']) ?></h3>
          <p class="small text-secondary mb-4 flex-grow-1"><?= e($cs['final_outcome']) ?></p>
          <a href="/case-studies/<?= e($cs['slug']) ?>" class="text-decoration-none fw-semibold small" style="color:var(--orixo-accent);">Read Case Study <i class="fa-solid fa-arrow-right ms-1"></i></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 6. Industries, Process & Technologies -->
<section class="section-pad">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-6">
        <span class="editorial-eyebrow">05 / Industries Served</span>
        <h2 class="h2 fw-bold mb-4">Domain-Aware Engineering</h2>
        <div class="d-grid gap-3">
          <?php foreach ($industries as $ind): ?>
          <a href="/industries/<?= e($ind['slug']) ?>" class="editorial-card p-3 text-decoration-none d-flex justify-content-between align-items-center">
            <div>
              <h4 class="h6 fw-bold mb-1"><?= e($ind['name']) ?></h4>
              <p class="small text-muted mb-0"><?= e($ind['short_summary']) ?></p>
            </div>
            <i class="fa-solid fa-arrow-right text-muted ms-3"></i>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <span class="editorial-eyebrow">06 / Our Delivery Process</span>
        <h2 class="h2 fw-bold mb-4">8 Structured Stages</h2>
        <div class="row g-3">
          <?php foreach (array_slice($processSteps, 0, 8) as $st): ?>
          <div class="col-sm-6">
            <div class="p-3 bg-white border rounded-2 h-100">
              <div class="font-mono-code text-danger fw-bold mb-1"><?= e($st['step']) ?></div>
              <div class="fw-semibold small mb-1"><?= e($st['title']) ?></div>
              <div class="small text-muted" style="font-size:0.8rem;"><?= e($st['desc']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 7. Tech Expertise, Testimonials & Team -->
<section class="section-pad section-alt">
  <div class="container">
    <div class="mb-5">
      <span class="editorial-eyebrow">07 / Technology Stack</span>
      <div class="d-flex flex-wrap justify-content-between align-items-end mb-3">
        <h2 class="h2 fw-bold mb-0">Battle-Tested Technologies</h2>
        <a href="/technologies" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">Full Stack Overview →</a>
      </div>
      <div class="row g-3">
        <?php foreach ($technologies as $tech): ?>
        <div class="col-6 col-md-3">
          <div class="bg-white border rounded-2 p-3 d-flex align-items-center gap-3">
            <i class="<?= e($tech['icon_class_or_path'] ?: 'fa-solid fa-code') ?> fa-lg" style="color:var(--orixo-accent);"></i>
            <div>
              <div class="fw-semibold small"><?= e($tech['name']) ?></div>
              <div class="text-muted" style="font-size:0.72rem;text-transform:uppercase;"><?= e(str_replace('_', ' ', $tech['category'])) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-4 mt-2">
      <div class="col-12"><span class="editorial-eyebrow">08 / Client Endorsements</span><h2 class="h2 fw-bold mb-3">Trusted by Leaders</h2></div>
      <?php foreach ($testimonials as $tm): ?>
      <div class="col-lg-4">
        <div class="editorial-card p-4 d-flex flex-column">
          <p class="small fst-italic flex-grow-1 mb-4">"<?= e($tm['quote']) ?>"</p>
          <div class="border-top pt-3">
            <div class="fw-bold small"><?= e($tm['client_name']) ?></div>
            <div class="text-muted" style="font-size:0.8rem;"><?= e($tm['designation']) ?>, <?= e($tm['company_name']) ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($team)): ?>
    <div class="mt-5 pt-4 border-top">
      <div class="d-flex justify-content-between align-items-end mb-4">
        <div><span class="editorial-eyebrow">09 / Leadership</span><h2 class="h2 fw-bold mb-0">Meet the Studio</h2></div>
        <a href="/team" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">All Team Profiles →</a>
      </div>
      <div class="row g-4">
        <?php foreach ($team as $member): ?>
        <div class="col-md-4">
          <div class="editorial-card p-4">
            <?php if (!empty($member['avatar_path'])): ?>
              <div class="team-avatar-frame"><img src="<?= e($member['avatar_path']) ?>" alt="<?= e($member['full_name']) ?>"></div>
            <?php endif; ?>
            <div class="fw-bold h5 mb-1"><?= e($member['full_name']) ?></div>
            <div class="small fw-semibold mb-2" style="color:var(--orixo-accent);"><?= e($member['designation']) ?></div>
            <p class="small text-muted mb-0"><?= e($member['bio']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- 8. Final Project Inquiry CTA -->
<section class="section-pad">
  <div class="container">
    <div class="p-5 rounded-3 section-dark d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
      <div>
        <span class="editorial-eyebrow">Ready to Build?</span>
        <h2 class="h1 fw-bold mb-2">Have a digital initiative in mind?</h2>
        <p class="text-secondary mb-0">Submit a structured project inquiry and our architects will respond within one business day.</p>
      </div>
      <div class="flex-shrink-0">
        <a href="/project-inquiry" class="btn btn-orixo btn-lg">Start Project Inquiry <i class="fa-solid fa-arrow-right ms-2"></i></a>
      </div>
    </div>
  </div>
</section>

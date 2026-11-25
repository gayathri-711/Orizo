<section class="section-pad">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <span class="editorial-eyebrow">Legal &amp; Governance</span>
        <h1 class="hero-headline mb-4"><?= e($page['title'] ?? $fallbackTitle) ?></h1>
        <div class="editorial-card p-4 p-md-5 lh-lg text-muted">
          <?= $page['content_body'] ?? '<p>Document content unavailable.</p>' ?>
        </div>
      </div>
    </div>
  </div>
</section>

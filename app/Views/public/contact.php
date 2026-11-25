<section class="section-pad">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5">
        <span class="editorial-eyebrow">Contact</span>
        <h1 class="hero-headline mb-3">Let's Talk</h1>
        <p class="text-muted mb-4">Have a general question, partnership idea, or speaking request? Reach out directly or start a full <a href="/project-inquiry" style="color:var(--orixo-accent);">Project Inquiry</a>.</p>
        <div class="editorial-card p-4">
          <div class="mb-3"><div class="small text-muted">Email</div><strong><?= e($settings['site.email'] ?? '') ?></strong></div>
          <div class="mb-3"><div class="small text-muted">Phone</div><strong><?= e($settings['site.phone'] ?? '') ?></strong></div>
          <div><div class="small text-muted">Studio Address</div><strong><?= e($settings['site.address'] ?? '') ?></strong></div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="editorial-card p-4 p-md-5">
          <h3 class="h4 fw-bold mb-3">Send a Message</h3>
          <div id="contactAlert" class="alert d-none"></div>
          <form id="contactForm">
            <input type="text" name="website_honeypot" class="d-none" tabindex="-1" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label small fw-semibold">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Business Email *</label><input type="email" name="email" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Phone</label><input type="text" name="phone" class="form-control"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Company</label><input type="text" name="company_name" class="form-control"></div>
              <div class="col-12"><label class="form-label small fw-semibold">Subject</label><input type="text" name="subject" class="form-control"></div>
              <div class="col-12"><label class="form-label small fw-semibold">Message *</label><textarea name="message" rows="5" class="form-control" required></textarea></div>
              <div class="col-12"><button type="submit" class="btn btn-orixo">Send Message <i class="fa-solid fa-paper-plane ms-2"></i></button></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section-pad">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <span class="editorial-eyebrow">Project Intake</span>
        <h1 class="hero-headline mb-3">Start a Project Inquiry</h1>
        <p class="hero-lead mb-4">Share your scope, budget range, and timeline. Approved inquiries are provisioned directly with a dedicated Client Portal workspace.</p>

        <div class="editorial-card p-4 p-md-5">
          <div id="inquiryAlert" class="alert d-none"></div>
          <form id="inquiryForm" enctype="multipart/form-data">
            <input type="text" name="website_honeypot" class="d-none" tabindex="-1" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label small fw-semibold">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Business Email *</label><input type="email" name="email" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Phone Number</label><input type="text" name="phone" class="form-control"></div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Company Name</label><input type="text" name="company_name" class="form-control"></div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Service Required</label>
                <select name="service_id" class="form-select">
                  <option value="">Select a service...</option>
                  <?php foreach ($services as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= ($selectedServiceId ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6"><label class="form-label small fw-semibold">Project Title *</label><input type="text" name="project_title" class="form-control" placeholder="e.g. Enterprise Client Portal v2" required></div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Estimated Budget (INR) *</label>
                <select name="estimated_budget" class="form-select" required>
                  <option value="">Choose budget range...</option>
                  <option value="Under ₹2,00,000">Under ₹2,00,000</option>
                  <option value="₹2,00,000 – ₹5,00,000">₹2,00,000 – ₹5,00,000</option>
                  <option value="₹5,00,000 – ₹12,00,000">₹5,00,000 – ₹12,00,000</option>
                  <option value="₹12,00,000+">₹12,00,000+</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Expected Timeline *</label>
                <select name="expected_timeline" class="form-select" required>
                  <option value="">Choose timeline...</option>
                  <option value="4–6 Weeks">4–6 Weeks</option>
                  <option value="6–12 Weeks">6–12 Weeks</option>
                  <option value="3–6 Months">3–6 Months</option>
                  <option value="Ongoing Retainer">Ongoing Retainer</option>
                </select>
              </div>
              <div class="col-12"><label class="form-label small fw-semibold">Project Description &amp; Objectives *</label><textarea name="message_or_description" rows="5" class="form-control" placeholder="Describe your goals, key users, and technical requirements..." required></textarea></div>
              <div class="col-12"><label class="form-label small fw-semibold">Additional Information</label><textarea name="additional_info" rows="2" class="form-control"></textarea></div>
              <div class="col-12"><label class="form-label small fw-semibold">Reference Attachment (Optional — PDF, DOCX, PNG, JPG, ZIP up to 10 MB)</label><input type="file" name="attachment" class="form-control"></div>
              <div class="col-12 pt-2"><button type="submit" class="btn btn-orixo btn-lg">Submit Project Inquiry <i class="fa-solid fa-arrow-right ms-2"></i></button></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

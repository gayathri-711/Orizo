<div id="adminCmsRoot">
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Agency CMS &amp; Catalog</h1>
    <p class="small text-muted mb-0">Dynamically manage Services, Portfolio, Case Studies, Industries, Technologies, Testimonials, and Editable Pages.</p>
  </div>

  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cmsTabServices">Services</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cmsTabPortfolio">Portfolio</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cmsTabCaseStudies">Case Studies</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cmsTabIndustries">Industries &amp; Tech</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cmsTabTestimonials">Testimonials</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cmsTabPages">Editable Pages</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="cmsTabServices">
      <div class="row g-4">
        <div class="col-lg-5"><div class="admin-card p-4"><h6 class="fw-bold mb-3">Add Service</h6>
          <form id="cmsServiceForm" enctype="multipart/form-data">
            <div class="mb-2"><label class="small fw-semibold">Category</label><select name="category_id" id="cmsSvcCatSelect" class="form-select form-select-sm" required></select></div>
            <div class="mb-2"><label class="small fw-semibold">Title *</label><input type="text" name="title" class="form-control form-control-sm" required></div>
            <div class="mb-2"><label class="small fw-semibold">Short Summary *</label><textarea name="short_summary" rows="2" class="form-control form-control-sm" required></textarea></div>
            <div class="mb-2"><label class="small fw-semibold">Problems Addressed</label><textarea name="problems_addressed" rows="2" class="form-control form-control-sm"></textarea></div>
            <div class="mb-2"><label class="small fw-semibold">Solutions Offered</label><textarea name="solutions_offered" rows="2" class="form-control form-control-sm"></textarea></div>
            <div class="mb-2"><label class="small fw-semibold">Font Awesome Icon</label><input type="text" name="icon_class" class="form-control form-control-sm" value="fa-layer-group"></div>
            <div class="mb-3"><label class="small fw-semibold">Image (Optional)</label><input type="file" name="image" class="form-control form-control-sm"></div>
            <button type="submit" class="btn btn-orixo btn-sm">Publish Service</button>
          </form>
        </div></div>
        <div class="col-lg-7"><div class="admin-card p-3"><table class="table table-sm admin-table mb-0" id="cmsServicesTable"><thead><tr><th>Title</th><th>Category</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody></tbody></table></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="cmsTabPortfolio">
      <div class="row g-4">
        <div class="col-lg-5"><div class="admin-card p-4"><h6 class="fw-bold mb-3">Add Portfolio Project</h6>
          <form id="cmsPortfolioForm" enctype="multipart/form-data">
            <div class="mb-2"><label class="small fw-semibold">Category</label><select name="category_id" id="cmsPortCatSelect" class="form-select form-select-sm" required></select></div>
            <div class="mb-2"><label class="small fw-semibold">Title *</label><input type="text" name="title" class="form-control form-control-sm" required></div>
            <div class="mb-2"><label class="small fw-semibold">Client Display Name</label><input type="text" name="client_display_name" class="form-control form-control-sm"></div>
            <div class="mb-2"><label class="small fw-semibold">Summary *</label><textarea name="summary" rows="2" class="form-control form-control-sm" required></textarea></div>
            <div class="mb-2"><label class="small fw-semibold">Outcomes</label><input type="text" name="outcomes_summary" class="form-control form-control-sm"></div>
            <div class="mb-3"><label class="small fw-semibold">Cover Image (Optional)</label><input type="file" name="image" class="form-control form-control-sm"></div>
            <button type="submit" class="btn btn-orixo btn-sm">Publish Portfolio</button>
          </form>
        </div></div>
        <div class="col-lg-7"><div class="admin-card p-3"><table class="table table-sm admin-table mb-0" id="cmsPortfolioTable"><thead><tr><th>Title</th><th>Category</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody></tbody></table></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="cmsTabCaseStudies">
      <div class="row g-4">
        <div class="col-lg-5"><div class="admin-card p-4"><h6 class="fw-bold mb-3">Add Case Study</h6>
          <form id="cmsCaseStudyForm">
            <div class="mb-2"><input type="text" name="title" class="form-control form-control-sm" placeholder="Case study title *" required></div>
            <div class="mb-2"><textarea name="client_overview" rows="2" class="form-control form-control-sm" placeholder="Client overview *" required></textarea></div>
            <div class="mb-2"><textarea name="business_challenge" rows="2" class="form-control form-control-sm" placeholder="Business challenge *" required></textarea></div>
            <div class="mb-2"><textarea name="proposed_solution" rows="2" class="form-control form-control-sm" placeholder="Proposed solution *" required></textarea></div>
            <div class="mb-2"><textarea name="final_outcome" rows="2" class="form-control form-control-sm" placeholder="Final outcome *" required></textarea></div>
            <button type="submit" class="btn btn-orixo btn-sm">Publish Case Study</button>
          </form>
        </div></div>
        <div class="col-lg-7"><div class="admin-card p-3"><table class="table table-sm admin-table mb-0" id="cmsCaseStudiesTable"><thead><tr><th>Title</th><th>Industry</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody></tbody></table></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="cmsTabIndustries">
      <div class="row g-4">
        <div class="col-lg-6"><div class="admin-card p-4 mb-3"><h6 class="fw-bold mb-2">Add Industry</h6>
          <form id="cmsIndustryForm" class="row g-2"><div class="col-8"><input type="text" name="name" class="form-control form-control-sm" placeholder="Industry name *" required></div><div class="col-4"><button class="btn btn-orixo btn-sm w-100">Save</button></div><div class="col-12"><input type="text" name="short_summary" class="form-control form-control-sm" placeholder="Summary *" required></div></form>
        </div><div class="admin-card p-3"><table class="table table-sm admin-table mb-0" id="cmsIndustriesTable"><thead><tr><th>Industry</th><th>Status</th></tr></thead><tbody></tbody></table></div></div>
        <div class="col-lg-6"><div class="admin-card p-4 mb-3"><h6 class="fw-bold mb-2">Add Technology</h6>
          <form id="cmsTechForm" class="row g-2"><div class="col-5"><input type="text" name="name" class="form-control form-control-sm" placeholder="Tech name *" required></div><div class="col-4"><select name="category" class="form-select form-select-sm"><option value="frontend">Frontend</option><option value="backend">Backend</option><option value="database">Database</option><option value="cloud_devops">Cloud/DevOps</option><option value="ai_data">AI/Data</option><option value="mobile">Mobile</option><option value="design">Design</option></select></div><div class="col-3"><button class="btn btn-orixo btn-sm w-100">Save</button></div></form>
        </div><div class="admin-card p-3"><table class="table table-sm admin-table mb-0" id="cmsTechTable"><thead><tr><th>Technology</th><th>Category</th></tr></thead><tbody></tbody></table></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="cmsTabTestimonials">
      <div class="row g-4">
        <div class="col-lg-5"><div class="admin-card p-4"><h6 class="fw-bold mb-3">Add Testimonial</h6>
          <form id="cmsTestimonialForm">
            <div class="mb-2"><input type="text" name="client_name" class="form-control form-control-sm" placeholder="Client Name *" required></div>
            <div class="mb-2"><input type="text" name="designation" class="form-control form-control-sm" placeholder="Designation *" required></div>
            <div class="mb-2"><input type="text" name="company_name" class="form-control form-control-sm" placeholder="Company *" required></div>
            <div class="mb-2"><textarea name="quote" rows="3" class="form-control form-control-sm" placeholder="Quote *" required></textarea></div>
            <button type="submit" class="btn btn-orixo btn-sm">Publish Testimonial</button>
          </form>
        </div></div>
        <div class="col-lg-7"><div class="admin-card p-3"><table class="table table-sm admin-table mb-0" id="cmsTestimonialsTable"><thead><tr><th>Client</th><th>Company</th><th>Status</th></tr></thead><tbody></tbody></table></div></div>
      </div>
    </div>

    <div class="tab-pane fade" id="cmsTabPages">
      <div class="admin-card p-4" id="cmsPagesList"></div>
    </div>
  </div>
</div>

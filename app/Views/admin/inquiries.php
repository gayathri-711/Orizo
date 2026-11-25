<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1">Inquiry &amp; Contact Inbox</h1><p class="small text-muted mb-0">Review visitor inquiries, download attachments, and convert approved requests into Clients &amp; Projects.</p></div>
  <div class="d-flex flex-wrap gap-2">
    <select id="inqFilterType" class="form-select form-select-sm" style="width:auto;">
      <option value="">All Types</option>
      <option value="project_inquiry">Project Inquiries</option>
      <option value="general_contact">Contact Messages</option>
    </select>
    <select id="inqFilterStatus" class="form-select form-select-sm" style="width:auto;">
      <option value="">All Statuses</option>
      <option value="new">New</option>
      <option value="under_review">Under Review</option>
      <option value="qualified">Qualified</option>
      <option value="approved">Approved</option>
      <option value="converted">Converted</option>
      <option value="rejected">Rejected</option>
    </select>
    <input type="text" id="inqFilterSearch" class="form-control form-control-sm" placeholder="Search ref, name, email..." style="width:200px;">
  </div>
</div>

<div class="admin-card p-3">
  <div class="table-responsive">
    <table class="table admin-table align-middle mb-0" id="adminInquiriesTable">
      <thead><tr><th>Reference</th><th>Type</th><th>Contact</th><th>Company</th><th>Status</th><th>Submitted</th><th class="text-end">Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="inquiryReviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title fw-bold">Inquiry <span id="inqModalRef" class="font-mono-code"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="inqModalBody"></div>
        <div class="mb-3"><strong>Attachments:</strong> <div id="inqModalAttachments" class="mt-1"></div></div>
        <form id="inquiryUpdateForm">
          <input type="hidden" id="inqModalId">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label small fw-semibold">Status</label><select id="inqModalStatus" class="form-select form-select-sm"><option value="new">New</option><option value="under_review">Under Review</option><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="closed">Closed</option></select></div>
            <div class="col-md-8"><label class="form-label small fw-semibold">Internal Notes</label><textarea id="inqModalNotes" rows="2" class="form-control form-control-sm"></textarea></div>
            <div class="col-12 d-flex justify-content-between pt-2">
              <button type="button" id="convBtnOpen" class="btn btn-orixo btn-sm">Approve &amp; Convert to Client / Project</button>
              <button type="submit" class="btn btn-dark btn-sm">Save Status &amp; Notes</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Convert Modal -->
<div class="modal fade" id="convertInquiryModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="convertInquiryForm">
      <div class="modal-header"><h5 class="modal-title fw-bold">Convert Inquiry to Client &amp; Project</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" id="convInqId">
        <p class="small text-muted">Existing Client accounts are reused safely. Staff email conflicts are blocked automatically.</p>
        <div class="mb-2"><label class="form-label small fw-semibold">Client Name</label><input type="text" name="client_name" id="convClientName" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Client Email</label><input type="email" name="client_email" id="convClientEmail" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Company Name</label><input type="text" name="company_name" id="convCompany" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Initial Project Title</label><input type="text" name="project_title" id="convProjectTitle" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Project Description</label><textarea name="project_description" id="convProjectDesc" rows="3" class="form-control form-control-sm" required></textarea></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Project Manager</label><select name="project_manager_id" class="form-select form-select-sm"><option value="">Assign later...</option><?php foreach ($teamMembers as $tm): ?><option value="<?= (int) $tm['id'] ?>"><?= e($tm['full_name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-orixo btn-sm">Confirm Conversion</button></div>
    </form>
  </div>
</div>

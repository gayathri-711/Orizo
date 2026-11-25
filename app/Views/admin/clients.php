<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1">Client Management</h1><p class="small text-muted mb-0">Manage invited client accounts, resend activation links, and control portal access.</p></div>
  <div class="d-flex gap-2">
    <input type="text" id="cliSearch" class="form-control form-control-sm" placeholder="Search company/email...">
    <select id="cliStatus" class="form-select form-select-sm"><option value="">All</option><option value="active">Active</option><option value="pending_activation">Pending Activation</option><option value="inactive">Inactive</option></select>
    <button class="btn btn-orixo btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#createClientModal">+ Create Client</button>
  </div>
</div>

<div class="admin-card p-3">
  <div class="table-responsive">
    <table class="table admin-table align-middle mb-0" id="adminClientsTable">
      <thead><tr><th>Company</th><th>Primary Contact</th><th>Projects</th><th>Status</th><th>Last Login</th><th class="text-end">Actions</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="createClientModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="createClientForm">
      <div class="modal-header"><h5 class="modal-title fw-bold">Invite New Client</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small fw-semibold">Contact Full Name *</label><input type="text" name="full_name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Business Email *</label><input type="email" name="email" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Company Name *</label><input type="text" name="company_name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Phone</label><input type="text" name="phone" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Tax / GSTIN</label><input type="text" name="tax_id" class="form-control form-control-sm"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Billing Address</label><textarea name="billing_address" rows="2" class="form-control form-control-sm"></textarea></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-orixo btn-sm">Create &amp; Send Invitation</button></div>
    </form>
  </div>
</div>

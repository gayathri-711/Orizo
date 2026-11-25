<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h1 class="h3 fw-bold mb-1">Team Management</h1><p class="small text-muted mb-0">Manage team accounts, public website visibility, and project access.</p></div>
  <button class="btn btn-orixo btn-sm" data-bs-toggle="modal" data-bs-target="#createTeamModal">+ Add Team Member</button>
</div>

<div class="admin-card p-3">
  <div class="table-responsive">
    <table class="table admin-table align-middle mb-0" id="adminTeamTable">
      <thead><tr><th>Member</th><th>Designation</th><th>Assigned Projects</th><th>Public Site</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="createTeamModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="createTeamForm">
      <div class="modal-header"><h5 class="modal-title fw-bold">Create Team Member</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small fw-semibold">Full Name *</label><input type="text" name="full_name" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Email *</label><input type="email" name="email" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Designation *</label><input type="text" name="designation" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Skills (comma-separated)</label><input type="text" name="skills" class="form-control form-control-sm" placeholder="PHP, MySQL, UI/UX"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Bio</label><textarea name="bio" rows="2" class="form-control form-control-sm"></textarea></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Initial Strong Password *</label><input type="password" name="password" class="form-control form-control-sm" required></div>
        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="tmShowPublic"><label class="form-check-label small" for="tmShowPublic">Show on Public "Our Team" page</label></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-orixo btn-sm">Create Team Member</button></div>
    </form>
  </div>
</div>

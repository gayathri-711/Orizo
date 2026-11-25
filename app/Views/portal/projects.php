<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1">My Projects</h1>
    <p class="small text-muted mb-0">Track progress, milestones, and deliverables across your Orixo engagements.</p>
  </div>
  <div class="d-flex gap-2">
    <input type="text" id="cpSearch" class="form-control form-control-sm" placeholder="Search project...">
    <select id="cpStatus" class="form-select form-select-sm">
      <option value="">All Statuses</option>
      <option value="planning">Planning</option>
      <option value="in_progress">In Progress</option>
      <option value="on_hold">On Hold</option>
      <option value="completed">Completed</option>
    </select>
  </div>
</div>

<div class="portal-card p-3">
  <div class="table-responsive">
    <table class="table align-middle mb-0" id="clientProjectsTable">
      <thead><tr><th>Code</th><th>Project Title</th><th>Lead</th><th>Target Date</th><th>Status</th><th>Progress</th><th class="text-end">Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

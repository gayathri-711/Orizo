<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1">Project Management</h1><p class="small text-muted mb-0">Create projects, assign clients &amp; teams, and monitor automated progress.</p></div>
  <div class="d-flex gap-2">
    <input type="text" id="apSearch" class="form-control form-control-sm" placeholder="Search project/client...">
    <select id="apStatus" class="form-select form-select-sm"><option value="">All Statuses</option><option value="planning">Planning</option><option value="in_progress">In Progress</option><option value="on_hold">On Hold</option><option value="completed">Completed</option><option value="archived">Archived</option></select>
    <button class="btn btn-orixo btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#createProjectModal">+ New Project</button>
  </div>
</div>

<div class="admin-card p-3">
  <div class="table-responsive">
    <table class="table admin-table align-middle mb-0" id="adminProjectsTable">
      <thead><tr><th>Code</th><th>Title</th><th>Client</th><th>PM</th><th>Status</th><th>Progress</th><th class="text-end">Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="createProjectModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="createProjectForm">
      <div class="modal-header"><h5 class="modal-title fw-bold">Create Project</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label small fw-semibold">Client *</label><select name="client_id" class="form-select form-select-sm" required><option value="">Select client...</option><?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['company_name']) ?> (<?= e($c['full_name']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Project Manager</label><select name="project_manager_id" class="form-select form-select-sm"><option value="">Select PM...</option><?php foreach ($teamMembers as $tm): ?><option value="<?= (int) $tm['id'] ?>"><?= e($tm['full_name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-8"><label class="form-label small fw-semibold">Project Title *</label><input type="text" name="title" class="form-control form-control-sm" required></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Service</label><select name="service_id" class="form-select form-select-sm"><option value="">Optional...</option><?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['title']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Start Date</label><input type="date" name="start_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Target Completion</label><input type="date" name="target_completion_date" class="form-control form-control-sm"></div>
          <div class="col-12"><label class="form-label small fw-semibold">Description *</label><textarea name="description" rows="3" class="form-control form-control-sm" required></textarea></div>
          <div class="col-12"><label class="form-label small fw-semibold">Scope of Work</label><textarea name="scope_of_work" rows="2" class="form-control form-control-sm"></textarea></div>
        </div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-orixo btn-sm">Create Project</button></div>
    </form>
  </div>
</div>

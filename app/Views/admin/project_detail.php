<?php
$isAdminRole = ($authUser['role_slug'] ?? '') === 'admin' && empty($isTeamWorkspace);
?>
<div id="adminProjectDetailRoot" data-project-id="<?= (int) $projectId ?>" data-team-ws="<?= $isAdminRole ? '0' : '1' ?>">
  <div class="mb-3"><a href="<?= $isAdminRole ? '/admin/projects' : '/admin/workspace/projects' ?>" class="small text-decoration-none text-muted">← Back to Projects</a></div>

  <div class="admin-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div>
        <span class="font-mono-code text-muted" id="apdCode"></span>
        <h1 class="h3 fw-bold mb-1" id="apdTitle">Loading...</h1>
        <div class="small text-muted" id="apdClient"></div>
      </div>
      <div id="apdStatus"></div>
    </div>
    <div class="d-flex justify-content-between small mb-1">
      <span id="apdProgressMeta" class="text-muted"></span>
      <strong class="tabular-nums" id="apdProgressPct">0%</strong>
    </div>
    <div class="progress mb-3"><div class="progress-bar progress-bar-orixo" id="apdProgressBar" style="width:0%"></div></div>

    <?php if ($isAdminRole): ?>
    <form id="admProgressForm" class="row g-2 align-items-end pt-3 border-top">
      <div class="col-auto"><label class="form-label small fw-semibold mb-1">Progress Mode</label><select id="progModeSelect" class="form-select form-select-sm"><option value="auto">Auto-calculated (Tasks)</option><option value="manual">Manual Override</option></select></div>
      <div class="col-auto"><label class="form-label small fw-semibold mb-1">Manual %</label><input type="number" id="progManualPct" min="0" max="100" class="form-control form-control-sm" style="width:90px;"></div>
      <div class="col-auto"><button type="submit" class="btn btn-dark btn-sm">Apply Progress Setting</button></div>
    </form>
    <?php endif; ?>
  </div>

  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabWsTasks">Tasks &amp; Milestones</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWsDocs">Documents &amp; Client Uploads</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWsDeliverables">Deliverables</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWsMessages">Messages</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabWsTeam">Team</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tabWsTasks">
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="admin-card p-4 mb-4">
            <h5 class="fw-bold mb-3">Milestones</h5>
            <?php if ($isAdminRole): ?>
            <form id="admMilestoneForm" class="row g-2 mb-3 pb-3 border-bottom">
              <div class="col-12"><input type="text" name="title" class="form-control form-control-sm" placeholder="Milestone title *" required></div>
              <div class="col-6"><input type="date" name="planned_date" class="form-control form-control-sm"></div>
              <div class="col-6"><button type="submit" class="btn btn-orixo btn-sm w-100">+ Add Milestone</button></div>
            </form>
            <?php endif; ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="apdMilestonesTable"><thead><tr><th>Milestone</th><th>Planned</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody></tbody></table></div>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="admin-card p-4">
            <h5 class="fw-bold mb-3">Project Tasks</h5>
            <form id="wsTaskForm" class="row g-2 mb-3 pb-3 border-bottom">
              <div class="col-md-5"><input type="text" name="title" class="form-control form-control-sm" placeholder="New task title *" required></div>
              <div class="col-md-3"><select name="milestone_id" id="taskMilestoneSelect" class="form-select form-select-sm"></select></div>
              <div class="col-md-2"><select name="priority" class="form-select form-select-sm"><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select></div>
              <div class="col-md-2"><button type="submit" class="btn btn-orixo btn-sm w-100">+ Task</button></div>
            </form>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="apdTasksTable"><thead><tr><th>Task</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Due</th><th class="text-end">Action</th></tr></thead><tbody></tbody></table></div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tabWsDocs">
      <div class="admin-card p-4">
        <h5 class="fw-bold mb-1">Documents &amp; Client Uploads</h5>
        <p class="small text-muted mb-3"><?= $isAdminRole ? 'Admin may publish agency documents to Client Portal.' : 'Team uploads default to internal-only pending Admin review.' ?></p>
        <form id="wsDocUploadForm" class="row g-2 mb-3 pb-3 border-bottom" enctype="multipart/form-data">
          <div class="col-md-3"><input type="text" name="title" class="form-control form-control-sm" placeholder="Document title *" required></div>
          <div class="col-md-2"><select name="category" class="form-select form-select-sm"><option value="design_doc">Design Doc</option><option value="dev_doc">Dev Doc</option><option value="brief">Brief</option><option value="agreement">Agreement</option><option value="other">Other</option></select></div>
          <?php if ($isAdminRole): ?>
          <div class="col-md-2"><select name="folder_area" class="form-select form-select-sm"><option value="internal_only">Internal Only</option><option value="agency_shared">Agency Shared</option></select></div>
          <?php endif; ?>
          <div class="col-md-3"><input type="file" name="file" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><button type="submit" class="btn btn-orixo btn-sm w-100">Upload</button></div>
        </form>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="apdDocsTable"><thead><tr><th>Document</th><th>Area</th><th>Review</th><th>Client Visibility</th><th>Uploaded By</th><th class="text-end">Actions</th></tr></thead><tbody></tbody></table></div>
      </div>
    </div>

    <div class="tab-pane fade" id="tabWsDeliverables">
      <div class="admin-card p-4">
        <h5 class="fw-bold mb-1">Project Deliverables</h5>
        <p class="small text-muted mb-3">Team Members submit deliverables for review. Only Administrators can publish official deliverables to clients.</p>
        <form id="wsDeliverableForm" class="row g-2 mb-3 pb-3 border-bottom" enctype="multipart/form-data">
          <div class="col-md-4"><input type="text" name="title" class="form-control form-control-sm" placeholder="Deliverable title *" required></div>
          <div class="col-md-2"><input type="text" name="version_label" class="form-control form-control-sm" value="v1.0" placeholder="Version"></div>
          <div class="col-md-4"><input type="file" name="file" class="form-control form-control-sm" required></div>
          <div class="col-md-2"><button type="submit" class="btn btn-orixo btn-sm w-100">Submit</button></div>
        </form>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="apdDeliverablesTable"><thead><tr><th>Deliverable</th><th>Review Status</th><th>Client Visibility</th><th>Submitted By</th><th>Date</th><th class="text-end">Actions</th></tr></thead><tbody></tbody></table></div>
      </div>
    </div>

    <div class="tab-pane fade" id="tabWsMessages">
      <div class="admin-card p-4">
        <h5 class="fw-bold mb-3">Client &amp; Team Communication</h5>
        <div id="apdMessagesBox" class="mb-3" style="max-height:380px;overflow-y:auto;"></div>
        <form id="wsMessageForm" enctype="multipart/form-data" class="border-top pt-3">
          <textarea name="body" rows="3" class="form-control mb-2" placeholder="Reply to client or post project update..."></textarea>
          <div class="d-flex justify-content-between align-items-center">
            <input type="file" name="attachment" class="form-control form-control-sm" style="max-width:280px;">
            <button type="submit" class="btn btn-orixo btn-sm">Send Reply</button>
          </div>
        </form>
      </div>
    </div>

    <div class="tab-pane fade" id="tabWsTeam">
      <div class="admin-card p-4">
        <h5 class="fw-bold mb-3">Assigned Project Team</h5>
        <?php if ($isAdminRole): ?>
        <form id="admAssignForm" class="row g-2 mb-3 pb-3 border-bottom">
          <div class="col-md-5"><select name="team_member_id" class="form-select form-select-sm" required><option value="">Select team member...</option><?php foreach ($teamMembers as $tm): ?><option value="<?= (int) $tm['id'] ?>"><?= e($tm['full_name']) ?> (<?= e($tm['designation']) ?>)</option><?php endforeach; ?></select></div>
          <div class="col-md-5"><input type="text" name="project_role" class="form-control form-control-sm" placeholder="Project Role (e.g. Backend Lead)" value="Contributor"></div>
          <div class="col-md-2"><button type="submit" class="btn btn-orixo btn-sm w-100">Assign</button></div>
        </form>
        <?php endif; ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="apdTeamTable"><thead><tr><th>Name</th><th>Designation</th><th>Project Role</th><th class="text-end">Action</th></tr></thead><tbody></tbody></table></div>
      </div>
    </div>
  </div>
</div>

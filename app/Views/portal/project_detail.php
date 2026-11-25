<div id="clientProjectDetailRoot" data-project-id="<?= (int) $projectId ?>">
  <div class="mb-3"><a href="/portal/projects" class="small text-decoration-none text-muted">← Back to My Projects</a></div>

  <div class="portal-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <div>
        <span class="font-mono-code text-muted" id="pdCode"></span>
        <h1 class="h3 fw-bold mb-1" id="pdTitle">Loading...</h1>
        <div class="small text-muted">Lead: <span id="pdPm"></span> · Timeline: <span id="pdDates"></span></div>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <span id="pdStatus"></span>
        <span id="pdModeBadge"></span>
      </div>
    </div>

    <div class="mb-2 d-flex justify-content-between small">
      <span class="fw-semibold">Overall Progress</span>
      <strong class="tabular-nums" id="pdProgressPct">0%</strong>
    </div>
    <div class="progress mb-3"><div class="progress-bar progress-bar-orixo" id="pdProgressBar" style="width:0%"></div></div>
    <div id="pdTaskBreakdown" class="d-flex flex-wrap gap-2 mb-3"></div>
    <div class="row g-3 pt-3 border-top small">
      <div class="col-md-6"><strong>Description:</strong><div class="text-muted mt-1" id="pdDescription"></div></div>
      <div class="col-md-6"><strong>Scope &amp; Assigned Team:</strong><div class="text-muted mt-1 mb-2" id="pdScope"></div><div id="pdTeamList"></div></div>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabTimeline">Timeline &amp; Tasks</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDeliverables">Official Deliverables</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDocuments">Documents &amp; Client Uploads</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMessages">Project Discussion</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tabTimeline">
      <div class="row g-4">
        <div class="col-lg-5"><div class="portal-card p-4"><h5 class="fw-bold mb-3">Project Milestones</h5><div id="pdMilestonesList"></div></div></div>
        <div class="col-lg-7">
          <div class="portal-card p-4">
            <h5 class="fw-bold mb-3">Tasks Summary</h5>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="pdTasksTable"><thead><tr><th>Task</th><th>Priority</th><th>Status</th><th>Due Date</th></tr></thead><tbody></tbody></table></div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tabDeliverables">
      <div class="portal-card p-4">
        <h5 class="fw-bold mb-1">Published Official Deliverables</h5>
        <p class="small text-muted mb-3">Only deliverables reviewed and approved for release by Orixo appear here.</p>
        <div id="pdDeliverablesList"></div>
      </div>
    </div>

    <div class="tab-pane fade" id="tabDocuments">
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="portal-card p-4 h-100">
            <h5 class="fw-bold mb-1">Agency Shared Documents</h5>
            <p class="small text-muted mb-3">Contracts, architecture briefs, and approved shared files.</p>
            <div id="pdSharedDocsList"></div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="portal-card p-4 h-100">
            <h5 class="fw-bold mb-1">Client Uploads</h5>
            <p class="small text-muted mb-3">Upload project briefs, reference assets, brand images, or agreements for agency review.</p>
            <form id="clientUploadForm" class="client-upload-dropzone mb-3" enctype="multipart/form-data">
              <div class="row g-2">
                <div class="col-md-7"><input type="text" name="title" class="form-control form-control-sm" placeholder="Document Title *" required></div>
                <div class="col-md-5"><select name="category" class="form-select form-select-sm"><option value="brief">Brief</option><option value="agreement">Agreement</option><option value="reference">Reference Asset</option><option value="other">Other</option></select></div>
                <div class="col-12"><input type="text" name="description" class="form-control form-control-sm" placeholder="Short note (optional)"></div>
                <div class="col-12"><input type="file" name="file" class="form-control form-control-sm" required></div>
                <div class="col-12"><button type="submit" class="btn btn-orixo btn-sm">Upload File</button></div>
              </div>
            </form>
            <div id="pdClientUploadsList"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tabMessages">
      <div class="portal-card p-4">
        <h5 class="fw-bold mb-3">Project Thread</h5>
        <div id="pdMessagesBox" class="mb-3" style="max-height:420px;overflow-y:auto;"></div>
        <form id="clientMessageForm" enctype="multipart/form-data" class="border-top pt-3">
          <div class="mb-2"><textarea name="body" rows="3" class="form-control" placeholder="Write a message to your Orixo project team..."></textarea></div>
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <input type="file" name="attachment" class="form-control form-control-sm" style="max-width:300px;">
            <button type="submit" class="btn btn-orixo btn-sm">Send Message</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

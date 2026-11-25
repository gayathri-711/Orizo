<div id="clientDashboardRoot">
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Welcome back, <?= e($authUser['full_name'] ?? 'Client') ?></h1>
    <p class="text-muted small mb-0"><?= e($authUser['company_name'] ?? '') ?> — Live overview of your projects, deliverables, and invoices.</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="portal-card p-3"><div class="small text-muted">Active / Total Projects</div><div class="h3 fw-bold tabular-nums mb-0"><span id="kpiActiveProjects">0</span> / <span id="kpiTotalProjects">0</span></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="portal-card p-3"><div class="small text-muted">Published Deliverables</div><div class="h3 fw-bold tabular-nums mb-0" id="kpiDeliverables">0</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="portal-card p-3"><div class="small text-muted">Unread Messages</div><div class="h3 fw-bold tabular-nums mb-0" id="kpiUnreadMsgs">0</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="portal-card p-3"><div class="small text-muted">Outstanding Balance</div><div class="h3 fw-bold tabular-nums mb-0 text-danger" id="kpiOutstanding">₹0.00</div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="portal-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">My Projects</h5>
          <a href="/portal/projects" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">View All →</a>
        </div>
        <div id="dashProjectsList">Loading projects...</div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="portal-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Notifications</h5>
          <a href="/portal/notifications" class="small text-muted text-decoration-none">All</a>
        </div>
        <div id="dashNotificationsList">Loading...</div>
      </div>
    </div>
  </div>

  <div class="portal-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="fw-bold mb-0">Recent Invoices</h5>
      <a href="/portal/invoices" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">All Invoices →</a>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="dashInvoicesTable">
        <thead><tr><th>Invoice #</th><th>Project</th><th>Balance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

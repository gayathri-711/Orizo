<div id="adminDashboardRoot">
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Agency Operations Overview</h1>
    <p class="small text-muted mb-0">Live metrics across clients, delivery projects, inquiries, deliverables, and INR financials.</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2"><div class="admin-card p-3"><div class="small text-muted">Active / Total Clients</div><div class="h4 fw-bold tabular-nums mb-0" id="admKpiClients">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="admin-card p-3"><div class="small text-muted">Active / Total Projects</div><div class="h4 fw-bold tabular-nums mb-0" id="admKpiProjects">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="admin-card p-3"><div class="small text-muted">Blocked Projects</div><div class="h4 fw-bold tabular-nums text-danger mb-0" id="admKpiBlocked">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="admin-card p-3"><div class="small text-muted">New Inquiries</div><div class="h4 fw-bold tabular-nums mb-0" id="admKpiInquiries">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="admin-card p-3"><div class="small text-muted">Pending Deliverables</div><div class="h4 fw-bold tabular-nums mb-0" id="admKpiDeliverables">0</div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="admin-card p-3"><div class="small text-muted">Total Invoices</div><div class="h4 fw-bold tabular-nums mb-0" id="admKpiInvoices">0</div></div></div>
  </div>

  <div class="admin-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="fw-bold mb-0">Financial Summary (By Currency)</h5>
      <a href="/admin/invoices" class="small fw-semibold text-decoration-none" style="color:var(--orixo-accent);">Manage Invoices →</a>
    </div>
    <div id="admFinanceSummary"></div>
  </div>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="admin-card p-4 h-100">
        <h5 class="fw-bold mb-3">Projects Requiring Attention</h5>
        <div id="admAttentionList"></div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="admin-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0">Recent Audit Activity</h5>
          <a href="/admin/activity-logs" class="small text-muted text-decoration-none">Full Logs →</a>
        </div>
        <div id="admActivityList"></div>
      </div>
    </div>
  </div>
</div>

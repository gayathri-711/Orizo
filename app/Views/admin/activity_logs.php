<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1">Security &amp; Activity Audit Logs</h1><p class="small text-muted mb-0">Immutable trail of authentication, conversions, deliverables, invoices, and payments.</p></div>
  <input type="text" id="logSearch" class="form-control form-control-sm" placeholder="Filter by action (e.g. invoice, auth)..." style="width:260px;">
</div>
<div class="admin-card p-3">
  <div class="table-responsive">
    <table class="table table-sm admin-table align-middle mb-0" id="adminActivityLogsTable">
      <thead><tr><th>Timestamp</th><th>Actor</th><th>Action</th><th>Summary</th><th>IP</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

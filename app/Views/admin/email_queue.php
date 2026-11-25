<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1">Email Queue &amp; Transactional Templates</h1><p class="small text-muted mb-0">Persist-first SMTP queue (3 attempts max, 5m/15m retry backoff). Cron: <code>bin/process_email_queue.php</code></p></div>
  <div class="d-flex gap-2">
    <select id="eqStatus" class="form-select form-select-sm"><option value="">All Statuses</option><option value="pending">Pending</option><option value="sent">Sent</option><option value="failed">Failed (Retrying)</option><option value="permanently_failed">Permanently Failed</option></select>
    <button id="btnProcessQueue" class="btn btn-orixo btn-sm text-nowrap">Run Queue Now</button>
  </div>
</div>

<div class="admin-card p-3 mb-4">
  <div class="table-responsive">
    <table class="table table-sm admin-table align-middle mb-0" id="adminEmailQueueTable">
      <thead><tr><th>ID</th><th>Recipient</th><th>Template</th><th>Status</th><th>Attempts</th><th>Last Error</th><th class="text-end">Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="admin-card p-4">
  <h5 class="fw-bold mb-3">Registered Transactional Templates (14)</h5>
  <div id="emailTemplatesList"></div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1">Invoices &amp; Manual Payments</h1><p class="small text-muted mb-0">Default billing currency: INR (₹) with per-invoice multi-currency preservation.</p></div>
  <div class="d-flex gap-2">
    <input type="text" id="invSearch" class="form-control form-control-sm" placeholder="Search invoice/client...">
    <select id="invStatus" class="form-select form-select-sm"><option value="">All Statuses</option><option value="draft">Draft</option><option value="issued">Issued</option><option value="partially_paid">Partially Paid</option><option value="paid">Paid</option><option value="overdue">Overdue</option><option value="cancelled">Cancelled</option></select>
    <button class="btn btn-orixo btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">+ New Invoice</button>
  </div>
</div>

<div class="admin-card p-3">
  <div class="table-responsive">
    <table class="table admin-table align-middle mb-0" id="adminInvoicesTable">
      <thead><tr><th>Invoice #</th><th>Client</th><th>Project</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th class="text-end">Action</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="createInvoiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="createInvoiceForm">
      <div class="modal-header"><h5 class="modal-title fw-bold">Create Draft Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-6"><label class="form-label small fw-semibold">Client *</label><select id="invClientSelect" class="form-select form-select-sm" required><option value="">Select client...</option><?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['company_name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Project *</label><select id="invProjectSelect" class="form-select form-select-sm" required><option value="">Select client's project...</option><?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>" data-client="<?= (int) $p['client_id'] ?>"><?= e($p['project_code']) ?> — <?= e($p['title']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Currency</label><select name="currency_code" class="form-select form-select-sm"><option value="INR" selected>INR (₹)</option><option value="USD">USD ($)</option><option value="EUR">EUR (€)</option><option value="GBP">GBP (£)</option></select></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Tax Rate (%)</label><input type="number" step="0.01" name="tax_rate" class="form-control form-control-sm" value="<?= e(setting('finance.default_tax_rate', '18.00')) ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Issue Date</label><input type="date" name="issue_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Due Date</label><input type="date" name="due_date" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+15 days')) ?>"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2"><strong class="small">Line Items</strong><button type="button" id="addInvItemBtn" class="btn btn-sm btn-outline-secondary">+ Add Item</button></div>
        <div id="invItemsContainer">
          <div class="row g-2 mb-2 inv-item-row">
            <div class="col-md-6"><input type="text" class="form-control form-control-sm item-desc" placeholder="Line item description *" required></div>
            <div class="col-md-2"><input type="number" step="0.01" class="form-control form-control-sm item-qty" value="1" required></div>
            <div class="col-md-3"><input type="number" step="0.01" class="form-control form-control-sm item-price" placeholder="Unit price" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100 js-rm-inv-row">×</button></div>
          </div>
        </div>
        <div class="mt-2"><label class="form-label small fw-semibold">Notes</label><textarea name="notes" rows="2" class="form-control form-control-sm"></textarea></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-orixo btn-sm">Create Draft Invoice</button></div>
    </form>
  </div>
</div>

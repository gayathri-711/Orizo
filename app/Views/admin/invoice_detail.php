<div id="adminInvoiceDetailRoot" data-invoice-id="<?= (int) $invoiceId ?>">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="/admin/invoices" class="small text-decoration-none text-muted">← Back to Invoices</a>
    <div class="d-flex gap-2">
      <button id="btnIssueInvoice" class="btn btn-orixo btn-sm">Issue Invoice &amp; Notify Client</button>
      <button id="btnCancelInvoice" class="btn btn-outline-danger btn-sm">Cancel Invoice</button>
      <a href="/api/v1/admin/invoices/<?= (int) $invoiceId ?>/pdf" class="btn btn-dark btn-sm"><i class="fa-solid fa-file-pdf me-1"></i>Download PDF</a>
    </div>
  </div>

  <div class="admin-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
      <div><h2 class="h4 fw-bold font-mono-code mb-1" id="aiNumber">...</h2><div class="small text-muted" id="aiMeta"></div></div>
      <div id="aiStatus"></div>
    </div>
    <div class="table-responsive mb-3">
      <table class="table align-middle" id="aiItemsTable">
        <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="row justify-content-end">
      <div class="col-md-5">
        <div class="d-flex justify-content-between small py-1"><span>Subtotal</span><span class="tabular-nums" id="aiSubtotal"></span></div>
        <div class="d-flex justify-content-between small py-1"><span>Tax</span><span class="tabular-nums" id="aiTax"></span></div>
        <div class="d-flex justify-content-between fw-bold py-2 border-top"><span>Total</span><span class="tabular-nums" id="aiTotal"></span></div>
        <div class="d-flex justify-content-between small py-1 text-success"><span>Amount Paid</span><span class="tabular-nums" id="aiPaid"></span></div>
        <div class="d-flex justify-content-between fw-bold py-2 border-top text-danger"><span>Remaining Balance</span><span class="tabular-nums" id="aiBalance"></span></div>
      </div>
    </div>
  </div>

  <div class="admin-card p-4 mb-4" id="recordPaymentCard">
    <h5 class="fw-bold mb-3">Record Manual / Offline Payment</h5>
    <form id="recordPaymentForm" class="row g-2">
      <div class="col-md-2"><label class="form-label small fw-semibold">Amount *</label><input type="number" step="0.01" name="amount" class="form-control form-control-sm" required></div>
      <div class="col-md-2"><label class="form-label small fw-semibold">Date *</label><input type="date" name="payment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-md-2"><label class="form-label small fw-semibold">Method *</label><select name="payment_method" class="form-select form-select-sm"><option value="upi">UPI</option><option value="neft_rtgs">NEFT / RTGS</option><option value="bank_transfer">Bank Transfer</option><option value="wire">Wire Transfer</option><option value="check">Cheque</option><option value="cash">Cash</option><option value="card_offline">Offline Card</option><option value="other">Other</option></select></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Transaction Reference</label><input type="text" name="transaction_reference" class="form-control form-control-sm" placeholder="UTR / Ref #"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Notes</label><input type="text" name="notes" class="form-control form-control-sm"></div>
      <div class="col-12 mt-2"><button type="submit" class="btn btn-orixo btn-sm">Record Payment</button></div>
    </form>
  </div>

  <div class="admin-card p-4">
    <h5 class="fw-bold mb-3">Payment Ledger (Immutable — Mistakes Must Be Voided)</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0" id="aiPaymentsTable">
        <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th>Txn Ref</th><th>Amount</th><th>Status</th><th class="text-end">Action / Reason</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

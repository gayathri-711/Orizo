<div id="clientInvoiceDetailRoot" data-invoice-id="<?= (int) $invoiceId ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="/portal/invoices" class="small text-decoration-none text-muted">← Back to Invoices</a>
    <a href="/api/v1/client/invoices/<?= (int) $invoiceId ?>/pdf" class="btn btn-orixo btn-sm"><i class="fa-solid fa-file-pdf me-1"></i>Download PDF</a>
  </div>

  <div class="portal-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
      <div>
        <h2 class="h4 fw-bold font-mono-code mb-1" id="ciNumber">...</h2>
        <div class="small text-muted" id="ciProject"></div>
        <div class="small text-muted" id="ciDates"></div>
      </div>
      <div id="ciStatus"></div>
    </div>

    <div class="table-responsive mb-4">
      <table class="table align-middle" id="ciItemsTable">
        <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>

    <div class="row justify-content-end">
      <div class="col-md-5">
        <div class="d-flex justify-content-between small py-1"><span>Subtotal</span><span class="tabular-nums" id="ciSubtotal"></span></div>
        <div class="d-flex justify-content-between small py-1"><span>Tax</span><span class="tabular-nums" id="ciTax"></span></div>
        <div class="d-flex justify-content-between fw-bold py-2 border-top"><span>Total Amount</span><span class="tabular-nums" id="ciTotal"></span></div>
        <div class="d-flex justify-content-between small py-1 text-success"><span>Amount Paid</span><span class="tabular-nums" id="ciPaid"></span></div>
        <div class="d-flex justify-content-between fw-bold py-2 border-top text-danger"><span>Remaining Balance</span><span class="tabular-nums" id="ciBalance"></span></div>
      </div>
    </div>
  </div>

  <div class="portal-card p-4">
    <h5 class="fw-bold mb-3">Payment History</h5>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0" id="ciPaymentsTable">
        <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

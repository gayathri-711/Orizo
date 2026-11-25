<div class="mb-4">
  <h1 class="h3 fw-bold mb-1">Website, Finance &amp; Email Settings</h1>
  <p class="small text-muted mb-0">SMTP credentials remain secured in server <code>.env</code> outside the database and webroot.</p>
</div>
<div class="admin-card p-4">
  <form id="adminSettingsForm">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label small fw-semibold">Agency Name</label><input type="text" data-setting="site.name" class="form-control form-control-sm"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Tagline</label><input type="text" data-setting="site.tagline" class="form-control form-control-sm"></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Public Contact Email</label><input type="email" data-setting="site.email" class="form-control form-control-sm"></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Public Phone</label><input type="text" data-setting="site.phone" class="form-control form-control-sm"></div>
      <div class="col-md-4"><label class="form-label small fw-semibold">Email Reply-To</label><input type="email" data-setting="email.reply_to" class="form-control form-control-sm"></div>
      <div class="col-12"><label class="form-label small fw-semibold">Studio Address</label><input type="text" data-setting="site.address" class="form-control form-control-sm"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Default Currency</label><select data-setting="finance.default_currency" class="form-select form-select-sm"><option value="INR">INR (₹)</option><option value="USD">USD ($)</option><option value="EUR">EUR (€)</option></select></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Default Tax Rate (%)</label><input type="text" data-setting="finance.default_tax_rate" class="form-control form-control-sm"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Invoice Prefix</label><input type="text" data-setting="finance.invoice_prefix" class="form-control form-control-sm"></div>
      <div class="col-md-3"><label class="form-label small fw-semibold">Maintenance Mode</label><select data-setting="system.maintenance_mode" class="form-select form-select-sm"><option value="0">Disabled</option><option value="1">Enabled (503 Public)</option></select></div>
      <div class="col-12"><label class="form-label small fw-semibold">Remittance / Bank &amp; UPI Details (Shown on Invoice PDFs)</label><textarea data-setting="finance.bank_details" rows="4" class="form-control form-control-sm"></textarea></div>
      <div class="col-12"><label class="form-label small fw-semibold">SEO Default Title</label><input type="text" data-setting="seo.default_title" class="form-control form-control-sm"></div>
      <div class="col-12"><label class="form-label small fw-semibold">SEO Default Description</label><textarea data-setting="seo.default_description" rows="2" class="form-control form-control-sm"></textarea></div>
      <div class="col-12 pt-2"><button type="submit" class="btn btn-orixo btn-sm">Save All Settings</button></div>
    </div>
  </form>
</div>

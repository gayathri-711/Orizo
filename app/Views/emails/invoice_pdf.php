<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; color: #18181B; font-size: 12px; margin: 0; padding: 28px; }
  .header { border-bottom: 3px solid #E04F26; padding-bottom: 14px; margin-bottom: 20px; }
  .brand { font-size: 22px; font-weight: bold; color: #18181B; }
  .muted { color: #52525B; font-size: 11px; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { background: #F7F5F0; text-align: left; padding: 8px; border-bottom: 1px solid #E4E2DD; font-size: 10px; text-transform: uppercase; }
  td { padding: 8px; border-bottom: 1px solid #E4E2DD; }
  .right { text-align: right; }
  .totals { width: 45%; margin-left: 55%; margin-top: 16px; }
  .totals td { border: none; padding: 4px 8px; }
  .grand { font-size: 14px; font-weight: bold; border-top: 2px solid #18181B !important; padding-top: 8px !important; }
  .box { background: #FAF8F5; border: 1px solid #E4E2DD; padding: 10px 12px; margin-top: 18px; border-radius: 4px; }
</style>
</head>
<body>
  <div class="header">
    <table style="margin:0;">
      <tr>
        <td style="border:none;padding:0;">
          <div class="brand"><?= e($settings['site.name'] ?? 'ORIXO') ?></div>
          <div class="muted"><?= e($settings['site.tagline'] ?? 'Creative Digital Solutions & Innovation Studio') ?></div>
          <div class="muted"><?= e($settings['site.address'] ?? '') ?> | <?= e($settings['site.email'] ?? '') ?></div>
        </td>
        <td style="border:none;padding:0;" class="right">
          <div style="font-size:18px;font-weight:bold;color:#E04F26;">INVOICE</div>
          <div><strong><?= e($invoice['invoice_number']) ?></strong></div>
          <div class="muted">Status: <?= e(strtoupper(str_replace('_', ' ', $invoice['status']))) ?></div>
          <div class="muted">Currency: <?= e($invoice['currency_code']) ?></div>
        </td>
      </tr>
    </table>
  </div>

  <table>
    <tr>
      <td style="width:55%;border:none;padding:0;vertical-align:top;">
        <div class="muted">BILL TO</div>
        <strong><?= e($invoice['company_name']) ?></strong><br>
        <?= e($invoice['client_name']) ?> (<?= e($invoice['client_email']) ?>)<br>
        <?php if (!empty($invoice['billing_address'])): ?><?= nl2br(e($invoice['billing_address'])) ?><br><?php endif; ?>
        <?php if (!empty($invoice['tax_id'])): ?><span class="muted">Tax/GSTIN: <?= e($invoice['tax_id']) ?></span><?php endif; ?>
      </td>
      <td style="width:45%;border:none;padding:0;vertical-align:top;" class="right">
        <div class="muted">PROJECT</div>
        <strong><?= e($invoice['project_code']) ?> — <?= e($invoice['project_title']) ?></strong><br><br>
        <span class="muted">Issue Date:</span> <?= e(format_date($invoice['issue_date'])) ?><br>
        <span class="muted">Due Date:</span> <?= e(format_date($invoice['due_date'])) ?>
      </td>
    </tr>
  </table>

  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th class="right">Qty</th>
        <th class="right">Unit Price</th>
        <th class="right">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= e($item['description']) ?></td>
        <td class="right"><?= e(number_format((float) $item['quantity'], 2)) ?></td>
        <td class="right"><?= e(format_currency($item['unit_price'], $invoice['currency_code'])) ?></td>
        <td class="right"><?= e(format_currency($item['line_total'], $invoice['currency_code'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="totals">
    <tr><td>Subtotal</td><td class="right"><?= e(format_currency($invoice['subtotal'], $invoice['currency_code'])) ?></td></tr>
    <tr><td>Tax (<?= e($invoice['tax_rate']) ?>%)</td><td class="right"><?= e(format_currency($invoice['tax_amount'], $invoice['currency_code'])) ?></td></tr>
    <tr><td class="grand">Total</td><td class="right grand"><?= e(format_currency($invoice['total_amount'], $invoice['currency_code'])) ?></td></tr>
    <tr><td>Amount Paid</td><td class="right"><?= e(format_currency($invoice['amount_paid'], $invoice['currency_code'])) ?></td></tr>
    <tr><td><strong>Remaining Balance</strong></td><td class="right"><strong><?= e(format_currency($invoice['remaining_balance'], $invoice['currency_code'])) ?></strong></td></tr>
  </table>

  <?php if (!empty($payments)): ?>
  <div class="box">
    <strong>Recorded Payments</strong>
    <table style="margin-top:6px;">
      <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th class="right">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= e($p['payment_reference']) ?></td>
          <td><?= e(format_date($p['payment_date'])) ?></td>
          <td><?= e(strtoupper(str_replace('_', ' ', $p['payment_method']))) ?></td>
          <td class="right"><?= e(format_currency($p['amount'], $invoice['currency_code'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if (!empty($settings['finance.bank_details']) || !empty($invoice['notes'])): ?>
  <div class="box">
    <?php if (!empty($invoice['notes'])): ?><div><strong>Notes:</strong> <?= nl2br(e($invoice['notes'])) ?></div><?php endif; ?>
    <?php if (!empty($settings['finance.bank_details'])): ?><div style="margin-top:8px;"><strong>Remittance Instructions:</strong><br><span class="muted"><?= nl2br(e($settings['finance.bank_details'])) ?></span></div><?php endif; ?>
  </div>
  <?php endif; ?>
</body>
</html>

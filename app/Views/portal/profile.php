<?php
$pdo = \Orixo\Bootstrap\Database::connection();
$cStmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$cStmt->execute([(int) $authUser['client_id']]);
$client = $cStmt->fetch() ?: [];
?>
<div class="mb-4">
  <h1 class="h3 fw-bold mb-1">Profile &amp; Account Security</h1>
  <p class="small text-muted mb-0">Manage your contact details and update your portal password.</p>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="portal-card p-4">
      <h5 class="fw-bold mb-3">Company &amp; Contact Information</h5>
      <form id="clientProfileForm">
        <div class="mb-3"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= e($authUser['full_name'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Email (Managed by Admin)</label><input type="email" class="form-control" value="<?= e($authUser['email'] ?? '') ?>" disabled></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($authUser['phone'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Company Name</label><input type="text" name="company_name" class="form-control" value="<?= e($client['company_name'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Billing Address</label><textarea name="billing_address" rows="3" class="form-control"><?= e($client['billing_address'] ?? '') ?></textarea></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Website URL</label><input type="url" name="website_url" class="form-control" value="<?= e($client['website_url'] ?? '') ?>"></div>
        <button type="submit" class="btn btn-orixo btn-sm">Save Profile</button>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="portal-card p-4">
      <h5 class="fw-bold mb-3">Change Password</h5>
      <form id="clientPasswordForm">
        <div class="mb-3"><label class="form-label small fw-semibold">Current Password</label><input type="password" name="current_password" autocomplete="current-password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label small fw-semibold">New Password</label><input type="password" name="new_password" autocomplete="new-password" class="form-control" required><div class="form-text">Min 10 characters with uppercase, lowercase, number, and symbol.</div></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Confirm New Password</label><input type="password" name="confirm_password" autocomplete="new-password" class="form-control" required></div>
        <button type="submit" class="btn btn-orixo-outline btn-sm">Update Password</button>
      </form>
    </div>
  </div>
</div>

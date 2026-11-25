<div class="mb-4">
  <h1 class="h3 fw-bold mb-1">My Team Profile &amp; Security</h1>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="admin-card p-4">
      <h5 class="fw-bold mb-3">Profile Details</h5>
      <form id="wsProfileForm">
        <div class="mb-3"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= e($authUser['full_name'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Designation</label><input type="text" class="form-control" value="<?= e($authUser['designation'] ?? '') ?>" disabled></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($authUser['phone'] ?? '') ?>"></div>
        <button type="submit" class="btn btn-orixo btn-sm">Update Profile</button>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="admin-card p-4">
      <h5 class="fw-bold mb-3">Change Password</h5>
      <form id="wsPasswordForm">
        <div class="mb-3"><label class="form-label small fw-semibold">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label small fw-semibold">New Password</label><input type="password" name="new_password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
        <button type="submit" class="btn btn-orixo-outline btn-sm">Update Password</button>
      </form>
    </div>
  </div>
</div>

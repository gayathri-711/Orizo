<section class="section-pad">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        <div class="editorial-card p-4 p-md-5">
          <span class="editorial-eyebrow">Security</span>
          <h1 class="h3 fw-bold mb-2">Set New Password</h1>
          <p class="small text-muted mb-4">Password must be at least 10 characters and include uppercase, lowercase, number, and symbol.</p>
          <div id="resetAlert" class="alert d-none"></div>
          <form id="resetPasswordForm">
            <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
            <div class="mb-3"><label class="form-label small fw-semibold">New Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label small fw-semibold">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
            <button type="submit" class="btn btn-orixo w-100">Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

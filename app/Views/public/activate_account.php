<section class="section-pad">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        <div class="editorial-card p-4 p-md-5">
          <span class="editorial-eyebrow">Client Onboarding</span>
          <h1 class="h3 fw-bold mb-2">Activate Your Account</h1>
          <p class="small text-muted mb-4">Set a strong password (at least <strong>10 characters</strong> including uppercase <code>A-Z</code>, lowercase <code>a-z</code>, number <code>0-9</code>, and a symbol like <code>@ # $ !</code>) to unlock your Client Portal.</p>
          <div id="activateAlert" class="alert d-none"></div>
          <form id="activateForm" method="post" action="/activate-account">
            <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
            <div class="mb-3"><label class="form-label small fw-semibold">New Password</label><div class="input-group"><input type="password" name="password" autocomplete="new-password" class="form-control" required><button class="btn btn-outline-secondary js-toggle-pwd" type="button" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button></div></div>
            <div class="mb-3"><label class="form-label small fw-semibold">Confirm Password</label><div class="input-group"><input type="password" name="password_confirmation" autocomplete="new-password" class="form-control" required><button class="btn btn-outline-secondary js-toggle-pwd" type="button" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button></div></div>
            <button type="submit" class="btn btn-orixo w-100">Activate Account</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

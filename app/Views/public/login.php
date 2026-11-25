<section class="section-pad">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        <div class="editorial-card p-4 p-md-5">
          <span class="editorial-eyebrow">Secure Gateway</span>
          <h1 class="h2 fw-bold mb-2">Portal &amp; Workspace Login</h1>
          <p class="small text-muted mb-4">Clients access project progress and INR invoices. Orixo accounts are provisioned by invitation only.</p>

          <?php if (!empty($loginError)): ?>
            <div class="alert alert-danger small"><?= e($loginError) ?></div>
          <?php elseif (($reason ?? '') === 'session_expired' || ($reason ?? '') === 'auth_required'): ?>
            <div class="alert alert-warning small">Please sign in to continue.</div>
          <?php endif; ?>

          <form id="loginForm" method="post" action="/login">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label for="loginEmail" class="form-label small fw-semibold">Email Address</label>
              <input id="loginEmail" type="email" name="email" autocomplete="username" class="form-control" value="<?= e($loginEmail ?? '') ?>" required autofocus>
            </div>
            <div class="mb-3">
              <div class="d-flex justify-content-between">
                <label for="loginPassword" class="form-label small fw-semibold">Password</label>
                <a href="/forgot-password" class="small text-decoration-none" style="color:var(--orixo-accent);">Forgot password?</a>
              </div>
              <div class="input-group">
                <input id="loginPassword" type="password" name="password" autocomplete="current-password" class="form-control" required>
                <button class="btn btn-outline-secondary js-toggle-pwd" type="button" aria-label="Toggle password"><i class="fa-regular fa-eye"></i></button>
              </div>
            </div>
            <button type="submit" class="btn btn-orixo w-100 mt-2">Sign In <i class="fa-solid fa-arrow-right ms-2"></i></button>
          </form>

          <hr class="my-4">
          <div class="small text-muted text-center">
            New prospective client? <a href="/project-inquiry" class="fw-semibold text-decoration-none" style="color:var(--orixo-accent);">Submit a Project Inquiry</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

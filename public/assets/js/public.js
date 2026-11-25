(function ($) {
  'use strict';

  // Password visibility toggle
  $(document).on('click', '.js-toggle-pwd', function () {
    var $input = $(this).closest('.input-group').find('input');
    var isPwd = $input.attr('type') === 'password';
    $input.attr('type', isPwd ? 'text' : 'password');
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // Portfolio & Services category filter pills
  $(document).on('click', '.js-filter-pill', function () {
    var $btn = $(this);
    var group = $btn.data('group');
    var cat = $btn.data('filter');
    $('.js-filter-pill[data-group="' + group + '"]').removeClass('btn-orixo').addClass('btn-orixo-outline');
    $btn.removeClass('btn-orixo-outline').addClass('btn-orixo');

    $('.js-filter-item[data-group="' + group + '"]').each(function () {
      var itemCat = $(this).data('category');
      $(this).toggle(cat === 'all' || itemCat === cat);
    });
  });

  // General Contact Form
  $('#contactForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    OrixoApi.setLoading($btn, true, 'Sending...');

    var payload = {};
    $form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });

    OrixoApi.request('POST', '/api/v1/public/contact', payload).done(function (res) {
      $('#contactAlert').removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
      $form[0].reset();
      OrixoApi.toast(res.message, 'success');
    }).always(function () {
      OrixoApi.setLoading($btn, false);
    });
  });

  // Project Inquiry Multipart Form
  $('#inquiryForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    OrixoApi.setLoading($btn, true, 'Submitting Inquiry...');

    var fd = new FormData(this);
    OrixoApi.request('POST', '/api/v1/public/inquiries', fd, true).done(function (res) {
      $('#inquiryAlert').removeClass('d-none alert-danger').addClass('alert-success')
        .html('<strong>' + OrixoApi.escapeHtml(res.message) + '</strong><br><span class="small">Keep your reference code for follow-ups. A confirmation email has been queued.</span>');
      $form[0].reset();
      OrixoApi.toast('Inquiry received!', 'success');
    }).always(function () {
      OrixoApi.setLoading($btn, false);
    });
  });

  // Native POST submit for Login Form (ensures browser Password Manager prompts "Save password")
  $('#loginForm').on('submit', function () {
    var $btn = $(this).find('button[type="submit"]');
    setTimeout(function () { OrixoApi.setLoading($btn, true, 'Signing in...'); }, 0);
  });

  // Account Activation Form
  $('#activateForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    OrixoApi.setLoading($btn, true, 'Activating...');
    var payload = {
      token: $form.find('[name="token"]').val(),
      password: $form.find('[name="password"]').val(),
      password_confirmation: $form.find('[name="password_confirmation"]').val()
    };
    OrixoApi.request('POST', '/api/v1/auth/activate', payload).done(function (res) {
      $('#activateAlert').removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
      setTimeout(function () { window.location.href = '/login'; }, 1500);
    }).fail(function (xhr) {
      var r = xhr.responseJSON || {};
      var detail = (r.errors && Object.values(r.errors).length) ? Object.values(r.errors).join(' ') : (r.message || 'Activation failed.');
      $('#activateAlert').removeClass('d-none alert-success').addClass('alert-danger').text(detail);
    }).always(function () {
      OrixoApi.setLoading($btn, false);
    });
  });

  // Forgot Password Form
  $('#forgotPasswordForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    OrixoApi.setLoading($btn, true, 'Sending...');
    OrixoApi.request('POST', '/api/v1/auth/forgot-password', { email: $form.find('[name="email"]').val() }).done(function (res) {
      $('#forgotAlert').removeClass('d-none').addClass('alert-success').text(res.message);
      $form[0].reset();
    }).always(function () {
      OrixoApi.setLoading($btn, false);
    });
  });

  // Reset Password Form
  $('#resetPasswordForm').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    OrixoApi.setLoading($btn, true, 'Updating...');
    OrixoApi.request('POST', '/api/v1/auth/reset-password', {
      token: $form.find('[name="token"]').val(),
      password: $form.find('[name="password"]').val(),
      password_confirmation: $form.find('[name="password_confirmation"]').val()
    }).done(function (res) {
      $('#resetAlert').removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
      setTimeout(function () { window.location.href = '/login'; }, 1500);
    }).fail(function (xhr) {
      var r = xhr.responseJSON || {};
      var detail = (r.errors && Object.values(r.errors).length) ? Object.values(r.errors).join(' ') : (r.message || 'Password reset failed.');
      $('#resetAlert').removeClass('d-none alert-success').addClass('alert-danger').text(detail);
    }).always(function () {
      OrixoApi.setLoading($btn, false);
    });
  });
})(jQuery);

window.OrixoApi = (function ($) {
  function csrfToken() {
    return $('meta[name="csrf-token"]').attr('content') || '';
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function toast(message, type) {
    var variant = type === 'error' ? 'bg-danger text-white' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-dark text-white');
    var $container = $('#orixo-toast-container');
    if (!$container.length) {
      $container = $('<div id="orixo-toast-container"></div>').appendTo('body');
    }
    var $el = $(
      '<div class="toast align-items-center ' + variant + ' border-0 mb-2 shadow-sm" role="alert">' +
      '<div class="d-flex"><div class="toast-body small fw-medium">' + escapeHtml(message) + '</div>' +
      '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>'
    );
    $container.append($el);
    var bsToast = new bootstrap.Toast($el[0], { delay: 4200 });
    bsToast.show();
    $el.on('hidden.bs.toast', function () { $(this).remove(); });
  }

  function request(method, url, data, isMultipart) {
    var opts = {
      url: url,
      method: method,
      headers: { 'X-CSRF-Token': csrfToken() },
      dataType: 'json'
    };
    if (isMultipart) {
      opts.data = data;
      opts.processData = false;
      opts.contentType = false;
    } else if (data && method !== 'GET') {
      opts.data = JSON.stringify(data);
      opts.contentType = 'application/json; charset=utf-8';
    } else if (data && method === 'GET') {
      opts.data = data;
    }

    return $.ajax(opts).fail(function (xhr) {
      var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Request failed (' + xhr.status + ').';
      if (xhr.responseJSON && xhr.responseJSON.errors) {
        var errs = Object.values(xhr.responseJSON.errors);
        if (errs.length) msg = errs.join(' ');
      }
      if (xhr.status === 401 && !window.location.pathname.startsWith('/login')) {
        window.location.href = '/login?reason=session_expired';
      }
      toast(msg, 'error');
    });
  }

  function statusBadge(status) {
    var s = String(status || '').toLowerCase();
    var cls = 'status-neutral';
    if (['completed', 'paid', 'approved', 'approved_published', 'published', 'active', 'converted', 'sent', 'reviewed', 'recorded'].indexOf(s) !== -1) {
      cls = 'status-success';
    } else if (['review', 'pending_review', 'partially_paid', 'on_hold', 'under_review', 'qualified', 'delayed', 'pending_activation'].indexOf(s) !== -1) {
      cls = 'status-warning';
    } else if (['blocked', 'overdue', 'rejected', 'cancelled', 'voided', 'inactive', 'locked', 'failed', 'permanently_failed'].indexOf(s) !== -1) {
      cls = 'status-danger';
    } else if (['in_progress', 'issued', 'new', 'contacted', 'processing'].indexOf(s) !== -1) {
      cls = 'status-info';
    }
    return '<span class="status-pill ' + cls + '">' + escapeHtml(s.replace(/_/g, ' ')) + '</span>';
  }

  function formatBytes(bytes) {
    var b = Number(bytes || 0);
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
    return (b / 1048576).toFixed(2) + ' MB';
  }

  function setLoading($btn, loading, loadingText) {
    if (loading) {
      $btn.data('orig-html', $btn.html());
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>' + escapeHtml(loadingText || 'Processing...'));
    } else {
      $btn.prop('disabled', false).html($btn.data('orig-html') || 'Submit');
    }
  }

  function logout() {
    request('POST', '/api/v1/auth/logout', {}).done(function (res) {
      window.location.href = (res.data && res.data.redirect) ? res.data.redirect : '/login';
    });
  }

  $(document).on('click', '.js-orixo-logout', function (e) {
    e.preventDefault();
    logout();
  });

  return {
    request: request,
    toast: toast,
    escapeHtml: escapeHtml,
    statusBadge: statusBadge,
    formatBytes: formatBytes,
    setLoading: setLoading,
    logout: logout
  };
})(jQuery);

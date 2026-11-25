(function ($) {
  'use strict';
  var E = OrixoApi.escapeHtml;

  // 1. Client Dashboard
  if ($('#clientDashboardRoot').length) {
    OrixoApi.request('GET', '/api/v1/client/dashboard').done(function (res) {
      var d = res.data;
      $('#kpiTotalProjects').text(d.kpis.total_projects);
      $('#kpiActiveProjects').text(d.kpis.active_projects);
      $('#kpiDeliverables').text(d.kpis.published_deliverables);
      $('#kpiOutstanding').text(d.kpis.outstanding_balance_formatted);
      $('#kpiUnreadMsgs').text(d.kpis.unread_messages);

      var $proj = $('#dashProjectsList').empty();
      if (!d.projects.length) {
        $proj.html('<div class="text-muted small py-3">No active projects assigned yet.</div>');
      } else {
        d.projects.forEach(function (p) {
          var modeLabel = p.progress_mode === 'manual' ? '<span class="status-pill status-warning">Manual Override</span>' : '<span class="status-pill status-neutral">Auto-calculated</span>';
          $proj.append(
            '<div class="border rounded-2 p-3 mb-3">' +
            '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">' +
            '<div><span class="font-mono-code text-muted me-2">' + E(p.project_code) + '</span><a href="/portal/projects/' + p.id + '" class="fw-bold text-dark text-decoration-none">' + E(p.title) + '</a></div>' +
            '<div class="d-flex gap-2">' + OrixoApi.statusBadge(p.status) + modeLabel + '</div></div>' +
            '<div class="d-flex justify-content-between small text-muted mb-1"><span>Progress (' + p.completed_tasks_count + '/' + p.total_active_tasks + ' tasks)</span><strong class="tabular-nums">' + p.progress_percentage + '%</strong></div>' +
            '<div class="progress"><div class="progress-bar progress-bar-orixo" style="width:' + p.progress_percentage + '%"></div></div>' +
            '</div>'
          );
        });
      }

      var $notif = $('#dashNotificationsList').empty();
      if (!d.notifications.length) {
        $notif.html('<div class="text-muted small">No notifications yet.</div>');
      } else {
        d.notifications.forEach(function (n) {
          $notif.append(
            '<div class="border-bottom pb-2 mb-2">' +
            '<div class="small fw-semibold">' + E(n.title) + (!n.is_read ? ' <span class="badge bg-danger">New</span>' : '') + '</div>' +
            '<div class="small text-muted">' + E(n.message) + '</div></div>'
          );
        });
      }

      var $inv = $('#dashInvoicesTable tbody').empty();
      if (!d.invoices.length) {
        $inv.html('<tr><td colspan="5" class="text-center text-muted small py-3">No issued invoices yet.</td></tr>');
      } else {
        d.invoices.forEach(function (i) {
          var st = i.is_overdue ? OrixoApi.statusBadge('overdue') : OrixoApi.statusBadge(i.status);
          $inv.append(
            '<tr><td class="font-mono-code">' + E(i.invoice_number) + '</td><td>' + E(i.project_title) + '</td>' +
            '<td class="tabular-nums">' + E(i.formatted_balance) + '</td><td>' + st + '</td>' +
            '<td class="text-end"><a href="/portal/invoices/' + i.id + '" class="btn btn-sm btn-outline-secondary">View</a> ' +
            '<a href="/api/v1/client/invoices/' + i.id + '/pdf" class="btn btn-sm btn-orixo">PDF</a></td></tr>'
          );
        });
      }
    });
  }

  // 2. My Projects List
  function loadClientProjects() {
    OrixoApi.request('GET', '/api/v1/client/projects', {
      search: $('#cpSearch').val(),
      status: $('#cpStatus').val()
    }).done(function (res) {
      var $tb = $('#clientProjectsTable tbody').empty();
      if (!res.data.length) {
        $tb.html('<tr><td colspan="7" class="text-center text-muted py-4">No projects match your filters.</td></tr>');
        return;
      }
      res.data.forEach(function (p) {
        var modeBadge = p.progress_mode === 'manual' ? 'Manual Override' : 'Auto-calculated';
        $tb.append(
          '<tr><td class="font-mono-code">' + E(p.project_code) + '</td>' +
          '<td><a href="/portal/projects/' + p.id + '" class="fw-semibold text-dark text-decoration-none">' + E(p.title) + '</a></td>' +
          '<td>' + E(p.pm_name || 'Assigned Lead') + '</td>' +
          '<td>' + E(p.target_completion_date || '—') + '</td>' +
          '<td>' + OrixoApi.statusBadge(p.status) + '</td>' +
          '<td style="min-width:160px;"><div class="d-flex justify-content-between small mb-1"><span class="text-muted" style="font-size:0.72rem;">' + modeBadge + '</span><strong class="tabular-nums">' + p.progress_percentage + '%</strong></div><div class="progress"><div class="progress-bar progress-bar-orixo" style="width:' + p.progress_percentage + '%"></div></div></td>' +
          '<td class="text-end"><a href="/portal/projects/' + p.id + '" class="btn btn-sm btn-orixo-outline">Open Workspace</a></td></tr>'
        );
      });
    });
  }
  if ($('#clientProjectsTable').length) {
    loadClientProjects();
    $('#cpSearch, #cpStatus').on('change keyup', loadClientProjects);
  }

  // 3. Single Project Workspace (Overview, Timeline, Tasks, Docs/Uploads, Deliverables, Messages)
  var $pdRoot = $('#clientProjectDetailRoot');
  if ($pdRoot.length) {
    var pid = $pdRoot.data('project-id');

    function loadProjectDetail() {
      OrixoApi.request('GET', '/api/v1/client/projects/' + pid).done(function (res) {
        var d = res.data;
        var p = d.project;
        var bd = d.task_breakdown || {};
        $('#pdTitle').text(p.title);
        $('#pdCode').text(p.project_code);
        $('#pdStatus').html(OrixoApi.statusBadge(p.status));
        $('#pdModeBadge').html(p.progress_mode === 'manual' ? '<span class="status-pill status-warning">Manual Override</span>' : '<span class="status-pill status-neutral">Auto-calculated</span>');
        $('#pdProgressPct').text(p.progress_percentage + '%');
        $('#pdProgressBar').css('width', p.progress_percentage + '%');
        $('#pdTaskBreakdown').html(
          '<span class="status-pill status-success">' + (bd.completed || 0) + ' Completed</span> ' +
          '<span class="status-pill status-warning">' + (bd.review || 0) + ' In Review</span> ' +
          '<span class="status-pill status-info">' + (bd.in_progress || 0) + ' In Progress</span> ' +
          '<span class="status-pill status-neutral">' + (bd.not_started || 0) + ' Not Started</span> ' +
          ((bd.blocked || 0) > 0 ? '<span class="status-pill status-danger">' + bd.blocked + ' Blocked</span>' : '')
        );
        $('#pdDescription').text(p.description);
        $('#pdScope').text(p.scope_of_work || 'Scope documented in project brief.');
        $('#pdPm').text(d.project_manager ? (d.project_manager.full_name + ' (' + d.project_manager.designation + ')') : 'Assigned Studio Lead');
        $('#pdDates').text((p.start_date || 'TBD') + ' → ' + (p.target_completion_date || 'TBD'));

        var $team = $('#pdTeamList').empty();
        d.team.forEach(function (t) {
          $team.append('<span class="badge bg-light text-dark border me-1 mb-1">' + E(t.full_name) + ' · ' + E(t.project_role) + '</span>');
        });

        var $ms = $('#pdMilestonesList').empty();
        if (!d.milestones.length) $ms.html('<div class="text-muted small">No milestones defined yet.</div>');
        d.milestones.forEach(function (m) {
          $ms.append(
            '<div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">' +
            '<div><div class="fw-semibold">' + E(m.title) + '</div><div class="small text-muted">' + E(m.description || '') + '</div>' +
            '<div class="small text-muted mt-1">Planned: ' + E(m.planned_date || 'TBD') + (m.completion_date ? ' · Completed: ' + E(m.completion_date) : '') + '</div></div>' +
            OrixoApi.statusBadge(m.status) + '</div>'
          );
        });

        var $tasks = $('#pdTasksTable tbody').empty();
        if (!d.tasks.length) $tasks.html('<tr><td colspan="4" class="text-center text-muted small py-3">No tasks logged yet.</td></tr>');
        d.tasks.forEach(function (t) {
          $tasks.append('<tr><td class="fw-medium">' + E(t.title) + '</td><td>' + OrixoApi.statusBadge(t.priority) + '</td><td>' + OrixoApi.statusBadge(t.status) + '</td><td>' + E(t.due_date || '—') + '</td></tr>');
        });
      });
    }

    function loadProjectDocs() {
      OrixoApi.request('GET', '/api/v1/client/projects/' + pid + '/documents').done(function (res) {
        var $shared = $('#pdSharedDocsList').empty();
        var $uploads = $('#pdClientUploadsList').empty();
        var sharedCnt = 0, upCnt = 0;

        res.data.forEach(function (doc) {
          var row =
            '<div class="d-flex justify-content-between align-items-center border-bottom py-2">' +
            '<div><div class="fw-semibold small">' + E(doc.title) + ' <span class="badge bg-light text-dark border">' + E(doc.category) + '</span></div>' +
            '<div class="text-muted" style="font-size:0.75rem;">' + E(doc.original_name) + ' · ' + OrixoApi.formatBytes(doc.file_size_bytes) + ' · ' + OrixoApi.statusBadge(doc.review_status) + '</div></div>' +
            '<a href="/api/v1/client/files/' + doc.file_metadata_id + '/download" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-download"></i></a></div>';
          if (doc.folder_area === 'client_uploads') {
            upCnt++; $uploads.append(row);
          } else {
            sharedCnt++; $shared.append(row);
          }
        });
        if (!sharedCnt) $shared.html('<div class="text-muted small py-2">No agency shared documents published yet.</div>');
        if (!upCnt) $uploads.html('<div class="text-muted small py-2">You have not uploaded any reference files yet.</div>');
      });
    }

    function loadProjectDeliverables() {
      OrixoApi.request('GET', '/api/v1/client/projects/' + pid + '/deliverables').done(function (res) {
        var $el = $('#pdDeliverablesList').empty();
        if (!res.data.length) {
          $el.html('<div class="text-muted small py-3">No official deliverables published yet.</div>');
          return;
        }
        res.data.forEach(function (d) {
          $el.append(
            '<div class="d-flex justify-content-between align-items-center border rounded-2 p-3 mb-2">' +
            '<div><div class="fw-semibold">' + E(d.title) + ' <span class="badge bg-dark">' + E(d.version_label) + '</span></div>' +
            '<div class="small text-muted">' + E(d.description || '') + '</div>' +
            '<div class="text-muted" style="font-size:0.75rem;">' + E(d.original_name) + ' · ' + OrixoApi.formatBytes(d.file_size_bytes) + ' · Published ' + E(d.published_at) + '</div></div>' +
            '<a href="/api/v1/client/files/' + d.file_metadata_id + '/download" class="btn btn-sm btn-orixo"><i class="fa-solid fa-download me-1"></i>Download</a></div>'
          );
        });
      });
    }

    function loadProjectMessages() {
      OrixoApi.request('GET', '/api/v1/client/projects/' + pid + '/messages').done(function (res) {
        var $box = $('#pdMessagesBox').empty();
        var msgs = res.data.messages || [];
        if (!msgs.length) {
          $box.html('<div class="text-muted small py-3">No messages yet. Start the conversation below.</div>');
          return;
        }
        msgs.forEach(function (m) {
          var isClient = m.sender_role === 'client';
          var atts = (m.attachments || []).map(function (a) {
            return '<a href="/api/v1/client/files/' + a.file_metadata_id + '/download" class="badge bg-light text-dark border text-decoration-none me-1"><i class="fa-solid fa-paperclip me-1"></i>' + E(a.original_name) + '</a>';
          }).join('');
          $box.append(
            '<div class="p-3 rounded-2 mb-2 ' + (isClient ? 'bg-light border-start border-3 border-danger' : 'bg-white border') + '">' +
            '<div class="d-flex justify-content-between small mb-1"><strong>' + E(m.sender_name) + ' <span class="text-muted fw-normal">(' + E(m.sender_role.replace('_', ' ')) + ')</span></strong><span class="text-muted">' + E(m.created_at) + '</span></div>' +
            '<div class="small">' + E(m.body) + '</div>' + (atts ? '<div class="mt-2">' + atts + '</div>' : '') + '</div>'
          );
        });
      });
    }

    loadProjectDetail();
    loadProjectDocs();
    loadProjectDeliverables();
    loadProjectMessages();

    $('#clientUploadForm').on('submit', function (e) {
      e.preventDefault();
      var $btn = $(this).find('button[type="submit"]');
      OrixoApi.setLoading($btn, true, 'Uploading...');
      OrixoApi.request('POST', '/api/v1/client/projects/' + pid + '/client-uploads', new FormData(this), true).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        $('#clientUploadForm')[0].reset();
        loadProjectDocs();
      }).always(function () { OrixoApi.setLoading($btn, false); });
    });

    $('#clientMessageForm').on('submit', function (e) {
      e.preventDefault();
      var $btn = $(this).find('button[type="submit"]');
      OrixoApi.setLoading($btn, true, 'Sending...');
      OrixoApi.request('POST', '/api/v1/client/projects/' + pid + '/messages', new FormData(this), true).done(function () {
        $('#clientMessageForm')[0].reset();
        loadProjectMessages();
        OrixoApi.toast('Message sent.', 'success');
      }).always(function () { OrixoApi.setLoading($btn, false); });
    });
  }

  // 4. Standalone Deliverables, Documents, Messages, Invoices, Notifications, Profile
  if ($('#clientAllDeliverablesTable').length) {
    OrixoApi.request('GET', '/api/v1/client/deliverables').done(function (res) {
      var $tb = $('#clientAllDeliverablesTable tbody').empty();
      if (!res.data.length) $tb.html('<tr><td colspan="5" class="text-center text-muted py-4">No deliverables published yet.</td></tr>');
      res.data.forEach(function (d) {
        $tb.append('<tr><td><span class="font-mono-code">' + E(d.project_code) + '</span> ' + E(d.project_title) + '</td><td class="fw-semibold">' + E(d.title) + '</td><td><span class="badge bg-dark">' + E(d.version_label) + '</span></td><td>' + E(d.published_at) + '</td><td class="text-end"><a href="/api/v1/client/files/' + d.file_metadata_id + '/download" class="btn btn-sm btn-orixo">Download</a></td></tr>');
      });
    });
  }

  if ($('#clientAllDocsTable').length) {
    OrixoApi.request('GET', '/api/v1/client/documents').done(function (res) {
      var $tb = $('#clientAllDocsTable tbody').empty();
      if (!res.data.length) $tb.html('<tr><td colspan="6" class="text-center text-muted py-4">No documents available.</td></tr>');
      res.data.forEach(function (d) {
        var area = d.folder_area === 'client_uploads' ? '<span class="status-pill status-info">Client Upload</span>' : '<span class="status-pill status-success">Agency Shared</span>';
        $tb.append('<tr><td>' + E(d.project_code) + '</td><td class="fw-semibold">' + E(d.title) + '</td><td>' + area + '</td><td>' + OrixoApi.statusBadge(d.review_status) + '</td><td>' + OrixoApi.formatBytes(d.file_size_bytes) + '</td><td class="text-end"><a href="/api/v1/client/files/' + d.file_metadata_id + '/download" class="btn btn-sm btn-outline-secondary">Download</a></td></tr>');
      });
    });
  }

  if ($('#clientMessagesHub').length) {
    OrixoApi.request('GET', '/api/v1/client/projects').done(function (res) {
      var $list = $('#clientMsgProjects').empty();
      if (!res.data.length) $list.html('<div class="text-muted small">No projects available.</div>');
      res.data.forEach(function (p) {
        $list.append('<a href="/portal/projects/' + p.id + '#messages" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><div><div class="fw-semibold">' + E(p.title) + '</div><small class="text-muted">' + E(p.project_code) + '</small></div><span class="btn btn-sm btn-orixo-outline">Open Thread</span></a>');
      });
    });
  }

  if ($('#clientInvoicesTable').length) {
    OrixoApi.request('GET', '/api/v1/client/invoices').done(function (res) {
      var $tb = $('#clientInvoicesTable tbody').empty();
      if (!res.data.length) $tb.html('<tr><td colspan="8" class="text-center text-muted py-4">No invoices issued yet.</td></tr>');
      res.data.forEach(function (i) {
        var st = i.is_overdue ? OrixoApi.statusBadge('overdue') : OrixoApi.statusBadge(i.status);
        $tb.append(
          '<tr><td class="font-mono-code">' + E(i.invoice_number) + '</td><td>' + E(i.project_title) + '</td>' +
          '<td>' + E(i.issue_date) + '</td><td>' + E(i.due_date) + '</td>' +
          '<td class="tabular-nums fw-semibold">' + E(i.formatted_total) + '</td>' +
          '<td class="tabular-nums">' + E(i.formatted_balance) + '</td>' +
          '<td>' + st + '</td>' +
          '<td class="text-end"><a href="/portal/invoices/' + i.id + '" class="btn btn-sm btn-outline-secondary">View</a> <a href="/api/v1/client/invoices/' + i.id + '/pdf" class="btn btn-sm btn-orixo">PDF</a></td></tr>'
        );
      });
    });
  }

  if ($('#clientInvoiceDetailRoot').length) {
    var invId = $('#clientInvoiceDetailRoot').data('invoice-id');
    OrixoApi.request('GET', '/api/v1/client/invoices/' + invId).done(function (res) {
      var inv = res.data.invoice;
      $('#ciNumber').text(inv.invoice_number);
      $('#ciStatus').html(inv.is_overdue ? OrixoApi.statusBadge('overdue') : OrixoApi.statusBadge(inv.status));
      $('#ciProject').text(inv.project_code + ' — ' + inv.project_title);
      $('#ciDates').text('Issued: ' + inv.issue_date + ' | Due: ' + inv.due_date);
      $('#ciSubtotal').text(inv.formatted_subtotal);
      $('#ciTax').text(inv.formatted_tax + ' (' + inv.tax_rate + '%)');
      $('#ciTotal').text(inv.formatted_total);
      $('#ciPaid').text(inv.formatted_paid);
      $('#ciBalance').text(inv.formatted_balance);

      var $it = $('#ciItemsTable tbody').empty();
      res.data.items.forEach(function (item) {
        $it.append('<tr><td>' + E(item.description) + '</td><td class="text-end tabular-nums">' + E(item.quantity) + '</td><td class="text-end tabular-nums">' + E(item.formatted_unit_price) + '</td><td class="text-end tabular-nums fw-semibold">' + E(item.formatted_line_total) + '</td></tr>');
      });

      var $pt = $('#ciPaymentsTable tbody').empty();
      if (!res.data.payments.length) $pt.html('<tr><td colspan="4" class="text-center text-muted small py-3">No recorded payments yet.</td></tr>');
      res.data.payments.forEach(function (pm) {
        $pt.append('<tr><td class="font-mono-code">' + E(pm.payment_reference) + '</td><td>' + E(pm.payment_date) + '</td><td>' + E(pm.payment_method.toUpperCase()) + '</td><td class="text-end tabular-nums fw-semibold">' + E(pm.formatted_amount) + '</td></tr>');
      });
    });
  }

  if ($('#clientNotificationsList').length) {
    function loadNotifs() {
      OrixoApi.request('GET', '/api/v1/client/notifications').done(function (res) {
        var $el = $('#clientNotificationsList').empty();
        if (!res.data.length) $el.html('<div class="text-muted small py-3">No notifications.</div>');
        res.data.forEach(function (n) {
          $el.append(
            '<div class="d-flex justify-content-between align-items-center border-bottom py-3">' +
            '<div><div class="fw-semibold">' + E(n.title) + (!n.is_read ? ' <span class="badge bg-danger ms-1">Unread</span>' : '') + '</div>' +
            '<div class="small text-muted">' + E(n.message) + ' · ' + E(n.created_at) + '</div></div>' +
            (!n.is_read ? '<button class="btn btn-sm btn-outline-secondary js-mark-read" data-id="' + n.id + '">Mark Read</button>' : '<span class="small text-muted">Read</span>') + '</div>'
          );
        });
      });
    }
    loadNotifs();
    $(document).on('click', '.js-mark-read', function () {
      OrixoApi.request('PATCH', '/api/v1/client/notifications/' + $(this).data('id') + '/read', {}).done(loadNotifs);
    });
  }

  $('#clientProfileForm').on('submit', function (e) {
    e.preventDefault();
    var payload = {};
    $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
    OrixoApi.request('PATCH', '/api/v1/client/profile', payload).done(function (res) {
      OrixoApi.toast(res.message, 'success');
    });
  });

  $('#clientPasswordForm').on('submit', function (e) {
    e.preventDefault();
    var payload = {};
    $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
    OrixoApi.request('PATCH', '/api/v1/client/password', payload).done(function (res) {
      OrixoApi.toast(res.message, 'success');
      $('#clientPasswordForm')[0].reset();
    });
  });
})(jQuery);

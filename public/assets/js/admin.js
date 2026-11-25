(function ($) {
  'use strict';
  var E = OrixoApi.escapeHtml;
  var role = $('.admin-shell').data('role') || 'team_member';

  // 1. Admin Dashboard
  if ($('#adminDashboardRoot').length) {
    OrixoApi.request('GET', '/api/v1/admin/dashboard').done(function (res) {
      var k = res.data.kpis;
      $('#admKpiClients').text(k.active_clients + ' / ' + k.total_clients);
      $('#admKpiProjects').text(k.in_progress_projects + ' / ' + k.total_projects);
      $('#admKpiBlocked').text(k.blocked_projects);
      $('#admKpiInquiries').text(k.new_inquiries);
      $('#admKpiDeliverables').text(k.pending_deliverables);
      $('#admKpiInvoices').text(k.total_invoices);

      var $fin = $('#admFinanceSummary').empty();
      if (!res.data.finance_summary.length) {
        $fin.html('<div class="text-muted small">No active invoices yet.</div>');
      } else {
        res.data.finance_summary.forEach(function (f) {
          $fin.append(
            '<div class="d-flex flex-wrap justify-content-between align-items-center border-bottom py-2 small">' +
            '<strong>' + E(f.currency_code) + ' (' + f.invoice_count + ' invoices)</strong>' +
            '<div class="tabular-nums">Invoiced: <strong>' + E(f.formatted_invoiced) + '</strong> · Collected: <strong class="text-success">' + E(f.formatted_collected) + '</strong> · Outstanding: <strong class="text-danger">' + E(f.formatted_outstanding) + '</strong></div></div>'
          );
        });
      }

      var $att = $('#admAttentionList').empty();
      if (!res.data.attention_projects.length) {
        $att.html('<div class="text-muted small">All active projects have zero blocked tasks.</div>');
      } else {
        res.data.attention_projects.forEach(function (p) {
          $att.append('<a href="/admin/projects/' + p.id + '" class="d-flex justify-content-between align-items-center border-bottom py-2 text-decoration-none text-dark"><div><div class="fw-semibold small">' + E(p.title) + '</div><small class="text-muted">' + E(p.company_name) + '</small></div><span class="status-pill status-danger">' + p.blocked_tasks_count + ' Blocked</span></a>');
        });
      }

      var $act = $('#admActivityList').empty();
      res.data.recent_activity.forEach(function (a) {
        $act.append('<div class="border-bottom py-2 small"><div class="fw-medium">' + E(a.summary) + '</div><div class="text-muted" style="font-size:0.75rem;">' + E(a.actor_name || 'System') + ' · ' + E(a.action) + ' · ' + E(a.created_at) + '</div></div>');
      });
    });
  }

  // 2. Inquiries & Contact Inbox
  function loadInquiries() {
    OrixoApi.request('GET', '/api/v1/admin/inquiries', {
      inquiry_type: $('#inqFilterType').val(),
      status: $('#inqFilterStatus').val(),
      search: $('#inqFilterSearch').val()
    }).done(function (res) {
      var $tb = $('#adminInquiriesTable tbody').empty();
      if (!res.data.length) {
        $tb.html('<tr><td colspan="7" class="text-center text-muted py-4">No inquiries found.</td></tr>');
        return;
      }
      res.data.forEach(function (i) {
        var typeLabel = i.inquiry_type === 'general_contact' ? '<span class="status-pill status-neutral">Contact</span>' : '<span class="status-pill status-info">Project Inquiry</span>';
        $tb.append(
          '<tr><td class="font-mono-code">' + E(i.reference_code) + '</td><td>' + typeLabel + '</td>' +
          '<td><div class="fw-semibold">' + E(i.full_name) + '</div><div class="small text-muted">' + E(i.email) + '</div></td>' +
          '<td>' + E(i.company_name || '—') + '</td>' +
          '<td>' + OrixoApi.statusBadge(i.status) + '</td><td>' + E(i.created_at) + '</td>' +
          '<td class="text-end"><button class="btn btn-sm btn-outline-secondary js-view-inquiry" data-id="' + i.id + '">Review</button></td></tr>'
        );
      });
    });
  }
  if ($('#adminInquiriesTable').length) {
    loadInquiries();
    $('#inqFilterType, #inqFilterStatus, #inqFilterSearch').on('change keyup', loadInquiries);

    $(document).on('click', '.js-view-inquiry', function () {
      var id = $(this).data('id');
      OrixoApi.request('GET', '/api/v1/admin/inquiries/' + id).done(function (res) {
        var i = res.data;
        $('#inqModalRef').text(i.reference_code);
        $('#inqModalId').val(i.id);
        $('#inqModalBody').html(
          '<div class="row g-2 small mb-3">' +
          '<div class="col-md-6"><strong>Name:</strong> ' + E(i.full_name) + '</div>' +
          '<div class="col-md-6"><strong>Email:</strong> ' + E(i.email) + '</div>' +
          '<div class="col-md-6"><strong>Company:</strong> ' + E(i.company_name || '—') + '</div>' +
          '<div class="col-md-6"><strong>Budget / Timeline:</strong> ' + E((i.estimated_budget || '—') + ' / ' + (i.expected_timeline || '—')) + '</div>' +
          '<div class="col-12"><strong>Project Title:</strong> ' + E(i.project_title || '—') + '</div>' +
          '<div class="col-12 p-2 bg-light rounded"><strong>Message:</strong><br>' + E(i.message_or_description) + '</div></div>'
        );
        var atts = (i.attachments || []).map(function (a) {
          return '<a href="/api/v1/admin/inquiries/' + i.id + '/attachments/' + a.attachment_id + '/download" class="btn btn-sm btn-outline-secondary me-2"><i class="fa-solid fa-download me-1"></i>' + E(a.original_name) + '</a>';
        }).join('');
        $('#inqModalAttachments').html(atts || '<span class="small text-muted">No attachments.</span>');
        $('#inqModalStatus').val(i.status);
        $('#inqModalNotes').val(i.internal_notes || '');

        // Pre-fill conversion modal
        $('#convInqId').val(i.id);
        $('#convClientName').val(i.full_name);
        $('#convClientEmail').val(i.email);
        $('#convCompany').val(i.company_name || i.full_name);
        $('#convProjectTitle').val(i.project_title || 'Digital Engagement');
        $('#convProjectDesc').val(i.message_or_description);
        $('#convBtnOpen').toggle(i.status !== 'converted');

        new bootstrap.Modal('#inquiryReviewModal').show();
      });
    });

    $('#inquiryUpdateForm').on('submit', function (e) {
      e.preventDefault();
      var id = $('#inqModalId').val();
      OrixoApi.request('PATCH', '/api/v1/admin/inquiries/' + id, {
        status: $('#inqModalStatus').val(),
        internal_notes: $('#inqModalNotes').val()
      }).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        bootstrap.Modal.getInstance('#inquiryReviewModal').hide();
        loadInquiries();
      });
    });

    $('#convBtnOpen').on('click', function () {
      bootstrap.Modal.getInstance('#inquiryReviewModal').hide();
      new bootstrap.Modal('#convertInquiryModal').show();
    });

    $('#convertInquiryForm').on('submit', function (e) {
      e.preventDefault();
      var id = $('#convInqId').val();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/inquiries/' + id + '/convert', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        bootstrap.Modal.getInstance('#convertInquiryModal').hide();
        loadInquiries();
      });
    });
  }

  // 3. Clients Management
  function loadClients() {
    OrixoApi.request('GET', '/api/v1/admin/clients', { search: $('#cliSearch').val(), status: $('#cliStatus').val() }).done(function (res) {
      var $tb = $('#adminClientsTable tbody').empty();
      if (!res.data.length) $tb.html('<tr><td colspan="6" class="text-center text-muted py-4">No clients found.</td></tr>');
      res.data.forEach(function (c) {
        var actBtn = c.user_status === 'pending_activation'
          ? '<button class="btn btn-sm btn-outline-warning js-resend-act" data-id="' + c.id + '">Resend Invite</button> ' : '';
        var toggleLabel = c.status === 'active' ? 'Deactivate' : 'Activate';
        var nextStatus = c.status === 'active' ? 'inactive' : 'active';
        $tb.append(
          '<tr><td class="fw-semibold">' + E(c.company_name) + '</td><td><div>' + E(c.full_name) + '</div><small class="text-muted">' + E(c.email) + '</small></td>' +
          '<td>' + c.project_count + '</td><td>' + OrixoApi.statusBadge(c.user_status) + '</td><td>' + E(c.last_login_at || 'Never') + '</td>' +
          '<td class="text-end">' + actBtn + '<button class="btn btn-sm btn-outline-secondary js-toggle-client" data-id="' + c.id + '" data-status="' + nextStatus + '">' + toggleLabel + '</button></td></tr>'
        );
      });
    });
  }
  if ($('#adminClientsTable').length) {
    loadClients();
    $('#cliSearch, #cliStatus').on('change keyup', loadClients);
    $('#createClientForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/clients', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        bootstrap.Modal.getInstance('#createClientModal').hide();
        $('#createClientForm')[0].reset();
        loadClients();
      });
    });
    $(document).on('click', '.js-resend-act', function () {
      OrixoApi.request('POST', '/api/v1/admin/clients/' + $(this).data('id') + '/resend-activation', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
      });
    });
    $(document).on('click', '.js-toggle-client', function () {
      var id = $(this).data('id'), st = $(this).data('status');
      OrixoApi.request('PATCH', '/api/v1/admin/clients/' + id, { status: st }).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadClients();
      });
    });
  }

  // 4. Projects List (Admin)
  function loadAdminProjects() {
    OrixoApi.request('GET', '/api/v1/admin/projects', { search: $('#apSearch').val(), status: $('#apStatus').val() }).done(function (res) {
      var $tb = $('#adminProjectsTable tbody').empty();
      if (!res.data.length) $tb.html('<tr><td colspan="7" class="text-center text-muted py-4">No projects found.</td></tr>');
      res.data.forEach(function (p) {
        $tb.append(
          '<tr><td class="font-mono-code">' + E(p.project_code) + '</td><td><a href="/admin/projects/' + p.id + '" class="fw-semibold text-dark text-decoration-none">' + E(p.title) + '</a></td>' +
          '<td>' + E(p.company_name) + '</td><td>' + E(p.pm_name || '—') + '</td><td>' + OrixoApi.statusBadge(p.status) + '</td>' +
          '<td class="tabular-nums">' + p.progress_percentage + '% <small class="text-muted">(' + p.progress_mode + ')</small></td>' +
          '<td class="text-end"><a href="/admin/projects/' + p.id + '" class="btn btn-sm btn-orixo-outline">Manage</a></td></tr>'
        );
      });
    });
  }
  if ($('#adminProjectsTable').length) {
    loadAdminProjects();
    $('#apSearch, #apStatus').on('change keyup', loadAdminProjects);
    $('#createProjectForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/projects', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        bootstrap.Modal.getInstance('#createProjectModal').hide();
        $('#createProjectForm')[0].reset();
        loadAdminProjects();
      });
    });
  }

  // 5. Project Detail Workspace (Shared Admin + Restricted Team Workspace)
  var $admPd = $('#adminProjectDetailRoot');
  if ($admPd.length) {
    var pid = $admPd.data('project-id');
    var isTeamWs = String($admPd.data('team-ws')) === '1';

    function loadWsProject() {
      OrixoApi.request('GET', '/api/v1/workspace/projects/' + pid).done(function (res) {
        var p = res.data.project;
        $('#apdCode').text(p.project_code);
        $('#apdTitle').text(p.title);
        $('#apdClient').text(res.data.client ? (res.data.client.company_name + ' (' + res.data.client.contact_name + ')') : '');
        $('#apdStatus').html(OrixoApi.statusBadge(p.status));
        $('#apdProgressPct').text(p.progress_percentage + '%');
        $('#apdProgressBar').css('width', p.progress_percentage + '%');
        $('#apdProgressMeta').text(p.completed_tasks_count + '/' + p.total_active_tasks + ' completed · ' + p.blocked_tasks_count + ' blocked · Mode: ' + p.progress_mode.toUpperCase());
        $('#progModeSelect').val(p.progress_mode);
        $('#progManualPct').val(p.progress_percentage);

        var $team = $('#apdTeamTable tbody').empty();
        res.data.team.forEach(function (t) {
          var rmBtn = (!isTeamWs && role === 'admin') ? '<button class="btn btn-sm btn-outline-danger js-rm-assign" data-tid="' + t.team_member_id + '">Remove</button>' : '';
          $team.append('<tr><td class="fw-semibold">' + E(t.full_name) + '</td><td>' + E(t.designation) + '</td><td>' + E(t.project_role) + '</td><td class="text-end">' + rmBtn + '</td></tr>');
        });

        var $ms = $('#apdMilestonesTable tbody').empty();
        var $taskMs = $('#taskMilestoneSelect').html('<option value="">No milestone</option>');
        res.data.milestones.forEach(function (m) {
          $taskMs.append('<option value="' + m.id + '">' + E(m.title) + '</option>');
          var msActions = (!isTeamWs && role === 'admin')
            ? '<button class="btn btn-sm btn-outline-success me-1 js-ms-status" data-id="' + m.id + '" data-status="completed">Complete</button><button class="btn btn-sm btn-outline-danger js-ms-del" data-id="' + m.id + '">Delete</button>'
            : '';
          $ms.append('<tr><td class="fw-semibold">' + E(m.title) + '</td><td>' + E(m.planned_date || '—') + '</td><td>' + OrixoApi.statusBadge(m.status) + '</td><td class="text-end">' + msActions + '</td></tr>');
        });

        var $tasks = $('#apdTasksTable tbody').empty();
        if (!res.data.tasks.length) $tasks.html('<tr><td colspan="6" class="text-center text-muted py-3">No tasks yet.</td></tr>');
        res.data.tasks.forEach(function (t) {
          var sel = '<select class="form-select form-select-sm js-task-status" data-id="' + t.id + '" style="width:140px;">' +
            ['not_started', 'in_progress', 'review', 'completed', 'blocked'].map(function (st) {
              return '<option value="' + st + '"' + (t.status === st ? ' selected' : '') + '>' + st.replace('_', ' ') + '</option>';
            }).join('') + '</select>';
          $tasks.append('<tr><td class="fw-medium">' + E(t.title) + '</td><td>' + E(t.assignee_name || 'Unassigned') + '</td><td>' + OrixoApi.statusBadge(t.priority) + '</td><td>' + sel + '</td><td>' + E(t.due_date || '—') + '</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary js-task-archive" data-id="' + t.id + '">Archive</button></td></tr>');
        });
      });
    }

    function loadWsDocs() {
      OrixoApi.request('GET', '/api/v1/workspace/projects/' + pid + '/documents').done(function (res) {
        var $tb = $('#apdDocsTable tbody').empty();
        if (!res.data.length) $tb.html('<tr><td colspan="6" class="text-center text-muted py-3">No documents yet.</td></tr>');
        res.data.forEach(function (d) {
          var btns = '<a href="/api/v1/workspace/files/' + d.file_metadata_id + '/download" class="btn btn-sm btn-outline-secondary me-1"><i class="fa-solid fa-download"></i></a>';
          if (d.review_status === 'pending_review') {
            btns += '<button class="btn btn-sm btn-outline-info me-1 js-doc-review" data-id="' + d.id + '">Mark Reviewed</button>';
          }
          if (!isTeamWs && role === 'admin') {
            if (!Number(d.is_client_visible) && d.folder_area !== 'client_uploads') {
              btns += '<button class="btn btn-sm btn-orixo me-1 js-doc-publish" data-id="' + d.id + '">Publish to Client</button>';
            } else if (d.folder_area === 'agency_shared') {
              btns += '<button class="btn btn-sm btn-outline-warning me-1 js-doc-unpublish" data-id="' + d.id + '">Hide</button>';
            }
          }
          $tb.append('<tr><td class="fw-semibold">' + E(d.title) + '<div class="small text-muted">' + E(d.original_name) + '</div></td><td>' + E(d.folder_area) + '</td><td>' + OrixoApi.statusBadge(d.review_status) + '</td><td>' + (Number(d.is_client_visible) ? '<span class="badge bg-success">Visible</span>' : '<span class="badge bg-secondary">Internal</span>') + '</td><td>' + E(d.uploaded_by_name) + '</td><td class="text-end">' + btns + '</td></tr>');
        });
      });
    }

    function loadWsDeliverables() {
      OrixoApi.request('GET', '/api/v1/workspace/projects/' + pid + '/deliverables').done(function (res) {
        var $tb = $('#apdDeliverablesTable tbody').empty();
        if (!res.data.length) $tb.html('<tr><td colspan="6" class="text-center text-muted py-3">No deliverables uploaded yet.</td></tr>');
        res.data.forEach(function (d) {
          var btns = '<a href="/api/v1/workspace/files/' + d.file_metadata_id + '/download" class="btn btn-sm btn-outline-secondary me-1"><i class="fa-solid fa-download"></i></a>';
          if (!isTeamWs && role === 'admin' && d.review_status !== 'approved_published') {
            btns += '<button class="btn btn-sm btn-orixo me-1 js-deliv-publish" data-id="' + d.id + '">Publish</button>';
            btns += '<button class="btn btn-sm btn-outline-danger js-deliv-reject" data-id="' + d.id + '">Reject</button>';
          }
          $tb.append('<tr><td class="fw-semibold">' + E(d.title) + ' <span class="badge bg-dark">' + E(d.version_label) + '</span><div class="small text-muted">' + E(d.original_name) + '</div></td><td>' + OrixoApi.statusBadge(d.review_status) + '</td><td>' + (Number(d.is_published_to_client) ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Hidden</span>') + '</td><td>' + E(d.submitted_by_name) + '</td><td>' + E(d.created_at) + '</td><td class="text-end">' + btns + '</td></tr>');
        });
      });
    }

    function loadWsMessages() {
      OrixoApi.request('GET', '/api/v1/workspace/projects/' + pid + '/messages').done(function (res) {
        var $box = $('#apdMessagesBox').empty();
        (res.data.messages || []).forEach(function (m) {
          $box.append('<div class="p-2 border rounded mb-2"><div class="d-flex justify-content-between small"><strong>' + E(m.sender_name) + ' (' + E(m.sender_role) + ')</strong><span class="text-muted">' + E(m.created_at) + '</span></div><div class="small mt-1">' + E(m.body) + '</div></div>');
        });
      });
    }

    loadWsProject();
    loadWsDocs();
    loadWsDeliverables();
    loadWsMessages();

    $('#wsTaskForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/workspace/projects/' + pid + '/tasks', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        $('#wsTaskForm')[0].reset();
        loadWsProject();
      });
    });

    $(document).on('change', '.js-task-status', function () {
      OrixoApi.request('PATCH', '/api/v1/workspace/tasks/' + $(this).data('id'), { status: $(this).val() }).done(function () {
        OrixoApi.toast('Task status & progress updated.', 'success');
        loadWsProject();
      });
    });

    $(document).on('click', '.js-task-archive', function () {
      if (!confirm('Archive this task? Archived tasks are excluded from progress calculation.')) return;
      OrixoApi.request('PATCH', '/api/v1/workspace/tasks/' + $(this).data('id'), { is_archived: 1 }).done(function () {
        OrixoApi.toast('Task archived.', 'success');
        loadWsProject();
      });
    });

    $('#wsDocUploadForm').on('submit', function (e) {
      e.preventDefault();
      OrixoApi.request('POST', '/api/v1/workspace/projects/' + pid + '/documents', new FormData(this), true).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        $('#wsDocUploadForm')[0].reset();
        loadWsDocs();
      });
    });

    $(document).on('click', '.js-doc-review', function () {
      OrixoApi.request('PATCH', '/api/v1/workspace/documents/' + $(this).data('id') + '/review', { review_status: 'reviewed' }).done(function () {
        OrixoApi.toast('Marked reviewed.', 'success');
        loadWsDocs();
      });
    });

    $(document).on('click', '.js-doc-publish', function () {
      OrixoApi.request('PATCH', '/api/v1/admin/documents/' + $(this).data('id') + '/publish', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadWsDocs();
      });
    });

    $(document).on('click', '.js-doc-unpublish', function () {
      OrixoApi.request('PATCH', '/api/v1/admin/documents/' + $(this).data('id') + '/unpublish', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadWsDocs();
      });
    });

    $('#wsDeliverableForm').on('submit', function (e) {
      e.preventDefault();
      OrixoApi.request('POST', '/api/v1/workspace/projects/' + pid + '/deliverables', new FormData(this), true).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        $('#wsDeliverableForm')[0].reset();
        loadWsDeliverables();
      });
    });

    $(document).on('click', '.js-deliv-publish', function () {
      if (!confirm('Publish this official deliverable to the Client Portal?')) return;
      OrixoApi.request('PATCH', '/api/v1/admin/deliverables/' + $(this).data('id') + '/publish', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadWsDeliverables();
      });
    });

    $(document).on('click', '.js-deliv-reject', function () {
      var notes = prompt('Enter rejection feedback for the team member:');
      if (!notes) return;
      OrixoApi.request('PATCH', '/api/v1/admin/deliverables/' + $(this).data('id') + '/reject', { notes: notes }).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadWsDeliverables();
      });
    });

    $('#wsMessageForm').on('submit', function (e) {
      e.preventDefault();
      OrixoApi.request('POST', '/api/v1/workspace/projects/' + pid + '/messages', new FormData(this), true).done(function () {
        $('#wsMessageForm')[0].reset();
        loadWsMessages();
        OrixoApi.toast('Reply posted.', 'success');
      });
    });

    // Admin-only Project Progress, Assignments & Milestones
    $('#admProgressForm').on('submit', function (e) {
      e.preventDefault();
      OrixoApi.request('PATCH', '/api/v1/admin/projects/' + pid + '/progress', {
        progress_mode: $('#progModeSelect').val(),
        progress_percentage: $('#progManualPct').val()
      }).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadWsProject();
      });
    });

    $('#admAssignForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/projects/' + pid + '/assignments', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadWsProject();
      });
    });

    $(document).on('click', '.js-rm-assign', function () {
      if (!confirm('Remove this team member? Access is revoked immediately.')) return;
      OrixoApi.request('DELETE', '/api/v1/admin/projects/' + pid + '/assignments/' + $(this).data('tid')).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadWsProject();
      });
    });

    $('#admMilestoneForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/projects/' + pid + '/milestones', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        $('#admMilestoneForm')[0].reset();
        loadWsProject();
      });
    });

    $(document).on('click', '.js-ms-status', function () {
      OrixoApi.request('PATCH', '/api/v1/admin/milestones/' + $(this).data('id'), { status: $(this).data('status') }).done(loadWsProject);
    });
    $(document).on('click', '.js-ms-del', function () {
      if (!confirm('Delete this milestone?')) return;
      OrixoApi.request('DELETE', '/api/v1/admin/milestones/' + $(this).data('id')).done(loadWsProject);
    });
  }

  // 6. Invoices & Payments (Admin)
  function loadAdminInvoices() {
    OrixoApi.request('GET', '/api/v1/admin/invoices', { search: $('#invSearch').val(), status: $('#invStatus').val() }).done(function (res) {
      var $tb = $('#adminInvoicesTable tbody').empty();
      if (!res.data.length) $tb.html('<tr><td colspan="8" class="text-center text-muted py-4">No invoices found.</td></tr>');
      res.data.forEach(function (i) {
        var st = i.is_overdue ? OrixoApi.statusBadge('overdue') : OrixoApi.statusBadge(i.status);
        $tb.append(
          '<tr><td class="font-mono-code">' + E(i.invoice_number) + '</td><td>' + E(i.company_name) + '</td><td>' + E(i.project_code) + '</td>' +
          '<td class="tabular-nums">' + E(i.formatted_total) + '</td><td class="tabular-nums">' + E(i.formatted_paid) + '</td>' +
          '<td class="tabular-nums fw-semibold">' + E(i.formatted_balance) + '</td><td>' + st + '</td>' +
          '<td class="text-end"><a href="/admin/invoices/' + i.id + '" class="btn btn-sm btn-orixo-outline">Open</a></td></tr>'
        );
      });
    });
  }
  if ($('#adminInvoicesTable').length) {
    loadAdminInvoices();
    $('#invSearch, #invStatus').on('change keyup', loadAdminInvoices);

    $('#invClientSelect').on('change', function () {
      var cid = $(this).val();
      $('#invProjectSelect option').each(function () {
        var pcid = $(this).data('client');
        $(this).toggle(!pcid || String(pcid) === String(cid));
      });
      $('#invProjectSelect').val('');
    });

    $('#addInvItemBtn').on('click', function () {
      $('#invItemsContainer').append(
        '<div class="row g-2 mb-2 inv-item-row"><div class="col-md-6"><input type="text" class="form-control form-control-sm item-desc" placeholder="Line item description *" required></div>' +
        '<div class="col-md-2"><input type="number" step="0.01" class="form-control form-control-sm item-qty" value="1" required></div>' +
        '<div class="col-md-3"><input type="number" step="0.01" class="form-control form-control-sm item-price" placeholder="Unit price" required></div>' +
        '<div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100 js-rm-inv-row">×</button></div></div>'
      );
    });
    $(document).on('click', '.js-rm-inv-row', function () {
      if ($('.inv-item-row').length > 1) $(this).closest('.inv-item-row').remove();
    });

    $('#createInvoiceForm').on('submit', function (e) {
      e.preventDefault();
      var items = [];
      $('.inv-item-row').each(function () {
        items.push({
          description: $(this).find('.item-desc').val(),
          quantity: $(this).find('.item-qty').val(),
          unit_price: $(this).find('.item-price').val()
        });
      });
      var payload = {
        client_id: $('#invClientSelect').val(),
        project_id: $('#invProjectSelect').val(),
        currency_code: $('[name="currency_code"]', this).val(),
        issue_date: $('[name="issue_date"]', this).val(),
        due_date: $('[name="due_date"]', this).val(),
        tax_rate: $('[name="tax_rate"]', this).val(),
        notes: $('[name="notes"]', this).val(),
        items: items
      };
      OrixoApi.request('POST', '/api/v1/admin/invoices', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        window.location.href = '/admin/invoices/' + res.data.invoice_id;
      });
    });
  }

  // 7. Admin Invoice Detail & Payments
  var $admInvDet = $('#adminInvoiceDetailRoot');
  if ($admInvDet.length) {
    var invId = $admInvDet.data('invoice-id');
    function loadAdmInvoiceDetail() {
      OrixoApi.request('GET', '/api/v1/admin/invoices/' + invId).done(function (res) {
        var inv = res.data.invoice;
        $('#aiNumber').text(inv.invoice_number);
        $('#aiMeta').text(inv.company_name + ' · ' + inv.project_code + ' (' + inv.project_title + ') · Currency: ' + inv.currency_code);
        $('#aiStatus').html(inv.is_overdue ? OrixoApi.statusBadge('overdue') : OrixoApi.statusBadge(inv.status));
        $('#aiSubtotal').text(inv.formatted_subtotal);
        $('#aiTax').text(inv.formatted_tax + ' (' + inv.tax_rate + '%)');
        $('#aiTotal').text(inv.formatted_total);
        $('#aiPaid').text(inv.formatted_paid);
        $('#aiBalance').text(inv.formatted_balance);

        $('#btnIssueInvoice').toggle(inv.status === 'draft');
        $('#btnCancelInvoice').toggle(inv.status === 'draft' || (inv.status === 'issued' && Number(inv.amount_paid) === 0));
        $('#recordPaymentCard').toggle(inv.status === 'issued' || inv.status === 'partially_paid');

        var $it = $('#aiItemsTable tbody').empty();
        res.data.items.forEach(function (item) {
          $it.append('<tr><td>' + E(item.description) + '</td><td class="text-end tabular-nums">' + E(item.quantity) + '</td><td class="text-end tabular-nums">' + E(item.formatted_unit_price) + '</td><td class="text-end tabular-nums fw-semibold">' + E(item.formatted_line_total) + '</td></tr>');
        });

        var $pt = $('#aiPaymentsTable tbody').empty();
        if (!res.data.payments.length) $pt.html('<tr><td colspan="7" class="text-center text-muted py-3">No payments recorded.</td></tr>');
        res.data.payments.forEach(function (pm) {
          var voidBtn = pm.status === 'recorded' ? '<button class="btn btn-sm btn-outline-danger js-void-pay" data-id="' + pm.id + '">Void</button>' : '<small class="text-muted">' + E(pm.void_reason || '') + '</small>';
          $pt.append('<tr><td class="font-mono-code">' + E(pm.payment_reference) + '</td><td>' + E(pm.payment_date) + '</td><td>' + E(pm.payment_method.toUpperCase()) + '</td><td>' + E(pm.transaction_reference || '—') + '</td><td class="tabular-nums fw-semibold">' + E(pm.formatted_amount) + '</td><td>' + OrixoApi.statusBadge(pm.status) + '</td><td class="text-end">' + voidBtn + '</td></tr>');
        });
      });
    }
    loadAdmInvoiceDetail();

    $('#btnIssueInvoice').on('click', function () {
      if (!confirm('Issue this invoice? Once issued, it becomes visible to the client and generates a PDF.')) return;
      OrixoApi.request('POST', '/api/v1/admin/invoices/' + invId + '/issue', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadAdmInvoiceDetail();
      });
    });

    $('#btnCancelInvoice').on('click', function () {
      if (!confirm('Cancel this invoice?')) return;
      OrixoApi.request('POST', '/api/v1/admin/invoices/' + invId + '/cancel', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadAdmInvoiceDetail();
      });
    });

    $('#recordPaymentForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/invoices/' + invId + '/payments', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        $('#recordPaymentForm')[0].reset();
        loadAdmInvoiceDetail();
      });
    });

    $(document).on('click', '.js-void-pay', function () {
      var reason = prompt('Enter mandatory reason for voiding this payment entry:');
      if (!reason) return;
      OrixoApi.request('POST', '/api/v1/admin/payments/' + $(this).data('id') + '/void', { void_reason: reason }).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadAdmInvoiceDetail();
      });
    });
  }

  // 8. Team Members, Users, Settings, Email Queue, Activity Logs, CMS & Workspace Profile
  if ($('#adminTeamTable').length) {
    function loadTeam() {
      OrixoApi.request('GET', '/api/v1/admin/team-members').done(function (res) {
        var $tb = $('#adminTeamTable tbody').empty();
        res.data.forEach(function (t) {
          var nextSt = t.status === 'active' ? 'inactive' : 'active';
          var nextPub = Number(t.show_on_public_website) ? 0 : 1;
          $tb.append(
            '<tr><td class="fw-semibold">' + E(t.full_name) + '<div class="small text-muted">' + E(t.email) + '</div></td>' +
            '<td>' + E(t.designation) + '</td><td>' + t.assigned_projects + '</td>' +
            '<td>' + (Number(t.show_on_public_website) ? '<span class="badge bg-success">Public</span>' : '<span class="badge bg-secondary">Hidden</span>') + '</td>' +
            '<td>' + OrixoApi.statusBadge(t.status) + '</td>' +
            '<td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1 js-tm-pub" data-id="' + t.id + '" data-pub="' + nextPub + '">Toggle Public</button>' +
            '<button class="btn btn-sm btn-outline-secondary js-tm-st" data-id="' + t.id + '" data-st="' + nextSt + '">' + (t.status === 'active' ? 'Deactivate' : 'Activate') + '</button></td></tr>'
          );
        });
      });
    }
    loadTeam();
    $('#createTeamForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      payload.show_on_public_website = $('#tmShowPublic').is(':checked') ? 1 : 0;
      OrixoApi.request('POST', '/api/v1/admin/team-members', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        bootstrap.Modal.getInstance('#createTeamModal').hide();
        $('#createTeamForm')[0].reset();
        loadTeam();
      });
    });
    $(document).on('click', '.js-tm-pub', function () {
      OrixoApi.request('PATCH', '/api/v1/admin/team-members/' + $(this).data('id'), { show_on_public_website: $(this).data('pub') }).done(loadTeam);
    });
    $(document).on('click', '.js-tm-st', function () {
      OrixoApi.request('PATCH', '/api/v1/admin/team-members/' + $(this).data('id'), { status: $(this).data('st') }).done(loadTeam);
    });
  }

  if ($('#adminUsersTable').length) {
    function loadUsers() {
      OrixoApi.request('GET', '/api/v1/admin/users').done(function (res) {
        var $tb = $('#adminUsersTable tbody').empty();
        res.data.forEach(function (u) {
          $tb.append(
            '<tr><td class="fw-semibold">' + E(u.full_name) + '</td><td>' + E(u.email) + '</td>' +
            '<td><span class="badge bg-dark">' + E(u.role_name) + '</span></td>' +
            '<td>' + OrixoApi.statusBadge(u.status) + '</td><td>' + E(u.last_login_at || 'Never') + '</td>' +
            '<td class="text-end"><button class="btn btn-sm btn-outline-secondary js-toggle-usr" data-id="' + u.id + '" data-st="' + (u.status === 'active' ? 'inactive' : 'active') + '">' + (u.status === 'active' ? 'Deactivate' : 'Activate') + '</button></td></tr>'
          );
        });
      });
    }
    loadUsers();
    $(document).on('click', '.js-toggle-usr', function () {
      OrixoApi.request('PATCH', '/api/v1/admin/users/' + $(this).data('id'), { status: $(this).data('st') }).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadUsers();
      });
    });
  }

  if ($('#adminSettingsForm').length) {
    OrixoApi.request('GET', '/api/v1/admin/settings').done(function (res) {
      var s = res.data.settings;
      $('[data-setting]').each(function () {
        var k = $(this).data('setting');
        if (s[k] !== undefined) $(this).val(s[k]);
      });
    });
    $('#adminSettingsForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {};
      $('[data-setting]').each(function () { payload[$(this).data('setting')] = $(this).val(); });
      OrixoApi.request('PATCH', '/api/v1/admin/settings', payload).done(function (res) {
        OrixoApi.toast(res.message, 'success');
      });
    });
  }

  if ($('#adminEmailQueueTable').length) {
    function loadEmailQueue() {
      OrixoApi.request('GET', '/api/v1/admin/email-queue', { status: $('#eqStatus').val() }).done(function (res) {
        var $tb = $('#adminEmailQueueTable tbody').empty();
        if (!res.data.length) $tb.html('<tr><td colspan="7" class="text-center text-muted py-4">Email queue is empty.</td></tr>');
        res.data.forEach(function (q) {
          var retryBtn = (q.status === 'failed' || q.status === 'permanently_failed')
            ? '<button class="btn btn-sm btn-outline-primary js-eq-retry" data-id="' + q.id + '">Retry</button>' : '';
          $tb.append('<tr><td>#' + q.id + '</td><td>' + E(q.recipient_email) + '</td><td class="font-mono-code">' + E(q.template_key) + '</td><td>' + OrixoApi.statusBadge(q.status) + '</td><td>' + q.attempts + '/' + q.max_attempts + '</td><td class="small text-danger">' + E(q.last_error || '') + '</td><td class="text-end">' + retryBtn + '</td></tr>');
        });
      });
      OrixoApi.request('GET', '/api/v1/admin/email-templates').done(function (res) {
        var $el = $('#emailTemplatesList').empty();
        res.data.forEach(function (t) {
          $el.append('<div class="border rounded p-3 mb-2"><div class="d-flex justify-content-between"><strong>' + E(t.name) + '</strong><span class="font-mono-code text-muted">' + E(t.template_key) + '</span></div><div class="small text-muted">Subject: ' + E(t.subject_template) + '</div></div>');
        });
      });
    }
    loadEmailQueue();
    $('#eqStatus').on('change', loadEmailQueue);
    $('#btnProcessQueue').on('click', function () {
      OrixoApi.request('POST', '/api/v1/admin/email-queue/process', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadEmailQueue();
      });
    });
    $(document).on('click', '.js-eq-retry', function () {
      OrixoApi.request('POST', '/api/v1/admin/email-queue/' + $(this).data('id') + '/retry', {}).done(function (res) {
        OrixoApi.toast(res.message, 'success');
        loadEmailQueue();
      });
    });
  }

  if ($('#adminActivityLogsTable').length) {
    function loadLogs() {
      OrixoApi.request('GET', '/api/v1/admin/activity-logs', { action: $('#logSearch').val() }).done(function (res) {
        var $tb = $('#adminActivityLogsTable tbody').empty();
        res.data.forEach(function (l) {
          $tb.append('<tr><td class="small text-muted">' + E(l.created_at) + '</td><td>' + E(l.actor_name || 'System') + ' <small class="text-muted">(' + E(l.actor_role || 'system') + ')</small></td><td class="font-mono-code">' + E(l.action) + '</td><td>' + E(l.summary) + '</td><td class="font-mono-code small">' + E(l.ip_address || '') + '</td></tr>');
        });
      });
    }
    loadLogs();
    $('#logSearch').on('keyup', loadLogs);
  }

  if ($('#adminCmsRoot').length) {
    function loadCms() {
      OrixoApi.request('GET', '/api/v1/admin/cms/all').done(function (res) {
        var d = res.data;
        var $scSel = $('#cmsSvcCatSelect').empty();
        d.service_categories.forEach(function (c) { $scSel.append('<option value="' + c.id + '">' + E(c.name) + '</option>'); });

        var $pcSel = $('#cmsPortCatSelect').empty();
        d.portfolio_categories.forEach(function (c) { $pcSel.append('<option value="' + c.id + '">' + E(c.name) + '</option>'); });

        var $svcTb = $('#cmsServicesTable tbody').empty();
        d.services.forEach(function (s) {
          $svcTb.append('<tr><td class="fw-semibold">' + E(s.title) + '</td><td>' + E(s.category_name) + '</td><td>' + OrixoApi.statusBadge(s.status) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-danger js-cms-del-svc" data-id="' + s.id + '">Archive</button></td></tr>');
        });

        var $portTb = $('#cmsPortfolioTable tbody').empty();
        d.portfolio.forEach(function (p) {
          $portTb.append('<tr><td class="fw-semibold">' + E(p.title) + '</td><td>' + E(p.category_name) + '</td><td>' + OrixoApi.statusBadge(p.status) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-danger js-cms-del-port" data-id="' + p.id + '">Archive</button></td></tr>');
        });

        var $csTb = $('#cmsCaseStudiesTable tbody').empty();
        d.case_studies.forEach(function (c) {
          $csTb.append('<tr><td class="fw-semibold">' + E(c.title) + '</td><td>' + E(c.industry_name || '—') + '</td><td>' + OrixoApi.statusBadge(c.status) + '</td><td class="text-end"><button class="btn btn-sm btn-outline-danger js-cms-del-cs" data-id="' + c.id + '">Archive</button></td></tr>');
        });

        var $indTb = $('#cmsIndustriesTable tbody').empty();
        d.industries.forEach(function (i) { $indTb.append('<tr><td class="fw-semibold">' + E(i.name) + '</td><td>' + OrixoApi.statusBadge(i.status) + '</td></tr>'); });

        var $techTb = $('#cmsTechTable tbody').empty();
        d.technologies.forEach(function (t) { $techTb.append('<tr><td class="fw-semibold">' + E(t.name) + '</td><td>' + E(t.category) + '</td></tr>'); });

        var $testTb = $('#cmsTestimonialsTable tbody').empty();
        d.testimonials.forEach(function (tm) { $testTb.append('<tr><td class="fw-semibold">' + E(tm.client_name) + '</td><td>' + E(tm.company_name) + '</td><td>' + OrixoApi.statusBadge(tm.status) + '</td></tr>'); });

        var $pages = $('#cmsPagesList').empty();
        d.cms_pages.forEach(function (pg) {
          $pages.append(
            '<form class="border rounded p-3 mb-3 js-cms-page-form" data-key="' + E(pg.page_key) + '">' +
            '<div class="d-flex justify-content-between mb-2"><strong>' + E(pg.page_key.toUpperCase()) + '</strong><button type="submit" class="btn btn-sm btn-orixo">Save Page</button></div>' +
            '<input type="text" name="title" class="form-control form-control-sm mb-2" value="' + E(pg.title) + '" required>' +
            '<textarea name="content_body" rows="4" class="form-control form-control-sm" required>' + E(pg.content_body) + '</textarea></form>'
          );
        });
      });
    }
    loadCms();

    $('#cmsServiceForm').on('submit', function (e) {
      e.preventDefault();
      OrixoApi.request('POST', '/api/v1/admin/cms/services', new FormData(this), true).done(function (res) { OrixoApi.toast(res.message, 'success'); $('#cmsServiceForm')[0].reset(); loadCms(); });
    });
    $('#cmsPortfolioForm').on('submit', function (e) {
      e.preventDefault();
      OrixoApi.request('POST', '/api/v1/admin/cms/portfolio', new FormData(this), true).done(function (res) { OrixoApi.toast(res.message, 'success'); $('#cmsPortfolioForm')[0].reset(); loadCms(); });
    });
    $('#cmsCaseStudyForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {}; $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/cms/case-studies', payload).done(function (res) { OrixoApi.toast(res.message, 'success'); $('#cmsCaseStudyForm')[0].reset(); loadCms(); });
    });
    $('#cmsIndustryForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {}; $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/cms/industries', payload).done(function (res) { OrixoApi.toast(res.message, 'success'); $('#cmsIndustryForm')[0].reset(); loadCms(); });
    });
    $('#cmsTechForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {}; $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/cms/technologies', payload).done(function (res) { OrixoApi.toast(res.message, 'success'); $('#cmsTechForm')[0].reset(); loadCms(); });
    });
    $('#cmsTestimonialForm').on('submit', function (e) {
      e.preventDefault();
      var payload = {}; $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
      OrixoApi.request('POST', '/api/v1/admin/cms/testimonials', payload).done(function (res) { OrixoApi.toast(res.message, 'success'); $('#cmsTestimonialForm')[0].reset(); loadCms(); });
    });
    $(document).on('submit', '.js-cms-page-form', function (e) {
      e.preventDefault();
      var key = $(this).data('key');
      OrixoApi.request('PATCH', '/api/v1/admin/cms/pages/' + key, {
        title: $('[name="title"]', this).val(),
        content_body: $('[name="content_body"]', this).val()
      }).done(function (res) { OrixoApi.toast(res.message, 'success'); });
    });
    $(document).on('click', '.js-cms-del-svc', function () { OrixoApi.request('DELETE', '/api/v1/admin/cms/services/' + $(this).data('id')).done(loadCms); });
    $(document).on('click', '.js-cms-del-port', function () { OrixoApi.request('DELETE', '/api/v1/admin/cms/portfolio/' + $(this).data('id')).done(loadCms); });
    $(document).on('click', '.js-cms-del-cs', function () { OrixoApi.request('DELETE', '/api/v1/admin/cms/case-studies/' + $(this).data('id')).done(loadCms); });
  }

  // 9. Restricted Team Workspace Dashboard & Projects
  if ($('#wsDashboardRoot').length) {
    OrixoApi.request('GET', '/api/v1/workspace/dashboard').done(function (res) {
      $('#wsProjCount').text(res.data.assigned_projects_count);
      $('#wsTaskCount').text(res.data.open_tasks_count);
      var $p = $('#wsProjectsList').empty();
      if (!res.data.projects.length) $p.html('<div class="text-muted small py-3">No projects assigned to you yet.</div>');
      res.data.projects.forEach(function (pr) {
        $p.append('<a href="/admin/workspace/projects/' + pr.id + '" class="d-flex justify-content-between align-items-center border-bottom py-2 text-decoration-none text-dark"><div><div class="fw-semibold">' + E(pr.title) + '</div><small class="text-muted">' + E(pr.project_code) + ' · ' + E(pr.company_name) + '</small></div><span class="tabular-nums fw-bold">' + pr.progress_percentage + '%</span></a>');
      });
      var $t = $('#wsTasksList').empty();
      if (!res.data.tasks.length) $t.html('<div class="text-muted small py-3">No open tasks.</div>');
      res.data.tasks.forEach(function (tk) {
        $t.append('<div class="d-flex justify-content-between align-items-center border-bottom py-2 small"><div><div class="fw-medium">' + E(tk.title) + '</div><div class="text-muted">' + E(tk.project_code) + '</div></div>' + OrixoApi.statusBadge(tk.status) + '</div>');
      });
    });
  }

  if ($('#wsProjectsTable').length) {
    OrixoApi.request('GET', '/api/v1/workspace/projects').done(function (res) {
      var $tb = $('#wsProjectsTable tbody').empty();
      if (!res.data.length) $tb.html('<tr><td colspan="6" class="text-center text-muted py-4">No assigned projects.</td></tr>');
      res.data.forEach(function (p) {
        $tb.append('<tr><td class="font-mono-code">' + E(p.project_code) + '</td><td class="fw-semibold">' + E(p.title) + '</td><td>' + E(p.company_name) + '</td><td>' + OrixoApi.statusBadge(p.status) + '</td><td class="tabular-nums">' + p.progress_percentage + '%</td><td class="text-end"><a href="/admin/workspace/projects/' + p.id + '" class="btn btn-sm btn-orixo-outline">Open</a></td></tr>');
      });
    });
  }

  $('#wsProfileForm').on('submit', function (e) {
    e.preventDefault();
    var payload = {}; $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
    OrixoApi.request('PATCH', '/api/v1/workspace/profile', payload).done(function (res) { OrixoApi.toast(res.message, 'success'); });
  });
  $('#wsPasswordForm').on('submit', function (e) {
    e.preventDefault();
    var payload = {}; $(this).serializeArray().forEach(function (f) { payload[f.name] = f.value; });
    OrixoApi.request('PATCH', '/api/v1/workspace/password', payload).done(function (res) { OrixoApi.toast(res.message, 'success'); $('#wsPasswordForm')[0].reset(); });
  });
})(jQuery);

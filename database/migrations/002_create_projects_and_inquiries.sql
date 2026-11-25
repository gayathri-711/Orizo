-- Migration 002: File Metadata, Projects, Inquiries (with Circular FK Resolution), Assignments, Milestones & Tasks

CREATE TABLE IF NOT EXISTS file_metadata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    storage_disk ENUM('private','public') NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(120) NOT NULL,
    relative_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    sha256_checksum CHAR(64) NOT NULL,
    lifecycle_status ENUM('active','pending_deletion','deleted') NOT NULL DEFAULT 'active',
    uploaded_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_file_metadata_stored_name (stored_name),
    KEY idx_file_metadata_lifecycle (lifecycle_status, created_at),
    CONSTRAINT fk_file_metadata_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_code VARCHAR(30) NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    project_manager_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    scope_of_work TEXT NULL,
    start_date DATE NULL,
    target_completion_date DATE NULL,
    actual_completion_date DATE NULL,
    status ENUM('planning','in_progress','on_hold','completed','cancelled','archived') NOT NULL DEFAULT 'planning',
    progress_mode ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    progress_percentage TINYINT UNSIGNED NOT NULL DEFAULT 0,
    total_active_tasks INT UNSIGNED NOT NULL DEFAULT 0,
    completed_tasks_count INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_tasks_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_projects_code (project_code),
    UNIQUE KEY uq_projects_id_client (id, client_id),
    KEY idx_projects_client_status (client_id, status),
    CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_projects_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_pm FOREIGN KEY (project_manager_id) REFERENCES team_members(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(30) NOT NULL,
    inquiry_type ENUM('project_inquiry','general_contact') NOT NULL DEFAULT 'project_inquiry',
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NULL,
    company_name VARCHAR(160) NULL,
    service_id BIGINT UNSIGNED NULL,
    project_title VARCHAR(180) NULL,
    message_or_description TEXT NOT NULL,
    estimated_budget VARCHAR(80) NULL,
    expected_timeline VARCHAR(80) NULL,
    additional_info TEXT NULL,
    status ENUM('new','under_review','contacted','qualified','approved','rejected','converted','closed') NOT NULL DEFAULT 'new',
    internal_notes TEXT NULL,
    converted_client_id BIGINT UNSIGNED NULL,
    converted_project_id BIGINT UNSIGNED NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inquiries_ref (reference_code),
    KEY idx_inquiries_type_status (inquiry_type, status, created_at),
    KEY idx_inquiries_email (email),
    CONSTRAINT fk_inquiries_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    CONSTRAINT fk_inquiries_converted_client FOREIGN KEY (converted_client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_inquiries_converted_project FOREIGN KEY (converted_project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_inquiries_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Complete the circular FK on clients after inquiries exists
ALTER TABLE clients
    ADD CONSTRAINT fk_clients_created_from_inquiry
    FOREIGN KEY (created_from_inquiry_id) REFERENCES inquiries(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS inquiry_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inquiry_id BIGINT UNSIGNED NOT NULL,
    file_metadata_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inquiry_att_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
    CONSTRAINT fk_inquiry_att_file FOREIGN KEY (file_metadata_id) REFERENCES file_metadata(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    team_member_id BIGINT UNSIGNED NOT NULL,
    project_role VARCHAR(80) NOT NULL DEFAULT 'Contributor',
    assigned_by BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_assignment (project_id, team_member_id),
    CONSTRAINT fk_pa_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_team FOREIGN KEY (team_member_id) REFERENCES team_members(id),
    CONSTRAINT fk_pa_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    planned_date DATE NULL,
    completion_date DATE NULL,
    status ENUM('pending','in_progress','completed','delayed') NOT NULL DEFAULT 'pending',
    display_order INT NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_milestones_project_order (project_id, display_order),
    CONSTRAINT fk_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_milestones_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    milestone_id BIGINT UNSIGNED NULL,
    assigned_to_team_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('not_started','in_progress','review','completed','blocked') NOT NULL DEFAULT 'not_started',
    due_date DATE NULL,
    is_client_visible TINYINT(1) NOT NULL DEFAULT 1,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tasks_project_active (project_id, is_archived, status),
    KEY idx_tasks_assignee (assigned_to_team_id, status),
    CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_milestone FOREIGN KEY (milestone_id) REFERENCES project_milestones(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_assignee FOREIGN KEY (assigned_to_team_id) REFERENCES team_members(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    note VARCHAR(500) NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tsh_task_time (task_id, changed_at),
    CONSTRAINT fk_tsh_task FOREIGN KEY (task_id) REFERENCES project_tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tsh_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

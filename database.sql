CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Store only hashed passwords',
  `user_type` enum('applicant','admin','member') NOT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Set to 1 after OTP verification',
  `email_verification_token` varchar(100) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `google_id` VARCHAR(255) NULL,
  `auth_provider` ENUM('local', 'google') DEFAULT 'local',
  `profile_picture` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_user_type_status` (`user_type`, `status`, `deleted_at`),
  KEY `idx_users_email_verified` (`email_verified`),
  UNIQUE KEY (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE oauth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    token_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (provider, provider_user_id)
);
-- Create a table for team members
CREATE TABLE `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('Case Manager', 'Document Creator', 'Career Consultant', 'Business Plan Creator', 'Immigration Assistant', 'Social Media Manager', 'Leads & CRM Manager', 'Custom') NOT NULL,
  `custom_role_name` varchar(100) DEFAULT NULL COMMENT 'Name of custom role if role is set to Custom',
  `permissions` text DEFAULT NULL COMMENT 'JSON string of permissions associated with this role',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`, `deleted_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 

-- Main tasks table (revised)
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `admin_id` int(11) NOT NULL COMMENT 'The admin who created the task',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_priority` (`priority`),
  KEY `idx_tasks_due_date` (`due_date`),
  CONSTRAINT `tasks_admin_id_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task assignments table for multiple assignees
CREATE TABLE `task_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `team_member_id` int(11) NOT NULL COMMENT 'The team member assigned to the task',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_team_member` (`task_id`, `team_member_id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `idx_task_assignments_status` (`status`),
  CONSTRAINT `task_assignments_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_assignments_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task Comments table (unchanged but with updated foreign keys)
CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Can be admin or team member',
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `task_comments_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task attachments table (unchanged but with updated foreign keys)
CREATE TABLE `task_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Who uploaded the attachment',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `task_attachments_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_attachments_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Task activity log (expanded to include assignment activities)
CREATE TABLE `task_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `team_member_id` int(11) DEFAULT NULL COMMENT 'The team member being acted upon, if applicable',
  `activity_type` enum('created','updated','status_changed','assigned','unassigned','member_status_changed','commented','attachment_added') NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `idx_task_activity_type` (`activity_type`),
  CONSTRAINT `task_activity_logs_task_id_fk` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_activity_logs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_activity_logs_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Countries table
CREATE TABLE `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` char(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2 country code',
  `flag_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_countries_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `visa_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL COMMENT 'Country-specific visa code if applicable',
  `description` text DEFAULT NULL,
  `processing_time` varchar(100) DEFAULT NULL COMMENT 'Typical processing time range',
  `validity_period` varchar(100) DEFAULT NULL COMMENT 'How long visa is typically valid',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_visa_unique` (`country_id`, `name`),
  KEY `idx_visa_types_country` (`country_id`),
  KEY `idx_visa_types_active` (`is_active`),
  CONSTRAINT `visa_types_country_id_fk` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `service_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default service types
INSERT INTO `service_types` (`name`, `description`) VALUES
('Complete', 'Full service offering where professional handles everything'),
('Guidance', 'Advisory service where professional provides direction and advice'),
('Do It Yourself', 'Self-service option with professional resources and support'),
('Consultation', 'One-time advisory session'),
('Review', 'Professional review of client-provided materials');

-- Consultation modes available in the system
CREATE TABLE `consultation_modes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default consultation modes
INSERT INTO `consultation_modes` (`name`, `description`) VALUES
('offline', 'Consultation over offline meeting and then follow up with email'),
('Google Meet', 'Video meeting through Google Meet'),
('Phone Call', 'Voice call over phone'),
('In-person', 'Physical in-person meeting'),
('Zoom', 'Video meeting through Zoom'),
('Custom', 'Custom consultation method');

-- Document categories for organization
CREATE TABLE `document_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert common document categories
INSERT INTO `document_categories` (`name`, `description`) VALUES
('Identity', 'Identity documents like passport, ID card'),
('Education', 'Educational certificates and transcripts'),
('Employment', 'Employment proof and work history'),
('Financial', 'Bank statements and financial documents'),
('Immigration', 'Previous visas and immigration history'),
('Medical', 'Medical certificates and health records'),
('Supporting', 'Supporting documents like cover letters, photos');

-- Document types master table
CREATE TABLE `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_document_category` (`category_id`),
  CONSTRAINT `document_types_category_id_fk` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert common document types
INSERT INTO `document_types` (`category_id`, `name`, `description`) VALUES
(1, 'Passport', 'Valid passport with at least 6 months validity'),
(1, 'National ID Card', 'Government-issued national identification card'),
(2, 'Degree Certificate', 'University or college degree certificate'),
(2, 'Transcripts', 'Academic transcripts and mark sheets'),
(3, 'Employment Contract', 'Current employment contract'),
(3, 'Experience Letter', 'Work experience letter from employer'),
(4, 'Bank Statement', 'Bank statement for the last 6 months'),
(4, 'Income Tax Returns', 'Income tax returns for the last 3 years'),
(5, 'Previous Visa', 'Copy of previous visas'),
(6, 'Medical Certificate', 'Medical fitness certificate'),
(7, 'Photographs', 'Passport-sized photographs');

-- Required documents for each visa type
CREATE TABLE `visa_required_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_type_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `additional_requirements` text DEFAULT NULL COMMENT 'Specific formatting or content requirements',
  `order_display` int(11) DEFAULT 0 COMMENT 'Display order for document checklist',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `visa_document_unique` (`visa_type_id`, `document_type_id`),
  KEY `idx_required_docs_visa` (`visa_type_id`),
  KEY `idx_required_docs_document` (`document_type_id`),
  CONSTRAINT `required_docs_visa_type_id_fk` FOREIGN KEY (`visa_type_id`) REFERENCES `visa_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `required_docs_document_type_id_fk` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Applications submitted by users (updated to use visa_service_configurations)
CREATE TABLE `visa_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'The applicant user',
  `service_config_id` int(11) NOT NULL COMMENT 'The service configuration being used',
  `status` enum('draft','submitted','in_review','document_requested','processing','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `assigned_team_member_id` int(11) DEFAULT NULL COMMENT 'Primary team member assigned to this application',
  `applicant_notes` text DEFAULT NULL COMMENT 'Notes from the applicant',
  `admin_notes` text DEFAULT NULL COMMENT 'Private notes for admin and team',
  `rejection_reason` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `idx_applications_user` (`user_id`),
  KEY `idx_applications_service_config` (`service_config_id`),
  KEY `idx_applications_status` (`status`),
  KEY `idx_applications_assigned_member` (`assigned_team_member_id`),
  CONSTRAINT `applications_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applications_service_config_id_fk` FOREIGN KEY (`service_config_id`) REFERENCES `visa_service_configurations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `applications_assigned_member_fk` FOREIGN KEY (`assigned_team_member_id`) REFERENCES `team_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- Additional team members assigned to applications
CREATE TABLE `application_team_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `team_member_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL COMMENT 'Admin who assigned this team member',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_team_member` (`application_id`, `team_member_id`),
  KEY `idx_app_assignments_team_member` (`team_member_id`),
  KEY `idx_app_assignments_admin` (`assigned_by`),
  CONSTRAINT `app_assignments_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_assignments_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_assignments_assigned_by_fk` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Document requests from admin/team members to applicants
CREATE TABLE `application_document_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL COMMENT 'Admin or team member who requested the document',
  `status` enum('requested','submitted','approved','rejected') NOT NULL DEFAULT 'requested',
  `request_notes` text DEFAULT NULL COMMENT 'Special instructions for this document',
  `rejection_reason` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_document_unique` (`application_id`, `document_type_id`),
  KEY `idx_doc_requests_application` (`application_id`),
  KEY `idx_doc_requests_document` (`document_type_id`),
  KEY `idx_doc_requests_status` (`status`),
  KEY `idx_doc_requests_requested_by` (`requested_by`),
  CONSTRAINT `doc_requests_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doc_requests_document_type_id_fk` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doc_requests_requested_by_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Uploaded documents from applicants (unchanged but with updated foreign key)
CREATE TABLE `application_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(100) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'Team member or admin who reviewed the document',
  `review_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_documents_request` (`request_id`),
  KEY `idx_documents_reviewed_by` (`reviewed_by`),
  CONSTRAINT `documents_request_id_fk` FOREIGN KEY (`request_id`) REFERENCES `application_document_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_reviewed_by_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Custom document templates created by team members
CREATE TABLE `document_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_by` int(11) NOT NULL COMMENT 'Team member or admin who created the template',
  `name` varchar(100) NOT NULL,
  `visa_type_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether template is shared with applicants',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_templates_created_by` (`created_by`),
  KEY `idx_templates_visa_type` (`visa_type_id`),
  CONSTRAINT `templates_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `templates_visa_type_id_fk` FOREIGN KEY (`visa_type_id`) REFERENCES `visa_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Application activity logs
CREATE TABLE `application_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who performed the action',
  `activity_type` enum('created','updated','status_changed','assigned','document_requested','document_submitted','document_reviewed','comment_added') NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_app_activity_application` (`application_id`),
  KEY `idx_app_activity_user` (`user_id`),
  KEY `idx_app_activity_type` (`activity_type`),
  CONSTRAINT `app_activity_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_activity_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Application comments
CREATE TABLE `application_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who added the comment',
  `comment` text NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether comment is visible only to team/admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_app_comments_application` (`application_id`),
  KEY `idx_app_comments_user` (`user_id`),
  KEY `idx_app_comments_privacy` (`is_private`),
  CONSTRAINT `app_comments_application_id_fk` FOREIGN KEY (`application_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `app_comments_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- Time slots configuration table (for recurring timeslots)
-- Team Member Service Capabilities
CREATE TABLE `team_member_service_capabilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `visa_type_id` int(11) NOT NULL,
  `service_type_id` int(11) NOT NULL,
  `consultation_mode_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_capability` (`team_member_id`, `visa_type_id`, `service_type_id`, `consultation_mode_id`),
  KEY `idx_capability_member` (`team_member_id`),
  KEY `idx_capability_visa` (`visa_type_id`),
  KEY `idx_capability_service` (`service_type_id`),
  CONSTRAINT `capability_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `capability_visa_type_id_fk` FOREIGN KEY (`visa_type_id`) REFERENCES `visa_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `capability_service_type_id_fk` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `capability_mode_id_fk` FOREIGN KEY (`consultation_mode_id`) REFERENCES `consultation_modes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Team Member Availability
CREATE TABLE `team_member_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_member_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL COMMENT 'Optional reason for unavailability',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_availability_member` (`team_member_id`),
  KEY `idx_availability_date` (`date`),
  CONSTRAINT `availability_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Service Configurations (as provided by you)
CREATE TABLE `visa_service_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_type_id` int(11) NOT NULL,
  `service_type_id` int(11) NOT NULL,
  `consultation_mode_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `visa_service_mode_unique` (`visa_type_id`, `service_type_id`, `consultation_mode_id`),
  KEY `idx_config_visa_type` (`visa_type_id`),
  KEY `idx_config_service_type` (`service_type_id`),
  KEY `idx_config_mode` (`consultation_mode_id`),
  CONSTRAINT `config_visa_type_id_fk` FOREIGN KEY (`visa_type_id`) REFERENCES `visa_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `config_service_type_id_fk` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `config_mode_id_fk` FOREIGN KEY (`consultation_mode_id`) REFERENCES `consultation_modes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Modified Bookings Table for Admin Assignment
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Applicant who made the booking',
  `team_member_id` int(11) NULL COMMENT 'Team member assigned by admin (NULL if not yet assigned)',
  `visa_type_id` int(11) NOT NULL COMMENT 'Visa type related to booking',
  `service_type_id` int(11) NOT NULL COMMENT 'Service type being booked',
  `consultation_mode_id` int(11) NOT NULL COMMENT 'Mode of consultation',
  `booking_date` date NULL COMMENT 'To be set by admin during assignment',
  `start_time` time NULL COMMENT 'To be set by admin during assignment',
  `end_time` time NULL COMMENT 'To be set by admin during assignment',
  `duration` int(11) DEFAULT 60 COMMENT 'Duration in minutes',
  `status` enum('pending_assignment','assigned','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'pending_assignment',
  `payment_status` enum('pending', 'partial', 'paid', 'refunded') NOT NULL DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancellation_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'Additional notes from applicant',
  `admin_notes` text DEFAULT NULL COMMENT 'Notes from admin',
  `team_member_notes` text DEFAULT NULL COMMENT 'Private notes for team member',
  `price` decimal(10,2) NOT NULL COMMENT 'Price at time of booking',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `idx_booking_user` (`user_id`),
  KEY `idx_booking_team_member` (`team_member_id`),
  KEY `idx_booking_visa_type` (`visa_type_id`),
  KEY `idx_booking_service_type` (`service_type_id`),
  KEY `idx_booking_mode` (`consultation_mode_id`),
  KEY `idx_booking_date` (`booking_date`),
  KEY `idx_booking_status` (`status`),
  CONSTRAINT `booking_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_visa_type_id_fk` FOREIGN KEY (`visa_type_id`) REFERENCES `visa_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_service_type_id_fk` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_mode_id_fk` FOREIGN KEY (`consultation_mode_id`) REFERENCES `consultation_modes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Booking Assignment History
CREATE TABLE `booking_assignment_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL COMMENT 'Admin who assigned the booking',
  `team_member_id` int(11) NOT NULL COMMENT 'Team member assigned to',
  `previous_team_member_id` int(11) DEFAULT NULL COMMENT 'Previous team member if reassigned',
  `assigned_date` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assignment_booking` (`booking_id`),
  KEY `idx_assignment_member` (`team_member_id`),
  CONSTRAINT `assignment_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_team_member_id_fk` FOREIGN KEY (`team_member_id`) REFERENCES `team_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_admin_id_fk` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_prev_team_member_id_fk` FOREIGN KEY (`previous_team_member_id`) REFERENCES `team_members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Booking Files
CREATE TABLE `booking_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who uploaded the file',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `is_private` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether file is visible only to team/admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_file_booking` (`booking_id`),
  KEY `idx_file_user` (`user_id`),
  CONSTRAINT `file_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `file_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Booking Payment Records
CREATE TABLE `booking_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL COMMENT 'User who recorded the payment',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payment_booking` (`booking_id`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `payment_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Booking Comments
CREATE TABLE `booking_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who added the comment',
  `comment` text NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether comment is visible only to team/admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comment_booking` (`booking_id`),
  KEY `idx_comment_user` (`user_id`),
  CONSTRAINT `comment_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Booking Activity Log
CREATE TABLE `booking_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who performed the action',
  `user_type` enum('admin','team_member','client') NOT NULL DEFAULT 'admin',
  `activity_type` enum('created','assigned','reassigned','rescheduled','updated','status_changed',
                       'payment_updated','cancelled','completed','no_show','notes_updated','files_added') NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `additional_data` JSON DEFAULT NULL COMMENT 'Any additional contextual data for the activity',
  PRIMARY KEY (`id`),
  KEY `idx_activity_booking` (`booking_id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_activity_date` (`created_at`),
  CONSTRAINT `activity_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Conversations/threads table
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `type` enum('direct','group','system') NOT NULL COMMENT 'Direct is between two users, group is for multiple users, system is for system notifications',
  `created_by` int(11) NOT NULL COMMENT 'User who initiated the conversation',
  `related_to_type` enum('application','booking','task','general') DEFAULT NULL COMMENT 'What the conversation is related to',
  `related_to_id` int(11) DEFAULT NULL COMMENT 'ID of the related item',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversations_created_by` (`created_by`),
  KEY `idx_conversations_related` (`related_to_type`, `related_to_id`),
  KEY `idx_conversations_type` (`type`),
  CONSTRAINT `conversations_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Conversation participants table
CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','participant','observer') NOT NULL DEFAULT 'participant' COMMENT 'Admin can add/remove participants, observer can only view',
  `is_muted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether user has muted notifications for this conversation',
  `last_read_message_id` int(11) DEFAULT NULL COMMENT 'ID of the last message read by this participant',
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `left_at` datetime DEFAULT NULL COMMENT 'When user left the conversation, NULL if still active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_user_unique` (`conversation_id`, `user_id`),
  KEY `idx_participants_user` (`user_id`),
  KEY `idx_participants_active` (`left_at`),
  CONSTRAINT `participants_conversation_id_fk` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participants_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Messages table
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL COMMENT 'NULL for system messages',
  `message_type` enum('text','image','document','system_notification') NOT NULL DEFAULT 'text',
  `content` text NOT NULL,
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `parent_message_id` int(11) DEFAULT NULL COMMENT 'For replies/threads',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_messages_conversation` (`conversation_id`),
  KEY `idx_messages_sender` (`sender_id`),
  KEY `idx_messages_parent` (`parent_message_id`),
  KEY `idx_messages_type` (`message_type`),
  CONSTRAINT `messages_conversation_id_fk` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_parent_message_id_fk` FOREIGN KEY (`parent_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Message attachments table
CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'Size in bytes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attachments_message` (`message_id`),
  CONSTRAINT `attachments_message_id_fk` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Message read status table
CREATE TABLE `message_read_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_user_unique` (`message_id`, `user_id`),
  KEY `idx_read_status_user` (`user_id`),
  KEY `idx_read_status_read` (`is_read`),
  CONSTRAINT `read_status_message_id_fk` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `read_status_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- System notifications table
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User who receives the notification',
  `related_user_id` int(11) DEFAULT NULL COMMENT 'User who triggered the notification, if applicable',
  `notification_type` enum(
    'application_status_change',
    'document_requested',
    'document_submitted',
    'document_approved',
    'document_rejected',
    'booking_created',
    'booking_confirmed',
    'booking_rescheduled',
    'booking_cancelled',
    'task_assigned',
    'task_updated',
    'task_completed',
    'message_received',
    'comment_added',
    'team_member_assigned',
    'system_alert'
  ) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `related_to_type` enum('application','booking','task','message','document','user','system') NOT NULL,
  `related_to_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `is_actionable` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether notification requires user action',
  `action_url` varchar(255) DEFAULT NULL COMMENT 'URL to direct user for action',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL COMMENT 'When notification should expire/be removed',
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_type` (`notification_type`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_related` (`related_to_type`, `related_to_id`),
  KEY `idx_notifications_related_user` (`related_user_id`),
  CONSTRAINT `notifications_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_related_user_id_fk` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Notification preferences table
CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_type` enum(
    'application_status_change',
    'document_requested',
    'document_submitted',
    'document_approved',
    'document_rejected',
    'booking_created',
    'booking_confirmed',
    'booking_rescheduled',
    'booking_cancelled',
    'task_assigned',
    'task_updated',
    'task_completed',
    'message_received',
    'comment_added',
    'team_member_assigned',
    'system_alert',
    'all' -- Special type to control all notifications
  ) NOT NULL,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `in_app_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_notification_type_unique` (`user_id`, `notification_type`),
  CONSTRAINT `preferences_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default notification preferences for common user types
INSERT INTO `notification_preferences` (`user_id`, `notification_type`, `email_enabled`, `push_enabled`, `in_app_enabled`)
SELECT `id`, 'all', 1, 1, 1 FROM `users` WHERE `user_type` IN ('admin', 'member');

-- Function to create a conversation between two users
DELIMITER $$
CREATE FUNCTION create_direct_conversation(user1_id INT, user2_id INT, title VARCHAR(255))
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE new_conversation_id INT;
    
    -- Check if conversation already exists between these users
    SELECT c.id INTO new_conversation_id
    FROM conversations c
    JOIN conversation_participants p1 ON c.id = p1.conversation_id
    JOIN conversation_participants p2 ON c.id = p2.conversation_id
    WHERE c.type = 'direct'
    AND p1.user_id = user1_id
    AND p2.user_id = user2_id
    AND p1.left_at IS NULL
    AND p2.left_at IS NULL
    LIMIT 1;
    
    -- If conversation doesn't exist, create a new one
    IF new_conversation_id IS NULL THEN
        INSERT INTO conversations (title, type, created_by)
        VALUES (title, 'direct', user1_id);
        
        SET new_conversation_id = LAST_INSERT_ID();
        
        -- Add both users as participants
        INSERT INTO conversation_participants (conversation_id, user_id, role)
        VALUES (new_conversation_id, user1_id, 'admin');
        
        INSERT INTO conversation_participants (conversation_id, user_id, role)
        VALUES (new_conversation_id, user2_id, 'participant');
    END IF;
    
    RETURN new_conversation_id;
END$$
DELIMITER ;

-- Trigger to automatically create message read status records when a new message is sent
DELIMITER $$
CREATE TRIGGER after_message_insert
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    -- Create message read status for all participants except sender
    INSERT INTO message_read_status (message_id, user_id, is_read)
    SELECT NEW.id, cp.user_id, 0
    FROM conversation_participants cp
    WHERE cp.conversation_id = NEW.conversation_id
    AND cp.left_at IS NULL
    AND (cp.user_id != NEW.sender_id OR NEW.sender_id IS NULL);
    
    -- Mark as read for the sender
    IF NEW.sender_id IS NOT NULL THEN
        INSERT INTO message_read_status (message_id, user_id, is_read, read_at)
        VALUES (NEW.id, NEW.sender_id, 1, NOW());
        
        -- Update last read message for the sender
        UPDATE conversation_participants
        SET last_read_message_id = NEW.id
        WHERE conversation_id = NEW.conversation_id
        AND user_id = NEW.sender_id;
    END IF;
    
    -- Create notifications for all participants except sender
    IF NEW.message_type != 'system_notification' THEN
        INSERT INTO notifications (
            user_id, 
            related_user_id,
            notification_type,
            title,
            content,
            related_to_type,
            related_to_id,
            is_actionable,
            action_url
        )
        SELECT 
            cp.user_id,
            NEW.sender_id,
            'message_received',
            CONCAT('New message from ', IFNULL((SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = NEW.sender_id), 'System')),
            SUBSTRING(NEW.content, 1, 100),
            'message',
            NEW.id,
            1,
            CONCAT('/messages/', NEW.conversation_id)
        FROM conversation_participants cp
        WHERE cp.conversation_id = NEW.conversation_id
        AND cp.left_at IS NULL
        AND cp.user_id != NEW.sender_id
        AND cp.is_muted = 0;
    END IF;
END$$
DELIMITER ;

-- Procedure to create system notifications for various events
DELIMITER $$
CREATE PROCEDURE create_system_notification(
    IN p_user_id INT,
    IN p_related_user_id INT,
    IN p_notification_type VARCHAR(50),
    IN p_title VARCHAR(255),
    IN p_content TEXT,
    IN p_related_to_type VARCHAR(50),
    IN p_related_to_id INT,
    IN p_is_actionable TINYINT,
    IN p_action_url VARCHAR(255)
)
BEGIN
    -- Check if the user has this notification type enabled
    IF EXISTS (
        SELECT 1 
        FROM notification_preferences 
        WHERE user_id = p_user_id 
        AND (notification_type = p_notification_type OR notification_type = 'all')
        AND in_app_enabled = 1
    ) THEN
        INSERT INTO notifications (
            user_id,
            related_user_id,
            notification_type,
            title,
            content,
            related_to_type,
            related_to_id,
            is_actionable,
            action_url
        ) VALUES (
            p_user_id,
            p_related_user_id,
            p_notification_type,
            p_title,
            p_content,
            p_related_to_type,
            p_related_to_id,
            p_is_actionable,
            p_action_url
        );
    END IF;
END$$
DELIMITER ;

-- Trigger to create notifications when application status changes
DELIMITER $$
CREATE TRIGGER after_application_status_update
AFTER UPDATE ON visa_applications
FOR EACH ROW
BEGIN
    DECLARE notification_title VARCHAR(255);
    DECLARE notification_content TEXT;
    
    -- Only process if status changed
    IF NEW.status != OLD.status THEN
        -- Create notification for applicant
        SET notification_title = CONCAT('Application status changed to ', NEW.status);
        SET notification_content = CONCAT('Your visa application (Ref: ', NEW.reference_number, ') status has been updated to ', NEW.status);
        
        CALL create_system_notification(
            NEW.user_id,
            NULL,
            'application_status_change',
            notification_title,
            notification_content,
            'application',
            NEW.id,
            1,
            CONCAT('/applications/', NEW.id)
        );
        
        -- Create notification for assigned team member if exists
        IF NEW.assigned_team_member_id IS NOT NULL THEN
            SET notification_title = CONCAT('Application status changed to ', NEW.status);
            SET notification_content = CONCAT('Application (Ref: ', NEW.reference_number, ') has been updated to status ', NEW.status);
            
            CALL create_system_notification(
                (SELECT user_id FROM team_members WHERE id = NEW.assigned_team_member_id),
                NULL,
                'application_status_change',
                notification_title,
                notification_content,
                'application',
                NEW.id,
                1,
                CONCAT('/admin/applications/', NEW.id)
            );
        END IF;
    END IF;
END$$
DELIMITER ;

-- Trigger to create notifications when documents are requested
DELIMITER $$
CREATE TRIGGER after_document_request_insert
AFTER INSERT ON application_document_requests
FOR EACH ROW
BEGIN
    DECLARE app_ref VARCHAR(50);
    DECLARE doc_name VARCHAR(100);
    DECLARE applicant_id INT;
    
    -- Get the application reference and applicant ID
    SELECT a.reference_number, a.user_id INTO app_ref, applicant_id
    FROM visa_applications a
    WHERE a.id = NEW.application_id;
    
    -- Get document name
    SELECT name INTO doc_name
    FROM document_types
    WHERE id = NEW.document_type_id;
    
    -- Create notification for applicant
    CALL create_system_notification(
        applicant_id,
        NEW.requested_by,
        'document_requested',
        CONCAT('Document requested: ', doc_name),
        CONCAT('Please upload your ', doc_name, ' for application (Ref: ', app_ref, ')'),
        'document',
        NEW.id,
        1,
        CONCAT('/applications/', NEW.application_id, '/documents')
    );
END$$
DELIMITER ;

-- Trigger to create notifications when documents are submitted
DELIMITER $$
CREATE TRIGGER after_document_upload
AFTER INSERT ON application_documents
FOR EACH ROW
BEGIN
    DECLARE request_id INT;
    DECLARE app_id INT;
    DECLARE app_ref VARCHAR(50);
    DECLARE doc_name VARCHAR(100);
    DECLARE uploader_id INT;
    DECLARE reviewer_id INT;
    
    -- Get document request info
    SELECT r.id, r.application_id, r.requested_by, r.document_type_id
    INTO request_id, app_id, reviewer_id, doc_name
    FROM application_document_requests r
    WHERE r.id = NEW.request_id;
    
    -- Get application reference
    SELECT a.reference_number INTO app_ref
    FROM visa_applications a
    WHERE a.id = app_id;
    
    -- Get document name
    SELECT name INTO doc_name
    FROM document_types
    WHERE id = doc_name;
    
    -- Create notification for document reviewer
    CALL create_system_notification(
        reviewer_id,
        NULL,
        'document_submitted',
        CONCAT('Document submitted: ', doc_name),
        CONCAT('A new ', doc_name, ' has been uploaded for application (Ref: ', app_ref, ')'),
        'document',
        NEW.id,
        1,
        CONCAT('/admin/applications/', app_id, '/documents')
    );
END$$
DELIMITER ;

-- Trigger to create notifications when bookings are created
DELIMITER $$
CREATE TRIGGER after_booking_insert
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    DECLARE team_member_user_id INT;
    
    -- Get team member's user ID
    SELECT user_id INTO team_member_user_id
    FROM team_members
    WHERE id = NEW.team_member_id;
    
    -- Create notification for team member
    CALL create_system_notification(
        team_member_user_id,
        NEW.user_id,
        'booking_created',
        'New booking scheduled',
        CONCAT('A new consultation has been scheduled on ', DATE_FORMAT(NEW.booking_date, '%M %d, %Y'), ' at ', TIME_FORMAT(NEW.start_time, '%h:%i %p')),
        'booking',
        NEW.id,
        1,
        CONCAT('/admin/bookings/', NEW.id)
    );
    
    -- Create notification for applicant
    CALL create_system_notification(
        NEW.user_id,
        NULL,
        'booking_created',
        'Booking confirmation',
        CONCAT('Your consultation has been scheduled on ', DATE_FORMAT(NEW.booking_date, '%M %d, %Y'), ' at ', TIME_FORMAT(NEW.start_time, '%h:%i %p')),
        'booking',
        NEW.id,
        1,
        CONCAT('/bookings/', NEW.id)
    );
END$$
DELIMITER ;

-- Trigger to create notifications when tasks are assigned
DELIMITER $$
CREATE TRIGGER after_task_assignment
AFTER INSERT ON task_assignments
FOR EACH ROW
BEGIN
    DECLARE task_name VARCHAR(255);
    DECLARE assignee_id INT;
    
    -- Get task name
    SELECT name INTO task_name
    FROM tasks
    WHERE id = NEW.task_id;
    
    -- Get user ID of team member
    SELECT user_id INTO assignee_id
    FROM team_members
    WHERE id = NEW.team_member_id;
    
    -- Create notification for assignee
    CALL create_system_notification(
        assignee_id,
        NULL,
        'task_assigned',
        CONCAT('New task assigned: ', task_name),
        CONCAT('You have been assigned a new task: ', task_name),
        'task',
        NEW.task_id,
        1,
        CONCAT('/admin/tasks/', NEW.task_id)
    );
END$$
DELIMITER ;

-- Trigger to mark all messages as read when a user leaves a conversation
DELIMITER $$
CREATE TRIGGER after_participant_leave
AFTER UPDATE ON conversation_participants
FOR EACH ROW
BEGIN
    IF NEW.left_at IS NOT NULL AND OLD.left_at IS NULL THEN
        -- Mark all unread messages as read when leaving
        UPDATE message_read_status mrs
        JOIN messages m ON mrs.message_id = m.id
        SET mrs.is_read = 1, mrs.read_at = NOW()
        WHERE m.conversation_id = NEW.conversation_id
        AND mrs.user_id = NEW.user_id
        AND mrs.is_read = 0;
    END IF;
END$$
DELIMITER ;
-- Trigger to prevent double-booking for team members
DELIMITER $$
CREATE TRIGGER check_team_member_double_booking BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    -- Check for overlapping bookings
    IF EXISTS (
        SELECT 1 
        FROM bookings b
        WHERE b.team_member_id = NEW.team_member_id
          AND b.booking_date = NEW.booking_date
          AND b.status NOT IN ('cancelled', 'no_show')
          AND (
              (NEW.start_time BETWEEN b.start_time AND b.end_time)
              OR (NEW.end_time BETWEEN b.start_time AND b.end_time)
              OR (NEW.start_time <= b.start_time AND NEW.end_time >= b.end_time)
          )
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'There is already a booking at this time for this team member';
    END IF;
END$$
DELIMITER ;

-- Trigger to check team member availability based on configured timeslots
DELIMITER $$
CREATE TRIGGER check_team_member_availability BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    DECLARE day_num TINYINT;
    DECLARE is_available BOOLEAN DEFAULT FALSE;
    
    -- Get day of week for the booking date (0=Sunday, 1=Monday, etc.)
    SET day_num = DAYOFWEEK(NEW.booking_date) - 1;
    
    -- First check if there's a date-specific override making the team member unavailable
    IF EXISTS (
        SELECT 1 
        FROM team_member_availability_overrides
        WHERE team_member_id = NEW.team_member_id
          AND date = NEW.booking_date
          AND is_available = 0
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'The team member is not available on this date';
    END IF;
    
    -- Then check if there's a specific timeslot override for this date
    IF EXISTS (
        SELECT 1 
        FROM team_member_timeslot_overrides
        WHERE team_member_id = NEW.team_member_id
          AND date = NEW.booking_date
          AND is_available = 1
          AND NEW.start_time >= start_time
          AND NEW.end_time <= end_time
    ) THEN
        SET is_available = TRUE;
    -- Otherwise check regular timeslot configuration
    ELSEIF EXISTS (
        SELECT 1 
        FROM team_member_timeslots
        WHERE team_member_id = NEW.team_member_id
          AND day_of_week = day_num
          AND is_available = 1
          AND NEW.start_time >= start_time
          AND NEW.end_time <= end_time
    ) THEN
        SET is_available = TRUE;
    END IF;
    
    -- If not available in any configuration, raise error
    IF is_available = FALSE THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'The team member is not available at this time';
    END IF;
END$$
DELIMITER ;

CREATE TABLE `visa_service_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visa_type_id` int(11) NOT NULL,
  `service_type_id` int(11) NOT NULL,
  `consultation_mode_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `visa_service_mode_unique` (`visa_type_id`, `service_type_id`, `consultation_mode_id`),
  KEY `idx_config_visa_type` (`visa_type_id`),
  KEY `idx_config_service_type` (`service_type_id`),
  KEY `idx_config_mode` (`consultation_mode_id`),
  CONSTRAINT `config_visa_type_id_fk` FOREIGN KEY (`visa_type_id`) REFERENCES `visa_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `config_service_type_id_fk` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `config_mode_id_fk` FOREIGN KEY (`consultation_mode_id`) REFERENCES `consultation_modes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
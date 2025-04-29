-- Team Members Table (using existing structure)
CREATE TABLE `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
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
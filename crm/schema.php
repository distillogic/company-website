<?php
declare(strict_types=1);

return [
    "CREATE TABLE IF NOT EXISTS users (
      id CHAR(36) PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      email VARCHAR(320) NOT NULL,
      role ENUM('employee','manager','technical','admin') NOT NULL DEFAULT 'employee',
      active TINYINT(1) NOT NULL DEFAULT 1,
      password_hash VARCHAR(255) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY users_email_unique (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS companies (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      name_key VARCHAR(255) NOT NULL,
      email VARCHAR(255) NULL,
      phone VARCHAR(40) NULL,
      industry VARCHAR(120) NULL,
      website VARCHAR(255) NULL,
      address VARCHAR(255) NULL,
      city VARCHAR(120) NULL,
      notes TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL,
      UNIQUE KEY companies_name_key_unique (name_key),
      KEY companies_deleted_at_idx (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS communications (
      id CHAR(36) PRIMARY KEY,
      company_id BIGINT UNSIGNED NOT NULL,
      user_id CHAR(36) NULL,
      assigned_user_id CHAR(36) NULL,
      source ENUM('website','telephone') NOT NULL DEFAULT 'telephone',
      status ENUM('new','contacted','qualified','proposal','won','lost','archived') NOT NULL DEFAULT 'new',
      contact_name VARCHAR(150) NULL,
      contact_role VARCHAR(120) NULL,
      outcome ENUM('no_answer','callback','interested','not_interested') NULL,
      interest_level TINYINT UNSIGNED NULL,
      notes TEXT NULL,
      next_action VARCHAR(255) NULL,
      next_action_at DATETIME NULL,
      archived_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      deleted_at DATETIME NULL,
      CONSTRAINT communications_company_fk FOREIGN KEY (company_id) REFERENCES companies(id),
      CONSTRAINT communications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
      CONSTRAINT communications_assigned_fk FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
      KEY communications_source_status_idx (source, status),
      KEY communications_next_action_idx (next_action_at),
      KEY communications_company_idx (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS communication_project_briefs (
      communication_id CHAR(36) PRIMARY KEY,
      country VARCHAR(100) NULL,
      service VARCHAR(80) NULL,
      project_title VARCHAR(180) NULL,
      requirement TEXT NULL,
      technologies TEXT NULL,
      project_stage VARCHAR(80) NULL,
      engagement VARCHAR(80) NULL,
      timeline VARCHAR(80) NULL,
      request_nda TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT project_brief_communication_fk FOREIGN KEY (communication_id) REFERENCES communications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS website_enquiries (
      id CHAR(36) PRIMARY KEY,
      reference VARCHAR(20) NOT NULL,
      communication_id CHAR(36) NOT NULL,
      business_email VARCHAR(255) NOT NULL,
      language ENUM('en','el') NOT NULL DEFAULT 'en',
      source_page VARCHAR(300) NULL,
      submitted_at DATETIME NOT NULL,
      request_ip_hash CHAR(64) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY website_reference_unique (reference),
      UNIQUE KEY website_communication_unique (communication_id),
      CONSTRAINT website_enquiry_communication_fk FOREIGN KEY (communication_id) REFERENCES communications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS website_enquiry_documents (
      id CHAR(36) PRIMARY KEY,
      website_enquiry_id CHAR(36) NOT NULL,
      file_name VARCHAR(255) NOT NULL,
      mime_type VARCHAR(150) NOT NULL,
      size_bytes BIGINT UNSIGNED NOT NULL,
      content LONGBLOB NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT website_document_enquiry_fk FOREIGN KEY (website_enquiry_id) REFERENCES website_enquiries(id) ON DELETE CASCADE,
      KEY website_document_enquiry_idx (website_enquiry_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS notifications (
      id CHAR(36) PRIMARY KEY,
      user_id CHAR(36) NOT NULL,
      title VARCHAR(160) NOT NULL,
      body TEXT NOT NULL,
      action_url VARCHAR(255) NULL,
      read_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      KEY notifications_user_created_idx (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS crm_settings (
      setting_key VARCHAR(100) PRIMARY KEY,
      setting_value TEXT NOT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS request_limits (
      limit_key CHAR(64) PRIMARY KEY,
      attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      window_started DATETIME NOT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY request_limits_window_idx (window_started)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

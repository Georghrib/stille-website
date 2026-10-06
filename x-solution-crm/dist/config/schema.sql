-- X-Solution CRM – Datenbankschema (MySQL 5.7+/8, MariaDB 10.3+)
-- Beträge immer als Cent-Integer (BIGINT).

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status ENUM('interessent','kunde','inaktiv') NOT NULL DEFAULT 'interessent',
  name VARCHAR(190) NOT NULL,
  contact_person VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  street VARCHAR(190) NULL,
  zip VARCHAR(20) NULL,
  city VARCHAR(120) NULL,
  country CHAR(2) NOT NULL DEFAULT 'AT',
  vat_id VARCHAR(40) NULL,
  easybill_customer_id BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_easybill (easybill_customer_id),
  KEY idx_customers_email (email),
  KEY idx_customers_status (status),
  KEY idx_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS easybill_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  easybill_id BIGINT NOT NULL,
  easybill_customer_id BIGINT NULL,
  customer_id INT UNSIGNED NULL,
  contract_id INT UNSIGNED NULL,
  type ENUM('angebot','rechnung','gutschrift') NOT NULL,
  number VARCHAR(60) NULL,
  title VARCHAR(255) NULL,
  customer_name VARCHAR(190) NULL,
  customer_email VARCHAR(190) NULL,
  amount_net_cents BIGINT NOT NULL DEFAULT 0,
  amount_gross_cents BIGINT NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  document_date DATE NULL,
  due_date DATE NULL,
  paid_at DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'offen',
  is_draft TINYINT(1) NOT NULL DEFAULT 0,
  ref_easybill_id BIGINT NULL,
  inbox_state ENUM('neu','zugeordnet','interessent','vertrag','abgelegt') NOT NULL DEFAULT 'neu',
  suggestion VARCHAR(30) NULL,
  suggestion_ref_id INT UNSIGNED NULL,
  pdf_path VARCHAR(255) NULL,
  raw_payload JSON NULL,
  easybill_edited_at DATETIME NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_easybill_id (easybill_id),
  KEY idx_doc_customer (customer_id),
  KEY idx_doc_contract (contract_id),
  KEY idx_doc_inbox (inbox_state),
  KEY idx_doc_type_date (type, document_date),
  KEY idx_doc_ref (ref_easybill_id),
  CONSTRAINT fk_doc_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  total_value_cents BIGINT NOT NULL DEFAULT 0,
  billing_interval ENUM('einmalig','monatlich','quartal','jaehrlich') NOT NULL DEFAULT 'monatlich',
  monthly_value_cents BIGINT NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  notice_days INT UNSIGNED NOT NULL DEFAULT 90,
  status ENUM('entwurf','aktiv','gekuendigt','beendet') NOT NULL DEFAULT 'aktiv',
  cancelled_at DATE NULL,
  source_document_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contracts_customer (customer_id),
  KEY idx_contracts_status_end (status, end_date),
  CONSTRAINT fk_contract_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_contract_document FOREIGN KEY (source_document_id) REFERENCES easybill_documents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  contract_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notes_customer (customer_id),
  CONSTRAINT fk_note_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_note_contract FOREIGN KEY (contract_id) REFERENCES contracts (id) ON DELETE SET NULL,
  CONSTRAINT fk_note_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind ENUM('aufgabe','termin') NOT NULL DEFAULT 'aufgabe',
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  due_at DATETIME NULL,
  customer_id INT UNSIGNED NULL,
  contract_id INT UNSIGNED NULL,
  assigned_to INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  done_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_appt_due (done_at, due_at),
  CONSTRAINT fk_appt_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
  CONSTRAINT fk_appt_contract FOREIGN KEY (contract_id) REFERENCES contracts (id) ON DELETE SET NULL,
  CONSTRAINT fk_appt_user FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel ENUM('api','webhook','cron','system') NOT NULL,
  method VARCHAR(10) NULL,
  endpoint VARCHAR(255) NULL,
  http_status SMALLINT NULL,
  status ENUM('ok','fehler','empfangen','verarbeitet','ignoriert') NOT NULL DEFAULT 'ok',
  message VARCHAR(500) NULL,
  payload MEDIUMTEXT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_sync_channel_status (channel, status),
  KEY idx_sync_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

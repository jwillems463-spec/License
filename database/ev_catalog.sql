-- =====================================================================
--  EV Catalog — MySQL / MariaDB schema + seed data
--  Compatible with MySQL 5.7+ / 8.x and MariaDB 10.3+
--  Import via cPanel > phpMyAdmin > (select database) > Import
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Users & roles
--   admin  : full control (EVs, brands, users, settings, audit log, deletes)
--   editor : create / edit EVs and brands, upload images (no deletes,
--            no user or settings management)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                  VARCHAR(100) NOT NULL,
  email                 VARCHAR(190) NOT NULL,
  password_hash         VARCHAR(255) NOT NULL,
  role                  ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  is_active             TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password  TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at         DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Brands (manufacturers)
-- ---------------------------------------------------------------------
CREATE TABLE brands (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(120) NOT NULL,
  country     VARCHAR(80)  NULL,
  logo_url    VARCHAR(500) NULL,
  website     VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_brands_name (name),
  UNIQUE KEY uq_brands_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Vehicles (EVs)
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand_id            INT UNSIGNED NOT NULL,
  model               VARCHAR(120) NOT NULL,
  variant             VARCHAR(120) NULL,
  slug                VARCHAR(190) NOT NULL,
  model_year          SMALLINT UNSIGNED NOT NULL,
  body_type           ENUM('sedan','suv','hatchback','crossover','pickup','van','coupe','wagon') NOT NULL,
  drivetrain          ENUM('FWD','RWD','AWD') NOT NULL,
  price_usd           DECIMAL(10,2) NULL,
  battery_kwh         DECIMAL(6,1)  NULL,
  range_km            SMALLINT UNSIGNED NULL,
  efficiency_wh_km    SMALLINT UNSIGNED NULL,
  acceleration_0_100  DECIMAL(4,1)  NULL,
  top_speed_kmh       SMALLINT UNSIGNED NULL,
  power_kw            SMALLINT UNSIGNED NULL,
  torque_nm           SMALLINT UNSIGNED NULL,
  seats               TINYINT UNSIGNED NULL,
  charging_ac_kw      DECIMAL(5,1)  NULL,
  charging_dc_kw      SMALLINT UNSIGNED NULL,
  charge_10_80_min    SMALLINT UNSIGNED NULL,
  cargo_l             SMALLINT UNSIGNED NULL,
  weight_kg           SMALLINT UNSIGNED NULL,
  image_url           VARCHAR(500) NULL,
  description         TEXT NULL,
  status              ENUM('draft','published') NOT NULL DEFAULT 'draft',
  is_featured         TINYINT(1) NOT NULL DEFAULT 0,
  created_by          INT UNSIGNED NULL,
  updated_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vehicles_slug (slug),
  KEY idx_vehicles_brand (brand_id),
  KEY idx_vehicles_status (status),
  KEY idx_vehicles_price (price_usd),
  KEY idx_vehicles_range (range_km),
  KEY idx_vehicles_body (body_type),
  CONSTRAINT fk_vehicles_brand   FOREIGN KEY (brand_id)   REFERENCES brands(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_vehicles_created FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL,
  CONSTRAINT fk_vehicles_updated FOREIGN KEY (updated_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Site settings (key/value, editable from the admin dashboard)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  setting_key    VARCHAR(64)  NOT NULL,
  setting_value  TEXT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Login throttling
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address    VARCHAR(45)  NOT NULL,
  email         VARCHAR(190) NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempts_lookup (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Audit log (who changed what)
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(32)  NOT NULL,
  entity      VARCHAR(32)  NOT NULL,
  entity_id   INT UNSIGNED NULL,
  summary     VARCHAR(255) NULL,
  ip_address  VARCHAR(45)  NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  SEED DATA
-- =====================================================================

-- Default administrator
--   email:    admin@example.com
--   password: ChangeMe123!
-- You are forced to change this password at first login.
INSERT INTO users (name, email, password_hash, role, is_active, must_change_password) VALUES
('Site Administrator', 'admin@example.com', '$2y$12$ZsCmZuwwUcLTCqhe3H70FumaiQz6M9sk1O4PgLYqILkt31733.wGu', 'admin', 1, 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name',        'EV Catalog'),
('site_tagline',     'Find, explore and compare electric vehicles'),
('contact_email',    'hello@example.com'),
('currency_symbol',  '$'),
('hero_title',       'Find your next electric vehicle'),
('hero_subtitle',    'Browse specs, filter by range and price, and compare up to 4 EVs side by side.'),
('footer_text',      '© EV Catalog. Specifications are indicative and may vary by market.'),
('max_compare',      '4'),
('items_per_page',   '12');

INSERT INTO brands (id, name, slug, country, website) VALUES
(1, 'Tesla',      'tesla',      'United States', 'https://www.tesla.com'),
(2, 'Hyundai',    'hyundai',    'South Korea',   'https://www.hyundai.com'),
(3, 'Kia',        'kia',        'South Korea',   'https://www.kia.com'),
(4, 'BMW',        'bmw',        'Germany',       'https://www.bmw.com'),
(5, 'Volkswagen', 'volkswagen', 'Germany',       'https://www.vw.com'),
(6, 'BYD',        'byd',        'China',         'https://www.byd.com'),
(7, 'Ford',       'ford',       'United States', 'https://www.ford.com'),
(8, 'Polestar',   'polestar',   'Sweden',        'https://www.polestar.com'),
(9, 'Nissan',     'nissan',     'Japan',         'https://www.nissan-global.com'),
(10,'Rivian',     'rivian',     'United States', 'https://rivian.com');

-- Sample vehicles. Figures are indicative sample data — edit them in the admin dashboard.
INSERT INTO vehicles
(brand_id, model, variant, slug, model_year, body_type, drivetrain, price_usd, battery_kwh, range_km, efficiency_wh_km,
 acceleration_0_100, top_speed_kmh, power_kw, torque_nm, seats, charging_ac_kw, charging_dc_kw, charge_10_80_min,
 cargo_l, weight_kg, image_url, description, status, is_featured) VALUES
(1,'Model 3','Long Range AWD','tesla-model-3-long-range-awd-2025',2025,'sedan','AWD',47490,78.1,629,142,4.4,201,366,493,5,11.0,250,27,594,1828,NULL,
 'A best-selling electric sports sedan with a long range, fast Supercharger access and a minimalist cabin.','published',1),
(1,'Model Y','Long Range AWD','tesla-model-y-long-range-awd-2025',2025,'suv','AWD',50490,78.1,533,155,5.0,201,378,493,5,11.0,250,27,854,1979,NULL,
 'Practical mid-size electric SUV with generous cargo space and a strong charging network.','published',1),
(2,'IONIQ 5','Long Range AWD','hyundai-ioniq-5-long-range-awd-2025',2025,'crossover','AWD',52600,84.0,507,178,5.1,185,239,605,5,10.9,235,18,527,2100,NULL,
 'Retro-futuristic crossover on an 800-volt platform for very fast charging and vehicle-to-load.','published',1),
(2,'IONIQ 6','Long Range RWD','hyundai-ioniq-6-long-range-rwd-2025',2025,'sedan','RWD',45500,77.4,614,140,7.4,185,168,350,5,10.9,235,18,401,1910,NULL,
 'Streamlined electric sedan with one of the lowest drag coefficients on the market.','published',0),
(3,'EV6','GT-Line AWD','kia-ev6-gt-line-awd-2025',2025,'crossover','AWD',57900,84.0,494,170,5.1,188,239,605,5,10.9,235,18,480,2090,NULL,
 'Sporty crossover sharing the E-GMP 800V platform, with sharp handling and fast charging.','published',0),
(3,'EV9','Long Range AWD','kia-ev9-long-range-awd-2025',2025,'suv','AWD',63900,99.8,489,215,6.0,200,283,700,7,10.9,210,24,333,2585,NULL,
 'Three-row, seven-seat electric SUV aimed at families that need space and range.','published',1),
(4,'i4','eDrive40','bmw-i4-edrive40-2025',2025,'sedan','RWD',57900,81.1,590,165,5.7,190,250,430,5,11.0,205,31,470,2125,NULL,
 'Four-door gran coupe that brings BMW driving dynamics to an electric drivetrain.','published',0),
(4,'iX','xDrive50','bmw-ix-xdrive50-2025',2025,'suv','AWD',87250,105.2,633,195,4.6,200,385,765,5,11.0,195,35,500,2510,NULL,
 'Luxury electric SUV with a spacious lounge-style interior and long range.','published',0),
(5,'ID.4','Pro S','volkswagen-id-4-pro-s-2025',2025,'suv','RWD',44875,77.0,520,170,6.7,160,210,545,5,11.0,175,28,543,2124,NULL,
 'Comfortable, family-friendly compact electric SUV.','published',0),
(6,'Seal','Excellence AWD','byd-seal-excellence-awd-2025',2025,'sedan','AWD',44990,82.5,520,170,3.8,180,390,670,5,11.0,150,26,400,2185,NULL,
 'Quick electric sedan with BYD Blade battery technology and cell-to-body construction.','published',0),
(6,'Dolphin','Comfort','byd-dolphin-comfort-2025',2025,'hatchback','FWD',32990,60.4,427,150,7.0,160,150,310,5,11.0,88,29,345,1658,NULL,
 'Affordable, well-equipped electric hatchback for city and commuter driving.','published',0),
(7,'Mustang Mach-E','Premium AWD Extended','ford-mustang-mach-e-premium-awd-2025',2025,'crossover','AWD',53995,91.0,500,190,5.1,180,290,580,5,11.0,150,36,402,2232,NULL,
 'Electric crossover with Mustang-inspired styling and an engaging drive.','published',0),
(7,'F-150 Lightning','Lariat Extended','ford-f-150-lightning-lariat-2025',2025,'pickup','AWD',77495,131.0,515,250,4.5,180,433,1050,5,19.2,155,41,400,2948,NULL,
 'Full-size electric pickup with a front trunk and home backup power capability.','published',0),
(8,'Polestar 2','Long Range Dual Motor','polestar-2-long-range-dual-motor-2025',2025,'hatchback','AWD',55300,82.0,568,165,4.5,205,310,740,5,11.0,205,28,405,2095,NULL,
 'Scandinavian-designed performance fastback with Google built-in.','published',0),
(9,'Ariya','Evolve+ e-4ORCE','nissan-ariya-evolve-plus-e-4orce-2025',2025,'crossover','AWD',53690,87.0,440,195,5.7,200,290,600,5,7.2,130,35,468,2255,NULL,
 'Calm, premium-feeling crossover with twin-motor all-wheel control.','published',0),
(10,'R1S','Dual Large','rivian-r1s-dual-large-2025',2025,'suv','AWD',83900,109.0,563,210,4.5,180,467,829,7,11.5,220,31,776,3060,NULL,
 'Adventure-ready seven-seat electric SUV with serious off-road capability.','draft',0);

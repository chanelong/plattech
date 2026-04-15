-- ============================================
-- dormMNL - Full Setup (Database + Tables + Seed Data)
-- Includes: Schema, Migration Tables, 100-Row Seed Data
-- All passwords = "password"
-- ============================================

-- ============================================
-- CREATE DATABASE
-- ============================================
CREATE DATABASE IF NOT EXISTS dormMNL
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dormMNL;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
  id            INT           AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password      VARCHAR(255)  NOT NULL,
  role          ENUM('admin','renter','rentee') DEFAULT 'rentee',
  status        ENUM('active','banned')         DEFAULT 'active',
  reset_code    VARCHAR(10)   DEFAULT NULL,
  reset_expires DATETIME      DEFAULT NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- DORMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS dorms (
  id                INT           AUTO_INCREMENT PRIMARY KEY,
  owner_id          INT           NOT NULL,
  name              VARCHAR(150)  NOT NULL,
  description       TEXT,
  address           VARCHAR(255),
  university        VARCHAR(100),
  price             DECIMAL(10,2) NOT NULL DEFAULT 0,
  lat               DECIMAL(10,7) DEFAULT 14.5950,
  lng               DECIMAL(10,7) DEFAULT 120.9850,
  availability      INT           DEFAULT 0,
  total_slots       INT           DEFAULT 0,
  amenities         TEXT,
  images            TEXT,
  status            ENUM('pending','approved','rejected') DEFAULT 'pending',
  last_activity     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  inactive_notice_id INT          DEFAULT NULL,
  created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- BOOKINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS bookings (
  id          INT  AUTO_INCREMENT PRIMARY KEY,
  user_id     INT  NOT NULL,
  dorm_id     INT  NOT NULL,
  status      ENUM('pending','approved','rejected','vacated') DEFAULT 'pending',
  message     TEXT,
  date        DATE,
  vacate_date DATE         DEFAULT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (dorm_id) REFERENCES dorms(id) ON DELETE CASCADE
);

-- ============================================
-- REVIEWS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  dorm_id    INT NOT NULL,
  rating     INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment    TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (dorm_id) REFERENCES dorms(id) ON DELETE CASCADE,
  UNIQUE KEY unique_review (user_id, dorm_id)
);

-- ============================================
-- CHAT MESSAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS chat_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  sender_id  INT NOT NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id)  REFERENCES users(id)    ON DELETE CASCADE
);

-- ============================================
-- NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT         NOT NULL,
  type       VARCHAR(50) NOT NULL DEFAULT 'info',
  message    TEXT        NOT NULL,
  related_id INT         DEFAULT NULL,
  is_read    TINYINT(1)  DEFAULT 0,
  created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  action      VARCHAR(50),
  description TEXT,
  ip_address  VARCHAR(50),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INACTIVE NOTICES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS inactive_notices (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  dorm_id     INT       NOT NULL,
  admin_id    INT       NOT NULL,
  notice_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at  DATETIME  NOT NULL,
  status      ENUM('active','resolved','auto_deleted') DEFAULT 'active',
  FOREIGN KEY (dorm_id)  REFERENCES dorms(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- RECENTLY DELETED TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS recently_deleted (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  type          ENUM('dorm','user') NOT NULL,
  original_id   INT                 NOT NULL,
  data          LONGTEXT            NOT NULL,
  deleted_by    INT                 DEFAULT NULL,
  deleted_at    TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
  auto_purge_at DATETIME            NOT NULL,
  FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- ADMIN MESSAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS admin_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT  NOT NULL,
  renter_id  INT  NOT NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) DEFAULT 0,
  sent_by    INT        DEFAULT NULL,
  created_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (renter_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- SEED DATA
-- ============================================


-- ============================================
-- USERS
-- ID 1        = admin
-- IDs 2–11    = renters  (10 renters)
-- IDs 12–71   = rentees  (60 rentees)
-- ============================================
INSERT INTO users (id, name, email, password, role, status) VALUES
-- Admin
(1,  'Admin User',        'admin@dormMNL.ph',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',  'active'),

-- Renters (10)
(2,  'Maria Santos',      'maria@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(3,  'Mark Lim',          'mark@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(4,  'Ramon Aquino',      'ramon@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(5,  'Rosa Bautista',     'rosa@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(6,  'Tony Ramos',        'tony@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(7,  'Ben Torres',        'ben@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(8,  'Luz Ocampo',        'luz@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(9,  'Eddie Sison',       'eddie@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(10, 'Carla Vega',        'carla.v@example.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),
(11, 'Dante Flores',      'dante@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'renter', 'active'),

-- Rentees (60, IDs 12–71)
(12, 'Jose Cruz',         'jose@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(13, 'Ana Reyes',         'ana@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(14, 'Carlo Mendoza',     'carlo@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(15, 'Pia Villanueva',    'pia@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(16, 'Lena Pascual',      'lena@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(17, 'Kevin Tan',         'kevin@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(18, 'Grace Dela Cruz',   'grace@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(19, 'Sheila Gomez',      'sheila@example.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(20, 'Noel Garcia',       'noel@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(21, 'Claire Navarro',    'claire@example.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(22, 'Mia Castillo',      'mia@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(23, 'Leo Fernandez',     'leo@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(24, 'Nina Ramos',        'nina@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(25, 'Rico Buenaventura', 'rico@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(26, 'Tina Macaraeg',     'tina@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(27, 'Arvin Dela Rosa',   'arvin@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(28, 'Bea Soriano',       'bea@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(29, 'Gino Abad',         'gino@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(30, 'Hazel Corpuz',      'hazel@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(31, 'Ian Salazar',       'ian@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(32, 'Jenny Manalo',      'jenny@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(33, 'Kurt Aguilar',      'kurt@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(34, 'Lily Reyes',        'lily@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(35, 'Marco Dizon',       'marco@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(36, 'Nadia Peralta',     'nadia@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(37, 'Oscar Evangelista', 'oscar@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(38, 'Paula Santiago',    'paula@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(39, 'Quino Bacalso',     'quino@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(40, 'Rina Magno',        'rina@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(41, 'Sam Fajardo',       'sam@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(42, 'Trish Alcantara',   'trish@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(43, 'Ugo Bernabe',       'ugo@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(44, 'Vera Santos',       'vera@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(45, 'Will Chua',         'will@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(46, 'Xia Bondoc',        'xia@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(47, 'Yvan Ong',          'yvan@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(48, 'Zara Muñoz',        'zara@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(49, 'Abel Ponce',        'abel@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(50, 'Beth Ignacio',      'beth@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(51, 'Cris Hernandez',    'cris@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(52, 'Dana Rivera',       'dana@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(53, 'Eli Robles',        'eli@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(54, 'Faye Gutierrez',    'faye@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(55, 'Gio Mendez',        'gio@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(56, 'Hope Lacson',       'hope@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(57, 'Irene Quizon',      'irene@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(58, 'Jaime Agno',        'jaime@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(59, 'Kaye Villarin',     'kaye@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(60, 'Louie Delos Santos','louie@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(61, 'Mae Tolentino',     'mae@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(62, 'Nathan Sevilla',    'nathan@example.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(63, 'Olive Buenaobra',   'olive@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(64, 'Pete Umali',        'pete@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(65, 'Queen Macasaet',    'queen@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(66, 'Rex Alvarez',       'rex@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(67, 'Sofia Buenaventura','sofia@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(68, 'Tomas Natividad',   'tomas@example.com',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(69, 'Ursula Abad',       'ursula@example.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(70, 'Vic Batungbakal',   'vic@example.com',         '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active'),
(71, 'Weng Macalinao',    'weng@example.com',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rentee', 'active');
-- Total users: 71 (1 admin + 10 renters + 60 rentees)

-- ============================================
-- DORMS (30 dorms, 3 per renter)
-- owner_id 2  → dorms 1–3
-- owner_id 3  → dorms 4–6
-- owner_id 4  → dorms 7–9
-- owner_id 5  → dorms 10–12
-- owner_id 6  → dorms 13–15
-- owner_id 7  → dorms 16–18
-- owner_id 8  → dorms 19–21
-- owner_id 9  → dorms 22–24
-- owner_id 10 → dorms 25–27
-- owner_id 11 → dorms 28–30
-- ============================================
INSERT INTO dorms (id, owner_id, name, description, address, university, price, lat, lng, availability, total_slots, amenities, status) VALUES
-- Maria Santos (2)
(1,  2,  'Sampaguita Dormitory',      'Clean dorm near UST. 24/7 security and CCTV.',                   'España Blvd, Sampaloc, Manila',        'UST',      3500, 14.6103, 120.9890, 4, 10, 'WiFi,AC,CCTV,Laundry,Water Included',            'approved'),
(2,  2,  'Maynila Suites',            'Modern boarding house near PLM. LRT accessible.',                'Taft Ave, Ermita, Manila',             'PLM',      4200, 14.5794, 120.9840, 3,  8, 'WiFi,AC,Study Room,Kitchen,Parking',             'approved'),
(3,  2,  'Arroceros Boarding House',  'Cozy and secure boarding house near Mapua.',                     'Arroceros St, Ermita, Manila',         'Mapua',    3200, 14.5950, 120.9827, 5,  8, 'WiFi,Fan,CCTV,Water Included',                   'approved'),

-- Mark Lim (3)
(4,  3,  'Rizal Student Homes',       'Budget-friendly dorm beside DLSU. Walking distance to school.',  'Taft Ave, Malate, Manila',             'DLSU',     2800, 14.5640, 120.9935, 3,  8, 'WiFi,Fan,Security,Laundry',                      'approved'),
(5,  3,  'Plaza Cervantes Residences','Premium dorm with private rooms near FEU.',                      'Nicanor Reyes St, Sampaloc, Manila',   'FEU',      5500, 14.6001, 120.9869, 2,  5, 'WiFi,AC,Private Bath,Study Area,Meals Included', 'approved'),
(6,  3,  'Taft Residences',           'Well-maintained dorm along Taft, close to DLSU and PLM.',        'Taft Ave, Malate, Manila',             'DLSU',     3800, 14.5670, 120.9920, 4,  9, 'WiFi,AC,CCTV,Kitchen',                           'approved'),

-- Ramon Aquino (4)
(7,  4,  'Espana Lodge',              'Affordable lodge for UST and NU students.',                      'España Blvd, Manila',                  'UST',      3000, 14.6090, 120.9870, 5, 10, 'WiFi,Fan,Water Included,Security',               'approved'),
(8,  4,  'Recto Haven',               'Strategic location near PUP, LRT Recto station nearby.',         'Claro M. Recto Ave, Manila',           'PUP',      2500, 14.5988, 120.9850, 6,  9, 'WiFi,Fan,Laundry,CCTV',                          'approved'),
(9,  4,  'Vito Cruz Suites',          'Modern suites near De La Salle University.',                     'Vito Cruz, Malate, Manila',            'DLSU',     4500, 14.5630, 120.9940, 3,  7, 'WiFi,AC,Private Bath,Study Room',                'approved'),

-- Rosa Bautista (5)
(10, 5,  'Dapitan Student Homes',     'Quiet dorm near UST College of Nursing.',                        'Dapitan St, Sampaloc, Manila',         'UST',      3100, 14.6080, 120.9910, 4,  9, 'WiFi,Fan,CCTV,Water Included',                   'approved'),
(11, 5,  'Intramuros Scholar Lodge',  'Historic area dorm near PLM and Mapua.',                         'General Luna St, Intramuros, Manila',  'PLM',      2900, 14.5895, 120.9750, 5,  8, 'WiFi,Fan,Security,Laundry',                      'approved'),
(12, 5,  'Lacson Boarding House',     'Near Centro Escolar University and UST.',                        'Lacson Ave, Sampaloc, Manila',         'CEU',      3300, 14.6050, 120.9940, 4, 10, 'WiFi,AC,CCTV,Kitchen,Water Included',            'approved'),

-- Tony Ramos (6)
(13, 6,  'Mendiola Residences',       'Close to San Beda, Mapua, and FEU campuses.',                    'Mendiola St, Manila',                  'San Beda', 3600, 14.6020, 120.9890, 3,  6, 'WiFi,AC,Security,Study Room',                    'approved'),
(14, 6,  'UN Ave Dormitory',          'Near Pamantasan ng Lungsod ng Maynila.',                         'UN Ave, Ermita, Manila',               'PLM',      2700, 14.5810, 120.9820, 5, 10, 'WiFi,Fan,CCTV,Laundry',                          'approved'),
(15, 6,  'P. Noval Pension House',    'Affordable pension near UST and FEU.',                           'P. Noval St, Sampaloc, Manila',        'FEU',      3000, 14.6070, 120.9900, 6,  9, 'WiFi,Fan,Water Included,CCTV',                   'approved'),

-- Ben Torres (7)
(16, 7,  'Sampaloc Central Dorm',     'Great location, near multiple universities in Sampaloc.',         'Earnshaw St, Sampaloc, Manila',        'UST',      3200, 14.6110, 120.9920, 4,  8, 'WiFi,AC,Laundry,Security',                       'approved'),
(17, 7,  'Mapa Residences',           'Near Mapua and PUP, very budget-friendly.',                      'Mapa St, Sta. Cruz, Manila',           'Mapua',    2600, 14.6005, 120.9840, 5,  9, 'WiFi,Fan,Water Included,CCTV',                   'approved'),
(18, 7,  'Singalong Student Dorm',    'Walking distance to DLSU and Adamson University.',               'Singalong St, Malate, Manila',         'DLSU',     3400, 14.5660, 120.9930, 3,  7, 'WiFi,AC,CCTV,Kitchen',                           'approved'),

-- Luz Ocampo (8)
(19, 8,  'España Corner Lodge',       'Corner lot dorm, highly accessible near UST.',                   'España corner Lacson, Manila',         'UST',      3150, 14.6095, 120.9950, 5,  9, 'WiFi,Fan,Security,Laundry,Water Included',        'approved'),
(20, 8,  'Adamson Nearby Dorm',       'Best pick for Adamson University students.',                     'San Marcelino St, Malate, Manila',     'Adamson',  2950, 14.5700, 120.9910, 4,  8, 'WiFi,Fan,CCTV,Laundry',                          'approved'),
(21, 8,  'Paco Student Hub',          'Quiet neighborhood near PUP satellite campus.',                  'Paco, Manila',                         'PUP',      2800, 14.5820, 120.9930, 6, 10, 'WiFi,Fan,Water Included,Security',               'approved'),

-- Eddie Sison (9)
(22, 9,  'Nagtahan Boarding House',   'Close to UST Graduate School and PGH.',                          'Nagtahan, Sampaloc, Manila',           'UST',      3400, 14.6040, 120.9900, 3,  7, 'WiFi,AC,CCTV,Study Room',                        'approved'),
(23, 9,  'Quirino Grand Dorm',        'Near PLM main campus and Pasay area LRT.',                       'Quirino Ave, Malate, Manila',          'PLM',      2750, 14.5760, 120.9850, 5,  9, 'WiFi,Fan,Laundry,Water Included',                'approved'),
(24, 9,  'Sta. Cruz Scholar House',   'Ideal for Mapua and PUP students. Has a common study area.',     'Sta. Cruz, Manila',                    'Mapua',    3050, 14.6010, 120.9860, 4,  8, 'WiFi,AC,Study Room,Security',                    'approved'),

-- Carla Vega (10)
(25, 10, 'Legarda Student Lodge',     'Near FEU and NU campuses, accessible via LRT.',                  'Legarda St, Sampaloc, Manila',         'FEU',      3250, 14.5990, 120.9855, 4,  9, 'WiFi,AC,CCTV,Kitchen',                           'approved'),
(26, 10, 'Lacson Premium Suites',     'Premium rooms near UST and CEU. 24-hour guard.',                 'Lacson Ave Extension, Manila',         'CEU',      4800, 14.6055, 120.9945, 2,  6, 'WiFi,AC,Private Bath,Study Room,Meals Included', 'approved'),
(27, 10, 'Antipolo Flats Manila',     'Budget flats near Mapua Intramuros campus.',                     'Antipolo St, Sta. Cruz, Manila',       'Mapua',    2650, 14.5960, 120.9820, 5, 10, 'WiFi,Fan,CCTV,Laundry',                          'approved'),

-- Dante Flores (11)
(28, 11, 'Abad Santos Scholar Den',   'Quiet street near San Beda College of Law.',                     'Abad Santos Ave, Tondo, Manila',       'San Beda', 3050, 14.6180, 120.9810, 4,  8, 'WiFi,Fan,Security,Water Included',               'approved'),
(29, 11, 'Blumentritt Budget Rooms',  'Very affordable for students at UPHSD Manila.',                  'Blumentritt Rd, Sta. Cruz, Manila',    'UPHSD',    2400, 14.6130, 120.9830, 6, 10, 'WiFi,Fan,Laundry,CCTV',                          'approved'),
(30, 11, 'Oroquieta Dorm',            'Comfortable dorm a few blocks from FEU and NU.',                 'Oroquieta St, Sta. Cruz, Manila',      'FEU',      3100, 14.6015, 120.9845, 5,  9, 'WiFi,AC,CCTV,Kitchen,Water Included',            'approved');
-- Total dorms: 30

-- ============================================
-- BOOKINGS
-- approved:  30 (rentees 12–41, 1 active each)
-- pending:   10 (rentees 42–51)
-- rejected:  10 (rentees 52–61)
-- vacated:   10 (rentees 62–71)
-- Total:     60
-- ============================================
INSERT INTO bookings (id, user_id, dorm_id, status, message, date, vacate_date) VALUES
-- APPROVED (30)
(1,  12, 1,  'approved', 'UST freshman. Looking for a long-term stay.',                 '2025-01-10', NULL),
(2,  13, 2,  'approved', 'PLM student, 2nd year. Need quiet room.',                     '2025-01-12', NULL),
(3,  14, 3,  'approved', 'Mapua student, need dorm near school.',                       '2025-01-15', NULL),
(4,  15, 4,  'approved', 'DLSU freshman. Budget is limited.',                           '2025-01-18', NULL),
(5,  16, 5,  'approved', 'FEU student, board reviewer, need study area.',               '2025-01-20', NULL),
(6,  17, 6,  'approved', 'DLSU sophomore, prefer AC room.',                             '2025-01-22', NULL),
(7,  18, 7,  'approved', 'UST Nursing student, need long-term accommodation.',          '2025-01-25', NULL),
(8,  19, 8,  'approved', 'PUP student, looking for affordable dorm.',                   '2025-01-28', NULL),
(9,  20, 9,  'approved', 'DLSU student, need single room with study table.',            '2025-02-01', NULL),
(10, 21, 10, 'approved', 'UST CON student, prefer quiet area.',                         '2025-02-03', NULL),
(11, 22, 11, 'approved', 'PLM student. First-time renter.',                             '2025-02-05', NULL),
(12, 23, 12, 'approved', 'CEU freshmen. Need room near Lacson.',                        '2025-02-08', NULL),
(13, 24, 13, 'approved', 'San Beda law student, need quiet place.',                     '2025-02-10', NULL),
(14, 25, 14, 'approved', 'PLM student, need affordable room near UN Ave.',              '2025-02-12', NULL),
(15, 26, 15, 'approved', 'FEU student. Looking for semi-furnished room.',               '2025-02-14', NULL),
(16, 27, 16, 'approved', 'UST Med student, need dorm with fast WiFi.',                  '2025-02-16', NULL),
(17, 28, 17, 'approved', 'Mapua freshman. Tight budget.',                               '2025-02-18', NULL),
(18, 29, 18, 'approved', 'DLSU Architecture student, need creative workspace.',         '2025-02-20', NULL),
(19, 30, 19, 'approved', 'UST Pharma student. Prefer dorm with 24/7 access.',           '2025-02-22', NULL),
(20, 31, 20, 'approved', 'Adamson University student. Need affordable dorm.',           '2025-02-24', NULL),
(21, 32, 21, 'approved', 'PUP satellite campus student. Budget-conscious.',             '2025-02-26', NULL),
(22, 33, 22, 'approved', 'UST Grad student, need a quiet study environment.',           '2025-03-01', NULL),
(23, 34, 23, 'approved', 'PLM student. Need dorm near Quirino Ave.',                    '2025-03-03', NULL),
(24, 35, 24, 'approved', 'Mapua student, looking for a study-friendly dorm.',           '2025-03-05', NULL),
(25, 36, 25, 'approved', 'FEU student, need LRT-accessible dorm.',                     '2025-03-07', NULL),
(26, 37, 26, 'approved', 'CEU student. Prefer AC room with private bath.',              '2025-03-09', NULL),
(27, 38, 27, 'approved', 'Mapua Intramuros student, need budget room.',                 '2025-03-11', NULL),
(28, 39, 28, 'approved', 'San Beda student. Need dorm near Abad Santos.',               '2025-03-13', NULL),
(29, 40, 29, 'approved', 'UPHSD student. Very tight budget.',                           '2025-03-15', NULL),
(30, 41, 30, 'approved', 'FEU student, looking for dorm near Oroquieta.',               '2025-03-17', NULL),

-- PENDING (10)
(31, 42, 1,  'pending',  'UST student, applying for room this semester.',               '2025-04-01', NULL),
(32, 43, 4,  'pending',  'DLSU incoming, looking for budget room near Taft.',          '2025-04-02', NULL),
(33, 44, 7,  'pending',  'UST applicant, need room immediately.',                       '2025-04-03', NULL),
(34, 45, 10, 'pending',  'UST nursing applicant, need quiet room.',                     '2025-04-04', NULL),
(35, 46, 13, 'pending',  'San Beda student, applying for next semester.',               '2025-04-05', NULL),
(36, 47, 16, 'pending',  'UST Med applicant, need single room.',                        '2025-04-06', NULL),
(37, 48, 19, 'pending',  'UST student, looking for dorm near España.',                 '2025-04-07', NULL),
(38, 49, 22, 'pending',  'UST Grad applicant, need study-friendly room.',               '2025-04-08', NULL),
(39, 50, 25, 'pending',  'FEU freshman, looking for LRT-accessible dorm.',             '2025-04-09', NULL),
(40, 51, 28, 'pending',  'San Beda applicant, need room near Abad Santos.',             '2025-04-10', NULL),

-- REJECTED (10)
(41, 52, 2,  'rejected', 'PLM student, budget too low for the listing.',                '2025-03-01', NULL),
(42, 53, 5,  'rejected', 'FEU student, did not meet dorm requirements.',                '2025-03-02', NULL),
(43, 54, 8,  'rejected', 'PUP student, dorm was already full.',                         '2025-03-03', NULL),
(44, 55, 11, 'rejected', 'PLM applicant, did not provide valid ID.',                    '2025-03-04', NULL),
(45, 56, 14, 'rejected', 'PLM student, profile incomplete.',                            '2025-03-05', NULL),
(46, 57, 17, 'rejected', 'Mapua student, timing conflict with move-in.',                '2025-03-06', NULL),
(47, 58, 20, 'rejected', 'Adamson student, dorm policy mismatch.',                      '2025-03-07', NULL),
(48, 59, 23, 'rejected', 'PLM student, deposit not settled in time.',                   '2025-03-08', NULL),
(49, 60, 26, 'rejected', 'CEU student, wrong university listed.',                       '2025-03-09', NULL),
(50, 61, 29, 'rejected', 'UPHSD student, did not respond to owner messages.',           '2025-03-10', NULL),

-- VACATED (10)
(51, 62, 3,  'vacated',  'Finished semester. Vacating room.',                           '2024-08-01', '2024-12-20'),
(52, 63, 6,  'vacated',  'Graduated. No longer needs the room.',                        '2024-08-05', '2024-12-22'),
(53, 64, 9,  'vacated',  'Transferred school, needs to move out.',                      '2024-08-10', '2024-12-25'),
(54, 65, 12, 'vacated',  'End of contract. Moving to another city.',                    '2024-08-15', '2024-12-28'),
(55, 66, 15, 'vacated',  'Completed board exams, going home to province.',              '2024-08-20', '2025-01-05'),
(56, 67, 18, 'vacated',  'Finished 1st semester. Moving to new dorm.',                  '2024-09-01', '2025-01-10'),
(57, 68, 21, 'vacated',  'Semester done. Returning home.',                              '2024-09-05', '2025-01-12'),
(58, 69, 24, 'vacated',  'Study abroad program, vacating dorm.',                        '2024-09-10', '2025-01-15'),
(59, 70, 27, 'vacated',  'Graduated last December. No longer needs room.',              '2024-09-15', '2025-01-18'),
(60, 71, 30, 'vacated',  'Done with thesis, heading home to province.',                 '2024-09-20', '2025-01-20');
-- Total bookings: 60

-- ============================================
-- REVIEWS (20)
-- user_id must have an approved or vacated booking for that dorm_id
-- ============================================
INSERT INTO reviews (user_id, dorm_id, rating, comment) VALUES
(12, 1,  5, 'Napaka-nice ng dorm! Malinis at may mabilis na WiFi. Very safe din.'),
(13, 2,  4, 'Okay naman. May AC at madaling makarating sa PLM. A bit strict curfew pero understandable.'),
(14, 3,  4, 'Worth the price! May laundry area at malapit sa Mapua. Highly recommended.'),
(15, 4,  5, 'Best dorm ko ever sa Taft! Walking distance sa DLSU. Very affordable.'),
(16, 5,  5, 'Premium talaga ito. May private bath at meals included. Worth every peso.'),
(17, 6,  4, 'Nice dorm, malapit sa DLSU. AC room ay comfortable. Highly recommended.'),
(18, 7,  5, 'Sobrang ganda! Malinis, may CCTV, at mabait ang may-ari. Best for UST!'),
(19, 8,  3, 'Medyo basic lang pero okay naman para sa budget ko. Malapit sa LRT Recto.'),
(20, 9,  5, 'Vito Cruz Suites is the best! Private bath at study room ang plus factor.'),
(21, 10, 4, 'Quiet area, perfect for Nursing students. May water included pa.'),
(22, 11, 4, 'Good value for money. Malapit sa PLM at Mapua. Recommend ko ito!'),
(23, 12, 5, 'Lacson area is perfect for CEU students. Sobrang linis ng dorm!'),
(62, 3,  4, 'Magandang dorm ang Arroceros! Malapit sa Mapua at may 24/7 guard.'),
(63, 6,  3, 'Okay naman but medyo maingay sa gabi. Good location though sa Taft.'),
(64, 9,  5, 'Pinaka-favorite ko! Tahimik at malinis. May study room pa. Worth it!'),
(65, 12, 4, 'Nice dorm sa Lacson area. WiFi is fast and owner is very approachable.'),
(66, 15, 5, 'P. Noval Pension House is underrated! Very clean at may mabait na staff.'),
(67, 18, 4, 'Singalong dorm is great for DLSU students. AC room at malapit sa school.'),
(68, 21, 4, 'Paco Student Hub is quiet and affordable. Good for PUP students.'),
(69, 24, 5, 'Sta. Cruz Scholar House is amazing! May study lounge at mabait ang may-ari.');
-- Total reviews: 20

-- ============================================
-- CHAT MESSAGES (20)
-- Only from approved bookings (IDs 1–30)
-- ============================================
INSERT INTO chat_messages (booking_id, sender_id, message) VALUES
-- Booking 1: Jose Cruz (12) ↔ Maria Santos (2)
(1,  2,  'Hi Jose! Welcome to Sampaguita Dormitory. Please bring 2 valid IDs on move-in day.'),
(1,  12, 'Thank you po! Kailan po pwedeng mag-move in? This Saturday okay ba?'),
(1,  2,  'Yes, Saturday is fine. Move-in is 8AM–5PM only. See you!'),
-- Booking 4: Pia Villanueva (15) ↔ Mark Lim (3)
(4,  3,  'Hi Pia! Booking confirmed. Please settle the deposit within 3 days.'),
(4,  15, 'Sige po! Magbabayad na po ako bukas. Salamat po!'),
(4,  3,  'Great! Welcome to Rizal Student Homes.'),
-- Booking 7: Grace Dela Cruz (18) ↔ Ramon Aquino (4)
(7,  4,  'Hi Grace! Approved na ang booking mo. Kelan ka makakapag-move in?'),
(7,  18, 'This coming Monday po sana. Okay lang po ba?'),
(7,  4,  'Monday is fine! Bring one month deposit and advance rent.'),
-- Booking 10: Claire Navarro (21) ↔ Rosa Bautista (5)
(10, 5,  'Hi Claire! Booking confirmed. UST CON student ka diba? Malapit kami doon.'),
(10, 21, 'Oo po! Excited na po ako. May park ba kayo para sa bisita?'),
(10, 5,  'Meron! Pero limited lang. Inform kami in advance kung may bisita.'),
-- Booking 13: Nina Ramos (24) ↔ Tony Ramos (6)
(13, 6,  'Hi Nina! Approved na ang iyong booking sa Mendiola Residences.'),
(13, 24, 'Salamat po! Pwede po ba akong mag-move in this weekend?'),
(13, 6,  'Okay lang! Text mo kami kapag papunta ka na.'),
-- Booking 16: Arvin Dela Rosa (27) ↔ Ben Torres (7)
(16, 7,  'Hello Arvin! Sampaloc Central Dorm is ready for you. Welcome!'),
(16, 27, 'Salamat po! Excited na ko. May laundry shop ba sa malapit?'),
(16, 7,  'Meron! Katabi lang ng dorm. See you on move-in day!'),
-- Booking 19: Hazel Corpuz (30) ↔ Luz Ocampo (8)
(19, 8,  'Hi Hazel! Your room at España Corner Lodge is ready. Congrats!'),
(19, 30, 'Thank you po! Dala ko na po lahat ng requirements.');
-- Total chat messages: 20

-- ============================================
-- NOTIFICATIONS (20)
-- ============================================
INSERT INTO notifications (user_id, type, message, related_id) VALUES
(12, 'booking_approved', 'Your booking for Sampaguita Dormitory has been approved! You can now chat with the owner.', 1),
(2,  'new_booking',      'Jose Cruz applied to rent Sampaguita Dormitory.',                                           1),
(13, 'booking_approved', 'Your booking for Maynila Suites has been approved!',                                        2),
(15, 'booking_approved', 'Your booking for Rizal Student Homes has been approved!',                                   4),
(3,  'new_booking',      'Pia Villanueva applied to rent Rizal Student Homes.',                                       4),
(18, 'booking_approved', 'Your booking for Espana Lodge has been approved!',                                          7),
(4,  'new_booking',      'Grace Dela Cruz applied to rent Espana Lodge.',                                             7),
(19, 'booking_rejected', 'Your booking for Recto Haven has been rejected. You may apply to other dorms.',             8),
(21, 'booking_approved', 'Your booking for Dapitan Student Homes has been approved!',                                 10),
(5,  'new_booking',      'Claire Navarro applied to rent Dapitan Student Homes.',                                     10),
(42, 'new_booking',      'Your application for Sampaguita Dormitory is under review.',                                31),
(43, 'new_booking',      'Your application for Rizal Student Homes is under review.',                                 32),
(52, 'booking_rejected', 'Your booking for Maynila Suites was not accepted.',                                         41),
(62, 'vacated_confirmed','You have successfully vacated Arroceros Boarding House. Thank you for staying!',            51),
(63, 'vacated_confirmed','You have successfully vacated Taft Residences. Thank you for staying!',                     52),
(27, 'booking_approved', 'Your booking for Sampaloc Central Dorm has been approved!',                                 16),
(7,  'new_booking',      'Arvin Dela Rosa applied to rent Sampaloc Central Dorm.',                                    16),
(30, 'booking_approved', 'Your booking for España Corner Lodge has been approved!',                                   19),
(8,  'new_booking',      'Hazel Corpuz applied to rent España Corner Lodge.',                                         19),
(1,  'info',             'System: 3 new dorm listings are pending review.',                                           NULL);
-- Total notifications: 20

-- ============================================
-- LOGS (20)
-- ============================================
INSERT INTO logs (user_id, action, description, ip_address, created_at) VALUES
(1,  'login',         'Admin logged in',                                    '192.168.1.1',  '2025-01-01 08:00:00'),
(2,  'create_dorm',   'Created listing: Sampaguita Dormitory',              '192.168.1.2',  '2025-01-02 09:00:00'),
(2,  'create_dorm',   'Created listing: Maynila Suites',                    '192.168.1.2',  '2025-01-02 09:15:00'),
(2,  'create_dorm',   'Created listing: Arroceros Boarding House',          '192.168.1.2',  '2025-01-02 09:30:00'),
(3,  'create_dorm',   'Created listing: Rizal Student Homes',               '192.168.1.3',  '2025-01-03 10:00:00'),
(4,  'create_dorm',   'Created listing: Espana Lodge',                      '192.168.1.4',  '2025-01-04 10:00:00'),
(12, 'login',         'User Jose Cruz logged in',                           '192.168.1.12', '2025-01-10 08:00:00'),
(12, 'booking',       'Booked Sampaguita Dormitory',                        '192.168.1.12', '2025-01-10 08:05:00'),
(15, 'booking',       'Booked Rizal Student Homes',                         '192.168.1.15', '2025-01-18 09:00:00'),
(18, 'booking',       'Booked Espana Lodge',                                '192.168.1.18', '2025-01-25 10:00:00'),
(1,  'approve_dorm',  'Approved listing: Sampaguita Dormitory',             '192.168.1.1',  '2025-01-05 08:30:00'),
(1,  'approve_dorm',  'Approved listing: Rizal Student Homes',              '192.168.1.1',  '2025-01-05 08:45:00'),
(2,  'booking_update','Approved booking #1 for Jose Cruz',                  '192.168.1.2',  '2025-01-11 09:00:00'),
(3,  'booking_update','Approved booking #4 for Pia Villanueva',             '192.168.1.3',  '2025-01-19 09:00:00'),
(62, 'vacated',       'Rentee vacated Arroceros Boarding House',            '192.168.1.62', '2024-12-20 14:00:00'),
(63, 'vacated',       'Rentee vacated Taft Residences',                     '192.168.1.63', '2024-12-22 14:00:00'),
(1,  'ban_user',      'No bans issued this period.',                        '192.168.1.1',  '2025-04-01 08:00:00'),
(42, 'booking',       'Applied to Sampaguita Dormitory',                    '192.168.1.42', '2025-04-01 10:00:00'),
(43, 'booking',       'Applied to Rizal Student Homes',                     '192.168.1.43', '2025-04-02 10:00:00'),
(1,  'login',         'Admin performed system check',                       '192.168.1.1',  '2025-04-10 08:00:00');
-- Total logs: 20

-- ============================================
-- ADMIN MESSAGES (5)
-- ============================================
INSERT INTO admin_messages (admin_id, renter_id, message, is_read, sent_by) VALUES
(1, 2,  'Hi Maria! Your dorm listings have been approved. Please make sure your availability counts are up to date.', 1, 1),
(1, 3,  'Hi Mark! We noticed Rizal Student Homes has low availability. Please update your listing regularly.',        0, 1),
(1, 4,  'Hello Ramon! Your Espana Lodge listing looks great. Keep it updated regularly.',                             1, 1),
(1, 5,  'Hi Rosa! Thank you for being a top renter on dormMNL. Your listings are highly rated!',                     0, 1),
(1, 6,  'Hi Tony! Please review the house rules section in your UN Ave Dormitory listing.',                          0, 1);
-- Total admin messages: 5

-- ============================================
-- SUMMARY
-- ============================================
-- users:          71  (1 admin + 10 renters + 60 rentees)
-- dorms:          30  (3 per renter × 10 renters)
-- bookings:       60  (30 approved + 10 pending + 10 rejected + 10 vacated)
-- reviews:        20
-- chat_messages:  20
-- notifications:  20
-- logs:           20
-- admin_messages:  5
-- inactive_notices: 0 (managed via admin UI)
-- recently_deleted: 0 (managed via admin UI)
-- GRAND TOTAL:   246 rows
--
-- CONSTRAINTS ENFORCED:
--   ✅ Each rentee has AT MOST 1 active (approved/pending) booking at a time
--   ✅ Each renter owns exactly 3 dorms
--   ✅ Chat messages only exist on approved bookings
--   ✅ Reviews only from rentees with a matching approved/vacated booking
--   ✅ FK integrity: all user_id, dorm_id, booking_id references are valid
--   ✅ All passwords = "password"
-- ============================================

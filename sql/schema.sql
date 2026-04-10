CREATE TABLE IF NOT EXISTS classes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  description TEXT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_class_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS semesters (
  class_id INT UNSIGNED NOT NULL,
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  starts_on DATE DEFAULT NULL,
  ends_on DATE DEFAULT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_class_semester_slug (class_id, slug),
  KEY idx_semesters_class (class_id),
  CONSTRAINT fk_semesters_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'manager',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_user_email (email),
  KEY idx_users_class (class_id),
  CONSTRAINT fk_users_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  semester_id INT UNSIGNED NOT NULL,
  manager_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  student_name VARCHAR(120) NOT NULL,
  summary TEXT NOT NULL,
  description_html MEDIUMTEXT NOT NULL,
  image_url VARCHAR(500) DEFAULT NULL,
  external_url VARCHAR(500) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_project_slug (slug),
  KEY idx_projects_semester (semester_id),
  KEY idx_projects_manager (manager_user_id),
  CONSTRAINT fk_projects_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE,
  CONSTRAINT fk_projects_manager FOREIGN KEY (manager_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  semester_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  device_token VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_like_device_project (semester_id, project_id, device_token),
  KEY idx_likes_semester_device (semester_id, device_token),
  KEY idx_favorites_project (project_id),
  CONSTRAINT fk_favorites_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE,
  CONSTRAINT fk_favorites_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vote_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  icon VARCHAR(40) NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_vote_category_class_slug (class_id, slug),
  KEY idx_vote_categories_class_order (class_id, is_active, display_order),
  CONSTRAINT fk_vote_categories_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_votes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_vote_per_user_category (semester_id, user_id, category_id),
  KEY idx_project_votes_project_category (project_id, category_id),
  KEY idx_project_votes_semester_category (semester_id, category_id),
  KEY idx_project_votes_class (class_id),
  CONSTRAINT fk_project_votes_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_votes_semester FOREIGN KEY (semester_id) REFERENCES semesters (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_votes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_votes_category FOREIGN KEY (category_id) REFERENCES vote_categories (id) ON DELETE CASCADE,
  CONSTRAINT fk_project_votes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_settings (
  class_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (class_id, setting_key),
  CONSTRAINT fk_class_settings_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO classes (name, slug, description, is_active, display_order)
SELECT 'CGT390', 'cgt390', 'Student project showcase for CGT390.', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM classes WHERE slug = 'cgt390');

INSERT INTO classes (name, slug, description, is_active, display_order)
SELECT 'CGT270', 'cgt270', 'Student project showcase for CGT270.', 1, 2
WHERE NOT EXISTS (SELECT 1 FROM classes WHERE slug = 'cgt270');

INSERT INTO classes (name, slug, description, is_active, display_order)
SELECT 'CGT370', 'cgt370', 'Student project showcase for CGT370.', 1, 3
WHERE NOT EXISTS (SELECT 1 FROM classes WHERE slug = 'cgt370');

SET @class_cgt390 = (SELECT id FROM classes WHERE slug = 'cgt390' LIMIT 1);

SET @users_role_supports_regular_user = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'users'
    AND column_name = 'role'
    AND column_type LIKE "%\\'user\\'%"
);
SET @expand_users_role_enum_sql = IF(
  @users_role_supports_regular_user = 0,
  'ALTER TABLE users MODIFY COLUMN role ENUM(\'admin\', \'manager\', \'user\') NOT NULL DEFAULT \'manager\'',
  'SELECT 1'
);
PREPARE expand_users_role_enum_statement FROM @expand_users_role_enum_sql;
EXECUTE expand_users_role_enum_statement;
DEALLOCATE PREPARE expand_users_role_enum_statement;

SET @has_users_class_id = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'users'
    AND column_name = 'class_id'
);
SET @add_users_class_id_sql = IF(
  @has_users_class_id = 0,
  'ALTER TABLE users ADD COLUMN class_id INT UNSIGNED NULL AFTER id, ADD KEY idx_users_class (class_id)',
  'SELECT 1'
);
PREPARE add_users_class_id_statement FROM @add_users_class_id_sql;
EXECUTE add_users_class_id_statement;
DEALLOCATE PREPARE add_users_class_id_statement;

UPDATE users
SET class_id = @class_cgt390
WHERE role = 'manager' AND class_id IS NULL;

SET @has_users_class_fk = (
  SELECT COUNT(*)
  FROM information_schema.referential_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'users'
    AND constraint_name = 'fk_users_class'
);
SET @add_users_class_fk_sql = IF(
  @has_users_class_fk = 0,
  'ALTER TABLE users ADD CONSTRAINT fk_users_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE add_users_class_fk_statement FROM @add_users_class_fk_sql;
EXECUTE add_users_class_fk_statement;
DEALLOCATE PREPARE add_users_class_fk_statement;

SET @has_semesters_class_id = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'semesters'
    AND column_name = 'class_id'
);
SET @add_semesters_class_id_sql = IF(
  @has_semesters_class_id = 0,
  'ALTER TABLE semesters ADD COLUMN class_id INT UNSIGNED NULL FIRST, ADD KEY idx_semesters_class (class_id)',
  'SELECT 1'
);
PREPARE add_semesters_class_id_statement FROM @add_semesters_class_id_sql;
EXECUTE add_semesters_class_id_statement;
DEALLOCATE PREPARE add_semesters_class_id_statement;

UPDATE semesters
SET class_id = @class_cgt390
WHERE class_id IS NULL;

SET @semesters_class_id_nullable = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'semesters'
    AND column_name = 'class_id'
    AND is_nullable = 'YES'
);
SET @make_semesters_class_id_not_null_sql = IF(
  @semesters_class_id_nullable > 0,
  'ALTER TABLE semesters MODIFY COLUMN class_id INT UNSIGNED NOT NULL FIRST',
  'SELECT 1'
);
PREPARE make_semesters_class_id_not_null_statement FROM @make_semesters_class_id_not_null_sql;
EXECUTE make_semesters_class_id_not_null_statement;
DEALLOCATE PREPARE make_semesters_class_id_not_null_statement;

SET @has_old_semester_slug_unique = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'semesters'
    AND index_name = 'uniq_semester_slug'
);
SET @drop_old_semester_slug_unique_sql = IF(
  @has_old_semester_slug_unique > 0,
  'ALTER TABLE semesters DROP INDEX uniq_semester_slug',
  'SELECT 1'
);
PREPARE drop_old_semester_slug_unique_statement FROM @drop_old_semester_slug_unique_sql;
EXECUTE drop_old_semester_slug_unique_statement;
DEALLOCATE PREPARE drop_old_semester_slug_unique_statement;

SET @has_new_semester_slug_unique = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'semesters'
    AND index_name = 'uniq_class_semester_slug'
);
SET @add_new_semester_slug_unique_sql = IF(
  @has_new_semester_slug_unique = 0,
  'ALTER TABLE semesters ADD UNIQUE KEY uniq_class_semester_slug (class_id, slug)',
  'SELECT 1'
);
PREPARE add_new_semester_slug_unique_statement FROM @add_new_semester_slug_unique_sql;
EXECUTE add_new_semester_slug_unique_statement;
DEALLOCATE PREPARE add_new_semester_slug_unique_statement;

SET @has_semesters_class_fk = (
  SELECT COUNT(*)
  FROM information_schema.referential_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'semesters'
    AND constraint_name = 'fk_semesters_class'
);
SET @add_semesters_class_fk_sql = IF(
  @has_semesters_class_fk = 0,
  'ALTER TABLE semesters ADD CONSTRAINT fk_semesters_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE add_semesters_class_fk_statement FROM @add_semesters_class_fk_sql;
EXECUTE add_semesters_class_fk_statement;
DEALLOCATE PREPARE add_semesters_class_fk_statement;

SET @has_likes_semester_device_idx = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'favorites'
    AND index_name = 'idx_likes_semester_device'
);
SET @add_likes_semester_device_idx_sql = IF(
  @has_likes_semester_device_idx = 0,
  'ALTER TABLE favorites ADD KEY idx_likes_semester_device (semester_id, device_token)',
  'SELECT 1'
);
PREPARE add_likes_semester_device_idx_statement FROM @add_likes_semester_device_idx_sql;
EXECUTE add_likes_semester_device_idx_statement;
DEALLOCATE PREPARE add_likes_semester_device_idx_statement;

SET @has_new_likes_unique = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'favorites'
    AND index_name = 'uniq_like_device_project'
);
SET @add_new_likes_unique_sql = IF(
  @has_new_likes_unique = 0,
  'ALTER TABLE favorites ADD UNIQUE KEY uniq_like_device_project (semester_id, project_id, device_token)',
  'SELECT 1'
);
PREPARE add_new_likes_unique_statement FROM @add_new_likes_unique_sql;
EXECUTE add_new_likes_unique_statement;
DEALLOCATE PREPARE add_new_likes_unique_statement;

SET @has_old_likes_unique = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'favorites'
    AND index_name = 'uniq_favorite_device_per_semester'
);
SET @drop_old_likes_unique_sql = IF(
  @has_old_likes_unique > 0,
  'ALTER TABLE favorites DROP INDEX uniq_favorite_device_per_semester',
  'SELECT 1'
);
PREPARE drop_old_likes_unique_statement FROM @drop_old_likes_unique_sql;
EXECUTE drop_old_likes_unique_statement;
DEALLOCATE PREPARE drop_old_likes_unique_statement;

-- -----------------------------------------------------
-- Dummy seed data (safe to run multiple times)
-- -----------------------------------------------------

INSERT INTO site_settings (setting_key, setting_value)
SELECT 'site_logo_text', 'Student Projects'
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'site_logo_text');

INSERT INTO site_settings (setting_key, setting_value)
SELECT 'home_heading', 'Top-rated project stories across every semester.'
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'home_heading');

INSERT INTO class_settings (class_id, setting_key, setting_value)
SELECT @class_cgt390, 'site_logo_text', 'Student Projects'
WHERE @class_cgt390 IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM class_settings WHERE class_id = @class_cgt390 AND setting_key = 'site_logo_text'
  );

INSERT INTO class_settings (class_id, setting_key, setting_value)
SELECT @class_cgt390, 'home_heading', 'Top-rated project stories across every semester.'
WHERE @class_cgt390 IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM class_settings WHERE class_id = @class_cgt390 AND setting_key = 'home_heading'
  );

INSERT INTO vote_categories (class_id, name, slug, icon, display_order, is_primary, is_active)
SELECT c.id, 'Best Overall', 'best-overall', 'trophy', 1, 1, 1
FROM classes c
WHERE NOT EXISTS (
  SELECT 1
  FROM vote_categories vc
  WHERE vc.class_id = c.id AND vc.slug = 'best-overall'
);

INSERT INTO vote_categories (class_id, name, slug, icon, display_order, is_primary, is_active)
SELECT c.id, 'Most Creative', 'most-creative', 'palette', 2, 0, 1
FROM classes c
WHERE NOT EXISTS (
  SELECT 1
  FROM vote_categories vc
  WHERE vc.class_id = c.id AND vc.slug = 'most-creative'
);

INSERT INTO vote_categories (class_id, name, slug, icon, display_order, is_primary, is_active)
SELECT c.id, 'Best Technical Execution', 'best-technical-execution', 'gear', 3, 0, 1
FROM classes c
WHERE NOT EXISTS (
  SELECT 1
  FROM vote_categories vc
  WHERE vc.class_id = c.id AND vc.slug = 'best-technical-execution'
);

INSERT INTO vote_categories (class_id, name, slug, icon, display_order, is_primary, is_active)
SELECT c.id, 'Audience Choice', 'audience-choice', 'spark', 4, 0, 1
FROM classes c
WHERE NOT EXISTS (
  SELECT 1
  FROM vote_categories vc
  WHERE vc.class_id = c.id AND vc.slug = 'audience-choice'
);

INSERT INTO semesters (class_id, name, slug, starts_on, ends_on, is_current)
SELECT @class_cgt390, 'Fall 2024', 'fall-2024', '2024-08-15', '2024-12-15', 0
WHERE @class_cgt390 IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM semesters WHERE class_id = @class_cgt390 AND slug = 'fall-2024');

INSERT INTO semesters (class_id, name, slug, starts_on, ends_on, is_current)
SELECT @class_cgt390, 'Spring 2025', 'spring-2025', '2025-01-10', '2025-05-20', 0
WHERE @class_cgt390 IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM semesters WHERE class_id = @class_cgt390 AND slug = 'spring-2025');

INSERT INTO semesters (class_id, name, slug, starts_on, ends_on, is_current)
SELECT @class_cgt390, 'Fall 2025', 'fall-2025', '2025-08-18', '2025-12-18', 1
WHERE @class_cgt390 IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM semesters WHERE class_id = @class_cgt390 AND slug = 'fall-2025');

UPDATE semesters
SET is_current = CASE WHEN slug = 'fall-2025' THEN 1 ELSE 0 END
WHERE class_id = @class_cgt390
  AND slug IN ('fall-2024', 'spring-2025', 'fall-2025');

INSERT INTO users (name, email, password_hash, role, is_active)
SELECT 'Admin User', 'admin@studentprojects.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@studentprojects.local');

INSERT INTO users (class_id, name, email, password_hash, role, is_active)
SELECT @class_cgt390, 'Ava Manager', 'ava.manager@studentprojects.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'ava.manager@studentprojects.local');

INSERT INTO users (class_id, name, email, password_hash, role, is_active)
SELECT @class_cgt390, 'Ben Manager', 'ben.manager@studentprojects.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'ben.manager@studentprojects.local');

INSERT INTO users (class_id, name, email, password_hash, role, is_active)
SELECT @class_cgt390, 'Cora Manager', 'cora.manager@studentprojects.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'cora.manager@studentprojects.local');

SET @sem_fall_2024 = (SELECT id FROM semesters WHERE class_id = @class_cgt390 AND slug = 'fall-2024' LIMIT 1);
SET @sem_spring_2025 = (SELECT id FROM semesters WHERE class_id = @class_cgt390 AND slug = 'spring-2025' LIMIT 1);
SET @sem_fall_2025 = (SELECT id FROM semesters WHERE class_id = @class_cgt390 AND slug = 'fall-2025' LIMIT 1);

SET @mgr_ava = (SELECT id FROM users WHERE email = 'ava.manager@studentprojects.local' LIMIT 1);
SET @mgr_ben = (SELECT id FROM users WHERE email = 'ben.manager@studentprojects.local' LIMIT 1);
SET @mgr_cora = (SELECT id FROM users WHERE email = 'cora.manager@studentprojects.local' LIMIT 1);

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2024, @mgr_ava, 'Smart Attendance Scanner', 'smart-attendance-scanner', 'Mia Johnson', 'Face-assisted attendance with confidence scoring and weekly export reports.', '<p>A full stack attendance platform with in-class check-in, anomaly flags, and downloadable reports.</p>', 'https://images.unsplash.com/photo-1584697964358-3e14ca57658b?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/smart-attendance-scanner', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'smart-attendance-scanner');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2024, @mgr_ben, 'Campus Repair Tracker', 'campus-repair-tracker', 'Noah Patel', 'Students report facility issues and track maintenance status in real time.', '<p>Includes triage labels, location tagging, and status updates for each submitted ticket.</p>', 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/campus-repair-tracker', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'campus-repair-tracker');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2024, @mgr_cora, 'Peer Mentor Match', 'peer-mentor-match', 'Leah Kim', 'Recommendation engine matching first-years with peer mentors.', '<p>Combines profile preferences and availability windows for high quality matches.</p>', 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/peer-mentor-match', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'peer-mentor-match');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_spring_2025, @mgr_ava, 'Lab Safety Quiz Bot', 'lab-safety-quiz-bot', 'Ethan Rivera', 'Adaptive safety quizzes that focus on each student''s weak areas.', '<p>Automated quiz generation and personalized revision loops for lab compliance.</p>', 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/lab-safety-quiz-bot', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'lab-safety-quiz-bot');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_spring_2025, @mgr_ben, 'Internship Compass', 'internship-compass', 'Sophia Chen', 'Dashboard that maps applications, deadlines, and interview prep tasks.', '<p>Students can track recruiter pipelines and get reminders before deadlines.</p>', 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/internship-compass', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'internship-compass');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_spring_2025, @mgr_cora, 'Green Route Planner', 'green-route-planner', 'Lucas Brown', 'Low-emission route planning for walking, biking, and transit.', '<p>Optimizes travel paths with weather and carbon impact overlays.</p>', 'https://images.unsplash.com/photo-1475483768296-6163e08872a1?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/green-route-planner', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'green-route-planner');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_spring_2025, @mgr_ava, 'Study Pod Scheduler', 'study-pod-scheduler', 'Amelia Torres', 'Collaborative room booking with group availability sync.', '<p>Coordinates team schedules and auto-suggests best shared time slots.</p>', 'https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/study-pod-scheduler', 4, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'study-pod-scheduler');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2025, @mgr_ben, 'Campus Kitchen Connect', 'campus-kitchen-connect', 'Daniel Wright', 'Share surplus meal credits and reduce food waste in dining halls.', '<p>Provides meal swap listings and real-time redemption tracking.</p>', 'https://images.unsplash.com/photo-1498837167922-ddd27525d352?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/campus-kitchen-connect', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'campus-kitchen-connect');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2025, @mgr_cora, 'Hackathon Team Finder', 'hackathon-team-finder', 'Olivia Scott', 'Find teammates by skills, goals, and preferred build stack.', '<p>Supports team cards, invite requests, and project idea boards.</p>', 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/hackathon-team-finder', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'hackathon-team-finder');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2025, @mgr_ava, 'Lecture Snapshot AI', 'lecture-snapshot-ai', 'Henry Adams', 'Capture key lecture points and generate revision cards instantly.', '<p>Creates concise summaries with timestamped references to lecture moments.</p>', 'https://images.unsplash.com/photo-1513258496099-48168024aec0?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/lecture-snapshot-ai', 3, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'lecture-snapshot-ai');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2025, @mgr_ben, 'Career Story Builder', 'career-story-builder', 'Grace Lee', 'Portfolio storytelling tool tailored for internship and grad applications.', '<p>Students compose narrative-driven achievements with reusable templates.</p>', 'https://images.unsplash.com/photo-1484480974693-6ca0a78fb36b?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/career-story-builder', 4, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'career-story-builder');

INSERT INTO projects (semester_id, manager_user_id, title, slug, student_name, summary, description_html, image_url, external_url, sort_order, is_published)
SELECT @sem_fall_2025, @mgr_cora, 'Club Event Pulse', 'club-event-pulse', 'Isabella Martinez', 'Measure engagement for student club events with attendance heatmaps.', '<p>Combines RSVP, check-in, and post-event feedback into one scorecard.</p>', 'https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=1200&q=80', 'https://example.com/projects/club-event-pulse', 5, 1
WHERE NOT EXISTS (SELECT 1 FROM projects WHERE slug = 'club-event-pulse');

SET @proj_attendance = (SELECT id FROM projects WHERE slug = 'smart-attendance-scanner' LIMIT 1);
SET @proj_internship = (SELECT id FROM projects WHERE slug = 'internship-compass' LIMIT 1);
SET @proj_hackathon = (SELECT id FROM projects WHERE slug = 'hackathon-team-finder' LIMIT 1);

INSERT INTO favorites (semester_id, project_id, device_token)
SELECT @sem_fall_2024, @proj_attendance, 'demo-device-fall24-a'
WHERE NOT EXISTS (
  SELECT 1 FROM favorites
  WHERE semester_id = @sem_fall_2024 AND device_token = 'demo-device-fall24-a'
);

INSERT INTO favorites (semester_id, project_id, device_token)
SELECT @sem_spring_2025, @proj_internship, 'demo-device-spring25-a'
WHERE NOT EXISTS (
  SELECT 1 FROM favorites
  WHERE semester_id = @sem_spring_2025 AND device_token = 'demo-device-spring25-a'
);

INSERT INTO favorites (semester_id, project_id, device_token)
SELECT @sem_fall_2025, @proj_hackathon, 'demo-device-fall25-a'
WHERE NOT EXISTS (
  SELECT 1 FROM favorites
  WHERE semester_id = @sem_fall_2025 AND device_token = 'demo-device-fall25-a'
);
# Stimma - Databasinstallation

Detta dokument beskriver hur du ställer in databasen för Stimma-systemet.

## Förutsättningar

- MySQL/MariaDB-server (rekommenderad version: MariaDB 10.5+)
- phpMyAdmin eller annan MySQL-klient
- Databasadministratörsrättigheter

## Steg 1: Skapa databasen

1. Logga in i phpMyAdmin eller använd MySQL-kommandoraden
2. Skapa en ny databas som heter `stimma`:

```sql
CREATE DATABASE stimma CHARACTER SET utf8mb4 COLLATE utf8mb4_swedish_ci;
```

## Steg 2: Skapa en databasanvändare (valfritt men rekommenderat)

```sql
CREATE USER 'stimma_user'@'localhost' IDENTIFIED BY 'ditt_lösenord';
GRANT ALL PRIVILEGES ON stimma.* TO 'stimma_user'@'localhost';
FLUSH PRIVILEGES;
```

Ersätt `'ditt_lösenord'` med ett starkt lösenord.

## Steg 3: Importera databasen

Kör följande SQL-kod för att skapa tabellerna. Du kan kopiera och klistra in detta i phpMyAdmin eller köra det via kommandoraden:

```sql
-- Tabellstruktur `categories`
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `courses`
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty_level` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `duration_minutes` int(11) DEFAULT 0,
  `prerequisites` text DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `image_url` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `courses_ibfk_1` (`category_id`),
  KEY `courses_ibfk_2` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `lessons`
CREATE TABLE `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `estimated_duration` int(11) DEFAULT 5,
  `image_url` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `resource_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resource_links`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ai_instruction` text DEFAULT NULL,
  `ai_prompt` text DEFAULT NULL,
  `quiz_question` text DEFAULT NULL,
  `quiz_answer1` text DEFAULT NULL,
  `quiz_answer2` text DEFAULT NULL,
  `quiz_answer3` text DEFAULT NULL,
  `quiz_correct_answer` tinyint(4) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `lessons_ibfk_2` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `logs`
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `progress`
CREATE TABLE `progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `status` enum('not_started','completed') DEFAULT 'not_started',
  `started_at` timestamp NULL DEFAULT NULL,
  `completion_time` int(11) DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `score` int(11) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `resources`
CREATE TABLE `resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `type` enum('link','file','embed') DEFAULT 'link',
  `lesson_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `course_id` (`course_id`),
  KEY `author_id` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0,
  `is_editor` tinyint(1) DEFAULT 0,
  `role` enum('student','teacher','admin','super_admin') DEFAULT 'student',
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `course_editors`
CREATE TABLE `course_editors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_editor` (`course_id`,`email`),
  KEY `idx_email` (`email`),
  CONSTRAINT `course_editors_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `user_progress`
CREATE TABLE `user_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `status` enum('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  `last_accessed` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_lesson` (`user_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `user_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Tabellstruktur `activity_log`
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(150) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci;

-- Skapa relationer/främmande nycklar
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessons_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resources_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resources_ibfk_3` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
```

## Steg 4: Verifiera installationen

Kontrollera att tabellerna har skapats korrekt genom att fråga databasen:

```sql
SHOW TABLES FROM stimma;
```

Du bör se följande tabeller:
- categories
- courses
- lessons
- logs
- progress
- resources
- users
- course_editors
- user_progress
- activity_log

## Steg 5: Uppdatera konfigurationen

Uppdatera dina databasanslutningsinställningar i systemets konfigurationsfil (vanligtvis `config.php`):

```php
// Databasinställningar
define('DB_HOST', 'localhost');
define('DB_NAME', 'stimma');
define('DB_USER', 'stimma_user');
define('DB_PASS', 'ditt_lösenord');
```

Ersätt värdena ovan med dina faktiska databasuppgifter.

## Felsökning

Om du stöter på problem vid installation:

1. **Kontrollera rättigheter**: Se till att databasanvändaren har tillräckliga rättigheter.
2. **Teckenkodning**: Kontrollera att databasen använder UTF8MB4-teckenkodning.
3. **MySQL-version**: Se till att du använder MySQL 5.7+ eller MariaDB 10.5+.
4. **JSON-stöd**: Databasen använder JSON-fält som kräver stöd i din MySQL/MariaDB-version.

## Säkerhetsåtgärder

- Använd alltid ett starkt lösenord för databasanvändaren
- Begränsa användaren till endast `stimma`-databasen
- Överväg att använda en brandvägg för att begränsa åtkomst till databasen
- Gör regelbundna säkerhetskopior av databasen

---

Om du har frågor eller stöter på problem, kontakta systemadministratören.
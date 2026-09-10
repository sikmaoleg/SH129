-- =====================================================================
--  Молодая Гвардия · Щёлково — структура базы данных
--  MySQL 5.7+ / MariaDB 10.3+
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Пользователи
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`          VARCHAR(190) NOT NULL,
  `password_hash`  VARCHAR(255) NOT NULL,
  `last_name`      VARCHAR(80)  NOT NULL,
  `first_name`     VARCHAR(80)  NOT NULL,
  `middle_name`    VARCHAR(80)      NULL,
  `phone`          VARCHAR(32)      NULL,
  `birth_date`     DATE             NULL,
  `vk`             VARCHAR(190)     NULL,
  `telegram`       VARCHAR(190)     NULL,
  `school`         VARCHAR(190)     NULL,
  `about`          TEXT             NULL,
  `avatar`         VARCHAR(190)     NULL,
  `role`           ENUM('volunteer','admin','dev') NOT NULL DEFAULT 'volunteer',
  `status`         ENUM('pending','approved','rejected','blocked') NOT NULL DEFAULT 'pending',
  `points`         INT NOT NULL DEFAULT 0,
  `hours`          DECIMAL(7,1) NOT NULL DEFAULT 0,
  `reject_reason`  VARCHAR(255)     NULL,
  `approved_by`    INT UNSIGNED     NULL,
  `approved_at`    DATETIME         NULL,
  `last_login_at`  DATETIME         NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_role` (`role`),
  KEY `idx_points` (`points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Направления работы
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `directions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(120) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255)     NULL,
  `sort`        INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Мероприятия
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(190) NOT NULL,
  `description`   TEXT             NULL,
  `direction_id`  INT UNSIGNED     NULL,
  `starts_at`     DATETIME     NOT NULL,
  `location`      VARCHAR(190)     NULL,
  `capacity`      INT NOT NULL DEFAULT 0,
  `points_reward` INT NOT NULL DEFAULT 10,
  `cover`         VARCHAR(190)     NULL,
  `status`        ENUM('draft','published','finished','cancelled') NOT NULL DEFAULT 'published',
  `created_by`    INT UNSIGNED     NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_starts` (`starts_at`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_events_direction` FOREIGN KEY (`direction_id`)
      REFERENCES `directions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Записи волонтёров на мероприятия
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_registrations` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`       INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `status`         ENUM('registered','attended','no_show','cancelled') NOT NULL DEFAULT 'registered',
  `hours`          DECIMAL(5,1) NOT NULL DEFAULT 0,
  `points_awarded` INT NOT NULL DEFAULT 0,
  `comment`        VARCHAR(255)     NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_user` (`event_id`,`user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_reg_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reg_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- История начисления очков
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `point_transactions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `points`     INT NOT NULL,
  `reason`     VARCHAR(190) NOT NULL,
  `event_id`   INT UNSIGNED     NULL,
  `created_by` INT UNSIGNED     NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`created_at`),
  CONSTRAINT `fk_pt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Бейджи
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `badges` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(60)  NOT NULL,
  `title`       VARCHAR(120) NOT NULL,
  `description` VARCHAR(255)     NULL,
  `icon`        VARCHAR(16)      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_badges` (
  `user_id`    INT UNSIGNED NOT NULL,
  `badge_id`   INT UNSIGNED NOT NULL,
  `awarded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `awarded_by` INT UNSIGNED NULL,
  PRIMARY KEY (`user_id`,`badge_id`),
  CONSTRAINT `fk_ub_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ub_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Новости
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `news` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`        VARCHAR(190) NOT NULL,
  `excerpt`      VARCHAR(400)     NULL,
  `body`         MEDIUMTEXT       NULL,
  `cover`        VARCHAR(190)     NULL,
  `status`       ENUM('draft','published') NOT NULL DEFAULT 'published',
  `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author_id`    INT UNSIGNED     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pub` (`status`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Настройки (правит разработчик)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key`   VARCHAR(80) NOT NULL,
  `value` TEXT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Журнал действий
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED     NULL,
  `action`     VARCHAR(80)  NOT NULL,
  `entity`     VARCHAR(60)      NULL,
  `entity_id`  INT UNSIGNED     NULL,
  `meta`       VARCHAR(500)     NULL,
  `ip`         VARCHAR(45)      NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  Начальные данные
-- =====================================================================

INSERT IGNORE INTO `directions` (`title`,`slug`,`description`,`sort`) VALUES
('Патриотические проекты','patriot','Памятные даты, шествия, работа с ветеранами',1),
('Помощь людям','help','Адресная помощь, забота о старшем поколении, донорство',2),
('Экология','eco','Субботники, озеленение, экологические акции',3),
('МедиаГвардия','media','Съёмка, монтаж, соцсети и дизайн',4),
('Спорт и ЗОЖ','sport','Турниры, забеги, массовые зарядки',5),
('Школа лидерства','school','Тренинги и обучение волонтёров',6);

INSERT IGNORE INTO `badges` (`code`,`title`,`description`,`icon`) VALUES
('first_step','Первый шаг','Участие в первом мероприятии','1'),
('marathon','Марафонец','10 мероприятий подряд','10'),
('universal','Универсал','Участие в 5 разных направлениях','5'),
('mentor','Наставник','Помог освоиться трём новичкам','N'),
('night','Ночная смена','Участие в срочной или ночной задаче','24'),
('month','Волонтёр месяца','Первое место в месячном рейтинге','1');

INSERT IGNORE INTO `settings` (`key`,`value`) VALUES
('org_name','Молодая Гвардия · Щёлково'),
('org_email','info@example.ru'),
('org_address','Московская область, г. Щёлково'),
('org_vk',''),
('org_tg',''),
('points_per_hour','10'),
('points_coordinator','50'),
('registration_open','1'),
('level_thresholds','0,100,300,700,1500'),
('level_names','Новичок,Активист,Опытный,Наставник,Легенда'),
('stat_volunteers','0'),
('stat_events','0'),
('stat_hours','0');

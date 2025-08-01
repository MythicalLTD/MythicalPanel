CREATE TABLE
	IF NOT EXISTS `mythicalpanel_users` (
		`id` INT NOT NULL AUTO_INCREMENT,
		`username` VARCHAR(64) NOT NULL,
		`first_name` VARCHAR(64) NOT NULL,
		`last_name` VARCHAR(64) NOT NULL,
		`email` VARCHAR(255) NOT NULL,
		`role` INT NOT NULL DEFAULT '1',
		`external_id` INT NULL DEFAULT NULL,
		`password` VARCHAR(255) NOT NULL,
		`remember_token` VARCHAR(255) NOT NULL,
		`avatar` VARCHAR(255) NOT NULL DEFAULT 'https://github.com/mythicalltd.png',
		`uuid` CHAR(36) NOT NULL,
		`first_ip` VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
		`last_ip` VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
		`banned` ENUM ('false', 'true') NOT NULL DEFAULT 'false',
		`two_fa_enabled` ENUM ('false', 'true') NOT NULL DEFAULT 'false',
		`two_fa_key` VARCHAR(255) NULL DEFAULT NULL,
		`two_fa_blocked` ENUM ('false', 'true') NOT NULL DEFAULT 'false',
		`deleted` ENUM ('false', 'true') NOT NULL DEFAULT 'false',
		`locked` ENUM ('false', 'true') NOT NULL DEFAULT 'false',
		`last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE (`email`),
		UNIQUE (`username`),
		UNIQUE (`uuid`)
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;
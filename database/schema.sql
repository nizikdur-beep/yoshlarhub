CREATE DATABASE IF NOT EXISTS yoshlarhub
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE yoshlarhub;

CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NULL,
    region VARCHAR(100) NULL,
    age TINYINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_region (region)
);

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    emoji VARCHAR(10) NULL
);

CREATE TABLE interests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE user_interests (
    user_id BIGINT UNSIGNED NOT NULL,
    interest_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, interest_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE
);

CREATE TABLE opportunities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    region VARCHAR(100) NULL,
    organizer VARCHAR(255) NULL,
    url VARCHAR(1000) NULL,
    deadline DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_category (category_id),
    INDEX idx_region (region),
    INDEX idx_deadline (deadline),
    INDEX idx_active (is_active)
);

CREATE TABLE bookmarks (
    user_id BIGINT UNSIGNED NOT NULL,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, opportunity_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (opportunity_id) REFERENCES opportunities(id) ON DELETE CASCADE
);

INSERT INTO categories (name, emoji) VALUES
('Grantlar', '🎓'),
('Tanlovlar', '🏆'),
('Stajirovkalar', '💼'),
('Volontyorlik', '🤝'),
('Kurslar', '📚'),
('Startaplar', '🚀'),
('Olimpiadalar', '🧠');

INSERT INTO interests (name) VALUES
('IT'),
('AI'),
('Biznes'),
('Ingliz tili'),
('Taʼlim'),
('Fan'),
('Media'),
('Dizayn'),
('Startup'),
('Volontyorlik');

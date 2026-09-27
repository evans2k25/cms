CREATE DATABASE IF NOT EXISTS journal_cms
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE journal_cms;

-- =========================
-- UTILISATEURS
-- =========================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM(
        'super_admin',
        'admin',
        'editor',
        'journalist',
        'writer',
        'moderator'
    ) NOT NULL DEFAULT 'writer',
    status ENUM('active', 'inactive', 'blocked')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- CATEGORIES
-- =========================

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- ARTICLES
-- =========================

CREATE TABLE articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,

    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,

    excerpt TEXT DEFAULT NULL,
    content LONGTEXT NOT NULL,

    featured_image VARCHAR(255) DEFAULT NULL,

    status ENUM(
        'draft',
        'pending',
        'published',
        'scheduled',
        'archived'
    ) NOT NULL DEFAULT 'draft',

    featured TINYINT(1) NOT NULL DEFAULT 0,

    published_at DATETIME DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,

    views BIGINT UNSIGNED NOT NULL DEFAULT 0,

    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    meta_keywords VARCHAR(500) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_articles_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_articles_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE SET NULL
);

-- =========================
-- TAGS
-- =========================

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- RELATION ARTICLES / TAGS
-- =========================

CREATE TABLE article_tags (
    article_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (article_id, tag_id),

    CONSTRAINT fk_article_tags_article
        FOREIGN KEY (article_id)
        REFERENCES articles(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_article_tags_tag
        FOREIGN KEY (tag_id)
        REFERENCES tags(id)
        ON DELETE CASCADE
);

-- =========================
-- MEDIAS
-- =========================

CREATE TABLE media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,

    alt_text VARCHAR(255) DEFAULT NULL,
    caption TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_media_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

-- =========================
-- COMMENTAIRES
-- =========================

CREATE TABLE comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    article_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,

    status ENUM(
        'pending',
        'approved',
        'rejected',
        'spam'
    ) NOT NULL DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_comments_article
        FOREIGN KEY (article_id)
        REFERENCES articles(id)
        ON DELETE CASCADE
);

-- =========================
-- PAGES
-- =========================

CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,

    status ENUM('draft', 'published')
        NOT NULL DEFAULT 'draft',

    published_at DATETIME DEFAULT NULL,

    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_pages_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

-- =========================
-- PARAMETRES DU JOURNAL
-- =========================

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- STATISTIQUES DES ARTICLES
-- =========================

CREATE TABLE article_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    article_id BIGINT UNSIGNED NOT NULL,

    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,

    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_article_views_article
        FOREIGN KEY (article_id)
        REFERENCES articles(id)
        ON DELETE CASCADE
);

-- =========================
-- INDEX
-- =========================

CREATE INDEX idx_articles_status
ON articles(status);

CREATE INDEX idx_articles_published
ON articles(published_at);

CREATE INDEX idx_articles_category
ON articles(category_id);

CREATE INDEX idx_articles_user
ON articles(user_id);

CREATE INDEX idx_comments_status
ON comments(status);

CREATE INDEX idx_views_article
ON article_views(article_id);

-- =========================
-- CATEGORIES DE BASE
-- =========================

INSERT INTO categories (name, slug, description) VALUES
('Actualité', 'actualite', 'Les dernières actualités'),
('Politique', 'politique', 'Actualités politiques'),
('Économie', 'economie', 'Économie et finances'),
('Société', 'societe', 'Société et faits de société'),
('Technologie', 'technologie', 'Technologie et innovation'),
('Sport', 'sport', 'Actualités sportives'),
('Culture', 'culture', 'Culture, musique et arts'),
('International', 'international', 'Actualités internationales');

-- =========================
-- PARAMETRES INITIAUX
-- =========================

INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'Mon Journal'),
('site_description', 'Votre source d’information'),
('site_email', 'contact@monjournal.com'),
('posts_per_page', '10'),
('allow_comments', '1'),
('timezone', 'Africa/Abidjan');
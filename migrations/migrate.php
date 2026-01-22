<?php

/**
 * Database Migration Script
 * 
 * Run: php migrations/migrate.php
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/constants.php';

$config = require dirname(__DIR__) . '/config/config.php';
$dbConfig = $config['database'];

try {
    // Connect without database name first
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port']);
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbConfig['name']}`");
    
    echo "✓ Connected to database: {$dbConfig['name']}\n";
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Migration SQL
$migrations = [
    '001_create_users_table' => "
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role ENUM('super_admin') DEFAULT 'super_admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '002_create_seasonal_events_table' => "
        CREATE TABLE IF NOT EXISTS seasonal_events (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            priority INT DEFAULT 5,
            content_themes JSON,
            keywords JSON,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dates (start_date, end_date),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '003_create_wp_categories_table' => "
        CREATE TABLE IF NOT EXISTS wp_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            wp_category_id INT NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            parent_id INT NULL,
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wp_id (wp_category_id),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '004_create_wp_authors_table' => "
        CREATE TABLE IF NOT EXISTS wp_authors (
            id INT PRIMARY KEY AUTO_INCREMENT,
            wp_user_id INT NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wp_id (wp_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '005_create_wp_products_table' => "
        CREATE TABLE IF NOT EXISTS wp_products (
            id INT PRIMARY KEY AUTO_INCREMENT,
            wc_product_id INT NOT NULL UNIQUE,
            title VARCHAR(500) NOT NULL,
            description TEXT,
            short_description TEXT,
            price DECIMAL(10,2),
            regular_price DECIMAL(10,2),
            sale_price DECIMAL(10,2),
            stock_status ENUM('instock', 'outofstock', 'onbackorder') DEFAULT 'instock',
            brand_slug VARCHAR(100),
            brand_name VARCHAR(255),
            category_slugs JSON,
            category_names JSON,
            tags JSON,
            image_url VARCHAR(500),
            permalink VARCHAR(500),
            sku VARCHAR(100),
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wc_id (wc_product_id),
            INDEX idx_brand (brand_slug),
            INDEX idx_stock (stock_status),
            FULLTEXT idx_search (title, description, short_description)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '006_create_wp_page_blocks_table' => "
        CREATE TABLE IF NOT EXISTS wp_page_blocks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            wp_block_id INT NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            category_slug VARCHAR(100),
            category_name VARCHAR(255),
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wp_id (wp_block_id),
            INDEX idx_category (category_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '007_create_posts_table' => "
        CREATE TABLE IF NOT EXISTS posts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(500) NOT NULL,
            slug VARCHAR(500),
            intro_content TEXT,
            outro_content TEXT,
            content_json JSON,
            wp_content LONGTEXT,
            meta_description VARCHAR(160),
            status ENUM('idea', 'draft', 'review', 'scheduled', 'published', 'archived') DEFAULT 'idea',
            wp_category_id INT,
            wp_author_id INT,
            seasonal_event_id INT,
            scheduled_date DATE,
            scheduled_time TIME DEFAULT '09:00:00',
            published_date DATETIME,
            wp_post_id INT,
            ai_generation_log JSON,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_date),
            INDEX idx_wp_post (wp_post_id),
            INDEX idx_event (seasonal_event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '008_create_post_sections_table' => "
        CREATE TABLE IF NOT EXISTS post_sections (
            id INT PRIMARY KEY AUTO_INCREMENT,
            post_id INT NOT NULL,
            section_index INT NOT NULL,
            heading VARCHAR(255) NOT NULL,
            content TEXT,
            cta_text VARCHAR(100),
            cta_url VARCHAR(500),
            carousel_brand_slug VARCHAR(100),
            carousel_category_slug VARCHAR(100),
            carousel_taxonomy_filter JSON,
            fallback_block_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_post_section (post_id, section_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '009_create_post_products_table' => "
        CREATE TABLE IF NOT EXISTS post_products (
            id INT PRIMARY KEY AUTO_INCREMENT,
            post_id INT NOT NULL,
            section_id INT NOT NULL,
            wc_product_id INT NOT NULL,
            product_title VARCHAR(500),
            display_order INT DEFAULT 0,
            is_ai_suggested BOOLEAN DEFAULT FALSE,
            is_manually_added BOOLEAN DEFAULT FALSE,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_post (post_id),
            INDEX idx_section (section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '010_create_brand_voice_table' => "
        CREATE TABLE IF NOT EXISTS brand_voice (
            id INT PRIMARY KEY AUTO_INCREMENT,
            attribute VARCHAR(100) NOT NULL,
            description TEXT,
            examples JSON,
            weight INT DEFAULT 5,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '011_create_activity_log_table' => "
        CREATE TABLE IF NOT EXISTS activity_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            details_json JSON,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '012_create_email_logs_table' => "
        CREATE TABLE IF NOT EXISTS email_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            type VARCHAR(50) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            body TEXT,
            status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
            error_message TEXT,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '013_create_brainstorm_ideas_table' => "
        CREATE TABLE IF NOT EXISTS brainstorm_ideas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            suggested_event_id INT,
            suggested_products JSON,
            ai_expanded_notes JSON,
            status ENUM('new', 'approved', 'converted', 'rejected') DEFAULT 'new',
            converted_post_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '014_create_settings_table' => "
        CREATE TABLE IF NOT EXISTS settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value JSON,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '015_create_sessions_table' => "
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            payload TEXT,
            last_activity INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    '016_add_foreign_keys' => "
        -- Add foreign keys (separate to avoid circular dependency issues)
        ALTER TABLE posts 
            ADD CONSTRAINT fk_posts_event FOREIGN KEY (seasonal_event_id) REFERENCES seasonal_events(id) ON DELETE SET NULL,
            ADD CONSTRAINT fk_posts_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
        
        ALTER TABLE post_sections 
            ADD CONSTRAINT fk_sections_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE;
        
        ALTER TABLE post_products 
            ADD CONSTRAINT fk_products_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_products_section FOREIGN KEY (section_id) REFERENCES post_sections(id) ON DELETE CASCADE;
        
        ALTER TABLE brainstorm_ideas 
            ADD CONSTRAINT fk_ideas_event FOREIGN KEY (suggested_event_id) REFERENCES seasonal_events(id) ON DELETE SET NULL,
            ADD CONSTRAINT fk_ideas_post FOREIGN KEY (converted_post_id) REFERENCES posts(id) ON DELETE SET NULL;
        
        ALTER TABLE activity_log 
            ADD CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
    "
];

// Seed data
$seeds = [
    '100_seed_seasonal_events' => "
        INSERT IGNORE INTO seasonal_events (name, slug, start_date, end_date, priority, content_themes, keywords) VALUES
        ('New Year Sales', 'new-year-sales', '2025-01-01', '2025-01-15', 8, 
         '[\"new year new wardrobe\", \"winter sale picks\", \"fresh start fashion\"]', 
         '[\"sale\", \"new year\", \"wardrobe refresh\", \"january\"]'),
        ('Valentine\\'s Day', 'valentines-day', '2025-02-01', '2025-02-14', 7,
         '[\"date night outfits\", \"romantic style\", \"gifts for her\"]',
         '[\"valentine\", \"date night\", \"romantic\", \"love\", \"gift\"]'),
        ('Spring Preview', 'spring-preview', '2025-02-15', '2025-03-15', 6,
         '[\"transitional dressing\", \"spring trends\", \"lighter layers\"]',
         '[\"spring\", \"transitional\", \"new season\", \"fresh\"]'),
        ('Mother\\'s Day', 'mothers-day', '2025-03-01', '2025-03-30', 7,
         '[\"gifts for mum\", \"mother daughter style\", \"timeless pieces\"]',
         '[\"mother\", \"mum\", \"gift\", \"timeless\"]'),
        ('Easter', 'easter', '2025-04-01', '2025-04-21', 6,
         '[\"easter brunch outfits\", \"spring occasion wear\", \"pastel styling\"]',
         '[\"easter\", \"spring\", \"brunch\", \"occasion\"]'),
        ('Summer Holiday Prep', 'summer-holiday', '2025-05-01', '2025-07-31', 8,
         '[\"holiday wardrobe\", \"resort wear\", \"summer essentials\", \"beach to bar\"]',
         '[\"holiday\", \"summer\", \"vacation\", \"resort\", \"beach\", \"travel\"]'),
        ('Wedding Season', 'wedding-season', '2025-05-01', '2025-09-30', 7,
         '[\"wedding guest outfits\", \"occasion dresses\", \"summer events\"]',
         '[\"wedding\", \"guest\", \"occasion\", \"dress\", \"event\"]'),
        ('Back to Reality', 'back-to-reality', '2025-09-01', '2025-09-30', 6,
         '[\"autumn transition\", \"back to work style\", \"new season wardrobe\"]',
         '[\"autumn\", \"fall\", \"work\", \"transition\", \"new season\"]'),
        ('Autumn/Winter Preview', 'aw-preview', '2025-09-15', '2025-10-31', 7,
         '[\"autumn trends\", \"knitwear guide\", \"layering\", \"coats\"]',
         '[\"autumn\", \"winter\", \"knitwear\", \"layers\", \"coats\"]'),
        ('Black Friday', 'black-friday', '2025-11-20', '2025-12-02', 10,
         '[\"black friday picks\", \"sale shopping guide\", \"wishlist worthy\"]',
         '[\"black friday\", \"sale\", \"discount\", \"deals\", \"wishlist\"]'),
        ('Christmas Gift Guide', 'christmas-gifts', '2025-11-15', '2025-12-24', 10,
         '[\"gift guide\", \"stocking fillers\", \"luxury gifts\", \"last minute gifts\"]',
         '[\"christmas\", \"gift\", \"present\", \"stocking\", \"holiday\"]'),
        ('Christmas Party', 'christmas-party', '2025-11-20', '2025-12-31', 9,
         '[\"party outfits\", \"festive dressing\", \"sparkle and shine\"]',
         '[\"party\", \"festive\", \"christmas\", \"new year\", \"sparkle\"]'),
        ('Winter Sun', 'winter-sun', '2025-10-01', '2026-02-28', 7,
         '[\"winter getaway\", \"warm weather escape\", \"holiday packing\"]',
         '[\"winter sun\", \"holiday\", \"escape\", \"warm\", \"travel\"]')
    ",
    
    '101_seed_brand_voice' => "
        INSERT IGNORE INTO brand_voice (attribute, description, examples, weight, is_active) VALUES
        ('Girls at lunch', 'Like chatting with your most stylish friend. Warm, conversational, never preachy.', 
         '[\"Think of it as...\", \"You know that feeling when...\", \"We are obsessed with...\"]', 10, TRUE),
        ('Confident', 'Knows what looks good. Makes bold recommendations without being pushy.',
         '[\"This is THE dress for...\", \"Trust us on this one\", \"An absolute must\"]', 9, TRUE),
        ('Effortless', 'Style should feel easy, not try-hard. Relaxed but considered.',
         '[\"Throw it on with...\", \"Works with everything\", \"No effort required\"]', 9, TRUE),
        ('Relatable', 'Understands real life. School runs, zoom calls, nights out, hangovers.',
         '[\"We have all been there\", \"For those days when...\", \"Because sometimes you just need...\"]', 8, TRUE),
        ('Inclusive', 'Fashion is for everyone. No gatekeeping, no judgement.',
         '[\"Whatever your style\", \"Works for every occasion\", \"No rules here\"]', 7, TRUE),
        ('Direct', 'Gets to the point. No waffle, no jargon, no fashion speak.',
         '[\"Here is the thing:\", \"Simply put:\", \"Bottom line:\"]', 8, TRUE),
        ('Simple', 'Clear language. Short sentences. Easy to scan.',
         '[\"Clean lines.\", \"Easy styling.\", \"Done.\"]', 7, TRUE),
        ('Real', 'Honest recommendations. Would genuinely wear/buy this.',
         '[\"Honestly?\", \"Real talk:\", \"We actually own this\"]', 8, TRUE),
        ('Witty', 'Clever but not trying too hard. A knowing smile, not a belly laugh.',
         '[\"(your future wardrobe staple)\", \"spoiler: you will want both\", \"...just saying\"]', 6, TRUE),
        ('Dry', 'British humour. Understated. The kind that makes you smirk.',
         '[\"Obviously.\", \"Because of course.\", \"Groundbreaking, we know.\"]', 5, TRUE)
    ",
    
    '102_seed_settings' => "
        INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
        ('divider_image_id', '57585'),
        ('posts_per_month_target', '6'),
        ('default_publish_time', '\"09:00:00\"'),
        ('schedule_buffer_days', '3'),
        ('setup_complete', 'false')
    "
];

// Run migrations
echo "\n📦 Running migrations...\n\n";

foreach ($migrations as $name => $sql) {
    try {
        // Skip foreign keys if they already exist
        if (strpos($name, 'foreign_keys') !== false) {
            // Check if constraints exist first
            $result = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = '{$dbConfig['name']}' AND CONSTRAINT_NAME = 'fk_posts_event'")->fetchColumn();
            if ($result > 0) {
                echo "⏭️  Skipping {$name} (already exists)\n";
                continue;
            }
        }
        
        $pdo->exec($sql);
        echo "✓ {$name}\n";
    } catch (PDOException $e) {
        // Ignore duplicate errors for idempotent migrations
        if (strpos($e->getMessage(), 'Duplicate') === false && 
            strpos($e->getMessage(), 'already exists') === false) {
            echo "✗ {$name}: " . $e->getMessage() . "\n";
        } else {
            echo "⏭️  {$name} (already exists)\n";
        }
    }
}

// Run seeds
echo "\n🌱 Running seeds...\n\n";

foreach ($seeds as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ {$name}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "✗ {$name}: " . $e->getMessage() . "\n";
        } else {
            echo "⏭️  {$name} (data exists)\n";
        }
    }
}

echo "\n✅ Migration complete!\n\n";

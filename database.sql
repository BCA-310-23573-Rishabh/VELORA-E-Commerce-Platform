-- ============================================================
-- VELORA Database Schema (Final - INR, India, GST)
-- Run this in phpMyAdmin: select velora_db → Import → choose this file
-- ============================================================

DROP DATABASE IF EXISTS velora_db;
CREATE DATABASE velora_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE velora_db;

-- ── USERS ────────────────────────────────────────────────────────────
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    phone       VARCHAR(15),
    password    VARCHAR(255) NOT NULL,
    is_admin    TINYINT(1)   DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── PRODUCTS ─────────────────────────────────────────────────────────
CREATE TABLE products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    gst_rate     DECIMAL(5,2) DEFAULT 12.00,   -- GST % e.g. 5, 12, 18, 28
    category     ENUM('essential','denim','outerwear','linen','accessories','shirts','tshirts','jeans','trousers','cargo-pants','shoes','overshirt','plus-size','shorts') NOT NULL,
    subcategory  VARCHAR(100),
    image        VARCHAR(500),
    hover_image  VARCHAR(500),
    badge        VARCHAR(50),
    stock        INT          DEFAULT 10,
    sizes        VARCHAR(255),
    color        VARCHAR(100),
    is_active    TINYINT(1)   DEFAULT 1,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── GST RATE PRESETS (for the admin GST management tab) ──────────────
CREATE TABLE gst_rates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    rate        DECIMAL(5,2) NOT NULL,
    description VARCHAR(255),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── ORDERS ───────────────────────────────────────────────────────────
CREATE TABLE orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    order_number     VARCHAR(20) NOT NULL UNIQUE,
    user_id          INT NULL,
    customer_name    VARCHAR(255),
    customer_email   VARCHAR(255),
    customer_phone   VARCHAR(15),
    shipping_address TEXT,
    shipping_city    VARCHAR(100),
    shipping_state   VARCHAR(100),
    shipping_pincode VARCHAR(10),
    shipping_country VARCHAR(100) DEFAULT 'India',
    payment_method   VARCHAR(50)  DEFAULT 'upi',
    subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
    gst_amount       DECIMAL(10,2) DEFAULT 0,
    discount         DECIMAL(10,2) DEFAULT 0,
    promo_code       VARCHAR(50),
    shipping_cost    DECIMAL(10,2) DEFAULT 0,
    total            DECIMAL(10,2) NOT NULL DEFAULT 0,
    status           ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── ORDER ITEMS ──────────────────────────────────────────────────────
CREATE TABLE order_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     INT NOT NULL,
    product_id   INT NULL,
    product_name VARCHAR(255) NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    gst_rate     DECIMAL(5,2)  DEFAULT 0,
    gst_amount   DECIMAL(10,2) DEFAULT 0,
    quantity     INT NOT NULL   DEFAULT 1,
    size         VARCHAR(20),
    color        VARCHAR(50),
    item_total   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── CONTACT MESSAGES ─────────────────────────────────────────────────
CREATE TABLE contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    phone      VARCHAR(15),
    topic      VARCHAR(100),
    message    TEXT NOT NULL,
    is_read    TINYINT(1)   DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── NEWSLETTER ───────────────────────────────────────────────────────
CREATE TABLE newsletter_subscribers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL UNIQUE,
    subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── SAVED ADDRESSES ──────────────────────────────────────────────────
CREATE TABLE user_addresses (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    label        VARCHAR(50)  DEFAULT 'Home',
    full_name    VARCHAR(200),
    address      VARCHAR(300),
    city         VARCHAR(100),
    state        VARCHAR(100),
    pincode      VARCHAR(10),
    country      VARCHAR(100) DEFAULT 'India',
    phone        VARCHAR(15),
    is_default   TINYINT(1)   DEFAULT 0,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── SEED: GST RATE PRESETS ───────────────────────────────────────────
INSERT INTO gst_rates (label, rate, description) VALUES
('Essential (5%)',    5.00,  'Basic necessities — food, books, medicines'),
('Standard (12%)',   12.00,  'Clothing and apparel (default for VELORA products)'),
('Standard (18%)',   18.00,  'Most manufactured goods'),
('Luxury (28%)',     28.00,  'Luxury and premium items'),
('Zero Rated (0%)',   0.00,  'Exempt goods');

-- ── SEED: PRODUCTS (prices in INR, GST 12% for apparel) ──────────────
INSERT INTO products (id, name, price, gst_rate, category, subcategory, image, hover_image, badge, stock, sizes, color) VALUES
-- Essential Wear
(101,'pocket oversized tee',   1299.00, 12.00,'essential','tees',        'Images/Essential/pocket_oversized_tee.jpg',  '/Images/Essential/pocket_oversized_tee.jpg',  'best seller',15,'S,M,L,XL',      'black'),
(102,'heavyweight fleece',     2499.00, 12.00,'essential','sweatshirts', '/Images/Essential/heavyweight fleece.jpg',    '/Images/Essential/heavyweight fleece.jpg',    NULL,          8,'M,L,XL,XXL',    'gray'),
(103,'slub jersey crew',       1699.00, 12.00,'essential','tees',        '/Images/Essential/slub jersey crew.jpg',      '/Images/Essential/slub jersey crew.jpg',      NULL,         12,'XS,S,M,L',       'white'),
(104,'vintage wash hoodie',    2799.00, 12.00,'essential','sweatshirts', '/Images/Essential/vintage wash hoodie.jpg',   '/Images/Essential/vintage wash hoodie.jpg',   'new',        10,'S,M,L,XL',      'brown'),
(105,'organic cotton tee',     1199.00, 12.00,'essential','tees',        '/Images/Essential/organic cotton tee.webp',   '/Images/Essential/organic cotton tee.webp',   NULL,         20,'XS,S,M,L,XL',  'black'),
(106,'relaxed sweatpants',     2199.00, 12.00,'essential','bottoms',     '/Images/Essential/relaxed sweatpants.jpeg',   '/Images/Essential/relaxed sweatpants.jpeg',   NULL,         14,'S,M,L,XL',      'gray'),
(107,'ribbed tank top',         999.00, 12.00,'essential','tees',        '/Images/Essential/ribbed tank top.jpg',       '/Images/Essential/ribbed tank top.jpg',       NULL,         18,'XS,S,M,L',       'white'),
(108,'hooded zip up',          2999.00, 12.00,'essential','sweatshirts', '/Images/Essential/hooded zip up.jpeg',        '/Images/Essential/hooded zip up.jpeg',        'sale',        6,'S,M,L,XL',      'black'),
-- Denim
(201,'relaxed taper jean',     3199.00, 12.00,'denim',    'jeans',       '/Images/Denim/relaxed taper jean.jpeg',       '/Images/Denim/relaxed taper jean.jpeg',       NULL,          5,'30,32,34,36',   'blue'),
(202,'selvedge straight fit',  4199.00, 12.00,'denim',    'jeans',       '/Images/Denim/selvedge straight fit.avif',    '/Images/Denim/selvedge straight fit.avif',    'premium',     7,'30,32,34,36,38','blue'),
(203,'washed black denim',     3399.00, 12.00,'denim',    'jeans',       '/Images/Denim/washed black denim.jpeg',       '/Images/Denim/washed black denim.jpeg',       NULL,          9,'28,30,32,34',   'black'),
(204,'denim trucker jacket',   4799.00, 12.00,'denim',    'jackets',     '/Images/Denim/denim trucker jacket.webp',     '/Images/Denim/denim trucker jacket.webp',     'new',         4,'S,M,L,XL',      'blue'),
(205,'light wash jeans',       2999.00, 12.00,'denim',    'jeans',       '/Images/Denim/light wash jeans.jpeg',         '/Images/Denim/light wash jeans.jpeg',         NULL,         11,'30,32,34,36',   'blue'),
(206,'denim overshirt',        3599.00, 12.00,'denim',    'shirts',      '/Images/Denim/denim overshirt.webp',          '/Images/Denim/denim overshirt.webp',          NULL,          8,'S,M,L,XL',      'blue'),
-- Outerwear
(301,'wool blend coat',        7499.00, 12.00,'outerwear','coats',       '/Images/Outerwear/wool blend coat.webp',      '/Images/Outerwear/wool blend coat.webp',      'new',         3,'S,M,L,XL',      'brown'),
(302,'technical shell jacket', 5799.00, 12.00,'outerwear','jackets',     '/Images/Outerwear/technical shell jacket.jpeg','/Images/Outerwear/technical shell jacket.jpeg',NULL,        6,'S,M,L,XL',      'gray'),
(303,'quilted overshirt',      4299.00, 12.00,'outerwear','shirts',      '/Images/Outerwear/quilted overshirt.avif',    '/Images/Outerwear/quilted overshirt.avif',    NULL,          9,'S,M,L,XL',      'green'),
(304,'leather biker jacket',   9799.00, 18.00,'outerwear','jackets',     '/Images/Outerwear/leather biker jacket.webp', '/Images/Outerwear/leather biker jacket.webp', 'premium',     4,'S,M,L,XL',      'black'),
(305,'puffer vest',            3799.00, 12.00,'outerwear','vests',       '/Images/Outerwear/puffer vest.jpeg',          '/Images/Outerwear/puffer vest.jpeg',          NULL,         10,'S,M,L,XL',      'black'),
(306,'wool peacoat',           8199.00, 12.00,'outerwear','coats',       '/Images/Outerwear/wool peacoat.jpeg',         '/Images/Outerwear/wool peacoat.jpeg',         NULL,          5,'S,M,L,XL',      'navy'),
-- Linen
(401,'linen button down',      2299.00, 12.00,'linen',    'shirts',      '/Images/Linen/linen button down.jpeg',        '/Images/Linen/linen button down.jpeg',        NULL,         12,'S,M,L,XL',      'white'),
(402,'drawstring linen pant',  2699.00, 12.00,'linen',    'bottoms',     '/Images/Linen/drawstring linen pant.jpg',     '/Images/Linen/drawstring linen pant.jpg',     NULL,          8,'S,M,L,XL',      'beige'),
(403,'linen blend shorts',     1799.00, 12.00,'linen',    'bottoms',     '/Images/Linen/linen blend shorts.jpeg',       '/Images/Linen/linen blend shorts.jpeg',       NULL,         15,'S,M,L,XL',      'gray'),
(404,'oversized linen shirt',  2399.00, 12.00,'linen',    'shirts',      '/Images/Linen/oversized linen shirt.webp',    '/Images/Linen/oversized linen shirt.webp',    'new',        10,'S,M,L,XL',      'blue'),
(405,'linen resort shirt',     2199.00, 12.00,'linen',    'shirts',      '/Images/Linen/linen resort shirt.webp',       '/Images/Linen/linen resort shirt.webp',       NULL,          7,'S,M,L,XL',      'green'),
(406,'wide leg linen pant',    2799.00, 12.00,'linen',    'bottoms',     '/Images/Linen/wide leg linen pant.avif',      '/Images/Linen/wide leg linen pant.avif',      NULL,          9,'S,M,L,XL',      'black'),
-- Accessories
(501,'wool beanie',             999.00,  5.00,'accessories','hats',      '/Images/Accessories/wool beanie.jpeg',        '/Images/Accessories/wool beanie.jpeg',        NULL,         20,'One Size',       'black'),
(502,'leather belt',           1499.00, 18.00,'accessories','belts',     '/Images/Accessories/leather belt.avif',       '/Images/Accessories/leather belt.avif',       'new',        15,'S,M,L',          'brown'),
(503,'canvas tote',            1199.00, 12.00,'accessories','bags',      '/Images/Accessories/canvas tote.webp',        '/Images/Accessories/canvas tote.webp',        NULL,         25,'One Size',       'natural'),
(504,'silk scarf',             1299.00, 12.00,'accessories','scarves',   '/Images/Accessories/silk scarf.webp',         '/Images/Accessories/silk scarf.webp',         NULL,         18,'One Size',       'blue');

-- ── HOW TO MAKE YOURSELF ADMIN ────────────────────────────────────────
-- 1. Register an account on the website
-- 2. Then run this query in phpMyAdmin (SQL tab):
--    UPDATE users SET is_admin = 1 WHERE email = 'your@email.com';

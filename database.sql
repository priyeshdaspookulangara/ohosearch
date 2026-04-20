CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'contributor') DEFAULT 'contributor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    parent_id INT NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    address VARCHAR(255),
    district VARCHAR(100),
    state VARCHAR(100),
    pin VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(255),
    whatsapp VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    category_id INT,
    user_id INT,
    status ENUM('pending_approval', 'live') DEFAULT 'pending_approval',
    hero_image VARCHAR(255),
    youtube_video_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS working_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    open_time TIME,
    close_time TIME,
    is_closed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS business_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    image_path VARCHAR(255),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

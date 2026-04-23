CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'contributor') DEFAULT 'contributor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    parent_id INT NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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
    user_id INT,
    status ENUM('pending_approval', 'live') DEFAULT 'pending_approval',
    hero_image VARCHAR(255),
    youtube_video_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Many-to-Many Categories
CREATE TABLE IF NOT EXISTS business_categories (
    business_id INT,
    category_id INT,
    PRIMARY KEY (business_id, category_id),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS working_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    open_time TIME,
    close_time TIME,
    is_closed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    image_path VARCHAR(255),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Offerings (Products/Services)
CREATE TABLE IF NOT EXISTS offerings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2),
    image_path VARCHAR(255),
    is_flagship BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Achievements, Awards, Certifications
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    issuer VARCHAR(255),
    date_awarded DATE,
    image_path VARCHAR(255),
    type ENUM('award', 'certification', 'achievement'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reviews and Ratings
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    user_id INT,
    rating INT CHECK(rating >= 1 AND rating <= 5),
    comment TEXT,
    reply TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Favorites / Wishlist
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    business_id INT,
    offering_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (offering_id) REFERENCES offerings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Coupons
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) UNIQUE NOT NULL,
    discount_value DECIMAL(10, 2),
    discount_type ENUM('percentage', 'fixed'),
    purpose ENUM('registration', 'featured'),
    assigned_to_user_id INT NULL,
    business_id INT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    used_at DATETIME,
    expiry_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Coupon Requests
CREATE TABLE IF NOT EXISTS coupon_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT,
    business_id INT NULL,
    purpose VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    business_id INT,
    amount DECIMAL(10, 2),
    currency VARCHAR(10) DEFAULT 'INR',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    coupon_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Featured Listings
CREATE TABLE IF NOT EXISTS featured_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    start_date DATETIME,
    end_date DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    payment_id INT,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Customer Enquiries
CREATE TABLE IF NOT EXISTS enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    user_id INT NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(20),
    message TEXT,
    status ENUM('new', 'replied', 'closed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Support Tickets
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    subject VARCHAR(255),
    message TEXT,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Claim Requests
CREATE TABLE IF NOT EXISTS claim_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    user_id INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255),
    message TEXT,
    type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

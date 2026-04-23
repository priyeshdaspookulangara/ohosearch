CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'contributor', -- 'admin', 'contributor', 'customer'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    parent_id INTEGER NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS businesses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    address TEXT,
    district TEXT,
    state TEXT,
    pin TEXT,
    phone TEXT,
    email TEXT,
    whatsapp TEXT,
    latitude REAL,
    longitude REAL,
    user_id INTEGER,
    status TEXT DEFAULT 'pending_approval',
    hero_image TEXT,
    youtube_video_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Many-to-Many Categories
CREATE TABLE IF NOT EXISTS business_categories (
    business_id INTEGER,
    category_id INTEGER,
    PRIMARY KEY (business_id, category_id),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS working_hours (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    day_of_week TEXT,
    open_time TEXT,
    close_time TEXT,
    is_closed BOOLEAN DEFAULT 0,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS business_gallery (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    image_path TEXT,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Offerings (Products/Services)
CREATE TABLE IF NOT EXISTS offerings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    name TEXT NOT NULL,
    description TEXT,
    price REAL,
    image_path TEXT,
    is_flagship BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Achievements, Awards, Certifications
CREATE TABLE IF NOT EXISTS achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    title TEXT NOT NULL,
    description TEXT,
    issuer TEXT,
    date_awarded DATE,
    image_path TEXT,
    type TEXT, -- 'award', 'certification', 'achievement'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Reviews and Ratings
CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    user_id INTEGER,
    rating INTEGER CHECK(rating >= 1 AND rating <= 5),
    comment TEXT,
    reply TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Favorites / Wishlist
CREATE TABLE IF NOT EXISTS favorites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    business_id INTEGER,
    offering_id INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (offering_id) REFERENCES offerings(id) ON DELETE CASCADE
);

-- Coupons
CREATE TABLE IF NOT EXISTS coupons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    discount_value REAL,
    discount_type TEXT, -- 'percentage' or 'fixed'
    purpose TEXT, -- 'registration' or 'featured'
    assigned_to_user_id INTEGER NULL, -- Contributor or Owner
    business_id INTEGER NULL,
    is_used BOOLEAN DEFAULT 0,
    used_at DATETIME,
    expiry_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
);

-- Coupon Requests
CREATE TABLE IF NOT EXISTS coupon_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requester_id INTEGER,
    business_id INTEGER NULL,
    purpose TEXT,
    status TEXT DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    admin_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    business_id INTEGER,
    amount REAL,
    currency TEXT DEFAULT 'INR',
    payment_method TEXT,
    transaction_id TEXT UNIQUE,
    status TEXT DEFAULT 'pending', -- 'pending', 'completed', 'failed'
    coupon_id INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
);

-- Featured Listings
CREATE TABLE IF NOT EXISTS featured_listings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    start_date DATETIME,
    end_date DATETIME,
    is_active BOOLEAN DEFAULT 1,
    payment_id INTEGER,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
);

-- Customer Enquiries
CREATE TABLE IF NOT EXISTS enquiries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    user_id INTEGER NULL,
    customer_name TEXT,
    customer_email TEXT,
    customer_phone TEXT,
    message TEXT,
    status TEXT DEFAULT 'new', -- 'new', 'replied', 'closed'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Support Tickets
CREATE TABLE IF NOT EXISTS support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    subject TEXT,
    message TEXT,
    status TEXT DEFAULT 'open', -- 'open', 'in_progress', 'resolved', 'closed'
    priority TEXT DEFAULT 'normal',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Claim Requests
CREATE TABLE IF NOT EXISTS claim_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_id INTEGER,
    user_id INTEGER,
    status TEXT DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    title TEXT,
    message TEXT,
    type TEXT, -- 'approval', 'expiry', 'coupon', 'payment', 'enquiry'
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

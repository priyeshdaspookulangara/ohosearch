CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'contributor',
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
    category_id INTEGER,
    user_id INTEGER,
    status TEXT DEFAULT 'pending_approval',
    hero_image TEXT,
    youtube_video_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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

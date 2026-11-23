-- SWEETPOP Database Schema & Seeds

CREATE TABLE IF NOT EXISTS admin_info (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_name VARCHAR(100) NOT NULL,
    admin_email VARCHAR(300) NOT NULL UNIQUE,
    admin_password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_info (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(300) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    mobile VARCHAR(20),
    address1 VARCHAR(300),
    address2 VARCHAR(300),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_info_backup (
    user_id INT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(300) NOT NULL,
    password VARCHAR(255) NOT NULL,
    mobile VARCHAR(20),
    address1 VARCHAR(300),
    address2 VARCHAR(300)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_info (
    email_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(300) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    cat_id INT AUTO_INCREMENT PRIMARY KEY,
    cat_title VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_cat INT NOT NULL,
    product_title VARCHAR(200) NOT NULL,
    product_price DECIMAL(12,2) NOT NULL,
    product_description TEXT,
    product_image VARCHAR(255),
    product_stock INT DEFAULT 0,
    product_status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_cat) REFERENCES categories(cat_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(300) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    shipping_address TEXT NOT NULL,
    shipping_city VARCHAR(100) NOT NULL,
    delivery_date DATE NULL,
    delivery_time VARCHAR(100) NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'QRIS',
    notes TEXT,
    cake_message VARCHAR(255) NULL,
    candle_request VARCHAR(100) NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    trx_id VARCHAR(255) NULL,
    p_status VARCHAR(50) DEFAULT 'Pending',
    payment_expiry DATETIME NULL,
    paid_at DATETIME NULL,
    snap_token VARCHAR(255) NULL,
    midtrans_qr_url VARCHAR(500) NULL,
    midtrans_va_number VARCHAR(100) NULL,
    midtrans_bank VARCHAR(50) NULL,
    midtrans_bill_key VARCHAR(100) NULL,
    midtrans_biller_code VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_info(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(200) NOT NULL,
    product_price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger replication
DROP TRIGGER IF EXISTS after_user_info_insert;
CREATE TRIGGER after_user_info_insert
AFTER INSERT ON user_info
FOR EACH ROW
BEGIN
    INSERT INTO user_info_backup (
        user_id, first_name, last_name, email, password, mobile, address1, address2
    ) VALUES (
        NEW.user_id, NEW.first_name, NEW.last_name, NEW.email, NEW.password, NEW.mobile, NEW.address1, NEW.address2
    );
END;

-- Stored Procedure
DROP PROCEDURE IF EXISTS getcat;
CREATE PROCEDURE getcat(IN cid INT)
BEGIN
    SELECT * FROM categories WHERE cat_id = cid;
END;

<?php
require_once __DIR__ . '/db.php';

echo "<h2>Sweetpop Database Setup & Seed</h2>";

// 1. Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS admin_info (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_name VARCHAR(100) NOT NULL,
        admin_email VARCHAR(300) NOT NULL UNIQUE,
        admin_password VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS user_info (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(300) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        mobile VARCHAR(20),
        address1 VARCHAR(300),
        address2 VARCHAR(300),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS user_info_backup (
        user_id INT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(300) NOT NULL,
        password VARCHAR(255) NOT NULL,
        mobile VARCHAR(20),
        address1 VARCHAR(300),
        address2 VARCHAR(300)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS email_info (
        email_id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(300) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS categories (
        cat_id INT AUTO_INCREMENT PRIMARY KEY,
        cat_title VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS products (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        customer_name VARCHAR(150) NOT NULL,
        customer_email VARCHAR(300) NOT NULL,
        customer_phone VARCHAR(30) NOT NULL,
        shipping_address TEXT NOT NULL,
        shipping_city VARCHAR(100) NOT NULL,
        payment_method VARCHAR(50) NOT NULL DEFAULT 'QRIS',
        notes TEXT,
        total_amount DECIMAL(12,2) NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user_info(user_id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS order_items (
        order_item_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        product_name VARCHAR(200) NOT NULL,
        product_price DECIMAL(12,2) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        subtotal DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($tables as $sql) {
    if (mysqli_query($con, $sql)) {
        echo "<p style='color:green;'>✓ Table created or verified successfully</p>";
    } else {
        echo "<p style='color:red;'>✗ Error creating table: " . mysqli_error($con) . "</p>";
    }
}

// 2. Trigger
mysqli_query($con, "DROP TRIGGER IF EXISTS after_user_info_insert");
$triggerSql = "CREATE TRIGGER after_user_info_insert
AFTER INSERT ON user_info
FOR EACH ROW
BEGIN
    INSERT INTO user_info_backup (
        user_id, first_name, last_name, email, password, mobile, address1, address2
    ) VALUES (
        NEW.user_id, NEW.first_name, NEW.last_name, NEW.email, NEW.password, NEW.mobile, NEW.address1, NEW.address2
    );
END";
if (mysqli_query($con, $triggerSql)) {
    echo "<p style='color:green;'>✓ Trigger `after_user_info_insert` created successfully</p>";
} else {
    echo "<p style='color:red;'>✗ Trigger error: " . mysqli_error($con) . "</p>";
}

// 3. Stored Procedure
mysqli_query($con, "DROP PROCEDURE IF EXISTS getcat");
$procSql = "CREATE PROCEDURE getcat(IN cid INT)
BEGIN
    SELECT * FROM categories WHERE cat_id = cid;
END";
if (mysqli_query($con, $procSql)) {
    echo "<p style='color:green;'>✓ Stored Procedure `getcat` created successfully</p>";
} else {
    echo "<p style='color:red;'>✗ Stored Procedure error: " . mysqli_error($con) . "</p>";
}

// 4. Seed Admin
$adminEmail = 'admin@sweetpop.com';
$adminName = 'SweetPop Admin';
$adminPass = password_hash('admin123', PASSWORD_DEFAULT);

$checkAdmin = mysqli_query($con, "SELECT admin_id FROM admin_info WHERE admin_email = '$adminEmail'");
if (mysqli_num_rows($checkAdmin) == 0) {
    $stmt = mysqli_prepare($con, "INSERT INTO admin_info (admin_name, admin_email, admin_password) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sss", $adminName, $adminEmail, $adminPass);
    mysqli_stmt_execute($stmt);
    echo "<p style='color:green;'>✓ Default Admin seeded: admin@sweetpop.com / admin123</p>";
} else {
    // update password to ensure bcrypt matches
    $stmt = mysqli_prepare($con, "UPDATE admin_info SET admin_password = ? WHERE admin_email = ?");
    mysqli_stmt_bind_param($stmt, "ss", $adminPass, $adminEmail);
    mysqli_stmt_execute($stmt);
    echo "<p style='color:blue;'>ℹ Admin already exists, password updated to 'admin123'</p>";
}

// 5. Seed Categories
$categories = [
    [1, 'Cake'],
    [2, 'Dessert Box'],
    [3, 'Cookies'],
    [4, 'Brownies'],
    [5, 'Pudding'],
    [6, 'Drinks'],
    [7, 'Hampers']
];

foreach ($categories as $cat) {
    $cid = $cat[0];
    $title = $cat[1];
    $checkCat = mysqli_query($con, "SELECT cat_id FROM categories WHERE cat_id = $cid");
    if (mysqli_num_rows($checkCat) == 0) {
        mysqli_query($con, "INSERT INTO categories (cat_id, cat_title) VALUES ($cid, '$title')");
    }
}
echo "<p style='color:green;'>✓ Categories verified.</p>";

// 6. Seed Products
$products = [
    [1, 1, 'Strawberry Cloud Cake', 45000, 'Soft vanilla chiffon cake layered with fresh whipped strawberry cream and organic strawberries.', 'strawberry-cloud.jpg', 25, 'active'],
    [2, 2, 'Choco Oreo Dessert Box', 28000, 'Creamy rich Belgian chocolate mousse with crushed crunchy Oreo layers in a cute pastel box.', 'choco-oreo.jpg', 30, 'active'],
    [3, 3, 'Pink Sprinkle Cookies', 22000, 'Chewy buttery NYC-style cookies with pastel rainbow sprinkles and white chocolate chips.', 'pink-cookies.jpg', 40, 'active'],
    [4, 4, 'Fudgy Choco Brownies', 30000, 'Ultra fudgy dark chocolate brownies with shiny crackly crust and melted chocolate chunks.', 'choco-brownies.jpg', 20, 'active'],
    [5, 5, 'Berry Pudding Cup', 18000, 'Silky smooth Japanese-style milk pudding with sweet and tangy mixed berry compote.', 'berry-pudding.jpg', 35, 'active'],
    [6, 6, 'Strawberry Matcha Latte', 25000, 'Ceremonial grade Uji matcha with fresh strawberry puree and creamy oat milk.', 'strawberry-matcha.jpg', 50, 'active'],
    [7, 7, 'Sweetest Dream Hamper', 125000, 'Luxury gift hamper box with 2 dessert boxes, 1 cookie jar, ribbon, and personalized card.', 'sweet-hampers.jpg', 15, 'active'],
    [8, 1, 'Pastel Velvet Cake', 48000, 'Velvety baby pink sponge cake with smooth Madagascar cream cheese frosting.', 'pastel-velvet.jpg', 20, 'active'],
    [9, 2, 'Lotus Biscoff Dream Box', 32000, 'Layered caramelized Biscoff spread, creamy cheese mousse, and crispy Lotus biscuit crumbs.', 'lotus-biscoff.jpg', 25, 'active'],
    [10, 3, 'Matcha White Choc Cookies', 24000, 'Earthy premium matcha cookies filled with gooey Belgian white chocolate pockets.', 'matcha-cookies.jpg', 30, 'active']
];

foreach ($products as $p) {
    $pid = $p[0];
    $pcat = $p[1];
    $ptitle = mysqli_real_escape_string($con, $p[2]);
    $pprice = $p[3];
    $pdesc = mysqli_real_escape_string($con, $p[4]);
    $pimg = $p[5];
    $pstock = $p[6];
    $pstat = $p[7];

    $checkProd = mysqli_query($con, "SELECT product_id FROM products WHERE product_id = $pid");
    if (mysqli_num_rows($checkProd) == 0) {
        $insertSql = "INSERT INTO products (product_id, product_cat, product_title, product_price, product_description, product_image, product_stock, product_status)
                      VALUES ($pid, $pcat, '$ptitle', $pprice, '$pdesc', '$pimg', $pstock, '$pstat')";
        mysqli_query($con, $insertSql);
    } else {
        $updateSql = "UPDATE products SET product_cat=$pcat, product_title='$ptitle', product_price=$pprice, product_description='$pdesc', product_image='$pimg', product_stock=$pstock, product_status='$pstat' WHERE product_id=$pid";
        mysqli_query($con, $updateSql);
    }
}
echo "<p style='color:green;'>✓ Products seeded & verified.</p>";

echo "<h3 style='color:green;'>All SweetPop database tables, triggers, procedures, admin, and products are ready!</h3>";
?>

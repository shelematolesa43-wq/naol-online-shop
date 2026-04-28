<?php
session_start();

$host = "mysql-11ead335-shelematolesa43-84db.g.aivencloud.com";
$user = "avnadmin";
$pass = 'AVNS__vcyJnLCW7tPcJRITMN'; 
$db   = "defaultdb";
$port = 23454;

$conn = mysqli_init();

// SSL dabaladhu
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, FALSE);

// Connection
$success = mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    // Dogoggora DNS yoo ta'e asitti siif hima
    die("Connection Error: " . mysqli_connect_error());
}

// --- DATABASE TABLES UUMUUF (KANA DABALADHU) ---
if ($success) {
    // 1. Table products
    $conn->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        img_url VARCHAR(500),
        stock INT DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Table shop_settings
    $conn->query("CREATE TABLE IF NOT EXISTS shop_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE,
        setting_value TEXT
    )");

    // 3. Initial settings galchuuf
    $conn->query("INSERT IGNORE INTO shop_settings (setting_key, setting_value) VALUES ('shop_name', 'NAOL SHOP'), ('location', 'Burrayu, Ethiopia')");
    // 5. Table orders uumuuf (Naol akka arguuf)
$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255),
    size VARCHAR(20),
    bank VARCHAR(50),
    price DECIMAL(10, 2),
    status VARCHAR(20) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
    if (isset($_POST['action']) && $_POST['action'] == 'place_order') {
    $name = $_POST['item_name'];
    $price = $_POST['price'];
    $size = $_POST['size'];
    $bank = $_POST['bank'];

    // Database keessatti galchuuf (Naol akka arguuf)
    $stmt = $conn->prepare("INSERT INTO orders (product_name, price, size, bank) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdss", $name, $price, $size, $bank);
    $stmt->execute();

    echo json_encode(["status" => "success"]);
    exit;
}
    // 4. Admin user yoo hin jirre uumuuf (Username: admin, Password: password123)
    $pass_hash = password_hash('password123', PASSWORD_DEFAULT);
    $conn->query("CREATE TABLE IF NOT EXISTS admin_users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password_hash VARCHAR(255))");
    $conn->query("INSERT IGNORE INTO admin_users (username, password_hash) VALUES ('admin', '$pass_hash')");
}
// ----------------------------------------------
function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// 2.2 API: Handle Order & Send Email (Naol Shop Notification)
if (isset($_POST['action']) && $_POST['action'] == 'place_order') {
    $name = $_POST['item_name'] ?? 'Unknown Item';
    $price = $_POST['price'] ?? '0';
    $size = $_POST['size'] ?? 'N/A';
    $bank = $_POST['bank'] ?? 'N/A';

    $to = "jnaol2002@gmail.com";
    $subject = "New Order from NAOL SHOP";
    $message = "You have a new order!\n\nProduct: $name\nPrice: ETB $price\nSize: $size\nPayment: $bank";
    $headers = "From: webmaster@naolshop.vercel.app\r\n";
    $headers .= "Reply-To: jnaol2002@gmail.com";

    // Vercel irratti mail() hojjechuu dhiisuu danda'a (Check Build Logs)
    if(mail($to, $subject, $message, $headers)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Mail server error"]);
    }
    exit;
}

// 2.3 Auth: Login Logic
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $admin_user = $_POST['username'];
    $admin_pass = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT password_hash FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $admin_user);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && password_verify($admin_pass, $res['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        echo json_encode(["status" => "success"]);
    } else {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(["status" => "fail"]);
    }
    exit;
}

// 2.4 Auth: Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// 2.5 Data Fetching: Initialization
if (isset($_GET['action']) && $_GET['action'] == 'get_init') {
    header('Content-Type: application/json');
    
    // Shop settings fetch
    $settings_query = $conn->query("SELECT * FROM shop_settings");
    $settings = $settings_query ? $settings_query->fetch_all(MYSQLI_ASSOC) : [];
    
    // Products fetch
    $products_query = $conn->query("SELECT * FROM products ORDER BY id DESC");
    $products = $products_query ? $products_query->fetch_all(MYSQLI_ASSOC) : [];
    
    echo json_encode([
        "settings" => array_column($settings, 'setting_value', 'setting_key'),
        "products" => $products,
        "is_admin" => isAdmin()
    ]);
    exit;
}

// 2.6 Product Management: Create (Upload)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_shoe'])) {
    if (!isset($_POST['admin_key']) || $_POST['admin_key'] !== "naol123") {
        header('HTTP/1.1 403 Forbidden');
        exit("Invalid Admin Key");
    }

    $name = htmlspecialchars($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $img_url = "https://via.placeholder.com/300x200?text=No+Image";

    if (isset($_FILES['shoeFile']) && $_FILES['shoeFile']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES["shoeFile"]["name"], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $target_dir = "uploads/"; // Vercel irratti kun yeroo gabaabaaf qofa tura
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $file_name = time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $target_path = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["shoeFile"]["tmp_name"], $target_path)) {
                $img_url = $target_path;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO products (name, price, img_url, stock) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdsi", $name, $price, $img_url, $stock);
    $stmt->execute();
    header("Location: index.php"); // Refresh after add
    exit;
}

// 2.7 Settings Management: Update
if (isset($_POST['update_settings']) && isAdmin()) {
    foreach(['shop_name', 'location'] as $key) {
        if(isset($_POST[$key])) {
            $stmt = $conn->prepare("UPDATE shop_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $_POST[$key], $key);
            $stmt->execute();
        }
    }
    exit;
}

// 2.8 Product Management: Delete
if (isset($_GET['delete_id'])) {
    if (!isset($_GET['key']) || $_GET['key'] !== "naol123") {
        header('HTTP/1.1 403 Forbidden');
        exit("Unauthorized");
    }
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $_GET['delete_id']);
    $stmt->execute();
    header("Location: index.php");
    exit;
}

// 2.9 Product Management: Update (Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_shoe'])) {
    if (!isset($_POST['admin_key']) || $_POST['admin_key'] !== "naol123") {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
    $stmt = $conn->prepare("UPDATE products SET name = ?, price = ? WHERE id = ?");
    $stmt->bind_param("sdi", $_POST['name'], $_POST['price'], $_POST['id']);
    $stmt->execute();
    header("Location: index.php");
    exit;
}

// 2.10 API: Get Shoes List
if (isset($_GET['action']) && $_GET['action'] == 'get_shoes') {
    header('Content-Type: application/json');
    $result = $conn->query("SELECT * FROM products ORDER BY id DESC");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    exit;
}
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_shoe'])) {
    $name = htmlspecialchars($_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $img_url = "https://via.placeholder.com/300x200?text=No+Image"; // Yoo suuraan hin jirre

    if (isset($_FILES['shoeFile']) && $_FILES['shoeFile']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES["shoeFile"]["name"], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            
            // Maqaa fayilaa adda gochuuf (Fakkeenya: 1714001234_a2b3c4.jpg)
            $file_name = time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            $target_path = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["shoeFile"]["tmp_name"], $target_path)) {
                $img_url = $target_path; 
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO products (name, price, img_url, stock) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdsi", $name, $price, $img_url, $stock);
    $stmt->execute();
    header("Location: index.php");
    exit;
}
        // --- DATABASE TABLES UUMUUF ---
if ($success) {
    // ... koodii table uumuuf ati qabdu jira ...

    // --- SHOES 12 KALLAATTIIN GALCHUUF ---
    $google_shoes = [
        ['name' => 'Nike Air Force 1', 'price' => 4500, 'url' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?q=80&w=1000'],
        ['name' => 'Adidas Superstar', 'price' => 3800, 'url' => 'https://images.unsplash.com/photo-1518002171953-a080ee817e1f?q=80&w=1000'],
        ['name' => 'Jordan 4 Retro', 'price' => 6200, 'url' => 'https://images.unsplash.com/photo-1514989940723-e8e51635b782?q=80&w=1000'],
        ['name' => 'Puma RS-X', 'price' => 3200, 'url' => 'https://images.unsplash.com/photo-1584735175315-9d581f7a06c9?q=80&w=1000'],
        ['name' => 'New Balance 550', 'price' => 4100, 'url' => 'https://images.unsplash.com/photo-1539185441755-769473a23570?q=80&w=1000'],
        ['name' => 'Converse Chuck Taylor', 'price' => 2500, 'url' => 'https://images.unsplash.com/photo-1491553895911-0055eca6402d?q=80&w=1000'],
        ['name' => 'Vans Old Skool', 'price' => 2800, 'url' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?q=80&w=1000'],
        ['name' => 'Reebok Classic', 'price' => 3000, 'url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=1000'],
        ['name' => 'Nike Dunk Low', 'price' => 5000, 'url' => 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?q=80&w=1000'],
        ['name' => 'Yeezy Boost 350', 'price' => 8500, 'url' => 'https://images.unsplash.com/photo-1587563871167-1ee9c731aefb?q=80&w=1000'],
        ['name' => 'Balenciaga Speed', 'price' => 9500, 'url' => 'https://images.unsplash.com/photo-1595341888016-a392ef81b7de?q=80&w=1000'],
        ['name' => 'Asics Gel-Kayano', 'price' => 4200, 'url' => 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?q=80&w=1000']
    ];

    foreach ($google_shoes as $shoe) {
        // 'INSERT IGNORE' yoo jenne data dachaa (duplicate) nuuf hin galchu
        $stmt = $conn->prepare("INSERT IGNORE INTO products (name, price, img_url) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $shoe['name'], $shoe['price'], $shoe['url']);
        $stmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAOL SHOP | Premium Sneakers</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        /* --- CSS STYLING SECTION --- */
        :root { 
            --nav-bg: #232F3E; 
            --sub-nav: #37475A; 
            --cta: #FF9900; 
            --cta-hover: #e68a00; 
            --bg: #EAEDED; 
            --text-dark: #111111; 
            --sidebar-width: 280px; 
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(-45deg, #EAEDED, #ffffff, #f2f2f2, #EAEDED);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: var(--text-dark); 
            display: flex; 
            min-height: 100vh; 
            overflow-x: hidden; 
            flex-direction: column; 
        }

        @keyframes gradientBG { 
            0% { background-position: 0% 50%; } 
            50% { background-position: 100% 50%; } 
            100% { background-position: 0% 50%; } 
        }

        /* Sidebar & Navigation */
  /* Sidebar menu akka hunda gubbaa dhufuuf */
.sidebar { 
    width: var(--sidebar-width); 
    background: var(--nav-bg); 
    height: 100vh; 
    position: fixed; 
    left: -280px; /* Bakka kana sirreessineerra */
    top: 0; 
    padding: 2.5rem 1.5rem; 
    transition: 0.5s cubic-bezier(0.4, 0, 0.2, 1); 
    z-index: 9999 !important; /* Baay'ee barbaachisaadha */
    color: white; 
    display: flex; 
    flex-direction: column; 
}

.sidebar.active { 
    left: 0 !important; 
    box-shadow: 15px 0 50px rgba(0,0,0,0.7); 
}

/* Hamburger icon akka tuqamuuf */
.menu-icon {
    font-size: 2rem; 
    cursor: pointer; 
    z-index: 10001; 
    position: relative;
    padding: 5px;
}
        .sidebar.active { left: 0; box-shadow: 15px 0 50px rgba(0,0,0,0.7); }
        
        .nav-links { list-style: none; margin-top: 30px; }
        .nav-links a { 
            color: #eaeded; 
            text-decoration: none; 
            display: flex; 
            align-items: center;
            padding: 14px; 
            border-radius: 10px; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            margin-bottom: 8px; 
            font-weight: 500;
        }
        .nav-links a:hover { 
            background: var(--sub-nav); 
            color: var(--cta); 
            transform: translateX(8px); 
        }

        .main-content { margin-left: 0; flex-grow: 1; width: 100%; transition: 0.4s; }
        @media (min-width: 1024px) { .main-content.shifted { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); } }
        
        nav { 
            background: linear-gradient(90deg, #232F3E 0%, #37475A 100%); 
            padding: 1rem 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
            color: white; 
            border-bottom: 3px solid var(--cta); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); 
        }
/* Footer Animation Styling */
.animated-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background: #232F3E;
    color: white;
    text-align: center;
    padding: 15px 0;
    font-weight: bold;
    font-size: 1.2rem;
    z-index: 2000;
    border-top: 4px solid #FF9900;
    
    /* Animation for movement and color */
    animation: moveFooter 5s ease-in-out infinite, changeColor 8s linear infinite;
}

/* Movement Animation: Jala gadii fi ol socho'a */
@keyframes moveFooter {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

/* Color Animation: Halluu sadiin jijjiirama */
@keyframes changeColor {
    0% { color: #FF9900; }      /* Orange */
    33% { color: #00FFCC; }     /* Teal/Greenish */
    66% { color: #FFFFFF; }     /* White */
    100% { color: #FF9900; }    /* Back to Orange */
}
        /* Hero UI */
        .hero-banner { 
            width: 100%; 
            height: 380px; 
            background: url('https://images.unsplash.com/photo-1556906781-9a412961c28c?q=80&w=2000') center/cover; 
            position: relative; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            text-align: center; 
        }
        .hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
        
        .miracle-text { 
            font-size: clamp(2rem, 5vw, 4rem); 
            font-weight: 800; 
            background: linear-gradient(to right, #fff, var(--cta), #fff, var(--cta)); 
            background-size: 200% auto; 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            animation: shine 4s linear infinite; 
            position: relative; 
            z-index: 2; 
        }
        @keyframes shine { to { background-position: 200% center; } }

        /* Product Cards */
        .container { max-width: 1440px; margin: 0 auto; padding: 3rem 2rem; }
        .shop-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
            gap: 2.5rem; 
        }
        /* Akka Dropdown sun dirqama mul'atu godha */
.shop-select {
    display: block !important; /* Dhokatee yoo jiraate akka mul'atu godha */
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background-color: white;
    color: #333;
    font-size: 0.9rem;
    cursor: pointer;
}

/* Shoe card keessatti iddoo akka qabaatu godha */
.shoe-card {
    height: auto !important; /* Dheerinni isaa akka dabalatuuf */
    padding-bottom: 20px;
}
        .shoe-card { 
            background: var(--white); 
            border-radius: 16px; 
            padding: 24px; 
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            box-shadow: var(--shadow); 
            position: relative; 
            border: 1px solid #f0f0f0;
        }
        .shoe-card:hover { 
            transform: translateY(-15px); 
            box-shadow: 0 25px 50px rgba(255, 153, 0, 0.2); 
            border-color: var(--cta); 
        }

        .shoe-img-box { 
            height: 220px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: #f9f9f9;
            border-radius: 12px; 
            margin-bottom: 20px; 
            overflow: hidden;
        }
        .shoe-img-box img { max-width: 90%; max-height: 90%; transition: 0.5s; }
        .shoe-card:hover .shoe-img-box img { transform: scale(1.1) rotate(-3deg); }

        /* Components */
        .btn-impulse { 
            background: var(--cta); 
            border: none; 
            padding: 16px; 
            width: 100%; 
            cursor: pointer; 
            border-radius: 12px; 
            font-weight: 800; 
            color: #111; 
            text-transform: uppercase; 
            transition: 0.3s; 
            box-shadow: 0 4px 0 #cc7a00;
        }
        .btn-impulse:active { transform: translateY(3px); box-shadow: none; }

        .toast { 
            position: fixed; 
            bottom: 40px; 
            right: 40px; 
            background: #232F3E; 
            color: white; 
            border-left: 8px solid var(--cta); 
            padding: 25px; 
            border-radius: 12px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); 
            display: none; 
            z-index: 3000; 
            animation: slideIn 0.5s ease;
        }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        .notif-dropdown {
    position: absolute;
    top: 60px; /* Navbar jalaa akka mul'atuuf */
    right: 0;
    width: 320px;
    background: white;
    color: black; /* Barreeffamni akka mul'atuuf */
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    padding: 15px;
    display: none; /* Javascript-tu bana */
    z-index: 9999; /* Element-oota biroo hunda gubbaa akka ta'u */
    max-height: 400px;
    overflow-y: auto;
}
.notif-dropdown {
    position: absolute;
    top: 60px;
    right: 20px;
    width: 300px;
    background: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border-radius: 10px;
    display: none; /* Jalqaba irratti ni dhokata */
    z-index: 1000;
    padding: 15px;
    color: #333;
}

.history-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    font-size: 0.9rem;
}

        .admin-field { 
            width: 100%; 
            padding: 14px; 
            margin: 10px 0; 
            border: 2px solid #eee; 
            border-radius: 10px; 
            font-family: inherit;
        }

        footer { 
            background: var(--nav-bg); 
            color: white; 
            padding: 60px 5%; 
            text-align: center; 
            margin-top: auto; 
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <h2 id="display-brand-name" style="letter-spacing: 2px; color: var(--cta);">NAOL SHOP</h2>
    <p id="display-location" style="font-size: 0.9rem; opacity: 0.8; margin-bottom: 20px;">📍 Burrayu, Ethiopia</p>
    <div class="nav-links">
        <a id="login-link" onclick="toggleAdmin()"><span>🔑</span> Admin Central</a>
        <a id="setting-link" style="display:none;" onclick="openSettings()"><span>⚙️</span> Settings</a>
        <a onclick="location.reload()"><span>📦</span> Refresh Catalog</a>
        <a href="mailto:jnaol202@gmail.com"><span>📧</span> Contact Naol</a>
        <a id="logout-link" style="display:none;" href="?action=logout" style="color:#ff4d4d;"><span>🚪</span> Logout</a>
    </div>
</div>

<div class="main-content" id="mainContent">
 <nav>
    <div style="display:flex; align-items:center; gap:20px;">
        <span style="font-size:2rem; cursor:pointer;" onclick="toggleSidebar()">☰</span>
        <h1 style="color:var(--cta); font-weight: 900; letter-spacing: -1px;">NAOL</h1>
    </div>

    <div style="flex-grow: 1; max-width: 500px; margin: 0 30px; display: flex;">
        <input type="text" id="search-input" style="width:100%; padding:12px 20px; border-radius:8px 0 0 8px; border:none;" placeholder="Search for shoes..." onkeyup="searchShoes()">
        <button style="background:var(--cta); border:none; padding:0 20px; border-radius:0 8px 8px 0; cursor:pointer;">🔍</button>
    </div>

    <div style="display:flex; gap:30px; align-items:center;">
        
        <div style="cursor:pointer; position:relative;" onclick="toggleDropdown('order-dropdown')">
            <small style="display:block; opacity:0.7; font-size:0.7rem;">Your</small>
            <strong style="font-size:0.9rem;">Orders</strong>
            <span style="font-size:1.6rem;">🛒</span>
            <span id="order-badge" style="background:var(--cta); color:black; padding:2px 6px; border-radius:50%; font-size:0.7rem; position:absolute; top:-5px; right:-10px; display:none;">0</span>
            
            <div class="notif-dropdown" id="order-dropdown">
                <h4 style="border-bottom: 2px solid var(--cta); padding-bottom: 10px; color:black;">Order History</h4>
                <div id="order-history-list" style="margin-top:15px; max-height: 300px; overflow-y:auto; color:black;">
                    <p style="text-align:center; color:#999; font-size:0.8rem;">No orders yet.</p>
                </div>
            </div>
        </div>

        <div style="cursor:pointer; position:relative;" onclick="toggleDropdown('notif-dropdown')">
            <span style="font-size:1.6rem;">🛎️</span>
            <span id="notif-badge" style="background:var(--cta); color:black; padding:2px 7px; border-radius:50%; font-size:0.7rem; position:absolute; top:0; right:-5px; display:none;">0</span>
            
            <div class="notif-dropdown" id="notif-dropdown">
                <h4 style="border-bottom: 2px solid var(--cta); padding-bottom: 10px; color:black;">Notifications</h4>
                <div id="notif-list" style="margin-top:10px; max-height: 300px; overflow-y:auto; color:black;"></div>
            </div>
        </div>

    </div>
</nav>

    <div class="hero-banner">
        <div class="hero-overlay"></div>
        <div style="position:relative; z-index:5;">
            <h1 class="miracle-text">NAOL ONLINE SHOP</h1>
            <p style="font-size:1.2rem; margin-top:10px; font-weight:300;">Experience Quality. Comfort. Style.</p>
        </div>
    </div>

    <div class="container">
        <div id="settings-panel" style="display:none; background:white; padding:30px; border-radius:15px; margin-bottom:30px; border-left:10px solid var(--cta); box-shadow: var(--shadow);">
            <h3>Store Configuration</h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-top:20px;">
                <div>
                    <label>Shop Display Name</label>
                    <input type="text" id="set-shop-name" class="admin-field" placeholder="Brand Name">
                </div>
                <div>
                    <label>Store Location</label>
                    <input type="text" id="set-location" class="admin-field" placeholder="City, Country">
                </div>
            </div>
            <button class="btn-impulse" onclick="saveSettings()" style="width:200px; margin-top:20px;">Update Store</button>
        </div>

       <div id="admin-panel" style="display:none; background:white; padding:30px; border-radius:15px; margin-bottom:30px; border-left:10px solid var(--cta); box-shadow: var(--shadow);">
    <h3>Add New Inventory</h3>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-top:15px;">
        <input type="text" id="shoeModel" placeholder="Sneaker Name" class="admin-field">
        <input type="number" id="shoePrice" placeholder="Price (ETB)" class="admin-field">
        <input type="number" id="shoeStock" placeholder="Stock" class="admin-field" value="10">
        
        <div style="grid-column: 1 / -1;">
            <label style="font-weight:600; display:block; margin-bottom:5px;">Select Product Image:</label>
            <input type="file" id="shoeFile" class="admin-field" accept="image/*" style="padding: 10px;">
        </div>
    </div>
    <button class="btn-impulse" onclick="addShoe()" style="width:200px; margin-top:10px;">List Product</button>
</div>

        <div id="shop-display" class="shop-grid"></div>
    </div>
</div>

<div id="toast" class="toast">
    <strong id="toast-title">Success!</strong><br>
    <span id="toast-msg">Action completed.</span>
</div>

<footer>
    <div style="max-width:1200px; margin:0 auto; display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:40px; text-align:left;">
        <div>
            <h2 style="color:var(--cta); margin-bottom:20px;">NAOL SHOP</h2>
            <p style="opacity:0.7; font-size:0.9rem;">The leading destination for premium footwear in Ethiopia. We bring style to your doorstep.</p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <p style="margin-top:10px; opacity:0.7; cursor:pointer;" onclick="location.reload()">New Arrivals</p>
            <p style="margin-top:5px; opacity:0.7; cursor:pointer;" onclick="toggleAdmin()">Admin Access</p>
        </div>
        <footer class="animated-footer">
    "THANK YOU FOR EVERYTHINGS OUR COSTUMER"
</footer>
        <div>
            <h4>Contact Developer</h4>
            <p style="margin-top:10px; opacity:0.8;"><b>Shelema Tolesa</b></p>
            <p style="opacity:0.6;">Full Stack Developer</p>
        </div>
    </div>
    <div style="margin-top:50px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.1); opacity:0.5; font-size:0.8rem;">
        &copy; 2026 NAOL SHOP. All Rights Reserved.
    </div>
</footer>

<script>
    // --- JAVASCRIPT APP LOGIC ---
    let isAdminStatus = false;
    let currentKey = "";
    let allShoes = [];
    let notifs = 0;
    let orders = 0;

    // 1. Initial Load
    async function init() {
        try {
            const res = await fetch('index.php?action=get_init');
            const data = await res.json();
            
            // Set Store UI
            if(data.settings.shop_name) document.getElementById('display-brand-name').innerText = data.settings.shop_name.toUpperCase();
            if(data.settings.location) document.getElementById('display-location').innerText = "📍 " + data.settings.location;
            
            isAdminStatus = data.is_admin;
            allShoes = data.products;
            
            if(isAdminStatus) {
                currentKey = "naol123"; 
                showAdminUI();
            }
            
            renderShop(allShoes);
        } catch (e) {
            console.error("Load failed", e);
        }
    }

    // 2. Render Products (Bakka tokkotti qofa barreeffame)
    function renderShop(items) {
        const container = document.getElementById('shop-display');
        container.innerHTML = '';
        
        if(items.length === 0) {
            container.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:100px; opacity:0.5;"><h3>No products found.</h3></div>`;
            return;
        }

        items.forEach((item, i) => {
            const card = document.createElement('div');
            card.className = 'shoe-card';
            // Animation kee as jira - hin tuqamne
            card.style.animation = `popIn 0.5s ease forwards ${i * 0.1}s`;
            card.style.opacity = '0';
            
            card.innerHTML = `
                <div class="shoe-img-box">
                    <img src="${item.img_url}" onerror="this.src='https://via.placeholder.com/300x200?text=Sneaker'">
                </div>
                <div style="margin-bottom:15px;">
                    <h3 style="font-weight:700;">${item.name}</h3>
                    <p style="color:#B12704; font-size:1.4rem; font-weight:800;">ETB ${item.price}</p>
                </div>
                
                <div style="margin-bottom:15px;">
                    <label style="font-size:0.7rem; font-weight:800; color:#888;">SELECT SIZE</label>
                    <select id="size-${item.id}" class="shop-select" style="width:100%; padding:8px; margin-top:5px; border-radius:5px; border:1px solid #ddd;">
                        <option value="39">size 39</option>
                        <option value="40">size 40</option>
                        <option value="41">size 41</option>
                        <option value="42">size 42</option>
                        <option value="43">size 43</option>
                        <option value="44">size 44</option>
                    </select>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="font-size:0.7rem; font-weight:800; color:#888;">SELECT BANK</label>
                    <select id="bank-${item.id}" class="shop-select" style="width:100%; padding:8px; margin-top:5px; border-radius:5px; border:1px solid #ddd;">
                        <option value="CBE">CBE (Commercial Bank)</option>
                        <option value="CBO">CBO (Coop Bank)</option>
                        <option value="SINQEE">SINQEE Bank</option>
                        <option value="TELEBIRR">TELEBIRR</option>
                    </select>
                </div>

                <button class="btn-impulse" onclick="handlePurchase(${item.id}, '${item.name}', ${item.price})">BUY NOW</button>
                
                ${isAdminStatus ? `
                    <div style="display:flex; justify-content:space-between; margin-top:15px; padding-top:10px; border-top:1px solid #eee;">
                        <button onclick="deleteProduct(${item.id})" style="color:red; background:none; border:none; cursor:pointer; font-weight:bold;">Delete</button>
                        <button onclick="editProduct(${item.id})" style="color:blue; background:none; border:none; cursor:pointer; font-weight:bold;">Edit</button>
                    </div>
                ` : ''}
            `;
            container.appendChild(card);
        });
    }

async function handlePurchase(id, name, price) {
    const bank = document.getElementById(`bank-${id}`).value;
    const size = document.getElementById(`size-${id}`).value;

    const fd = new FormData();
    fd.append('action', 'place_order');
    fd.append('item_name', name);
    fd.append('price', price);
    fd.append('size', size);
    fd.append('bank', bank);

    try {
        const res = await fetch('index.php', { method: 'POST', body: fd });
        const result = await res.json();
        
        if(result.status === "success") {
            // 1. Show short pop-up message
            showToast("Order Sent!", "Naol will contact you soon.");
            
            // 2. Update the Notification Bell (🛎️) - This was missing!
            notify("NEW ORDER", `You ordered ${name} (Size: ${size}).`);

            // 3. Update the Order Cart (🛒)
            addToOrderHistory(name, size, bank);
        }
    } catch (e) {
        console.error("Purchase failed", e);
        notify("ERROR", "Something went wrong with your order.");
    }
}

/**
 * Function to update the Bell (🛎️) notification
 */
function notify(type, msg) {
    notifs++;
    const badge = document.getElementById('notif-badge');
    if(badge) {
        badge.innerText = notifs;
        badge.style.display = 'block'; // Make badge visible
    }
    
    const list = document.getElementById('notif-list');
    if(list) {
        const item = document.createElement('div');
        item.style = "padding:10px; background:#f0f7ff; margin-bottom:5px; border-radius:5px; font-size:0.8rem; color:black; border-left:4px solid #007bff;";
        item.innerHTML = `<strong>${type}</strong>: ${msg}`;
        list.prepend(item);
    }
}

/**
 * Function to update the Cart (🛒) history
 */
function addToOrderHistory(name, size, bank) {
    orders++;
    const badge = document.getElementById('order-badge');
    if(badge) {
        badge.innerText = orders;
        badge.style.display = 'block';
    }
    
    const hist = document.getElementById('order-history-list');
    if(hist) {
        if(orders === 1) hist.innerHTML = ''; // Clear "No orders" message
        const div = document.createElement('div');
        div.style = "padding:10px; border-bottom:1px solid #eee; font-size:0.85rem; color:black;";
        div.innerHTML = `
            <b>${name}</b><br>
            Size: ${size} | Bank: ${bank}<br>
            <small style="color:green;">Status: Processing</small>
        `;
        hist.prepend(div);
    }
}

/**
 * Function to show Toast notification
 */
function showToast(title, msg) {
    const t = document.getElementById('toast');
    if(t) {
        document.getElementById('toast-title').innerText = title;
        document.getElementById('toast-msg').innerText = msg;
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }
}
    // --- Admin Handlers ---
    function toggleAdmin() {
        const pass = prompt("Enter Admin Access Key:");
        if(pass === "naol123") {
            isAdminStatus = true;
            currentKey = pass;
            showAdminUI();
            renderShop(allShoes);
            notify("System", "Admin privileges granted.");
        } else if(pass) {
            alert("Unauthorized access attempt.");
        }
    }

    function showAdminUI() {
        document.getElementById('admin-panel').style.display = 'block';
        document.getElementById('setting-link').style.display = 'block';
        document.getElementById('logout-link').style.display = 'block';
        document.getElementById('login-link').style.display = 'none';
    }

    async function addShoe() {
        const name = document.getElementById('shoeModel').value;
        const price = document.getElementById('shoePrice').value;
        const stock = document.getElementById('shoeStock').value;
        const fileInput = document.getElementById('shoeFile');
        
        if(!name || !price || fileInput.files.length === 0) {
            alert("Maaloo hunda guuti, suuraas filadhu!");
            return;
        }

        const fd = new FormData();
        fd.append('add_shoe', '1');
        fd.append('name', name);
        fd.append('price', price);
        fd.append('stock', stock);
        fd.append('shoeFile', fileInput.files[0]);
        fd.append('admin_key', "naol123"); 

        await fetch('index.php', { method: 'POST', body: fd });
        location.reload(); 
    }

    async function deleteProduct(id) {
        if(confirm("Confirm deletion?")) {
            await fetch(`index.php?delete_id=${id}&key=${currentKey}`);
            init();
            notify("Inventory", "Product removed.");
        }
    }

function notify(type, msg) {
    notifs++;
    const badge = document.getElementById('notif-badge');
    if(badge) {
        badge.innerText = notifs;
        badge.style.display = 'inline-block'; // Akka mul'atu godha
    }
    
    const list = document.getElementById('notif-list');
    if(list) {
        const item = document.createElement('div');
        item.style = "padding:10px; background:#f0f7ff; margin-bottom:5px; border-radius:5px; font-size:0.8rem; border-left:4px solid #007bff;";
        item.innerHTML = `<strong>${type}</strong>: ${msg}`;
        list.prepend(item);
    }
}

function showToast(title, msg) {
    const t = document.getElementById('toast');
    if(t) {
        document.getElementById('toast-title').innerText = title;
        document.getElementById('toast-msg').innerText = msg;
        t.style.display = 'block';
        
        // Erga sekondii 3 booda akka dhokatu
        setTimeout(() => {
            t.style.display = 'none';
        }, 3000);
    }
}
// Sidebar banuuf fi cufuuf
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    if (sidebar) {
        sidebar.classList.toggle('active');
        // Main content yoo shifted gochuu barbaadde
        if (mainContent) {
            mainContent.classList.toggle('shifted');
        }
    }
}
    // Funktii kanaan Naol order haaraa ilaaluu danda'a
async function checkNewOrders() {
    if(!isAdminStatus) return; // Yoo admin ta'e qofa

    try {
        const res = await fetch('index.php?action=get_orders'); // API kana PHP keessatti dabalachuu qabda
        const orders = await res.json();
        
        if(orders.length > 0) {
            notify("ADMIN ALERT", `${orders.length} orders haaraa dhufeera!`);
            // Order badge irratti lakkoofsa ni daballa
            document.getElementById('notif-badge').innerText = orders.length;
        }
    } catch (e) {
        console.log("Admin sync failed");
    }
}

// Sekondii 30 30n akka check godhuuf
if(isAdminStatus) {
    setInterval(checkNewOrders, 30000);
}

// Yoo sidebar alatti tuqan akka cufamuuf
window.onclick = function(event) {
    const sidebar = document.getElementById('sidebar');
    if (event.target == sidebar) {
        sidebar.classList.remove('active');
    }
}
    function searchShoes() {
        const q = document.getElementById('search-input').value.toLowerCase();
        const filtered = allShoes.filter(s => s.name.toLowerCase().includes(q));
        renderShop(filtered);
    }
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    
    // Dropdown-oota biroo cufi
    const allDropdowns = document.querySelectorAll('.notif-dropdown');
    allDropdowns.forEach(d => {
        if (d.id !== id) d.style.display = 'none';
    });

    // Isa barbaadame bani ykn cufi
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        dropdown.style.display = 'block';
    }
}
    function openSettings() {
        const s = document.getElementById('settings-panel');
        if(s) s.style.display = s.style.display === 'none' ? 'block' : 'none';
    }

    window.onload = init;
</script>

<style>
    /* Animation kee as jira */
    @keyframes popIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .shop-select {
        background-color: white;
        color: #333;
        font-weight: 600;
        cursor: pointer;
    }
</style>
</body>
</html>

<?php
// --- SECTION 1: DATABASE & SESSION CONFIGURATION ---
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- DATABASE CONFIGURATION ---
$host = "mysql-11ead335-shelematolesa43-84db.g.aivencloud.com";
$user = "avnadmin";
$pass = 'AVNS_an3G9_uvEmH_QWK4EQx'; // Single quotes fayyadamuun filatamaadha
$db   = "defaultdb";
$port = 23454;

// 1. Initialize MySQLi
$conn = mysqli_init();

// 2. SSL Setup (Aiven-iif murteessaadha)
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

// 3. Connect gochuu
$success = mysqli_real_connect(
    $conn, 
    $host, 
    $user, 
    $pass, 
    $db, 
    $port, 
    NULL, 
    MYSQLI_CLIENT_SSL
);

if (!$success) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// --- SECTION 2: BACKEND API LOGIC ---

// 2.1 Helper Functions
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
        .sidebar { 
            width: var(--sidebar-width); 
            background: var(--nav-bg); 
            height: 100vh; 
            position: fixed; 
            left: calc(var(--sidebar-width) * -1); 
            top: 0; 
            padding: 2.5rem 1.5rem; 
            transition: 0.5s cubic-bezier(0.4, 0, 0.2, 1); 
            z-index: 2001; 
            color: white; 
            display: flex; 
            flex-direction: column; 
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
            top: 60px; 
            right: 0; 
            background: white; 
            width: 340px; 
            border-radius: 15px; 
            display: none; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.25); 
            padding: 20px; 
            z-index: 2000; 
            color: black;
            border: 1px solid #eee;
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
                <span id="order-badge" style="background:var(--cta); color:black; padding:2px 6px; border-radius:50%; font-size:0.7rem; position:absolute; top:-10px; right:-15px; display:none;">0</span>
                <div class="notif-dropdown" id="order-dropdown">
                    <h4 style="border-bottom: 2px solid var(--cta); padding-bottom: 10px;">Order History</h4>
                    <div id="order-history-list" style="margin-top:15px; max-height: 300px; overflow-y:auto;">
                        <p style="text-align:center; color:#999; font-size:0.8rem;">No orders yet.</p>
                    </div>
                </div>
            </div>
            <div style="cursor:pointer; position:relative;" onclick="toggleDropdown('notif-dropdown')">
                <span style="font-size:1.6rem;">🛎️</span>
                <span id="notif-badge" style="background:var(--cta); color:black; padding:2px 7px; border-radius:50%; font-size:0.7rem; position:absolute; top:0; right:-5px;">0</span>
                <div class="notif-dropdown" id="notif-dropdown">
                    <h4 style="border-bottom: 2px solid var(--cta); padding-bottom: 10px;">Notifications</h4>
                    <div id="notif-list" style="margin-top:10px;"></div>
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
                <input type="number" id="shoeStock" placeholder="Stock Quantity" class="admin-field" value="10">
                <div style="grid-column: 1 / -1;">
                    <label style="font-weight:600; font-size:0.8rem;">Product Image:</label>
                    <input type="file" id="shoeFile" class="admin-field" accept="image/*">
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
                currentKey = "naol123"; // Logic placeholder
                showAdminUI();
            }
            
            renderShop(allShoes);
        } catch (e) {
            console.error("Load failed", e);
        }
    }

    // 2. Render Products
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
                    <select id="size-${item.id}" class="admin-field" style="margin:5px 0;">
                        <option value="39">size 39</option>
                        <option value="40">size 40</option>
                        <option value="41">size 41</option>
                        <option value="42">size 42</option>
                        <option value="43">size 43</option>
                        <option value="44">size 44 (Limited)</option>
                    </select>
                </div>
<div style="margin-bottom:15px;">
                    <label style="font-size:0.7rem; font-weight:800; color:#888;">SELECT SIZE</label>
                    <select id="size-${item.id}" class="admin-field" style="margin:5px 0;">
                        <option value="CBE">CBE</option>
                        <option value="CBO">CBO</option>
                        <option value="SINQEE">SINQEE</option>
                        <option value="ABSINIA">ABSINIA</option>
                        <option value="AWASH">AWASH</option>
                        <option value="TELEBIR">TELEBIR</option>
                    </select>
                </div>
                <button class="btn-impulse" onclick="handlePurchase(${item.id}, '${item.name}', ${item.price})">BUY</button>
                
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

    // 3. Admin Handlers
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
        const file = document.getElementById('shoeFile').files[0];
        
        if(!name || !price) return alert("Missing data");

        const fd = new FormData();
        fd.append('add_shoe', '1');
        fd.append('admin_key', currentKey);
        fd.append('name', name);
        fd.append('price', price);
        fd.append('stock', document.getElementById('shoeStock').value);
        if(file) fd.append('shoeFile', file);

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

    // 4. Shop Features
    function handlePurchase(id, name, price) {
        const size = document.getElementById(`size-${id}`).value;
        orders++;
        document.getElementById('order-badge').innerText = orders;
        document.getElementById('order-badge').style.display = 'block';
        
        const hist = document.getElementById('order-history-list');
        if(orders === 1) hist.innerHTML = '';
        
        const div = document.createElement('div');
        div.style = "padding:10px; border-bottom:1px solid #eee; font-size:0.85rem;";
        div.innerHTML = `<b>${name}</b><br>Size: ${size} | ETB ${price}<br><small style="color:green;">Status: Processing</small>`;
        hist.prepend(div);
        
        notify("Order", `Successfully added ${name} to orders.`);
        showToast("Success", "Added to your orders!");
    }

    function notify(type, msg) {
        notifs++;
        document.getElementById('notif-badge').innerText = notifs;
        const list = document.getElementById('notif-list');
        const item = document.createElement('div');
        item.style = "padding:10px; background:#f0f7ff; margin-bottom:5px; border-radius:5px; font-size:0.8rem;";
        item.innerHTML = `<strong>${type}</strong>: ${msg}`;
        list.prepend(item);
    }

    function showToast(title, msg) {
        const t = document.getElementById('toast');
        document.getElementById('toast-title').innerText = title;
        document.getElementById('toast-msg').innerText = msg;
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3000);
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('mainContent').classList.toggle('shifted');
    }

    function toggleDropdown(id) {
        const d = document.getElementById(id);
        d.style.display = d.style.display === 'block' ? 'none' : 'block';
    }

    function searchShoes() {
        const q = document.getElementById('search-input').value.toLowerCase();
        const filtered = allShoes.filter(s => s.name.toLowerCase().includes(q));
        renderShop(filtered);
    }

    function openSettings() {
        const s = document.getElementById('settings-panel');
        s.style.display = s.style.display === 'none' ? 'block' : 'none';
    }
async function handlePurchase(id, name, price) {
    const size = document.getElementById(`size-${id}`).value;
    const bank = document.getElementById(`bank-${item.id}`).value; // Bank filatame

    // Gara PHP erguuf (Email akka ba'uuf)
    const fd = new FormData();
    fd.append('action', 'place_order');
    fd.append('item_name', name);
    fd.append('price', price);
    fd.append('size', size);
    fd.append('bank', bank);

    const res = await fetch('index.php', { method: 'POST', body: fd });
    const result = await res.json();

    if(result.status === "success") {
        notify("Email", "Order details sent to Naol.");
        showToast("Success", "Order sent to Naol's email!");
    }

    // Logic'n kanaan dura ture itti fufa (Order history irratti dabaluu)
    orders++;
    document.getElementById('order-badge').innerText = orders;
    // ... (koodii kee isa duraa)
}
    async function saveSettings() {
        const name = document.getElementById('set-shop-name').value;
        const loc = document.getElementById('set-location').value;
        
        const fd = new FormData();
        fd.append('update_settings', '1');
        if(name) fd.append('shop_name', name);
        if(loc) fd.append('location', loc);

        await fetch('index.php', { method: 'POST', body: fd });
        location.reload();
    }

    window.onload = init;
</script>


<style>
    @keyframes popIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }
</style>
</body>
</html>

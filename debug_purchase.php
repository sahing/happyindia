<?php
// Debug script to test purchase button functionality
session_start();

// Force local environment
$_SERVER['HTTP_HOST'] = 'localhost';

include 'includes/db_config.php';
include 'includes/language.php';

echo "<h1>Purchase Button Debug</h1>";

// Check session
echo "<h2>Session Check</h2>";
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ No user session found. Redirecting to login...</p>";
    echo "<script>window.location.href = 'login.php';</script>";
    exit();
} else {
    echo "<p style='color: green;'>✅ User session found: ID " . $_SESSION['user_id'] . ", Name: " . $_SESSION['user_name'] . "</p>";
}

// Check database connection
echo "<h2>Database Check</h2>";
$conn = getDBConnection();
if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Database connection successful</p>";
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_coins = $stmt->get_result()->fetch_assoc()['coins'];
echo "<p>Current user coins: ₹" . number_format($user_coins, 2) . "</p>";

// Get products
$products_result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}
echo "<p>Found " . count($products) . " products</p>";

// Test POST data
echo "<h2>POST Data Check</h2>";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<p style='color: blue;'>📨 POST request received</p>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    if (isset($_POST['purchase_product'])) {
        echo "<p style='color: green;'>✅ purchase_product button was clicked</p>";

        $product_id = (int)$_POST['product_id'];
        echo "<p>Product ID: $product_id</p>";

        // Get product details
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if ($product) {
            echo "<p style='color: green;'>✅ Product found: " . $product['name'] . " - ₹" . $product['price'] . "</p>";

            if ($user_coins >= $product['price']) {
                echo "<p style='color: green;'>✅ Sufficient balance for purchase</p>";
                echo "<p>Processing purchase...</p>";

                // Simulate purchase logic
                $new_balance = $user_coins - $product['price'];
                echo "<p>New balance after purchase: ₹" . number_format($new_balance, 2) . "</p>";

                echo "<p style='color: green;'>✅ Purchase simulation successful!</p>";
            } else {
                echo "<p style='color: red;'>❌ Insufficient balance (₹$user_coins < ₹{$product['price']})</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Product not found</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ purchase_product not set in POST data</p>";
    }
} else {
    echo "<p>Waiting for POST request...</p>";
}

// Show test form
echo "<h2>Test Purchase Form</h2>";
if (!empty($products)) {
    $product = $products[0]; // Test with first product
    echo "<div style='border: 1px solid #ccc; padding: 20px; margin: 20px 0;'>";
    echo "<h3>Test Product: {$product['name']}</h3>";
    echo "<p>Price: ₹{$product['price']}</p>";
    echo "<p>Your Balance: ₹" . number_format($user_coins, 2) . "</p>";

    echo "<form method='POST' style='margin-top: 20px;'>";
    echo "<input type='hidden' name='product_id' value='{$product['id']}'>";
    echo "<button type='submit' name='purchase_product' class='btn btn-primary'";
    if ($user_coins < $product['price']) {
        echo " disabled style='background: #ccc;'";
    }
    echo ">Test Purchase</button>";
    echo "</form>";
    echo "</div>";
}

closeDBConnection($conn);
?>
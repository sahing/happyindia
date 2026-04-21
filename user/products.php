<?php
session_start();
include '../includes/db_config.php';
include '../includes/language.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$currentLang = getCurrentLanguage();
$langSwitcher = getLanguageSwitcher();

$user_id = $_SESSION['user_id'];
$message = '';
$success = '';

// Handle product purchase
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['purchase_product'])) {
    $product_id = (int)$_POST['product_id'];

    $conn = getDBConnection();    // Get product details
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if ($product) {
        // Check if user has enough coins
        $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_coins_check = $stmt->get_result()->fetch_assoc()['coins'];

        if ($user_coins_check >= $product['price']) {
            // Deduct coins from user
            $new_balance = $user_coins - $product['price'];
            $stmt = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
            $stmt->bind_param("di", $new_balance, $user_id);
            $stmt->execute();
            
            // Record purchase
            $stmt = $conn->prepare("INSERT INTO purchases (user_id, product_id, amount) VALUES (?, ?, ?)");
            $stmt->bind_param("iid", $user_id, $product_id, $product['price']);
            $purchase_id = $stmt->execute() ? $conn->insert_id : 0;
            
            if ($purchase_id) {
                // Log wallet transaction for purchase
                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, reference_id, balance_after) VALUES (?, 'purchase', ?, ?, ?, ?)");
                $stmt->bind_param("idsid", $user_id, $product['price'], $product['name'], $purchase_id, $new_balance);
                $stmt->execute();
                
                // Distribute commissions
                distributeCommissions($conn, $user_id, $product['price'], $purchase_id);
                
                $success = __('product purchased successfully');
            } else {
                $message = __('purchase failed');
            }
        } else {
            $message = __('insufficient coins');
        }
    } else {
        $message = __('product not found');
    }
    
    closeDBConnection($conn);
}

// Get products
$conn = getDBConnection();
$products_result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}

// Get user coins
$stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_coins = $stmt->get_result()->fetch_assoc()['coins'];

closeDBConnection($conn);

// Commission distribution function
function distributeCommissions($conn, $buyer_id, $amount, $purchase_id) {
    // Get commission settings
    $commission_result = $conn->query("SELECT * FROM commission_settings ORDER BY level");
    $commissions = [];
    while ($row = $commission_result->fetch_assoc()) {
        $commissions[$row['level']] = $row['percentage'];
    }
    
    $current_user_id = $buyer_id;
    $level = 1;
    
    while ($level <= count($commissions) && $current_user_id) {
        // Find referrer
        $stmt = $conn->prepare("SELECT referrer_id FROM referrals WHERE referee_id = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $referrer_id = $result->fetch_assoc()['referrer_id'];
            
            if ($referrer_id && isset($commissions[$level])) {
                $commission_amount = ($amount * $commissions[$level]) / 100;
                
                // Get current balance of referrer
                $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
                $stmt->bind_param("i", $referrer_id);
                $stmt->execute();
                $referrer_balance = $stmt->get_result()->fetch_assoc()['coins'];
                $new_referrer_balance = $referrer_balance + $commission_amount;
                
                // Add commission to referrer
                $stmt = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
                $stmt->bind_param("di", $new_referrer_balance, $referrer_id);
                $stmt->execute();
                
                // Record commission
                $stmt = $conn->prepare("INSERT INTO commissions (purchase_id, recipient_user_id, amount, level) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iidi", $purchase_id, $referrer_id, $commission_amount, $level);
                $commission_id = $stmt->execute() ? $conn->insert_id : 0;
                
                // Log wallet transaction for commission
                if ($commission_id) {
                    $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, reference_id, balance_after) VALUES (?, 'commission', ?, ?, ?, ?)");
                    $description = "Commission from Level " . $level . " referral purchase";
                    $stmt->bind_param("idsid", $referrer_id, $commission_amount, $description, $commission_id, $new_referrer_balance);
                    $stmt->execute();
                }
                
                $current_user_id = $referrer_id;
                $level++;
            } else {
                break;
            }
        } else {
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('products'); ?> - <?php echo __('site_title'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    <style>
        :root {
            --primary-color: #e91e63;
            --secondary-color: #9c27b0;
        }
        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .product-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .product-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
        }
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .main-content {
            padding: 20px;
        }
        .language-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1002;
        }
        .coins-badge {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1003;
            padding: 10px 15px;
        }
        .mobile-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mobile-profile-dropdown .dropdown-menu {
            min-width: 250px;
        }
        .mobile-profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .mobile-profile-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary-color);
        }
        .mobile-coins {
            font-size: 0.8rem;
            color: #666;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                width: 250px;
                z-index: 1000;
                transition: left 0.3s;
                margin-top: 70px; /* Space for mobile header */
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                padding-top: 80px; /* Space for mobile header */
            }
            .menu-toggle {
                display: block !important;
                position: static;
                background: var(--primary-color);
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 5px;
                font-size: 1.1rem;
            }
            .mobile-header {
                display: block;
            }
            .language-switcher {
                display: none; /* Hide desktop language switcher on mobile */
            }
            .desktop-profile {
                display: none; /* Hide desktop profile on mobile */
            }
            .mobile-header .menu-toggle:hover {
                background: var(--secondary-color);
            }
        }
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <h1 class="h5 mb-0 text-center flex-grow-1"><?php echo __('products'); ?></h1>

            <div class="d-flex align-items-center gap-2">
                <!-- Mobile Profile Dropdown -->
                <div class="dropdown mobile-profile-dropdown">
                    <button class="btn btn-link text-decoration-none p-0" type="button" id="mobileProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="mobile-profile-info">
                            <div class="mobile-profile-name">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </div>
                            <div class="mobile-coins">
                                <i class="fas fa-coins"></i> <?php echo number_format($user_coins, 2); ?>
                            </div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileProfileDropdown">
                        <li><h6 class="dropdown-header"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?></a></li>
                        <li><a class="dropdown-item" href="products.php"><i class="fas fa-shopping-cart"></i> <?php echo __('products'); ?></a></li>
                        <li><a class="dropdown-item" href="referrals.php"><i class="fas fa-users"></i> <?php echo __('my referrals'); ?></a></li>
                        <li><a class="dropdown-item" href="earnings.php"><i class="fas fa-rupee-sign"></i> <?php echo __('earnings'); ?></a></li>
                        <li><a class="dropdown-item" href="withdrawals.php"><i class="fas fa-money-bill-wave"></i> <?php echo __('withdrawals'); ?></a></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> <?php echo __('profile'); ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?></a></li>
                    </ul>
                </div>

                <!-- Mobile Language Switcher -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="mobileLanguageDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-globe"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($langSwitcher as $code => $lang): ?>
                            <li><a class="dropdown-item <?php echo $lang['active'] ? 'active' : ''; ?>" href="<?php echo $lang['url']; ?>">
                                <?php echo $lang['name']; ?>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Language Switcher -->
    <div class="language-switcher">
        <div class="dropdown">
            <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown">
                <i class="fas fa-globe"></i> <?php echo __('language'); ?>
            </button>
            <ul class="dropdown-menu">
                <?php foreach ($langSwitcher as $code => $lang): ?>
                    <li><a class="dropdown-item <?php echo $lang['active'] ? 'active' : ''; ?>" href="<?php echo $lang['url']; ?>">
                        <?php echo $lang['name']; ?>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
                <div class="p-3">
                    <h4 class="mb-4">
                        <i class="fas fa-user"></i> <?php echo __('user_panel'); ?>
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?>
                        </a>
                        <a class="nav-link active" href="products.php">
                            <i class="fas fa-shopping-cart"></i> <?php echo __('products'); ?>
                        </a>
                        <a class="nav-link" href="referrals.php">
                            <i class="fas fa-users"></i> <?php echo __('my referrals'); ?>
                        </a>
                        <a class="nav-link" href="earnings.php">
                            <i class="fas fa-rupee-sign"></i> <?php echo __('earnings'); ?>
                        </a>
                        <a class="nav-link" href="withdrawals.php">
                            <i class="fas fa-money-bill-wave"></i> <?php echo __('withdrawals'); ?>
                        </a>
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user-edit"></i> <?php echo __('profile'); ?>
                        </a>
                        <hr class="my-3">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4 desktop-profile">
                    <h1 class="h3 mb-0"><?php echo __('products'); ?></h1>
                    <div class="d-flex align-items-center gap-3">
                        <div class="coins-badge">
                            <i class="fas fa-coins"></i> <?php echo number_format($user_coins, 2); ?> <?php echo __('coins'); ?>
                        </div>
                        <span class="text-muted"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Products Grid -->
                <div class="row">
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="dashboard-card product-card">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text flex-grow-1"><?php echo htmlspecialchars($product['description']); ?></p>
                                    <div class="product-price mb-3">₹<?php echo number_format($product['price'], 2); ?></div>
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="purchase_product" class="btn btn-primary w-100"
                                                <?php echo $user_coins < $product['price'] ? 'disabled' : ''; ?>>
                                            <i class="fas fa-shopping-cart"></i> <?php echo __('purchase'); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted"><?php echo __('no products available'); ?></h4>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.menu-toggle');
            const mobileHeader = document.querySelector('.mobile-header');
            if (!sidebar.contains(event.target) &&
                !toggle.contains(event.target) &&
                !mobileHeader.contains(event.target) &&
                window.innerWidth <= 768) {
                sidebar.classList.remove('show');
            }
        });

        // Handle mobile profile dropdown
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure mobile dropdowns work properly
            const mobileDropdowns = document.querySelectorAll('.mobile-header .dropdown-toggle');
            mobileDropdowns.forEach(dropdown => {
                dropdown.addEventListener('click', function(e) {
                    // Close sidebar if open when opening dropdown
                    if (window.innerWidth <= 768) {
                        document.getElementById('sidebar').classList.remove('show');
                    }
                });
            });
        });
    </script>
</body>
</html>
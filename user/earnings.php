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

$conn = getDBConnection();

// Get user's current coin balance
$stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_coins = $stmt->get_result()->fetch_assoc()['coins'];

// Get commission history
$stmt = $conn->prepare("
    SELECT c.amount, c.level, c.created_at, pr.name as product_name, u.name as buyer_name
    FROM commissions c
    JOIN purchases p ON c.purchase_id = p.id
    JOIN products pr ON p.product_id = pr.id
    JOIN users u ON p.user_id = u.id
    WHERE c.recipient_user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$commissions_result = $stmt->get_result();
$commissions = [];
$total_earnings = 0;

while ($row = $commissions_result->fetch_assoc()) {
    $commissions[] = $row;
    $total_earnings += $row['amount'];
}

// Get earnings by level
$earnings_by_level = [1 => 0, 2 => 0, 3 => 0];
foreach ($commissions as $commission) {
    if (isset($earnings_by_level[$commission['level']])) {
        $earnings_by_level[$commission['level']] += $commission['amount'];
    }
}

// Get recent purchases (for context)
$stmt = $conn->prepare("
    SELECT p.amount, p.purchase_date, pr.name as product_name
    FROM purchases p
    JOIN products pr ON p.product_id = pr.id
    WHERE p.user_id = ?
    ORDER BY p.purchase_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$purchases_result = $stmt->get_result();
$purchases = [];
while ($row = $purchases_result->fetch_assoc()) {
    $purchases[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('earnings'); ?> - <?php echo __('site_title'); ?></title>
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
        .stat-card {
            text-align: center;
            padding: 30px 20px;
        }
        .stat-number {
            font-size: 2.5rem;
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
        .level-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .level-1 { background: #28a745; color: white; }
        .level-2 { background: #ffc107; color: black; }
        .level-3 { background: #dc3545; color: white; }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                width: 250px;
                z-index: 1000;
                transition: left 0.3s;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block !important;
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
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Language Switcher -->
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
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-shopping-cart"></i> <?php echo __('products'); ?>
                        </a>
                        <a class="nav-link" href="referrals.php">
                            <i class="fas fa-users"></i> <?php echo __('my referrals'); ?>
                        </a>
                        <a class="nav-link active" href="earnings.php">
                            <i class="fas fa-rupee-sign"></i> <?php echo __('earnings'); ?>
                        </a>
                        <a class="nav-link" href="wallet.php">
                            <i class="fas fa-wallet"></i> <?php echo __('wallet'); ?>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0"><?php echo __('earnings'); ?></h1>
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge bg-success fs-6 px-3 py-2">
                            <i class="fas fa-coins"></i> <?php echo number_format($user_coins, 2); ?> <?php echo __('coins'); ?>
                        </div>
                        <span class="text-muted"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </div>
                </div>

                <!-- Earnings Overview -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-coins fa-2x text-success mb-2"></i>
                            <div class="stat-number">₹<?php echo number_format($total_earnings, 2); ?></div>
                            <div><?php echo __('total earnings'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-wallet fa-2x text-primary mb-2"></i>
                            <div class="stat-number">₹<?php echo number_format($user_coins, 2); ?></div>
                            <div><?php echo __('available balance'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                            <div class="stat-number"><?php echo count($commissions); ?></div>
                            <div><?php echo __('total commissions'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Earnings by Level -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="dashboard-card p-3">
                            <h5 class="text-center mb-3">
                                <span class="level-badge level-1"><?php echo __('level'); ?> 1</span>
                            </h5>
                            <div class="text-center">
                                <div class="h4 text-success">₹<?php echo number_format($earnings_by_level[1], 2); ?></div>
                                <small class="text-muted"><?php echo __('direct referrals'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-card p-3">
                            <h5 class="text-center mb-3">
                                <span class="level-badge level-2"><?php echo __('level'); ?> 2</span>
                            </h5>
                            <div class="text-center">
                                <div class="h4 text-warning">₹<?php echo number_format($earnings_by_level[2], 2); ?></div>
                                <small class="text-muted"><?php echo __('level 2 referrals'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-card p-3">
                            <h5 class="text-center mb-3">
                                <span class="level-badge level-3"><?php echo __('level'); ?> 3</span>
                            </h5>
                            <div class="text-center">
                                <div class="h4 text-danger">₹<?php echo number_format($earnings_by_level[3], 2); ?></div>
                                <small class="text-muted"><?php echo __('level 3 referrals'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Commission History -->
                <div class="dashboard-card p-4">
                    <h4><i class="fas fa-history"></i> <?php echo __('commission history'); ?></h4>
                    <?php if (empty($commissions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-coins fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted"><?php echo __('no commissions yet'); ?></h5>
                            <p class="text-muted"><?php echo __('earn commissions when your referrals make purchases'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="commissionsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo __('date'); ?></th>
                                        <th><?php echo __('buyer'); ?></th>
                                        <th><?php echo __('product'); ?></th>
                                        <th><?php echo __('level'); ?></th>
                                        <th><?php echo __('commission'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($commissions as $commission): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($commission['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($commission['buyer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($commission['product_name']); ?></td>
                                            <td>
                                                <span class="level-badge level-<?php echo $commission['level']; ?>">
                                                    <?php echo __('level'); ?> <?php echo $commission['level']; ?>
                                                </span>
                                            </td>
                                            <td class="text-success fw-bold">₹<?php echo number_format($commission['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Purchases -->
                <div class="dashboard-card p-4 mt-4">
                    <h4><i class="fas fa-shopping-cart"></i> <?php echo __('your purchases'); ?></h4>
                    <?php if (empty($purchases)): ?>
                        <div class="text-center py-3">
                            <small class="text-muted"><?php echo __('no purchases yet'); ?></small>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th><?php echo __('date'); ?></th>
                                        <th><?php echo __('product'); ?></th>
                                        <th><?php echo __('amount'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchases as $purchase): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($purchase['product_name']); ?></td>
                                            <td>₹<?php echo number_format($purchase['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
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
            if (!sidebar.contains(event.target) && !toggle.contains(event.target) && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
            }
        });

        // Initialize DataTable
        $(document).ready(function() {
            $('#commissionsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/<?php echo $currentLang == 'bn' ? 'bn' : 'en'; ?>.json'
                },
                order: [[0, 'desc']]
            });
        });
    </script>
</body>
</html>
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
$errors = [];
$success = '';

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['withdraw'])) {
    $amount = (float)$_POST['amount'];
    $payment_method = trim($_POST['payment_method']);
    $account_details = trim($_POST['account_details']);

    if ($amount <= 0) {
        $errors[] = __('invalid amount');
    }

    if (empty($errors)) {
        $conn = getDBConnection();

        // Check if user has enough earnings (simplified - count referrals)
        $stmt = $conn->prepare("SELECT COUNT(*) as referral_count FROM referrals WHERE referrer_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $referral_count = $result->fetch_assoc()['referral_count'];

        $earnings = floor($referral_count / 100) * 5000; // ₹5000 per 100 referrals

        if ($amount > $earnings) {
            $errors[] = __('insufficient earnings');
        } else {
            $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, payment_method, account_details) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idss", $user_id, $amount, $payment_method, $account_details);
            if ($stmt->execute()) {
                $success = __('withdrawal request submitted successfully');
            } else {
                $errors[] = __('request failed');
            }
        }

        $stmt->close();
        closeDBConnection($conn);
    }
}

// Get user data
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get payment status
$stmt = $conn->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

// Get referrals
$stmt = $conn->prepare("SELECT COUNT(*) as total_referrals FROM referrals WHERE referrer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$referrals = $stmt->get_result()->fetch_assoc();

// Get withdrawals
$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY requested_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$withdrawals_result = $stmt->get_result();
$withdrawals = [];
while ($row = $withdrawals_result->fetch_assoc()) {
    $withdrawals[] = $row;
}

closeDBConnection($conn);

// Calculate earnings
$referral_count = $referrals['total_referrals'];
$earnings = floor($referral_count / 100) * 5000;
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('user_dashboard_title'); ?> - <?php echo __('site_title'); ?></title>
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
        .menu-card {
            padding: 20px;
            text-align: center;
            height: 100%;
        }
        .menu-card i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?>
                        </a>
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-shopping-cart"></i> <?php echo __('products'); ?>
                        </a>
                        <a class="nav-link" href="referrals.php">
                            <i class="fas fa-users"></i> <?php echo __('my referrals'); ?>
                        </a>
                        <a class="nav-link" href="earnings.php">
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
                    <h1 class="h3 mb-0"><?php echo __('user_dashboard_title'); ?></h1>
                    <div>
                        <span class="text-muted"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <div class="stat-number"><?php echo $referral_count; ?></div>
                            <div><?php echo __('total referrals'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-rupee-sign fa-2x text-success mb-2"></i>
                            <div class="stat-number">₹<?php echo number_format($earnings); ?></div>
                            <div><?php echo __('total earnings'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-credit-card fa-2x text-info mb-2"></i>
                            <div class="stat-number">
                                <?php
                                if ($payment) {
                                    echo $payment['verified'] == 'verified' ? __('verified') : ($payment['verified'] == 'rejected' ? __('rejected') : __('pending'));
                                } else {
                                    echo __('no');
                                }
                                ?>
                            </div>
                            <div><?php echo __('payment status'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-share-alt fa-2x text-warning mb-2"></i>
                            <div class="stat-number"><?php echo htmlspecialchars($user['referral_id']); ?></div>
                            <div><?php echo __('referral id'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-shopping-cart"></i>
                            <h4><?php echo __('products'); ?></h4>
                            <p><?php echo __('browse and purchase products'); ?></p>
                            <a href="products.php" class="btn btn-primary"><?php echo __('go'); ?></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-users"></i>
                            <h4><?php echo __('my referrals'); ?></h4>
                            <p><?php echo __('view your referral network'); ?></p>
                            <a href="referrals.php" class="btn btn-primary"><?php echo __('go'); ?></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-rupee-sign"></i>
                            <h4><?php echo __('earnings'); ?></h4>
                            <p><?php echo __('track your earnings and commissions'); ?></p>
                            <a href="earnings.php" class="btn btn-primary"><?php echo __('go'); ?></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-money-bill-wave"></i>
                            <h4><?php echo __('withdrawals'); ?></h4>
                            <p><?php echo __('manage withdrawal requests'); ?></p>
                            <a href="withdrawals.php" class="btn btn-primary"><?php echo __('go'); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="dashboard-card p-4 mt-4">
                    <h4><i class="fas fa-history"></i> <?php echo __('recent activity'); ?></h4>
                    <div class="table-responsive">
                        <table id="activityTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo __('date'); ?></th>
                                    <th><?php echo __('activity'); ?></th>
                                    <th><?php echo __('amount'); ?></th>
                                    <th><?php echo __('status'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Show recent withdrawals
                                foreach (array_slice($withdrawals, 0, 5) as $withdrawal): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($withdrawal['requested_at'])); ?></td>
                                        <td><?php echo __('withdrawal request'); ?></td>
                                        <td>₹<?php echo number_format($withdrawal['amount']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $withdrawal['status'] == 'approved' ? 'success' :
                                                     ($withdrawal['status'] == 'rejected' ? 'danger' : 'warning');
                                            ?>">
                                                <?php echo $withdrawal['status'] == 'approved' ? __('approved') :
                                                           ($withdrawal['status'] == 'rejected' ? __('rejected') : __('pending')); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
            $('#activityTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/<?php echo $currentLang == 'bn' ? 'bn' : 'en'; ?>.json'
                }
            });
        });
    </script>
</body>
</html>
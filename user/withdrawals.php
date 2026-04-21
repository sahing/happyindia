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

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_withdrawal'])) {
    $amount = (float)$_POST['amount'];
    $payment_method = trim($_POST['payment_method']);
    $account_details = trim($_POST['account_details']);

    if ($amount <= 0) {
        $message = __('invalid amount');
    } elseif (empty($payment_method) || empty($account_details)) {
        $message = __('all fields required');
    } else {
        $conn = getDBConnection();

        // Check user's available coins
        $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_coins = $stmt->get_result()->fetch_assoc()['coins'];

        if ($amount > $user_coins) {
            $message = __('insufficient balance');
        } elseif ($amount < 500) { // Minimum withdrawal amount
            $message = __('minimum withdrawal amount is') . ' ₹500';
        } else {
            // Create withdrawal request
            $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, payment_method, account_details) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idss", $user_id, $amount, $payment_method, $account_details);

            if ($stmt->execute()) {
                // Deduct coins from user balance
                $new_balance = $user_coins - $amount;
                $stmt = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
                $stmt->bind_param("di", $new_balance, $user_id);
                $stmt->execute();

                // Log wallet transaction for withdrawal
                $withdrawal_id = $conn->insert_id;
                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, reference_id, balance_after) VALUES (?, 'withdrawal', ?, ?, ?, ?)");
                $description = "Withdrawal request - " . $payment_method;
                $stmt->bind_param("idsid", $user_id, $amount, $description, $withdrawal_id, $new_balance);
                $stmt->execute();

                $success = __('withdrawal request submitted successfully');
            } else {
                $message = __('request failed');
            }
        }

        closeDBConnection($conn);
    }
}

$conn = getDBConnection();

// Get user's current coin balance
$stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_coins = $stmt->get_result()->fetch_assoc()['coins'];

// Get withdrawal history
$stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY requested_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$withdrawals_result = $stmt->get_result();
$withdrawals = [];
$total_withdrawn = 0;
$pending_amount = 0;

while ($row = $withdrawals_result->fetch_assoc()) {
    $withdrawals[] = $row;
    $total_withdrawn += $row['amount'];
    if ($row['status'] == 'pending') {
        $pending_amount += $row['amount'];
    }
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('withdrawals'); ?> - <?php echo __('site_title'); ?></title>
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
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-pending { background: #ffc107; color: black; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
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
                        <a class="nav-link" href="earnings.php">
                            <i class="fas fa-rupee-sign"></i> <?php echo __('earnings'); ?>
                        </a>
                        <a class="nav-link" href="wallet.php">
                            <i class="fas fa-wallet"></i> <?php echo __('wallet'); ?>
                        </a>
                        <a class="nav-link active" href="withdrawals.php">
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
                    <h1 class="h3 mb-0"><?php echo __('withdrawals'); ?></h1>
                    <div class="d-flex align-items-center gap-3">
                        <div class="badge bg-success fs-6 px-3 py-2">
                            <i class="fas fa-coins"></i> ₹<?php echo number_format($user_coins, 2); ?> <?php echo __('available'); ?>
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

                <!-- Withdrawal Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-wallet fa-2x text-success mb-2"></i>
                            <div class="stat-number">₹<?php echo number_format($user_coins, 2); ?></div>
                            <div><?php echo __('available balance'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <div class="stat-number">₹<?php echo number_format($pending_amount, 2); ?></div>
                            <div><?php echo __('pending withdrawals'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                            <div class="stat-number">₹<?php echo number_format($total_withdrawn - $pending_amount, 2); ?></div>
                            <div><?php echo __('total withdrawn'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Request Withdrawal -->
                <div class="dashboard-card p-4 mb-4">
                    <h4><i class="fas fa-plus-circle"></i> <?php echo __('request withdrawal'); ?></h4>
                    <div class="row">
                        <div class="col-md-8">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="amount" class="form-label"><?php echo __('amount'); ?> (₹)</label>
                                        <input type="number" class="form-control" id="amount" name="amount" min="500"
                                               max="<?php echo $user_coins; ?>" step="0.01" required>
                                        <div class="form-text"><?php echo __('minimum withdrawal amount is') . ' ₹500'; ?></div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="payment_method" class="form-label"><?php echo __('payment method'); ?></label>
                                        <select class="form-select" id="payment_method" name="payment_method" required>
                                            <option value=""><?php echo __('select'); ?></option>
                                            <option value="UPI">UPI</option>
                                            <option value="Bank Transfer"><?php echo __('bank transfer'); ?></option>
                                            <option value="Paytm">Paytm</option>
                                            <option value="PhonePe">PhonePe</option>
                                            <option value="Google Pay">Google Pay</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="account_details" class="form-label"><?php echo __('account details'); ?></label>
                                        <input type="text" class="form-control" id="account_details" name="account_details"
                                               placeholder="UPI ID / Account Number" required>
                                    </div>
                                </div>
                                <button type="submit" name="request_withdrawal" class="btn btn-primary"
                                        <?php echo $user_coins < 500 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane"></i> <?php echo __('submit request'); ?>
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> <?php echo __('withdrawal rules'); ?></h6>
                                <ul class="mb-0 small">
                                    <li><?php echo __('minimum withdrawal amount is') . ' ₹500'; ?></li>
                                    <li><?php echo __('processing time is 1-3 business days'); ?></li>
                                    <li><?php echo __('funds will be credited to your provided account'); ?></li>
                                    <li><?php echo __('contact support if you have any issues'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal History -->
                <div class="dashboard-card p-4">
                    <h4><i class="fas fa-history"></i> <?php echo __('withdrawal history'); ?></h4>
                    <?php if (empty($withdrawals)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted"><?php echo __('no withdrawal requests yet'); ?></h5>
                            <p class="text-muted"><?php echo __('request your first withdrawal above'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="withdrawalsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo __('request date'); ?></th>
                                        <th><?php echo __('amount'); ?></th>
                                        <th><?php echo __('payment method'); ?></th>
                                        <th><?php echo __('account details'); ?></th>
                                        <th><?php echo __('status'); ?></th>
                                        <th><?php echo __('processed date'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawals as $withdrawal): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($withdrawal['requested_at'])); ?></td>
                                            <td class="fw-bold">₹<?php echo number_format($withdrawal['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($withdrawal['payment_method']); ?></td>
                                            <td><small><?php echo htmlspecialchars($withdrawal['account_details']); ?></small></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $withdrawal['status']; ?>">
                                                    <?php
                                                    switch($withdrawal['status']) {
                                                        case 'pending': echo __('pending'); break;
                                                        case 'approved': echo __('approved'); break;
                                                        case 'rejected': echo __('rejected'); break;
                                                        default: echo $withdrawal['status'];
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                if ($withdrawal['processed_at']) {
                                                    echo date('d/m/Y H:i', strtotime($withdrawal['processed_at']));
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
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
            $('#withdrawalsTable').DataTable({
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
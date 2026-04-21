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

// Handle wallet transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['transfer'])) {
    $recipient_referral_id = trim($_POST['recipient_referral_id']);
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);

    if (empty($recipient_referral_id)) {
        $message = __('recipient referral id is required');
    } elseif ($amount <= 0) {
        $message = __('invalid transfer amount');
    } elseif (empty($description)) {
        $message = __('transfer description is required');
    } else {
        $conn = getDBConnection();

        // Get sender's current balance
        $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $sender_balance = $stmt->get_result()->fetch_assoc()['coins'];

        if ($amount > $sender_balance) {
            $message = __('insufficient balance');
        } else {
            // Find recipient by referral ID
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE referral_id = ?");
            $stmt->bind_param("s", $recipient_referral_id);
            $stmt->execute();
            $recipient = $stmt->get_result()->fetch_assoc();

            if (!$recipient) {
                $message = __('recipient not found');
            } elseif ($recipient['id'] == $user_id) {
                $message = __('cannot transfer to yourself');
            } else {
                // Start transaction
                $conn->begin_transaction();

                try {
                    // Deduct from sender
                    $new_sender_balance = $sender_balance - $amount;
                    $stmt = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
                    $stmt->bind_param("di", $new_sender_balance, $user_id);
                    $stmt->execute();

                    // Add to recipient
                    $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
                    $stmt->bind_param("i", $recipient['id']);
                    $stmt->execute();
                    $recipient_balance = $stmt->get_result()->fetch_assoc()['coins'];
                    $new_recipient_balance = $recipient_balance + $amount;

                    $stmt = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
                    $stmt->bind_param("di", $new_recipient_balance, $recipient['id']);
                    $stmt->execute();

                    // Log sender transaction
                    $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, balance_after) VALUES (?, 'transfer_sent', ?, ?, ?)");
                    $stmt->bind_param("idss", $user_id, $amount, $description, $new_sender_balance);
                    $stmt->execute();

                    // Log recipient transaction
                    $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, balance_after) VALUES (?, 'transfer_received', ?, ?, ?)");
                    $stmt->bind_param("idss", $recipient['id'], $amount, $description, $new_recipient_balance);
                    $stmt->execute();

                    $conn->commit();
                    $success = __('transfer successful');
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = __('transfer failed');
                }
            }
        }

        closeDBConnection($conn);
    }
}

// Handle add money to wallet
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_deposit'])) {
    $amount = (float)$_POST['deposit_amount'];
    $description = trim($_POST['deposit_description']);
    $utr_number = trim($_POST['deposit_utr']);
    $qr_code_id = (int)$_POST['qr_code_id'];

    if ($amount <= 0) {
        $message = __('invalid amount');
    } elseif (empty($description)) {
        $message = __('description is required');
    } elseif (empty($utr_number)) {
        $message = __('utr_required');
    } elseif (empty($qr_code_id)) {
        $message = __('please select a payment method');
    } else {
        $conn = getDBConnection();

        // Handle screenshot upload
        $screenshot_path = null;
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file = $_FILES['payment_screenshot'];

            if (in_array($file['type'], $allowed_types) && $file['size'] <= 5 * 1024 * 1024) { // 5MB limit
                // Create uploads directory if not exists
                $upload_dir = '../assets/images/deposit_screenshots/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $filename = 'deposit_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $screenshot_path = $filename;
                }
            }
        }

        // Insert deposit request
        $stmt = $conn->prepare("INSERT INTO wallet_deposits (user_id, amount, description, screenshot_path, qr_code_id, utr_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idssis", $user_id, $amount, $description, $screenshot_path, $qr_code_id, $utr_number);

        if ($stmt->execute()) {
            $success = __('deposit request submitted successfully');
        } else {
            $message = __('failed to submit deposit request');
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

// Get wallet transaction history
$stmt = $conn->prepare("
    SELECT wt.*, u.name as related_user_name
    FROM wallet_transactions wt
    LEFT JOIN users u ON (
        CASE
            WHEN wt.transaction_type = 'transfer_sent' THEN wt.user_id != ?
            WHEN wt.transaction_type = 'transfer_received' THEN wt.user_id != ?
            ELSE FALSE
        END
    )
    WHERE wt.user_id = ?
    ORDER BY wt.created_at DESC
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$transactions_result = $stmt->get_result();
$transactions = [];
while ($row = $transactions_result->fetch_assoc()) {
    $transactions[] = $row;
}

// Get transaction statistics
$stmt = $conn->prepare("
    SELECT
        COUNT(CASE WHEN transaction_type = 'deposit' THEN 1 END) as total_deposits,
        COUNT(CASE WHEN transaction_type = 'withdrawal' THEN 1 END) as total_withdrawals,
        COUNT(CASE WHEN transaction_type = 'purchase' THEN 1 END) as total_purchases,
        COUNT(CASE WHEN transaction_type = 'commission' THEN 1 END) as total_commissions,
        COUNT(CASE WHEN transaction_type = 'transfer_sent' THEN 1 END) as total_transfers_sent,
        COUNT(CASE WHEN transaction_type = 'transfer_received' THEN 1 END) as total_transfers_received,
        SUM(CASE WHEN transaction_type IN ('deposit', 'commission', 'transfer_received') THEN amount END) as total_credited,
        SUM(CASE WHEN transaction_type IN ('withdrawal', 'purchase', 'transfer_sent') THEN amount END) as total_debited
    FROM wallet_transactions
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

closeDBConnection($conn);

// Get active QR codes for deposit modal
$conn = getDBConnection();
$qr_result = $conn->query("SELECT * FROM qr_codes WHERE is_active = 1 ORDER BY created_at DESC");
$active_qr_codes = [];
while ($row = $qr_result->fetch_assoc()) {
    $active_qr_codes[] = $row;
}
closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('wallet'); ?> - <?php echo __('site_title'); ?></title>
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
        .wallet-balance {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }
        .balance-amount {
            font-size: 3rem;
            font-weight: bold;
            margin: 10px 0;
        }
        .balance-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .transaction-card {
            border-left: 4px solid;
            margin-bottom: 10px;
        }
        .transaction-deposit { border-left-color: #28a745; }
        .transaction-withdrawal { border-left-color: #dc3545; }
        .transaction-purchase { border-left-color: #ffc107; }
        .transaction-commission { border-left-color: #17a2b8; }
        .transaction-transfer-sent { border-left-color: #6f42c1; }
        .transaction-transfer-received { border-left-color: #20c997; }
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
        .stat-box {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
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
                        <a class="nav-link active" href="wallet.php">
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
                    <h1 class="h3 mb-0"><?php echo __('wallet'); ?></h1>
                    <span class="text-muted"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Wallet Balance -->
                <div class="wallet-balance">
                    <i class="fas fa-wallet fa-3x mb-3"></i>
                    <div class="balance-label"><?php echo __('current balance'); ?></div>
                    <div class="balance-amount">₹<?php echo number_format($user_coins, 2); ?></div>
                    <div class="balance-label"><?php echo __('available coins'); ?></div>
                </div>

                <!-- Wallet Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $stats['total_deposits']; ?></span>
                            <span class="stat-label"><?php echo __('deposits'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $stats['total_withdrawals']; ?></span>
                            <span class="stat-label"><?php echo __('withdrawals'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $stats['total_purchases']; ?></span>
                            <span class="stat-label"><?php echo __('purchases'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $stats['total_commissions']; ?></span>
                            <span class="stat-label"><?php echo __('commissions'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $stats['total_transfers_sent']; ?></span>
                            <span class="stat-label"><?php echo __('sent'); ?></span>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-3">
                        <div class="stat-box">
                            <span class="stat-number"><?php echo $stats['total_transfers_received']; ?></span>
                            <span class="stat-label"><?php echo __('received'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Request Deposit -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card p-4">
                            <h4><i class="fas fa-plus-circle"></i> <?php echo __('request deposit'); ?></h4>
                            <p class="text-muted small"><?php echo __('deposit requests require admin approval'); ?></p>
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#depositModal">
                                <i class="fas fa-plus"></i> <?php echo __('request deposit'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Transfer Money -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card p-4">
                            <h4><i class="fas fa-exchange-alt"></i> <?php echo __('transfer money'); ?></h4>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="recipient_referral_id" class="form-label"><?php echo __('recipient referral id'); ?></label>
                                    <input type="text" class="form-control" id="recipient_referral_id" name="recipient_referral_id" required>
                                </div>
                                <div class="mb-3">
                                    <label for="amount" class="form-label"><?php echo __('amount'); ?> (₹)</label>
                                    <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label"><?php echo __('description'); ?></label>
                                    <input type="text" class="form-control" id="description" name="description" required>
                                </div>
                                <button type="submit" name="transfer" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane"></i> <?php echo __('transfer'); ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card p-4">
                            <h4><i class="fas fa-bolt"></i> <?php echo __('quick actions'); ?></h4>
                            <div class="d-grid gap-2">
                                <a href="products.php" class="btn btn-outline-primary">
                                    <i class="fas fa-shopping-cart"></i> <?php echo __('buy products'); ?>
                                </a>
                                <a href="withdrawals.php" class="btn btn-outline-success">
                                    <i class="fas fa-money-bill-wave"></i> <?php echo __('request withdrawal'); ?>
                                </a>
                                <a href="referrals.php" class="btn btn-outline-info">
                                    <i class="fas fa-users"></i> <?php echo __('view referrals'); ?>
                                </a>
                                <a href="earnings.php" class="btn btn-outline-warning">
                                    <i class="fas fa-chart-line"></i> <?php echo __('view earnings'); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Wallet Summary -->
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card p-4">
                            <h4><i class="fas fa-chart-pie"></i> <?php echo __('wallet summary'); ?></h4>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo __('total credited'); ?>:</span>
                                    <span class="text-success fw-bold">₹<?php echo number_format($stats['total_credited'], 2); ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo __('total debited'); ?>:</span>
                                    <span class="text-danger fw-bold">₹<?php echo number_format($stats['total_debited'], 2); ?></span>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <span><?php echo __('net balance'); ?>:</span>
                                <span class="fw-bold">₹<?php echo number_format($user_coins, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction History -->
                <div class="dashboard-card p-4">
                    <h4><i class="fas fa-history"></i> <?php echo __('transaction history'); ?></h4>
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted"><?php echo __('no transactions yet'); ?></h5>
                            <p class="text-muted"><?php echo __('your wallet transactions will appear here'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="transactionsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo __('date'); ?></th>
                                        <th><?php echo __('type'); ?></th>
                                        <th><?php echo __('description'); ?></th>
                                        <th><?php echo __('amount'); ?></th>
                                        <th><?php echo __('balance'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $transaction['transaction_type'] == 'deposit' ? 'success' :
                                                         ($transaction['transaction_type'] == 'withdrawal' ? 'danger' :
                                                         ($transaction['transaction_type'] == 'purchase' ? 'warning' :
                                                         ($transaction['transaction_type'] == 'commission' ? 'info' :
                                                         ($transaction['transaction_type'] == 'transfer_sent' ? 'secondary' : 'primary'))));
                                                ?>">
                                                    <?php
                                                    echo $transaction['transaction_type'] == 'deposit' ? __('deposit') :
                                                         ($transaction['transaction_type'] == 'withdrawal' ? __('withdrawal') :
                                                         ($transaction['transaction_type'] == 'purchase' ? __('purchase') :
                                                         ($transaction['transaction_type'] == 'commission' ? __('commission') :
                                                         ($transaction['transaction_type'] == 'transfer_sent' ? __('transfer sent') : __('transfer received')))));
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td class="<?php echo in_array($transaction['transaction_type'], ['deposit', 'commission', 'transfer_received']) ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                <?php echo in_array($transaction['transaction_type'], ['deposit', 'commission', 'transfer_received']) ? '+' : '-'; ?>₹<?php echo number_format($transaction['amount'], 2); ?>
                                            </td>
                                            <td>₹<?php echo number_format($transaction['balance_after'], 2); ?></td>
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

    <!-- Deposit Request Modal -->
    <div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="depositModalLabel"><?php echo __('request wallet deposit'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><?php echo __('select payment method'); ?></h6>
                                <div class="mb-3">
                                    <?php if (empty($active_qr_codes)): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> <?php echo __('no payment methods available'); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($active_qr_codes as $qr): ?>
                                                <div class="col-12 mb-3">
                                                    <div class="card qr-card" data-qr-id="<?php echo $qr['id']; ?>" style="cursor: pointer;">
                                                        <div class="card-body text-center p-3">
                                                            <img src="../assets/images/qr_codes/<?php echo htmlspecialchars($qr['filename']); ?>"
                                                                 alt="QR Code" class="img-fluid mb-2" style="max-height: 200px; max-width: 200px;">
                                                            <div class="form-check">
                                                                <input class="form-check-input qr-radio" type="radio" name="qr_code_id"
                                                                       value="<?php echo $qr['id']; ?>" id="qr_<?php echo $qr['id']; ?>" required>
                                                                <label class="form-check-label" for="qr_<?php echo $qr['id']; ?>">
                                                                    Select this payment method
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6><?php echo __('deposit details'); ?></h6>
                                <div class="mb-3">
                                    <label for="deposit_amount" class="form-label"><?php echo __('amount'); ?> (₹)</label>
                                    <input type="number" class="form-control" id="deposit_amount" name="deposit_amount" min="1" step="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label for="deposit_description" class="form-label"><?php echo __('description'); ?></label>
                                    <input type="text" class="form-control" id="deposit_description" name="deposit_description"
                                           placeholder="<?php echo __('e.g. bank deposit'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="deposit_utr" class="form-label"><?php echo __('utr_number'); ?></label>
                                    <input type="text" class="form-control" id="deposit_utr" name="deposit_utr"
                                           placeholder="<?php echo __('utr_placeholder'); ?>" maxlength="100" required>
                                    <div class="form-text"><?php echo __('utr_help_text'); ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_screenshot" class="form-label"><?php echo __('payment screenshot'); ?></label>
                                    <input type="file" class="form-control" id="payment_screenshot" name="payment_screenshot"
                                           accept="image/*" required>
                                    <div class="form-text"><?php echo __('upload payment proof screenshot'); ?> (Max 5MB)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" name="request_deposit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> <?php echo __('submit request'); ?>
                        </button>
                    </div>
                </form>
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
            $('#transactionsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/<?php echo $currentLang == 'bn' ? 'bn' : 'en'; ?>.json'
                },
                order: [[0, 'desc']]
            });

            // QR Code selection
            $('.qr-card').click(function() {
                const qrId = $(this).data('qr-id');
                $('.qr-radio').prop('checked', false);
                $('#qr_' + qrId).prop('checked', true);
                $('.qr-card').removeClass('border-primary');
                $(this).addClass('border-primary');
            });
        });
    </script>
</body>
</html>
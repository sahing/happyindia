<?php
session_start();
include '../includes/db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $payment_id = (int)$_POST['payment_id'];
    $action = $_POST['action'];

    $conn = getDBConnection();
    if ($action == 'verify') {
    $utr_number = trim($_POST['utr_number'] ?? '');
    $stmt = $conn->prepare("UPDATE payments SET verified = 'verified', verified_at = NOW(), utr_number = ? WHERE id = ?");
    $stmt->bind_param("si", $utr_number, $payment_id);
    $stmt->execute();

        // Update user payment status
        $stmt = $conn->prepare("UPDATE users SET payment_status = 'verified' WHERE id = (SELECT user_id FROM payments WHERE id = ?)");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();

        $message = 'পেমেন্ট ভেরিফাই করা হয়েছে';
    } elseif ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE payments SET verified = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE users SET payment_status = 'rejected' WHERE id = (SELECT user_id FROM payments WHERE id = ?)");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();

        $message = 'পেমেন্ট রিজেক্ট করা হয়েছে';
    }
    closeDBConnection($conn);
}

// Handle wallet deposit actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deposit_action'])) {
    $deposit_id = (int)$_POST['deposit_id'];
    $action = $_POST['deposit_action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    $utr_number = trim($_POST['deposit_utr_number'] ?? '');

    $conn = getDBConnection();

    if ($action == 'approve') {
        // Get deposit details
        $stmt = $conn->prepare("SELECT * FROM wallet_deposits WHERE id = ?");
        $stmt->bind_param("i", $deposit_id);
        $stmt->execute();
        $deposit = $stmt->get_result()->fetch_assoc();

        if ($deposit && $deposit['status'] == 'pending') {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Update deposit status
                $stmt = $conn->prepare("UPDATE wallet_deposits SET status = 'approved', admin_notes = ?, utr_number = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
                $stmt->bind_param("ssii", $admin_notes, $utr_number, $_SESSION['admin_id'], $deposit_id);
                $stmt->execute();

                // Add amount to user balance
                $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
                $stmt->bind_param("i", $deposit['user_id']);
                $stmt->execute();
                $current_balance = $stmt->get_result()->fetch_assoc()['coins'];

                $new_balance = $current_balance + $deposit['amount'];
                $stmt = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
                $stmt->bind_param("di", $new_balance, $deposit['user_id']);
                $stmt->execute();

                // Log transaction
                $description = $deposit['description'];
                if (!empty($utr_number)) {
                    $description .= " (UTR: " . $utr_number . ")";
                }
                $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, balance_after) VALUES (?, 'deposit', ?, ?, ?)");
                $stmt->bind_param("idss", $deposit['user_id'], $deposit['amount'], $description, $new_balance);
                $stmt->execute();

                $conn->commit();
                $message = 'ওয়ালেট ডিপোজিট অনুমোদিত করা হয়েছে';
            } catch (Exception $e) {
                $conn->rollback();
                $message = 'ওয়ালেট ডিপোজিট অনুমোদন করতে ব্যর্থ';
            }
        }
    } elseif ($action == 'reject') {
        $stmt = $conn->prepare("UPDATE wallet_deposits SET status = 'rejected', admin_notes = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt->bind_param("sii", $admin_notes, $_SESSION['admin_id'], $deposit_id);
        $stmt->execute();
        $message = 'ওয়ালেট ডিপোজিট রিজেক্ট করা হয়েছে';
    }

    closeDBConnection($conn);
}

// Get pending payments (registration payments)
$conn = getDBConnection();
$result = $conn->query("SELECT p.*, u.name, u.mobile FROM payments p JOIN users u ON p.user_id = u.id WHERE p.verified = 'pending' ORDER BY p.created_at DESC");
$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

// Get pending wallet deposit requests
$result = $conn->query("
    SELECT wd.*, u.name, u.mobile, qc.original_name as qr_name
    FROM wallet_deposits wd
    JOIN users u ON wd.user_id = u.id
    LEFT JOIN qr_codes qc ON wd.qr_code_id = qc.id
    WHERE wd.status = 'pending'
    ORDER BY wd.requested_at DESC
");
$wallet_deposits = [];
while ($row = $result->fetch_assoc()) {
    $wallet_deposits[] = $row;
}

// Get payment history (all payments and deposits)
$result = $conn->query("
    SELECT 
        'registration' as payment_type,
        p.id,
        p.user_id,
        u.name,
        u.mobile,
        p.amount,
        p.verified as status,
        p.utr_number,
        p.created_at as request_date,
        p.verified_at as processed_date,
        NULL as qr_name,
        NULL as admin_notes
    FROM payments p
    JOIN users u ON p.user_id = u.id
    UNION ALL
    SELECT 
        'wallet_deposit' as payment_type,
        wd.id,
        wd.user_id,
        u.name,
        u.mobile,
        wd.amount,
        wd.status,
        wd.utr_number,
        wd.requested_at as request_date,
        wd.processed_at as processed_date,
        qc.original_name as qr_name,
        wd.admin_notes
    FROM wallet_deposits wd
    JOIN users u ON wd.user_id = u.id
    LEFT JOIN qr_codes qc ON wd.qr_code_id = qc.id
    ORDER BY request_date DESC
");
$payment_history = [];
while ($row = $result->fetch_assoc()) {
    $payment_history[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>পেমেন্ট ম্যানেজমেন্ট - অ্যাডমিন</title>
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
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
        }
        .screenshot-img {
            max-width: 100px;
            max-height: 100px;
            cursor: pointer;
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

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
                <div class="p-3">
                    <h4 class="mb-4">
                        <i class="fas fa-user-shield"></i> অ্যাডমিন
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> ড্যাশবোর্ড
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> ইউজার ম্যানেজমেন্ট
                        </a>
                        <a class="nav-link active" href="payments.php">
                            <i class="fas fa-credit-card"></i> পেমেন্ট ম্যানেজমেন্ট
                        </a>
                        <a class="nav-link" href="withdrawals.php">
                            <i class="fas fa-money-bill-wave"></i> উত্তোলন ম্যানেজমেন্ট
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog"></i> সেটিংস
                        </a>
                        <hr class="my-3">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> লগআউট
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">পেমেন্ট ম্যানেজমেন্ট</h1>
                    <div>
                        <span class="text-muted">স্বাগতম, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="paymentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="registration-tab" data-bs-toggle="tab" data-bs-target="#registration" type="button" role="tab" aria-controls="registration" aria-selected="true">
                                    <i class="fas fa-user-plus"></i> রেজিস্ট্রেশন পেমেন্ট (<?php echo count($payments); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="wallet-tab" data-bs-toggle="tab" data-bs-target="#wallet" type="button" role="tab" aria-controls="wallet" aria-selected="false">
                                    <i class="fas fa-wallet"></i> ওয়ালেট ডিপোজিট (<?php echo count($wallet_deposits); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                                    <i class="fas fa-history"></i> পেমেন্ট হিস্টোরি
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="paymentTabsContent">
                            <!-- Registration Payments Tab -->
                            <div class="tab-pane fade show active" id="registration" role="tabpanel" aria-labelledby="registration-tab">
                                <?php if (empty($payments)): ?>
                                    <p class="text-muted">কোন পেন্ডিং রেজিস্ট্রেশন পেমেন্ট নেই</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="paymentsTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ইউজার</th>
                                                    <th>পরিমাণ</th>
                                                    <th>স্ক্রিনশট</th>
                                                    <th>UTR নম্বর</th>
                                                    <th>আপলোড তারিখ</th>
                                                    <th>অ্যাকশন</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($payments as $payment): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($payment['name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($payment['mobile']); ?></small>
                                                        </td>
                                                        <td>₹<?php echo number_format($payment['amount']); ?></td>
                                                        <td>
                                                            <?php if ($payment['screenshot_path']): ?>
                                                                <img src="../assets/images/screenshots/<?php echo htmlspecialchars($payment['screenshot_path']); ?>"
                                                                     alt="Screenshot" class="screenshot-img img-thumbnail"
                                                                     onclick="showImage(this.src)">
                                                            <?php else: ?>
                                                                <span class="text-muted">না</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($payment['utr_number'])): ?>
                                                                <span class="text-success fw-bold"><?php echo htmlspecialchars($payment['utr_number']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                                                        <td>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                                <input type="text" name="utr_number" class="form-control form-control-sm mb-1" placeholder="UTR নম্বর" style="max-width:120px;display:inline-block;" required>
                                                                <button type="submit" name="action" value="verify" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-check"></i> ভেরিফাই
                                                                </button>
                                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-times"></i> রিজেক্ট
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Wallet Deposits Tab -->
                            <div class="tab-pane fade" id="wallet" role="tabpanel" aria-labelledby="wallet-tab">
                                <?php if (empty($wallet_deposits)): ?>
                                    <p class="text-muted">কোন পেন্ডিং ওয়ালেট ডিপোজিট রিকোয়েস্ট নেই</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="depositsTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ইউজার</th>
                                                    <th>পরিমাণ</th>
                                                    <th>পেমেন্ট মেথড</th>
                                                    <th>স্ক্রিনশট</th>
                                                    <th>UTR নম্বর</th>
                                                    <th>বর্ণনা</th>
                                                    <th>আবেদন তারিখ</th>
                                                    <th>অ্যাকশন</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($wallet_deposits as $deposit): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($deposit['name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($deposit['mobile']); ?></small>
                                                        </td>
                                                        <td>₹<?php echo number_format($deposit['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($deposit['qr_name'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <?php if ($deposit['screenshot_path']): ?>
                                                                <img src="../assets/images/deposit_screenshots/<?php echo htmlspecialchars($deposit['screenshot_path']); ?>"
                                                                     alt="Screenshot" class="screenshot-img img-thumbnail"
                                                                     onclick="showImage(this.src)">
                                                            <?php else: ?>
                                                                <span class="text-muted">না</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($deposit['description']); ?></td>
                                                        <td>
                                                            <?php if (!empty($deposit['utr_number'])): ?>
                                                                <span class="text-success fw-bold"><?php echo htmlspecialchars($deposit['utr_number']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($deposit['requested_at'])); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-success mb-1"
                                                                    onclick="showApprovalModal(<?php echo $deposit['id']; ?>, 'approve')">
                                                                <i class="fas fa-check"></i> অনুমোদন
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger"
                                                                    onclick="showApprovalModal(<?php echo $deposit['id']; ?>, 'reject')">
                                                                <i class="fas fa-times"></i> রিজেক্ট
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Payment History Tab -->
                            <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                                <?php if (empty($payment_history)): ?>
                                    <p class="text-muted">কোন পেমেন্ট হিস্টোরি নেই</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="historyTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>পেমেন্ট টাইপ</th>
                                                    <th>ইউজার</th>
                                                    <th>পরিমাণ</th>
                                                    <th>স্ট্যাটাস</th>
                                                    <th>UTR নম্বর</th>
                                                    <th>পেমেন্ট মেথড</th>
                                                    <th>আবেদন তারিখ</th>
                                                    <th>প্রসেস তারিখ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($payment_history as $payment): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if ($payment['payment_type'] == 'registration'): ?>
                                                                <span class="badge bg-primary">রেজিস্ট্রেশন</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-info">ওয়ালেট ডিপোজিট</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($payment['name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($payment['mobile']); ?></small>
                                                        </td>
                                                        <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                                                        <td>
                                                            <?php
                                                            $status_class = '';
                                                            $status_text = '';
                                                            if ($payment['status'] == 'verified' || $payment['status'] == 'approved') {
                                                                $status_class = 'success';
                                                                $status_text = 'অনুমোদিত';
                                                            } elseif ($payment['status'] == 'rejected') {
                                                                $status_class = 'danger';
                                                                $status_text = 'রিজেক্ট';
                                                            } elseif ($payment['status'] == 'pending') {
                                                                $status_class = 'warning';
                                                                $status_text = 'পেন্ডিং';
                                                            }
                                                            ?>
                                                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($payment['utr_number'])): ?>
                                                                <span class="text-success fw-bold"><?php echo htmlspecialchars($payment['utr_number']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($payment['qr_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($payment['request_date'])); ?></td>
                                                        <td>
                                                            <?php if ($payment['processed_date']): ?>
                                                                <?php echo date('d/m/Y H:i', strtotime($payment['processed_date'])); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
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
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approvalModalTitle">ওয়ালেট ডিপোজিট অনুমোদন</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="deposit_id" id="modal_deposit_id">
                        <input type="hidden" name="deposit_action" id="modal_action">
                        <div class="mb-3" id="utrField" style="display: none;">
                            <label for="deposit_utr_number" class="form-label">UTR নম্বর</label>
                            <input type="text" class="form-control" id="deposit_utr_number" name="deposit_utr_number"
                                   placeholder="পেমেন্টের UTR নম্বর লিখুন" maxlength="100">
                            <div class="form-text">অনুমোদনের জন্য UTR নম্বর প্রয়োজন</div>
                        </div>
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">অ্যাডমিন নোটস (অপশনাল)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3"
                                      placeholder="অনুমোদন/রিজেকশনের কারণ লিখুন..."></textarea>
                        </div>
                        <p id="confirmationText">আপনি কি এই ওয়ালেট ডিপোজিট রিকোয়েস্ট অনুমোদন করতে চান?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                        <button type="submit" class="btn" id="modalSubmitBtn">অনুমোদন করুন</button>
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
        $(document).ready(function() {
            $('#paymentsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: 4 },
                    { responsivePriority: 3, targets: 1 }
                ],
                order: [[3, 'desc']] // Sort by upload date descending
            });

            $('#depositsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: 6 },
                    { responsivePriority: 3, targets: 1 }
                ],
                order: [[5, 'desc']] // Sort by request date descending
            });

            $('#historyTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 1 },
                    { responsivePriority: 2, targets: 3 },
                    { responsivePriority: 3, targets: 2 }
                ],
                order: [[6, 'desc']] // Sort by request date descending
            });
        });

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

        function showImage(src) {
            document.getElementById('modalImage').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        function showApprovalModal(depositId, action) {
            document.getElementById('modal_deposit_id').value = depositId;
            document.getElementById('modal_action').value = action;

            const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
            const submitBtn = document.getElementById('modalSubmitBtn');
            const title = document.getElementById('approvalModalTitle');
            const confirmationText = document.getElementById('confirmationText');
            const utrField = document.getElementById('utrField');
            const utrInput = document.getElementById('deposit_utr_number');

            if (action === 'approve') {
                title.textContent = 'ওয়ালেট ডিপোজিট অনুমোদন';
                confirmationText.textContent = 'আপনি কি এই ওয়ালেট ডিপোজিট রিকোয়েস্ট অনুমোদন করতে চান? এতে ইউজারের ওয়ালেটে টাকা যোগ হবে।';
                submitBtn.textContent = 'অনুমোদন করুন';
                submitBtn.className = 'btn btn-success';
                utrField.style.display = 'block';
                utrInput.required = true;
                utrInput.value = '';
            } else {
                title.textContent = 'ওয়ালেট ডিপোজিট রিজেক্ট';
                confirmationText.textContent = 'আপনি কি এই ওয়ালেট ডিপোজিট রিকোয়েস্ট রিজেক্ট করতে চান?';
                submitBtn.textContent = 'রিজেক্ট করুন';
                submitBtn.className = 'btn btn-danger';
                utrField.style.display = 'none';
                utrInput.required = false;
            }

            modal.show();
        }
    </script>
</body>
</html>
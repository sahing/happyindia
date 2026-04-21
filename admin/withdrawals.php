<?php
session_start();
include '../includes/db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $withdrawal_id = (int)$_POST['withdrawal_id'];
    $action = $_POST['action'];

    $conn = getDBConnection();

    // Get withdrawal details
    $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE id = ?");
    $stmt->bind_param("i", $withdrawal_id);
    $stmt->execute();
    $withdrawal = $stmt->get_result()->fetch_assoc();

    if ($withdrawal) {
        if ($action == 'approve') {
            // Update withdrawal status
            $stmt = $conn->prepare("UPDATE withdrawals SET status = 'approved', processed_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $withdrawal_id);
            $stmt->execute();

            // Log wallet transaction for approved withdrawal (already deducted when requested)
            $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, reference_id, balance_after) VALUES (?, 'withdrawal', ?, ?, ?, (SELECT coins FROM users WHERE id = ?))");
            $description = "Withdrawal approved - " . $withdrawal['payment_method'];
            $stmt->bind_param("idsii", $withdrawal['user_id'], $withdrawal['amount'], $description, $withdrawal_id, $withdrawal['user_id']);
            $stmt->execute();

            $message = 'উত্তোলন অনুমোদন করা হয়েছে';
        } elseif ($action == 'reject') {
            // Update withdrawal status
            $stmt = $conn->prepare("UPDATE withdrawals SET status = 'rejected', processed_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $withdrawal_id);
            $stmt->execute();

            // Return coins to user
            $stmt = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
            $stmt->bind_param("di", $withdrawal['amount'], $withdrawal['user_id']);
            $stmt->execute();

            // Get new balance
            $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
            $stmt->bind_param("i", $withdrawal['user_id']);
            $stmt->execute();
            $new_balance = $stmt->get_result()->fetch_assoc()['coins'];

            // Log wallet transaction for rejected withdrawal (refund)
            $stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, transaction_type, amount, description, reference_id, balance_after) VALUES (?, 'deposit', ?, ?, ?, ?)");
            $description = "Withdrawal rejected - refund - " . $withdrawal['payment_method'];
            $stmt->bind_param("idsid", $withdrawal['user_id'], $withdrawal['amount'], $description, $withdrawal_id, $new_balance);
            $stmt->execute();

            $message = 'উত্তোলন রিজেক্ট করা হয়েছে এবং কয়েন রিফান্ড করা হয়েছে';
        }
    }

    closeDBConnection($conn);
}

// Get pending withdrawals
$conn = getDBConnection();
$pending_result = $conn->query("SELECT w.*, u.name, u.mobile FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.status = 'pending' ORDER BY w.requested_at DESC");
$pending_withdrawals = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_withdrawals[] = $row;
}

// Get all withdrawals history
$all_result = $conn->query("SELECT w.*, u.name, u.mobile FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY w.requested_at DESC");
$all_withdrawals = [];
while ($row = $all_result->fetch_assoc()) {
    $all_withdrawals[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>উত্তোলন ম্যানেজমেন্ট - অ্যাডমিন</title>
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
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card"></i> পেমেন্ট ভেরিফিকেশন
                        </a>
                        <a class="nav-link active" href="withdrawals.php">
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
                    <h1 class="h3 mb-0">উত্তোলন ম্যানেজমেন্ট</h1>
                    <div>
                        <span class="text-muted">স্বাগতম, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>

                <!-- Withdrawal Management Tabs -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="withdrawalTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                                    <i class="fas fa-clock"></i> পেন্ডিং উত্তোলন (<?php echo count($pending_withdrawals); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                                    <i class="fas fa-history"></i> উত্তোলন ইতিহাস (<?php echo count($all_withdrawals); ?>)
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="withdrawalTabsContent">
                            <!-- Pending Withdrawals Tab -->
                            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                                <h5 class="mb-3">পেন্ডিং উত্তোলন অনুরোধ</h5>
                                <?php if (empty($pending_withdrawals)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h5 class="text-muted">কোন পেন্ডিং উত্তোলন নেই</h5>
                                        <p class="text-muted">সব উত্তোলন অনুরোধ প্রসেস করা হয়েছে</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="pendingWithdrawalsTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ইউজার</th>
                                                    <th>পরিমাণ</th>
                                                    <th>পেমেন্ট মেথড</th>
                                                    <th>অ্যাকাউন্ট বিস্তারিত</th>
                                                    <th>অনুরোধ তারিখ</th>
                                                    <th>অ্যাকশন</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_withdrawals as $withdrawal): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($withdrawal['name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($withdrawal['mobile']); ?></small>
                                                        </td>
                                                        <td class="fw-bold">₹<?php echo number_format($withdrawal['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($withdrawal['payment_method']); ?></td>
                                                        <td><?php echo htmlspecialchars($withdrawal['account_details']); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($withdrawal['requested_at'])); ?></td>
                                                        <td>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">
                                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success me-1">
                                                                    <i class="fas fa-check"></i> অনুমোদন
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

                            <!-- Withdrawal History Tab -->
                            <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                                <h5 class="mb-3">সব উত্তোলন ইতিহাস</h5>
                                <?php if (empty($all_withdrawals)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">কোন উত্তোলন ইতিহাস নেই</h5>
                                        <p class="text-muted">এখনও কোন উত্তোলন অনুরোধ করা হয়নি</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table id="allWithdrawalsTable" class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>ইউজার</th>
                                                    <th>পরিমাণ</th>
                                                    <th>পেমেন্ট মেথড</th>
                                                    <th>অ্যাকাউন্ট বিস্তারিত</th>
                                                    <th>অনুরোধ তারিখ</th>
                                                    <th>স্ট্যাটাস</th>
                                                    <th>প্রসেস তারিখ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_withdrawals as $withdrawal): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($withdrawal['name']); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($withdrawal['mobile']); ?></small>
                                                        </td>
                                                        <td class="fw-bold">₹<?php echo number_format($withdrawal['amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($withdrawal['payment_method']); ?></td>
                                                        <td><?php echo htmlspecialchars($withdrawal['account_details']); ?></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($withdrawal['requested_at'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php
                                                                echo $withdrawal['status'] == 'approved' ? 'success' :
                                                                     ($withdrawal['status'] == 'rejected' ? 'danger' : 'warning');
                                                            ?>">
                                                                <?php
                                                                echo $withdrawal['status'] == 'approved' ? 'অনুমোদিত' :
                                                                     ($withdrawal['status'] == 'rejected' ? 'প্রত্যাখ্যাত' : 'পেন্ডিং');
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
            // Initialize DataTable for pending withdrawals
            $('#pendingWithdrawalsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: 5 },
                    { responsivePriority: 3, targets: 1 }
                ],
                order: [[4, 'desc']], // Sort by request date descending
                pageLength: 10
            });

            // Initialize DataTable for all withdrawals history
            $('#allWithdrawalsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: 5 },
                    { responsivePriority: 3, targets: 1 }
                ],
                order: [[4, 'desc']], // Sort by request date descending
                pageLength: 25
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
    </script>
</body>
</html>
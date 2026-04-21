<?php
session_start();
include '../includes/db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get stats
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->execute() ? $stmt->get_result()->fetch_assoc()['total_users'] : 0;

$stmt = $conn->prepare("SELECT COUNT(*) as pending_payments FROM payments WHERE verified = 'pending'");
$pending_payments = $stmt->execute() ? $stmt->get_result()->fetch_assoc()['pending_payments'] : 0;

$stmt = $conn->prepare("SELECT COUNT(*) as pending_withdrawals FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals = $stmt->execute() ? $stmt->get_result()->fetch_assoc()['pending_withdrawals'] : 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total_referrals FROM referrals");
$total_referrals = $stmt->execute() ? $stmt->get_result()->fetch_assoc()['total_referrals'] : 0;

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অ্যাডমিন ড্যাশবোর্ড - Happy West Bengal Happy</title>
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> ড্যাশবোর্ড
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> ইউজার ম্যানেজমেন্ট
                        </a>
                        <a class="nav-link" href="payments.php">
                            <i class="fas fa-credit-card"></i> পেমেন্ট ভেরিফিকেশন
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
                    <h1 class="h3 mb-0">অ্যাডমিন ড্যাশবোর্ড</h1>
                    <div>
                        <span class="text-muted">স্বাগতম, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <div class="stat-number"><?php echo $total_users; ?></div>
                            <div>মোট ইউজার</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-credit-card fa-2x text-warning mb-2"></i>
                            <div class="stat-number"><?php echo $pending_payments; ?></div>
                            <div>পেন্ডিং পেমেন্ট</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                            <div class="stat-number"><?php echo $pending_withdrawals; ?></div>
                            <div>পেন্ডিং উত্তোলন</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-share-alt fa-2x text-info mb-2"></i>
                            <div class="stat-number"><?php echo $total_referrals; ?></div>
                            <div>মোট রেফারেল</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-users"></i>
                            <h4>ইউজার ম্যানেজমেন্ট</h4>
                            <p>ইউজারদের তথ্য দেখুন এবং ম্যানেজ করুন</p>
                            <a href="users.php" class="btn btn-primary">যান</a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-credit-card"></i>
                            <h4>পেমেন্ট ভেরিফিকেশন</h4>
                            <p>ইউজার পেমেন্ট ভেরিফাই করুন</p>
                            <a href="payments.php" class="btn btn-primary">যান</a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-money-bill-wave"></i>
                            <h4>উত্তোলন ম্যানেজমেন্ট</h4>
                            <p>উত্তোলনের অনুরোধ অনুমোদন করুন</p>
                            <a href="withdrawals.php" class="btn btn-primary">যান</a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="dashboard-card menu-card">
                            <i class="fas fa-cog"></i>
                            <h4>সেটিংস</h4>
                            <p>পাসওয়ার্ড, UPI ID, অ্যাডমিন ম্যানেজ করুন</p>
                            <a href="settings.php" class="btn btn-primary">যান</a>
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
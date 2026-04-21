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

// Get user referral ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT referral_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_referral_id = $stmt->get_result()->fetch_assoc()['referral_id'];
closeDBConnection($conn);

// Get user's referral statistics
$conn = getDBConnection();

// Get direct referrals
$stmt = $conn->prepare("SELECT COUNT(*) as direct_referrals FROM referrals WHERE referrer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$direct_referrals = $stmt->get_result()->fetch_assoc()['direct_referrals'];

// Get total network size (recursive)
function getNetworkSize($conn, $user_id, $level = 0, $max_level = 5) {
    if ($level >= $max_level) return 0;

    $stmt = $conn->prepare("SELECT referee_id FROM referrals WHERE referrer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $count = $result->num_rows;

    while ($row = $result->fetch_assoc()) {
        $count += getNetworkSize($conn, $row['referee_id'], $level + 1, $max_level);
    }

    return $count;
}

$total_network = getNetworkSize($conn, $user_id);

// Get referral details with user info
$stmt = $conn->prepare("
    SELECT u.name, u.mobile, u.referral_id, r.created_at
    FROM referrals r
    JOIN users u ON r.referee_id = u.id
    WHERE r.referrer_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$referrals_result = $stmt->get_result();
$referrals = [];
while ($row = $referrals_result->fetch_assoc()) {
    $referrals[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('my referrals'); ?> - <?php echo __('site_title'); ?></title>
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
        .referral-tree {
            position: relative;
            padding-left: 30px;
        }
        .referral-tree::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-color);
        }
        .referral-node {
            position: relative;
            margin-bottom: 20px;
        }
        .referral-node::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 15px;
            width: 20px;
            height: 2px;
            background: var(--primary-color);
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
                        <a class="nav-link active" href="referrals.php">
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
                    <h1 class="h3 mb-0"><?php echo __('my referrals'); ?></h1>
                    <div>
                        <span class="text-muted"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </div>
                </div>

                <!-- Referral Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
                            <div class="stat-number"><?php echo $direct_referrals; ?></div>
                            <div><?php echo __('direct referrals'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dashboard-card stat-card">
                            <i class="fas fa-network-wired fa-2x text-success mb-2"></i>
                            <div class="stat-number"><?php echo $total_network; ?></div>
                            <div><?php echo __('total network'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Direct Referrals Table -->
                <div class="dashboard-card p-4">
                    <h4><i class="fas fa-users"></i> <?php echo __('direct referrals'); ?></h4>
                    <?php if (empty($referrals)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted"><?php echo __('no referrals yet'); ?></h5>
                            <p class="text-muted"><?php echo __('share your referral link to start building your network'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="referralsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo __('name'); ?></th>
                                        <th><?php echo __('mobile'); ?></th>
                                        <th><?php echo __('referral id'); ?></th>
                                        <th><?php echo __('joined date'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referrals as $referral): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($referral['name']); ?></td>
                                            <td><?php echo htmlspecialchars($referral['mobile']); ?></td>
                                            <td><code><?php echo htmlspecialchars($referral['referral_id']); ?></code></td>
                                            <td><?php echo date('d/m/Y', strtotime($referral['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Referral Link -->
                <div class="dashboard-card p-4 mt-4">
                    <h4><i class="fas fa-share-alt"></i> <?php echo __('referral link'); ?></h4>
                    <div class="input-group">
                        <input type="text" class="form-control" id="referralLink" readonly
                               value="<?php echo 'http://' . $_SERVER['HTTP_HOST']; ?>/user/register.php?ref=<?php echo urlencode($user_referral_id); ?>">
                        <button class="btn btn-primary" onclick="copyReferralLink()">
                            <i class="fas fa-copy"></i> <?php echo __('copy'); ?>
                        </button>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <?php echo __('share this link with others to earn commissions when they join'); ?>
                    </small>
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
            $('#referralsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/<?php echo $currentLang == 'bn' ? 'bn' : 'en'; ?>.json'
                },
                order: [[3, 'desc']]
            });
        });

        function copyReferralLink() {
            const linkInput = document.getElementById('referralLink');
            linkInput.select();
            document.execCommand('copy');

            // Show success message
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> <?php echo __('copied'); ?>';
            button.classList.remove('btn-primary');
            button.classList.add('btn-success');

            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
            }, 2000);
        }
    </script>
</body>
</html>
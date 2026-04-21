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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $age = (int)$_POST['age'];
    $address = trim($_POST['address']);
    $education = trim($_POST['education']);

    if (empty($name)) {
        $message = __('name is required');
    } elseif ($age < 18 || $age > 100) {
        $message = __('age must be between 18 and 100');
    } elseif (empty($address)) {
        $message = __('address is required');
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("UPDATE users SET name = ?, age = ?, address = ?, education = ? WHERE id = ?");
        $stmt->bind_param("sisss", $name, $age, $address, $education, $user_id);

        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name; // Update session name
            $success = __('profile updated successfully');
        } else {
            $message = __('update failed');
        }

        closeDBConnection($conn);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 6) {
        $message = __('password must be at least 6 characters');
    } elseif ($new_password !== $confirm_password) {
        $message = __('passwords do not match');
    } else {
        $conn = getDBConnection();

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (password_verify($current_password, $user['password'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_hash, $user_id);

            if ($stmt->execute()) {
                $success = __('password changed successfully');
            } else {
                $message = __('password change failed');
            }
        } else {
            $message = __('current password is incorrect');
        }

        closeDBConnection($conn);
    }
}

$conn = getDBConnection();

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
$stmt = $conn->prepare("SELECT COUNT(*) as referral_count FROM referrals WHERE referrer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$referral_count = $stmt->get_result()->fetch_assoc()['referral_count'];

$stmt = $conn->prepare("SELECT COUNT(*) as purchase_count FROM purchases WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$purchase_count = $stmt->get_result()->fetch_assoc()['purchase_count'];

$stmt = $conn->prepare("SELECT SUM(amount) as total_spent FROM purchases WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$total_spent = $result['total_spent'] ?: 0;

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('profile'); ?> - <?php echo __('site_title'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            margin: 0 auto 20px;
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
        .stat-box {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
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
                        <a class="nav-link" href="wallet.php">
                            <i class="fas fa-wallet"></i> <?php echo __('wallet'); ?>
                        </a>
                        <a class="nav-link" href="withdrawals.php">
                            <i class="fas fa-money-bill-wave"></i> <?php echo __('withdrawals'); ?>
                        </a>
                        <a class="nav-link active" href="profile.php">
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
                    <h1 class="h3 mb-0"><?php echo __('profile'); ?></h1>
                    <span class="text-muted"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Profile Overview -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="dashboard-card p-4 text-center">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($user['referral_id']); ?></p>
                            <div class="badge bg-primary"><?php echo __('member since'); ?> <?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="stat-box">
                                    <span class="stat-number"><?php echo $referral_count; ?></span>
                                    <span class="stat-label"><?php echo __('referrals'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-box">
                                    <span class="stat-number"><?php echo $purchase_count; ?></span>
                                    <span class="stat-label"><?php echo __('purchases'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-box">
                                    <span class="stat-number">₹<?php echo number_format($total_spent, 0); ?></span>
                                    <span class="stat-label"><?php echo __('total spent'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Update Profile -->
                    <div class="col-md-6 mb-4">
                        <div class="dashboard-card p-4">
                            <h4><i class="fas fa-user-edit"></i> <?php echo __('update profile'); ?></h4>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="name" class="form-label"><?php echo __('form_name'); ?> *</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="age" class="form-label"><?php echo __('age'); ?> *</label>
                                    <input type="number" class="form-control" id="age" name="age"
                                           value="<?php echo $user['age']; ?>" min="18" max="100" required>
                                </div>
                                <div class="mb-3">
                                    <label for="mobile" class="form-label"><?php echo __('form_mobile'); ?></label>
                                    <input type="text" class="form-control" id="mobile"
                                           value="<?php echo htmlspecialchars($user['mobile']); ?>" readonly>
                                    <div class="form-text"><?php echo __('mobile number cannot be changed'); ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label"><?php echo __('address'); ?> *</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="education" class="form-label"><?php echo __('education'); ?></label>
                                    <select class="form-select" id="education" name="education">
                                        <option value=""><?php echo __('select'); ?></option>
                                        <option value="Secondary" <?php echo $user['education'] == 'Secondary' ? 'selected' : ''; ?>><?php echo __('secondary'); ?></option>
                                        <option value="Higher Secondary" <?php echo $user['education'] == 'Higher Secondary' ? 'selected' : ''; ?>><?php echo __('higher secondary'); ?></option>
                                        <option value="Graduate" <?php echo $user['education'] == 'Graduate' ? 'selected' : ''; ?>><?php echo __('graduate'); ?></option>
                                        <option value="Post Graduate" <?php echo $user['education'] == 'Post Graduate' ? 'selected' : ''; ?>><?php echo __('post graduate'); ?></option>
                                        <option value="Other" <?php echo $user['education'] == 'Other' ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('referral id'); ?></label>
                                    <input type="text" class="form-control"
                                           value="<?php echo htmlspecialchars($user['referral_id']); ?>" readonly>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo __('update profile'); ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-md-6 mb-4">
                        <div class="dashboard-card p-4">
                            <h4><i class="fas fa-key"></i> <?php echo __('change password'); ?></h4>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label"><?php echo __('current password'); ?></label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label"><?php echo __('new password'); ?></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                                    <div class="form-text"><?php echo __('minimum 6 characters'); ?></div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label"><?php echo __('confirm password'); ?></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i> <?php echo __('change password'); ?>
                                </button>
                            </form>
                        </div>

                        <!-- Account Information -->
                        <div class="dashboard-card p-4">
                            <h4><i class="fas fa-info-circle"></i> <?php echo __('account information'); ?></h4>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong><?php echo __('registration date'); ?>:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('last login'); ?>:</strong></td>
                                    <td><?php echo date('d/m/Y H:i'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('account status'); ?>:</strong></td>
                                    <td><span class="badge bg-success"><?php echo __('active'); ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('referral link'); ?>:</strong></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo $_SERVER['HTTP_HOST']; ?>/user/register.php?ref=<?php echo $user_id; ?>
                                        </small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (newPassword !== confirmPassword) {
                this.setCustomValidity('<?php echo __('passwords do not match'); ?>');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
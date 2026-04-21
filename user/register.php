<?php
session_start();
include '../includes/db_config.php';
include '../includes/language.php';

$currentLang = getCurrentLanguage();
$langSwitcher = getLanguageSwitcher();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $age = (int)$_POST['age'];
    $mobile = trim($_POST['mobile']);
    $password = $_POST['password'];
    $address = trim($_POST['address']);
    $education = trim($_POST['education']);
    $referral_code = trim($_POST['referral_code']);

    // Validation
    if (empty($name)) $errors[] = __('form_name') . ' ' . __('required', 'is required');
    if ($age < 18 || $age > 100) $errors[] = __('age must be between 18 and 100');
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) $errors[] = __('invalid mobile number');
    if (strlen($password) < 6) $errors[] = __('password must be at least 6 characters');
    if (empty($address)) $errors[] = __('address is required');

    if (empty($errors)) {
        $conn = getDBConnection();

        // Check if mobile already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = __('mobile number already registered');
        } else {
            // Generate unique referral ID
            $referral_id = 'HW' . strtoupper(substr(md5(uniqid()), 0, 6));

            // Check if referral_code exists and get referrer_id
            $referrer_id = null;
            if (!empty($referral_code)) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE referral_id = ?");
                $stmt->bind_param("s", $referral_code);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $referrer_id = $row['id'];
                }
            }

            // Insert user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, age, mobile, password, address, education, referral_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssss", $name, $age, $mobile, $password_hash, $address, $education, $referral_id);
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;

                // If referrer exists, add to referrals table
                if ($referrer_id) {
                    $stmt = $conn->prepare("INSERT INTO referrals (referrer_id, referee_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $referrer_id, $user_id);
                    $stmt->execute();
                }

                $success = __('registration_success') . ' ' . __('your referral id') . ': ' . $referral_id;
                // Redirect to payment page
                header("Location: payment.php?user_id=" . $user_id);
                exit();
            } else {
                $errors[] = __('registration failed');
            }
        }
        $stmt->close();
        closeDBConnection($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('register_title'); ?> - <?php echo __('site_title'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #e91e63;
            --secondary-color: #9c27b0;
        }
        body {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
            padding: 20px;
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
        }
        .language-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
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

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-plus"></i> <?php echo __('register_title'); ?></h3>
                        <p><?php echo __('join our community'); ?></p>
                    </div>
                    <div class="card-body p-4">
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

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label"><?php echo __('form_name'); ?> *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="age" class="form-label"><?php echo __('age'); ?> *</label>
                                    <input type="number" class="form-control" id="age" name="age" min="18" max="100" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="mobile" class="form-label"><?php echo __('form_mobile'); ?> *</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile" pattern="[6-9]{1}[0-9]{9}" required>
                                <div class="form-text"><?php echo __('10 digit mobile number starting with 6,7,8,9'); ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label"><?php echo __('form_password'); ?> *</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                <div class="form-text"><?php echo __('minimum 6 characters'); ?></div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label"><?php echo __('address'); ?> *</label>
                                <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="education" class="form-label"><?php echo __('education'); ?></label>
                                <select class="form-select" id="education" name="education">
                                    <option value=""><?php echo __('select'); ?></option>
                                    <option value="Secondary"><?php echo __('secondary'); ?></option>
                                    <option value="Higher Secondary"><?php echo __('higher secondary'); ?></option>
                                    <option value="Graduate"><?php echo __('graduate'); ?></option>
                                    <option value="Post Graduate"><?php echo __('post graduate'); ?></option>
                                    <option value="Other"><?php echo __('other'); ?></option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="referral_code" class="form-label"><?php echo __('referral code (if any)'); ?></label>
                                <input type="text" class="form-control" id="referral_code" name="referral_code" placeholder="HWXXXXXX">
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><?php echo __('register_button'); ?></button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="login.php"><?php echo __('already_have_account'); ?> <?php echo __('login'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pre-fill referral code from URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const refParam = urlParams.get('ref');
            if (refParam) {
                const referralCodeInput = document.getElementById('referral_code');
                if (referralCodeInput) {
                    referralCodeInput.value = refParam;
                }
            }
        });
    </script>
</body>
</html>
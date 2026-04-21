<?php
session_start();
include '../includes/db_config.php';
include '../includes/language.php';

$currentLang = getCurrentLanguage();
$langSwitcher = getLanguageSwitcher();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mobile = trim($_POST['mobile']);
    $password = $_POST['password'];

    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errors[] = __('invalid mobile number');
    }

    if (empty($errors)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE mobile = ?");
        $stmt->bind_param("s", $mobile);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                header("Location: dashboard.php");
                exit();
            } else {
                $errors[] = __('invalid password');
            }
        } else {
            $errors[] = __('mobile number not found');
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
    <title><?php echo __('login_title'); ?> - <?php echo __('site_title'); ?></title>
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
        .login-card {
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
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="card-header">
                        <h3><i class="fas fa-sign-in-alt"></i> <?php echo __('login_title'); ?></h3>
                        <p><?php echo __('login to your account'); ?></p>
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

                        <form method="POST">
                            <div class="mb-3">
                                <label for="mobile" class="form-label"><?php echo __('form_mobile'); ?></label>
                                <input type="tel" class="form-control" id="mobile" name="mobile" pattern="[6-9]{1}[0-9]{9}" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label"><?php echo __('form_password'); ?></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><?php echo __('login_button'); ?></button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="register.php"><?php echo __('not registered yet'); ?> <?php echo __('register'); ?></a>
                        </div>
                        <div class="text-center mt-2">
                            <a href="forgot_password.php"><?php echo __('forgot password'); ?>?</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
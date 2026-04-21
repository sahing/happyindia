<?php
session_start();
include '../includes/db_config.php';

// Check if user is logged in or has user_id from registration
$user_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    // Set session for new user
    $_SESSION['user_id'] = $user_id;
} else {
    header("Location: login.php");
    exit();
}

// Get user information
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT name, referral_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
closeDBConnection($conn);

if (!$user) {
    header("Location: login.php");
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['screenshot'])) {
        $file = $_FILES['screenshot'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'শুধুমাত্র JPG, PNG ফাইল অনুমোদিত';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $errors[] = 'ফাইল সাইজ 5MB এর কম হতে হবে';
        } else {
            // Create uploads directory if not exists
            $upload_dir = '../assets/images/screenshots/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename = 'payment_' . $user_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $conn = getDBConnection();
                
                // Check if payment already exists
                $stmt = $conn->prepare("SELECT id FROM payments WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    // Insert payment
                    $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, screenshot_path) VALUES (?, 100.00, ?)");
                    $stmt->bind_param("is", $user_id, $filename);
                    if ($stmt->execute()) {
                        $success = 'পেমেন্ট স্ক্রিনশট আপলোড সফল! অ্যাডমিন ভেরিফাই করার পর আপনার অ্যাকাউন্ট অ্যাক্টিভ হবে।';
                    } else {
                        $errors[] = 'ডাটাবেস ত্রুটি';
                    }
                } else {
                    $errors[] = 'পেমেন্ট ইতিমধ্যে আপলোড করা হয়েছে';
                }
                
                $stmt->close();
                closeDBConnection($conn);
            } else {
                $errors[] = 'ফাইল আপলোড ব্যর্থ';
            }
        }
    }
}

// Get active QR codes
$conn = getDBConnection();
$qr_result = $conn->query("SELECT filename FROM qr_codes WHERE is_active = TRUE ORDER BY RAND() LIMIT 1");
$qr_code = $qr_result->fetch_assoc();
closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>পেমেন্ট - Happy West Bengal Happy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #e91e63;
            --secondary-color: #9c27b0;
        }
        body {
            background-color: #f8f9fa;
        }
        .payment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .qr-code {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-smile text-primary"></i> Happy West Bengal Happy
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link">স্বাগতম, <?php echo htmlspecialchars($user['name']); ?></span>
                <a class="nav-link" href="dashboard.php">ড্যাশবোর্ড</a>
                <a class="nav-link" href="logout.php">লগআউট</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="payment-card p-4">
                    <h2 class="text-center mb-4">পেমেন্ট সম্পন্ন করুন</h2>
                    
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

                    <div class="row">
                        <div class="col-md-6">
                            <div class="qr-code">
                                <h4>QR কোড স্ক্যান করুন</h4>
                                <p>₹100 পেমেন্ট করুন</p>
                                <?php if ($qr_code): ?>
                                    <img src="../assets/images/qr_codes/<?php echo htmlspecialchars($qr_code['filename']); ?>" 
                                         alt="Payment QR Code" class="img-fluid">
                                <?php else: ?>
                                    <div class="alert alert-warning">QR কোড উপলব্ধ নেই। অনুগ্রহ করে পরে চেষ্টা করুন।</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4>পেমেন্ট স্ক্রিনশট আপলোড করুন</h4>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="screenshot" class="form-label">স্ক্রিনশট নির্বাচন করুন</label>
                                    <input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/*" required>
                                    <div class="form-text">JPG, PNG ফাইল, সর্বোচ্চ 5MB</div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">আপলোড করুন</button>
                            </form>
                            <div class="mt-3">
                                <h5>আপনার রেফারেল ID: <span class="text-primary"><?php echo htmlspecialchars($user['referral_id']); ?></span></h5>
                                <p>এই ID শেয়ার করে বন্ধুদের আমন্ত্রণ করুন</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
include '../includes/db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $message = 'নতুন পাসওয়ার্ড এবং কনফার্ম পাসওয়ার্ড মিলছে না';
        } elseif (strlen($new_password) < 6) {
            $message = 'পাসওয়ার্ড কমপক্ষে 6 অক্ষর হতে হবে';
        } else {
            $stmt = $conn->prepare("SELECT password_hash FROM admins WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['admin_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            
            if (password_verify($current_password, $admin['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                $stmt->bind_param("si", $new_hash, $_SESSION['admin_id']);
                $stmt->execute();
                $message = 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে';
            } else {
                $message = 'বর্তমান পাসওয়ার্ড ভুল';
            }
        }
    } elseif (isset($_POST['add_qr'])) {
        if (isset($_FILES['qr_code'])) {
            $file = $_FILES['qr_code'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $message = 'শুধুমাত্র JPG, PNG ফাইল অনুমোদিত';
            } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
                $message = 'ফাইল সাইজ 5MB এর কম হতে হবে';
            } else {
                // Create uploads directory if not exists
                $upload_dir = '../assets/images/qr_codes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $filename = 'qr_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $stmt = $conn->prepare("INSERT INTO qr_codes (filename, original_name) VALUES (?, ?)");
                    $stmt->bind_param("ss", $filename, $file['name']);
                    if ($stmt->execute()) {
                        $message = 'QR কোড আপলোড সফল';
                    } else {
                        $message = 'QR কোড সংরক্ষণ করতে ব্যর্থ';
                    }
                } else {
                    $message = 'ফাইল আপলোড ব্যর্থ';
                }
            }
        }
    } elseif (isset($_POST['toggle_qr'])) {
        $qr_id = (int)$_POST['qr_id'];
        $stmt = $conn->prepare("UPDATE qr_codes SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $qr_id);
        $stmt->execute();
        $message = 'QR কোড স্ট্যাটাস পরিবর্তন করা হয়েছে';
    } elseif (isset($_POST['delete_qr'])) {
        $qr_id = (int)$_POST['qr_id'];
        
        // Get filename before deleting
        $stmt = $conn->prepare("SELECT filename FROM qr_codes WHERE id = ?");
        $stmt->bind_param("i", $qr_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $qr = $result->fetch_assoc();
        
        if ($qr) {
            // Delete file
            $filepath = '../assets/images/qr_codes/' . $qr['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM qr_codes WHERE id = ?");
            $stmt->bind_param("i", $qr_id);
            $stmt->execute();
            $message = 'QR কোড মুছে ফেলা হয়েছে';
        }
    } elseif (isset($_POST['add_admin'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $permission = $_POST['permission'];
        
        if (empty($username) || empty($password)) {
            $message = 'সব ফিল্ড পূরণ করুন';
        } elseif (strlen($password) < 6) {
            $message = 'পাসওয়ার্ড কমপক্ষে 6 অক্ষর হতে হবে';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (username, password_hash, permission_level) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hash, $permission);
            if ($stmt->execute()) {
                $message = 'অ্যাডমিন যোগ করা হয়েছে';
            } else {
                $message = 'অ্যাডমিন যোগ করতে ব্যর্থ';
            }
        }
    } elseif (isset($_POST['add_product'])) {
        $name = trim($_POST['product_name']);
        $description = trim($_POST['product_description']);
        $price = (float)$_POST['product_price'];
        
        if (!empty($name) && $price > 0) {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price) VALUES (?, ?, ?)");
            $stmt->bind_param("ssd", $name, $description, $price);
            if ($stmt->execute()) {
                $message = 'প্রোডাক্ট যোগ করা হয়েছে';
            } else {
                $message = 'প্রোডাক্ট যোগ করতে ব্যর্থ';
            }
        }
    } elseif (isset($_POST['edit_product'])) {
        $product_id = (int)$_POST['product_id'];
        $name = trim($_POST['edit_product_name']);
        $description = trim($_POST['edit_product_description']);
        $price = (float)$_POST['edit_product_price'];
        
        if (!empty($name) && $price > 0) {
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ? WHERE id = ?");
            $stmt->bind_param("ssdi", $name, $description, $price, $product_id);
            if ($stmt->execute()) {
                $message = 'প্রোডাক্ট আপডেট করা হয়েছে';
            } else {
                $message = 'প্রোডাক্ট আপডেট করতে ব্যর্থ';
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $message = 'প্রোডাক্ট মুছে ফেলা হয়েছে';
        } else {
            $message = 'প্রোডাক্ট মুছে ফেলতে ব্যর্থ';
        }
    } elseif (isset($_POST['update_commission'])) {
        $level = (int)$_POST['commission_level'];
        $percentage = (float)$_POST['commission_percentage'];
        
        $stmt = $conn->prepare("UPDATE commission_settings SET percentage = ? WHERE level = ?");
        $stmt->bind_param("di", $percentage, $level);
        if ($stmt->execute()) {
            $message = 'কমিশন সেটিংস আপডেট করা হয়েছে';
        } else {
            $message = 'কমিশন সেটিংস আপডেট করতে ব্যর্থ';
        }
    }
    
    closeDBConnection($conn);
}

// Get QR Codes
$conn = getDBConnection();
$qr_result = $conn->query("SELECT * FROM qr_codes ORDER BY created_at DESC");
$qr_codes = [];
while ($row = $qr_result->fetch_assoc()) {
    $qr_codes[] = $row;
}

// Get admins
$admin_result = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
$admins = [];
while ($row = $admin_result->fetch_assoc()) {
    $admins[] = $row;
}

// Get products
$product_result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
$products = [];
while ($row = $product_result->fetch_assoc()) {
    $products[] = $row;
}

// Get commission settings
$commission_result = $conn->query("SELECT * FROM commission_settings ORDER BY level");
$commission_settings = [];
while ($row = $commission_result->fetch_assoc()) {
    $commission_settings[] = $row;
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>সেটিংস - অ্যাডমিন</title>
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
                            <i class="fas fa-credit-card"></i> পেমেন্ট ম্যানেজমেন্ট
                        </a>
                        <a class="nav-link" href="withdrawals.php">
                            <i class="fas fa-money-bill-wave"></i> উত্তোলন ম্যানেজমেন্ট
                        </a>
                        <a class="nav-link active" href="settings.php">
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
                    <h1 class="h3 mb-0">সেটিংস</h1>
                    <div>
                        <span class="text-muted">স্বাগতম, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Change Password -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-key"></i> পাসওয়ার্ড পরিবর্তন</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">বর্তমান পাসওয়ার্ড</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">নতুন পাসওয়ার্ড</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">পাসওয়ার্ড কনফার্ম</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">পরিবর্তন করুন</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- QR Codes Management -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-qrcode"></i> QR কোড ম্যানেজমেন্ট</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data" class="mb-3">
                                    <div class="mb-3">
                                        <label for="qr_code" class="form-label">QR কোড আপলোড করুন</label>
                                        <input type="file" class="form-control" id="qr_code" name="qr_code" accept="image/*" required>
                                        <div class="form-text">JPG, PNG ফাইল, সর্বোচ্চ 5MB</div>
                                    </div>
                                    <button type="submit" name="add_qr" class="btn btn-primary">আপলোড করুন</button>
                                </form>

                                <div class="table-responsive">
                                    <table id="qrTable" class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>প্রিভিউ</th>
                                                <th>ফাইল নাম</th>
                                                <th>স্ট্যাটাস</th>
                                                <th>অ্যাকশন</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($qr_codes as $qr): ?>
                                                <tr>
                                                    <td>
                                                        <img src="../assets/images/qr_codes/<?php echo htmlspecialchars($qr['filename']); ?>" 
                                                             alt="QR Code" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($qr['original_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $qr['is_active'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $qr['is_active'] ? 'অ্যাক্টিভ' : 'ইনঅ্যাক্টিভ'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="qr_id" value="<?php echo $qr['id']; ?>">
                                                            <button type="submit" name="toggle_qr" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-toggle-<?php echo $qr['is_active'] ? 'on' : 'off'; ?>"></i>
                                                            </button>
                                                            <button type="submit" name="delete_qr" class="btn btn-sm btn-danger" onclick="return confirm('আপনি কি নিশ্চিত?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Management -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user-shield"></i> অ্যাডমিন ম্যানেজমেন্ট</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="mb-3">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <input type="text" class="form-control" name="username" placeholder="ইউজারনেম" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <input type="password" class="form-control" name="password" placeholder="পাসওয়ার্ড" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select class="form-select" name="permission">
                                        <option value="admin">অ্যাডমিন</option>
                                        <option value="moderator">মডারেটর</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-2">
                                    <button type="submit" name="add_admin" class="btn btn-primary w-100">যোগ করুন</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table id="adminsTable" class="table">
                                <thead>
                                    <tr>
                                        <th>ইউজারনেম</th>
                                        <th>পারমিশন লেভেল</th>
                                        <th>তৈরি হয়েছে</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $admin): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php 
                                                    echo $admin['permission_level'] == 'super_admin' ? 'সুপার অ্যাডমিন' : 
                                                         ($admin['permission_level'] == 'admin' ? 'অ্যাডমিন' : 'মডারেটর'); 
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Product Management -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-shopping-cart"></i> প্রোডাক্ট ম্যানেজমেন্ট</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-3">
                                    <div class="mb-3">
                                        <input type="text" class="form-control" name="product_name" placeholder="প্রোডাক্ট নাম" required>
                                    </div>
                                    <div class="mb-3">
                                        <textarea class="form-control" name="product_description" rows="2" placeholder="বর্ণনা"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <input type="number" class="form-control" name="product_price" placeholder="মূল্য (₹)" step="0.01" min="0" required>
                                    </div>
                                    <button type="submit" name="add_product" class="btn btn-primary">প্রোডাক্ট যোগ করুন</button>
                                </form>

                                <div class="table-responsive">
                                    <table id="productsTable" class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>নাম</th>
                                                <th>বর্ণনা</th>
                                                <th>মূল্য</th>
                                                <th>তৈরি হয়েছে</th>
                                                <th>অ্যাকশন</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . (strlen($product['description']) > 50 ? '...' : ''); ?></td>
                                                    <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-warning" onclick="editProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', '<?php echo addslashes($product['description']); ?>', <?php echo $product['price']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <button type="submit" name="delete_product" class="btn btn-sm btn-danger" onclick="return confirm('আপনি কি নিশ্চিত যে এই প্রোডাক্ট মুছে ফেলতে চান?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Product Modal -->
                    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editProductModalLabel">প্রোডাক্ট এডিট করুন</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="product_id" id="edit_product_id">
                                        <div class="mb-3">
                                            <label for="edit_product_name" class="form-label">প্রোডাক্ট নাম</label>
                                            <input type="text" class="form-control" id="edit_product_name" name="edit_product_name" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_product_description" class="form-label">বর্ণনা</label>
                                            <textarea class="form-control" id="edit_product_description" name="edit_product_description" rows="3"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_product_price" class="form-label">মূল্য (₹)</label>
                                            <input type="number" class="form-control" id="edit_product_price" name="edit_product_price" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                                        <button type="submit" name="edit_product" class="btn btn-primary">আপডেট করুন</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Commission Settings -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-percentage"></i> কমিশন সেটিংস</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($commission_settings as $setting): ?>
                                    <form method="POST" class="mb-3">
                                        <div class="row align-items-end">
                                            <div class="col-4">
                                                <label class="form-label">লেভেল <?php echo $setting['level']; ?></label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($setting['description']); ?>" readonly>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label">পার্সেন্টেজ (%)</label>
                                                <input type="number" class="form-control" name="commission_percentage" value="<?php echo $setting['percentage']; ?>" step="0.01" min="0" max="100" required>
                                            </div>
                                            <div class="col-4">
                                                <input type="hidden" name="commission_level" value="<?php echo $setting['level']; ?>">
                                                <button type="submit" name="update_commission" class="btn btn-primary">আপডেট</button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
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
            $('#qrTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                paging: false,
                searching: false,
                info: false
            });

            $('#adminsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                paging: false,
                searching: false,
                info: false
            });

            $('#productsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/bn.json'
                },
                paging: false,
                searching: false,
                info: false
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

        // Edit product function
        function editProduct(id, name, description, price) {
            document.getElementById('edit_product_id').value = id;
            document.getElementById('edit_product_name').value = name;
            document.getElementById('edit_product_description').value = description;
            document.getElementById('edit_product_price').value = price;
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }
    </script>
</body>
</html>
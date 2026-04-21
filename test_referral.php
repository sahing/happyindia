<?php
session_start();
include '../includes/db_config.php';
include '../includes/language.php';

// Simulate user session
$_SESSION['user_id'] = 1; // Assuming user ID 1 for testing

// Get user referral ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT referral_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user_referral_id = $stmt->get_result()->fetch_assoc()['referral_id'];
closeDBConnection($conn);

echo "Referral Link: http://" . $_SERVER['HTTP_HOST'] . "/user/register.php?ref=" . urlencode($user_referral_id) . PHP_EOL;
echo "Referral ID: " . $user_referral_id . PHP_EOL;
?>
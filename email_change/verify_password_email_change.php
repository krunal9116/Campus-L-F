<?php
session_start();

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in!']);
    exit();
}

$username = $_SESSION['username'];
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required!']);
    exit();
}

// Get user
$user_query = "SELECT * FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);

if (mysqli_num_rows($user_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'User not found!']);
    exit();
}

$user_data = mysqli_fetch_assoc($user_result);

// Verify password
if (password_verify($password, $user_data['password'])) {
    $_SESSION['email_change_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'Password verified!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect password!']);
}

mysqli_close($conn);
?>
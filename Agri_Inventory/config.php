<?php
// Database connection configuration
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'agri_inventory';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Function to require login, redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to generate random IDs for database tables
function generateID($prefix, $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $id;
}

// Function to sanitize user input
function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}
// In config.php, add this function:
function generateUniqueID($prefix = '') {
    // Generate a unique ID with the given prefix
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    return $prefix . $timestamp . $random;
}

// Function to display success message
function showSuccess($message) {
    $_SESSION['success_message'] = $message;
}

// Function to display error message
function showError($message) {
    $_SESSION['error_message'] = $message;
}

// Function to get the current date in YYYY-MM-DD format
function getCurrentDate() {
    return date('Y-m-d');
}

// Function to format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}
?>
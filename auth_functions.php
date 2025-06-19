<?php
// auth_functions.php

// Check if user is logged in
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Signup function
function signupUser($fullname, $email, $username, $password) {
    global $pdo;
    
    try {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username or email already exists.'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, is_verified, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 0, 1, NOW(), NOW())
        ");
        
        if ($stmt->execute([$username, $email, $hashedPassword, $fullname])) {
            $userId = $pdo->lastInsertId();
            return ['success' => true, 'user_id' => $userId];
        } else {
            return ['success' => false, 'error' => 'Failed to create account. Please try again.'];
        }
        
    } catch (PDOException $e) {
        logError("Signup error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred.'];
    }
}

// Login function
function loginUser($username, $password) {
    global $pdo;
    
    try {
        // Find user by username or email
        $stmt = $pdo->prepare("SELECT id, username, email, password, full_name, is_active FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Your account has been deactivated.'];
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['logged_in'] = true;
            
            // Update last login and login count
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return ['success' => true, 'user_id' => $user['id']];
        } else {
            return ['success' => false, 'error' => 'Invalid username or password.'];
        }
        
    } catch (PDOException $e) {
        logError("Login error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error occurred.'];
    }
}

// Get current user info
function getCurrentUser() {
    global $pdo;
    
    if (!isUserLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Get current user error: " . $e->getMessage());
        return null;
    }
}

// Logout function
function logoutUser() {
    session_unset();
    session_destroy();
    session_start(); // Start new clean session
}

// Check if user has permission
function hasPermission($permission) {
    if (!isUserLoggedIn()) {
        return false;
    }
    
    // Add role-based permissions later if needed
    return true;
}

// Log errors (only declare if not already exists)
if (!function_exists('logError')) {
    function logError($message) {
        error_log(date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, 3, 'error.log');
    }
}

// Require login (redirect if not logged in)
function requireLogin($redirectTo = 'login.php') {
    if (!isUserLoggedIn()) {
        header("Location: $redirectTo");
        exit();
    }
}
?>
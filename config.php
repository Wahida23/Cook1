<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cookistry_db";

try {
    // Create connection using PDO
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session for login management
if (session_status() === PHP_SESSION_NONE) {
    // Set session parameters before starting
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    
    // Only set secure if HTTPS is available
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Security Configuration
define('UPLOAD_DIR', 'images/');
define('VIDEO_UPLOAD_DIR', 'uploads/videos/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB for images
define('MAX_VIDEO_SIZE', 150 * 1024 * 1024); // 150MB for videos
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif']);
define('ALLOWED_VIDEO_EXTENSIONS', ['mp4', 'mpeg', 'mov', 'avi']);
define('ALLOWED_VIDEO_MIME_TYPES', ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo']);

// Password Security Configuration
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_COST', 12);

// Function to check if admin is logged in
function checkAdminLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: admin-login.php");
        exit();
    }
}

// Function to redirect if already logged in
function redirectIfLoggedIn() {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header("Location: admin-dashboard.php");
        exit();
    }
}

// Sanitize input function
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate CSRF Token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Simple rate limiting function
function checkRateLimit($action, $max_attempts = 5, $time_window = 300) {
    $ip = getClientIP();
    $rate_key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$rate_key])) {
        $_SESSION[$rate_key] = ['count' => 0, 'time' => time()];
    }
    
    $rate_data = $_SESSION[$rate_key];
    
    // Reset if time window has passed
    if (time() - $rate_data['time'] > $time_window) {
        $_SESSION[$rate_key] = ['count' => 1, 'time' => time()];
        return true;
    }
    
    // Check if exceeded max attempts
    if ($rate_data['count'] >= $max_attempts) {
        return false;
    }
    
    // Increment attempt count
    $_SESSION[$rate_key]['count']++;
    return true;
}

// Enhanced secure file upload function
function secureFileUpload($file) {
    $result = ['success' => false, 'filename' => '', 'errors' => []];
    
    // Check if upload directory exists
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            $result['errors'][] = "Failed to create upload directory.";
            logError("Failed to create upload directory: " . UPLOAD_DIR, __FILE__, __LINE__, ['file' => $file['name']]);
            return $result;
        }
    }
    
    // Basic file validation
    if ($file['size'] > MAX_FILE_SIZE) {
        $result['errors'][] = "File size too large. Maximum allowed: " . (MAX_FILE_SIZE / 1024 / 1024) . "MB";
        logError("File size exceeded: " . $file['size'] . " for " . $file['name'], __FILE__, __LINE__);
        return $result;
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
        $result['errors'][] = "Invalid file type. Allowed: " . implode(', ', ALLOWED_EXTENSIONS);
        logError("Invalid file extension: " . $file_extension . " for " . $file['name'], __FILE__, __LINE__);
        return $result;
    }
    
    // MIME type checking
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        $result['errors'][] = "Invalid file MIME type. Detected: $mime_type. Allowed: " . implode(', ', ALLOWED_MIME_TYPES);
        logError("Invalid MIME type: $mime_type for " . $file['name'], __FILE__, __LINE__);
        return $result;
    }
    
    // Generate unique filename
    $filename = uniqid('img_', true) . '.' . $file_extension;
    $file_path = UPLOAD_DIR . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $result['success'] = true;
        $result['filename'] = $file_path;
        
        // Log upload activity
        logError("File uploaded: " . $filename . " by " . ($_SESSION['admin_username'] ?? 'unknown'), __FILE__, __LINE__);
    } else {
        $result['errors'][] = "Failed to upload file. Check directory permissions.";
        logError("Failed to move uploaded file to $file_path for " . $file['name'], __FILE__, __LINE__);
    }
    
    return $result;
}

// Enhanced secure video upload function
function secureVideoUpload($file) {
    $result = ['success' => false, 'filename' => '', 'errors' => []];
    
    // Check if upload directory exists
    if (!is_dir(VIDEO_UPLOAD_DIR)) {
        if (!mkdir(VIDEO_UPLOAD_DIR, 0755, true)) {
            $result['errors'][] = "Failed to create video upload directory.";
            logError("Failed to create video upload directory: " . VIDEO_UPLOAD_DIR, __FILE__, __LINE__, ['file' => $file['name']]);
            return $result;
        }
    }
    
    // Basic file validation
    if ($file['size'] > MAX_VIDEO_SIZE) {
        $result['errors'][] = "Video file too large. Maximum allowed: " . (MAX_VIDEO_SIZE / 1024 / 1024) . "MB";
        logError("Video file size exceeded: " . $file['size'] . " for " . $file['name'], __FILE__, __LINE__);
        return $result;
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, ALLOWED_VIDEO_EXTENSIONS)) {
        $result['errors'][] = "Invalid video file type. Allowed: " . implode(', ', ALLOWED_VIDEO_EXTENSIONS);
        logError("Invalid video file extension: " . $file_extension . " for " . $file['name'], __FILE__, __LINE__);
        return $result;
    }
    
    // MIME type checking
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_VIDEO_MIME_TYPES)) {
        $result['errors'][] = "Invalid video MIME type. Detected: $mime_type. Allowed: " . implode(', ', ALLOWED_VIDEO_MIME_TYPES);
        logError("Invalid video MIME type: $mime_type for " . $file['name'], __FILE__, __LINE__);
        return $result;
    }
    
    // Generate unique filename
    $filename = uniqid('video_', true) . '.' . $file_extension;
    $file_path = VIDEO_UPLOAD_DIR . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $result['success'] = true;
        $result['filename'] = $file_path;
        
        // Log upload activity
        logError("Video uploaded: " . $filename . " by " . ($_SESSION['admin_username'] ?? 'unknown'), __FILE__, __LINE__);
    } else {
        $result['errors'][] = "Failed to upload video file. Check directory permissions.";
        logError("Failed to move uploaded video file to $file_path for " . $file['name'], __FILE__, __LINE__);
    }
    
    return $result;
}

// Enhanced client IP detection
function getClientIP() {
    $ip_keys = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Direct connection
    ];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                // Validate IP and exclude private ranges for public detection
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// Enhanced error logging function
function logError($message, $file = '', $line = '', $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $user = $_SESSION['admin_username'] ?? 'anonymous';
    
    $log_message = "[$timestamp] [$ip] [$user] ";
    
    if ($file && $line) {
        $log_message .= "$file:$line - ";
    }
    
    $log_message .= $message;
    
    if (!empty($context)) {
        $log_message .= " | Context: " . json_encode($context);
    }
    
    $log_message .= PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    error_log($log_message, 3, 'logs/error.log');
}

// Password strength validation
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

// Secure password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => PASSWORD_COST]);
}

// Enhanced secure admin login
function adminLogin($username, $password) {
    global $pdo;
    
    // Rate limiting check
    if (!checkRateLimit('admin_login', 5, 900)) { // 5 attempts per 15 minutes
        return ['success' => false, 'error' => 'Too many login attempts. Please try again later.'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            // Check if password is hashed or plain text (for backward compatibility)
            $password_valid = false;
            
            if (password_get_info($admin['password'])['algo'] !== null) {
                // Password is hashed
                $password_valid = password_verify($password, $admin['password']);
            } else {
                // Plain text password (backward compatibility)
                $password_valid = ($password === $admin['password']);
                
                // Auto-upgrade to hashed password
                if ($password_valid) {
                    $new_hash = hashPassword($password);
                    $update_stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                    $update_stmt->execute([$new_hash, $admin['id']]);
                }
            }
            
            if ($password_valid) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['login_time'] = time();
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
                
                // Log successful login
                logError("Admin login successful: " . $username);
                
                return ['success' => true];
            }
        }
        
        // Log failed login attempt
        logError("Admin login failed for: " . $username . " from IP: " . getClientIP());
        
        return ['success' => false, 'error' => 'Invalid username or password.'];
        
    } catch(PDOException $e) {
        logError("Login error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Login system temporarily unavailable.'];
    }
}

// Function to create new admin user
function createAdminUser($username, $password, $email, $role = 'admin') {
    global $pdo;
    
    // Validate password strength
    $password_errors = validatePasswordStrength($password);
    if (!empty($password_errors)) {
        return ['success' => false, 'errors' => $password_errors];
    }
    
    try {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()['count'] > 0) {
            return ['success' => false, 'error' => 'Username or email already exists.'];
        }
        
        // Create user with hashed password
        $hashed_password = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hashed_password, $email, $role]);
        
        logError("New admin user created: " . $username);
        
        return ['success' => true, 'message' => 'Admin user created successfully.'];
        
    } catch(PDOException $e) {
        logError("User creation error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to create user.'];
    }
}

// Function to logout admin
function adminLogout() {
    // Log logout activity
    if (isset($_SESSION['admin_username'])) {
        logError("Admin logout: " . $_SESSION['admin_username']);
    }
    
    session_unset();
    session_destroy();
    header("Location: admin-login.php");
    exit();
}

// Get recipe statistics
function getRecipeStats() {
    global $pdo;
    
    try {
        $stats = [];
        
        // Total recipes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM recipes WHERE status = 'published'");
        $stats['total_recipes'] = $stmt->fetch()['total'];
        
        // Total by category
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM recipes WHERE status = 'published' GROUP BY category");
        $stats['by_category'] = $stmt->fetchAll();
        
        // Average rating
        $stmt = $pdo->query("SELECT AVG(rating) as avg_rating FROM recipes WHERE status = 'published' AND rating > 0");
        $result = $stmt->fetch();
        $stats['avg_rating'] = $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
        
        // Most popular recipes
        $stmt = $pdo->query("SELECT title, views FROM recipes WHERE status = 'published' ORDER BY views DESC LIMIT 5");
        $stats['popular_recipes'] = $stmt->fetchAll();
        
        // Recent activity
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM recipes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_recipes'] = $stmt->fetch()['count'];
        
        return $stats;
    } catch(PDOException $e) {
        logError("Stats error: " . $e->getMessage());
        return [
            'total_recipes' => 0, 
            'by_category' => [], 
            'avg_rating' => 0,
            'popular_recipes' => [],
            'recent_recipes' => 0
        ];
    }
}

// Basic security headers function
function setBasicSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
}

// Basic session security
function basicSessionSecurity() {
    // Only set if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
    }
    
    // Session regeneration
    if (isset($_SESSION['last_regeneration'])) {
        if (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    } else {
        $_SESSION['last_regeneration'] = time();
    }
}

// Initialize basic security
basicSessionSecurity();
setBasicSecurityHeaders();

// Create logs directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Simple error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        logError("Error: " . $message, $file, $line);
    }
});

// Simple exception handler
set_exception_handler(function($exception) {
    logError("Uncaught exception: " . $exception->getMessage(), $exception->getFile(), $exception->getLine());
});
?>
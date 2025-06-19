<?php
// Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Redirect if already logged in
redirectIfLoggedIn();

$error_message = '';

// Handle form submission
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && $admin['password'] === $password) {
                // Update last login time
                $update_stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$admin['id']]);
                
                // Set session variables
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // Redirect to dashboard
                header("Location: admin-dashboard.php");
                exit();
            } else {
                $error_message = "Invalid username or password!";
            }
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "Database error occurred!";
        }
    } else {
        $error_message = "Please fill in all fields!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login - Cookistry</title>
  <link rel="icon" href="images/logo.png" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-container {
      background: white;
      padding: 3rem;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
      position: relative;
      overflow: hidden;
    }

    .login-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #4ca1af, #2c3e50);
    }

    .logo-section {
      text-align: center;
      margin-bottom: 2rem;
    }

    .logo-section img {
      width: 60px;
      height: 60px;
      margin-bottom: 1rem;
    }

    .logo-section h2 {
      color: #2c3e50;
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
    }

    .logo-section p {
      color: #666;
      font-size: 0.9rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: #333;
      font-weight: 600;
    }

    .form-group input {
      width: 100%;
      padding: 1rem;
      border: 2px solid #e1e5e9;
      border-radius: 10px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background: #f8f9fa;
    }

    .form-group input:focus {
      outline: none;
      border-color: #4ca1af;
      background: white;
      box-shadow: 0 0 0 3px rgba(76, 161, 175, 0.1);
    }

    .login-btn {
      width: 100%;
      padding: 1rem;
      background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-bottom: 1rem;
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
    }

    .login-btn:active {
      transform: translateY(0);
    }

    .back-link {
      text-align: center;
      margin-top: 1.5rem;
    }

    .back-link a {
      color: #4ca1af;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .back-link a:hover {
      color: #2c3e50;
    }

    .error-message {
      background: #ffebee;
      color: #c62828;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      border-left: 4px solid #c62828;
      text-align: center;
    }

    
    

    @media (max-width: 480px) {
      .login-container {
        margin: 1rem;
        padding: 2rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="logo-section">
      <img src="images/logo.png" alt="Cookistry Logo" />
      <h2>Admin Panel</h2>
      <p>Login to manage recipes</p>
    </div>


    <?php if ($error_message): ?>
    <div class="error-message">
      <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required placeholder="Enter admin username" 
               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="Enter admin password">
      </div>

      <button type="submit" class="login-btn">Login to Admin Panel</button>
    </form>

    <div class="back-link">
      <a href="index.html">← Back to Cookistry</a>
    </div>
  </div>

  <script>
    // Simple animation on page load
    document.addEventListener('DOMContentLoaded', function() {
      const container = document.querySelector('.login-container');
      container.style.opacity = '0';
      container.style.transform = 'translateY(20px)';
      
      setTimeout(() => {
        container.style.transition = 'all 0.5s ease';
        container.style.opacity = '1';
        container.style.transform = 'translateY(0)';
      }, 100);
    });
  </script>
</body>
</html>
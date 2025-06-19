<?php
session_start();
require_once 'config.php';
require_once 'auth_functions.php';

// Require login
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';

// Generate CSRF token
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        // Sanitize inputs
        $title = sanitizeInput($_POST['title']);
        $category = sanitizeInput($_POST['category']);
        $description = sanitizeInput($_POST['description']);
        $ingredients = sanitizeInput($_POST['ingredients']);
        $instructions = sanitizeInput($_POST['instructions']);
        $prep_time = sanitizeInput($_POST['prep_time']);
        $cook_time = sanitizeInput($_POST['cook_time'] ?? '');
        $total_time = sanitizeInput($_POST['total_time'] ?? '');
        $servings = (int)($_POST['servings'] ?? 4);
        $difficulty = sanitizeInput($_POST['difficulty']);
        $image_url = sanitizeInput($_POST['image_url'] ?? ''); // Keep as fallback
        $video_url = sanitizeInput($_POST['video_url'] ?? '');
        $tags = sanitizeInput($_POST['tags'] ?? '');
        $recipe_notes = sanitizeInput($_POST['recipe_notes'] ?? '');
        $cuisine_type = sanitizeInput($_POST['cuisine_type'] ?? '');
        $dietary_restrictions = sanitizeInput($_POST['dietary_restrictions'] ?? '');
        
        // Nutrition fields
        $calories_per_serving = (int)($_POST['calories_per_serving'] ?? 0);
        $protein_per_serving = floatval($_POST['protein_per_serving'] ?? 0);
        $carbs_per_serving = floatval($_POST['carbs_per_serving'] ?? 0);
        $fat_per_serving = floatval($_POST['fat_per_serving'] ?? 0);
        $fiber_per_serving = floatval($_POST['fiber_per_serving'] ?? 0);
        $sugar_per_serving = floatval($_POST['sugar_per_serving'] ?? 0);
        $sodium_per_serving = floatval($_POST['sodium_per_serving'] ?? 0);
        
        // Validation
        if (empty($title) || empty($category) || empty($description) || empty($ingredients) || empty($instructions)) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                // Handle image upload
                $image_path = '';
                if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] == 0) {
                    $upload_result = handleImageUpload($_FILES['recipe_image']);
                    if ($upload_result['success']) {
                        $image_path = $upload_result['path'];
                    } else {
                        $error = $upload_result['error'];
                    }
                } elseif (!empty($image_url)) {
                    // Fallback to URL if provided
                    $image_path = $image_url;
                }
                
                // Handle video upload
                $video_path = '';
                $video_file = '';
                if (isset($_FILES['recipe_video']) && $_FILES['recipe_video']['error'] == 0) {
                    $video_upload_result = handleVideoUpload($_FILES['recipe_video']);
                    if ($video_upload_result['success']) {
                        $video_path = $video_upload_result['path'];
                        $video_file = $video_upload_result['filename'];
                    } else {
                        $error = $video_upload_result['error'];
                    }
                }
                
                if (empty($error)) {
                    // Generate unique slug
                    $slug = generateSlug($title);
                    
                    // Insert recipe
                    $stmt = $pdo->prepare("
                        INSERT INTO recipes (
                            title, slug, image, video_url, video_path, video_file, description, ingredients, instructions, 
                            recipe_notes, tags, prep_time, cook_time, total_time, servings, difficulty, 
                            category, cuisine_type, dietary_restrictions, 
                            calories_per_serving, protein_per_serving, carbs_per_serving, fat_per_serving, 
                            fiber_per_serving, sugar_per_serving, sodium_per_serving,
                            rating, uploaded_by_user_id, is_user_recipe, moderation_status, status, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 4.5, ?, 1, 'pending', 'draft', NOW(), NOW())
                    ");
                    
                    $result = $stmt->execute([
                        $title, $slug, $image_path, $video_url, $video_path, $video_file, $description, $ingredients, $instructions,
                        $recipe_notes, $tags, $prep_time, $cook_time, $total_time, $servings, $difficulty,
                        $category, $cuisine_type, $dietary_restrictions,
                        $calories_per_serving, $protein_per_serving, $carbs_per_serving, $fat_per_serving,
                        $fiber_per_serving, $sugar_per_serving, $sodium_per_serving,
                        $user['id']
                    ]);
                    
                    if ($result) {
                        $success = "Recipe submitted successfully! It will be reviewed before publishing.";
                        // Clear form data
                        $_POST = array();
                    } else {
                        $error = "Failed to submit recipe. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                logError("User recipe upload error: " . $e->getMessage());
                $error = "An error occurred. Please try again.";
            }
        }
    }
}

// Helper function to handle image upload
function handleImageUpload($file) {
    $uploadDir = 'uploads/images/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'recipe_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Failed to upload image.'];
    }
}

// Helper function to handle video upload
function handleVideoUpload($file) {
    $uploadDir = 'uploads/videos/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    $allowedTypes = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo'];
    $maxSize = 100 * 1024 * 1024; // 100MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid video type. Please upload MP4, MPEG, MOV, or AVI videos.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Video too large. Maximum size is 100MB.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'recipe_video_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Failed to upload video.'];
    }
}

// Helper function to generate slug
function generateSlug($title) {
    global $pdo;
    
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    $originalSlug = $slug;
    $counter = 1;
    
    // Ensure slug is unique
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE slug = ?");
        $stmt->execute([$slug]);
        
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Recipe - Cookistry</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, 
                rgba(76, 161, 175, 0.1) 0%, 
                rgba(44, 62, 80, 0.05) 25%,
                rgba(235, 242, 247, 1) 50%,
                rgba(76, 161, 175, 0.08) 75%,
                rgba(44, 62, 80, 0.1) 100%);
            min-height: 100vh;
            color: #333;
        }

        /* Header - Same as dashboard */
        .header {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: white;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4ca1af 0%, #2c3e50 50%, #4ca1af 100%);
            animation: shimmer 3s ease-in-out infinite;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .page-header p {
            color: #64748b;
            font-size: 1.2rem;
            font-weight: 400;
        }

        /* Form Container */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        /* Alert */
        .alert {
            padding: 1.5rem 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
            backdrop-filter: blur(10px);
            animation: alertSlideIn 0.6s ease-out;
            border: 1px solid;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%);
            color: #dc2626;
            border-color: rgba(239, 68, 68, 0.3);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.15);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%);
            color: #059669;
            border-color: rgba(16, 185, 129, 0.3);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 3rem;
            padding: 2rem;
            background: rgba(248, 250, 252, 0.5);
            border-radius: 15px;
            border: 2px solid rgba(76, 161, 175, 0.1);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.8rem;
            font-size: 1rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1.2rem 1.5rem;
            border: 2px solid rgba(229, 231, 235, 0.8);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            font-family: 'Poppins', sans-serif;
            color: #374151;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4ca1af;
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-2px);
            box-shadow: 
                0 8px 25px rgba(76, 161, 175, 0.15),
                0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
        }

        .required {
            color: #ef4444;
        }

        .form-note {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.08) 0%, rgba(59, 130, 246, 0.05) 100%);
            border: 2px solid rgba(14, 165, 233, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #0c4a6e;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        /* File Upload Styles */
        .file-upload-container {
            position: relative;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-container:hover {
            border-color: #4ca1af;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }

        .file-upload-container.dragover {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            font-size: 3rem;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .upload-text {
            font-size: 1.1rem;
            color: #374151;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .upload-subtext {
            font-size: 0.9rem;
            color: #64748b;
        }

        .file-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            display: none;
        }

        .file-preview.show {
            display: block;
        }

        .preview-image {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .file-info {
            font-size: 0.9rem;
            color: #64748b;
        }

        /* Video Upload Specific */
        .video-upload-container {
            background: linear-gradient(135deg, #fef3f2 0%, #fee2e2 100%);
            border-color: #f87171;
        }

        .video-upload-container:hover {
            border-color: #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
        }

        .video-preview {
            max-width: 300px;
            max-height: 200px;
            border-radius: 8px;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1.5rem 2.5rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.8s;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(76, 161, 175, 0.4);
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        /* Nutrition Grid */
        .nutrition-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .nutrition-item {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
        }

        .nutrition-item:hover {
            border-color: #4ca1af;
            transform: translateY(-2px);
        }

        .nutrition-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #4ca1af;
            display: block;
        }

        .nutrition-item input {
            margin-top: 0.5rem;
            text-align: center;
            font-weight: 600;
        }

        /* Tag suggestions */
        .tag-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .tag-suggestion {
            background: #4ca1af;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tag-suggestion:hover {
            background: #2c3e50;
            transform: scale(1.05);
        }

        /* Animations */
        @keyframes shimmer {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(400%); }
        }

        @keyframes alertSlideIn {
            0% {
                opacity: 0;
                transform: translateX(-30px) scale(0.9);
            }
            100% {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .main-content {
                padding: 0 1rem;
                margin: 1rem auto;
            }
            
            .page-header, .form-container {
                padding: 2rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .nutrition-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .page-header, .form-container {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 1rem;
            }
            
            .submit-btn {
                padding: 1.2rem 2rem;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">🍳</div>
                <span class="logo-text">Cookistry</span>
            </a>
            
            <div class="nav-links">
                <a href="user-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my-recipes.php"><i class="fas fa-utensils"></i> My Recipes</a>
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <section class="page-header">
            <h1><i class="fas fa-utensils"></i> Upload Your Recipe</h1>
            <p>Share your delicious recipe with our cooking community</p>
        </section>

        <!-- Form Container -->
        <section class="form-container">
            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <div class="form-note">
                <i class="fas fa-info-circle"></i>
                <span>All fields marked with <span class="required">*</span> are required. Your recipe will be reviewed before being published.</span>
            </div>

            <form method="POST" enctype="multipart/form-data" id="recipeForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <!-- Basic Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </h3>
                    
                    <div class="form-group">
                        <label for="title">
                            Recipe Title <span class="required">*</span>
                        </label>
                        <input type="text" id="title" name="title" required 
                               placeholder="Enter your recipe title"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">
                                Category <span class="required">*</span>
                            </label>
                            <select id="category" name="category" required>
                                <option value="">Select a category</option>
                                <option value="appetizer" <?php echo (isset($_POST['category']) && $_POST['category'] == 'appetizer') ? 'selected' : ''; ?>>Appetizer</option>
                                <option value="breakfast" <?php echo (isset($_POST['category']) && $_POST['category'] == 'breakfast') ? 'selected' : ''; ?>>Breakfast</option>
                                <option value="lunch" <?php echo (isset($_POST['category']) && $_POST['category'] == 'lunch') ? 'selected' : ''; ?>>Lunch</option>
                                <option value="dinner" <?php echo (isset($_POST['category']) && $_POST['category'] == 'dinner') ? 'selected' : ''; ?>>Dinner</option>
                                <option value="dessert" <?php echo (isset($_POST['category']) && $_POST['category'] == 'dessert') ? 'selected' : ''; ?>>Dessert</option>
                                <option value="bread-bakes" <?php echo (isset($_POST['category']) && $_POST['category'] == 'bread-bakes') ? 'selected' : ''; ?>>Bread & Bakes</option>
                                <option value="salads" <?php echo (isset($_POST['category']) && $_POST['category'] == 'salads') ? 'selected' : ''; ?>>Salads</option>
                                <option value="healthy" <?php echo (isset($_POST['category']) && $_POST['category'] == 'healthy') ? 'selected' : ''; ?>>Healthy Food</option>
                                <option value="beverages" <?php echo (isset($_POST['category']) && $_POST['category'] == 'beverages') ? 'selected' : ''; ?>>Beverages</option>
                                <option value="snacks" <?php echo (isset($_POST['category']) && $_POST['category'] == 'snacks') ? 'selected' : ''; ?>>Snacks</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="difficulty">Difficulty Level</label>
                            <select id="difficulty" name="difficulty">
                                <option value="">Select difficulty</option>
                                <option value="Easy" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Easy') ? 'selected' : ''; ?>>Easy</option>
                                <option value="Medium" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                <option value="Hard" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Hard') ? 'selected' : ''; ?>>Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">
                            Description <span class="required">*</span>
                        </label>
                        <textarea id="description" name="description" required 
                                  placeholder="Briefly describe your recipe"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="cuisine_type">Cuisine Type</label>
                            <input type="text" id="cuisine_type" name="cuisine_type" 
                                   placeholder="e.g., Italian, Chinese, Mexican"
                                   value="<?php echo isset($_POST['cuisine_type']) ? htmlspecialchars($_POST['cuisine_type']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="dietary_restrictions">Dietary Restrictions</label>
                            <input type="text" id="dietary_restrictions" name="dietary_restrictions" 
                                   placeholder="e.g., Vegetarian, Vegan, Gluten-Free"
                                   value="<?php echo isset($_POST['dietary_restrictions']) ? htmlspecialchars($_POST['dietary_restrictions']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- Recipe Content -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-list"></i> Recipe Content
                    </h3>
                    
                    <div class="form-group">
                        <label for="ingredients">
                            Ingredients <span class="required">*</span>
                        </label>
                        <textarea id="ingredients" name="ingredients" required 
                                  placeholder="List all ingredients (one per line)"><?php echo isset($_POST['ingredients']) ? htmlspecialchars($_POST['ingredients']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="instructions">
                            Cooking Instructions <span class="required">*</span>
                        </label>
                        <textarea id="instructions" name="instructions" required 
                                  placeholder="Step-by-step cooking instructions"><?php echo isset($_POST['instructions']) ? htmlspecialchars($_POST['instructions']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="recipe_notes">Recipe Notes</label>
                        <textarea id="recipe_notes" name="recipe_notes" 
                                  placeholder="Any additional tips or notes"><?php echo isset($_POST['recipe_notes']) ? htmlspecialchars($_POST['recipe_notes']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Timing & Serving -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-clock"></i> Timing & Serving
                    </h3>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="prep_time">Preparation Time</label>
                            <input type="text" id="prep_time" name="prep_time" 
                                   placeholder="e.g., 30 minutes"
                                   value="<?php echo isset($_POST['prep_time']) ? htmlspecialchars($_POST['prep_time']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="cook_time">Cooking Time</label>
                            <input type="text" id="cook_time" name="cook_time" 
                                   placeholder="e.g., 45 minutes"
                                   value="<?php echo isset($_POST['cook_time']) ? htmlspecialchars($_POST['cook_time']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="servings">Servings</label>
                            <input type="number" id="servings" name="servings" 
                                   placeholder="Number of servings" min="1"
                                   value="<?php echo isset($_POST['servings']) ? htmlspecialchars($_POST['servings']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- Nutrition Information -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-apple-alt"></i> Nutrition Information (Optional)
                    </h3>
                    
                    <div class="nutrition-grid">
                        <div class="nutrition-item">
                            <i class="fas fa-fire nutrition-icon"></i>
                            <label for="calories_per_serving">Calories</label>
                            <input type="number" id="calories_per_serving" name="calories_per_serving" 
                                   min="0" placeholder="0"
                                   value="<?php echo isset($_POST['calories_per_serving']) ? htmlspecialchars($_POST['calories_per_serving']) : ''; ?>">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-dumbbell nutrition-icon"></i>
                            <label for="protein_per_serving">Protein (g)</label>
                            <input type="number" id="protein_per_serving" name="protein_per_serving" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo isset($_POST['protein_per_serving']) ? htmlspecialchars($_POST['protein_per_serving']) : ''; ?>">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-bread-slice nutrition-icon"></i>
                            <label for="carbs_per_serving">Carbs (g)</label>
                            <input type="number" id="carbs_per_serving" name="carbs_per_serving" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo isset($_POST['carbs_per_serving']) ? htmlspecialchars($_POST['carbs_per_serving']) : ''; ?>">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-cheese nutrition-icon"></i>
                            <label for="fat_per_serving">Fat (g)</label>
                            <input type="number" id="fat_per_serving" name="fat_per_serving" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo isset($_POST['fat_per_serving']) ? htmlspecialchars($_POST['fat_per_serving']) : ''; ?>">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-seedling nutrition-icon"></i>
                            <label for="fiber_per_serving">Fiber (g)</label>
                            <input type="number" id="fiber_per_serving" name="fiber_per_serving" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo isset($_POST['fiber_per_serving']) ? htmlspecialchars($_POST['fiber_per_serving']) : ''; ?>">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-candy-cane nutrition-icon"></i>
                            <label for="sugar_per_serving">Sugar (g)</label>
                            <input type="number" id="sugar_per_serving" name="sugar_per_serving" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo isset($_POST['sugar_per_serving']) ? htmlspecialchars($_POST['sugar_per_serving']) : ''; ?>">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-tint nutrition-icon"></i>
                            <label for="sodium_per_serving">Sodium (mg)</label>
                            <input type="number" id="sodium_per_serving" name="sodium_per_serving" 
                                   min="0" step="0.1" placeholder="0"
                                   value="<?php echo isset($_POST['sodium_per_serving']) ? htmlspecialchars($_POST['sodium_per_serving']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <!-- Media Upload -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-camera"></i> Recipe Image & Video
                    </h3>
                    
                    <!-- Recipe Image Upload -->
                    <div class="form-group">
                        <label for="recipe_image">
                            Recipe Image <span class="required">*</span>
                        </label>
                        <div class="file-upload-container" onclick="document.getElementById('recipe_image').click()">
                            <input type="file" id="recipe_image" name="recipe_image" class="file-upload-input" 
                                   accept="image/*" required onchange="previewImage(this)">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <div class="upload-text">Click to upload recipe image</div>
                            <div class="upload-subtext">or drag and drop your image here</div>
                            <div class="upload-subtext">Supported formats: JPG, PNG, GIF, WebP (max 5MB)</div>
                            
                            <div class="file-preview" id="imagePreview">
                                <img class="preview-image" id="previewImg" alt="Preview">
                                <div class="file-info" id="imageInfo"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Recipe Video Upload (Optional) -->
                    <div class="form-group">
                        <label for="recipe_video">Recipe Video (Optional)</label>
                        <div class="file-upload-container video-upload-container" onclick="document.getElementById('recipe_video').click()">
                            <input type="file" id="recipe_video" name="recipe_video" class="file-upload-input" 
                                   accept="video/*" onchange="previewVideo(this)">
                            <i class="fas fa-video upload-icon"></i>
                            <div class="upload-text">Click to upload recipe video</div>
                            <div class="upload-subtext">or drag and drop your video here</div>
                            <div class="upload-subtext">Supported formats: MP4, MPEG, MOV, AVI (max 100MB)</div>
                            
                            <div class="file-preview" id="videoPreview">
                                <video class="video-preview" id="previewVideo" controls>
                                    Your browser does not support the video tag.
                                </video>
                                <div class="file-info" id="videoInfo"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Video URL Alternative -->
                    <div class="form-group">
                        <label for="video_url">Or YouTube Video URL</label>
                        <input type="url" id="video_url" name="video_url" 
                               placeholder="https://youtube.com/watch?v=..."
                               value="<?php echo isset($_POST['video_url']) ? htmlspecialchars($_POST['video_url']) : ''; ?>">
                        <div style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">
                            <i class="fas fa-info-circle"></i> You can either upload a video file or provide a YouTube URL
                        </div>
                    </div>
                </div>

                <!-- Tags -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-tags"></i> Tags & Keywords
                    </h3>
                    
                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" 
                               placeholder="e.g., vegetarian, healthy, quick (comma separated)"
                               value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>">
                        
                        <div class="tag-suggestions">
                            <span class="tag-suggestion" onclick="addTag('quick')">quick</span>
                            <span class="tag-suggestion" onclick="addTag('healthy')">healthy</span>
                            <span class="tag-suggestion" onclick="addTag('vegetarian')">vegetarian</span>
                            <span class="tag-suggestion" onclick="addTag('comfort food')">comfort food</span>
                            <span class="tag-suggestion" onclick="addTag('family friendly')">family friendly</span>
                            <span class="tag-suggestion" onclick="addTag('budget friendly')">budget friendly</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Recipe for Review
                </button>
            </form>
        </section>
    </main>

    <script>
        // Image preview function
        function previewImage(input) {
            const file = input.files[0];
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            const imageInfo = document.getElementById('imageInfo');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imageInfo.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                    preview.classList.add('show');
                };
                reader.readAsDataURL(file);
            }
        }

        // Video preview function
        function previewVideo(input) {
            const file = input.files[0];
            const preview = document.getElementById('videoPreview');
            const previewVideo = document.getElementById('previewVideo');
            const videoInfo = document.getElementById('videoInfo');
            
            if (file) {
                const url = URL.createObjectURL(file);
                previewVideo.src = url;
                videoInfo.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                preview.classList.add('show');
            }
        }

        // Drag and drop functionality
        function setupDragAndDrop() {
            const uploadContainers = document.querySelectorAll('.file-upload-container');
            
            uploadContainers.forEach(container => {
                container.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });
                
                container.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                });
                
                container.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const input = this.querySelector('.file-upload-input');
                        input.files = files;
                        
                        if (input.accept.includes('image')) {
                            previewImage(input);
                        } else if (input.accept.includes('video')) {
                            previewVideo(input);
                        }
                    }
                });
            });
        }

        // Add tag functionality
        function addTag(tag) {
            const tagsInput = document.getElementById('tags');
            const currentTags = tagsInput.value;
            
            if (currentTags) {
                if (!currentTags.includes(tag)) {
                    tagsInput.value = currentTags + ', ' + tag;
                }
            } else {
                tagsInput.value = tag;
            }
        }

        // Auto-calculate total time
        function calculateTotalTime() {
            const prepTime = document.getElementById('prep_time').value;
            const cookTime = document.getElementById('cook_time').value;
            const totalTimeField = document.getElementById('total_time');
            
            if (prepTime && cookTime && totalTimeField) {
                const prepMinutes = parseInt(prepTime.match(/\d+/)) || 0;
                const cookMinutes = parseInt(cookTime.match(/\d+/)) || 0;
                const totalMinutes = prepMinutes + cookMinutes;
                totalTimeField.value = totalMinutes + ' minutes';
            }
        }

        // Form validation
        document.getElementById('recipeForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            
            // Basic validation
            const requiredFields = ['title', 'category', 'description', 'ingredients', 'instructions'];
            let isValid = true;
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#ef4444';
                } else {
                    field.style.borderColor = 'rgba(229, 231, 235, 0.8)';
                }
            });
            
            // Check if image is uploaded
            const imageField = document.getElementById('recipe_image');
            if (!imageField.files.length) {
                isValid = false;
                alert('Please upload a recipe image.');
                return;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Recipe...';
            submitBtn.disabled = true;
            
            // Re-enable button after form submission
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Real-time validation feedback
        const inputs = document.querySelectorAll('input[required], textarea[required], select[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#ef4444';
                }
            });

            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '#4ca1af';
                }
            });
        });

        // Auto-resize textareas
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });

        // Initialize drag and drop
        document.addEventListener('DOMContentLoaded', function() {
            setupDragAndDrop();
            
            // Add event listeners for time calculation
            document.getElementById('prep_time').addEventListener('input', calculateTotalTime);
            document.getElementById('cook_time').addEventListener('input', calculateTotalTime);
        });

        // Smooth scrolling for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
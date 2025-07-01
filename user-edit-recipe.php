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
$recipe = null;

// Get recipe ID from URL
$recipe_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recipe_id <= 0) {
    header('Location: my-recipes.php');
    exit;
}

// Fetch recipe data - ONLY user's own recipes
try {
    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND author_id = ? AND is_user_recipe = 1");
    $stmt->execute([$recipe_id, $user['id']]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recipe) {
        $error = "Recipe not found or you don't have permission to edit it!";
    }
} catch(PDOException $e) {
    logError("User recipe edit fetch error: " . $e->getMessage());
    $error = "Database error occurred.";
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Handle form submission
if ($_POST && $recipe) {
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
                // Handle image upload (optional for edit)
                $image_path = $recipe['image']; // Keep existing image by default
                if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] == 0) {
                    $upload_result = handleImageUpload($_FILES['recipe_image']);
                    if ($upload_result['success']) {
                        // Delete old image if exists
                        if ($recipe['image'] && file_exists($recipe['image'])) {
                            unlink($recipe['image']);
                        }
                        $image_path = $upload_result['path'];
                    } else {
                        $error = $upload_result['error'];
                    }
                }
                
                // Handle video upload
                $video_path = $recipe['video_path'];
                $video_file = $recipe['video_file'];
                if (isset($_FILES['recipe_video']) && $_FILES['recipe_video']['error'] == 0) {
                    $video_upload_result = handleVideoUpload($_FILES['recipe_video']);
                    if ($video_upload_result['success']) {
                        // Delete old video if exists
                        if ($recipe['video_path'] && file_exists($recipe['video_path'])) {
                            unlink($recipe['video_path']);
                        }
                        $video_path = $video_upload_result['path'];
                        $video_file = $video_upload_result['filename'];
                    } else {
                        $error = $video_upload_result['error'];
                    }
                }
                
                if (empty($error)) {
                    // Generate slug if title changed
                    $slug = ($title !== $recipe['title']) ? generateSlug($title) : $recipe['slug'];
                    
                    // Update recipe - User can only update basic fields, NOT status/moderation
                    $stmt = $pdo->prepare("
                        UPDATE recipes SET 
                            title = ?, 
                            slug = ?, 
                            image = ?, 
                            video_url = ?, 
                            video_path = ?, 
                            video_file = ?, 
                            description = ?, 
                            ingredients = ?, 
                            instructions = ?, 
                            recipe_notes = ?,
                            tags = ?, 
                            prep_time = ?, 
                            cook_time = ?, 
                            total_time = ?,
                            servings = ?, 
                            difficulty = ?, 
                            category = ?,
                            cuisine_type = ?,
                            dietary_restrictions = ?,
                            calories_per_serving = ?,
                            protein_per_serving = ?,
                            carbs_per_serving = ?,
                            fat_per_serving = ?,
                            fiber_per_serving = ?,
                            sugar_per_serving = ?,
                            sodium_per_serving = ?,
                            updated_at = NOW()
                        WHERE id = ? AND author_id = ?
                    ");
                    
                    if ($stmt->execute([
                        $title, $slug, $image_path, $video_url, $video_path, $video_file, 
                        $description, $ingredients, $instructions, $recipe_notes, $tags, 
                        $prep_time, $cook_time, $total_time, $servings, $difficulty, 
                        $category, $cuisine_type, $dietary_restrictions,
                        $calories_per_serving, $protein_per_serving, $carbs_per_serving, 
                        $fat_per_serving, $fiber_per_serving, $sugar_per_serving, $sodium_per_serving,
                        $recipe_id, $user['id']
                    ])) {
                        $success = "Recipe updated successfully!";
                        // Refresh recipe data
                        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND author_id = ?");
                        $stmt->execute([$recipe_id, $user['id']]);
                        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = "Error updating recipe!";
                    }
                }
            } catch(PDOException $e) {
                logError("User recipe update error: " . $e->getMessage());
                $error = "An error occurred. Please try again.";
            }
        }
    }
}

// Helper functions (same as upload-recipe.php)
function handleImageUpload($file) {
    $uploadDir = 'uploads/images/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'recipe_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Failed to upload image.'];
    }
}

function handleVideoUpload($file) {
    $uploadDir = 'uploads/videos/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo'];
    $maxSize = 100 * 1024 * 1024; // 100MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid video type. Please upload MP4, MPEG, MOV, or AVI videos.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Video too large. Maximum size is 100MB.'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'recipe_video_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $filepath, 'filename' => $filename];
    } else {
        return ['success' => false, 'error' => 'Failed to upload video.'];
    }
}

function generateSlug($title) {
    global $pdo;
    
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    $originalSlug = $slug;
    $counter = 1;
    
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
    <title>Edit Recipe - Cookistry</title>
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

        /* Header */
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

        .current-image {
            max-width: 200px;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: block;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(76, 161, 175, 0.4);
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
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
            <h1><i class="fas fa-edit"></i> Edit Your Recipe</h1>
            <p>Update your recipe details</p>
        </section>

        <!-- Form Container -->
        <section class="form-container">
            <?php if (!$recipe): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="my-recipes.php" class="submit-btn" style="display: inline-block; width: auto; padding: 1rem 2rem;">
                        <i class="fas fa-arrow-left"></i> Back to My Recipes
                    </a>
                </div>
            <?php else: ?>
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
                                   value="<?php echo htmlspecialchars($recipe['title']); ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="category">
                                    Category <span class="required">*</span>
                                </label>
                                <select id="category" name="category" required>
                                    <option value="">Select a category</option>
                                    <option value="appetizer" <?php echo ($recipe['category'] == 'appetizer') ? 'selected' : ''; ?>>Appetizer</option>
                                    <option value="breakfast" <?php echo ($recipe['category'] == 'breakfast') ? 'selected' : ''; ?>>Breakfast</option>
                                    <option value="lunch" <?php echo ($recipe['category'] == 'lunch') ? 'selected' : ''; ?>>Lunch</option>
                                    <option value="dinner" <?php echo ($recipe['category'] == 'dinner') ? 'selected' : ''; ?>>Dinner</option>
                                    <option value="dessert" <?php echo ($recipe['category'] == 'dessert') ? 'selected' : ''; ?>>Dessert</option>
                                    <option value="bread-bakes" <?php echo ($recipe['category'] == 'bread-bakes') ? 'selected' : ''; ?>>Bread & Bakes</option>
                                    <option value="salads" <?php echo ($recipe['category'] == 'salads') ? 'selected' : ''; ?>>Salads</option>
                                    <option value="healthy" <?php echo ($recipe['category'] == 'healthy') ? 'selected' : ''; ?>>Healthy Food</option>
                                    <option value="beverages" <?php echo ($recipe['category'] == 'beverages') ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="snacks" <?php echo ($recipe['category'] == 'snacks') ? 'selected' : ''; ?>>Snacks</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="difficulty">Difficulty Level</label>
                                <select id="difficulty" name="difficulty">
                                    <option value="">Select difficulty</option>
                                    <option value="Easy" <?php echo ($recipe['difficulty'] == 'Easy') ? 'selected' : ''; ?>>Easy</option>
                                    <option value="Medium" <?php echo ($recipe['difficulty'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                    <option value="Hard" <?php echo ($recipe['difficulty'] == 'Hard') ? 'selected' : ''; ?>>Hard</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">
                                Description <span class="required">*</span>
                            </label>
                            <textarea id="description" name="description" required 
                                      placeholder="Briefly describe your recipe"><?php echo htmlspecialchars($recipe['description']); ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="cuisine_type">Cuisine Type</label>
                                <input type="text" id="cuisine_type" name="cuisine_type" 
                                       placeholder="e.g., Italian, Chinese, Mexican"
                                       value="<?php echo htmlspecialchars($recipe['cuisine_type'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="dietary_restrictions">Dietary Restrictions</label>
                                <input type="text" id="dietary_restrictions" name="dietary_restrictions" 
                                       placeholder="e.g., Vegetarian, Vegan, Gluten-Free"
                                       value="<?php echo htmlspecialchars($recipe['dietary_restrictions'] ?? ''); ?>">
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
                                      placeholder="List all ingredients (one per line)"><?php echo htmlspecialchars($recipe['ingredients']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="instructions">
                                Cooking Instructions <span class="required">*</span>
                            </label>
                            <textarea id="instructions" name="instructions" required 
                                      placeholder="Step-by-step cooking instructions"><?php echo htmlspecialchars($recipe['instructions']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="recipe_notes">Recipe Notes</label>
                            <textarea id="recipe_notes" name="recipe_notes" 
                                      placeholder="Any additional tips or notes"><?php echo htmlspecialchars($recipe['recipe_notes'] ?? ''); ?></textarea>
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
                                       value="<?php echo htmlspecialchars($recipe['prep_time'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="cook_time">Cooking Time</label>
                                <input type="text" id="cook_time" name="cook_time" 
                                       placeholder="e.g., 45 minutes"
                                       value="<?php echo htmlspecialchars($recipe['cook_time'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="servings">Servings</label>
                                <input type="number" id="servings" name="servings" 
                                       placeholder="Number of servings" min="1"
                                       value="<?php echo htmlspecialchars($recipe['servings'] ?? ''); ?>">
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
                                       value="<?php echo htmlspecialchars($recipe['calories_per_serving'] ?? ''); ?>">
                            </div>

                            <div class="nutrition-item">
                                <i class="fas fa-dumbbell nutrition-icon"></i>
                                <label for="protein_per_serving">Protein (g)</label>
                                <input type="number" id="protein_per_serving" name="protein_per_serving" 
                                       min="0" step="0.1" placeholder="0"
                                       value="<?php echo htmlspecialchars($recipe['protein_per_serving'] ?? ''); ?>">
                            </div>

                            <div class="nutrition-item">
                                <i class="fas fa-bread-slice nutrition-icon"></i>
                                <label for="carbs_per_serving">Carbs (g)</label>
                                <input type="number" id="carbs_per_serving" name="carbs_per_serving" 
                                       min="0" step="0.1" placeholder="0"
                                       value="<?php echo htmlspecialchars($recipe['carbs_per_serving'] ?? ''); ?>">
                            </div>

                            <div class="nutrition-item">
                                <i class="fas fa-cheese nutrition-icon"></i>
                                <label for="fat_per_serving">Fat (g)</label>
                                <input type="number" id="fat_per_serving" name="fat_per_serving" 
                                       min="0" step="0.1" placeholder="0"
                                       value="<?php echo htmlspecialchars($recipe['fat_per_serving'] ?? ''); ?>">
                            </div>

                            <div class="nutrition-item">
                                <i class="fas fa-seedling nutrition-icon"></i>
                                <label for="fiber_per_serving">Fiber (g)</label>
                                <input type="number" id="fiber_per_serving" name="fiber_per_serving" 
                                       min="0" step="0.1" placeholder="0"
                                       value="<?php echo htmlspecialchars($recipe['fiber_per_serving'] ?? ''); ?>">
                            </div>

                            <div class="nutrition-item">
                                <i class="fas fa-candy-cane nutrition-icon"></i>
                                <label for="sugar_per_serving">Sugar (g)</label>
                                <input type="number" id="sugar_per_serving" name="sugar_per_serving" 
                                       min="0" step="0.1" placeholder="0"
                                       value="<?php echo htmlspecialchars($recipe['sugar_per_serving'] ?? ''); ?>">
                            </div>

                            <div class="nutrition-item">
                                <i class="fas fa-tint nutrition-icon"></i>
                                <label for="sodium_per_serving">Sodium (mg)</label>
                                <input type="number" id="sodium_per_serving" name="sodium_per_serving" 
                                       min="0" step="0.1" placeholder="0"
                                       value="<?php echo htmlspecialchars($recipe['sodium_per_serving'] ?? ''); ?>">
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
                            <label for="recipe_image">Recipe Image</label>
                            <?php if (!empty($recipe['image']) && file_exists($recipe['image'])): ?>
                                <img src="<?php echo htmlspecialchars($recipe['image']); ?>" alt="Current Recipe Image" class="current-image">
                                <div style="font-size: 0.9rem; color: #64748b; margin-bottom: 1rem;">
                                    Current image shown above. Upload a new image to replace it.
                                </div>
                            <?php endif; ?>
                            <div class="file-upload-container" onclick="document.getElementById('recipe_image').click()">
                                <input type="file" id="recipe_image" name="recipe_image" class="file-upload-input" 
                                       accept="image/*" onchange="previewImage(this)">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <div class="upload-text">Click to upload new recipe image</div>
                                <div class="upload-subtext">or drag and drop your image here</div>
                                <div class="upload-subtext">Supported formats: JPG, PNG, GIF, WebP (max 5MB)</div>
                            </div>
                        </div>

                        <!-- Video URL -->
                        <div class="form-group">
                            <label for="video_url">YouTube Video URL</label>
                            <input type="url" id="video_url" name="video_url" 
                                   placeholder="https://youtube.com/watch?v=..."
                                   value="<?php echo htmlspecialchars($recipe['video_url'] ?? ''); ?>">
                            <div style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Add a YouTube video to enhance your recipe
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
                                   value="<?php echo htmlspecialchars($recipe['tags'] ?? ''); ?>">
                            
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
                        <i class="fas fa-save"></i> Update Recipe
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Image preview function
        function previewImage(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create preview if doesn't exist
                    let preview = document.querySelector('.current-image');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.className = 'current-image';
                        input.parentNode.insertBefore(preview, input.parentNode.firstChild);
                    }
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
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
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Recipe...';
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
    </script>
</body>
</html>
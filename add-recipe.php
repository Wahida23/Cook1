<?php
require_once 'config.php';

// Check if admin is logged in
checkAdminLogin();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_POST) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
    } else {
        // Check rate limiting
        if (!checkRateLimit('add_recipe', 10, 3600)) {
            $error_message = "Too many requests. Please wait before adding another recipe.";
        } else {
            // Sanitize inputs
            $title = sanitizeInput($_POST['title']);
            $category = sanitizeInput($_POST['category']);
            $prep_time = sanitizeInput($_POST['prep_time']);
            $cook_time = sanitizeInput($_POST['cook_time'] ?? '');
            $total_time = sanitizeInput($_POST['total_time'] ?? '');
            $servings = (int)($_POST['servings'] ?? 4);
            $difficulty = sanitizeInput($_POST['difficulty']);
            $rating = floatval($_POST['rating']);
            $description = sanitizeInput($_POST['description']);
            $ingredients = sanitizeInput($_POST['ingredients']);
            $instructions = sanitizeInput($_POST['instructions']);
            $recipe_notes = sanitizeInput($_POST['recipe_notes'] ?? '');
            $tags = sanitizeInput($_POST['tags']);
            
            // Enhanced cuisine and dietary fields
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
            
            // Enhanced video handling
            $video_url = sanitizeInput($_POST['video_url'] ?? '');
            $video_type = sanitizeInput($_POST['video_type'] ?? 'none');
            
            // Enhanced validation
            $validation_errors = [];
            
            if (strlen($title) < 3 || strlen($title) > 255) {
                $validation_errors[] = "Title must be between 3 and 255 characters.";
            }
            
            // UPDATED: New categories validation
            if (!in_array($category, ['appetizer', 'breakfast', 'lunch', 'dinner', 'dessert', 'bread-bakes', 'salads', 'healthy', 'beverages', 'snacks'])) {
                $validation_errors[] = "Invalid category selected.";
            }
            
            if (!in_array($difficulty, ['Easy', 'Medium', 'Hard'])) {
                $validation_errors[] = "Invalid difficulty level.";
            }
            
            if ($rating < 1.0 || $rating > 5.0) {
                $validation_errors[] = "Rating must be between 1.0 and 5.0.";
            }
            
            if (strlen($description) < 10) {
                $validation_errors[] = "Description must be at least 10 characters long.";
            }
            
            if (strlen($ingredients) < 10) {
                $validation_errors[] = "Ingredients list must be at least 10 characters long.";
            }
            
            if (strlen($instructions) < 20) {
                $validation_errors[] = "Instructions must be at least 20 characters long.";
            }
            
            // Validate cuisine type length
            if (strlen($cuisine_type) > 100) {
                $validation_errors[] = "Cuisine type must be 100 characters or less.";
            }
            
            // Validate dietary restrictions length
            if (strlen($dietary_restrictions) > 255) {
                $validation_errors[] = "Dietary restrictions must be 255 characters or less.";
            }
            
            // Validate nutrition values
            if ($calories_per_serving < 0 || $calories_per_serving > 5000) {
                $validation_errors[] = "Calories per serving must be between 0 and 5000.";
            }
            
            if ($protein_per_serving < 0 || $protein_per_serving > 300) {
                $validation_errors[] = "Protein per serving must be between 0 and 300g.";
            }
            
            if ($carbs_per_serving < 0 || $carbs_per_serving > 500) {
                $validation_errors[] = "Carbs per serving must be between 0 and 500g.";
            }
            
            if ($fat_per_serving < 0 || $fat_per_serving > 200) {
                $validation_errors[] = "Fat per serving must be between 0 and 200g.";
            }
            
            // Enhanced video URL validation
            if (!empty($video_url) && $video_type == 'url') {
                if (!filter_var($video_url, FILTER_VALIDATE_URL)) {
                    $validation_errors[] = "Please enter a valid video URL.";
                } else {
                    // Auto-format YouTube URLs
                    $video_url = formatVideoUrl($video_url);
                }
            }
            
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $upload_result = secureFileUpload($_FILES['image']);
                
                if ($upload_result['success']) {
                    $image_path = $upload_result['filename'];
                } else {
                    $validation_errors = array_merge($validation_errors, $upload_result['errors']);
                }
            } else {
                $validation_errors[] = "Please upload a recipe image.";
            }
            
            // Handle video upload (optional)
            $video_path = '';
            if ($video_type == 'file' && isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
                $video_upload_result = secureVideoUpload($_FILES['video']);
                
                if ($video_upload_result['success']) {
                    $video_path = $video_upload_result['filename'];
                } else {
                    $validation_errors = array_merge($validation_errors, $video_upload_result['errors']);
                }
            }
            
            // Clear conflicting video fields
            if ($video_type == 'file') {
                $video_url = '';
            } elseif ($video_type == 'url') {
                $video_path = '';
            } else {
                $video_url = '';
                $video_path = '';
            }
            
            // If no validation errors, save to database
            if (empty($validation_errors)) {
                try {
                    // Generate unique slug
                    $slug = generateUniqueSlug($title, $pdo);
                    
                    // Insert recipe with enhanced fields (compatible with existing table)
                    $stmt = $pdo->prepare("
                        INSERT INTO recipes (
                            title, slug, image, video_path, video_url, prep_time, cook_time, total_time, servings, 
                            calories_per_serving, protein_per_serving, carbs_per_serving, fat_per_serving, 
                            fiber_per_serving, sugar_per_serving, sodium_per_serving,
                            difficulty, rating, description, ingredients, instructions, recipe_notes,
                            tags, category, cuisine_type, dietary_restrictions,
                            status, author_id, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?, NOW(), NOW())
                    ");
                    
                    if ($stmt->execute([
                        $title, $slug, $image_path, $video_path, $video_url, $prep_time, $cook_time, $total_time,
                        $servings, $calories_per_serving, $protein_per_serving, $carbs_per_serving, $fat_per_serving,
                        $fiber_per_serving, $sugar_per_serving, $sodium_per_serving,
                        $difficulty, $rating, $description, $ingredients, $instructions, $recipe_notes,
                        $tags, $category, $cuisine_type, $dietary_restrictions,
                        $_SESSION['admin_id'] ?? null
                    ])) {
                        $success_message = "Recipe added successfully!";
                        // Clear form data
                        $_POST = array();
                        
                        // Log successful addition
                        logError("Recipe added successfully: $title by " . ($_SESSION['admin_username'] ?? 'unknown'));
                    } else {
                        $error_message = "Error adding recipe to database!";
                    }
                } catch(PDOException $e) {
                    $error_message = "Database error: " . $e->getMessage();
                    logError("Database error in add-recipe: " . $e->getMessage());
                }
            } else {
                $error_message = implode('<br>', $validation_errors);
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Helper function to format video URLs
function formatVideoUrl($url) {
    // Convert YouTube watch URLs to embed format
    if (strpos($url, 'youtube.com/watch') !== false) {
        preg_match('/[?&]v=([^&]+)/', $url, $matches);
        if (isset($matches[1])) {
            return "https://youtu.be/" . $matches[1];
        }
    }
    
    // Convert youtu.be URLs to standard format
    if (strpos($url, 'youtu.be/') !== false) {
        return $url; // Already in correct format
    }
    
    return $url;
}

// Helper function to generate unique slug
function generateUniqueSlug($title, $pdo) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
    $slug = trim($slug, '-');
    
    $original_slug = $slug;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE slug = ?");
        $stmt->execute([$slug]);
        
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        
        $slug = $original_slug . '-' . $counter;
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
    <title>Add New Recipe - Cookistry Admin</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            color: #333;
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 2rem;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-section {
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);
            border-radius: 15px;
            border: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
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
            margin-bottom: 0.75rem;
            color: #333;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1.2rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
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

        .nutrition-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .nutrition-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #667eea;
            display: block;
        }

        .nutrition-item input {
            margin-top: 0.5rem;
            text-align: center;
            font-weight: 600;
        }

        .submit-btn {
            width: 100%;
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left-color: #dc3545;
        }

        .required {
            color: #e74c3c;
        }

        .cuisine-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 10px;
        }

        .cuisine-tag {
            background: #667eea;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cuisine-tag:hover {
            background: #764ba2;
            transform: scale(1.05);
        }

        .dietary-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 10px;
        }

        .dietary-tag {
            background: #10b981;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dietary-tag:hover {
            background: #059669;
            transform: scale(1.05);
        }

        .help-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
            font-style: italic;
        }

        /* YouTube Video Section */
        .youtube-section {
            background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin: 1rem 0;
        }

        .youtube-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .youtube-icon {
            font-size: 2.5rem;
        }

        .youtube-title {
            font-size: 1.3rem;
            font-weight: 600;
        }

        .youtube-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .youtube-controls {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .youtube-input {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            font-size: 1rem;
        }

        .youtube-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .youtube-input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.2);
        }

        .youtube-browse-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .youtube-browse-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .youtube-preview {
            margin-top: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 15px;
            display: none;
        }

        .youtube-preview.show {
            display: block;
        }

        .preview-thumbnail {
            width: 100%;
            max-width: 320px;
            height: 180px;
            border-radius: 8px;
            object-fit: cover;
            margin-bottom: 10px;
        }

        .preview-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .preview-url {
            font-size: 0.9rem;
            opacity: 0.8;
            word-break: break-all;
        }

        .youtube-tips {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 0.9rem;
        }

        .youtube-tips h4 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .youtube-tips ul {
            padding-left: 20px;
        }

        .youtube-tips li {
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .nutrition-grid {
                grid-template-columns: 1fr 1fr;
            }
            .youtube-controls {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .container {
                padding: 0 1rem;
            }
            .form-container {
                padding: 2rem;
            }
            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-plus-circle"></i> Add New Recipe</h1>
        <a href="admin-dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>

    <div class="container">
        <div class="form-container">
            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="recipeForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="video_type" value="url" id="videoType">
                
                <!-- Basic Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </h2>
                    
                    <div class="form-group">
                        <label for="title">Recipe Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                               placeholder="Enter a delicious recipe title..." maxlength="255">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category <span class="required">*</span></label>
                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="appetizer" <?php echo (isset($_POST['category']) && $_POST['category'] == 'appetizer') ? 'selected' : ''; ?>>🥗 Appetizer</option>
                                <option value="breakfast" <?php echo (isset($_POST['category']) && $_POST['category'] == 'breakfast') ? 'selected' : ''; ?>>🌅 Breakfast</option>
                                <option value="lunch" <?php echo (isset($_POST['category']) && $_POST['category'] == 'lunch') ? 'selected' : ''; ?>>🥪 Lunch</option>
                                <option value="dinner" <?php echo (isset($_POST['category']) && $_POST['category'] == 'dinner') ? 'selected' : ''; ?>>🍽️ Dinner</option>
                                <option value="dessert" <?php echo (isset($_POST['category']) && $_POST['category'] == 'dessert') ? 'selected' : ''; ?>>🍰 Dessert</option>
                                <option value="bread-bakes" <?php echo (isset($_POST['category']) && $_POST['category'] == 'bread-bakes') ? 'selected' : ''; ?>>🍞 Bread & Bakes</option>
                                <option value="salads" <?php echo (isset($_POST['category']) && $_POST['category'] == 'salads') ? 'selected' : ''; ?>>🥗 Salads</option>
                                <option value="healthy" <?php echo (isset($_POST['category']) && $_POST['category'] == 'healthy') ? 'selected' : ''; ?>>💪 Healthy</option>
                                <option value="beverages" <?php echo (isset($_POST['category']) && $_POST['category'] == 'beverages') ? 'selected' : ''; ?>>🥤 Beverages</option>
                                <option value="snacks" <?php echo (isset($_POST['category']) && $_POST['category'] == 'snacks') ? 'selected' : ''; ?>>🥜 Snacks</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cuisine_type">Cuisine Type</label>
                            <input type="text" id="cuisine_type" name="cuisine_type" 
                                   value="<?php echo isset($_POST['cuisine_type']) ? htmlspecialchars($_POST['cuisine_type']) : ''; ?>"
                                   placeholder="e.g., Italian, Chinese, Mexican..." maxlength="100">
                            <div class="cuisine-tags">
                                <span class="cuisine-tag" onclick="selectCuisine('Italian')">Italian</span>
                                <span class="cuisine-tag" onclick="selectCuisine('Chinese')">Chinese</span>
                                <span class="cuisine-tag" onclick="selectCuisine('Mexican')">Mexican</span>
                                <span class="cuisine-tag" onclick="selectCuisine('Indian')">Indian</span>
                                <span class="cuisine-tag" onclick="selectCuisine('French')">French</span>
                                <span class="cuisine-tag" onclick="selectCuisine('Japanese')">Japanese</span>
                                <span class="cuisine-tag" onclick="selectCuisine('Thai')">Thai</span>
                                <span class="cuisine-tag" onclick="selectCuisine('Mediterranean')">Mediterranean</span>
                                <span class="cuisine-tag" onclick="selectCuisine('American')">American</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" rows="4" required 
                                  placeholder="Describe your recipe in detail..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Timing & Serving Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-clock"></i>
                        Timing & Serving Information
                    </h2>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="prep_time">Prep Time <span class="required">*</span></label>
                            <input type="text" id="prep_time" name="prep_time" required 
                                   value="<?php echo isset($_POST['prep_time']) ? htmlspecialchars($_POST['prep_time']) : ''; ?>"
                                   placeholder="e.g., 15 minutes">
                        </div>

                        <div class="form-group">
                            <label for="cook_time">Cook Time</label>
                            <input type="text" id="cook_time" name="cook_time" 
                                   value="<?php echo isset($_POST['cook_time']) ? htmlspecialchars($_POST['cook_time']) : ''; ?>"
                                   placeholder="e.g., 30 minutes">
                        </div>

                        <div class="form-group">
                            <label for="total_time">Total Time</label>
                            <input type="text" id="total_time" name="total_time" 
                                   value="<?php echo isset($_POST['total_time']) ? htmlspecialchars($_POST['total_time']) : ''; ?>"
                                   placeholder="e.g., 45 minutes" readonly>
                            <div class="help-text">Auto-calculated from prep + cook time</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="servings">Servings</label>
                            <input type="number" id="servings" name="servings" min="1" max="50" 
                                   value="<?php echo isset($_POST['servings']) ? (int)$_POST['servings'] : 4; ?>">
                        </div>

                        <div class="form-group">
                            <label for="difficulty">Difficulty Level <span class="required">*</span></label>
                            <select id="difficulty" name="difficulty" required>
                                <option value="">Select Difficulty</option>
                                <option value="Easy" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Easy') ? 'selected' : ''; ?>>🟢 Easy</option>
                                <option value="Medium" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Medium') ? 'selected' : ''; ?>>🟡 Medium</option>
                                <option value="Hard" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] == 'Hard') ? 'selected' : ''; ?>>🔴 Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rating">Rating (1.0 - 5.0) <span class="required">*</span></label>
                        <input type="number" id="rating" name="rating" min="1.0" max="5.0" step="0.1" required 
                               value="<?php echo isset($_POST['rating']) ? floatval($_POST['rating']) : '4.5'; ?>">
                    </div>
                </div>

                <!-- Dietary Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-leaf"></i>
                        Dietary Information
                    </h2>
                    
                    <div class="form-group">
                        <label for="dietary_restrictions">Dietary Restrictions</label>
                        <input type="text" id="dietary_restrictions" name="dietary_restrictions" 
                               value="<?php echo isset($_POST['dietary_restrictions']) ? htmlspecialchars($_POST['dietary_restrictions']) : ''; ?>"
                               placeholder="e.g., Vegetarian, Vegan, Gluten-Free..." maxlength="255">
                        <div class="dietary-tags">
                            <span class="dietary-tag" onclick="selectDietary('Vegetarian')">Vegetarian</span>
                            <span class="dietary-tag" onclick="selectDietary('Vegan')">Vegan</span>
                            <span class="dietary-tag" onclick="selectDietary('Gluten-Free')">Gluten-Free</span>
                            <span class="dietary-tag" onclick="selectDietary('Dairy-Free')">Dairy-Free</span>
                            <span class="dietary-tag" onclick="selectDietary('Keto')">Keto</span>
                            <span class="dietary-tag" onclick="selectDietary('Paleo')">Paleo</span>
                            <span class="dietary-tag" onclick="selectDietary('Low-Carb')">Low-Carb</span>
                            <span class="dietary-tag" onclick="selectDietary('High-Protein')">High-Protein</span>
                        </div>
                    </div>
                </div>

                <!-- Nutrition Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-apple-alt"></i>
                        Nutrition Information (Per Serving)
                    </h2>
                    
                    <div class="nutrition-grid">
                        <div class="nutrition-item">
                            <i class="fas fa-fire nutrition-icon"></i>
                            <label for="calories_per_serving">Calories</label>
                            <input type="number" id="calories_per_serving" name="calories_per_serving" 
                                   min="0" max="5000" value="<?php echo isset($_POST['calories_per_serving']) ? (int)$_POST['calories_per_serving'] : ''; ?>"
                                   placeholder="0">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-dumbbell nutrition-icon"></i>
                            <label for="protein_per_serving">Protein (g)</label>
                            <input type="number" id="protein_per_serving" name="protein_per_serving" 
                                   min="0" max="300" step="0.1" value="<?php echo isset($_POST['protein_per_serving']) ? floatval($_POST['protein_per_serving']) : ''; ?>"
                                   placeholder="0">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-bread-slice nutrition-icon"></i>
                            <label for="carbs_per_serving">Carbs (g)</label>
                            <input type="number" id="carbs_per_serving" name="carbs_per_serving" 
                                   min="0" max="500" step="0.1" value="<?php echo isset($_POST['carbs_per_serving']) ? floatval($_POST['carbs_per_serving']) : ''; ?>"
                                   placeholder="0">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-cheese nutrition-icon"></i>
                            <label for="fat_per_serving">Fat (g)</label>
                            <input type="number" id="fat_per_serving" name="fat_per_serving" 
                                   min="0" max="200" step="0.1" value="<?php echo isset($_POST['fat_per_serving']) ? floatval($_POST['fat_per_serving']) : ''; ?>"
                                   placeholder="0">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-seedling nutrition-icon"></i>
                            <label for="fiber_per_serving">Fiber (g)</label>
                            <input type="number" id="fiber_per_serving" name="fiber_per_serving" 
                                   min="0" max="100" step="0.1" value="<?php echo isset($_POST['fiber_per_serving']) ? floatval($_POST['fiber_per_serving']) : ''; ?>"
                                   placeholder="0">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-candy-cane nutrition-icon"></i>
                            <label for="sugar_per_serving">Sugar (g)</label>
                            <input type="number" id="sugar_per_serving" name="sugar_per_serving" 
                                   min="0" max="200" step="0.1" value="<?php echo isset($_POST['sugar_per_serving']) ? floatval($_POST['sugar_per_serving']) : ''; ?>"
                                   placeholder="0">
                        </div>

                        <div class="nutrition-item">
                            <i class="fas fa-tint nutrition-icon"></i>
                            <label for="sodium_per_serving">Sodium (mg)</label>
                            <input type="number" id="sodium_per_serving" name="sodium_per_serving" 
                                   min="0" max="10000" step="0.1" value="<?php echo isset($_POST['sodium_per_serving']) ? floatval($_POST['sodium_per_serving']) : ''; ?>"
                                   placeholder="0">
                        </div>
                    </div>
                </div>

                <!-- Recipe Content -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i>
                        Recipe Content
                    </h2>
                    
                    <div class="form-group">
                        <label for="ingredients">Ingredients <span class="required">*</span></label>
                        <textarea id="ingredients" name="ingredients" rows="8" required 
                                  placeholder="List all ingredients with quantities (one per line)..."><?php echo isset($_POST['ingredients']) ? htmlspecialchars($_POST['ingredients']) : ''; ?></textarea>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> List each ingredient on a separate line with quantities (e.g., "2 cups flour")
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="instructions">Instructions <span class="required">*</span></label>
                        <textarea id="instructions" name="instructions" rows="12" required 
                                  placeholder="Step-by-step cooking instructions..."><?php echo isset($_POST['instructions']) ? htmlspecialchars($_POST['instructions']) : ''; ?></textarea>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> Write clear, step-by-step instructions for preparing this recipe
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="recipe_notes">Recipe Notes</label>
                        <textarea id="recipe_notes" name="recipe_notes" rows="4" 
                                  placeholder="Any additional tips, variations, or notes..."><?php echo isset($_POST['recipe_notes']) ? htmlspecialchars($_POST['recipe_notes']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" 
                               value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>"
                               placeholder="e.g., quick, healthy, comfort food (comma-separated)">
                        <div class="help-text">
                            <i class="fas fa-tags"></i> Separate tags with commas to help users find your recipe
                        </div>
                    </div>
                </div>

                <!-- Media Upload Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-camera"></i>
                        Media Upload
                    </h2>
                    
                    <div class="form-group">
                        <label for="image">Recipe Image <span class="required">*</span></label>
                        <input type="file" id="image" name="image" accept="image/*" required>
                        <div class="help-text">Supported: JPG, PNG, GIF (max 5MB)</div>
                    </div>

                    <!-- Enhanced YouTube Video Section -->
                    <div class="youtube-section">
                        <div class="youtube-header">
                            <i class="fab fa-youtube youtube-icon"></i>
                            <div>
                                <div class="youtube-title">Add YouTube Video (Optional)</div>
                                <div class="youtube-subtitle">Enhance your recipe with a cooking video</div>
                            </div>
                        </div>

                        <div class="youtube-controls">
                            <input type="url" 
                                   id="video_url" 
                                   name="video_url" 
                                   class="youtube-input"
                                   value="<?php echo isset($_POST['video_url']) ? htmlspecialchars($_POST['video_url']) : ''; ?>"
                                   placeholder="Paste YouTube URL here... (e.g., https://youtube.com/watch?v=...)">
                            
                            <button type="button" class="youtube-browse-btn" onclick="openYouTubeSearch()">
                                <i class="fas fa-search"></i>
                                Browse YouTube
                            </button>
                        </div>

                        <div class="youtube-preview" id="youtubePreview">
                            <div class="preview-title" id="previewTitle"></div>
                            <div class="preview-url" id="previewUrl"></div>
                        </div>

                        <div class="youtube-tips">
                            <h4><i class="fas fa-lightbulb"></i> Tips for Adding YouTube Videos:</h4>
                            <ul>
                                <li>Copy the full YouTube URL from your browser</li>
                                <li>Both youtube.com/watch and youtu.be/ formats work</li>
                                <li>Click "Browse YouTube" to search for cooking videos</li>
                                <li>Videos will be automatically formatted for optimal display</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus-circle"></i>
                    Add Recipe
                </button>
            </form>
        </div>
    </div>

    <script>
        // Auto-calculate total time
        document.getElementById('prep_time').addEventListener('input', calculateTotalTime);
        document.getElementById('cook_time').addEventListener('input', calculateTotalTime);

        function calculateTotalTime() {
            const prepTime = document.getElementById('prep_time').value;
            const cookTime = document.getElementById('cook_time').value;
            
            if (prepTime && cookTime) {
                // Simple time calculation (assumes minutes)
                const prepMinutes = parseInt(prepTime.match(/\d+/)) || 0;
                const cookMinutes = parseInt(cookTime.match(/\d+/)) || 0;
                const totalMinutes = prepMinutes + cookMinutes;
                
                document.getElementById('total_time').value = totalMinutes + ' minutes';
            }
        }

        // Cuisine selection
        function selectCuisine(cuisine) {
            document.getElementById('cuisine_type').value = cuisine;
        }

        // Dietary restrictions selection
        function selectDietary(dietary) {
            const field = document.getElementById('dietary_restrictions');
            const current = field.value;
            
            if (current) {
                if (!current.includes(dietary)) {
                    field.value = current + ', ' + dietary;
                }
            } else {
                field.value = dietary;
            }
        }

        // YouTube functionality
        function openYouTubeSearch() {
            const recipeTitle = document.getElementById('title').value || 'recipe cooking';
            const searchQuery = encodeURIComponent(recipeTitle + ' recipe cooking tutorial');
            const youtubeSearchUrl = `https://www.youtube.com/results?search_query=${searchQuery}`;
            
            // Open YouTube in a new tab
            window.open(youtubeSearchUrl, '_blank');
            
            // Show helpful message
            alert('🎥 YouTube will open in a new tab.\n\n📝 Instructions:\n1. Find your desired cooking video\n2. Copy the video URL from browser\n3. Return to this page and paste the URL\n\nTip: Look for videos that match your recipe!');
        }

        // YouTube URL validation and preview
        document.getElementById('video_url').addEventListener('input', function() {
            const url = this.value.trim();
            const preview = document.getElementById('youtubePreview');
            
            if (url && isValidYouTubeUrl(url)) {
                showYouTubePreview(url);
                preview.classList.add('show');
                
                // Auto-format the URL
                this.value = formatYouTubeUrl(url);
            } else {
                preview.classList.remove('show');
            }
        });

        function isValidYouTubeUrl(url) {
            const patterns = [
                /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/,
                /youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]+)/
            ];
            
            return patterns.some(pattern => pattern.test(url));
        }

        function formatYouTubeUrl(url) {
            // Convert to standard youtu.be format
            const videoIdMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
            if (videoIdMatch) {
                return `https://youtu.be/${videoIdMatch[1]}`;
            }
            return url;
        }

        function showYouTubePreview(url) {
            const videoId = extractYouTubeId(url);
            if (videoId) {
                document.getElementById('previewTitle').textContent = '✅ Valid YouTube Video Detected';
                document.getElementById('previewUrl').textContent = `Video ID: ${videoId}`;
            }
        }

        function extractYouTubeId(url) {
            const match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
            return match ? match[1] : null;
        }

        // Form validation
        document.getElementById('recipeForm').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = ['title', 'category', 'prep_time', 'difficulty', 'rating', 'description', 'ingredients', 'instructions'];
            
            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#e74c3c';
                } else {
                    field.style.borderColor = '#e1e5e9';
                }
            });
            
            // Check if image is uploaded
            const imageField = document.getElementById('image');
            if (!imageField.files.length) {
                isValid = false;
                alert('Please upload a recipe image.');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
            }
        });

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.form-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    section.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
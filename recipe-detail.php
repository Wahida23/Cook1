<?php
// Enhanced Recipe Details Page with Favorites
session_start();
require_once 'config.php';
require_once 'auth_functions.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "cookistry_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
$isLoggedIn = isUserLoggedIn();
$currentUser = null;
if ($isLoggedIn) {
    $currentUser = getCurrentUser();
}

// Get recipe ID from URL and sanitize
$recipe_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($recipe_id <= 0) {
    die("Invalid recipe ID");
}

// Fetch recipe details with enhanced fields
$sql = "SELECT * FROM recipes WHERE id = ? AND (status = 'published' OR status IS NULL OR status = '')";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Recipe not found");
}

$recipe = $result->fetch_assoc();
$stmt->close();

// Check if recipe is favorited by current user
$isFavorited = false;
if ($isLoggedIn) {
    $favStmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND recipe_id = ?");
    if ($favStmt) {
        $favStmt->bind_param("ii", $currentUser['id'], $recipe_id);
        $favStmt->execute();
        $favResult = $favStmt->get_result();
        $isFavorited = $favResult->num_rows > 0;
        $favStmt->close();
    }
}

// Handle AJAX favorites request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
    header('Content-Type: application/json');
    
    if (!$isLoggedIn) {
        echo json_encode(['success' => false, 'message' => 'Please login to save favorites']);
        exit();
    }
    
    $user_id = $currentUser['id'];
    $recipe_id_post = intval($_POST['recipe_id']);
    
    if ($recipe_id_post !== $recipe_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid recipe ID']);
        exit();
    }
    
    try {
        // Check if already favorited
        $checkStmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND recipe_id = ?");
        $checkStmt->bind_param("ii", $user_id, $recipe_id_post);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Remove from favorites
            $deleteStmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?");
            $deleteStmt->bind_param("ii", $user_id, $recipe_id_post);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            echo json_encode(['success' => true, 'favorited' => false, 'message' => 'Recipe removed from favorites']);
        } else {
            // Add to favorites
            $insertStmt = $conn->prepare("INSERT INTO favorites (user_id, recipe_id, created_at) VALUES (?, ?, NOW())");
            $insertStmt->bind_param("ii", $user_id, $recipe_id_post);
            $insertStmt->execute();
            $insertStmt->close();
            
            echo json_encode(['success' => true, 'favorited' => true, 'message' => 'Recipe added to favorites']);
        }
        
        $checkStmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit();
}

// Get related recipes from same category or cuisine
$related_sql = "SELECT * FROM recipes WHERE (category = ? OR cuisine_type = ?) AND id != ? AND (status = 'published' OR status IS NULL OR status = '') LIMIT 4";
$related_stmt = $conn->prepare($related_sql);
if (!$related_stmt) {
    die("Prepare failed: " . $conn->error);
}
$related_stmt->bind_param("ssi", $recipe['category'], $recipe['cuisine_type'], $recipe_id);
$related_stmt->execute();
$related_recipes = $related_stmt->get_result();
$related_stmt->close();

// Update view count
$update_views = $conn->prepare("UPDATE recipes SET views = views + 1 WHERE id = ?");
if ($update_views) {
    $update_views->bind_param("i", $recipe_id);
    $update_views->execute();
    $update_views->close();
}

// Function to get YouTube Video ID
function getYouTubeVideoId($url) {
    if (empty($url)) return null;
    
    $patterns = [
        '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/)([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]+)/',
        '/m\.youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

$youtube_id = getYouTubeVideoId($recipe['video_url'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['title']); ?> - Cookistry</title>
    
    <!-- Favicon -->
    <link rel="icon" href="images/logo.png" type="image/png">
    
    <!-- Google Fonts + Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css" />
    
    <!-- Meta tags for social sharing -->
    <meta property="og:title" content="<?php echo htmlspecialchars($recipe['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr($recipe['description'], 0, 200)); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($recipe['image'] ?? 'images/default-recipe.jpg'); ?>">
    <meta property="og:type" content="article">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background: rgb(235, 242, 247);
            overflow-x: hidden;
        }

        /* Fix Navigation - Make it visible */
        header {
            background: white;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #e2e8f0;
        }

        .navbar {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0.8rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            height: 60px !important;
            width: 60px !important;
        }

        nav {
            margin-left: auto;
            margin-right: 2rem;
        }

        nav ul {
            display: flex;
            list-style: none;
            align-items: center;
            gap: 2rem;
        }

        nav a {
            text-decoration: none;
            color: #4a5568 !important;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        nav a:hover, nav a.active {
            color: #4ca1af !important;
        }

        .dropdown {
            position: relative;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-radius: 8px;
            padding: 0.5rem 0;
            z-index: 1000;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-content a {
            display: block;
            padding: 0.75rem 1rem;
            color: #4a5568 !important;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .dropdown-content a:hover {
            background: #f7fafc;
            color: #4ca1af !important;
        }

        /* User dropdown styles - same as homepage */
        .user-dropdown .dropdown-content {
            min-width: 200px;
            right: 0;
            left: auto;
        }

        .user-menu li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .user-menu li:last-child {
            border-bottom: none;
        }

        .user-menu .divider {
            height: 1px;
            background: rgba(255,255,255,0.2);
            margin: 0.5rem 0;
            border: none;
            padding: 0;
        }

        .user-menu a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
        }

        .user-menu-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #4a5568;
            text-decoration: none;
        }

        .user-menu a:hover {
            background: rgba(76, 161, 175, 0.1);
            padding-left: 1.2rem;
        }

        /* Rest of the existing styles remain the same... */
        
        /* Compact Hero Section */
        .recipe-hero-compact {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            padding: 3rem 0 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .recipe-hero-compact::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="50" height="50" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
            z-index: 1;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 2;
        }

        .hero-breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .hero-breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .hero-breadcrumb a:hover {
            opacity: 0.8;
        }

        .hero-content-compact {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .hero-text {
            max-width: 600px;
        }

        .recipe-title-compact {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .recipe-description {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .recipe-meta-badges {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .meta-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .hero-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Main Content - Clean Layout */
        .recipe-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .recipe-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        /* Content Cards - Smaller and Cleaner */
        .content-section {
            background: white;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .section-header {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .section-content {
            padding: 1.5rem;
        }

        /* Image Section - Compact */
        .recipe-image-section {
            position: relative;
            height: 300px;
            overflow: hidden;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .recipe-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-overlay {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .overlay-badge {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.8rem;
            backdrop-filter: blur(5px);
        }

        /* Video Button */
        .video-trigger {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(220, 38, 38, 0.9);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.4);
        }

        .video-trigger:hover {
            transform: translate(-50%, -50%) scale(1.1);
            box-shadow: 0 8px 30px rgba(220, 38, 38, 0.6);
        }

        /* Ingredients - Compact List */
        .ingredients-list {
            list-style: none;
        }

        .ingredient-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }

        .ingredient-check {
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            flex-shrink: 0;
        }

        .ingredient-check.checked {
            background: #4ca1af;
            border-color: #4ca1af;
        }

        .ingredient-check.checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .ingredient-text.checked {
            text-decoration: line-through;
            opacity: 0.6;
        }

        /* Instructions - Cleaner Steps */
        .instructions-list {
            counter-reset: step-counter;
            list-style: none;
        }

        .instruction-item {
            counter-increment: step-counter;
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .instruction-number {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            flex-shrink: 0;
            margin-top: 0.25rem;
        }

        .instruction-number::before {
            content: counter(step-counter);
        }

        .instruction-text {
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Sidebar - Compact */
        .sidebar-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #f1f5f9;
        }

        .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        /* Nutrition Grid - Compact */
        .nutrition-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .nutrition-item {
            background: #f8fafc;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .nutrition-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #4ca1af;
            margin-bottom: 0.25rem;
        }

        .nutrition-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        .calories-main {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 1rem;
        }

        .calories-main .nutrition-value {
            color: white;
            font-size: 1.5rem;
        }

        .calories-main .nutrition-label {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Tags - Compact */
        .tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tag {
            background: #f1f5f9;
            color: #475569;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Action Buttons - Enhanced with Favorites */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .action-btn {
            flex: 1;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 161, 175, 0.3);
        }

        .action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Favorite button states */
        .favorite-btn {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            transition: all 0.3s ease;
        }

        .favorite-btn.favorited {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .favorite-btn:hover {
            transform: translateY(-2px);
        }

        .favorite-btn.favorited:hover {
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .favorite-btn:not(.favorited):hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }

        /* Login prompt for guests */
        .login-prompt {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-prompt a {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }

        /* Notification styles */
        .notification {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-left: 4px solid #10b981;
            z-index: 2000;
            min-width: 300px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            border-left-color: #ef4444;
        }

        .notification.warning {
            border-left-color: #f59e0b;
        }

        /* Rest of existing styles... Modal, Related recipes, etc. */
        
        /* Modal for Video */
        .video-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            padding: 2rem;
        }

        .video-modal-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            top: 50%;
            transform: translateY(-50%);
        }

        .video-close {
            position: absolute;
            top: -3rem;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }

        .video-iframe {
            width: 100%;
            height: 450px;
            border: none;
            border-radius: 12px;
        }

        /* Related Recipes - Compact Grid */
        .related-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .related-title {
            text-align: center;
            margin-bottom: 2rem;
        }

        .related-title h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .related-title p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .related-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .related-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .related-content {
            padding: 1rem;
        }

        .related-title-text {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
            line-height: 1.3;
        }

        .related-meta {
            display: flex;
            gap: 1rem;
            color: #64748b;
            font-size: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .btn-related {
            display: inline-block;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .btn-related:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 161, 175, 0.3);
            color: white;
            text-decoration: none;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .recipe-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .hero-content-compact {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }

            .hero-stats {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            nav ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }

            .recipe-title-compact {
                font-size: 2rem;
            }

            .recipe-container {
                padding: 1rem;
            }

            .hero-stats {
                grid-template-columns: 1fr 1fr;
            }

            .nutrition-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .related-grid {
                grid-template-columns: 1fr;
            }

            .video-modal {
                padding: 1rem;
            }

            .video-iframe {
                height: 250px;
            }

            .notification {
                right: 1rem;
                left: 1rem;
                min-width: auto;
            }
        }

        /* Print Styles */
        @media print {
            header, .video-trigger, .action-buttons, .related-section {
                display: none !important;
            }
            
            .recipe-layout {
                grid-template-columns: 1fr !important;
            }
        }

        /* Smooth animations */
        .content-section {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
        }

        .content-section:nth-child(1) { animation-delay: 0.1s; }
        .content-section:nth-child(2) { animation-delay: 0.2s; }
        .content-section:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Scroll to top */
        .scroll-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(76, 161, 175, 0.3);
        }

        .scroll-to-top.show {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.4);
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header>
        <div class="navbar">
            <img src="images/logo.png" alt="Cookistry Logo" class="logo" />
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li class="dropdown">
                        <a href="#">Categories <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-content">
                            <li><a href="appetizer.php">Appetizer</a></li>
                            <li><a href="breakfast.php">Breakfast</a></li>
                            <li><a href="lunch.php">Lunch</a></li>
                            <li><a href="dinner.php">Dinner</a></li>
                            <li><a href="dessert.php">Dessert</a></li>
                            <li><a href="bread-bakes.php">Bread & Bakes</a></li>
                            <li><a href="salads.php">Salads</a></li>
                            <li><a href="healthy.php">Healthy Food</a></li>
                            <li><a href="beverages.php">Beverages</a></li>
                            <li><a href="snacks.php">Snacks</a></li>
                        </ul>
                    </li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="about.php">About</a></li>
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- Logged in user menu -->
                        <li class="dropdown user-dropdown">
                            <a href="#" class="user-menu-toggle">
                                <i class="fas fa-user-circle"></i> 
                                <?php echo htmlspecialchars($currentUser['full_name'] ?: $currentUser['username']); ?>
                                <i class="fas fa-chevron-down"></i>
                            </a>
                            <ul class="dropdown-content user-menu">
                                <li><a href="user-dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                <li><a href="profile.php"><i class="fas fa-user-edit"></i> My Profile</a></li>
                                <li><a href="my-recipes.php"><i class="fas fa-utensils"></i> My Recipes</a></li>
                                <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
                                <li class="divider"></li>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Guest user menu -->
                        <li><a href="login.php">Login</a></li>
                        <li><a href="signup.php">Signup</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Rest of the HTML remains the same until the action buttons section -->
    
    <!-- Compact Hero Section -->
    <section class="recipe-hero-compact">
        <div class="hero-container">
            <div class="hero-breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <a href="<?php echo strtolower($recipe['category']); ?>.php"><?php echo ucfirst($recipe['category']); ?></a>
                <span>/</span>
                <span><?php echo htmlspecialchars($recipe['title']); ?></span>
            </div>
            
            <div class="hero-content-compact">
                <div class="hero-text">
                    <h1 class="recipe-title-compact"><?php echo htmlspecialchars($recipe['title']); ?></h1>
                    <p class="recipe-description"><?php echo htmlspecialchars($recipe['description']); ?></p>
                    
                    <div class="recipe-meta-badges">
                        <?php if (!empty($recipe['cuisine_type'])): ?>
                            <div class="meta-badge">
                                <i class="fas fa-globe"></i>
                                <?php echo htmlspecialchars($recipe['cuisine_type']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($recipe['dietary_restrictions'])): ?>
                            <div class="meta-badge">
                                <i class="fas fa-leaf"></i>
                                <?php echo htmlspecialchars($recipe['dietary_restrictions']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="meta-badge">
                            <i class="fas fa-signal"></i>
                            <?php echo ucfirst($recipe['difficulty'] ?? 'Medium'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="hero-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo htmlspecialchars($recipe['prep_time'] ?? '30'); ?></div>
                        <div class="stat-label">Prep Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo htmlspecialchars($recipe['cook_time'] ?? '20'); ?></div>
                        <div class="stat-label">Cook Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo htmlspecialchars($recipe['servings'] ?? '4'); ?></div>
                        <div class="stat-label">Servings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo !empty($recipe['rating']) ? number_format($recipe['rating'], 1) : '4.5'; ?></div>
                        <div class="stat-label">Rating</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Recipe Content -->
    <div class="recipe-container">
        <div class="recipe-layout">
            <!-- Main Content -->
            <div class="recipe-main">
                <!-- Recipe Image Section -->
                <div class="recipe-image-section">
                    <?php 
                    $imagePath = !empty($recipe['image']) ? htmlspecialchars($recipe['image']) : 'images/default-recipe.jpg';
                    if (!file_exists($imagePath) && !filter_var($imagePath, FILTER_VALIDATE_URL)) {
                        $imagePath = 'images/default-recipe.jpg';
                    }
                    ?>
                    <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>" class="recipe-image">
                    
                    <div class="image-overlay">
                        <div class="overlay-badge">
                            <i class="fas fa-clock"></i> <?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?>
                        </div>
                        <div class="overlay-badge">
                            <i class="fas fa-users"></i> <?php echo htmlspecialchars($recipe['servings'] ?? '4'); ?>
                        </div>
                    </div>
                    
                    <?php if ($youtube_id): ?>
                        <button class="video-trigger" onclick="openVideoModal()">
                            <i class="fas fa-play"></i>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Ingredients Section -->
                <div class="content-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <h2 class="section-title">Ingredients</h2>
                    </div>
                    <div class="section-content">
                        <ul class="ingredients-list">
                            <?php
                            $ingredients = explode("\n", trim($recipe['ingredients'] ?? ''));
                            foreach ($ingredients as $ingredient) {
                                if (!empty(trim($ingredient))) {
                                    echo '<li class="ingredient-item">';
                                    echo '<div class="ingredient-check" onclick="toggleIngredient(this)"></div>';
                                    echo '<span class="ingredient-text">' . htmlspecialchars(trim($ingredient)) . '</span>';
                                    echo '</li>';
                                }
                            }
                            ?>
                        </ul>
                    </div>
                </div>

                <!-- Instructions Section -->
                <div class="content-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h2 class="section-title">Instructions</h2>
                    </div>
                    <div class="section-content">
                        <ol class="instructions-list">
                            <?php
                            $instructions = explode("\n", trim($recipe['instructions'] ?? ''));
                            foreach ($instructions as $instruction) {
                                if (!empty(trim($instruction))) {
                                    echo '<li class="instruction-item">';
                                    echo '<div class="instruction-number"></div>';
                                    echo '<div class="instruction-text">' . htmlspecialchars(trim($instruction)) . '</div>';
                                    echo '</li>';
                                }
                            }
                            ?>
                        </ol>
                    </div>
                </div>

                <!-- Recipe Notes (if available) -->
                <?php if (!empty($recipe['recipe_notes'])): ?>
                <div class="content-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                        <h2 class="section-title">Chef's Notes</h2>
                    </div>
                    <div class="section-content">
                        <p style="color: #64748b; line-height: 1.6;"><?php echo htmlspecialchars($recipe['recipe_notes']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="recipe-sidebar">
                <!-- Nutrition Card -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">
                        <div class="sidebar-icon">
                            <i class="fas fa-apple-alt"></i>
                        </div>
                        Nutrition Facts
                    </h3>
                    <div class="nutrition-grid">
                        <div class="nutrition-item calories-main">
                            <div class="nutrition-value"><?php echo htmlspecialchars($recipe['calories_per_serving'] ?? 250); ?></div>
                            <div class="nutrition-label">Calories</div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-value"><?php echo htmlspecialchars($recipe['protein_per_serving'] ?? 12); ?>g</div>
                            <div class="nutrition-label">Protein</div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-value"><?php echo htmlspecialchars($recipe['carbs_per_serving'] ?? 30); ?>g</div>
                            <div class="nutrition-label">Carbs</div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-value"><?php echo htmlspecialchars($recipe['fat_per_serving'] ?? 8); ?>g</div>
                            <div class="nutrition-label">Fat</div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-value"><?php echo htmlspecialchars($recipe['fiber_per_serving'] ?? 3); ?>g</div>
                            <div class="nutrition-label">Fiber</div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-value"><?php echo htmlspecialchars($recipe['sodium_per_serving'] ?? 400); ?>mg</div>
                            <div class="nutrition-label">Sodium</div>
                        </div>
                    </div>
                </div>

                <!-- Recipe Info Card -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">
                        <div class="sidebar-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        Recipe Actions
                    </h3>
                    <div class="tags-list">
                        <?php
                        $tags = explode(',', trim($recipe['tags'] ?? 'delicious,homemade'));
                        foreach ($tags as $tag) {
                            if (!empty(trim($tag))) {
                                echo '<span class="tag">' . htmlspecialchars(trim($tag)) . '</span>';
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="action-btn" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="action-btn" onclick="shareRecipe()">
                            <i class="fas fa-share"></i> Share
                        </button>
                        
                        <?php if ($isLoggedIn): ?>
                            <button class="action-btn favorite-btn <?php echo $isFavorited ? 'favorited' : ''; ?>" 
                                    id="favoriteBtn" 
                                    onclick="toggleFavorite(<?php echo $recipe_id; ?>)">
                                <i class="fas fa-heart"></i> 
                                <span id="favoriteText"><?php echo $isFavorited ? 'Saved' : 'Save'; ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$isLoggedIn): ?>
                        <div class="login-prompt">
                            <i class="fas fa-info-circle"></i>
                            <a href="login.php">Login</a> or <a href="signup.php">Sign up</a> to save favorites
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Related Recipes Section -->
        <?php if ($related_recipes && $related_recipes->num_rows > 0): ?>
        <div class="related-section">
            <div class="related-title">
                <h2>You Might Also Like</h2>
                <p>Similar recipes you may enjoy</p>
            </div>
            
            <div class="related-grid">
                <?php while ($related = $related_recipes->fetch_assoc()): ?>
                    <div class="related-card">
                        <?php 
                        $relatedImagePath = !empty($related['image']) ? htmlspecialchars($related['image']) : 'images/default-recipe.jpg';
                        if (!file_exists($relatedImagePath) && !filter_var($relatedImagePath, FILTER_VALIDATE_URL)) {
                            $relatedImagePath = 'images/default-recipe.jpg';
                        }
                        ?>
                        <img src="<?php echo $relatedImagePath; ?>" alt="<?php echo htmlspecialchars($related['title']); ?>" class="related-image">
                        
                        <div class="related-content">
                            <h3 class="related-title-text"><?php echo htmlspecialchars($related['title']); ?></h3>
                            <div class="related-meta">
                                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($related['prep_time'] ?? '30 min'); ?></span>
                                <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($related['difficulty'] ?? 'Medium'); ?></span>
                            </div>
                            <a href="recipe-detail.php?id=<?php echo $related['id']; ?>" class="btn-related">
                                View Recipe
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Video Modal -->
    <?php if ($youtube_id): ?>
    <div class="video-modal" id="videoModal">
        <div class="video-modal-content">
            <button class="video-close" onclick="closeVideoModal()">×</button>
            <iframe class="video-iframe" 
                    src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtube_id); ?>?rel=0&modestbranding=1&fs=1&cc_load_policy=1&iv_load_policy=3&showinfo=0&controls=1" 
                    title="<?php echo htmlspecialchars($recipe['title']); ?> - Video Tutorial"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
            </iframe>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rest of your existing footer code... -->
    <footer class="enhanced-footer">
        <!-- Footer content remains the same -->
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" id="scrollToTop">
        <i class="fas fa-chevron-up"></i>
    </button>

    <script>
        // Enhanced JavaScript with Favorites functionality
        
        // Favorites toggle function
        function toggleFavorite(recipeId) {
            const btn = document.getElementById('favoriteBtn');
            const text = document.getElementById('favoriteText');
            const originalContent = btn.innerHTML;
            
            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';
            btn.disabled = true;
            
            // Send AJAX request
            fetch('recipe-detail.php?id=' + recipeId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_favorite&recipe_id=' + recipeId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button state
                    if (data.favorited) {
                        btn.classList.add('favorited');
                        btn.innerHTML = '<i class="fas fa-heart"></i> <span>Saved</span>';
                    } else {
                        btn.classList.remove('favorited');
                        btn.innerHTML = '<i class="fas fa-heart"></i> <span>Save</span>';
                    }
                    
                    // Show notification
                    showNotification(data.message, 'success');
                } else {
                    // Restore original state and show error
                    btn.innerHTML = originalContent;
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                // Restore original state and show error
                btn.innerHTML = originalContent;
                showNotification('Something went wrong. Please try again.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
            });
        }
        
        // Show notification function
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notif => notif.remove());
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, 3000);
        }

        // Rest of your existing JavaScript functions...
        
        // Video Modal Functions
        function openVideoModal() {
            document.getElementById('videoModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeVideoModal() {
            document.getElementById('videoModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('videoModal');
            if (event.target === modal) {
                closeVideoModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeVideoModal();
            }
        });

        // Ingredient Checkbox Toggle
        function toggleIngredient(checkbox) {
            checkbox.classList.toggle('checked');
            const text = checkbox.nextElementSibling;
            text.classList.toggle('checked');
        }

        // Share Recipe Function
        function shareRecipe() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($recipe['title']); ?>',
                    text: '<?php echo htmlspecialchars($recipe['description']); ?>',
                    url: window.location.href
                }).catch(() => {
                    copyToClipboard();
                });
            } else {
                copyToClipboard();
            }
        }
        
        function copyToClipboard() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                showNotification('Recipe link copied to clipboard!', 'success');
            }).catch(() => {
                showNotification('Failed to copy link. Please try again.', 'error');
            });
        }

        // Scroll to Top Functionality
        const scrollToTopBtn = document.getElementById('scrollToTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.classList.add('show');
            } else {
                scrollToTopBtn.classList.remove('show');
            }
        });

        scrollToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Smooth scroll for anchor links
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

        // Enhanced hover effects
        document.querySelectorAll('.content-section, .sidebar-card, .related-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
                this.style.boxShadow = '0 8px 30px rgba(0,0,0,0.12)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 20px rgba(0,0,0,0.06)';
            });
        });

        // Initialize page animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate content sections
            const sections = document.querySelectorAll('.content-section, .sidebar-card');
            sections.forEach((section, index) => {
                setTimeout(() => {
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>
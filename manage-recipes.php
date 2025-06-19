<?php
require_once 'config.php';

// Check if admin is logged in
checkAdminLogin();

$success_message = '';
$error_message = '';

// Handle delete request
if (isset($_POST['delete_recipe']) && isset($_POST['recipe_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $recipe_id = sanitizeInput($_POST['recipe_id']);
        
        try {
            // Get recipe info first to delete files
            $stmt = $pdo->prepare("SELECT image, video_path FROM recipes WHERE id = ?");
            $stmt->execute([$recipe_id]);
            $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recipe) {
                // Delete recipe from database
                $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ?");
                if ($stmt->execute([$recipe_id])) {
                    // Delete image file if exists
                    if ($recipe['image'] && file_exists($recipe['image'])) {
                        unlink($recipe['image']);
                    }
                    // Delete video file if exists
                    if ($recipe['video_path'] && file_exists($recipe['video_path'])) {
                        unlink($recipe['video_path']);
                    }
                    $success_message = "Recipe deleted successfully!";
                } else {
                    $error_message = "Error deleting recipe!";
                }
            }
        } catch(PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid request!";
    }
}

// Handle status change
if (isset($_POST['change_status']) && isset($_POST['recipe_id']) && isset($_POST['new_status'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $recipe_id = sanitizeInput($_POST['recipe_id']);
        $new_status = sanitizeInput($_POST['new_status']);
        
        if (in_array($new_status, ['published', 'draft', 'archived'])) {
            try {
                $stmt = $pdo->prepare("UPDATE recipes SET status = ? WHERE id = ?");
                if ($stmt->execute([$new_status, $recipe_id])) {
                    $success_message = "Recipe status updated successfully!";
                } else {
                    $error_message = "Error updating recipe status!";
                }
            } catch(PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get filters
$category_filter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query
$where_conditions = [];
$params = [];

if ($category_filter) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(title LIKE ? OR ingredients LIKE ? OR tags LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get recipes
try {
    $stmt = $pdo->prepare("
        SELECT id, title, category, difficulty, rating, prep_time, cook_time, 
               servings, image, status, created_at, updated_at 
        FROM recipes 
        $where_clause 
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $recipes = [];
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Recipes - Cookistry Admin</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-section img {
            width: 50px;
            height: 50px;
        }

        .logo-section h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.8rem;
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
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .page-title {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .filter-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .filter-btn, .add-recipe-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .filter-btn:hover, .add-recipe-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .clear-btn {
            background: #6c757d;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .clear-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
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

        .recipe-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .recipes-table {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Enhanced column widths */
        th:nth-child(1), td:nth-child(1) { width: 100px; } /* Image */
        th:nth-child(2), td:nth-child(2) { width: 280px; } /* Details */
        th:nth-child(3), td:nth-child(3) { width: 120px; } /* Category */
        th:nth-child(4), td:nth-child(4) { width: 150px; } /* Time */
        th:nth-child(5), td:nth-child(5) { width: 120px; } /* Difficulty */
        th:nth-child(6), td:nth-child(6) { width: 100px; } /* Rating */
        th:nth-child(7), td:nth-child(7) { width: 140px; } /* Status */
        th:nth-child(8), td:nth-child(8) { width: 180px; } /* Actions */

        th, td {
            padding: 1.5rem 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 700;
            color: #2c3e50;
            position: sticky;
            top: 0;
            font-size: 1.1rem;
        }

        tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .recipe-image {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .recipe-image:hover {
            transform: scale(1.1);
        }

        .recipe-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
            line-height: 1.4;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            display: block;
            width: 100%;
        }

        .recipe-meta {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.6;
            font-family: 'Poppins', sans-serif;
        }

        .recipe-meta div {
            margin-bottom: 4px;
            display: block;
            clear: both;
        }

        .time-info {
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .time-info > div {
            margin-bottom: 0.5rem;
            white-space: nowrap;
        }

        .time-info strong {
            font-weight: 700;
            color: #2c3e50;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-published {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
        }

        .status-draft {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 2px solid #ffc107;
        }

        .status-archived {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .difficulty-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .difficulty-easy {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
        }

        .difficulty-medium {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 2px solid #ffc107;
        }

        .difficulty-hard {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #ffc107;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .edit-btn, .delete-btn, .status-btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        .edit-btn {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }

        .delete-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.4);
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        .status-select {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-top: 0.75rem;
            width: 100%;
            background: white;
            transition: border-color 0.3s ease;
        }

        .status-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
            font-size: 2rem;
        }

        .empty-state p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .table-responsive {
                font-size: 0.8rem;
            }

            th, td {
                padding: 0.75rem 0.5rem;
            }

            .recipe-title {
                font-size: 1rem;
                line-height: 1.3;
            }
            
            .recipe-meta {
                font-size: 0.8rem;
            }
            
            .recipe-details-cell {
                min-width: 200px;
                max-width: 220px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .recipe-image {
                width: 60px;
                height: 60px;
            }

            /* Mobile table columns */
            th:nth-child(1), td:nth-child(1) { width: 80px; }
            th:nth-child(2), td:nth-child(2) { width: 220px; }
            th:nth-child(3), td:nth-child(3) { width: 100px; }
            th:nth-child(4), td:nth-child(4) { width: 120px; }
            th:nth-child(5), td:nth-child(5) { width: 100px; }
            th:nth-child(6), td:nth-child(6) { width: 80px; }
            th:nth-child(7), td:nth-child(7) { width: 120px; }
            th:nth-child(8), td:nth-child(8) { width: 140px; }
        }

        /* Loading animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .recipes-table {
            animation: fadeIn 0.6s ease;
        }

        .stat-card {
            animation: fadeIn 0.6s ease;
        }

        .page-header {
            animation: fadeIn 0.6s ease;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-section">
            <img src="images/logo.png" alt="Cookistry Logo">
            <h1>🍳 Manage Recipes</h1>
        </div>
        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
    </header>

    <div class="container">
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✅ <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            ❌ <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <h1 class="page-title">🍽️ Recipe Management Center</h1>
            
            <form method="GET" class="filters-form">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="search">🔍 Search Recipes</label>
                        <input type="text" id="search" name="search" placeholder="Search by title, ingredients, or tags..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                            <label for="category">📂 Category</label>
                            <select id="category" name="category">
                                <option value="">All Categories</option>
                                <option value="appetizer" <?php echo $category_filter === 'appetizer' ? 'selected' : ''; ?>>🥗 Appetizer</option>
                                <option value="breakfast" <?php echo $category_filter === 'breakfast' ? 'selected' : ''; ?>>🌅 Breakfast</option>
                                <option value="lunch" <?php echo $category_filter === 'lunch' ? 'selected' : ''; ?>>🥪 Lunch</option>
                                <option value="dinner" <?php echo $category_filter === 'dinner' ? 'selected' : ''; ?>>🍽️ Dinner</option>
                                <option value="dessert" <?php echo $category_filter === 'dessert' ? 'selected' : ''; ?>>🍰 Dessert</option>
                                <option value="bread-bakes" <?php echo $category_filter === 'bread-bakes' ? 'selected' : ''; ?>>🍞 Bread & Bakes</option>
                                <option value="salads" <?php echo $category_filter === 'salads' ? 'selected' : ''; ?>>🥗 Salads</option>
                                <option value="healthy" <?php echo $category_filter === 'healthy' ? 'selected' : ''; ?>>💪 Healthy</option>
                                <option value="beverages" <?php echo $category_filter === 'beverages' ? 'selected' : ''; ?>>🥤 Beverages</option>
                                <option value="snacks" <?php echo $category_filter === 'snacks' ? 'selected' : ''; ?>>🥜 Snacks</option>
                            </select>
                        </div>
                    
                    <div class="filter-group">
                        <label for="status">📊 Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>✅ Published</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>📝 Draft</option>
                            <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>📦 Archived</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="filter-btn">🔍 Apply Filters</button>
                    <a href="manage-recipes.php" class="clear-btn">🔄 Clear All</a>
                    <a href="add-recipe.php" class="add-recipe-btn">➕ Add New Recipe</a>
                </div>
            </form>
        </div>

        <?php
        // Calculate stats
        $total_recipes = count($recipes);
        $published_count = count(array_filter($recipes, function($r) { return isset($r['status']) && $r['status'] === 'published'; }));
        $draft_count = count(array_filter($recipes, function($r) { return isset($r['status']) && $r['status'] === 'draft'; }));
        $archived_count = count(array_filter($recipes, function($r) { return isset($r['status']) && $r['status'] === 'archived'; }));
        ?>
        
        <div class="recipe-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_recipes; ?></div>
                <div class="stat-label">📊 Total Recipes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $published_count; ?></div>
                <div class="stat-label">✅ Published</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $draft_count; ?></div>
                <div class="stat-label">📝 Drafts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $archived_count; ?></div>
                <div class="stat-label">📦 Archived</div>
            </div>
        </div>

        <div class="recipes-table">
            <?php if (empty($recipes)): ?>
            <div class="empty-state">
                <h3>🍽️ No Recipes Found</h3>
                <p>No recipes match your current filters. Try adjusting your search criteria or add your first recipe!</p>
                <a href="add-recipe.php" class="add-recipe-btn" style="margin-top: 2rem;">🍳 Add First Recipe</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>📸 Image</th>
                            <th>📋 Recipe Details</th>
                            <th>📂 Category</th>
                            <th>⏰ Time Info</th>
                            <th>📊 Difficulty</th>
                            <th>⭐ Rating</th>
                            <th>🔄 Status</th>
                            <th>⚙️ Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td>
                                <img src="<?php echo htmlspecialchars($recipe['image'] ?? 'images/default-recipe.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($recipe['title']); ?>" 
                                     class="recipe-image"
                                     onerror="this.src='images/default-recipe.jpg'">
                            </td>
                            <td class="recipe-details-cell">
                                <div class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></div>
                                <div class="recipe-meta">
                                    <div><strong>ID:</strong> <?php echo substr($recipe['id'], 0, 8); ?>...</div>
                                    <div><strong>Created:</strong> <?php echo date('M j, Y', strtotime($recipe['created_at'])); ?></div>
                                    <?php if (isset($recipe['updated_at']) && $recipe['updated_at']): ?>
                                    <div><strong>Updated:</strong> <?php echo date('M j, Y', strtotime($recipe['updated_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 1.1rem; font-weight: 600; color: #2c3e50;">
                                    <?php echo htmlspecialchars(ucfirst($recipe['category'] ?? 'N/A')); ?>
                                </span>
                            </td>
                            <td>
                                <div class="time-info">
                                    <div><strong>⏱️ Prep:</strong> <?php echo htmlspecialchars($recipe['prep_time'] ?? 'N/A'); ?></div>
                                    <?php if (isset($recipe['cook_time']) && $recipe['cook_time']): ?>
                                    <div><strong>🔥 Cook:</strong> <?php echo htmlspecialchars($recipe['cook_time']); ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($recipe['servings']) && $recipe['servings']): ?>
                                    <div><strong>👥 Serves:</strong> <?php echo htmlspecialchars($recipe['servings']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="difficulty-badge difficulty-<?php echo strtolower($recipe['difficulty'] ?? 'medium'); ?>">
                                    <?php echo htmlspecialchars($recipe['difficulty'] ?? 'Medium'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="rating">
                                    ⭐ <?php echo htmlspecialchars($recipe['rating'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td>
                                <?php $status = $recipe['status'] ?? 'published'; ?>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                                <form method="POST" style="margin-top: 0.75rem;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                    <select name="new_status" class="status-select" onchange="if(confirm('Change recipe status?')) this.form.submit();">
                                        <option value="">Change Status...</option>
                                        <option value="published" <?php echo $status === 'published' ? 'disabled' : ''; ?>>✅ Published</option>
                                        <option value="draft" <?php echo $status === 'draft' ? 'disabled' : ''; ?>>📝 Draft</option>
                                        <option value="archived" <?php echo $status === 'archived' ? 'disabled' : ''; ?>>📦 Archived</option>
                                    </select>
                                    <input type="hidden" name="change_status" value="1">
                                </form>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit-recipe.php?id=<?php echo $recipe['id']; ?>" class="edit-btn">✏️ Edit</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to delete this recipe?\n\nThis action cannot be undone and will permanently remove:\n• Recipe details\n• Associated images\n• All related data\n\nType DELETE to confirm.')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                        <button type="submit" name="delete_recipe" class="delete-btn">🗑️ Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Enhanced JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when filters change
            document.querySelectorAll('select[name="category"], select[name="status"]').forEach(function(select) {
                select.addEventListener('change', function() {
                    // Add loading state
                    const table = document.querySelector('.recipes-table');
                    table.style.opacity = '0.7';
                    
                    this.form.submit();
                });
            });

            // Enhanced search functionality
            const searchInput = document.getElementById('search');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    // Auto-submit after 1 second of no typing
                    if (this.value.length >= 3 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 1000);
            });

            // Enter key for immediate search
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    this.form.submit();
                }
            });

            // Enhanced delete confirmation
            document.querySelectorAll('form[onsubmit*="DELETE"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const recipeName = this.closest('tr').querySelector('.recipe-title').textContent;
                    
                    const confirmation = prompt(
                        `⚠️ DANGER: You are about to permanently delete "${recipeName}"\n\n` +
                        `This action CANNOT be undone and will remove:\n` +
                        `• Recipe details and content\n` +
                        `• Associated images and files\n` +
                        `• All related data\n\n` +
                        `Type "DELETE" (in capital letters) to confirm:`
                    );
                    
                    if (confirmation === 'DELETE') {
                        // Add loading state
                        const deleteBtn = this.querySelector('.delete-btn');
                        deleteBtn.innerHTML = '🔄 Deleting...';
                        deleteBtn.disabled = true;
                        
                        this.submit();
                    }
                });
            });

            // Image error handling with retry
            document.querySelectorAll('.recipe-image').forEach(img => {
                img.addEventListener('error', function() {
                    if (!this.hasAttribute('data-retry')) {
                        this.setAttribute('data-retry', 'true');
                        // Try loading the image again after a short delay
                        setTimeout(() => {
                            this.src = this.src + '?retry=' + Date.now();
                        }, 1000);
                    } else {
                        // If retry fails, use default image
                        this.src = 'images/default-recipe.jpg';
                    }
                });
            });

            // Add smooth animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe table rows for animation
            document.querySelectorAll('tbody tr').forEach(row => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                row.style.transition = 'all 0.6s ease';
                observer.observe(row);
            });
        });

        // Status change confirmation
        function confirmStatusChange(selectElement) {
            const newStatus = selectElement.value;
            const recipeName = selectElement.closest('tr').querySelector('.recipe-title').textContent;
            
            if (newStatus && confirm(`Change "${recipeName}" status to "${newStatus.toUpperCase()}"?`)) {
                selectElement.form.submit();
            } else {
                selectElement.value = ''; // Reset selection
            }
        }

        // Add status change confirmation to all status selects
        document.querySelectorAll('.status-select').forEach(select => {
            select.setAttribute('onchange', 'confirmStatusChange(this)');
        });
    </script>
</body>
</html>
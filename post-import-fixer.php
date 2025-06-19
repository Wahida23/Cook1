<?php
session_start();
require_once 'config.php';

$success_message = '';
$error_message = '';
$fix_results = [];

// Handle fix operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'scan_issues':
                $fix_results = scanDatabaseIssues();
                break;
            case 'fix_categories':
                $fix_results = fixCategoryIssues($_POST);
                break;
            case 'bulk_fix':
                $fix_results = bulkFixIssues($_POST);
                break;
        }
    }
}

function scanDatabaseIssues() {
    global $pdo;
    
    $issues = [
        'missing_category' => [],
        'invalid_category' => [],
        'missing_title' => [],
        'empty_ingredients' => [],
        'empty_instructions' => [],
        'summary' => []
    ];
    
    try {
        // Valid categories - Updated with all your categories
        $valid_categories = ['appetizer', 'breakfast', 'lunch', 'dinner', 'dessert', 'bread-bakes', 'salads', 'healthy', 'beverages', 'snacks'];
        
        // Find missing or invalid categories
        $stmt = $pdo->query("
            SELECT id, title, category, ingredients, instructions 
            FROM recipes 
            WHERE category IS NULL 
               OR category = '' 
               OR category NOT IN ('" . implode("','", $valid_categories) . "')
               OR title IS NULL 
               OR title = ''
               OR ingredients IS NULL 
               OR ingredients = ''
               OR instructions IS NULL 
               OR instructions = ''
            ORDER BY id
        ");
        
        while ($row = $stmt->fetch()) {
            // Missing category
            if (empty($row['category'])) {
                $issues['missing_category'][] = $row;
            }
            // Invalid category (like '4', numbers, etc.)
            elseif (!in_array($row['category'], $valid_categories)) {
                $issues['invalid_category'][] = $row;
            }
            
            // Missing title
            if (empty($row['title'])) {
                $issues['missing_title'][] = $row;
            }
            
            // Empty ingredients
            if (empty($row['ingredients'])) {
                $issues['empty_ingredients'][] = $row;
            }
            
            // Empty instructions
            if (empty($row['instructions'])) {
                $issues['empty_instructions'][] = $row;
            }
        }
        
        // Summary
        $issues['summary'] = [
            'total_recipes' => $pdo->query("SELECT COUNT(*) FROM recipes")->fetchColumn(),
            'missing_category_count' => count($issues['missing_category']),
            'invalid_category_count' => count($issues['invalid_category']),
            'missing_title_count' => count($issues['missing_title']),
            'empty_ingredients_count' => count($issues['empty_ingredients']),
            'empty_instructions_count' => count($issues['empty_instructions'])
        ];
        
        return ['success' => true, 'issues' => $issues];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function fixCategoryIssues($post_data) {
    global $pdo;
    
    $fixed = 0;
    $errors = [];
    
    try {
        $pdo->beginTransaction();
        
        // Fix individual recipes
        if (isset($post_data['recipe_fixes'])) {
            foreach ($post_data['recipe_fixes'] as $recipe_id => $new_category) {
                if (!empty($new_category) && $new_category !== 'skip') {
                    $stmt = $pdo->prepare("UPDATE recipes SET category = ? WHERE id = ?");
                    if ($stmt->execute([$new_category, $recipe_id])) {
                        $fixed++;
                    }
                }
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'fixed' => $fixed];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function bulkFixIssues($post_data) {
    global $pdo;
    
    $results = [
        'categories_fixed' => 0,
        'deleted_invalid' => 0,
        'auto_categorized' => 0,
        'errors' => []
    ];
    
    try {
        $pdo->beginTransaction();
        
        $action = $post_data['bulk_action'] ?? '';
        
        switch ($action) {
            case 'delete_invalid':
                // Delete recipes with invalid/missing categories and no essential data
                $stmt = $pdo->prepare("
                    DELETE FROM recipes 
                    WHERE (category IS NULL OR category = '' OR category NOT IN ('appetizer', 'breakfast', 'lunch', 'dinner', 'dessert', 'bread-bakes', 'salads', 'healthy', 'beverages', 'snacks'))
                    AND (ingredients IS NULL OR ingredients = '' OR instructions IS NULL OR instructions = '')
                ");
                $stmt->execute();
                $results['deleted_invalid'] = $stmt->rowCount();
                break;
                
            case 'auto_categorize':
                // Auto-categorize based on title keywords - Enhanced with all categories
                $category_keywords = [
                    'breakfast' => ['breakfast', 'morning', 'cereal', 'oatmeal', 'pancake', 'waffle', 'toast', 'egg', 'scrambled', 'omelet', 'muffin', 'granola', 'yogurt', 'smoothie bowl'],
                    'lunch' => ['lunch', 'sandwich', 'wrap', 'burger', 'panini', 'quesadilla', 'bowl', 'grain bowl'],
                    'dinner' => ['dinner', 'pasta', 'chicken', 'beef', 'pork', 'fish', 'salmon', 'steak', 'curry', 'stir-fry', 'casserole', 'risotto', 'lasagna', 'roast'],
                    'dessert' => ['dessert', 'cake', 'cookie', 'pie', 'chocolate', 'sweet', 'ice cream', 'pudding', 'brownie', 'tart', 'mousse', 'tiramisu', 'cheesecake'],
                    'appetizer' => ['appetizer', 'starter', 'dip', 'wings', 'nachos', 'bruschetta', 'hummus', 'deviled', 'stuffed', 'bites', 'skewers'],
                    'beverages' => ['drink', 'smoothie', 'juice', 'coffee', 'tea', 'lemonade', 'cocktail', 'shake', 'brew', 'water', 'milk', 'hot chocolate'],
                    'snacks' => ['snack', 'chips', 'nuts', 'trail mix', 'popcorn', 'crackers', 'bars', 'energy balls', 'roasted'],
                    'bread-bakes' => ['bread', 'bake', 'roll', 'biscuit', 'scone', 'croissant', 'loaf', 'muffin', 'focaccia', 'pizza dough', 'garlic bread'],
                    'healthy' => ['healthy', 'diet', 'low-fat', 'keto', 'vegan', 'vegetarian', 'quinoa', 'cauliflower', 'zucchini noodles', 'protein', 'clean eating'],
                    'salads' => ['salad', 'caesar', 'greek', 'waldorf', 'coleslaw', 'cucumber', 'spinach', 'caprese', 'quinoa salad', 'fruit salad']
                ];
                
                // Get recipes with missing/invalid categories
                $stmt = $pdo->query("
                    SELECT id, title 
                    FROM recipes 
                    WHERE category IS NULL 
                       OR category = '' 
                       OR category NOT IN ('appetizer', 'breakfast', 'lunch', 'dinner', 'dessert', 'bread-bakes', 'salads', 'healthy', 'beverages', 'snacks')
                ");
                
                $update_stmt = $pdo->prepare("UPDATE recipes SET category = ? WHERE id = ?");
                
                while ($row = $stmt->fetch()) {
                    $title_lower = strtolower($row['title']);
                    $categorized = false;
                    
                    foreach ($category_keywords as $category => $keywords) {
                        foreach ($keywords as $keyword) {
                            if (strpos($title_lower, $keyword) !== false) {
                                $update_stmt->execute([$category, $row['id']]);
                                $results['auto_categorized']++;
                                $categorized = true;
                                break 2;
                            }
                        }
                    }
                    
                    // Default to 'lunch' if no keywords matched
                    if (!$categorized) {
                        $update_stmt->execute(['lunch', $row['id']]);
                        $results['auto_categorized']++;
                    }
                }
                break;
                
            case 'set_default_category':
                $default_category = $post_data['default_category'] ?? 'lunch';
                $stmt = $pdo->prepare("
                    UPDATE recipes 
                    SET category = ? 
                    WHERE category IS NULL 
                       OR category = '' 
                       OR category NOT IN ('appetizer', 'breakfast', 'lunch', 'dinner', 'dessert', 'bread-bakes', 'salads', 'healthy', 'beverages', 'snacks')
                ");
                $stmt->execute([$default_category]);
                $results['categories_fixed'] = $stmt->rowCount();
                break;
        }
        
        $pdo->commit();
        return ['success' => true, 'results' => $results];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get initial scan if not POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fix_results = scanDatabaseIssues();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Import Database Fixer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0; }
        .issue-card { background: #fff; border-left: 4px solid #dc3545; padding: 1rem; margin: 0.5rem 0; border-radius: 8px; }
        .fixed-card { background: #d4edda; border-left: 4px solid #28a745; padding: 1rem; margin: 0.5rem 0; border-radius: 8px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0; }
        .stats-card { background: white; padding: 1.5rem; border-radius: 10px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .stats-number { font-size: 2rem; font-weight: bold; margin-bottom: 0.5rem; }
        .action-section { background: #f8f9fa; padding: 2rem; border-radius: 10px; margin: 1rem 0; }
        .recipe-fix-item { background: white; padding: 1rem; margin: 0.5rem 0; border-radius: 8px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-4">
            <h1 class="text-white"><i class="fas fa-tools"></i> Database Issue Fixer</h1>
            <p class="text-white-50">Fix category and data issues after CSV import</p>
        </div>

        <?php if (isset($fix_results['success']) && $fix_results['success'] && isset($fix_results['issues'])): ?>
        <!-- Database Issues Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Database Issues Found</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-number text-primary"><?php echo $fix_results['issues']['summary']['total_recipes']; ?></div>
                        <div class="text-muted">Total Recipes</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number text-danger"><?php echo $fix_results['issues']['summary']['missing_category_count']; ?></div>
                        <div class="text-muted">Missing Category</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number text-warning"><?php echo $fix_results['issues']['summary']['invalid_category_count']; ?></div>
                        <div class="text-muted">Invalid Category</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number text-info"><?php echo $fix_results['issues']['summary']['missing_title_count']; ?></div>
                        <div class="text-muted">Missing Title</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number text-secondary"><?php echo $fix_results['issues']['summary']['empty_ingredients_count']; ?></div>
                        <div class="text-muted">Empty Ingredients</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Fix Options -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-magic"></i> Quick Fix Options</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="action-section">
                            <h5><i class="fas fa-robot"></i> Auto-Categorize</h5>
                            <p class="small">Automatically categorize recipes based on title keywords</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="bulk_fix">
                                <input type="hidden" name="bulk_action" value="auto_categorize">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-wand-magic-sparkles"></i> Auto-Fix Categories
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="action-section">
                            <h5><i class="fas fa-tag"></i> Set Default Category</h5>
                            <p class="small">Set all invalid categories to a default value</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="bulk_fix">
                                <input type="hidden" name="bulk_action" value="set_default_category">
                                <select name="default_category" class="form-select mb-2">
                                    <option value="lunch">🥪 Lunch</option>
                                    <option value="dinner">🍽️ Dinner</option>
                                    <option value="breakfast">🌅 Breakfast</option>
                                    <option value="appetizer">🥗 Appetizer</option>
                                    <option value="dessert">🍰 Dessert</option>
                                    <option value="bread-bakes">🍞 Bread & Bakes</option>
                                    <option value="salads">🥗 Salads</option>
                                    <option value="healthy">💪 Healthy</option>
                                    <option value="beverages">🥤 Beverages</option>
                                    <option value="snacks">🥜 Snacks</option>
                                </select>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-tags"></i> Set Default
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="action-section">
                            <h5><i class="fas fa-trash"></i> Delete Invalid</h5>
                            <p class="small">Delete recipes with no category and missing essential data</p>
                            <form method="POST" onsubmit="return confirm('Are you sure? This will permanently delete invalid recipes!')">
                                <input type="hidden" name="action" value="bulk_fix">
                                <input type="hidden" name="bulk_action" value="delete_invalid">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i> Delete Invalid
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Fix Section -->
        <?php if (!empty($fix_results['issues']['missing_category']) || !empty($fix_results['issues']['invalid_category'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-edit"></i> Manual Category Fix</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="fix_categories">
                    
                    <?php if (!empty($fix_results['issues']['missing_category'])): ?>
                    <h5 class="text-danger">Missing Categories:</h5>
                    <?php foreach ($fix_results['issues']['missing_category'] as $recipe): ?>
                    <div class="recipe-fix-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <strong>ID: <?php echo $recipe['id']; ?></strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($recipe['title']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <select name="recipe_fixes[<?php echo $recipe['id']; ?>]" class="form-select">
                                    <option value="skip">Skip this recipe</option>
                                    <option value="breakfast">🌅 Breakfast</option>
                                    <option value="lunch">🥪 Lunch</option>
                                    <option value="dinner">🍽️ Dinner</option>
                                    <option value="appetizer">🥗 Appetizer</option>
                                    <option value="dessert">🍰 Dessert</option>
                                    <option value="bread-bakes">🍞 Bread & Bakes</option>
                                    <option value="salads">🥗 Salads</option>
                                    <option value="healthy">💪 Healthy</option>
                                    <option value="beverages">🥤 Beverages</option>
                                    <option value="snacks">🥜 Snacks</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($fix_results['issues']['invalid_category'])): ?>
                    <h5 class="text-warning mt-4">Invalid Categories:</h5>
                    <?php foreach ($fix_results['issues']['invalid_category'] as $recipe): ?>
                    <div class="recipe-fix-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <strong>ID: <?php echo $recipe['id']; ?></strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($recipe['title']); ?></span><br>
                                <small class="text-danger">Current: "<?php echo htmlspecialchars($recipe['category']); ?>"</small>
                            </div>
                            <div class="col-md-6">
                                <select name="recipe_fixes[<?php echo $recipe['id']; ?>]" class="form-select">
                                    <option value="skip">Skip this recipe</option>
                                    <option value="breakfast">🌅 Breakfast</option>
                                    <option value="lunch">🥪 Lunch</option>
                                    <option value="dinner">🍽️ Dinner</option>
                                    <option value="appetizer">🥗 Appetizer</option>
                                    <option value="dessert">🍰 Dessert</option>
                                    <option value="bread-bakes">🍞 Bread & Bakes</option>
                                    <option value="salads">🥗 Salads</option>
                                    <option value="healthy">💪 Healthy</option>
                                    <option value="beverages">🥤 Beverages</option>
                                    <option value="snacks">🥜 Snacks</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($fix_results['issues']['missing_category']) || !empty($fix_results['issues']['invalid_category'])): ?>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Fix Selected Categories
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <?php elseif (isset($fix_results['success']) && $fix_results['success'] && isset($fix_results['results'])): ?>
        <!-- Bulk Fix Results -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-check-circle"></i> Fix Results</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-number text-success"><?php echo $fix_results['results']['categories_fixed']; ?></div>
                        <div class="text-muted">Categories Fixed</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number text-info"><?php echo $fix_results['results']['auto_categorized']; ?></div>
                        <div class="text-muted">Auto-Categorized</div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-number text-danger"><?php echo $fix_results['results']['deleted_invalid']; ?></div>
                        <div class="text-muted">Deleted Invalid</div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="?refresh=1" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Scan Again
                    </a>
                    <a href="admin-dashboard.php" class="btn btn-success">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <?php elseif (isset($fix_results['success']) && $fix_results['success'] && isset($fix_results['fixed'])): ?>
        <!-- Manual Fix Results -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-check-circle"></i> Categories Fixed</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Successfully fixed <strong><?php echo $fix_results['fixed']; ?></strong> recipe categories!
                </div>
                
                <div class="text-center">
                    <a href="?refresh=1" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Scan Again
                    </a>
                    <a href="admin-dashboard.php" class="btn btn-success">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- No Issues Found -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-check-circle"></i> Database Status</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> No issues found! Your database looks good.
                </div>
                
                <div class="text-center">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="scan_issues">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Scan for Issues
                        </button>
                    </form>
                    <a href="admin-dashboard.php" class="btn btn-success">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (isset($fix_results['error'])): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error: <?php echo htmlspecialchars($fix_results['error']); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
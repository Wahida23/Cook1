<?php
session_start();
require_once 'config.php';
require_once 'auth_functions.php';

// Set basic security headers
setBasicSecurityHeaders();
basicSessionSecurity();

// Check if user is logged in
$isLoggedIn = isUserLoggedIn();
$currentUser = null;
if ($isLoggedIn) {
    $currentUser = getCurrentUser();
}

// Get ingredients from URL parameter
$selectedIngredients = isset($_GET['ingredients']) ? explode(',', $_GET['ingredients']) : [];
$selectedIngredients = array_filter(array_map('trim', $selectedIngredients));

$searchResults = [];
$noResults = false;

// If ingredients are provided, search for recipes
if (!empty($selectedIngredients)) {
    try {
        // Build search query - find recipes that contain any of the ingredients
        $ingredientPlaceholders = str_repeat('?,', count($selectedIngredients) - 1) . '?';
        $sql = "SELECT *, 
                (";
        
        // Count how many ingredients match
        foreach ($selectedIngredients as $index => $ingredient) {
            if ($index > 0) $sql .= " + ";
            $sql .= "(LOWER(ingredients) LIKE LOWER(?))";
        }
        
        $sql .= ") as ingredient_match_count
                FROM recipes 
                WHERE (status = 'published' OR status IS NULL OR status = '') 
                AND (";
        
        // Add OR conditions for each ingredient
        for ($i = 0; $i < count($selectedIngredients); $i++) {
            if ($i > 0) $sql .= " OR ";
            $sql .= "LOWER(ingredients) LIKE LOWER(?)";
        }
        
        $sql .= ") ORDER BY ingredient_match_count DESC, created_at DESC LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        
        // Prepare parameters with wildcards
        $params = [];
        // First set for counting matches
        foreach ($selectedIngredients as $ingredient) {
            $params[] = "%{$ingredient}%";
        }
        // Second set for WHERE clause
        foreach ($selectedIngredients as $ingredient) {
            $params[] = "%{$ingredient}%";
        }
        
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll();
        
        if (empty($searchResults)) {
            $noResults = true;
        }
        
    } catch (PDOException $e) {
        logError("Kitchen finder error: " . $e->getMessage());
        $searchResults = [];
        $noResults = true;
    }
}

// Popular ingredients for suggestions
$popularIngredients = [
    'chicken', 'rice', 'tomato', 'onion', 'garlic', 'potato', 'egg', 'flour',
    'milk', 'cheese', 'pasta', 'beef', 'fish', 'lemon', 'butter', 'oil',
    'carrot', 'bell pepper', 'mushroom', 'spinach', 'ginger', 'chili'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Finder - Find Recipes by Ingredients - Cookistry</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body {
            background: linear-gradient(135deg, 
                rgba(76, 161, 175, 0.1) 0%, 
                rgba(44, 62, 80, 0.05) 25%,
                rgba(235, 242, 247, 1) 50%,
                rgba(76, 161, 175, 0.08) 75%,
                rgba(44, 62, 80, 0.1) 100%);
            min-height: 100vh;
        }

        .kitchen-finder-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .finder-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .finder-header h1 {
            font-size: 2.5rem;
            color: #1e293b;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .finder-header p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .ingredients-search-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .ingredients-input-area {
            margin-bottom: 2rem;
        }

        .ingredients-input-area h3 {
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .selected-ingredients {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            min-height: 60px;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .selected-ingredients.empty {
            justify-content: center;
            color: #64748b;
            font-style: italic;
        }

        .ingredient-tag {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ingredient-tag .remove-btn {
            background: rgba(255, 255, 255, 0.3);
            border: none;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }

        .ingredient-input-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .ingredient-input-group input {
            flex: 1;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .ingredient-input-group input:focus {
            outline: none;
            border-color: #4ca1af;
            box-shadow: 0 0 0 3px rgba(76, 161, 175, 0.1);
        }

        .add-ingredient-btn {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .add-ingredient-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.3);
        }

        .popular-ingredients {
            margin-bottom: 1.5rem;
        }

        .popular-ingredients h4 {
            color: #374151;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .popular-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .popular-ingredient {
            background: #f1f5f9;
            color: #475569;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .popular-ingredient:hover {
            background: #4ca1af;
            color: white;
            transform: translateY(-1px);
        }

        .search-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .find-recipes-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 1.2rem 3rem;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .find-recipes-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
        }

        .clear-btn {
            background: #f1f5f9;
            color: #64748b;
            border: 2px solid #e2e8f0;
            padding: 1.2rem 2rem;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .clear-btn:hover {
            background: #e2e8f0;
        }

        .results-section {
            margin-top: 2rem;
        }

        .results-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .recipe-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .recipe-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .recipe-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .recipe-content {
            padding: 1.5rem;
        }

        .recipe-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .recipe-description {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .recipe-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            color: #64748b;
        }

        .matching-ingredients {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }

        .matching-ingredients h5 {
            color: #0c4a6e;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
        }

        .matched-ingredient {
            display: inline-block;
            background: #0ea5e9;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            margin: 0.2rem;
        }

        .view-recipe-btn {
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-recipe-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 161, 175, 0.3);
            color: white;
            text-decoration: none;
        }

        .no-results {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .no-results-icon {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .no-results h3 {
            color: #374151;
            margin-bottom: 1rem;
        }

        .no-results p {
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .ai-help-section {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-top: 1.5rem;
            text-align: center;
        }

        .ai-help-section h4 {
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .ai-help-section p {
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .ai-help-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .ai-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ai-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .kitchen-finder-container {
                padding: 1rem;
            }

            .finder-header h1 {
                font-size: 2rem;
            }

            .ingredient-input-group {
                flex-direction: column;
            }

            .search-actions {
                flex-direction: column;
            }

            .ai-help-buttons {
                flex-direction: column;
            }

            .results-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Header (same as homepage) -->
    <header>
        <div class="navbar">
            <a href="index.php">
                <img src="images/logo.png" alt="Cookistry Logo" class="logo" />
            </a>
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
                        <li><a href="upload-recipe.php"><i class="fas fa-plus-circle"></i> Submit Recipe</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="signup.php">Signup</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="kitchen-finder-container">
        <!-- Header Section -->
        <div class="finder-header">
            <h1><i class="fas fa-search"></i> Kitchen Finder</h1>
            <p>Tell us what ingredients you have, and we'll find perfect recipes for you!</p>
        </div>

        <!-- Ingredients Search Section -->
        <div class="ingredients-search-section">
            <div class="ingredients-input-area">
                <h3><i class="fas fa-list"></i> Select Your Available Ingredients</h3>
                
                <div class="selected-ingredients <?php echo empty($selectedIngredients) ? 'empty' : ''; ?>" id="selectedIngredients">
                    <?php if (empty($selectedIngredients)): ?>
                        <span>No ingredients selected yet. Add some below!</span>
                    <?php else: ?>
                        <?php foreach ($selectedIngredients as $ingredient): ?>
                            <div class="ingredient-tag">
                                <?php echo htmlspecialchars($ingredient); ?>
                                <button class="remove-btn" onclick="removeIngredient('<?php echo htmlspecialchars($ingredient); ?>')">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ingredient-input-group">
                    <input type="text" id="ingredientInput" placeholder="Type an ingredient name..." onkeypress="handleEnterKey(event)">
                    <button class="add-ingredient-btn" onclick="addIngredient()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>

                <div class="popular-ingredients">
                    <h4>Quick Add (Popular Ingredients):</h4>
                    <div class="popular-list">
                        <?php foreach ($popularIngredients as $ingredient): ?>
                            <span class="popular-ingredient" onclick="addSuggestedIngredient('<?php echo $ingredient; ?>')">
                                <?php echo ucfirst($ingredient); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="search-actions">
                    <button class="find-recipes-btn" onclick="findRecipes()">
                        <i class="fas fa-search"></i> Find Recipes
                    </button>
                    <button class="clear-btn" onclick="clearAll()">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <?php if (!empty($selectedIngredients)): ?>
        <div class="results-section">
            <div class="results-header">
                <h2>
                    <?php if (!empty($searchResults)): ?>
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                        Found <?php echo count($searchResults); ?> recipes with your ingredients!
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle" style="color: #f59e0b;"></i>
                        No recipes found with those ingredients
                    <?php endif; ?>
                </h2>
                <p>
                    Searching with: 
                    <?php foreach ($selectedIngredients as $index => $ingredient): ?>
                        <strong><?php echo htmlspecialchars($ingredient); ?></strong><?php echo ($index < count($selectedIngredients) - 1) ? ', ' : ''; ?>
                    <?php endforeach; ?>
                </p>
            </div>

            <?php if (!empty($searchResults)): ?>
                <div class="results-grid">
                    <?php foreach ($searchResults as $recipe): ?>
                        <div class="recipe-card">
                            <?php 
                            $imagePath = !empty($recipe['image']) ? htmlspecialchars($recipe['image']) : 'images/default-recipe.jpg';
                            if (!file_exists($imagePath) && !filter_var($imagePath, FILTER_VALIDATE_URL)) {
                                $imagePath = 'images/default-recipe.jpg';
                            }
                            ?>
                            <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>" class="recipe-image">
                            
                            <div class="recipe-content">
                                <h3 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                <p class="recipe-description"><?php echo htmlspecialchars(substr($recipe['description'], 0, 100) . '...'); ?></p>
                                
                                <div class="recipe-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($recipe['servings'] ?? '4'); ?></span>
                                    <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($recipe['difficulty'] ?? 'Medium'); ?></span>
                                </div>

                                <?php
                                // Find matching ingredients
                                $recipeIngredients = strtolower($recipe['ingredients']);
                                $matchedIngredients = [];
                                foreach ($selectedIngredients as $userIngredient) {
                                    if (strpos($recipeIngredients, strtolower($userIngredient)) !== false) {
                                        $matchedIngredients[] = $userIngredient;
                                    }
                                }
                                ?>

                                <?php if (!empty($matchedIngredients)): ?>
                                <div class="matching-ingredients">
                                    <h5><i class="fas fa-check"></i> Matching ingredients:</h5>
                                    <?php foreach ($matchedIngredients as $matched): ?>
                                        <span class="matched-ingredient"><?php echo htmlspecialchars($matched); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <a href="recipe-detail.php?id=<?php echo $recipe['id']; ?>" class="view-recipe-btn">
                                    <i class="fas fa-eye"></i> View Recipe
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No recipes found with your ingredients</h3>
                    <p>Don't worry! We couldn't find recipes in our database that match your available ingredients, but you still have options:</p>
                    
                    <div class="ai-help-section">
                        <h4><i class="fas fa-robot"></i> Get AI Recipe Suggestions</h4>
                        <p>Try asking AI assistants for custom recipes with your ingredients. They can create unique recipes just for you!</p>
                        
                        <div class="ai-help-buttons">
                            <a href="https://chat.openai.com" target="_blank" class="ai-btn">
                                <i class="fas fa-comments"></i> Ask ChatGPT
                            </a>
                            <a href="https://claude.ai" target="_blank" class="ai-btn">
                                <i class="fas fa-robot"></i> Try Claude AI
                            </a>
                            <a href="https://gemini.google.com" target="_blank" class="ai-btn">
                                <i class="fas fa-star"></i> Google Gemini
                            </a>
                        </div>
                        
                        <p style="margin-top: 1rem; font-size: 0.9rem; opacity: 0.8;">
                            <i class="fas fa-lightbulb"></i> 
                            Tip: Copy your ingredient list: <strong><?php echo implode(', ', $selectedIngredients); ?></strong>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let userIngredients = <?php echo json_encode($selectedIngredients); ?>;

        function addIngredient() {
            const input = document.getElementById('ingredientInput');
            const ingredient = input.value.trim().toLowerCase();
            
            if (ingredient && !userIngredients.includes(ingredient)) {
                userIngredients.push(ingredient);
                updateSelectedIngredients();
                input.value = '';
            }
        }

        function addSuggestedIngredient(ingredient) {
            if (!userIngredients.includes(ingredient)) {
                userIngredients.push(ingredient);
                updateSelectedIngredients();
            }
        }

        function removeIngredient(ingredient) {
            userIngredients = userIngredients.filter(item => item !== ingredient);
            updateSelectedIngredients();
        }

        function updateSelectedIngredients() {
            const container = document.getElementById('selectedIngredients');
            
            if (userIngredients.length === 0) {
                container.innerHTML = '<span>No ingredients selected yet. Add some below!</span>';
                container.classList.add('empty');
            } else {
                container.classList.remove('empty');
                container.innerHTML = userIngredients.map(ingredient => `
                    <div class="ingredient-tag">
                        ${ingredient}
                        <button class="remove-btn" onclick="removeIngredient('${ingredient}')">×</button>
                    </div>
                `).join('');
            }
        }

        function handleEnterKey(event) {
            if (event.key === 'Enter') {
                addIngredient();
            }
        }

        function findRecipes() {
            if (userIngredients.length === 0) {
                alert('Please add some ingredients first!');
                return;
            }
            
            const ingredientsString = userIngredients.join(',');
            window.location.href = `kitchen-finder.php?ingredients=${encodeURIComponent(ingredientsString)}`;
        }

        function clearAll() {
            userIngredients = [];
            updateSelectedIngredients();
            window.location.href = 'kitchen-finder.php';
        }

        // Copy ingredients to clipboard
        function copyIngredients() {
            if (userIngredients.length === 0) {
                alert('No ingredients to copy!');
                return;
            }
            
            const ingredientsList = userIngredients.join(', ');
            navigator.clipboard.writeText(ingredientsList).then(() => {
                // Show success message
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            }).catch(() => {
                // Fallback for older browsers
                prompt('Copy this ingredients list:', ingredientsList);
            });
        }

        // Enhanced animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate recipe cards
            const cards = document.querySelectorAll('.recipe-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add hover effects to popular ingredients
            const popularIngredients = document.querySelectorAll('.popular-ingredient');
            popularIngredients.forEach(ingredient => {
                ingredient.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });

        // Add smooth scroll
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
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

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();

// Get search and filter parameters
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';

try {
    // Build query with filters
    $sql = "SELECT * FROM recipes WHERE (status = 'published' OR status IS NULL OR status = '')";
    $params = [];

    if (!empty($searchTerm)) {
        $sql .= " AND (title LIKE :search OR description LIKE :search OR tags LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }

    if (!empty($category) && $category !== 'all') {
        $sql .= " AND LOWER(category) = :category";
        $params[':category'] = strtolower($category);
    }

    $sql .= " ORDER BY created_at DESC LIMIT 12";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $featuredRecipes = $stmt->fetchAll();

    // Get recipe statistics
    $stats = getRecipeStats();

    // Get quick pick recipes (recipes with prep time <= 30 minutes)
    $quickPicksSql = "SELECT * FROM recipes WHERE (status = 'published' OR status IS NULL OR status = '') 
                      AND (prep_time LIKE '%15%' OR prep_time LIKE '%20%' OR prep_time LIKE '%30%' OR prep_time LIKE '%quick%') 
                      ORDER BY RAND() LIMIT 6";
    $quickPicksStmt = $pdo->prepare($quickPicksSql);
    $quickPicksStmt->execute();
    $quickPicks = $quickPicksStmt->fetchAll();

} catch (PDOException $e) {
    logError("Homepage error: " . $e->getMessage());
    $featuredRecipes = [];
    $quickPicks = [];
    $stats = ['total_recipes' => 0, 'by_category' => [], 'avg_rating' => 0];
}

// Daily cooking tips array
$cookingTips = [
    "Always let your meat rest for 5-10 minutes after cooking to redistribute the juices.",
    "Add a pinch of salt to your coffee grounds before brewing for a smoother taste.",
    "Room temperature eggs mix better in baking recipes than cold ones.",
    "To prevent pasta from sticking, stir it frequently during the first 2 minutes of cooking.",
    "Add acidic ingredients like lemon juice or vinegar at the end of cooking to preserve their bright flavor.",
    "Use a kitchen scale for more accurate baking measurements.",
    "Taste your food as you cook and adjust seasonings gradually.",
    "Keep your knives sharp - they're safer and more efficient than dull ones.",
    "Let your pan heat up before adding oil to prevent sticking.",
    "Save pasta water - the starchy liquid is perfect for loosening sauces.",
    "Mise en place - prepare all ingredients before you start cooking.",
    "Don't overcrowd your pan when sautéing, or food will steam instead of brown.",
    "Fresh herbs should be added at the end of cooking to preserve their flavor.",
    "Use the right size pan for the amount of food you're cooking.",
    "Season food in layers throughout the cooking process, not just at the end."
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Cookistry - Your Gateway to Creative Cooking</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="icon" href="images/logo.png" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Enhanced submit recipe modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      backdrop-filter: blur(5px);
    }

    .modal-content {
      background-color: white;
      margin: 5% auto;
      padding: 2rem;
      border-radius: 20px;
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      transition: color 0.3s ease;
    }

    .close:hover {
      color: #333;
    }

    .modal h2 {
      color: #1e293b;
      margin-bottom: 1.5rem;
      text-align: center;
      font-size: 2rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: #374151;
      font-weight: 600;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e5e7eb;
      border-radius: 10px;
      font-size: 1rem;
      transition: all 0.3s ease;
      font-family: 'Poppins', sans-serif;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 100px;
    }

    .submit-btn {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      padding: 1rem 2rem;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
    }

    .submit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
    }

    .required {
      color: #ef4444;
    }

    .form-note {
      background: #f0f9ff;
      border: 1px solid #0ea5e9;
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      color: #0c4a6e;
      font-size: 0.9rem;
    }

    /* Improved Navigation */
    .logo {
      height: 60px !important;
      width: 60px !important;
    }

    .navbar {
      padding: 0.8rem 1rem;
    }

    nav {
      margin-left: auto;
      margin-right: 2rem;
    }

    /* User dropdown styles */
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
      color: white;
      text-decoration: none;
    }

    .user-menu a:hover {
      background: rgba(255,255,255,0.1);
      padding-left: 1.2rem;
    }

    
    /* Enhanced What's in Kitchen Section - Better contrast and functionality */
    .whats-in-kitchen {
      background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
      color: #1e293b;
      padding: 4rem 0;
    }

    .whats-in-kitchen h2 {
      color: #1e293b;
      text-shadow: none;
    }

    .whats-in-kitchen .section-subtitle {
      color: #64748b;
    }

    .kitchen-finder {
      max-width: 800px;
      margin: 0 auto;
    }

    .ingredients-input {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 2px solid rgba(76, 161, 175, 0.2);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      border-radius: 20px;
      padding: 2rem;
    }

    .ingredients-input h3 {
      color: #1e293b;
      margin-bottom: 1.5rem;
      font-size: 1.3rem;
      text-align: center;
    }

    .ingredient-tags {
      background: rgba(248, 250, 252, 0.8);
      border: 2px dashed rgba(76, 161, 175, 0.4);
      border-radius: 12px;
      padding: 1rem;
      margin-bottom: 1.5rem;
      min-height: 80px;
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }

    .ingredient-tags:empty::before {
      content: "No ingredients added yet";
      color: #64748b;
      font-style: italic;
      width: 100%;
      text-align: center;
    }

    .ingredient-tag {
      background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
      color: white;
      padding: 0.6rem 1rem;
      border-radius: 25px;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 0.8rem;
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(76, 161, 175, 0.3);
      transition: all 0.3s ease;
    }

    .ingredient-tag:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(76, 161, 175, 0.4);
    }

    .ingredient-tag i {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 0.7rem;
      transition: all 0.3s ease;
    }

    .ingredient-tag i:hover {
      background: rgba(255, 255, 255, 0.5);
      transform: scale(1.1);
    }

    .input-group {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .input-group input {
      flex: 1;
      background: white;
      border: 2px solid rgba(76, 161, 175, 0.3);
      color: #1e293b;
      padding: 1rem;
      border-radius: 12px;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .input-group input:focus {
      outline: none;
      border-color: #4ca1af;
      box-shadow: 0 0 0 3px rgba(76, 161, 175, 0.1);
    }

    .input-group input::placeholder {
      color: #64748b;
    }

    .btn-add {
      background: rgba(76, 161, 175, 0.15);
      color: #2c3e50;
      border: 2px solid #4ca1af;
      padding: 1rem 1.5rem;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-add:hover {
      background: #4ca1af;
      color: white;
      transform: translateY(-2px);
    }

    .suggested-ingredients {
      margin-bottom: 1.5rem;
      text-align: center;
    }

    .suggestion-label {
      color: #1e293b;
      font-weight: 600;
      margin-right: 1rem;
      font-size: 0.9rem;
    }

    .ingredient-suggestion {
      background: rgba(255, 255, 255, 0.9);
      color: #2c3e50;
      border: 1px solid rgba(76, 161, 175, 0.4);
      padding: 0.4rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-block;
      margin: 0.2rem;
    }

    .ingredient-suggestion:hover {
      background: #4ca1af;
      color: white;
      transform: translateY(-1px);
    }

    .kitchen-action-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
      margin-top: 1.5rem;
    }

    .btn-find-recipes {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      border: none;
      padding: 1.2rem 2.5rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
      box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .btn-find-recipes:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-3px);
      box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
    }

    .btn-advanced-search {
      background: rgba(255, 255, 255, 0.15);
      color: #2c3e50;
      padding: 1.2rem 2rem;
      border: 2px solid rgba(76, 161, 175, 0.3);
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .btn-advanced-search:hover {
      background: rgba(76, 161, 175, 0.15);
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(76, 161, 175, 0.2);
      border-color: rgba(76, 161, 175, 0.5);
      color: #2c3e50;
      text-decoration: none;
    }

    .btn-advanced-search i {
      font-size: 1.1rem;
      transition: transform 0.3s ease;
    }

    .btn-advanced-search:hover i {
      transform: rotate(90deg);
    }

    /* Quick Picks View More Button */
    .view-more-section {
      text-align: center;
      margin-top: 2rem;
      padding-top: 2rem;
      border-top: 2px solid rgba(255, 255, 255, 0.2);
    }

    .btn-view-more {
      display: inline-flex;
      align-items: center;
      gap: 0.8rem;
      background: rgba(255, 255, 255, 0.15);
      color: white;
      padding: 1rem 2rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .btn-view-more:hover {
      background: rgba(255, 255, 255, 0.25);
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
      border-color: rgba(255, 255, 255, 0.5);
    }

    .btn-view-more i {
      font-size: 1.1rem;
      transition: transform 0.3s ease;
    }

    .btn-view-more:hover i {
      transform: translateX(4px);
    }

    /* Mobile responsiveness for modal and kitchen finder */
    @media (max-width: 768px) {
      .modal-content {
        margin: 10% auto;
        width: 95%;
        padding: 1.5rem;
      }

      nav {
        margin-right: 1rem;
      }

      .navbar {
        padding: 0.5rem;
      }

      nav ul {
        flex-wrap: wrap;
        justify-content: center;
      }

      .user-dropdown .dropdown-content {
        position: relative;
        display: none;
        box-shadow: none;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        margin-top: 0.5rem;
      }
      
      .user-dropdown:hover .dropdown-content {
        display: block;
      }

      .kitchen-action-buttons {
        flex-direction: column;
        align-items: center;
      }
      
      .btn-find-recipes,
      .btn-advanced-search {
        width: 100%;
        max-width: 300px;
        justify-content: center;
      }
      
      .ingredient-tags {
        text-align: center;
      }

      .input-group {
        flex-direction: column;
      }

      .suggested-ingredients {
        text-align: center;
      }

      .suggestion-label {
        display: block;
        margin-bottom: 0.5rem;
      }
    }

    @media (max-width: 480px) {
      .logo {
        height: 50px !important;
        width: 50px !important;
      }

      nav {
        margin-right: 0.5rem;
      }

      .ingredients-input {
        padding: 1.5rem;
      }

      .ingredients-input h3 {
        font-size: 1.1rem;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="navbar">
      <img src="images/logo.png" alt="Cookistry Logo" class="logo" />
      <nav>
        <ul>
          <li><a href="#" class="active">Home</a></li>
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
            <li><a href="upload-recipe.php"><i class="fas fa-plus-circle"></i> Submit Recipe</a></li>
          <?php else: ?>
            <!-- Guest user menu -->
            <li><a href="login.php">Login</a></li>
            <li><a href="signup.php">Signup</a></li>
            <li><a href="#" onclick="openSubmitModal()"><i class="fas fa-plus-circle"></i> Submit Recipe</a></li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>

    <div class="search-box">
      <form action="search.php" method="GET">
        <input type="text" name="search" placeholder="Search recipes..." value="<?php echo htmlspecialchars($searchTerm); ?>" />
        <button type="submit"><i class="fas fa-search"></i> Search</button>
      </form>
    </div>
  </header>

  <section class="hero">
    <div class="hero-content">
      <h1>Welcome to Cookistry</h1>
      <p>Discover Sparkling Recipes That Light Up Your Kitchen!</p>
      <a href="#categories" class="btn">Explore Recipes</a>
    </div>
  </section>

  <section class="submit-recipe">
    <!-- Add sparkle elements for animation -->
    <div class="sparkle"></div>
    <div class="sparkle"></div>
    <div class="sparkle"></div>
    <div class="sparkle"></div>
    
    <h2>Have a Recipe to Share?</h2>
    <p>We'd love to hear your secret dish! Click below to submit.</p>
    <?php if ($isLoggedIn): ?>
      <a href="upload-recipe.php" class="btn">Add Your Recipe</a>
    <?php else: ?>
      <a href="#" class="btn" onclick="openSubmitModal()">Add Your Recipe</a>
    <?php endif; ?>
  </section>

  <section class="categories" id="categories">
    <h2>Recipe Categories</h2>
    <div class="category-cards">
      <div class="card">
        <a href="appetizer.php">
          <div class="card-image">
            <img src="images/appetizer.png" alt="Appetizer" />
            <div class="card-text"><p>Appetizer</p></div>
          </div>
        </a>
      </div>
      <div class="card">
        <a href="breakfast.php">
          <div class="card-image">
            <img src="images/breakfast.jpg" alt="Breakfast" />
            <div class="card-text"><p>Breakfast</p></div>
          </div>
        </a>
      </div>
      <div class="card">
        <a href="lunch.php">
        <div class="card-image">
          <img src="images/lunch.jpg" alt="Lunch" />
          <div class="card-text"><p>Lunch</p></div>
        </div>
        </a>
      </div>
      <div class="card">
        <a href="dinner.php">
        <div class="card-image">
          <img src="images/dinner.jpg" alt="Dinner" />
          <div class="card-text"><p>Dinner</p></div>
        </div>
        </a>
      </div>
      <div class="card">
        <a href="dessert.php">
        <div class="card-image">
          <img src="images/dessert.jpg" alt="Dessert" />
          <div class="card-text"><p>Dessert</p></div>
        </div>
        </a>
      </div>
      <div class="card">
        <a href="bread-bakes.php">
        <div class="card-image">
          <img src="images/bread.jpg" alt="Bread & Bakes" />
          <div class="card-text"><p>Bread & Bakes</p></div>
        </div>
        </a>
      </div>
      <div class="card">
        <a href="salads.php">
        <div class="card-image">
          <img src="images/salads.jpg" alt="Salads" />
          <div class="card-text"><p>Salads</p></div>
        </div>
        </a>
      </div>
      <div class="card">
        <a href="healthy.php">
        <div class="card-image">
          <img src="images/healthy.jpg" alt="Healthy Food" />
          <div class="card-text"><p>Healthy Food</p></div>
        </div>
        </a>
      </div>
      <div class="card">
        <a href="beverages.php">
        <div class="card-image">
          <img src="images/beverages.jpg" alt="Beverages" />
          <div class="card-text"><p>Beverages</p></div>
        </div>
        </a>
      </div>
      <div class="card">
        <a href="snacks.php">
        <div class="card-image">
          <img src="images/snacks.jpg" alt="Snacks" />
          <div class="card-text"><p>Snacks</p></div>
        </div>
        </a>
      </div>
    </div>
  </section>

  <!-- Daily Tips Section -->
  <section class="daily-tips">
    <div class="container">
      <h2><i class="fas fa-lightbulb"></i> Daily Cooking Tips</h2>
      <p class="section-subtitle">Master the art of cooking with expert tips</p>
      
      <div class="tips-container">
        <div class="tip-display">
          <div class="tip-icon">
            <i class="fas fa-chef-hat"></i>
          </div>
          <div class="tip-content">
            <p id="current-tip"><?php echo $cookingTips[0]; ?></p>
          </div>
        </div>
        <button class="btn-new-tip" onclick="getNewTip()">
          <i class="fas fa-sync-alt"></i> Get New Tip
        </button>
      </div>
    </div>
  </section>

  <!-- What's in Your Kitchen Section - Updated with Full Functionality -->
  <section class="whats-in-kitchen">
    <div class="container">
      <h2><i class="fas fa-utensils"></i> What's in Your Kitchen?</h2>
      <p class="section-subtitle">Find recipes based on ingredients you have</p>
      
      <div class="kitchen-finder">
        <div class="ingredients-input">
          <h3>Enter your available ingredients:</h3>
          <div class="ingredient-tags" id="ingredientTags">
            <!-- Dynamic ingredient tags will appear here -->
          </div>
          <div class="input-group">
            <input type="text" id="ingredientInput" placeholder="Type an ingredient and press Enter..." />
            <button type="button" onclick="addIngredient()" class="btn-add">
              <i class="fas fa-plus"></i> Add
            </button>
          </div>
          <div class="suggested-ingredients">
            <span class="suggestion-label">Quick add:</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('chicken')">Chicken</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('rice')">Rice</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('tomato')">Tomato</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('onion')">Onion</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('garlic')">Garlic</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('potato')">Potato</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('egg')">Egg</span>
            <span class="ingredient-suggestion" onclick="addSuggestedIngredient('cheese')">Cheese</span>
          </div>
          <div class="kitchen-action-buttons">
            <button type="button" onclick="findRecipes()" class="btn-find-recipes">
              <i class="fas fa-search"></i> Find Recipes
            </button>
            <a href="kitchen-finder.php" class="btn-advanced-search">
              <i class="fas fa-cogs"></i> Advanced Search
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Quick Picks Section -->
  <section class="quick-picks">
    <div class="container">
      <h2><i class="fas fa-bolt"></i> Quick Picks</h2>
      <p class="section-subtitle">Fast & delicious recipes for busy days</p>
      
      <div class="quick-picks-grid">
        <?php if (!empty($quickPicks)): ?>
          <?php foreach ($quickPicks as $recipe): ?>
            <div class="quick-pick-card">
              <div class="quick-pick-image">
                <img src="<?php echo htmlspecialchars($recipe['image'] ?? 'images/default-recipe.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($recipe['title']); ?>" />
                <div class="quick-time-badge">
                  <i class="fas fa-clock"></i> <?php echo htmlspecialchars($recipe['prep_time'] ?? '30 min'); ?>
                </div>
              </div>
              <div class="quick-pick-content">
                <h3><?php echo htmlspecialchars($recipe['title']); ?></h3>
                <p><?php echo htmlspecialchars(substr($recipe['description'], 0, 80) . '...'); ?></p>
                <a href="recipe-detail.php?id=<?php echo $recipe['id']; ?>" class="btn-quick">
                  <i class="fas fa-arrow-right"></i> Quick Cook
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-quick-picks">
            <i class="fas fa-clock"></i>
            <p>Quick recipes coming soon!</p>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- View More Quick Picks Button -->
      <div class="view-more-section">
        <a href="quick-picks.php" class="btn-view-more">
          <span>View More Quick Picks</span>
          <i class="fas fa-arrow-right"></i>
        </a>
      </div>
    </div>
  </section>
 
  <!-- Submit Recipe Modal -->
  <div id="submitModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeSubmitModal()">&times;</span>
      <h2><i class="fas fa-utensils"></i> Submit Your Recipe</h2>
      
      <div class="form-note">
        <i class="fas fa-info-circle"></i> 
        <?php if ($isLoggedIn): ?>
          Share your delicious recipe with our community! All fields marked with <span class="required">*</span> are required.
        <?php else: ?>
          Please <a href="login.php" style="color: #0ea5e9; text-decoration: underline;">login</a> or <a href="signup.php" style="color: #0ea5e9; text-decoration: underline;">signup</a> to submit recipes.
        <?php endif; ?>
      </div>

      <?php if ($isLoggedIn): ?>
      <form id="recipeForm" action="upload-recipe.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        
        <div class="form-group">
          <label for="recipeTitle">Recipe Title <span class="required">*</span></label>
          <input type="text" id="recipeTitle" name="title" required placeholder="Enter your recipe title">
        </div>

        <div class="form-group">
          <label for="recipeCategory">Category <span class="required">*</span></label>
          <select id="recipeCategory" name="category" required>
            <option value="">Select a category</option>
            <option value="appetizer">Appetizer</option>
            <option value="breakfast">Breakfast</option>
            <option value="lunch">Lunch</option>
            <option value="dinner">Dinner</option>
            <option value="dessert">Dessert</option>
            <option value="bread-bakes">Bread & Bakes</option>
            <option value="salads">Salads</option>
            <option value="healthy">Healthy Food</option>
            <option value="beverages">Beverages</option>
            <option value="snacks">Snacks</option>
          </select>
        </div>

        <div class="form-group">
          <label for="recipeDescription">Description <span class="required">*</span></label>
          <textarea id="recipeDescription" name="description" required placeholder="Briefly describe your recipe"></textarea>
        </div>

        <div class="form-group">
          <label for="recipeIngredients">Ingredients <span class="required">*</span></label>
          <textarea id="recipeIngredients" name="ingredients" required placeholder="List all ingredients (one per line)"></textarea>
        </div>

        <div class="form-group">
          <label for="recipeInstructions">Cooking Instructions <span class="required">*</span></label>
          <textarea id="recipeInstructions" name="instructions" required placeholder="Step-by-step cooking instructions"></textarea>
        </div>

        <div class="form-group">
          <label for="recipePrepTime">Preparation Time</label>
          <input type="text" id="recipePrepTime" name="prep_time" placeholder="e.g., 30 minutes">
        </div>

        <div class="form-group">
          <label for="recipeCookTime">Cooking Time</label>
          <input type="text" id="recipeCookTime" name="cook_time" placeholder="e.g., 45 minutes">
        </div>

        <div class="form-group">
          <label for="recipeServings">Servings</label>
          <input type="number" id="recipeServings" name="servings" placeholder="Number of servings" min="1">
        </div>

        <div class="form-group">
          <label for="recipeDifficulty">Difficulty Level</label>
          <select id="recipeDifficulty" name="difficulty">
            <option value="">Select difficulty</option>
            <option value="easy">Easy</option>
            <option value="medium">Medium</option>
            <option value="hard">Hard</option>
          </select>
        </div>

        <div class="form-group">
          <label for="recipeImage">Recipe Image URL</label>
          <input type="url" id="recipeImage" name="image" placeholder="https://example.com/your-recipe-image.jpg">
        </div>

        <div class="form-group">
          <label for="recipeTags">Tags</label>
          <input type="text" id="recipeTags" name="tags" placeholder="vegetarian, healthy, quick (comma separated)">
        </div>

        <button type="submit" class="submit-btn">
          <i class="fas fa-paper-plane"></i> Submit Recipe
        </button>
      </form>
      <?php else: ?>
        <div style="text-align: center; padding: 2rem;">
          <p style="margin-bottom: 1.5rem; color: #64748b;">Please create an account or login to submit recipes.</p>
          <a href="signup.php" class="submit-btn" style="display: inline-block; text-decoration: none; margin-bottom: 1rem;">
            <i class="fas fa-user-plus"></i> Create Account
          </a>
          <br>
          <a href="login.php" style="color: #4ca1af; text-decoration: none; font-weight: 600;">
            Already have an account? Login here
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

<footer class="enhanced-footer">
  <div class="footer-container">
    <div class="footer-grid">
      <!-- About Section -->
      <div class="footer-section">
        <div class="footer-logo">
          <img src="images/logo.png" alt="Cookistry Logo" class="footer-logo-img" />
          <h3>Cookistry</h3>
        </div>
        <p>Your gateway to creative cooking. Discover exciting recipes, cooking tips, and kitchen inspirations to make every meal memorable.</p>
        <div class="social-links">
          <a href="#" class="social-link" title="Facebook">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="#" class="social-link" title="Instagram">
            <i class="fab fa-instagram"></i>
          </a>
          <a href="#" class="social-link" title="Twitter">
            <i class="fab fa-twitter"></i>
          </a>
          <a href="#" class="social-link" title="YouTube">
            <i class="fab fa-youtube"></i>
          </a>
        </div>
      </div>

      <!-- Quick Links Section -->
      <div class="footer-section">
        <h3>Quick Links</h3>
        <ul class="footer-links">
          <li><a href="#">Home</a></li>
          <li><a href="about.php">About Us</a></li>
          <li><a href="contact.php">Contact</a></li>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
        </ul>
      </div>

      <!-- Categories Section -->
      <div class="footer-section">
        <h3>Recipe Categories</h3>
        <div class="category-grid">
          <ul class="footer-links">
            <li><a href="appetizer.php">Appetizers</a></li>
            <li><a href="breakfast.php">Breakfast</a></li>
            <li><a href="lunch.php">Lunch</a></li>
            <li><a href="dinner.php">Dinner</a></li>
          </ul>
          <ul class="footer-links">
            <li><a href="dessert.php">Desserts</a></li>
            <li><a href="bread-bakes.php">Bread & Bakes</a></li>
            <li><a href="salads.php">Salads</a></li>
            <li><a href="healthy.php">Healthy Food</a></li>
          </ul>
        </div>
      </div>

      <!-- Contact Info Section -->
      <div class="footer-section">
        <h3>Get In Touch</h3>
        <div class="contact-info">
          <div class="contact-item">
            <i class="fas fa-envelope"></i>
            <span>support@cookistry.com</span>
          </div>
          <div class="contact-item">
            <i class="fas fa-map-marker-alt"></i>
            <span>Chittagong, Bangladesh</span>
          </div>
        </div>
        <div class="social-links">
          <a href="#" class="social-link" title="Facebook">
            <i class="fab fa-facebook-f"></i>
          </a>
          <a href="#" class="social-link" title="Instagram">
            <i class="fab fa-instagram"></i>
          </a>
          <a href="#" class="social-link" title="Twitter">
            <i class="fab fa-twitter"></i>
          </a>
          <a href="#" class="social-link" title="YouTube">
            <i class="fab fa-youtube"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
      <div class="footer-bottom-content">
        <p>&copy; 2025 Cookistry. All rights reserved.</p>
        <div class="footer-bottom-links">
          <a href="#">Terms</a>
          <span>|</span>
          <a href="#">Privacy</a>
          <span>|</span>
          <a href="#">Cookies</a>
        </div>
      </div>
    </div>
  </div>
</footer>

<script>
// Cooking tips array
const cookingTips = <?php echo json_encode($cookingTips); ?>;
let currentTipIndex = 0;
let userIngredients = [];

// Modal functionality
function openSubmitModal() {
  document.getElementById('submitModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeSubmitModal() {
  document.getElementById('submitModal').style.display = 'none';
  document.body.style.overflow = 'auto';
  const form = document.getElementById('recipeForm');
  if (form) {
    form.reset();
  }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
  const modal = document.getElementById('submitModal');
  if (event.target === modal) {
    closeSubmitModal();
  }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeSubmitModal();
  }
});

// Daily Tips functionality
function getNewTip() {
  currentTipIndex = (currentTipIndex + 1) % cookingTips.length;
  document.getElementById('current-tip').textContent = cookingTips[currentTipIndex];
  
  // Add animation effect
  const tipContent = document.querySelector('.tip-content');
  tipContent.style.opacity = '0';
  setTimeout(() => {
    tipContent.style.opacity = '1';
  }, 200);
}

// Enhanced Kitchen Finder functionality
function addIngredient() {
  const input = document.getElementById('ingredientInput');
  const ingredient = input.value.trim().toLowerCase();
  
  if (ingredient && !userIngredients.includes(ingredient)) {
    userIngredients.push(ingredient);
    updateIngredientTags();
    input.value = '';
  }
}

function addSuggestedIngredient(ingredient) {
  if (!userIngredients.includes(ingredient.toLowerCase())) {
    userIngredients.push(ingredient.toLowerCase());
    updateIngredientTags();
  }
}

function removeIngredient(ingredient) {
  userIngredients = userIngredients.filter(item => item !== ingredient);
  updateIngredientTags();
}

function updateIngredientTags() {
  const tagsContainer = document.getElementById('ingredientTags');
  tagsContainer.innerHTML = '';
  
  if (userIngredients.length === 0) {
    tagsContainer.innerHTML = '<span style="color: #64748b; font-style: italic;">No ingredients added yet</span>';
    return;
  }
  
  userIngredients.forEach(ingredient => {
    const tag = document.createElement('span');
    tag.className = 'ingredient-tag';
    tag.innerHTML = `
      ${ingredient}
      <i class="fas fa-times" onclick="removeIngredient('${ingredient}')"></i>
    `;
    tagsContainer.appendChild(tag);
  });
}

// Find recipes based on ingredients - redirect to kitchen-finder.php
function findRecipes() {
  if (userIngredients.length === 0) {
    alert('Please add some ingredients first!');
    return;
  }
  
  // Redirect to kitchen-finder.php with ingredients
  const ingredientsString = userIngredients.join(',');
  window.location.href = `kitchen-finder.php?ingredients=${encodeURIComponent(ingredientsString)}`;
}

// Handle Enter key in ingredient input
document.addEventListener('DOMContentLoaded', function() {
  const ingredientInput = document.getElementById('ingredientInput');
  if (ingredientInput) {
    ingredientInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        addIngredient();
      }
    });
  }
});

// Form submission handler
const recipeForm = document.getElementById('recipeForm');
if (recipeForm) {
  recipeForm.addEventListener('submit', function(event) {
    const formData = new FormData(event.target);
    const recipeData = Object.fromEntries(formData);
    
    // Basic validation
    if (!recipeData.title || !recipeData.category || !recipeData.description || 
        !recipeData.ingredients || !recipeData.instructions) {
      event.preventDefault();
      alert('Please fill in all required fields marked with *');
      return;
    }
    
    const submitBtn = event.target.querySelector('.submit-btn');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    submitBtn.disabled = true;
    
    // Form will be submitted normally to upload-recipe.php
  });
}

// Enhanced form validation
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('recipeForm');
  if (form) {
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
      input.addEventListener('blur', function() {
        if (!this.value.trim()) {
          this.style.borderColor = '#ef4444';
        } else {
          this.style.borderColor = '#10b981';
        }
      });
      
      input.addEventListener('input', function() {
        if (this.value.trim()) {
          this.style.borderColor = '#10b981';
        }
      });
    });
  }
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

// Search functionality
document.querySelector('.search-box button').addEventListener('click', function(e) {
  const searchInput = document.querySelector('.search-box input[name="search"]');
  const searchTerm = searchInput.value.trim();
  
  if (!searchTerm) {
    e.preventDefault();
    searchInput.focus();
    searchInput.placeholder = 'Please enter a search term...';
    setTimeout(() => {
      searchInput.placeholder = 'Search recipes...';
    }, 2000);
  }
});

// Animation on scroll
function animateOnScroll() {
  const cards = document.querySelectorAll('.quick-pick-card, .card, .result-card');
  
  cards.forEach(card => {
    const cardTop = card.getBoundingClientRect().top;
    const cardBottom = card.getBoundingClientRect().bottom;
    
    if (cardTop < window.innerHeight && cardBottom > 0) {
      card.classList.add('animate-in');
    }
  });
}

window.addEventListener('scroll', animateOnScroll);
window.addEventListener('load', animateOnScroll);

// Add interactive animations
document.querySelectorAll('.btn').forEach(btn => {
  btn.addEventListener('mouseenter', function() {
    this.style.transform = 'translateY(-3px) scale(1.05)';
  });
  
  btn.addEventListener('mouseleave', function() {
    this.style.transform = 'translateY(0) scale(1)';
  });
});

// Category card hover effects
document.querySelectorAll('.card').forEach(card => {
  card.addEventListener('mouseenter', function() {
    this.style.transform = 'translateY(-10px) scale(1.02)';
  });
  
  card.addEventListener('mouseleave', function() {
    this.style.transform = 'translateY(0) scale(1)';
  });
});

// Enhanced ingredient suggestion interactions
document.querySelectorAll('.ingredient-suggestion').forEach(suggestion => {
  suggestion.addEventListener('click', function() {
    this.style.transform = 'scale(0.95)';
    setTimeout(() => {
      this.style.transform = 'scale(1)';
    }, 150);
  });
});

// Scroll to top functionality
function scrollToTop() {
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
}

// Show/hide scroll to top button
window.addEventListener('scroll', function() {
  const scrollButton = document.querySelector('.scroll-to-top');
  if (scrollButton) {
    if (window.pageYOffset > 300) {
      scrollButton.classList.add('show');
    } else {
      scrollButton.classList.remove('show');
    }
  }
});

// Initialize kitchen finder when page loads
document.addEventListener('DOMContentLoaded', function() {
  updateIngredientTags(); // Initialize empty state
});
</script>

<!-- Scroll to Top Button -->
<button class="scroll-to-top" onclick="scrollToTop()">
  <i class="fas fa-arrow-up"></i>
</button>

</body>
</html>
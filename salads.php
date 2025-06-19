<?php
// Start session and include authentication
session_start();
require_once 'config.php';
require_once 'auth_functions.php';

// Check if user is logged in
$isLoggedIn = isUserLoggedIn();
$currentUser = null;
if ($isLoggedIn) {
    $currentUser = getCurrentUser();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "cookistry_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set category to appetizer (change this for each category page)
$category = 'salads';
$categoryTitle = 'Fresh Salads';
$categoryDescription = 'Healthy and vibrant salad recipes with fresh ingredients and flavorful dressings';
// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'all';
$diet_filter = isset($_GET['diet']) ? $_GET['diet'] : 'all';
$time_filter = isset($_GET['time']) ? intval($_GET['time']) : 0;

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 9;
$offset = ($page - 1) * $per_page;

// Build SQL query
$sql = "SELECT * FROM recipes WHERE category = ? AND (status = 'published' OR status IS NULL OR status = '')";
$count_sql = "SELECT COUNT(*) as total FROM recipes WHERE category = ? AND (status = 'published' OR status IS NULL OR status = '')";
$params = [$category];
$types = "s";

// Add search condition
if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ? OR ingredients LIKE ?)";
    $count_sql .= " AND (title LIKE ? OR description LIKE ? OR ingredients LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

// Add difficulty filter
if ($difficulty_filter !== 'all') {
    $sql .= " AND difficulty = ?";
    $count_sql .= " AND difficulty = ?";
    $params[] = $difficulty_filter;
    $types .= "s";
}

// Add diet filter
if ($diet_filter !== 'all') {
    $sql .= " AND (tags LIKE ? OR dietary_info LIKE ?)";
    $count_sql .= " AND (tags LIKE ? OR dietary_info LIKE ?)";
    $diet_param = "%$diet_filter%";
    $params = array_merge($params, [$diet_param, $diet_param]);
    $types .= "ss";
}

// Add time filter
if ($time_filter > 0) {
    $sql .= " AND prep_time <= ?";
    $count_sql .= " AND prep_time <= ?";
    $params[] = $time_filter;
    $types .= "i";
}

// Add sorting
switch ($sort_by) {
    case 'rating':
        $sql .= " ORDER BY rating DESC, created_at DESC";
        break;
    case 'prep_time':
        $sql .= " ORDER BY prep_time ASC, created_at DESC";
        break;
    case 'title':
        $sql .= " ORDER BY title ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

// Get total count
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_recipes = $count_stmt->get_result()->fetch_assoc()['total'];

// Add pagination to main query
$sql .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate pagination
$total_pages = ceil($total_recipes / $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $categoryTitle; ?> - Cookistry</title>
    <link rel="icon" href="images/logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css" />
    
    <style>
        /* Override specific styles to match homepage */
        body {
            background: rgb(235, 242, 247);
        }

        /* Header Section - With Background Image */
        .category-hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.6)), 
                        url('images/<?php echo $category; ?>-bg.jpg') center center/cover;
            position: relative;
            overflow: hidden;
            padding: 6rem 0 4rem;
            margin-top: 0;
        }

        .category-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="grad1" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="rgba(255,255,255,0.1)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23grad1)"/><circle cx="800" cy="300" r="150" fill="url(%23grad1)"/><circle cx="300" cy="700" r="120" fill="url(%23grad1)"/></svg>') no-repeat center center;
            background-size: cover;
            opacity: 0.3;
        }

        .category-hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .category-hero h1 {
            font-size: 3.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .category-hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        /* Search Box - Matching Homepage Style */
        .search-box-category {
            max-width: 600px;
            margin: 0 auto;
        }

        .search-box-category form {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .search-box-category input {
            flex: 1;
            padding: 1.25rem 2rem;
            border: none;
            outline: none;
            font-size: 1.1rem;
            background: transparent;
            color: #2d3748;
        }

        .search-box-category input::placeholder {
            color: #a0aec0;
        }

        .search-box-category button {
            padding: 1.25rem 2.5rem;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .search-box-category button:hover {
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
            transform: scale(1.02);
        }

        /* Filter Section - Matching Homepage Colors */
        .filter-section {
            background: white;
            padding: 2rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border-bottom: 1px solid #e2e8f0;
        }

        .filter-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
            align-items: end;
            max-width: 800px;
            margin: 0 auto;
        }

        .filter-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            color: #374151;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #4ca1af;
            box-shadow: 0 0 0 3px rgba(76, 161, 175, 0.1);
        }

        /* Results Summary */
        .results-summary {
            background: #f8fafc;
            padding: 1.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .results-info {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-count {
            color: #4a5568;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .clear-filters {
            color: #4ca1af;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .clear-filters:hover {
            color: #2c3e50;
            text-decoration: underline;
        }

        /* Recipe Grid - Matching Homepage Card Style */
        .recipes-section {
            background: rgb(235, 242, 247);
            padding: 3rem 0;
        }

        .recipe-grid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .recipe-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid #f1f5f9;
        }

        .recipe-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }

        .recipe-image {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: #f8fafc;
        }

        .recipe-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .recipe-card:hover .recipe-image img {
            transform: scale(1.05);
        }

        .recipe-badges {
            position: absolute;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .difficulty-badge {
            background: rgba(255, 193, 7, 0.95);
            color: #000;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(5px);
        }

        .recipe-time {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            backdrop-filter: blur(5px);
        }

        .recipe-content {
            padding: 1.5rem;
        }

        .recipe-content h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.8rem;
            line-height: 1.4;
            min-height: 3.6rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .recipe-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .recipe-meta span {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .rating-stars {
            color: #fbbf24;
        }

        .recipe-content p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1rem;
            height: 3rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .recipe-tags {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
            min-height: 1.5rem;
        }

        .tag {
            background: #f1f5f9;
            color: #475569;
            padding: 0.2rem 0.6rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .btn-recipe {
            display: block;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 161, 175, 0.3);
        }

        .btn-recipe:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.4);
            color: white;
            text-decoration: none;
            background: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
        }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .no-results-icon {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 1.5rem;
        }

        .no-results h3 {
            font-size: 1.5rem;
            color: #1a202c;
            margin-bottom: 1rem;
        }

        .no-results p {
            color: #64748b;
            margin-bottom: 2rem;
        }

        .btn-primary {
            display: inline-block;
            background: linear-gradient(135deg, #4ca1af 0%, #2c3e50 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 161, 175, 0.4);
        }

        /* Pagination - Matching Homepage Style */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 3rem 0;
            flex-wrap: wrap;
            padding: 0 2rem;
        }

        .pagination a, .pagination span {
            padding: 0.75rem 1rem;
            text-decoration: none;
            border: 2px solid #e2e8f0;
            color: #4a5568;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-width: 45px;
            text-align: center;
            background: white;
        }

        .pagination a:hover {
            background: #4ca1af;
            color: white;
            border-color: #4ca1af;
            transform: translateY(-2px);
        }

        .pagination .active {
            background: #4ca1af;
            color: white;
            border-color: #4ca1af;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            color: #4a5568;
            text-decoration: none;
        }

        .user-menu a:hover {
            background: rgba(76, 161, 175, 0.1);
            padding-left: 1.2rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .category-hero {
                padding: 4rem 0 3rem;
            }

            .category-hero h1 {
                font-size: 2.5rem;
            }

            .category-hero p {
                font-size: 1.1rem;
            }

            .search-box-category form {
                flex-direction: column;
                border-radius: 15px;
            }

            .search-box-category button {
                border-radius: 0 0 15px 15px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .recipe-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 0 1rem;
            }

            .results-info {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .pagination {
                gap: 0.25rem;
                padding: 0 1rem;
            }

            .pagination a, .pagination span {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
                min-width: 40px;
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
        }

        /* Loading Animation */
        .recipe-card {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.6s ease forwards;
        }

        .recipe-card:nth-child(1) { animation-delay: 0.1s; }
        .recipe-card:nth-child(2) { animation-delay: 0.2s; }
        .recipe-card:nth-child(3) { animation-delay: 0.3s; }
        .recipe-card:nth-child(4) { animation-delay: 0.4s; }
        .recipe-card:nth-child(5) { animation-delay: 0.5s; }
        .recipe-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Header using same navbar from homepage with authentication -->
    <header>
        <div class="navbar">
            <img src="images/logo.png" alt="Cookistry Logo" class="logo" />
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li class="dropdown">
                        <a href="#" class="active">Categories <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-content">
                            <li><a href="appetizer.php" class="<?php echo $category === 'appetizer' ? 'active' : ''; ?>">Appetizer</a></li>
                            <li><a href="breakfast.php" class="<?php echo $category === 'breakfast' ? 'active' : ''; ?>">Breakfast</a></li>
                            <li><a href="lunch.php" class="<?php echo $category === 'lunch' ? 'active' : ''; ?>">Lunch</a></li>
                            <li><a href="dinner.php" class="<?php echo $category === 'dinner' ? 'active' : ''; ?>">Dinner</a></li>
                            <li><a href="dessert.php" class="<?php echo $category === 'dessert' ? 'active' : ''; ?>">Dessert</a></li>
                            <li><a href="bread-bakes.php" class="<?php echo $category === 'bread-bakes' ? 'active' : ''; ?>">Bread & Bakes</a></li>
                            <li><a href="salads.php" class="<?php echo $category === 'salads' ? 'active' : ''; ?>">Salads</a></li>
                            <li><a href="healthy.php" class="<?php echo $category === 'healthy' ? 'active' : ''; ?>">Healthy Food</a></li>
                            <li><a href="beverages.php" class="<?php echo $category === 'beverages' ? 'active' : ''; ?>">Beverages</a></li>
                            <li><a href="snacks.php" class="<?php echo $category === 'snacks' ? 'active' : ''; ?>">Snacks</a></li>
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

    <!-- Hero Section - Matching Homepage Style -->
    <section class="category-hero">
        <div class="category-hero-content">
            <h1><?php echo $categoryTitle; ?></h1>
            <p><?php echo $categoryDescription; ?></p>
            
            <div class="search-box-category">
                <form method="GET" action="">
                    <input type="text" name="search" placeholder="Search <?php echo strtolower($category); ?> recipes..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="filter-section">
        <div class="filter-container">
            <form method="GET" action="" class="filter-grid">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                
                <div class="filter-group">
                    <label for="sort">Sort By</label>
                    <select name="sort" id="sort" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                        <option value="prep_time" <?php echo $sort_by === 'prep_time' ? 'selected' : ''; ?>>Quick & Easy</option>
                        <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Alphabetical</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="difficulty">Difficulty</label>
                    <select name="difficulty" id="difficulty" onchange="this.form.submit()">
                        <option value="all" <?php echo $difficulty_filter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                        <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>Hard</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="diet">Dietary</label>
                    <select name="diet" id="diet" onchange="this.form.submit()">
                        <option value="all" <?php echo $diet_filter === 'all' ? 'selected' : ''; ?>>All Diets</option>
                        <option value="vegetarian" <?php echo $diet_filter === 'vegetarian' ? 'selected' : ''; ?>>Vegetarian</option>
                        <option value="vegan" <?php echo $diet_filter === 'vegan' ? 'selected' : ''; ?>>Vegan</option>
                        <option value="gluten-free" <?php echo $diet_filter === 'gluten-free' ? 'selected' : ''; ?>>Gluten Free</option>
                        <option value="keto" <?php echo $diet_filter === 'keto' ? 'selected' : ''; ?>>Keto</option>
                    </select>
                </div>
            </form>
        </div>
    </section>

    <!-- Results Summary -->
    <section class="results-summary">
        <div class="results-info">
            <div class="results-count">
                <i class="fas fa-utensils"></i>
                <span><?php echo $total_recipes; ?> <?php echo strtolower($category); ?> recipes found</span>
            </div>
            <?php if ($search || $difficulty_filter !== 'all' || $diet_filter !== 'all' || $time_filter > 0): ?>
                <a href="<?php echo $category; ?>.php" class="clear-filters">
                    <i class="fas fa-times-circle"></i> Clear All Filters
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Recipe Grid -->
    <section class="recipes-section">
        <div class="recipe-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($recipe = $result->fetch_assoc()): ?>
                    <article class="recipe-card">
                        <div class="recipe-image">
                            <img src="<?php echo !empty($recipe['image']) ? htmlspecialchars($recipe['image']) : 'images/default-recipe.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($recipe['title']); ?>" 
                                 loading="lazy">
                            <div class="recipe-badges">
                                <span class="difficulty-badge"><?php echo ucfirst(htmlspecialchars($recipe['difficulty'] ?? 'medium')); ?></span>
                                <div class="recipe-time">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo htmlspecialchars($recipe['prep_time'] ?? '30'); ?> min</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="recipe-content">
                            <h3><?php echo htmlspecialchars($recipe['title']); ?></h3>
                            
                            <div class="recipe-meta">
                                <span class="rating-stars">
                                    <?php 
                                    $rating = floatval($recipe['rating'] ?? 4);
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    }
                                    ?>
                                    <span style="color: #64748b; margin-left: 0.5rem;"><?php echo number_format($rating, 1); ?></span>
                                </span>
                                <span>
                                    <i class="fas fa-users"></i>
                                    <?php echo htmlspecialchars($recipe['servings'] ?? '4'); ?> servings
                                </span>
                            </div>
                            
                            <p><?php echo htmlspecialchars(substr($recipe['description'] ?? 'Delicious ' . $category . ' recipe perfect for any occasion.', 0, 100)) . '...'; ?></p>
                            
                            <div class="recipe-tags">
                                <?php 
                                $tags = !empty($recipe['tags']) ? explode(',', $recipe['tags']) : [$category];
                                foreach (array_slice($tags, 0, 3) as $tag): 
                                ?>
                                    <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <a href="recipe-detail.php?id=<?php echo $recipe['id']; ?>" class="btn-recipe">
                                <i class="fas fa-eye"></i> View Recipe
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No <?php echo strtolower($category); ?> recipes found</h3>
                    <p>Try adjusting your search terms or filters to find more recipes.</p>
                    <a href="<?php echo $category; ?>.php" class="btn-primary">Browse All <?php echo $categoryTitle; ?></a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
                if ($start_page > 2) echo '<span>...</span>';
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<span class="active">' . $i . '</span>';
                } else {
                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a>';
                }
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) echo '<span>...</span>';
                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a>';
            }
            ?>

            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <!-- Footer - Using Same Footer from Homepage -->
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
                        <li><a href="index.php">Home</a></li>
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
        // Form submission with loading state
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                this.style.opacity = '0.7';
                this.style.pointerEvents = 'none';
            });
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
        document.querySelector('.search-box-category button').addEventListener('click', function(e) {
            const searchInput = document.querySelector('.search-box-category input[name="search"]');
            const searchTerm = searchInput.value.trim();
            
            if (!searchTerm) {
                e.preventDefault();
                searchInput.focus();
                searchInput.placeholder = 'Please enter a search term...';
                setTimeout(() => {
                    searchInput.placeholder = 'Search <?php echo strtolower($category); ?> recipes...';
                }, 2000);
            }
        });

        // Card hover effects
        document.querySelectorAll('.recipe-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Loading animation for cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.recipe-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>

<?php
// Close connections
$stmt->close();
$count_stmt->close();
$conn->close();
?>
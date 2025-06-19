<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "cookistry_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'all';

// Build SQL query
$sql = "SELECT * FROM recipes WHERE status = 'published'";
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ? OR ingredients LIKE ? OR instructions LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

// Add category filter
if ($category_filter !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

// Add difficulty filter
if ($difficulty_filter !== 'all') {
    $sql .= " AND difficulty = ?";
    $params[] = $difficulty_filter;
    $types .= "s";
}

// Add sorting
switch ($sort_by) {
    case 'rating':
        $sql .= " ORDER BY rating DESC, created_at DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY created_at DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY created_at ASC";
        break;
    case 'title':
        $sql .= " ORDER BY title ASC";
        break;
    case 'views':
        $sql .= " ORDER BY views DESC";
        break;
    case 'relevance':
    default:
        if (!empty($search)) {
            $sql .= " ORDER BY (
                CASE 
                    WHEN title LIKE ? THEN 4
                    WHEN description LIKE ? THEN 3
                    WHEN ingredients LIKE ? THEN 2
                    WHEN instructions LIKE ? THEN 1
                    ELSE 0
                END
            ) DESC, rating DESC, views DESC";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            $types .= "ssss";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }
        break;
}

// Prepare and execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        die("Query preparation failed: " . $conn->error);
    }
} else {
    $result = $conn->query($sql);
}

// Get search statistics
$total_results = $result ? $result->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results<?php echo !empty($search) ? ' for "' . htmlspecialchars($search) . '"' : ''; ?> - Cookistry</title>
    
    <!-- Main stylesheet -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="images/logo.png" type="image/png">
    
    <!-- Google Fonts + Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .search-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 60px 20px 40px;
            position: relative;
        }
        
        .search-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .search-summary {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .filters-section {
            background: #f8f9fa;
            padding: 25px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .filters-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }
        
        .search-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 130px;
        }
        
        .results-info {
            color: #6c757d;
            font-weight: 500;
        }
        
        .search-results {
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .result-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .result-image-container {
            position: relative;
            height: 220px;
            overflow: hidden;
        }
        
        .result-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .result-card:hover .result-image {
            transform: scale(1.05);
        }
        
        .result-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .category-badge, .difficulty-badge {
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .difficulty-badge {
            background: rgba(255,193,7,0.9);
            color: #000;
        }
        
        .result-content {
            padding: 25px;
        }
        
        .result-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        
        .result-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .result-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        .result-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #ffc107;
        }
        
        .result-stats {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .btn-view-result {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-view-result:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .no-results {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
        }
        
        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 25px;
            opacity: 0.5;
        }
        
        .no-results h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #495057;
        }
        
        .search-suggestions {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .search-suggestions h4 {
            margin-bottom: 15px;
            color: #495057;
        }
        
        .search-suggestions ul {
            list-style: none;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-suggestions li a {
            background: white;
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            color: #667eea;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .search-suggestions li a:hover {
            background: #667eea;
            color: white;
        }
        
        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .search-hero h1 {
                font-size: 2rem;
            }
            
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-filters {
                justify-content: center;
            }
            
            .results-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- ===== HEADER / NAVBAR ===== -->
    <header>
        <div class="navbar">
            <!-- logo left -->
            <img src="images/logo.png" alt="Cookistry Logo" class="logo" />

            <!-- links right -->
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li class="dropdown">
                        <a href="#">Categories <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown-content">
                            <li><a href="category.php?category=appetizer">Appetizer</a></li>
                            <li><a href="category.php?category=breakfast">Breakfast</a></li>
                            <li><a href="category.php?category=lunch">Lunch</a></li>
                            <li><a href="category.php?category=dinner">Dinner</a></li>
                            <li><a href="category.php?category=dessert">Dessert</a></li>
                        </ul>
                    </li>
                    <li><a href="all-recipes.php">All Recipes</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="login.html">Login</a></li>
                    <li><a href="signup.html">Signup</a></li>
                </ul>
            </nav>
        </div>

        <!-- search bar -->
        <div class="search-box">
            <form action="search.php" method="GET">
                <input type="text" name="search" placeholder="Search recipes, ingredients..." value="<?php echo htmlspecialchars($search); ?>" />
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>
    </header>

    <!-- ===== SEARCH HERO ===== -->
    <section class="search-hero">
        <div class="search-hero-content">
            <?php if (!empty($search)): ?>
                <h1>Search Results for "<?php echo htmlspecialchars($search); ?>"</h1>
                <p class="search-summary">Found <?php echo $total_results; ?> recipe<?php echo $total_results !== 1 ? 's' : ''; ?> matching your search</p>
            <?php else: ?>
                <h1>All Recipes</h1>
                <p class="search-summary">Browse our complete collection of <?php echo $total_results; ?> recipes</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===== FILTERS SECTION ===== -->
    <section class="filters-section">
        <div class="filters-container">
            <div class="search-filters">
                <form method="GET" action="" id="filterForm">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    
                    <div class="filter-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" onchange="document.getElementById('filterForm').submit();">
                            <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <option value="appetizer" <?php echo $category_filter === 'appetizer' ? 'selected' : ''; ?>>Appetizer</option>
                            <option value="breakfast" <?php echo $category_filter === 'breakfast' ? 'selected' : ''; ?>>Breakfast</option>
                            <option value="lunch" <?php echo $category_filter === 'lunch' ? 'selected' : ''; ?>>Lunch</option>
                            <option value="dinner" <?php echo $category_filter === 'dinner' ? 'selected' : ''; ?>>Dinner</option>
                            <option value="dessert" <?php echo $category_filter === 'dessert' ? 'selected' : ''; ?>>Dessert</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort">Sort By</label>
                        <select id="sort" name="sort" onchange="document.getElementById('filterForm').submit();">
                            <option value="relevance" <?php echo $sort_by === 'relevance' ? 'selected' : ''; ?>>Relevance</option>
                            <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                            <option value="views" <?php echo $sort_by === 'views' ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Alphabetical</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="difficulty">Difficulty</label>
                        <select id="difficulty" name="difficulty" onchange="document.getElementById('filterForm').submit();">
                            <option value="all" <?php echo $difficulty_filter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                            <option value="Easy" <?php echo $difficulty_filter === 'Easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="Medium" <?php echo $difficulty_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Hard" <?php echo $difficulty_filter === 'Hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <div class="results-info">
                <i class="fas fa-info-circle"></i>
                Showing <?php echo $total_results; ?> results
            </div>
        </div>
    </section>

    <!-- ===== SEARCH RESULTS ===== -->
    <section class="search-results">
        <?php if ($result && $total_results > 0): ?>
            <div class="results-grid">
                <?php 
                // Function to highlight search terms
                function highlightSearchTerm($text, $search) {
                    if (empty($search)) return $text;
                    return preg_replace('/(' . preg_quote($search, '/') . ')/i', '<span class="highlight">$1</span>', $text);
                }
                
                while($row = $result->fetch_assoc()): ?>
                    <div class='result-card'>
                        <!-- Recipe Image -->
                        <div class='result-image-container'>
                            <?php if(!empty($row['image'])): ?>
                                <?php 
                                $imagePath = "uploads/" . $row['image'];
                                if(file_exists($imagePath)): ?>
                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" class="result-image" loading="lazy">
                                <?php else: ?>
                                    <img src="images/recipe-placeholder.jpg" alt="Recipe placeholder" class="result-image" loading="lazy">
                                <?php endif; ?>
                            <?php else: ?>
                                <img src="images/recipe-placeholder.jpg" alt="Recipe placeholder" class="result-image" loading="lazy">
                            <?php endif; ?>
                            
                            <!-- Recipe badges -->
                            <div class='result-badges'>
                                <?php if(!empty($row['category'])): ?>
                                    <span class='category-badge'><?php echo htmlspecialchars($row['category']); ?></span>
                                <?php endif; ?>
                                <?php if(!empty($row['difficulty'])): ?>
                                    <span class='difficulty-badge'><?php echo htmlspecialchars($row['difficulty']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recipe Content -->
                        <div class='result-content'>
                            <h3 class='result-title'><?php echo highlightSearchTerm(htmlspecialchars($row['title']), $search); ?></h3>
                            
                            <?php
                            // Show description with length limit and highlight
                            $description = htmlspecialchars($row['description']);
                            if(strlen($description) > 150) {
                                $description = substr($description, 0, 150) . "...";
                            }
                            echo "<p class='result-description'>" . highlightSearchTerm($description, $search) . "</p>";
                            ?>
                            
                            <!-- Recipe meta info -->
                            <div class='result-meta'>
                                <!-- Rating -->
                                <?php if(!empty($row['rating']) && $row['rating'] > 0): ?>
                                    <div class='result-rating'>
                                        <i class='fas fa-star'></i>
                                        <span><?php echo number_format($row['rating'], 1); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class='result-rating'>
                                        <span class='text-muted'>No rating yet</span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Stats -->
                                <div class='result-stats'>
                                    <?php if(!empty($row['prep_time'])): ?>
                                        <span><i class='fas fa-clock'></i> <?php echo htmlspecialchars($row['prep_time']); ?>m</span>
                                    <?php endif; ?>
                                    <?php if(!empty($row['views'])): ?>
                                        <span><i class='fas fa-eye'></i> <?php echo number_format($row['views']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Recipe action -->
                            <a href='recipe_detail.php?id=<?php echo $row['id']; ?>' class='btn-view-result'>
                                <i class='fas fa-eye'></i> View Recipe
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search no-results-icon"></i>
                <h3>No recipes found</h3>
                <p>Sorry, we couldn't find any recipes matching your search criteria.</p>
                
                <div class="search-suggestions">
                    <h4>Try searching for:</h4>
                    <ul>
                        <li><a href="search.php?search=chicken">Chicken</a></li>
                        <li><a href="search.php?search=pasta">Pasta</a></li>
                        <li><a href="search.php?search=salad">Salad</a></li>
                        <li><a href="search.php?search=soup">Soup</a></li>
                        <li><a href="search.php?search=dessert">Dessert</a></li>
                        <li><a href="search.php?search=vegetarian">Vegetarian</a></li>
                    </ul>
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="all-recipes.php" class="btn btn-outline">Browse All Recipes</a>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="enhanced-footer">
        <div class="footer-grid">
            <div class="footer-section">
                <h3>About Cookistry</h3>
                <p>Cookistry is your gateway to creative cooking. Whether you're a beginner or a seasoned chef, we help you discover exciting recipes, cooking tips, and kitchen inspirations to make every meal memorable.</p>
                <div class="social-links">
                    <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Recipe Categories</h3>
                <ul>
                    <li><a href="category.php?Appetizer">Appetizers</a></li>
                    <li><a href="category.php?Breakfast">Breakfast</a></li>
                    <li><a href="category.php?Lunch">Lunch</a></li>
                    <li><a href="category.php?Dinner">Dinner</a></li>
                    <li><a href="category.php?Dessert">Desserts</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Contact Us</h3>
                <div class="contact-info">
                    <p><i class="fas fa-envelope"></i> support@cookistry.com</p>
                    <p><i class="fas fa-phone"></i> +880-1234-567890</p>
                    <p><i class="fas fa-map-marker-alt"></i> Chittagong, Bangladesh</p>
                </div>
            </div>

            <div class="footer-section">
                <h3>Newsletter</h3>
                <p>Subscribe to get the latest recipes and cooking tips!</p>
                <form class="newsletter-form" action="subscribe.php" method="POST">
                    <input type="email" name="email" placeholder="Your email address" required>
                    <button type="submit"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <p>&copy; 2025 Cookistry. All rights reserved. Made with <i class="fas fa-heart"></i> in Bangladesh</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to top button -->
    <button id="scrollToTop" class="scroll-to-top" aria-label="Scroll to top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <script>
        // Scroll to top functionality
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

        // Add loading animation to result cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.result-card').forEach(card => {
            observer.observe(card);
        });
    </script>

</body>
</html>

<?php
// Close database connection
if (isset($stmt)) $stmt->close();
$conn->close();
?>
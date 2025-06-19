<?php
session_start();
require_once 'config.php';

$success_message = '';
$error_message = '';
$import_stats = [];

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $import_result = processImprovedCSV($_FILES['csv_file']);
    
    if ($import_result['success']) {
        $success_message = "Import completed successfully!";
        $import_stats = $import_result['stats'];
    } else {
        $error_message = $import_result['error'];
    }
}

function processImprovedCSV($file) {
    global $pdo;
    
    $stats = [
        'total_rows' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error: ' . $file['error']];
    }
    
    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        return ['success' => false, 'error' => 'File too large. Maximum 10MB allowed.'];
    }
    
    // Check file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_extension !== 'csv') {
        return ['success' => false, 'error' => 'Please upload a valid CSV file.'];
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        return ['success' => false, 'error' => 'Could not read CSV file'];
    }
    
    // Read and clean header row
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return ['success' => false, 'error' => 'Invalid CSV format - no headers found'];
    }
    
    // Clean headers (remove BOM, quotes, extra spaces)
    $headers = array_map(function($header) {
        // Remove BOM if present
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        // Remove quotes and trim
        return trim($header, '"\'` ');
    }, $headers);
    
    // Expected headers mapping - exactly matching your database
    $expected_headers = [
        'id' => 'id',
        'title' => 'title', 
        'slug' => 'slug',
        'image' => 'image',
        'video_url' => 'video_url',
        'video_file' => 'video_file', 
        'description' => 'description',
        'ingredients' => 'ingredients',
        'instructions' => 'instructions',
        'tags' => 'tags',
        'prep_time' => 'prep_time',
        'cook_time' => 'cook_time', 
        'servings' => 'servings',
        'difficulty' => 'difficulty',
        'rating' => 'rating',
        'rating_count' => 'rating_count',
        'category' => 'category',
        'status' => 'status',
        'views' => 'views', 
        'likes' => 'likes',
        'author_id' => 'author_id',
        'featured' => 'featured',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at'
    ];
    
    // Map CSV headers to database columns
    $header_map = [];
    foreach ($headers as $index => $header) {
        $clean_header = strtolower(trim($header));
        if (isset($expected_headers[$clean_header])) {
            $header_map[$index] = $expected_headers[$clean_header];
        }
    }
    
    if (empty($header_map)) {
        fclose($handle);
        return ['success' => false, 'error' => 'No valid headers found in CSV. Expected headers: ' . implode(', ', array_keys($expected_headers))];
    }
    
    try {
        $pdo->beginTransaction();
        
        $row_number = 1; // Start from 1 (header is row 0)
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            $stats['total_rows']++;
            
            // Skip empty rows
            if (empty(array_filter($row, function($cell) { return trim($cell) !== ''; }))) {
                $stats['skipped']++;
                continue;
            }
            
            // Clean row data (remove BOM, quotes, etc.)
            $row = array_map(function($cell) {
                if ($cell === null) return '';
                // Remove BOM if present
                $cell = str_replace("\xEF\xBB\xBF", '', $cell);
                // Remove surrounding quotes and trim
                return trim($cell, '"\'` ');
            }, $row);
            
            // Map row data to columns
            $recipe_data = [];
            foreach ($header_map as $csv_index => $db_column) {
                $recipe_data[$db_column] = isset($row[$csv_index]) ? $row[$csv_index] : '';
            }
            
            // Validate required fields
            if (empty($recipe_data['title'])) {
                $stats['errors'][] = "Row $row_number: Missing required field 'title'";
                $stats['skipped']++;
                continue;
            }
            
            if (empty($recipe_data['category'])) {
                $stats['errors'][] = "Row $row_number: Missing required field 'category'";
                $stats['skipped']++;
                continue;
            }
            
            // Clean and validate data
            $id = !empty($recipe_data['id']) ? (int)$recipe_data['id'] : null;
            $title = sanitizeText($recipe_data['title']);
            $slug = !empty($recipe_data['slug']) ? sanitizeSlug($recipe_data['slug']) : generateUniqueSlug($title, $pdo);
            $image = sanitizeText($recipe_data['image'] ?? 'default-recipe.jpg');
            $video_url = sanitizeUrl($recipe_data['video_url'] ?? '');
            $video_file = sanitizeText($recipe_data['video_file'] ?? '');
            $description = sanitizeText($recipe_data['description'] ?? '');
            $ingredients = processIngredientsList($recipe_data['ingredients'] ?? '');
            $instructions = processInstructionsList($recipe_data['instructions'] ?? '');
            $tags = sanitizeText($recipe_data['tags'] ?? '');
            $prep_time = sanitizeText($recipe_data['prep_time'] ?? '');
            $cook_time = sanitizeText($recipe_data['cook_time'] ?? '');
            $servings = !empty($recipe_data['servings']) ? max(1, (int)$recipe_data['servings']) : 4;
            $difficulty = validateDifficulty($recipe_data['difficulty'] ?? 'Medium');
            $rating = validateRating($recipe_data['rating'] ?? 4.0);
            $rating_count = !empty($recipe_data['rating_count']) ? max(0, (int)$recipe_data['rating_count']) : 0;
            $category = validateCategory($recipe_data['category']);
            $status = validateStatus($recipe_data['status'] ?? 'published');
            $views = !empty($recipe_data['views']) ? max(0, (int)$recipe_data['views']) : 0;
            $likes = !empty($recipe_data['likes']) ? max(0, (int)$recipe_data['likes']) : 0;
            $author_id = !empty($recipe_data['author_id']) ? (int)$recipe_data['author_id'] : null;
            $featured = validateFeatured($recipe_data['featured'] ?? '0');
            $created_at = validateDateTime($recipe_data['created_at'] ?? '');
            $updated_at = validateDateTime($recipe_data['updated_at'] ?? '');

            // Validate category
            if (!$category) {
                $stats['errors'][] = "Row $row_number: Invalid category '" . $recipe_data['category'] . "'";
                $stats['skipped']++;
                continue;
            }
            
            // Check if record exists (for update vs insert)
            $existing_recipe = null;
            if ($id) {
                $stmt = $pdo->prepare("SELECT id FROM recipes WHERE id = ?");
                $stmt->execute([$id]);
                $existing_recipe = $stmt->fetch();
            }
            
            if ($existing_recipe) {
                // Update existing recipe
                $stmt = $pdo->prepare("
                    UPDATE recipes SET
                        title = ?, slug = ?, image = ?, video_url = ?, video_file = ?,
                        description = ?, ingredients = ?, instructions = ?, tags = ?,
                        prep_time = ?, cook_time = ?, servings = ?, difficulty = ?,
                        rating = ?, rating_count = ?, category = ?, status = ?,
                        views = ?, likes = ?, author_id = ?, featured = ?,
                        updated_at = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([
                    $title, $slug, $image, $video_url, $video_file, $description,
                    $ingredients, $instructions, $tags, $prep_time, $cook_time,
                    $servings, $difficulty, $rating, $rating_count, $category,
                    $status, $views, $likes, $author_id, $featured, $updated_at, $id
                ])) {
                    $stats['updated']++;
                } else {
                    $stats['errors'][] = "Row $row_number: Database error updating '$title'";
                    $stats['skipped']++;
                }
            } else {
                // Insert new recipe
                $stmt = $pdo->prepare("
                    INSERT INTO recipes (
                        id, title, slug, image, video_url, video_file, description,
                        ingredients, instructions, tags, prep_time, cook_time,
                        servings, difficulty, rating, rating_count, category,
                        status, views, likes, author_id, featured, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");
                
                if ($stmt->execute([
                    $id, $title, $slug, $image, $video_url, $video_file, $description,
                    $ingredients, $instructions, $tags, $prep_time, $cook_time,
                    $servings, $difficulty, $rating, $rating_count, $category,
                    $status, $views, $likes, $author_id, $featured, $created_at, $updated_at
                ])) {
                    $stats['imported']++;
                } else {
                    $stats['errors'][] = "Row $row_number: Database error inserting '$title'";
                    $stats['skipped']++;
                }
            }
        }
        
        $pdo->commit();
        fclose($handle);
        
        return [
            'success' => true,
            'stats' => $stats
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        fclose($handle);
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    }
}

// Helper functions
function sanitizeText($text) {
    if (empty($text)) return '';
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

function sanitizeSlug($slug) {
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function sanitizeUrl($url) {
    if (empty($url)) return '';
    return filter_var(trim($url), FILTER_SANITIZE_URL);
}

function processIngredientsList($ingredients_raw) {
    if (empty($ingredients_raw)) return '';
    
    // Split by || and clean each ingredient
    $ingredients_array = explode('||', $ingredients_raw);
    $cleaned_ingredients = [];
    
    foreach ($ingredients_array as $ingredient) {
        $ingredient = trim($ingredient);
        if (!empty($ingredient)) {
            $cleaned_ingredients[] = sanitizeText($ingredient);
        }
    }
    
    // Join with newlines for database storage
    return implode("\n", $cleaned_ingredients);
}

function processInstructionsList($instructions_raw) {
    if (empty($instructions_raw)) return '';
    
    // Split by || and clean each instruction
    $instructions_array = explode('||', $instructions_raw);
    $cleaned_instructions = [];
    $step_number = 1;
    
    foreach ($instructions_array as $instruction) {
        $instruction = trim($instruction);
        if (!empty($instruction)) {
            // Add step number if not already present
            if (!preg_match('/^\d+\./', $instruction)) {
                $instruction = $step_number . '. ' . $instruction;
            }
            $cleaned_instructions[] = sanitizeText($instruction);
            $step_number++;
        }
    }
    
    // Join with newlines for database storage
    return implode("\n", $cleaned_instructions);
}

function validateDifficulty($difficulty) {
    $valid_difficulties = ['Easy', 'Medium', 'Hard'];
    $difficulty = ucfirst(strtolower(trim($difficulty)));
    return in_array($difficulty, $valid_difficulties) ? $difficulty : 'Medium';
}

function validateRating($rating) {
    $rating = (float)$rating;
    return max(1.0, min(5.0, $rating));
}

function validateCategory($category) {
    $valid_categories = ['appetizer', 'breakfast', 'lunch', 'dinner', 'dessert', 'bread-bakes', 'salads', 'healthy', 'beverages', 'snacks'];
    $category = strtolower(trim($category));
    return in_array($category, $valid_categories) ? $category : null;
}

function validateStatus($status) {
    $valid_statuses = ['published', 'draft', 'archived'];
    $status = strtolower(trim($status));
    return in_array($status, $valid_statuses) ? $status : 'published';
}

function validateFeatured($featured) {
    $featured = strtolower(trim($featured));
    return in_array($featured, ['1', 'yes', 'true', 'on']) ? 1 : 0;
}

function validateDateTime($datetime) {
    if (empty($datetime)) {
        return date('Y-m-d H:i:s');
    }
    
    // Try to parse the datetime
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return date('Y-m-d H:i:s');
    }
    
    return date('Y-m-d H:i:s', $timestamp);
}

function generateUniqueSlug($title, $pdo) {
    $slug = sanitizeSlug($title);
    if (empty($slug)) {
        $slug = 'recipe-' . uniqid();
    }
    
    // Check if slug exists
    $original_slug = $slug;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE slug = ?");
        $stmt->execute([$slug]);
        
        if ($stmt->fetchColumn() == 0) {
            break; // Slug is unique
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
    <title>Improved Recipe CSV Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .import-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .step-card { margin-bottom: 25px; border-left: 4px solid #007bff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .csv-preview { 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            padding: 15px; 
            font-family: 'Courier New', monospace; 
            font-size: 11px; 
            overflow-x: auto;
            white-space: pre;
            line-height: 1.4;
        }
        .template-download { 
            background: linear-gradient(135deg, #28a745, #20c997); 
            border: none;
            transition: all 0.3s;
        }
        .template-download:hover {
            background: linear-gradient(135deg, #218838, #1a9f7f);
            transform: translateY(-2px);
        }
        .upload-zone { 
            border: 3px dashed #007bff; 
            padding: 40px; 
            text-align: center; 
            border-radius: 10px; 
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-zone:hover { 
            background: #f8f9fa; 
            border-color: #0056b3; 
            transform: translateY(-2px);
        }
        .upload-zone.dragover {
            background: #e3f2fd;
            border-color: #1976d2;
        }
        .field-desc { 
            background: #fff3cd; 
            padding: 10px; 
            border-radius: 5px; 
            margin: 5px 0; 
            border-left: 4px solid #ffc107;
        }
        .error-list {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .database-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</head>
<body class="bg-light">
    <div class="import-container">
        <div class="text-center mb-5">
            <h1 class="display-4 text-primary"><i class="fas fa-database"></i> Improved Recipe CSV Import</h1>
            <p class="lead">Import recipes with enhanced validation and error handling</p>
            
            <div class="database-info">
                <h5><i class="fas fa-info-circle"></i> Database Status</h5>
                <?php
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM recipes WHERE status = 'published'");
                    $recipe_count = $stmt->fetch()['total'];
                    echo "<div class='alert alert-success mb-2'><strong>✅ Database Connected:</strong> $recipe_count published recipes found</div>";
                    
                    // Check table structure
                    $stmt = $pdo->query("DESCRIBE recipes");
                    $columns = $stmt->fetchAll();
                    echo "<small class='text-muted'>Table has " . count($columns) . " columns</small>";
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'><strong>❌ Database Error:</strong> " . $e->getMessage() . "</div>";
                }
                ?>
            </div>
        </div>

        <!-- Step 1: Download Template -->
        <div class="card step-card">
            <div class="card-header bg-success text-white">
                <h3><i class="fas fa-download"></i> Step 1: Download CSV Template</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <button class="btn btn-success btn-lg w-100 template-download" onclick="downloadTemplate()">
                            <i class="fas fa-file-csv"></i> Download Template CSV
                        </button>
                        <small class="text-muted d-block mt-2">Template matches your exact database structure</small>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Template Features:</h6>
                            <ul class="mb-0">
                                <li>All 24 database fields included</li>
                                <li>Sample data for reference</li>
                                <li>Proper formatting for ingredients (|| separator)</li>
                                <li>Valid category and difficulty examples</li>
                                <li>Matches your database schema exactly</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Field Guidelines -->
        <div class="card step-card">
            <div class="card-header bg-info text-white">
                <h3><i class="fas fa-list-check"></i> Step 2: CSV Field Guidelines</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Required Fields</h5>
                        <div class="field-desc">
                            <strong>title:</strong> Recipe name (e.g., "Chocolate Chip Cookies")
                        </div>
                        <div class="field-desc">
                            <strong>category:</strong> Must be one of: appetizer, breakfast, lunch, dinner, dessert, snack, beverage, main-course
                        </div>
                        
                        <h5 class="mt-4">Special Format Fields</h5>
                        <div class="field-desc">
                            <strong>ingredients:</strong> Separate each ingredient with ||<br>
                            <code>2 cups flour||1 cup sugar||2 eggs</code>
                        </div>
                        <div class="field-desc">
                            <strong>instructions:</strong> Separate each step with ||<br>
                            <code>Preheat oven to 350°F||Mix dry ingredients||Bake for 20 minutes</code>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Validation Rules</h5>
                        <div class="field-desc">
                            <strong>difficulty:</strong> Easy, Medium, or Hard (default: Medium)
                        </div>
                        <div class="field-desc">
                            <strong>rating:</strong> Number between 1.0 and 5.0 (default: 4.0)
                        </div>
                        <div class="field-desc">
                            <strong>servings:</strong> Positive integer (default: 4)
                        </div>
                        <div class="field-desc">
                            <strong>status:</strong> published, draft, or archived (default: published)
                        </div>
                        <div class="field-desc">
                            <strong>featured:</strong> 1, yes, true, on = featured; anything else = not featured
                        </div>
                        <div class="field-desc">
                            <strong>slug:</strong> Auto-generated from title if empty
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Upload Form -->
        <div class="card step-card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-cloud-upload-alt"></i> Step 3: Upload Your CSV File</h3>
            </div>
            <div class="card-body">
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <div class="upload-zone" onclick="document.getElementById('csvFile').click()">
                        <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display: none;" onchange="handleFileSelect(this)">
                        <div id="upload-content">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <h4>Click to select your CSV file</h4>
                            <p class="text-muted">or drag and drop your file here</p>
                            <small class="text-muted">Supports CSV files up to 10MB</small>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="import" id="importBtn" class="btn btn-primary btn-lg" disabled>
                            <i class="fas fa-rocket"></i> Import Recipes
                        </button>
                    </div>
                </form>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success mt-4">
                        <h5><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></h5>
                        <div class="stats-grid">
                            <div class="stats-card border-primary">
                                <div class="stats-number text-primary"><?php echo $import_stats['total_rows']; ?></div>
                                <div class="stats-label">Total Rows</div>
                            </div>
                            <div class="stats-card border-success">
                                <div class="stats-number text-success"><?php echo $import_stats['imported']; ?></div>
                                <div class="stats-label">New Recipes</div>
                            </div>
                            <div class="stats-card border-info">
                                <div class="stats-number text-info"><?php echo $import_stats['updated']; ?></div>
                                <div class="stats-label">Updated</div>
                            </div>
                            <div class="stats-card border-warning">
                                <div class="stats-number text-warning"><?php echo $import_stats['skipped']; ?></div>
                                <div class="stats-label">Skipped</div>
                            </div>
                        </div>
                        
                        <?php if (!empty($import_stats['errors'])): ?>
                            <details class="mt-3">
                                <summary class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-exclamation-triangle"></i> View Errors (<?php echo count($import_stats['errors']); ?>)
                                </summary>
                                <div class="error-list mt-2">
                                    <?php foreach ($import_stats['errors'] as $error): ?>
                                        <div class="text-danger"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger mt-4">
                        <h5><i class="fas fa-exclamation-circle"></i> Import Error</h5>
                        <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function downloadTemplate() {
            const csvContent = `id,title,slug,image,video_url,video_file,description,ingredients,instructions,tags,prep_time,cook_time,servings,difficulty,rating,rating_count,category,status,views,likes,author_id,featured,created_at,updated_at
1,"Chocolate Chip Cookies",chocolate-chip-cookies,images/cookies.jpg,https://youtube.com/watch?v=example,,Classic homemade cookies,"2 cups all-purpose flour||1 cup brown sugar||1/2 cup butter||2 eggs||1 tsp vanilla||1 cup chocolate chips","Preheat oven to 375°F||Mix dry ingredients||Cream butter and sugar||Add eggs and vanilla||Combine wet and dry ingredients||Fold in chocolate chips||Bake for 10-12 minutes",dessert,20 mins,12 mins,24,Easy,4.8,15,dessert,published,150,25,,1,2025-01-01 10:00:00,2025-01-01 10:00:00
2,"Caesar Salad",caesar-salad,images/caesar.jpg,,,Fresh Caesar salad with croutons,"1 head romaine lettuce||1/4 cup Caesar dressing||1/4 cup Parmesan cheese||1 cup croutons","Wash and chop romaine lettuce||Toss with Caesar dressing||Top with Parmesan and croutons||Serve immediately",salad,15 mins,0 mins,4,Easy,4.5,8,lunch,published,95,12,,0,2025-01-01 11:00:00,2025-01-01 11:00:00`;

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'recipe-import-template.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                document.getElementById('upload-content').innerHTML = `
                    <i class="fas fa-file-csv fa-3x text-success mb-3"></i>
                    <h4>File Selected: ${file.name}</h4>
                    <p class="text-success">Size: ${fileSize} MB</p>
                    <small class="text-success">Ready to import!</small>
                `;
                document.getElementById('importBtn').disabled = false;
            }
        }

        // Drag and drop functionality
        const uploadZone = document.querySelector('.upload-zone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadZone.classList.add('dragover');
        }

        function unhighlight(e) {
            uploadZone.classList.remove('dragover');
        }

        uploadZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                document.getElementById('csvFile').files = files;
                handleFileSelect(document.getElementById('csvFile'));
            }
        }

        // Form submission with loading state
        document.getElementById('uploadForm').addEventListener('submit', function() {
            const btn = document.getElementById('importBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
        });
    </script>
</body>
</html>
```
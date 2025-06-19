<?php
session_start();
require_once 'config.php';

$success_message = '';
$error_message = '';
$import_stats = [];
$duplicate_report = [];

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $import_result = processAdvancedCSV($_FILES['csv_file']);
    
    if ($import_result['success']) {
        $success_message = "Import completed successfully!";
        $import_stats = $import_result['stats'];
        $duplicate_report = $import_result['duplicates'];
    } else {
        $error_message = $import_result['error'];
    }
}

function processAdvancedCSV($file) {
    global $pdo;
    
    $stats = [
        'total_rows' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'duplicates_found' => 0,
        'duplicates_handled' => 0,
        'errors' => []
    ];
    
    $duplicates = [];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error: ' . $file['error']];
    }
    
    if ($file['size'] > 15 * 1024 * 1024) { // 15MB limit for large files
        return ['success' => false, 'error' => 'File too large. Maximum 15MB allowed.'];
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
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        return trim($header, '"\'` ');
    }, $headers);
    
    // Expected headers mapping
    $expected_headers = [
        'id' => 'id',
        'title' => 'title', 
        'slug' => 'slug',
        'image' => 'image',
        'video_url' => 'video_url',
        'video_file' => 'video_file', 
        'video_path' => 'video_path',
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
    
    // First pass: collect all titles and detect duplicates within CSV
    $csv_titles = [];
    $csv_data = [];
    $row_number = 1;
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        $row_number++;
        
        // Skip empty rows
        if (empty(array_filter($row, function($cell) { return trim($cell) !== ''; }))) {
            continue;
        }
        
        // Clean row data
        $row = array_map(function($cell) {
            if ($cell === null) return '';
            $cell = str_replace("\xEF\xBB\xBF", '', $cell);
            return trim($cell, '"\'` ');
        }, $row);
        
        // Map row data to columns
        $recipe_data = [];
        foreach ($header_map as $csv_index => $db_column) {
            $recipe_data[$db_column] = isset($row[$csv_index]) ? $row[$csv_index] : '';
        }
        
        $title = sanitizeText($recipe_data['title'] ?? '');
        
        if (!empty($title)) {
            $title_lower = strtolower($title);
            
            // Check for duplicates within CSV
            if (isset($csv_titles[$title_lower])) {
                $duplicates[] = [
                    'type' => 'within_csv',
                    'title' => $title,
                    'first_row' => $csv_titles[$title_lower],
                    'duplicate_row' => $row_number,
                    'action' => 'skipped'
                ];
                $stats['duplicates_found']++;
                continue;
            }
            
            $csv_titles[$title_lower] = $row_number;
            $csv_data[] = [
                'row_number' => $row_number,
                'data' => $recipe_data
            ];
        }
    }
    
    fclose($handle);
    
    try {
        $pdo->beginTransaction();
        
        // Get existing titles from database for duplicate checking
        $existing_titles = [];
        $stmt = $pdo->query("SELECT LOWER(title) as title_lower, id FROM recipes");
        while ($row = $stmt->fetch()) {
            $existing_titles[$row['title_lower']] = $row['id'];
        }
        
        // Process CSV data
        foreach ($csv_data as $csv_row) {
            $row_number = $csv_row['row_number'];
            $recipe_data = $csv_row['data'];
            $stats['total_rows']++;
            
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
            $title_lower = strtolower($title);
            
            // Check for duplicate in database
            if (isset($existing_titles[$title_lower])) {
                $duplicate_action = $_POST['duplicate_action'] ?? 'skip';
                
                $duplicates[] = [
                    'type' => 'in_database',
                    'title' => $title,
                    'row' => $row_number,
                    'existing_id' => $existing_titles[$title_lower],
                    'action' => $duplicate_action
                ];
                
                if ($duplicate_action === 'skip') {
                    $stats['duplicates_handled']++;
                    $stats['skipped']++;
                    continue;
                } elseif ($duplicate_action === 'update') {
                    // Update existing recipe
                    $id = $existing_titles[$title_lower];
                }
            }
            
            $slug = !empty($recipe_data['slug']) ? sanitizeSlug($recipe_data['slug']) : generateUniqueSlug($title, $pdo, $id);
            $image = sanitizeText($recipe_data['image'] ?? 'default-recipe.jpg');
            $video_url = sanitizeUrl($recipe_data['video_url'] ?? '');
            $video_file = sanitizeText($recipe_data['video_file'] ?? '');
            $video_path = sanitizeText($recipe_data['video_path'] ?? '');
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
                        title = ?, slug = ?, image = ?, video_url = ?, video_file = ?, video_path = ?,
                        description = ?, ingredients = ?, instructions = ?, tags = ?,
                        prep_time = ?, cook_time = ?, servings = ?, difficulty = ?,
                        rating = ?, rating_count = ?, category = ?, status = ?,
                        views = ?, likes = ?, author_id = ?, featured = ?,
                        updated_at = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([
                    $title, $slug, $image, $video_url, $video_file, $video_path, $description,
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
                        title, slug, image, video_url, video_file, video_path, description,
                        ingredients, instructions, tags, prep_time, cook_time,
                        servings, difficulty, rating, rating_count, category,
                        status, views, likes, author_id, featured, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");
                
                if ($stmt->execute([
                    $title, $slug, $image, $video_url, $video_file, $video_path, $description,
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
        
        return [
            'success' => true,
            'stats' => $stats,
            'duplicates' => $duplicates
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    }
}

// Helper functions (same as before)
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

function generateUniqueSlug($title, $pdo, $exclude_id = null) {
    $slug = sanitizeSlug($title);
    if (empty($slug)) {
        $slug = 'recipe-' . uniqid();
    }
    
    // Check if slug exists
    $original_slug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT COUNT(*) FROM recipes WHERE slug = ?";
        $params = [$slug];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->fetchColumn() == 0) {
            break; // Slug is unique
        }
        
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// CSV Deduplication Tool
function createDeduplicatedCSV($input_file, $output_file) {
    $handle = fopen($input_file, 'r');
    $output_handle = fopen($output_file, 'w');
    
    if (!$handle || !$output_handle) {
        return false;
    }
    
    $headers = fgetcsv($handle);
    fputcsv($output_handle, $headers);
    
    $seen_titles = [];
    $duplicates_removed = 0;
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        $title = strtolower(trim($row[1] ?? '')); // Assuming title is in column 1
        
        if (!isset($seen_titles[$title]) && !empty($title)) {
            $seen_titles[$title] = true;
            fputcsv($output_handle, $row);
        } else {
            $duplicates_removed++;
        }
    }
    
    fclose($handle);
    fclose($output_handle);
    
    return $duplicates_removed;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Recipe CSV Importer - Duplicate Handler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .import-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .step-card { margin-bottom: 25px; border-left: 4px solid #007bff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .duplicate-section { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .duplicate-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
        .option-card { background: white; padding: 15px; border-radius: 8px; border: 2px solid #ddd; cursor: pointer; transition: all 0.3s; }
        .option-card:hover { border-color: #007bff; transform: translateY(-2px); }
        .option-card.selected { border-color: #007bff; background: #e3f2fd; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
        .stats-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stats-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .duplicate-report { background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 20px 0; max-height: 400px; overflow-y: auto; }
        .duplicate-item { background: white; padding: 10px; margin: 5px 0; border-radius: 5px; border-left: 4px solid #ffc107; }
        .progress-section { background: #e7f3ff; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .cleanup-tools { background: #f0f8f0; border: 1px solid #4caf50; padding: 20px; border-radius: 10px; margin: 20px 0; }
    </style>
</head>
<body class="bg-light">
    <div class="import-container">
        <div class="text-center mb-5">
            <h1 class="display-4 text-primary"><i class="fas fa-shield-alt"></i> Advanced Recipe CSV Importer</h1>
            <p class="lead">Import recipes with intelligent duplicate detection and handling</p>
        </div>

        <!-- Duplicate Handling Options -->
        <div class="card step-card">
            <div class="card-header bg-warning text-dark">
                <h3><i class="fas fa-copy"></i> Duplicate Handling Settings</h3>
            </div>
            <div class="card-body">
                <div class="duplicate-section">
                    <h5><i class="fas fa-exclamation-triangle"></i> How should duplicates be handled?</h5>
                    <p class="text-muted">Choose what happens when recipes with the same title are found:</p>
                    
                    <div class="duplicate-options">
                        <div class="option-card" onclick="selectDuplicateAction('skip')" data-action="skip">
                            <div class="text-center">
                                <i class="fas fa-ban fa-2x text-danger mb-2"></i>
                                <h6>Skip Duplicates</h6>
                                <small>Skip recipes that already exist (Recommended)</small>
                            </div>
                        </div>
                        
                        <div class="option-card" onclick="selectDuplicateAction('update')" data-action="update">
                            <div class="text-center">
                                <i class="fas fa-sync-alt fa-2x text-warning mb-2"></i>
                                <h6>Update Existing</h6>
                                <small>Update existing recipes with new data</small>
                            </div>
                        </div>
                        
                        <div class="option-card" onclick="selectDuplicateAction('rename')" data-action="rename">
                            <div class="text-center">
                                <i class="fas fa-edit fa-2x text-info mb-2"></i>
                                <h6>Auto-Rename</h6>
                                <small>Add numbers to duplicate titles</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV Cleanup Tools -->
        <div class="cleanup-tools">
            <h5><i class="fas fa-tools"></i> CSV Cleanup Tools</h5>
            <p>Use these tools to clean your CSV before importing:</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6><i class="fas fa-broom"></i> Remove Duplicates from CSV</h6>
                            <p class="small text-muted">Clean your CSV file by removing duplicate entries before import</p>
                            <button class="btn btn-success btn-sm" onclick="showCleanupModal()">
                                <i class="fas fa-upload"></i> Clean CSV File
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6><i class="fas fa-merge"></i> Merge Multiple CSVs</h6>
                            <p class="small text-muted">Combine multiple CSV files into one (removes duplicates)</p>
                            <button class="btn btn-info btn-sm" onclick="showMergeModal()">
                                <i class="fas fa-layer-group"></i> Merge Files
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="card step-card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fas fa-cloud-upload-alt"></i> Upload Your CSV File</h3>
            </div>
            <div class="card-body">
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="duplicate_action" value="skip" id="duplicateAction">
                    
                    <div class="upload-zone" onclick="document.getElementById('csvFile').click()">
                        <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display: none;" onchange="handleFileSelect(this)">
                        <div id="upload-content">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <h4>Click to select your CSV file</h4>
                            <p class="text-muted">or drag and drop your file here</p>
                            <small class="text-muted">Supports CSV files up to 15MB</small>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" name="import" id="importBtn" class="btn btn-primary btn-lg" disabled>
                            <i class="fas fa-rocket"></i> Import Recipes with Duplicate Protection
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
                            <div class="stats-card border-danger">
                                <div class="stats-number text-danger"><?php echo $import_stats['duplicates_found']; ?></div>
                                <div class="stats-label">Duplicates Found</div>
                            </div>
                        </div>
                        
                        <?php if (!empty($duplicate_report)): ?>
                            <div class="duplicate-report">
                                <h6><i class="fas fa-list"></i> Duplicate Report:</h6>
                                <?php foreach ($duplicate_report as $dup): ?>
                                    <div class="duplicate-item">
                                        <strong><?php echo htmlspecialchars($dup['title']); ?></strong>
                                        <br><small>
                                            <?php if ($dup['type'] === 'within_csv'): ?>
                                                Found duplicate within CSV (Row <?php echo $dup['first_row']; ?> vs Row <?php echo $dup['duplicate_row']; ?>)
                                            <?php else: ?>
                                                Found in database (Row <?php echo $dup['row']; ?>) - Action: <?php echo $dup['action']; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($import_stats['errors'])): ?>
                            <details class="mt-3">
                                <summary class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-exclamation-triangle"></i> View Errors (<?php echo count($import_stats['errors']); ?>)
                                </summary>
                                <div class="error-list mt-2" style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 5px;">
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

        <!-- Help Section -->
        <div class="card step-card">
            <div class="card-header bg-info text-white">
                <h3><i class="fas fa-question-circle"></i> How It Works</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6><i class="fas fa-search"></i> Detection</h6>
                        <p class="small">The system automatically detects duplicates by comparing recipe titles (case-insensitive)</p>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="fas fa-cog"></i> Processing</h6>
                        <p class="small">Duplicates within CSV are removed first, then database duplicates are handled based on your choice</p>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="fas fa-chart-line"></i> Reporting</h6>
                        <p class="small">Detailed reports show exactly what was found and how it was handled</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CSV Cleanup Modal -->
    <div class="modal fade" id="cleanupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-broom"></i> Clean CSV File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Upload a CSV file to remove duplicate entries:</p>
                    <form id="cleanupForm">
                        <div class="mb-3">
                            <input type="file" class="form-control" id="cleanupFile" accept=".csv" required>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-broom"></i> Clean & Download
                            </button>
                        </div>
                    </form>
                    <div id="cleanupResult" class="mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Merge CSV Modal -->
    <div class="modal fade" id="mergeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-layer-group"></i> Merge CSV Files</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Upload multiple CSV files to merge them into one (duplicates will be removed):</p>
                    <form id="mergeForm">
                        <div class="mb-3">
                            <input type="file" class="form-control" id="mergeFiles" accept=".csv" multiple required>
                            <small class="text-muted">Select multiple CSV files (Ctrl+Click or Cmd+Click)</small>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-layer-group"></i> Merge & Download
                            </button>
                        </div>
                    </form>
                    <div id="mergeResult" class="mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Duplicate action selection
        function selectDuplicateAction(action) {
            // Remove selected class from all cards
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            document.querySelector(`[data-action="${action}"]`).classList.add('selected');
            
            // Set hidden input value
            document.getElementById('duplicateAction').value = action;
        }

        // Set default selection
        document.addEventListener('DOMContentLoaded', function() {
            selectDuplicateAction('skip');
        });

        // File upload handling
        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                document.getElementById('upload-content').innerHTML = `
                    <i class="fas fa-file-csv fa-3x text-success mb-3"></i>
                    <h4>File Selected: ${file.name}</h4>
                    <p class="text-success">Size: ${fileSize} MB</p>
                    <small class="text-success">Ready to import with duplicate protection!</small>
                `;
                document.getElementById('importBtn').disabled = false;
            }
        }

        // Modal functions
        function showCleanupModal() {
            new bootstrap.Modal(document.getElementById('cleanupModal')).show();
        }

        function showMergeModal() {
            new bootstrap.Modal(document.getElementById('mergeModal')).show();
        }

        // CSV Cleanup functionality
        document.getElementById('cleanupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('cleanupFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const csv = e.target.result;
                const lines = csv.split('\n');
                const headers = lines[0];
                const seenTitles = new Set();
                const cleanedLines = [headers];
                let duplicatesRemoved = 0;
                
                for (let i = 1; i < lines.length; i++) {
                    const line = lines[i].trim();
                    if (!line) continue;
                    
                    const columns = parseCSVLine(line);
                    const title = (columns[1] || '').toLowerCase().trim(); // Assuming title is in column 1
                    
                    if (!seenTitles.has(title) && title) {
                        seenTitles.add(title);
                        cleanedLines.push(line);
                    } else if (title) {
                        duplicatesRemoved++;
                    }
                }
                
                const cleanedCSV = cleanedLines.join('\n');
                downloadCSV(cleanedCSV, 'cleaned_recipes.csv');
                
                document.getElementById('cleanupResult').innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Cleaned successfully!<br>
                        <strong>Duplicates removed:</strong> ${duplicatesRemoved}<br>
                        <strong>Clean recipes:</strong> ${cleanedLines.length - 1}
                    </div>
                `;
                document.getElementById('cleanupResult').style.display = 'block';
            };
            
            reader.readAsText(file);
        });

        // CSV Merge functionality
        document.getElementById('mergeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('mergeFiles');
            const files = Array.from(fileInput.files);
            
            if (files.length < 2) {
                alert('Please select at least 2 CSV files to merge');
                return;
            }
            
            let processedFiles = 0;
            let allData = [];
            let headers = null;
            const seenTitles = new Set();
            let totalDuplicates = 0;
            
            files.forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const csv = e.target.result;
                    const lines = csv.split('\n');
                    
                    if (!headers) {
                        headers = lines[0];
                        allData.push(headers);
                    }
                    
                    for (let i = 1; i < lines.length; i++) {
                        const line = lines[i].trim();
                        if (!line) continue;
                        
                        const columns = parseCSVLine(line);
                        const title = (columns[1] || '').toLowerCase().trim();
                        
                        if (!seenTitles.has(title) && title) {
                            seenTitles.add(title);
                            allData.push(line);
                        } else if (title) {
                            totalDuplicates++;
                        }
                    }
                    
                    processedFiles++;
                    if (processedFiles === files.length) {
                        const mergedCSV = allData.join('\n');
                        downloadCSV(mergedCSV, 'merged_recipes.csv');
                        
                        document.getElementById('mergeResult').innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Merged successfully!<br>
                                <strong>Files merged:</strong> ${files.length}<br>
                                <strong>Duplicates removed:</strong> ${totalDuplicates}<br>
                                <strong>Final recipes:</strong> ${allData.length - 1}
                            </div>
                        `;
                        document.getElementById('mergeResult').style.display = 'block';
                    }
                };
                reader.readAsText(file);
            });
        });

        // Helper functions
        function parseCSVLine(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                
                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            
            result.push(current.trim());
            return result;
        }

        function downloadCSV(csvContent, filename) {
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
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
            uploadZone.style.background = '#e3f2fd';
            uploadZone.style.borderColor = '#1976d2';
        }

        function unhighlight(e) {
            uploadZone.style.background = '';
            uploadZone.style.borderColor = '';
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
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing with Duplicate Protection...';
        });

        // Add upload zone styling
        const uploadZoneStyle = `
            .upload-zone { 
                border: 3px dashed #007bff; 
                padding: 40px; 
                text-align: center; 
                border-radius: 10px; 
                transition: all 0.3s;
                cursor: pointer;
                background: #f8f9fa;
            }
            .upload-zone:hover { 
                background: #e3f2fd; 
                border-color: #1976d2; 
                transform: translateY(-2px);
            }
        `;
        
        const style = document.createElement('style');
        style.textContent = uploadZoneStyle;
        document.head.appendChild(style);
    </script>
</body>
</html>
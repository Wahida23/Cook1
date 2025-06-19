<?php
// Debug page to check database and recipes
$conn = new mysqli("localhost", "root", "", "cookistry");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Database Debug Information</h1>";

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'recipes'");
if($tableCheck->num_rows == 0) {
    echo "<p style='color: red;'>❌ Table 'recipes' doesn't exist!</p>";
} else {
    echo "<p style='color: green;'>✅ Table 'recipes' exists</p>";
}

// Check table structure
echo "<h2>Table Structure:</h2>";
$structure = $conn->query("DESCRIBE recipes");
if($structure) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Count total recipes
$countResult = $conn->query("SELECT COUNT(*) as total FROM recipes");
$count = $countResult->fetch_assoc();
echo "<h2>Total Recipes: " . $count['total'] . "</h2>";

// Show all recipes data
echo "<h2>All Recipes Data:</h2>";
$sql = "SELECT * FROM recipes ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Title</th><th>Description</th><th>Category</th><th>Image</th><th>Created At</th>";
    echo "</tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . substr(htmlspecialchars($row['description']), 0, 50) . "...</td>";
        echo "<td>" . (isset($row['category']) ? htmlspecialchars($row['category']) : 'N/A') . "</td>";
        echo "<td>";
        if(!empty($row['image'])) {
            $imagePath = "uploads/" . $row['image'];
            if(file_exists($imagePath)) {
                echo "✅ " . $row['image'];
            } else {
                echo "❌ " . $row['image'] . " (file not found)";
            }
        } else {
            echo "No image";
        }
        echo "</td>";
        echo "<td>" . (isset($row['created_at']) ? $row['created_at'] : 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No recipes found in database</p>";
}

// Check uploads directory
echo "<h2>Uploads Directory Check:</h2>";
if(is_dir('uploads')) {
    echo "<p style='color: green;'>✅ Uploads directory exists</p>";
    $files = scandir('uploads');
    $imageFiles = array_filter($files, function($file) {
        return !in_array($file, ['.', '..']) && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
    });
    
    if(count($imageFiles) > 0) {
        echo "<p>Images found: " . implode(', ', $imageFiles) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No image files found in uploads directory</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Uploads directory doesn't exist</p>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th { background-color: #f0f0f0; padding: 8px; }
td { padding: 8px; }
h1, h2 { color: #333; }
</style>
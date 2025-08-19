<?php
session_start();
require_once 'functions.php';

$message = '';

// Handle File Upload Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_products'])) {
    if (isset($_FILES['product_csv']) && $_FILES['product_csv']['error'] == 0) {
        $allowed_mime_types = ['text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/plain'];
        $uploaded_file_info = finfo_open(FILEINFO_MIME_TYPE);
        $uploaded_mime_type = finfo_file($uploaded_file_info, $_FILES['product_csv']['tmp_name']);
        finfo_close($uploaded_file_info);

        if (in_array($uploaded_mime_type, $allowed_mime_types)) {
            $temp_file = $_FILES['product_csv']['tmp_name'];
            $handle = fopen($temp_file, 'r');
            $header = fgetcsv($handle);
            fclose($handle);
            
            // --- CHANGE: Update the expected header ---
            $expected_header = ['S.No', 'Name', 'Dimensions'];

            if ($header === $expected_header) {
                $destination = DATA_PATH . 'products.csv';
                if (move_uploaded_file($temp_file, $destination)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Product CSV uploaded successfully!'];
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Could not save the uploaded file. Check folder permissions.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid CSV format. The header must be: S.No,Name,Dimensions'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid file type. Not a recognized CSV format.'];
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: No file uploaded or an upload error occurred.'];
    }
    header('Location: products.php');
    exit;
}

if (isset($_SESSION['message'])) {
    $message_type = $_SESSION['message']['type'] === 'success' ? 'color: green; background-color: #eaf7ea;' : 'color: red; background-color: #fdeaea;';
    $message = "<div style='padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid; {$message_type}'>" . htmlspecialchars($_SESSION['message']['text']) . "</div>";
    unset($_SESSION['message']);
}

$products = getCsvData('products.csv');

// Filter products based on search query if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = strtolower(trim($_GET['search']));
    $products = array_filter($products, function($product) use ($search) {
        return strpos(strtolower($product['Name']), $search) !== false ||
               strpos(strtolower($product['Dimensions']), $search) !== false;
    });
}

include 'includes/header.php';
?>

<h1>Product Management</h1>
<?= $message ?>

<form action="products.php" method="post" enctype="multipart/form-data">
    <fieldset>
        <legend>Upload New Product List</legend>
        <!-- --- CHANGE: Update the instruction text --- -->
        <p>Upload a CSV file to replace the current product list. The file must have the exact header row: <code>S.No,Name,Dimensions</code></p>
        
        <label for="product_csv">Select CSV File:</label>
        <input type="file" id="product_csv" name="product_csv" accept=".csv" required>
        
        <button type="submit" style="border-radius: 55px;" name="upload_products" style="margin-top: 10px;">Upload and Replace Products</button>
    </fieldset>
</form>

<hr>

<h2>Current Product List</h2>

<!-- Search Form -->
<form method="get" action="products.php" style="margin-bottom: 20px;">
    <label for="search">Search Products (by Name or Dimensions):</label>
    <input type="text" id="search" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Enter search term...">
    <button style="border-radius: 55px;" type="submit">Search</button>
    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
        <a href="products.php" style="margin-left: 10px;">Clear Search</a>
    <?php endif; ?>
</form>

<table>
    <thead>
        <!-- --- CHANGE: Update table headers --- -->
        <tr>
            <th>S.No</th>
            <th>Name</th>
            <th>Dimensions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($products)): ?>
            <tr>
                <td colspan="3">No products found matching your search.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
            <tr>
                <!-- --- CHANGE: Update table cells to match new keys --- -->
                <td><?= htmlspecialchars($product['S.No']) ?></td>
                <td><?= htmlspecialchars($product['Name']) ?></td>
                <td><?= htmlspecialchars($product['Dimensions']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>
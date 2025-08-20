<?php
// Start session at the very top for notifications
session_start();

require_once 'functions.php';

// --- Handle Status Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $newStatus = sanitize_input($_POST['new_status']);

    if (!empty($orderId) && in_array($newStatus, ['Pending', 'On Going', 'Completed'])) {
        updateCsvRow('orders.csv', $orderId, 'order_id', ['status' => $newStatus]);
        $_SESSION['message'] = ['type' => 'success', 'text' => "Order #$orderId status updated to $newStatus."];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid update request.'];
    }
    header('Location: orders.php');
    exit;
}

/**
 * Generate unique ID in the format: 3 letters + 4 digits (e.g., ORD1234).
 * - If $columnName exists as a column in $file, ensure uniqueness against that column.
 * - If $columnName == 'S.No' but not a real column (items stored in items_json), it scans items_json across rows.
 * - $prefix lets you fix the 3-letter part (e.g., 'ORD', 'MAN'); if shorter/empty, random letters fill in.
 */
if (!function_exists('generateUniqueId')) {
    function generateUniqueId($file, $columnName, $prefix = '') {
        $existingIds = [];

        if (file_exists($file) && ($handle = fopen($file, "r")) !== false) {
            $headers = fgetcsv($handle);
            $idIndex = $headers ? array_search($columnName, $headers) : false;

            if ($idIndex !== false) {
                // Column exists: collect values from that column.
                while (($row = fgetcsv($handle)) !== false) {
                    if (isset($row[$idIndex]) && $row[$idIndex] !== '') {
                        $existingIds[] = $row[$idIndex];
                    }
                }
            } else {
                // Special case: S.No values live inside items_json
                if ($columnName === 'S.No' && $headers) {
                    $itemsIdx = array_search('items_json', $headers);
                    if ($itemsIdx !== false) {
                        while (($row = fgetcsv($handle)) !== false) {
                            $items = json_decode($row[$itemsIdx] ?? '', true);
                            if (is_array($items)) {
                                foreach ($items as $it) {
                                    if (isset($it['S.No'])) {
                                        $existingIds[] = $it['S.No'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            fclose($handle);
        }

        // Generate until unique
        do {
            // Clean + normalize prefix; if missing/short, fill with random letters to reach 3
            $letters = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $prefix), 0, 3));
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            while (strlen($letters) < 3) {
                $letters .= $alphabet[random_int(0, 25)];
            }

            $numbers = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $uniqueId = $letters . $numbers;
        } while (in_array($uniqueId, $existingIds, true));

        return $uniqueId;
    }
}

// --- Handle form submission for creating a new order ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $customerId = sanitize_input($_POST['customer_id']);
    $poDate = sanitize_input($_POST['po_date']);
    $deliveryDate = sanitize_input($_POST['delivery_date']);
    $dueDate = sanitize_input($_POST['due_date']);
    
    $products = getCsvData('products.csv');
    $productDetailsMap = array_column($products, null, 'S.No');

    $orderItems = [];

    // Process product selected from dropdown
    $productSNo = $_POST['product_sno'][0] ?? ''; 
    if (!empty($productSNo)) {
        $quantity = (int)($_POST['quantity'][0] ?? 1);
        if ($quantity > 0 && isset($productDetailsMap[$productSNo])) {
            $product = $productDetailsMap[$productSNo];
            $orderItems[] = [
                'S.No' => $productSNo,
                'Name' => $product['Name'],
                'Dimensions' => $product['Dimensions'],
                'quantity' => $quantity
            ];
        }
    }
    
    // Process manually added product
    $manualName = sanitize_input($_POST['manual_product_name']);
    if (!empty($manualName)) {
        $manualDimensions = sanitize_input($_POST['manual_product_dimensions']);
        $manualQty = (int)($_POST['manual_product_quantity']);
        if ($manualQty > 0) {
            $orderItems[] = [
                'S.No' => generateUniqueId('orders.csv', 'S.No', 'MAN'),
                'Name' => $manualName,
                'Dimensions' => $manualDimensions,
                'quantity' => $manualQty
            ];
        }
    }

    // Save order if valid
    if (!empty($customerId) && !empty($orderItems) && !empty($poDate)) {
        $newOrder = [
            'order_id' => generateUniqueId('orders.csv', 'order_id', 'ORD'),
            'customer_id' => $customerId,
            'po_date' => $poDate,
            'delivery_date' => $deliveryDate,
            'due_date' => $dueDate,
            'items_json' => json_encode($orderItems),
            'status' => 'Pending'
        ];
        appendCsvData('orders.csv', $newOrder);
        $_SESSION['order_created'] = true;
        header('Location: orders.php');
        exit;
    }
}

// Check for notifications
$showToast = isset($_SESSION['order_created']) && $_SESSION['order_created'];
if ($showToast) {
    unset($_SESSION['order_created']);
}

$message = '';
if (isset($_SESSION['message'])) {
    $message_type = $_SESSION['message']['type'] === 'success' ? 'color: green; background-color: #eaf7ea;' : 'color: red; background-color: #fdeaea;';
    $message = "<div class='no-print' style='padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid; {$message_type}'>" . htmlspecialchars($_SESSION['message']['text']) . "</div>";
    unset($_SESSION['message']);
}

// Get data for display
$orders = getCsvData('orders.csv');
$customers = getCsvData('customers.csv');
$products = getCsvData('products.csv');
$customerMap = array_column($customers, 'name', 'id');

include 'includes/header.php';

if ($showToast) {
    echo '<div id="toast-notification">Order Created Successfully!</div>';
}
?>

<h1>Order Management</h1>
<?= $message ?>

<form action="orders.php" method="post" class="no-print">
    <!-- ... form contents ... -->
    <label for="customer_id">Select Customer:</label>
    <select name="customer_id" id="customer_id" required>
        <option value="">-- Choose a Customer --</option>
        <?php foreach ($customers as $customer): ?>
            <option value="<?= htmlspecialchars($customer['id']) ?>"><?= htmlspecialchars($customer['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <div style="display: flex; gap: 20px; margin-top: 15px;">
        <div style="flex: 1;"><label for="po_date">PO Date:</label><input type="date" id="po_date" name="po_date" required></div>
        <div style="flex: 1;"><label for="delivery_date">Expected Delivery Date:</label><input type="date" id="delivery_date" name="delivery_date"></div>
        <div style="flex: 1;"><label for="due_date">Payment Due Date:</label><input type="date" id="due_date" name="due_date"></div>
    </div>
    <fieldset>
        <legend>Select an Existing Product</legend>
        <label for="product_sno">Product:</label>
        <select name="product_sno[]">
             <option value="">-- None --</option>
            <?php foreach ($products as $product): ?>
                <option value="<?= htmlspecialchars($product['S.No']) ?>"><?= htmlspecialchars($product['Name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity[]" min="1" value="1" style="width: 100px;">
    </fieldset>
    <fieldset>
        <legend>Or Add a Manual Product</legend>
        <label for="manual_product_name">Product Name:</label>
        <input type="text" id="manual_product_name" name="manual_product_name" placeholder="e.g., Custom Cabinetry">
        <label for="manual_product_dimensions">Dimensions:</label>
        <input type="text" id="manual_product_dimensions" name="manual_product_dimensions" placeholder="e.g., 150cm x 60cm x 90cm">
        <label for="manual_product_quantity">Quantity:</label>
        <input type="number" id="manual_product_quantity" name="manual_product_quantity" min="1" value="1" style="width: 100px;">
    </fieldset>
   <br> <button type="submit" style="border-radius: 55px;" name="create_order">Create Order</button>
</form>

<hr class="no-print">

<div id="printable-area">
    <div class="table-header">
        <h2>Orders List</h2>
        <button onclick="window.print()" style="border-radius: 55px;" class="no-print">Print Orders</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>PO Date</th>
                <th>Items</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="5">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach (array_reverse($orders) as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['order_id']) ?></td>
                    <td><?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($order['po_date']) ?></td>
                    <td>
                        <div class="order-items">
                            <?php 
                            $items = json_decode($order['items_json'], true);
                            if (is_array($items)):
                                foreach ($items as $item):
                            ?>
                                <div class="item">
                                    <strong><?= htmlspecialchars($item['Name']) ?> (x<?= htmlspecialchars($item['quantity']) ?>)</strong>
                                    <p style="font-size: 0.9em; color: #555; margin: 0;"><?= htmlspecialchars($item['Dimensions']) ?></p>
                                </div>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </td>
                    <td>
                        <?php
                            $status = $order['status'] ?? 'Pending';
                            $status_class = 'status-' . strtolower(str_replace(' ', '-', $status));
                        ?>
                        <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                        <form action="orders.php" method="post" class="status-form no-print">
                            <!--
                                *** BUG FIX IS HERE ***
                                The 'value' attribute for the hidden input now correctly uses
                                the $order['order_id'] specific to this loop iteration.
                            -->
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                            <select name="new_status">
                                <option value="Pending" <?= $status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="On Going" <?= $status == 'On Going' ? 'selected' : '' ?>>On Going</option>
                                <option value="Completed" <?= $status == 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                            <button type="submit" style="border-radius: 55px;" name="update_status">Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>

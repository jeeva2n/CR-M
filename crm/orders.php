<?php
require_once 'functions.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $customerId = sanitize_input($_POST['customer_id']);
    $poDate = sanitize_input($_POST['po_date']);
    $deliveryDate = sanitize_input($_POST['delivery_date']);
    $dueDate = sanitize_input($_POST['due_date']);
    
    $products = getCsvData('products.csv');
    // --- CHANGE: Use S.No as the key for the lookup map ---
    $productDetailsMap = array_column($products, null, 'S.No');

    $orderItems = [];

    // Process product selected from dropdown
    $productSNo = $_POST['product_sno'][0] ?? ''; 
    if (!empty($productSNo)) {
        $quantity = (int)($_POST['quantity'][0] ?? 1);
        if ($quantity > 0 && isset($productDetailsMap[$productSNo])) {
            $product = $productDetailsMap[$productSNo];
            // --- CHANGE: Store the new product structure ---
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
        // --- CHANGE: The manual add now takes Name and Dimensions ---
        $manualDimensions = sanitize_input($_POST['manual_product_dimensions']);
        $manualQty = (int)($_POST['manual_product_quantity']);
        if ($manualQty > 0) {
            $orderItems[] = [
                'S.No' => 'MANUAL-' . time(),
                'Name' => $manualName,
                'Dimensions' => $manualDimensions,
                'quantity' => $manualQty
            ];
        }
    }

    if (!empty($customerId) && !empty($orderItems) && !empty($poDate)) {
        $newOrder = [
            'order_id' => 'ORD' . time(),
            'customer_id' => $customerId,
            'po_date' => $poDate,
            'delivery_date' => $deliveryDate,
            'due_date' => $dueDate,
            'items_json' => json_encode($orderItems),
        ];
        appendCsvData('orders.csv', $newOrder);
        header('Location: orders.php');
        exit;
    }
}

$orders = getCsvData('orders.csv');
$customers = getCsvData('customers.csv');
$products = getCsvData('products.csv');
$customerMap = array_column($customers, 'name', 'id');

include 'includes/header.php';
?>

<h1>Order Management</h1>
<form action="orders.php" method="post" class="no-print"> <!-- Add no-print class -->
    <!-- Customer and Date fields are the same -->
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
        <!-- --- CHANGE: Update dropdown to use S.No --- -->
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
        <!-- --- CHANGE: Update manual add form --- -->
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
<script>
function printDiv(divId) {
    var content = document.getElementById(divId).innerHTML;
    var printWindow = window.open('', '', 'width=800,height=600');
    printWindow.document.write('<html><head><title>Print</title>');
    
    // Optional: copy your CSS so it looks styled in print
    document.querySelectorAll('link[rel="stylesheet"]').forEach(function(link) {
        printWindow.document.write('<link rel="stylesheet" href="' + link.href + '">');
    });

    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>
<hr class="no-print"> <!-- Add no-print class -->

<!-- --- FEATURE: Add a container and Print button for the table --- -->
<div id="printable-area">
    <div class="table-header">
        <h2>Recent Orders</h2>
       <button style="border-radius: 55px;" onclick="printDiv('printable-area')" class="no-print">Print Orders</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>Order ID</th><th>Customer</th><th>PO Date</th><th>Delivery Date</th><th>Due Date</th><th>Items</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="6">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach (array_reverse($orders) as $order): ?>
                <tr>
                    <td><?= htmlspecialchars($order['order_id']) ?></td>
                    <td><?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($order['po_date']) ?></td>
                    <td><?= htmlspecialchars($order['delivery_date']) ?></td>
                    <td><?= htmlspecialchars($order['due_date']) ?></td>
                    <td>
                        <div class="order-items">
                            <?php 
                            $items = json_decode($order['items_json'], true);
                            if (is_array($items)):
                                foreach ($items as $item):
                            ?>
                                <div class="item">
                                    <!-- --- CHANGE: Display Name and Dimensions --- -->
                                    <strong><?= htmlspecialchars($item['Name']) ?> (x<?= htmlspecialchars($item['quantity']) ?>)</strong>
                                    <p style="font-size: 0.9em; color: #555; margin: 0;"><?= htmlspecialchars($item['Dimensions']) ?></p>
                                </div>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>

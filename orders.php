<?php
session_start();
require_once 'functions.php';

// --- STAGE 3: Handle Machining Process Forms ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_machining_process'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];
    $newProcess = [
        'name' => sanitize_input($_POST['process_name']), 'sequence' => sanitize_input($_POST['sequence_number']),
        'vendor' => sanitize_input($_POST['vendor_name']), 'start_date' => sanitize_input($_POST['start_date']),
        'expected_completion' => sanitize_input($_POST['expected_completion']), 'actual_completion' => '',
        'status' => 'Not Started', 'remarks' => '', 'documents' => [],
        'daily_updates' => [] // --- MODIFIED: Initialize daily updates array ---
    ];
    $orders = getCsvData('orders.csv');
    foreach ($orders as &$order) {
        if ($order['order_id'] == $orderId) {
            $items = json_decode($order['items_json'], true);
            if (!isset($items[$itemIndex]['machining_processes'])) { $items[$itemIndex]['machining_processes'] = []; }
            $items[$itemIndex]['machining_processes'][] = $newProcess;
            usort($items[$itemIndex]['machining_processes'], function($a, $b) { return $a['sequence'] <=> $b['sequence']; });
            updateCsvRow('orders.csv', $orderId, 'order_id', ['items_json' => json_encode($items)]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Machining process added.'];
            header('Location: orders.php'); exit;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_machining_process'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];
    $processIndex = (int)$_POST['process_index'];
    $orders = getCsvData('orders.csv');
    foreach ($orders as &$order) {
        if ($order['order_id'] == $orderId) {
            $items = json_decode($order['items_json'], true);
            $process = &$items[$itemIndex]['machining_processes'][$processIndex];
            $process['actual_completion'] = sanitize_input($_POST['actual_completion']);
            $process['status'] = sanitize_input($_POST['process_status']);
            $process['remarks'] = sanitize_input($_POST['remarks']);

            // --- NEW: Handle daily update submission ---
            $dailyUpdateNote = sanitize_input($_POST['daily_update_note']);
            if (!empty($dailyUpdateNote)) {
                if (!isset($process['daily_updates'])) { $process['daily_updates'] = []; }
                $newUpdate = [
                    'date' => date('Y-m-d H:i:s'),
                    'note' => $dailyUpdateNote
                ];
                $process['daily_updates'][] = $newUpdate;
            }
            // --- END NEW ---

            if (isset($_FILES['process_document']) && $_FILES['process_document']['error'] == 0) {
                $file = $_FILES['process_document']; $uploadDir = __DIR__ . '/uploads/machining_docs/';
                $newFilename = $orderId.'_'.$itemIndex.'_'.$processIndex.'_'.time().'_'.basename($file['name']);
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) { $process['documents'][] = $newFilename; }
            }
            updateCsvRow('orders.csv', $orderId, 'order_id', ['items_json' => json_encode($items)]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Machining process updated.'];
            if ($process['status'] === 'Completed') {
                $all_processes_complete = true;
                foreach($items[$itemIndex]['machining_processes'] as $p) { if ($p['status'] !== 'Completed') { $all_processes_complete = false; break; } }
                if ($all_processes_complete) {
                    $recipients = "sales@example.com, inspection@example.com"; $subject = "Product Ready for QC from Order #$orderId";
                    $body = "The Product '{$items[$itemIndex]['Name']}' from order #$orderId is now complete and ready for Quality Inspection.";
                    @mail($recipients, $subject, $body, "From: crm-noreply@example.com");
                }
            }
            header('Location: orders.php'); exit;
        }
    }
}

// --- STAGE 2: Handle Raw Material Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_raw_material'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $itemIndex = (int)$_POST['item_index'];
    $newMaterial = [
        'type' => sanitize_input($_POST['raw_material_type']), 'grade' => sanitize_input($_POST['raw_material_grade']),
        'dimensions' => sanitize_input($_POST['raw_material_dimensions']), 'vendor' => sanitize_input($_POST['vendor_name']),
        'purchase_date' => sanitize_input($_POST['purchase_date']),
    ];
    $orders = getCsvData('orders.csv');
    foreach ($orders as &$order) {
        if ($order['order_id'] == $orderId) {
            $items = json_decode($order['items_json'], true);
            if (!isset($items[$itemIndex]['raw_materials'])) { $items[$itemIndex]['raw_materials'] = []; }
            $items[$itemIndex]['raw_materials'][] = $newMaterial;
            updateCsvRow('orders.csv', $orderId, 'order_id', ['items_json' => json_encode($items)]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Raw material added.'];
            header('Location: orders.php'); exit;
        }
    }
}

// --- STAGE 1: Handle Order Creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $customerId = sanitize_input($_POST['customer_id']);
    $poDate = sanitize_input($_POST['po_date']);
    $deliveryDate = sanitize_input($_POST['delivery_date']);
    $dueDate = sanitize_input($_POST['due_date']);
    $products = getCsvData('products.csv');
    $productDetailsMap = array_column($products, null, 'S.No');
    $orderItems = [];
    $productSNo = $_POST['product_sno'][0] ?? '';
    if (!empty($productSNo)) {
        $quantity = (int)($_POST['quantity'][0] ?? 1);
        if ($quantity > 0 && isset($productDetailsMap[$productSNo])) {
            $product = $productDetailsMap[$productSNo];
            $orderItems[] = [
                'S.No' => $productSNo, 'Name' => $product['Name'], 'Dimensions' => $product['Dimensions'], 'quantity' => $quantity,
                'raw_materials' => [], 'machining_processes' => []
            ];
        }
    }
    $manualName = sanitize_input($_POST['manual_product_name']);
    if (!empty($manualName)) {
        $manualDimensions = sanitize_input($_POST['manual_product_dimensions']);
        $manualQty = (int)($_POST['manual_product_quantity']);
        if ($manualQty > 0) {
            $orderItems[] = [
                'S.No' => generateUniqueId('orders.csv', 'S.No', 'MAN'),
                'Name' => $manualName, 'Dimensions' => $manualDimensions, 'quantity' => $manualQty,
                'raw_materials' => [], 'machining_processes' => []
            ];
        }
    }
    if (!empty($customerId) && !empty($orderItems) && !empty($poDate)) {
        $orderId = generateUniqueId('orders.csv', 'order_id', 'ORD'); // --- MODIFIED: Generate ID earlier for filename
        $drawingFilename = '';

        // --- NEW: Handle Drawing/Image Upload ---
        if (isset($_FILES['drawing_file']) && $_FILES['drawing_file']['error'] == 0) {
            $file = $_FILES['drawing_file'];
            $uploadDir = __DIR__ . '/uploads/drawings/';
            // Ensure directory exists
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $newFilename = $orderId . '_' . time() . '_' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) {
                $drawingFilename = $newFilename;
            }
        }
        // --- END NEW ---

        $newOrder = [
            'order_id' => $orderId, // --- MODIFIED: Use the pre-generated ID
            'customer_id' => $customerId, 'po_date' => $poDate, 'delivery_date' => $deliveryDate,
            'due_date' => $dueDate, 'items_json' => json_encode($orderItems), 'status' => 'Pending',
            'drawing_filename' => $drawingFilename, // --- MODIFIED: Store the filename
            'inspection_reports_json' => json_encode([])
        ];
        appendCsvData('orders.csv', $newOrder);
        $_SESSION['order_created'] = true;
        header('Location: orders.php'); exit;
    }
}

// --- Handle Global Status Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = sanitize_input($_POST['order_id']);
    $newStatus = sanitize_input($_POST['new_status']);
    if (!empty($orderId) && in_array($newStatus, ['Pending', 'Sourcing Material', 'In Production', 'Ready for QC', 'Completed'])) {
        updateCsvRow('orders.csv', $orderId, 'order_id', ['status' => $newStatus]);
        $_SESSION['message'] = ['type' => 'success', 'text' => "Order #$orderId status updated to $newStatus."];
        if ($newStatus === 'In Production') {
            $recipients = "sales@example.com, production@example.com"; $subject = "Update: Raw Material Sourcing Completed for Order #$orderId";
            $body = "Raw material sourcing for order #$orderId is complete. The ticket is now assigned to the Production Head.";
            @mail($recipients, $subject, $body, "From: crm-noreply@example.com");
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid status update request.'];
    }
    header('Location: orders.php'); exit;
}

// --- Get data for display ---
$orders = getCsvData('orders.csv');
$customers = getCsvData('customers.csv');
$products = getCsvData('products.csv');
$customerMap = array_column($customers, 'name', 'id');
include 'includes/header.php';
if (isset($_SESSION['order_created'])) { echo '<div id="toast-notification">Order Created Successfully!</div>'; unset($_SESSION['order_created']); }
if (isset($_SESSION['message'])) { $message_type = $_SESSION['message']['type'] === 'success' ? 'color: green; background-color: #eaf7ea;' : 'color: red; background-color: #fdeaea;'; echo "<div class='no-print' style='padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid; {$message_type}'>" . htmlspecialchars($_SESSION['message']['text']) . "</div>"; unset($_SESSION['message']); }
?>
<style>
    /* --- NEW: Styles for new features --- */
    .drawing-preview { margin-top: 10px; }
    .drawing-preview img { max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px; }
    .daily-updates-log { background-color: #f9f9f9; border: 1px solid #eee; padding: 10px; margin-top: 10px; border-radius: 4px; max-height: 150px; overflow-y: auto; }
    .daily-updates-log h6 { margin-top: 0; margin-bottom: 8px; font-size: 0.9em; color: #333; }
    .daily-updates-log p { margin: 0 0 5px; font-size: 0.85em; }
    .daily-updates-log p small { color: #777; }
    .process-update-form textarea { width: 100%; box-sizing: border-box; margin-top: 5px; }
</style>

<h1>Order Management</h1>

<!-- MODIFIED: Added enctype for file uploads -->
<form action="orders.php" method="post" enctype="multipart/form-data" class="no-print">
    <fieldset><legend>Stage 1: Create Purchase Order</legend>
        <label for="customer_id">Client Name:</label>
        <select name="customer_id" id="customer_id" required><option value="">-- Choose a Client --</option><?php foreach ($customers as $customer): ?><option value="<?= htmlspecialchars($customer['id']) ?>"><?= htmlspecialchars($customer['name']) ?></option><?php endforeach; ?></select>
        <div style="display: flex; gap: 20px; margin-top: 15px;">
            <div style="flex: 1;"><label for="po_date">PO Date:</label><input type="date" id="po_date" name="po_date" required></div>
            <div style="flex: 1;"><label for="delivery_date">Delivery Date:</label><input type="date" id="delivery_date" name="delivery_date"></div>
            <div style="flex: 1;"><label for="due_date">Due Date:</label><input type="date" id="due_date" name="due_date"></div>
        </div>
        <!-- NEW: File input for drawing/image -->
        <div style="margin-top: 15px;">
            <label for="drawing_file">Upload Drawing/Image (Optional):</label>
            <input type="file" id="drawing_file" name="drawing_file">
        </div>
    </fieldset>
    <fieldset><legend>Add a Line Item</legend>
        <p>You can add either one existing product or one manual product per order submission.</p>
        <div><strong>Select an Existing Product:</strong><select name="product_sno[]"><option value="">-- None --</option><?php foreach ($products as $product): ?><option value="<?= htmlspecialchars($product['S.No']) ?>"><?= htmlspecialchars($product['Name']) ?></option><?php endforeach; ?></select><label for="quantity">Quantity:</label><input type="number" name="quantity[]" min="1" value="1" style="width: 100px;"></div><hr style="margin: 15px 0;">
        <div><strong>Or Add a Manual Product:</strong><input type="text" id="manual_product_name" name="manual_product_name" placeholder="Manual Product Name"><input type="text" id="manual_product_dimensions" name="manual_product_dimensions" placeholder="Dimensions"><input type="number" id="manual_product_quantity" name="manual_product_quantity" min="1" value="1" style="width: 100px;"></div>
    </fieldset>
    <button type="submit" name="create_order">Create Order</button>
</form>

<hr class="no-print">
<!-- 
<div id="printable-area">
    <div class="table-header"><h2>Order Pipeline</h2><button onclick="window.print()" class="no-print">Print Orders</button></div> -->
   
<!-- </div> -->
<div id="printable-area">
  <div class="table-header">
    <h2>Order Pipeline</h2>
    <button onclick="window.print()" class="no-print">Print Orders</button>
  </div>
  <div class="table-wrapper">
    <table>
         <table>
        <thead><tr><th>Order Details</th><th>Line Items & Workflow</th><th>Status Control</th></tr></thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="3">No orders found.</td></tr>
            <?php else: foreach (array_reverse($orders) as $order): ?>
                <tr>
                    <td><strong>Order #:</strong> <?= htmlspecialchars($order['order_id']) ?> <br><strong>Client:</strong> <?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'N/A') ?> <br><strong>Date:</strong> <?= htmlspecialchars($order['po_date']) ?></td>
                    <td>
                        <div class="order-items">
                            <?php // --- NEW: Display Drawing/Image ---
                            if (!empty($order['drawing_filename'])): ?>
                                <div class="drawing-preview">
                                    <strong>Drawing:</strong><br>
                                    <a href="uploads/drawings/<?= htmlspecialchars($order['drawing_filename']) ?>" target="_blank">
                                        <img src="uploads/drawings/<?= htmlspecialchars($order['drawing_filename']) ?>" alt="Drawing">
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php $items = json_decode($order['items_json'], true);
                            if (is_array($items)) foreach ($items as $itemIndex => $item): ?>
                                <div class="item">
                                    <strong><?= htmlspecialchars($item['Name'] ?? 'N/A') ?> (Qty: <?= htmlspecialchars($item['quantity']) ?>)</strong>
                                    <!-- Stage 2: Raw Material Section -->
                                    <div class="raw-materials-list">
                                        <?php if(!empty($item['raw_materials'])): ?><h5>Sourced Raw Materials:</h5>
                                            <table class="nested-table"><thead><tr><th>Type</th><th>Grade</th><th>Dimensions</th><th>Vendor</th><th>Date</th></tr></thead><tbody>
                                            <?php foreach ($item['raw_materials'] as $mat): ?>
                                                <tr><td><?= htmlspecialchars($mat['type'])?></td><td><?= htmlspecialchars($mat['grade'])?></td><td><?= htmlspecialchars($mat['dimensions'])?></td><td><?= htmlspecialchars($mat['vendor'])?></td><td><?= htmlspecialchars($mat['purchase_date'])?></td></tr>
                                            <?php endforeach; ?>
                                            </tbody></table>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (($order['status'] ?? '') === 'Sourcing Material'): ?>
                                    <form action="orders.php" method="post" class="raw-material-form no-print">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>"><input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                        <input type="text" name="raw_material_type" placeholder="Material Type" required><input type="text" name="raw_material_grade" placeholder="Grade" required>
                                        <input type="text" name="raw_material_dimensions" placeholder="Dimensions" required><input type="text" name="vendor_name" placeholder="Vendor Name" required>
                                        <input type="date" name="purchase_date" title="Purchase Date" required><button type="submit" name="add_raw_material">+ Add Material</button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Stage 3: Machining Section -->
                                    <div class="machining-processes">
                                        <?php if(!empty($item['machining_processes'])): ?><h5>Machining Processes:</h5>
                                            <?php foreach ($item['machining_processes'] as $procIdx => $proc): ?>
                                            <div class="process-item">
                                                <strong><?= htmlspecialchars($proc['sequence']) ?>. <?= htmlspecialchars($proc['name']) ?></strong> (<em><?= htmlspecialchars($proc['vendor']) ?></em>) - <span class="status-badge-small <?= 'status-'.strtolower(str_replace(' ','-',$proc['status']??'')) ?>"><?= htmlspecialchars($proc['status']??'Not Started') ?></span>
                                                <p>Timeline: <?= htmlspecialchars($proc['start_date']) ?> to <?= htmlspecialchars($proc['expected_completion']) ?> (Actual: <?= htmlspecialchars($proc['actual_completion']?:'N/A') ?>)</p>

                                                <!-- NEW: Display Daily Update Log -->
                                                <?php if (!empty($proc['daily_updates'])): ?>
                                                    <div class="daily-updates-log">
                                                        <h6>Daily Log:</h6>
                                                        <?php foreach (array_reverse($proc['daily_updates']) as $update): ?>
                                                            <p>
                                                                <small><?= htmlspecialchars($update['date']) ?></small><br>
                                                                <?= nl2br(htmlspecialchars($update['note'])) ?>
                                                            </p>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <!-- END NEW -->

                                                <form action="orders.php" method="post" enctype="multipart/form-data" class="process-update-form no-print">
                                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>"><input type="hidden" name="item_index" value="<?= $itemIndex ?>"><input type="hidden" name="process_index" value="<?= $procIdx ?>">
                                                    <select name="process_status"><option value="Not Started" <?= ($proc['status']??'')=='Not Started'?'selected':'' ?>>Not Started</option><option value="In Progress" <?= ($proc['status']??'')=='In Progress'?'selected':'' ?>>In Progress</option><option value="Completed" <?= ($proc['status']??'')=='Completed'?'selected':'' ?>>Completed</option></select>
                                                    <input type="date" name="actual_completion" value="<?= htmlspecialchars($proc['actual_completion']??'') ?>" title="Actual Completion"><input type="text" name="remarks" placeholder="Remarks" value="<?= htmlspecialchars($proc['remarks']??'') ?>">
                                                    <input type="file" name="process_document" title="Upload Document">
                                                    <!-- NEW: Textarea for daily update -->
                                                    <textarea name="daily_update_note" placeholder="Add a new daily update..."></textarea>
                                                    <button type="submit" name="update_machining_process">Update</button>
                                                </form>

                                                <div class="process-docs"><?php foreach (($proc['documents'] ?? []) as $doc): ?><a href="uploads/machining_docs/<?= htmlspecialchars($doc) ?>" target="_blank"><?= htmlspecialchars($doc) ?></a><?php endforeach; ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (($order['status'] ?? '') === 'In Production'): ?>
                                        <form action="orders.php" method="post" class="machining-add-form no-print">
                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>"><input type="hidden" name="item_index" value="<?= $itemIndex ?>">
                                            <input type="number" name="sequence_number" placeholder="Seq #" required><input type="text" name="process_name" placeholder="Process Name" required>
                                            <input type="text" name="vendor_name" placeholder="Vendor or 'In-house'" required><input type="date" name="start_date" title="Start Date" required>
                                            <input type="date" name="expected_completion" title="Expected Completion" required><button type="submit" name="add_machining_process">+ Add Process</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge <?= 'status-'.strtolower(str_replace(' ','-',$order['status']??'')) ?>"><?= htmlspecialchars($order['status'] ?? 'Pending') ?></span>
                        <form action="orders.php" method="post" class="status-form no-print">
                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                            <select name="new_status">
                                <option value="Pending" <?= ($order['status']??'')=='Pending'?'selected':''?>>Pending</option>
                                <option value="Sourcing Material" <?= ($order['status']??'')=='Sourcing Material'?'selected':''?>>Sourcing</option>
                                <option value="In Production" <?= ($order['status']??'')=='In Production'?'selected':''?>>Production</option>
                                <option value="Ready for QC" <?= ($order['status']??'')=='Ready for QC'?'selected':''?>>Ready for QC</option>
                                <option value="Completed" <?= ($order['status']??'')=='Completed'?'selected':''?>>Completed</option>
                            </select>
                            <button type="submit" name="update_status">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
      <!-- your PHP generated rows stay same -->
    </table>
  </div>
</div>


<?php include 'includes/footer.php'; ?>

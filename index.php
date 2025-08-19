<?php
require_once 'functions.php';

// --- Fetch all necessary data ---
$customers = getCsvData('customers.csv');
$products = getCsvData('products.csv'); // We need to read this file now
$orders = getCsvData('orders.csv');

// --- Calculate ALL metrics ---
$totalCustomers = count($customers);
$totalProducts = count($products);
$totalOrders = count($orders);

// Calculate order status counts
$pendingCount = 0;
$ongoingCount = 0;
$completedCount = 0;
foreach ($orders as $order) {
    $status = $order['status'] ?? 'Pending';
    switch ($status) {
        case 'On Going':
            $ongoingCount++;
            break;
        case 'Completed':
            $completedCount++;
            break;
        case 'Pending':
        default:
            $pendingCount++;
            break;
    }
}

// Get recent orders for the table
$recentOrders = array_slice(array_reverse($orders), 0, 5);
$customerMap = array_column($customers, 'name', 'id');

include 'includes/header.php';
?>

<h1>Dashboard</h1>

<!-- This is now a more comprehensive set of metric cards -->
<div class="dashboard-metrics-grid">
    <!-- Row 1: General Stats -->
    <div class="metric-card">
        <h2>Total Customers</h2>
        <p><?= $totalCustomers ?></p>
    </div>
    <div class="metric-card">
        <h2>Total Products</h2>
        <p><?= $totalProducts ?></p>
    </div>
    <div class="metric-card">
        <h2>Total Orders</h2>
        <p><?= $totalOrders ?></p>
    </div>

    <!-- Row 2: Order Status Stats -->
    <div class="metric-card status-pending">
        <h2>Pending Orders</h2>
        <p><?= $pendingCount ?></p>
    </div>
    <div class="metric-card status-on-going">
        <h2>On Going Orders</h2>
        <p><?= $ongoingCount ?></p>
    </div>
    <div class="metric-card status-completed">
        <h2>Completed Orders</h2>
        <p><?= $completedCount ?></p>
    </div>
</div>

<hr>

<h2>Recent Orders</h2>
<!-- The recent orders table remains the same -->
<table>
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>PO Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($recentOrders)): ?>
            <tr><td colspan="4">No orders have been placed yet.</td></tr>
        <?php else: ?>
            <?php foreach ($recentOrders as $order): ?>
            <tr>
                <td><?= htmlspecialchars($order['order_id']) ?></td>
                <td><?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($order['po_date']) ?></td>
                <td>
                    <?php
                        $status = $order['status'] ?? 'Pending';
                        $status_class = 'status-' . strtolower(str_replace(' ', '-', $status));
                    ?>
                    <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($status) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include 'includes/footer.php'; ?>

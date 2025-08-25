<?php
require_once 'functions.php';

$customers = getCsvData('customers.csv');
$products = getCsvData('products.csv');
$orders = getCsvData('orders.csv');

$totalCustomers = count($customers);
$totalProducts = count($products);
$totalOrders = count($orders);

// Initialize counts for all statuses
$statusCounts = [
    'Pending' => 0, 'Sourcing Material' => 0, 'In Production' => 0,
    'Ready for QC' => 0, 'Completed' => 0,
];

foreach ($orders as $order) {
    $status = $order['status'] ?? 'Pending';
    if (array_key_exists($status, $statusCounts)) {
        $statusCounts[$status]++;
    } else {
        $statusCounts['Pending']++; // Default for any unknown status
    }
}

$recentOrders = array_slice(array_reverse($orders), 0, 5);
$customerMap = array_column($customers, 'name', 'id');

include 'includes/header.php';
?>

<h1>Dashboard</h1>

<div class="dashboard-metrics-grid">
    <div class="metric-card"><h2>Total Customers</h2><p><?= $totalCustomers ?></p></div>
    <div class="metric-card"><h2>Total Products</h2><p><?= $totalProducts ?></p></div>
    <div class="metric-card"><h2>Total Orders</h2><p><?= $totalOrders ?></p></div>
    
    <div class="metric-card status-pending"><h2>Pending</h2><p><?= $statusCounts['Pending'] ?></p></div>
    <div class="metric-card status-sourcing-material"><h2>Sourcing</h2><p><?= $statusCounts['Sourcing Material'] ?></p></div>
    <div class="metric-card status-in-production"><h2>Production</h2><p><?= $statusCounts['In Production'] ?></p></div>
    <div class="metric-card status-ready-for-qc"><h2>Ready for QC</h2><p><?= $statusCounts['Ready for QC'] ?></p></div>
    <div class="metric-card status-completed"><h2>Completed</h2><p><?= $statusCounts['Completed'] ?></p></div>
</div>

<hr>
<h2>Recent Orders</h2>
<table>
    <thead>
        <tr><th>Order ID</th><th>Customer</th><th>PO Date</th><th>Status</th></tr>
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

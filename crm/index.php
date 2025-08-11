<?php
require_once 'functions.php';

// Fetch all necessary data
$customers = getCsvData('customers.csv');
$products = getCsvData('products.csv');
$orders = getCsvData('orders.csv');

// Calculate key metrics
$totalCustomers = count($customers);
$totalProducts = count($products);
$totalOrders = count($orders);

// Get the 5 most recent orders for the dashboard
$recentOrders = array_slice(array_reverse($orders), 0, 5);

// Create a customer lookup map for easy name retrieval in the recent orders list
$customerMap = array_column($customers, 'name', 'id');

include 'includes/header.php';
?>

<h1>Dashboard</h1>
<div class="dashboard-metrics">
    <div class="metric-card">
        <i class="fas fa-users"></i>
        <h2>Total Customers</h2>  
        <p class="count" data-target="<?= $totalCustomers ?>">0</p>
    </div>

    <div class="metric-card">
        <i class="fas fa-box"></i>
        <h2>Total Products</h2>
        <p class="count" data-target="<?= $totalProducts ?>">0</p>
    </div>

    <div class="metric-card">
        <i class="fas fa-shopping-cart"></i>
        <h2>Total Orders</h2>
        <p class="count" data-target="<?= $totalOrders ?>">0</p>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const counters = document.querySelectorAll('.count');
    const speed = 22; // smaller is faster

    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            const increment = Math.ceil(target / speed);

            if(count < target) {
                counter.innerText = count + increment;
                setTimeout(updateCount, 20);
            } else {
                counter.innerText = target.toLocaleString();
            }
        };
        updateCount();
    });
});
</script>


<hr>
<h2>Recent Orders</h2>
<table>
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>PO Date</th>
            <th>Delivery Date</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($recentOrders)): ?>
            <tr>
                <td colspan="4">No orders have been placed yet.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($recentOrders as $order): ?>
            <tr>
                <td><?= htmlspecialchars($order['order_id']) ?></td>
                <td><?= htmlspecialchars($customerMap[$order['customer_id']] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($order['po_date']) ?></td>
                <td><?= htmlspecialchars($order['delivery_date']) ?></td>
            </tr> 
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php include 'includes/footer.php'; ?>

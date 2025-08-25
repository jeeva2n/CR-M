<?php
require_once 'functions.php';

// Handle form submission for adding a new customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    
    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $newCustomer = [
          'id' => 'CUST' . str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT),
            'name' => $name,
            'email' => $email,
            'signup_date' => date('Y-m-d')
        ];
        appendCsvData('customers.csv', $newCustomer);
        header('Location: customers.php');
        exit;
    }
}

$customers = getCsvData('customers.csv');

include 'includes/header.php';
?>

<h1>Customer Management</h1>

<h2>Add New Customer</h2> 
<form action="customers.php" method="post">
    <label for="name">Name:</label>
    <input type="text" id="name" name="name" required>
    
    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required>
    
    <button type="submit" style="border-radius: 55px;" name="add_customer">Add Customer</button>
</form>
<hr>

<h2>Existing Customers</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Sign-up Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($customers as $customer): ?>
        <tr>
            <td><?= htmlspecialchars($customer['id']) ?></td>
            <td><?= htmlspecialchars($customer['name']) ?></td>
            <td><?= htmlspecialchars($customer['email']) ?></td>
            <td><?= htmlspecialchars($customer['signup_date']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<?php include 'includes/footer.php'; ?>

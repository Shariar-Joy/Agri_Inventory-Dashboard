<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Check if customer ID is provided
if (!isset($_GET['id'])) {
    // Redirect to customers page if no ID provided
    header("Location: customers.php");
    exit();
}

$customerId = sanitizeInput($_GET['id']);

// Get customer details
$query = "SELECT * FROM customer WHERE CustomerID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $customerId);
$stmt->execute();
$result = $stmt->get_result();

// Check if customer exists
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: customers.php");
    exit();
}

$customer = $result->fetch_assoc();
$stmt->close();

// Get customer contact numbers
$contactQuery = "SELECT ContactNumber FROM customer_contact WHERE CustomerID = ?";
$contactStmt = $conn->prepare($contactQuery);
$contactStmt->bind_param("s", $customerId);
$contactStmt->execute();
$contactResult = $contactStmt->get_result();

$contactNumbers = [];
while ($contactRow = $contactResult->fetch_assoc()) {
    $contactNumbers[] = $contactRow['ContactNumber'];
}
$contactStmt->close();

// Get customer orders
$orderQuery = "SELECT o.*, m.MarketName 
              FROM order_table o
              LEFT JOIN market m ON o.MarketID = m.MarketID 
              WHERE o.CustomerID = ?
              ORDER BY o.OrderDate DESC";
$orderStmt = $conn->prepare($orderQuery);
$orderStmt->bind_param("s", $customerId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

$orders = [];
while ($orderRow = $orderResult->fetch_assoc()) {
    $orders[] = $orderRow;
}
$orderStmt->close();

// Calculate customer statistics
$statsQuery = "SELECT 
                COUNT(o.OrderID) as TotalOrders,
                SUM(o.TotalAmount) as TotalSpent,
                MAX(o.OrderDate) as LastOrderDate
              FROM order_table o
              WHERE o.CustomerID = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("s", $customerId);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();
$statsStmt->close();

// Set page title
$pageTitle = "Customer Details: " . $customer['Name'];
include('includes/header.php');
?>

<main>
    <div class="page-header">
        <h1>Customer Details</h1>
        <div class="header-actions">
            <a href="customers.php" class="btn btn-secondary">Back to Customers</a>
            <a href="customers.php?action=edit&id=<?php echo $customerId; ?>" class="btn btn-primary">Edit Customer</a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Customer Information Card -->
    <div class="detail-card">
        <div class="card-header">
            <h2><?php echo $customer['Name']; ?></h2>
            <span class="customer-id"><?php echo $customer['CustomerID']; ?></span>
        </div>
        
        <div class="card-body">
            <div class="detail-section">
                <h3>Contact Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Address:</span>
                        <span class="detail-value"><?php echo $customer['Street'] . ', ' . $customer['City'] . ', ' . $customer['ZipCode']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Contact Numbers:</span>
                        <span class="detail-value">
                            <?php 
                                if (empty($contactNumbers)) {
                                    echo 'No contact numbers registered';
                                } else {
                                    echo implode(', ', $contactNumbers);
                                }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Customer Statistics</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Total Orders:</span>
                        <span class="detail-value"><?php echo $stats['TotalOrders'] ?? 0; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Spent:</span>
                        <span class="detail-value"><?php echo formatCurrency($stats['TotalSpent'] ?? 0); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Order Date:</span>
                        <span class="detail-value">
                            <?php echo ($stats['LastOrderDate'] ?? null) ? date('F j, Y', strtotime($stats['LastOrderDate'])) : 'No orders yet'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order History -->
    <div class="data-table">
        <div class="table-header">
            <h2>Order History</h2>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search orders...">
            </div>
        </div>
        
        <?php if (empty($orders)): ?>
            <div class="no-data-message">
                <p>This customer has no orders yet.</p>
                <a href="orders.php?customer=<?php echo $customerId; ?>" class="btn btn-primary">Create New Order</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Market</th>
                        <th>Total Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo $order['OrderID']; ?></td>
                            <td><?php echo date('F j, Y', strtotime($order['OrderDate'])); ?></td>
                            <td><?php echo $order['MarketName'] ?? 'N/A'; ?></td>
                            <td><?php echo formatCurrency($order['TotalAmount']); ?></td>
                            <td class="action-buttons">
                                <a href="order_details.php?id=<?php echo $order['OrderID']; ?>" class="btn btn-primary btn-sm">View</a>
                            </td>
                        </tr>
                        
                        <?php
                        // Get purchase details for this order
                        $purchaseQuery = "SELECT p.PurchaseID, p.CropName, p.Quantity, p.UnitPrice, p.TotalPrice, 
                                        GROUP_CONCAT(b.BatchID) as BatchIDs
                                    FROM purchase p
                                    LEFT JOIN batch_purchase bp ON p.PurchaseID = bp.PurchaseID
                                    LEFT JOIN batch b ON bp.BatchID = b.BatchID
                                    WHERE p.OrderID = ?
                                    GROUP BY p.PurchaseID";
                        $purchaseStmt = $conn->prepare($purchaseQuery);
                        $purchaseStmt->bind_param("s", $order['OrderID']);
                        $purchaseStmt->execute();
                        $purchaseResult = $purchaseStmt->get_result();
                        
                        if ($purchaseResult->num_rows > 0):
                        ?>
                            <tr class="order-details">
                                <td colspan="5">
                                    <div class="purchase-details">
                                        <h4>Purchase Details</h4>
                                        <table class="nested-table">
                                            <thead>
                                                <tr>
                                                    <th>Crop Name</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Price</th>
                                                    <th>Total Price</th>
                                                    <th>Batch IDs</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($purchase = $purchaseResult->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo $purchase['CropName']; ?></td>
                                                        <td><?php echo $purchase['Quantity']; ?></td>
                                                        <td><?php echo formatCurrency($purchase['UnitPrice']); ?></td>
                                                        <td><?php echo formatCurrency($purchase['TotalPrice']); ?></td>
                                                        <td><?php echo $purchase['BatchIDs'] ?? 'N/A'; ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                        endif;
                        $purchaseStmt->close();
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.detail-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.card-header {
    background-color: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h2 {
    margin: 0;
    font-size: 1.5rem;
}

.customer-id {
    background-color: #e9ecef;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.9rem;
}

.card-body {
    padding: 20px;
}

.detail-section {
    margin-bottom: 20px;
}

.detail-section h3 {
    font-size: 1.2rem;
    margin-bottom: 15px;
    color: #495057;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 5px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.9rem;
}

.detail-value {
    font-size: 1rem;
}

.no-data-message {
    text-align: center;
    padding: 30px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.order-details {
    background-color: #f8f9fa;
}

.purchase-details {
    padding: 15px;
}

.purchase-details h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #495057;
    font-size: 1rem;
}

.nested-table {
    width: 100%;
    font-size: 0.9rem;
    border-collapse: collapse;
}

.nested-table th, .nested-table td {
    padding: 8px;
    border: 1px solid #dee2e6;
}

.nested-table th {
    background-color: #e9ecef;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality for orders table
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr:not(.order-details)');
            
            tableRows.forEach(function(row, index) {
                const orderDetailsRow = row.nextElementSibling;
                const text = row.textContent.toLowerCase();
                const isVisible = text.indexOf(searchTerm) > -1;
                
                row.style.display = isVisible ? '' : 'none';
                
                // Also hide/show the associated order details row if it exists
                if (orderDetailsRow && orderDetailsRow.classList.contains('order-details')) {
                    orderDetailsRow.style.display = isVisible ? '' : 'none';
                }
            });
        });
    }
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
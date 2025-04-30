<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Get order ID from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to orders page if no ID is provided
    header("Location: orders.php");
    exit();
}

$orderId = sanitizeInput($_GET['id']);

// Get order details
$query = "SELECT o.*, m.MarketName, m.Street as MarketStreet, m.City as MarketCity, m.ZipCode as MarketZipCode,
          c.Name as CustomerName, c.Street as CustomerStreet, c.City as CustomerCity, c.ZipCode as CustomerZipCode
          FROM order_table o
          LEFT JOIN market m ON o.MarketID = m.MarketID
          LEFT JOIN customer c ON o.CustomerID = c.CustomerID
          WHERE o.OrderID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Order not found, redirect to orders page
    header("Location: orders.php");
    exit();
}

$orderDetails = $result->fetch_assoc();
$stmt->close();

// Get purchase items
$purchaseQuery = "SELECT p.*, 
                 (SELECT GROUP_CONCAT(bp.BatchID SEPARATOR ', ') FROM batch_purchase bp WHERE bp.PurchaseID = p.PurchaseID) as BatchIDs
                 FROM purchase p 
                 WHERE p.OrderID = ?";
$purchaseStmt = $conn->prepare($purchaseQuery);
$purchaseStmt->bind_param("s", $orderId);
$purchaseStmt->execute();
$purchaseResult = $purchaseStmt->get_result();
$purchases = [];

while ($row = $purchaseResult->fetch_assoc()) {
    $purchases[] = $row;
}
$purchaseStmt->close();

// Get customer contact numbers
$contactQuery = "SELECT ContactNumber FROM customer_contact WHERE CustomerID = ?";
$contactStmt = $conn->prepare($contactQuery);
$contactStmt->bind_param("s", $orderDetails['CustomerID']);
$contactStmt->execute();
$contactResult = $contactStmt->get_result();
$contactNumbers = [];

while ($row = $contactResult->fetch_assoc()) {
    $contactNumbers[] = $row['ContactNumber'];
}
$contactStmt->close();

// Set page title
$pageTitle = "Order Details";
include('includes/header.php');
?>

<main>
    <div class="content-header">
        <h1>Order Details</h1>
        <div class="actions">
            <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
            <a href="orders.php?action=edit&id=<?php echo $orderId; ?>" class="btn btn-primary">Edit Order</a>
            <a href="#" class="btn btn-primary" onclick="printPage()">Print</a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="order-meta">
                <h2>Order #<?php echo $orderDetails['OrderID']; ?></h2>
                <span class="order-date"><?php echo date('F d, Y', strtotime($orderDetails['OrderDate'])); ?></span>
            </div>
        </div>
        
        <div class="card-body">
            <div class="row">
                <div class="col">
                    <h3>Customer Information</h3>
                    <div class="detail-group">
                        <p><strong>Name:</strong> <?php echo $orderDetails['CustomerName']; ?></p>
                        <p><strong>Address:</strong> <?php echo $orderDetails['CustomerStreet'] . ', ' . $orderDetails['CustomerCity'] . ', ' . $orderDetails['CustomerZipCode']; ?></p>
                        <p><strong>Contact:</strong> 
                            <?php 
                            if (!empty($contactNumbers)) {
                                echo implode(', ', $contactNumbers);
                            } else {
                                echo 'No contact number available';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                
                <div class="col">
                    <h3>Market Information</h3>
                    <div class="detail-group">
                        <p><strong>Market:</strong> <?php echo $orderDetails['MarketName']; ?></p>
                        <p><strong>Location:</strong> <?php echo $orderDetails['MarketStreet'] . ', ' . $orderDetails['MarketCity'] . ', ' . $orderDetails['MarketZipCode']; ?></p>
                    </div>
                </div>
            </div>
            
            <h3>Purchase Items</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Purchase ID</th>
                            <th>Crop Name</th>
                            <th>Quantity (kg)</th>
                            <th>Unit Price ($)</th>
                            <th>Total Price ($)</th>
                            <th>Batch IDs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchases)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No purchase items found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?php echo $purchase['PurchaseID']; ?></td>
                                    <td><?php echo $purchase['CropName']; ?></td>
                                    <td><?php echo number_format($purchase['Quantity'], 2); ?></td>
                                    <td><?php echo formatCurrency($purchase['UnitPrice']); ?></td>
                                    <td><?php echo formatCurrency($purchase['TotalPrice']); ?></td>
                                    <td><?php echo $purchase['BatchIDs'] ? $purchase['BatchIDs'] : 'None'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Total:</strong></td>
                            <td><strong><?php echo formatCurrency($orderDetails['TotalAmount']); ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php
            // Check if this order has any batches
            $batchesExist = false;
            foreach ($purchases as $purchase) {
                if (!empty($purchase['BatchIDs'])) {
                    $batchesExist = true;
                    break;
                }
            }
            
            if ($batchesExist):
            ?>
            <h3>Batch Tracking Information</h3>
            <div class="tracking-info">
                <?php
                // Get unique batch IDs
                $allBatchIds = [];
                foreach ($purchases as $purchase) {
                    if (!empty($purchase['BatchIDs'])) {
                        $batchIdArray = explode(', ', $purchase['BatchIDs']);
                        $allBatchIds = array_merge($allBatchIds, $batchIdArray);
                    }
                }
                $uniqueBatchIds = array_unique($allBatchIds);
                
                // Get batch information
                if (!empty($uniqueBatchIds)) {
                    $placeholders = str_repeat('?,', count($uniqueBatchIds) - 1) . '?';
                    $batchInfoQuery = "SELECT b.BatchID, b.BatchProductionDate, c.CropName, c.CropVariety, 
                                      h.HarvestID, h.Day as HarvestDay, h.Month as HarvestMonth, h.Year as HarvestYear,
                                      f.Name as FarmerName, ws.EntryDate, ws.ExpiryDate, w.City as WarehouseCity
                                      FROM batch b
                                      JOIN crop c ON b.BatchID = c.BatchID
                                      JOIN harvest_session h ON b.HarvestID = h.HarvestID
                                      JOIN farmer f ON h.FarmerID = f.FarmerID
                                      JOIN warehouse_stock ws ON b.BatchID = ws.BatchID
                                      JOIN warehouse w ON ws.WarehouseID = w.WarehouseID
                                      WHERE b.BatchID IN ($placeholders)";
                    
                    $batchStmt = $conn->prepare($batchInfoQuery);
                    $types = str_repeat('s', count($uniqueBatchIds));
                    $batchStmt->bind_param($types, ...$uniqueBatchIds);
                    $batchStmt->execute();
                    $batchResult = $batchStmt->get_result();
                    
                    ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Batch ID</th>
                                    <th>Crop Info</th>
                                    <th>Production Date</th>
                                    <th>Farmer</th>
                                    <th>Harvest Date</th>
                                    <th>Storage Info</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($batch = $batchResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $batch['BatchID']; ?></td>
                                        <td><?php echo $batch['CropName'] . ' (' . $batch['CropVariety'] . ')'; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($batch['BatchProductionDate'])); ?></td>
                                        <td><?php echo $batch['FarmerName']; ?></td>
                                        <td><?php echo $batch['HarvestDay'] . '/' . $batch['HarvestMonth'] . '/' . $batch['HarvestYear']; ?></td>
                                        <td>
                                            <p>Warehouse: <?php echo $batch['WarehouseCity']; ?></p>
                                            <p>Entry: <?php echo date('M d, Y', strtotime($batch['EntryDate'])); ?></p>
                                            <p>Expiry: <?php echo date('M d, Y', strtotime($batch['ExpiryDate'])); ?></p>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    $batchStmt->close();
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.actions {
    display: flex;
    gap: 0.5rem;
}

.card {
    background: var(--color-white);
    border-radius: var(--border-radius-1);
    padding: 0;
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--color-light);
}

.card-body {
    padding: 1.5rem;
}

.order-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-date {
    font-size: 1.1rem;
    color: var(--color-dark-variant);
}

.row {
    display: flex;
    gap: 2rem;
    margin-bottom: 2rem;
}

.col {
    flex: 1;
}

.detail-group {
    background: var(--color-light);
    padding: 1rem;
    border-radius: var(--border-radius-1);
}

.detail-group p {
    margin: 0.5rem 0;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5rem;
}

.table th, .table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--color-light);
}

.table th {
    text-align: left;
    background-color: var(--color-light);
}

.table tfoot td {
    border-top: 2px solid var(--color-primary);
    font-weight: bold;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.tracking-info {
    background: var(--color-light);
    padding: 1rem;
    border-radius: var(--border-radius-1);
}

@media print {
    header, .sidebar, footer, .actions {
        display: none !important;
    }
    
    body {
        background: white;
    }
    
    main {
        margin: 0;
        padding: 0;
        width: 100%;
    }
    
    .card {
        box-shadow: none;
    }
}
</style>

<script>
function printPage() {
    window.print();
}
</script>

<?php
// Include footer
include('includes/footer.php');
?>
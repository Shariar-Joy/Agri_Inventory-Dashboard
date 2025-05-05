<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Get market ID from URL
if (!isset($_GET['id'])) {
    header("Location: markets.php");
    exit();
}

$marketId = sanitizeInput($_GET['id']);

// Get market details
$query = "SELECT * FROM market WHERE MarketID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $marketId);
$stmt->execute();
$result = $stmt->get_result();

// Check if market exists
if ($result->num_rows != 1) {
    showError("Market not found!");
    header("Location: markets.php");
    exit();
}

$market = $result->fetch_assoc();
$stmt->close();

// Get shipments for this market
$shipmentsQuery = "SELECT s.*, 
                  COUNT(bs.BatchID) as BatchCount,
                  SUM(st.Weight) as TotalShippedWeight
                  FROM shipment s
                  LEFT JOIN batch_shipment bs ON s.ShipmentID = bs.ShipmentID
                  LEFT JOIN shipment_transport st ON s.ShipmentID = st.ShipmentID
                  WHERE s.MarketID = ?
                  GROUP BY s.ShipmentID
                  ORDER BY s.Year DESC, s.Month DESC, s.Day DESC";
$stmt = $conn->prepare($shipmentsQuery);
$stmt->bind_param("s", $marketId);
$stmt->execute();
$shipmentsResult = $stmt->get_result();
$shipments = [];

if ($shipmentsResult && $shipmentsResult->num_rows > 0) {
    while ($row = $shipmentsResult->fetch_assoc()) {
        $shipments[] = $row;
    }
}
$stmt->close();

// Get orders for this market
$ordersQuery = "SELECT o.*, c.Name as CustomerName, 
               COUNT(p.PurchaseID) as PurchaseCount,
               SUM(p.Quantity) as TotalQuantity
               FROM order_table o
               LEFT JOIN customer c ON o.CustomerID = c.CustomerID
               LEFT JOIN purchase p ON o.OrderID = p.OrderID
               WHERE o.MarketID = ?
               GROUP BY o.OrderID
               ORDER BY o.OrderDate DESC";
$stmt = $conn->prepare($ordersQuery);
$stmt->bind_param("s", $marketId);
$stmt->execute();
$ordersResult = $stmt->get_result();
$orders = [];

if ($ordersResult && $ordersResult->num_rows > 0) {
    while ($row = $ordersResult->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();

// Set page title
$pageTitle = "Market Details: " . $market['MarketName'];
include('includes/header.php');
?>

<main>
    <div class="breadcrumb">
        <a href="dashboard.php">Dashboard</a> &gt; 
        <a href="markets.php">Markets</a> &gt; 
        <?php echo htmlspecialchars($market['MarketName']); ?>
    </div>

    <div class="page-header">
        <h1>Market Details: <?php echo htmlspecialchars($market['MarketName']); ?></h1>
        <div class="actions">
            <a href="markets.php?action=edit&id=<?php echo $marketId; ?>" class="btn btn-primary">Edit Market</a>
            <a href="shipment_new.php?market=<?php echo $marketId; ?>" class="btn btn-success">New Shipment</a>
            <a href="order_new.php?market=<?php echo $marketId; ?>" class="btn btn-success">New Order</a>
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
    
    <!-- Market Information Card -->
    <div class="info-card">
        <h2>Market Information</h2>
        <div class="info-card-content">
            <div class="info-group">
                <div class="info-item">
                    <span class="label">Market ID:</span>
                    <span class="value"><?php echo htmlspecialchars($market['MarketID']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Name:</span>
                    <span class="value"><?php echo htmlspecialchars($market['MarketName']); ?></span>
                </div>
            </div>
            
            <div class="info-group">
                <div class="info-item">
                    <span class="label">Address:</span>
                    <span class="value">
                        <?php echo htmlspecialchars($market['Street'] . ', ' . $market['City'] . ', ' . $market['ZipCode']); ?>
                    </span>
                </div>
            </div>
            
            <div class="info-group">
                <div class="info-item">
                    <span class="label">Total Shipments:</span>
                    <span class="value"><?php echo count($shipments); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Total Orders:</span>
                    <span class="value"><?php echo count($orders); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Market Stats -->
    <div class="row stats-container">
        <div class="stat-card">
            <h3>Orders Value</h3>
            <div class="stat-value">
                <?php 
                    $totalOrderValue = 0;
                    foreach ($orders as $order) {
                        $totalOrderValue += $order['TotalAmount'];
                    }
                    echo number_format($totalOrderValue, 2);
                ?> $
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Total Products Ordered</h3>
            <div class="stat-value">
                <?php 
                    $totalQuantity = 0;
                    foreach ($orders as $order) {
                        $totalQuantity += $order['TotalQuantity'];
                    }
                    echo number_format($totalQuantity, 2);
                ?> units
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Last Order</h3>
            <div class="stat-value">
                <?php 
                    echo !empty($orders) ? date('M d, Y', strtotime($orders[0]['OrderDate'])) : 'N/A';
                ?>
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Last Shipment</h3>
            <div class="stat-value">
                <?php
                    if (!empty($shipments)) {
                        $date = $shipments[0]['Year'] . '-' . 
                               str_pad($shipments[0]['Month'], 2, '0', STR_PAD_LEFT) . '-' .
                               str_pad($shipments[0]['Day'], 2, '0', STR_PAD_LEFT);
                        echo date('M d, Y', strtotime($date));
                    } else {
                        echo 'N/A';
                    }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Shipments Section -->
    <div class="data-table">
        <div class="table-header">
            <h2>Shipments</h2>
            <div class="search-container">
                <input type="text" id="searchShipments" placeholder="Search shipments...">
            </div>
        </div>
        
        <table id="shipmentsTable">
            <thead>
                <tr>
                    <th>Shipment ID</th>
                    <th>Date</th>
                    <th>Destination</th>
                    <th>Total Weight</th>
                    <th>Batches</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shipments)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No shipments found for this market.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shipments as $shipment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($shipment['ShipmentID']); ?></td>
                            <td>
                                <?php 
                                    $date = $shipment['Year'] . '-' . 
                                           str_pad($shipment['Month'], 2, '0', STR_PAD_LEFT) . '-' .
                                           str_pad($shipment['Day'], 2, '0', STR_PAD_LEFT);
                                    echo date('M d, Y', strtotime($date));
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($shipment['DestinationLocation']); ?></td>
                            <td><?php echo number_format($shipment['TotalWeight'], 2); ?> kg</td>
                            <td><?php echo $shipment['BatchCount']; ?></td>
                            <td class="action-buttons">
                                <a href="shipment_details.php?id=<?php echo $shipment['ShipmentID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Orders Section -->
    <div class="data-table">
        <div class="table-header">
            <h2>Orders</h2>
            <div class="search-container">
                <input type="text" id="searchOrders" placeholder="Search orders...">
            </div>
        </div>
        
        <table id="ordersTable">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Total Amount</th>
                    <th>Products</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No orders found for this market.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['OrderID']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                            <td><?php echo htmlspecialchars($order['CustomerName']); ?></td>
                            <td>$<?php echo number_format($order['TotalAmount'], 2); ?></td>
                            <td><?php echo $order['PurchaseCount']; ?></td>
                            <td class="action-buttons">
                                <a href="order_details.php?id=<?php echo $order['OrderID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
// Search functionality for shipments table
document.getElementById('searchShipments').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const table = document.getElementById('shipmentsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let found = false;
        const cells = rows[i].getElementsByTagName('td');
        
        for (let j = 0; j < cells.length; j++) {
            const cellText = cells[j].textContent || cells[j].innerText;
            
            if (cellText.toLowerCase().indexOf(searchValue) > -1) {
                found = true;
                break;
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
});

// Search functionality for orders table
document.getElementById('searchOrders').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const table = document.getElementById('ordersTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        let found = false;
        const cells = rows[i].getElementsByTagName('td');
        
        for (let j = 0; j < cells.length; j++) {
            const cellText = cells[j].textContent || cells[j].innerText;
            
            if (cellText.toLowerCase().indexOf(searchValue) > -1) {
                found = true;
                break;
            }
        }
        
        rows[i].style.display = found ? '' : 'none';
    }
});
</script>

<?php
// Include footer
//include('includes/footer.php');
?>
<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Dashboard";

// Get dashboard statistics
// Total Products (Batches)
$query = "SELECT COUNT(*) as total FROM batch";
$result = $conn->query($query);
$totalProducts = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalProducts = $row['total'];
}

// Total Sales
$query = "SELECT SUM(TotalAmount) as total_sales FROM order_table";
$result = $conn->query($query);
$totalSales = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalSales = $row['total_sales'] ?? 0;
}

// Total Shipments
$query = "SELECT COUNT(*) as total FROM shipment";
$result = $conn->query($query);
$totalShipments = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalShipments = $row['total'];
}

// Check if vehicle table exists before querying
$totalVehicles = 0;
$vehicleTableExists = false;
$checkTableQuery = "SHOW TABLES LIKE 'vehicle'";
$tableResult = $conn->query($checkTableQuery);
if ($tableResult && $tableResult->num_rows > 0) {
    $vehicleTableExists = true;
    // Total Vehicles
    $query = "SELECT COUNT(*) as total FROM vehicle";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $totalVehicles = $row['total'];
    }
}

// Inventory Value
$query = "SELECT SUM(p.UnitPrice * b.Quantity) as inventory_value
          FROM batch b
          JOIN purchase p ON b.BatchID = p.PurchaseID
          WHERE b.BatchID IN (SELECT BatchID FROM warehouse_stock)";
$result = $conn->query($query);
$inventoryValue = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $inventoryValue = $row['inventory_value'] ?? 0;
}

// Active Farmers
$query = "SELECT COUNT(DISTINCT FarmerID) as total FROM harvest_session";
$result = $conn->query($query);
$activeFarmers = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $activeFarmers = $row['total'];
}

// Total Warehouses
$query = "SELECT COUNT(*) as total FROM warehouse";
$result = $conn->query($query);
$totalWarehouses = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalWarehouses = $row['total'];
}

// Total Orders
$query = "SELECT COUNT(*) as total FROM order_table";
$result = $conn->query($query);
$totalOrders = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalOrders = $row['total'];
}

// Check if spoilage_report table exists before querying
$totalSpoilage = 0;
$spoilageValue = 0;
$spoilageTableExists = false;
$checkTableQuery = "SHOW TABLES LIKE 'spoilage_report'";
$tableResult = $conn->query($checkTableQuery);
if ($tableResult && $tableResult->num_rows > 0) {
    $spoilageTableExists = true;
    
    // Total Spoilage Reports
    $query = "SELECT COUNT(*) as total FROM spoilage_report";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $totalSpoilage = $row['total'];
    }

    // Total Spoilage Value
    $query = "SELECT SUM(EstimatedValue) as total_value FROM spoilage_report";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $spoilageValue = $row['total_value'] ?? 0;
    }
}

// Get latest climate data
$query = "SELECT wcl.*, w.City
          FROM warehouse_climate_log wcl
          JOIN warehouse w ON wcl.WarehouseID = w.WarehouseID
          ORDER BY wcl.RecordedAt DESC
          LIMIT 1";
$result = $conn->query($query);
$latestClimate = null;
if ($result && $result->num_rows > 0) {
    $latestClimate = $result->fetch_assoc();
}

// Warehouse temperature thresholds
$warehouseThresholds = [
    'min_temp' => 2.0,
    'max_temp' => 25.0,
    'min_humidity' => 30.0,
    'max_humidity' => 70.0
];

// Recent Orders
$query = "SELECT o.OrderID, o.OrderDate, o.TotalAmount, c.Name as CustomerName, m.MarketName
          FROM order_table o
          LEFT JOIN customer c ON o.CustomerID = c.CustomerID
          LEFT JOIN market m ON o.MarketID = m.MarketID
          ORDER BY o.OrderDate DESC LIMIT 10";
$result = $conn->query($query);
$recentOrders = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

// Get expiring soon inventory (within 30 days)
$expiringQuery = "
    SELECT COUNT(*) as ExpiringCount
    FROM warehouse_stock
    WHERE ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
";
$expiringResult = $conn->query($expiringQuery);
$expiringCount = 0;
if ($expiringResult && $expiringResult->num_rows > 0) {
    $row = $expiringResult->fetch_assoc();
    $expiringCount = $row['ExpiringCount'];
}

// Fetch all warehouses for filter dropdown
$warehousesQuery = "SELECT WarehouseID, City FROM warehouse ORDER BY City";
$warehousesResult = $conn->query($warehousesQuery);
$warehouses = [];
if ($warehousesResult && $warehousesResult->num_rows > 0) {
    while ($row = $warehousesResult->fetch_assoc()) {
        $warehouses[] = $row;
    }
}

// Handle warehouse selection
if (isset($_GET['action']) && $_GET['action'] === 'set_warehouse' && isset($_POST['warehouse_id'])) {
    $_SESSION['selectedWarehouse'] = $_POST['warehouse_id'];
}

// Include header
include('includes/header.php');
?>

<main>
    <h1>Dashboard</h1>

    <div class="date">
        <input type="date" value="<?php echo date('Y-m-d'); ?>">
    </div>

    <!-- Warehouse Selector -->
    <div class="filters">
        <div class="filter-container">
            <form action="dashboard.php?action=set_warehouse" method="POST" class="warehouse-filter">
                <div class="form-control">
                    <label for="warehouse_id">Select Active Warehouse:</label>
                    <select name="warehouse_id" id="warehouse_id">
                        <option value="">All Warehouses</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['WarehouseID']; ?>" <?php echo (isset($_SESSION['selectedWarehouse']) && $_SESSION['selectedWarehouse'] == $warehouse['WarehouseID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($warehouse['City'] . ' (ID: ' . $warehouse['WarehouseID'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Set Active Warehouse</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['selectedWarehouse']) && $_SESSION['selectedWarehouse'] != ''): ?>
        <?php
        // Get warehouse details
        $warehouseId = $_SESSION['selectedWarehouse'];
        $query = "SELECT WarehouseID, City FROM warehouse WHERE WarehouseID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $warehouseId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $warehouse = $result->fetch_assoc();
            $warehouseName = "Warehouse-" . $warehouse['WarehouseID'] . " (" . $warehouse['City'] . ")";
            echo '<div id="current-warehouse"><h3>Current Warehouse: ' . htmlspecialchars($warehouseName) . '</h3></div>';
        }
        
        $stmt->close();
        
        // Get warehouse capacity utilization
        $capacityQuery = "
            SELECT 
                w.Capacity as TotalCapacity,
                COALESCE(SUM(b.Quantity), 0) as UsedCapacity
            FROM 
                warehouse w
            LEFT JOIN 
                warehouse_stock ws ON w.WarehouseID = ws.WarehouseID
            LEFT JOIN 
                batch b ON ws.BatchID = b.BatchID
            WHERE 
                w.WarehouseID = '$warehouseId'
            GROUP BY 
                w.WarehouseID
        ";
        $capacityResult = $conn->query($capacityQuery);
        $totalCapacity = 0;
        $usedCapacity = 0;
        $capacityPercentage = 0;
        
        if ($capacityResult && $capacityResult->num_rows > 0) {
            $row = $capacityResult->fetch_assoc();
            $totalCapacity = $row['TotalCapacity'] ?? 0;
            $usedCapacity = $row['UsedCapacity'] ?? 0;
            $capacityPercentage = ($totalCapacity > 0) ? (($usedCapacity / $totalCapacity) * 100) : 0;
        }
        ?>
    <?php endif; ?>

    <div class="insights">
        <div class="sales">
            <span class="material-symbols-sharp">trending_up</span>
            <div class="middle">
                <div class="left">
                    <h3>Total Sales</h3>
                    <h1><?php echo formatCurrency($totalSales); ?></h1>
                </div>
            </div>
            <small class="text-muted">All Time</small>
        </div>
        <!-- END OF SALES -->

        <div class="expenses">
            <span class="material-symbols-sharp">inventory</span>
            <div class="middle">
                <div class="left">
                    <h3>Inventory Value</h3>
                    <h1><?php echo formatCurrency($inventoryValue); ?></h1>
                </div>
            </div>
            <small class="text-muted">Current Stock</small>
        </div>
        <!-- END OF EXPENSES -->

        <?php if (isset($_SESSION['selectedWarehouse']) && $_SESSION['selectedWarehouse'] != ''): ?>
        <div class="income">
            <span class="material-symbols-sharp">warehouse</span>
            <div class="middle">
                <div class="left">
                    <h3>Warehouse Capacity</h3>
                    <h1><?php echo number_format($capacityPercentage, 1); ?>%</h1>
                </div>
                <div class="progress">
                    <svg>
                        <circle cx="38" cy="38" r="36"></circle>
                    </svg>
                    <div class="number">
                        <p><?php echo number_format($capacityPercentage, 1); ?>%</p>
                    </div>
                </div>
            </div>
            <small class="text-muted"><?php echo number_format($usedCapacity, 2); ?> / <?php echo number_format($totalCapacity, 2); ?> Units</small>
        </div>
        <?php else: ?>
        <div class="income">
            <span class="material-symbols-sharp">local_shipping</span>
            <div class="middle">
                <div class="left">
                    <h3>Total Shipments</h3>
                    <h1><?php echo $totalShipments; ?></h1>
                </div>
            </div>
            <small class="text-muted">All Time</small>
        </div>
        <?php endif; ?>
        <!-- END OF INCOME -->
        
        <?php if ($spoilageTableExists): ?>
        <div class="spoilage">
            <span class="material-symbols-sharp">delete</span>
            <div class="middle">
                <div class="left">
                    <h3>Spoilage Value</h3>
                    <h1><?php echo formatCurrency($spoilageValue); ?></h1>
                </div>
            </div>
            <small class="text-muted">All Time</small>
        </div>
        <?php else: ?>
        <div class="expiring-insight">
            <span class="material-symbols-sharp">assignment_late</span>
            <div class="middle">
                <div class="left">
                    <h3>Expiring Soon</h3>
                    <h1><?php echo $expiringCount; ?> Items</h1>
                </div>
            </div>
            <small class="text-muted">Within 30 Days</small>
        </div>
        <?php endif; ?>
        <!-- END OF SPOILAGE -->
    </div>
    <!-- END OF INSIGHTS -->

    <!-- CLIMATE INSIGHTS -->
    <?php if ($latestClimate): ?>
    <div class="climate-overview">
        <h2>Latest Climate Readings</h2>
        <div class="climate-cards">
            <div class="climate-card">
                <div class="card-header">
                    <h3>
                        <span class="material-symbols-sharp">thermostat</span>
                        Temperature
                    </h3>
                </div>
                <div class="card-body">
                    <?php 
                        $tempStatus = 'normal';
                        if ($latestClimate['Temperature'] > $warehouseThresholds['max_temp']) {
                            $tempStatus = 'high';
                        } elseif ($latestClimate['Temperature'] < $warehouseThresholds['min_temp']) {
                            $tempStatus = 'low';
                        }
                    ?>
                    <div class="climate-value <?php echo $tempStatus; ?>">
                        <?php echo number_format($latestClimate['Temperature'], 1); ?>°C
                    </div>
                    <div class="climate-location">
                        Warehouse in <?php echo $latestClimate['City']; ?>
                    </div>
                    <div class="climate-time">
                        <?php echo date('M d, Y H:i', strtotime($latestClimate['RecordedAt'])); ?>
                    </div>
                </div>
            </div>

            <div class="climate-card">
                <div class="card-header">
                    <h3>
                        <span class="material-symbols-sharp">water_drop</span>
                        Humidity
                    </h3>
                </div>
                <div class="card-body">
                    <?php 
                        $humidityStatus = 'normal';
                        if ($latestClimate['Humidity'] > $warehouseThresholds['max_humidity']) {
                            $humidityStatus = 'high';
                        } elseif ($latestClimate['Humidity'] < $warehouseThresholds['min_humidity']) {
                            $humidityStatus = 'low';
                        }
                    ?>
                    <div class="climate-value <?php echo $humidityStatus; ?>">
                        <?php echo number_format($latestClimate['Humidity'], 1); ?>%
                    </div>
                    <div class="climate-location">
                        Warehouse in <?php echo $latestClimate['City']; ?>
                    </div>
                    <div class="climate-time">
                        <?php echo date('M d, Y H:i', strtotime($latestClimate['RecordedAt'])); ?>
                    </div>
                </div>
            </div>

            <div class="climate-card">
                <div class="card-header">
                    <h3>Climate Monitoring</h3>
                </div>
                <div class="card-body">
                    <div class="climate-value">
                        <a href="warehouse_climate.php" class="climate-link">
                            <span class="material-symbols-sharp">monitoring</span> View Climate Log
                        </a>
                    </div>
                    <div class="climate-desc">
                        Monitor temperature and humidity across all warehouses
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- END OF CLIMATE INSIGHTS -->

    <!-- EXPIRING INVENTORY SECTION -->
    <?php if ($expiringCount > 0): ?>
    <div class="expiring-inventory">
        <h2>Expiring Inventory Alert</h2>
        <div class="alert-card">
            <div class="alert-icon">
                <span class="material-symbols-sharp">warning</span>
            </div>
            <div class="alert-content">
                <h3><?php echo $expiringCount; ?> items are expiring within 30 days</h3>
                <p>Monitor these items closely to minimize spoilage and prioritize for shipment.</p>
                <a href="inventory.php" class="btn btn-warning">View Expiring Inventory</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- END OF EXPIRING INVENTORY SECTION -->

    <!-- SUMMARY CARDS -->
    <div class="summary-cards">
        <div class="summary-card">
            <span class="material-symbols-sharp">inventory</span>
            <div class="summary-info">
                <h3>Total Products</h3>
                <div class="summary-value"><?php echo $totalProducts; ?></div>
            </div>
        </div>

        <div class="summary-card">
            <span class="material-symbols-sharp">person</span>
            <div class="summary-info">
                <h3>Active Farmers</h3>
                <div class="summary-value"><?php echo $activeFarmers; ?></div>
            </div>
        </div>

        <div class="summary-card">
            <span class="material-symbols-sharp">warehouse</span>
            <div class="summary-info">
                <h3>Warehouses</h3>
                <div class="summary-value"><?php echo $totalWarehouses; ?></div>
            </div>
        </div>

        <div class="summary-card">
            <span class="material-symbols-sharp">shopping_basket</span>
            <div class="summary-info">
                <h3>Orders</h3>
                <div class="summary-value"><?php echo $totalOrders; ?></div>
            </div>
        </div>
        
        <?php if ($spoilageTableExists): ?>
        <div class="summary-card">
            <span class="material-symbols-sharp">recycling</span>
            <div class="summary-info">
                <h3>Spoilage Reports</h3>
                <div class="summary-value"><?php echo $totalSpoilage; ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($vehicleTableExists): ?>
        <div class="summary-card">
            <span class="material-symbols-sharp">directions_car</span>
            <div class="summary-info">
                <h3>Vehicles</h3>
                <div class="summary-value"><?php echo $totalVehicles; ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- END OF SUMMARY CARDS -->

    <div class="recent-orders">
        <h2>Recent Orders</h2>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Market</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No recent orders found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?php echo $order['OrderID']; ?></td>
                            <td><?php echo $order['CustomerName'] ?? 'N/A'; ?></td>
                            <td><?php echo $order['MarketName'] ?? 'N/A'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                            <td><?php echo formatCurrency($order['TotalAmount']); ?></td>
                            <td><span class="status completed">Completed</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="orders.php">Show All</a>
    </div>
    
    <?php if ($expiringCount > 0): ?>
    <div class="expiring-soon">
        <h2>Expiring Items (Within 30 Days)</h2>
        <table>
            <thead>
                <tr>
                    <th>Batch ID</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Warehouse</th>
                    <th>Expiry Date</th>
                    <th>Days Left</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $expiringItemsQuery = "
                    SELECT 
                        ws.WarehouseStockID,
                        b.BatchID,
                        c.CropName,
                        c.CropVariety,
                        b.Quantity,
                        ws.ExpiryDate,
                        w.City as WarehouseLocation,
                        w.WarehouseID
                    FROM 
                        warehouse_stock ws
                    JOIN 
                        warehouse w ON ws.WarehouseID = w.WarehouseID
                    JOIN 
                        batch b ON ws.BatchID = b.BatchID
                    LEFT JOIN 
                        crop c ON b.BatchID = c.BatchID
                    WHERE 
                        ws.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ";
                
                // Apply warehouse filter to expiring items query if selected
                if (isset($_SESSION['selectedWarehouse']) && $_SESSION['selectedWarehouse'] != '') {
                    $warehouseFilter = $_SESSION['selectedWarehouse'];
                    $expiringItemsQuery .= " AND ws.WarehouseID = '$warehouseFilter'";
                }
                
                $expiringItemsQuery .= " ORDER BY ws.ExpiryDate ASC LIMIT 5";
                $expiringItemsResult = $conn->query($expiringItemsQuery);
                
                if ($expiringItemsResult && $expiringItemsResult->num_rows > 0):
                    while ($item = $expiringItemsResult->fetch_assoc()):
                        $today = new DateTime();
                        $expiryDate = new DateTime($item['ExpiryDate']);
                        $daysLeft = $today->diff($expiryDate)->days;
                ?>
                    <tr>
                        <td><?php echo $item['BatchID']; ?></td>
                        <td>
                            <?php echo $item['CropName'] ?? 'Unknown'; ?>
                            <?php if (!empty($item['CropVariety'])): ?>
                                (Variety: <?php echo $item['CropVariety']; ?>)
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['Quantity']; ?> units</td>
                        <td><?php echo $item['WarehouseLocation'] . ' (' . $item['WarehouseID'] . ')'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['ExpiryDate'])); ?></td>
                        <td class="days-left <?php echo ($daysLeft <= 7) ? 'critical' : 'warning'; ?>">
                            <?php echo $daysLeft; ?> days
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="prioritize_shipment.php?stock_id=<?php echo $item['WarehouseStockID']; ?>" class="btn-action priority">
                                    <span class="material-symbols-sharp">priority_high</span>
                                </a>
                                <a href="batch_details.php?id=<?php echo $item['BatchID']; ?>" class="btn-action view">
                                    <span class="material-symbols-sharp">visibility</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php 
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No expiring items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="inventory.php">Show All Inventory</a>
    </div>
    <?php endif; ?>
    
    <?php if ($spoilageTableExists): ?>
    <div class="recent-spoilage">
        <h2>Recent Spoilage Reports</h2>
        <table>
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Batch ID</th>
                    <th>Quantity</th>
                    <th>Date Reported</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get recent spoilage reports
                $query = "SELECT sr.ReportID, sr.BatchID, sr.SpoiledQuantity, sr.ReportDate, sr.EstimatedValue, sr.Status 
                         FROM spoilage_report sr 
                         ORDER BY sr.ReportDate DESC LIMIT 5";
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo $row['ReportID']; ?></td>
                        <td><?php echo $row['BatchID']; ?></td>
                        <td><?php echo $row['SpoiledQuantity']; ?> kg</td>
                        <td><?php echo date('M d, Y', strtotime($row['ReportDate'])); ?></td>
                        <td><?php echo formatCurrency($row['EstimatedValue']); ?></td>
                        <td><span class="status <?php echo strtolower($row['Status']); ?>"><?php echo $row['Status']; ?></span></td>
                    </tr>
                <?php 
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No spoilage reports found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="spoilage.php">Show All</a>
    </div>
    <?php endif; ?>
    
    <!-- RECENT VEHICLES -->
    <?php if ($vehicleTableExists): ?>
    <div class="recent-vehicles">
        <h2>Recent Vehicles</h2>
        <table>
            <thead>
                <tr>
                    <th>Vehicle ID</th>
                    <th>Type</th>
                    <th>Registration No.</th>
                    <th>Capacity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get recent vehicles
                $query = "SELECT VehicleID, VehicleType, RegistrationNumber, Capacity, Status 
                         FROM vehicle 
                         ORDER BY VehicleID DESC LIMIT 5";
                $result = $conn->query($query);
                if ($result && $result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                    <tr>
                        <td><?php echo $row['VehicleID']; ?></td>
                        <td><?php echo $row['VehicleType']; ?></td>
                        <td><?php echo $row['RegistrationNumber']; ?></td>
                        <td><?php echo $row['Capacity']; ?> kg</td>
                        <td><span class="status <?php echo strtolower($row['Status']); ?>"><?php echo $row['Status']; ?></span></td>
                    </tr>
                <?php 
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No vehicles found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="vehicles.php">Show All</a>
    </div>
    <?php endif; ?>
    <!-- END OF RECENT VEHICLES -->
</main>

<style>
/* Climate Cards for Dashboard */
.climate-overview {
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.climate-overview h2 {
    margin-bottom: 1rem;
    color: var(--color-dark);
}

.climate-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.climate-card {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    transition: all 0.3s ease;
}

.climate-card:hover {
    box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.1);
}

.card-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.card-header h3 {
    display: flex;
    align-items: center;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--color-dark);
}

.card-header h3 .material-symbols-sharp {
    margin-right: 0.5rem;
}

.card-body {
    display: flex;
    flex-direction: column;
}

.climate-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.climate-value.high {
    color: var(--color-danger);
}

.climate-value.low {
    color: var(--color-warning);
}

.climate-value.normal {
    color: var(--color-success);
}

.climate-location {
    font-size: 0.9rem;
    color: var(--color-dark-variant);
    margin-bottom: 0.25rem;
}

.climate-time {
    font-size: 0.8rem;
    color: var(--color-info-dark);
}

.climate-link {
    display: flex;
    align-items: center;
    color: var(--color-primary);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.climate-link:hover {
    color: var(--color-primary-variant);
}

.climate-link .material-symbols-sharp {
    margin-right: 0.5rem;
}

.climate-desc {
    font-size: 0.85rem;
    color: var(--color-info-dark);
    margin-top: 0.5rem;
}

/* Expiring Inventory Alert */
.expiring-inventory {
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.expiring-inventory h2 {
    margin-bottom: 1rem;
    color: var(--color-dark);
}

.alert-card {
    background: var(--color-white);
    border-radius: var(--card-border-radius);
    padding: 1.5rem;
    box-shadow: var(--box-shadow);
    display: flex;
    align-items: center;
}

.alert-icon {
    background: rgba(255, 165, 0, 0.15);
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1.5rem;
}

.alert-icon .material-symbols-sharp {
    font-size: 1.8rem;
    color: var(--color-warning);
}

.alert-content h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--color-dark);
    margin-bottom: 0.5rem;
}

.alert-content p {
    font-size: 0.9rem;
    color: var(--color-dark-variant);
    margin-bottom: 1rem;
}

.btn-warning {
    background: var(--color-warning);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.4rem;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-block;
    text-decoration: none;
}

.btn-warning:hover {
    background: var(--color-warning-light);
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.summary-card:hover {
    box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.1);
}

.summary-card .material-symbols-sharp {
    font-size: 2rem;
    color: var(--color-primary);
    margin-right: 1rem;
}

.summary-info h3 {
    font-size: 0.9rem;
    color: var(--color-dark-variant);
    margin-bottom: 0.25rem;
}

.summary-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-dark);
}

/* Expiring Items Table */
.expiring-soon {
    margin-top: 2rem;
}

.days-left {
    font-weight: 600;
}

.days-left.critical {
    color: var(--color-danger);
}

.days-left.warning {
    color: var(--color-warning);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: var(--color-light);
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-action.priority {
    background: rgba(255, 165, 0, 0.15);
    color: var(--color-warning);
}

.btn-action.view {
    background: rgba(41, 128, 185, 0.15);
    color: var(--color-primary);
}

.btn-action:hover {
    transform: scale(1.1);
}

/* Warehouse Selector */
.filters {
    margin: 1.5rem 0;
}

.filter-container {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
}

.warehouse-filter {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.form-control {
    flex: 1;
}

.form-control label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--color-dark);
}

.form-control select {
    width: 100%;
    padding: 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid var(--color-info-light);
    background: var(--color-background);
    color: var(--color-dark);
    font-size: 0.9rem;
}

.btn-primary {
    background: var(--color-primary);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.4rem;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: var(--color-primary-variant);
}

#current-warehouse {
    background: var(--color-light);
    padding: 0.75rem 1.5rem;
    border-radius: var(--card-border-radius);
    margin-bottom: 1.5rem;
}

#current-warehouse h3 {
    font-size: 1rem;
    color: var(--color-dark);
    font-weight: 500;
}
/* Climate Cards for Dashboard */
.climate-overview {
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.climate-overview h2 {
    margin-bottom: 1rem;
    color: var(--color-dark);
}

.climate-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.climate-card {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    transition: all 0.3s ease;
}

.climate-card:hover {
    box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.1);
}

.card-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.card-header h3 {
    display: flex;
    align-items: center;
    font-size: 1rem;
    color: var(--color-dark);
}

.card-header h3 span {
    margin-right: 0.5rem;
}

.card-body {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.climate-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--color-dark);
}

.climate-value.high {
    color: var(--color-danger);
}

.climate-value.low {
    color: #3f51b5;
}

.climate-location, .climate-time, .climate-desc {
    color: var(--color-dark-variant);
    font-size: 0.85rem;
    text-align: center;
}

.climate-desc {
    margin-top: 0.5rem;
}

.climate-link {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary);
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.climate-link:hover {
    color: var(--color-primary-variant);
}

.climate-link span {
    margin-right: 0.5rem;
}

/* Vehicle Status Styles */
.status.active {
    background: var(--color-success);
    color: var(--color-white);
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
}

.status.maintenance {
    background: var(--color-warning);
    color: var(--color-white);
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
}

.status.inactive {
    background: var(--color-danger);
    color: var(--color-white);
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
}

.recent-vehicles {
    margin-top: 2rem;
}

.recent-vehicles h2 {
    margin-bottom: 0.8rem;
}

.recent-vehicles table {
    background-color: var(--color-white);
    width: 100%;
    border-radius: var(--card-border-radius);
    padding: var(--card-padding);
    text-align: center;
    box-shadow: var(--box-shadow);
    transition: all 0.3s ease;
}

.recent-vehicles table:hover {
    box-shadow: none;
}

.recent-vehicles table tbody td {
    height: 2.8rem;
    border-bottom: 1px solid var(--color-light);
    color: var(--color-dark-variant);
}

.recent-vehicles table tbody tr:last-child td {
    border: none;
}

.recent-vehicles a {
    text-align: center;
    display: block;
    margin: 1rem auto;
    color: var(--color-primary);
}

/* Spoilage Styles */
.spoilage {
    background: #ffd9d9;
    padding: var(--card-padding);
    border-radius: var(--card-border-radius);
    margin-top: 1rem;
    box-shadow: var(--box-shadow);
    transition: all 300ms ease;
}

.spoilage:hover {
    box-shadow: none;
}

.spoilage span {
    background: var(--color-danger);
    padding: 0.5rem;
    border-radius: 50%;
    color: var(--color-white);
    font-size: 2rem;
}

.spoilage .middle {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.spoilage h3 {
    margin: 1rem 0 0.6rem;
    font-size: 1rem;
}

.spoilage h1 {
    font-size: 1.8rem;
    color: var(--color-danger);
}

.spoilage .left {
    margin-bottom: 0.6rem;
    display: flex;
    flex-direction: column;
}

.recent-spoilage {
    margin-top: 2rem;
}

.recent-spoilage h2 {
    margin-bottom: 0.8rem;
}

.recent-spoilage table {
    background-color: var(--color-white);
    width: 100%;
    border-radius: var(--card-border-radius);
    padding: var(--card-padding);
    text-align: center;
    box-shadow: var(--box-shadow);
    transition: all 0.3s ease;
}

.recent-spoilage table:hover {
    box-shadow: none;
}

.recent-spoilage table tbody td {
    height: 2.8rem;
    border-bottom: 1px solid var(--color-light);
    color: var(--color-dark-variant);
}

.recent-spoilage table tbody tr:last-child td {
    border: none;
}

.recent-spoilage a {
    text-align: center;
    display: block;
    margin: 1rem auto;
    color: var(--color-primary);
}

.status.pending {
    background: var(--color-warning);
    color: var(--color-white);
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
}

.status.verified {
    background: var(--color-success);
    color: var(--color-white);
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
}

.status.disposed {
    background: var(--color-danger);
    color: var(--color-white);
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
}
</style>

<?php
// Include footer
include('includes/footer.php');
?>
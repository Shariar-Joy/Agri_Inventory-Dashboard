<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Inventory";

// Handle actions (if any)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Handle filtering by warehouse
    if ($action === 'filter_warehouse' && isset($_POST['warehouse_id'])) {
        $_SESSION['selected_warehouse_filter'] = $_POST['warehouse_id'];
    }
    
    // Clear filter
    if ($action === 'clear_filter') {
        unset($_SESSION['selected_warehouse_filter']);
    }
}

// Include header
include('includes/header.php');

// Fetch all warehouses for filter dropdown
$warehousesQuery = "SELECT WarehouseID, City FROM warehouse ORDER BY City";
$warehousesResult = $conn->query($warehousesQuery);
$warehouses = [];
if ($warehousesResult && $warehousesResult->num_rows > 0) {
    while ($row = $warehousesResult->fetch_assoc()) {
        $warehouses[] = $row;
    }
}

// Build inventory query with appropriate joins
$inventoryQuery = "
    SELECT 
        ws.WarehouseStockID,
        b.BatchID,
        c.CropName,
        c.CropVariety,
        c.CropType,
        b.Quantity,
        ws.EntryDate,
        ws.ExpiryDate,
        w.WarehouseID,
        w.City as WarehouseLocation,
        f.Name as FarmerName,
        hs.HarvestID
    FROM 
        warehouse_stock ws
    JOIN 
        warehouse w ON ws.WarehouseID = w.WarehouseID
    JOIN 
        batch b ON ws.BatchID = b.BatchID
    LEFT JOIN 
        crop c ON b.BatchID = c.BatchID
    LEFT JOIN 
        harvest_session hs ON b.HarvestID = hs.HarvestID
    LEFT JOIN 
        farmer f ON hs.FarmerID = f.FarmerID
";

// Apply warehouse filter if selected
if (isset($_SESSION['selected_warehouse_filter']) && $_SESSION['selected_warehouse_filter'] != '') {
    $warehouseFilter = $_SESSION['selected_warehouse_filter'];
    $inventoryQuery .= " WHERE w.WarehouseID = '$warehouseFilter'";
}

$inventoryQuery .= " ORDER BY ws.EntryDate DESC";
$inventoryResult = $conn->query($inventoryQuery);

// Calculate total inventory value
$totalValueQuery = "
    SELECT 
        SUM(b.Quantity * IFNULL(
            (SELECT AVG(p.UnitPrice) FROM purchase p 
             JOIN batch_purchase bp ON p.PurchaseID = bp.PurchaseID 
             WHERE bp.BatchID = b.BatchID),
            0
        )) as TotalValue
    FROM 
        warehouse_stock ws
    JOIN 
        batch b ON ws.BatchID = b.BatchID
";

// Apply warehouse filter to total value query if selected
if (isset($_SESSION['selected_warehouse_filter']) && $_SESSION['selected_warehouse_filter'] != '') {
    $warehouseFilter = $_SESSION['selected_warehouse_filter'];
    $totalValueQuery .= " WHERE ws.WarehouseID = '$warehouseFilter'";
}

$totalValueResult = $conn->query($totalValueQuery);
$totalInventoryValue = 0;
if ($totalValueResult && $totalValueResult->num_rows > 0) {
    $row = $totalValueResult->fetch_assoc();
    $totalInventoryValue = $row['TotalValue'] ?? 0;
}

// Get expiring soon inventory (within 30 days)
$expiringQuery = "
    SELECT COUNT(*) as ExpiringCount
    FROM warehouse_stock
    WHERE ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
";
// Apply warehouse filter to expiring query if selected
if (isset($_SESSION['selected_warehouse_filter']) && $_SESSION['selected_warehouse_filter'] != '') {
    $warehouseFilter = $_SESSION['selected_warehouse_filter'];
    $expiringQuery .= " AND WarehouseID = '$warehouseFilter'";
}

$expiringResult = $conn->query($expiringQuery);
$expiringCount = 0;
if ($expiringResult && $expiringResult->num_rows > 0) {
    $row = $expiringResult->fetch_assoc();
    $expiringCount = $row['ExpiringCount'];
}
?>

<main>
    <h1>Inventory Management</h1>

    <div class="date">
        <input type="date" value="<?php echo date('Y-m-d'); ?>">
    </div>

    <div class="insights">
        <div class="sales">
            <span class="material-symbols-sharp">inventory</span>
            <div class="middle">
                <div class="left">
                    <h3>Total Inventory Value</h3>
                    <h1><?php echo formatCurrency($totalInventoryValue); ?></h1>
                </div>
            </div>
            <small class="text-muted">Current Stock Value</small>
        </div>
        
        <div class="expenses">
            <span class="material-symbols-sharp">assignment_late</span>
            <div class="middle">
                <div class="left">
                    <h3>Expiring Soon</h3>
                    <h1><?php echo $expiringCount; ?> Items</h1>
                </div>
            </div>
            <small class="text-muted">Within 30 Days</small>
        </div>
        
        <?php if (isset($_SESSION['selected_warehouse_filter']) && $_SESSION['selected_warehouse_filter'] != ''): ?>
            <?php
            // Get warehouse capacity utilization
            $warehouseId = $_SESSION['selected_warehouse_filter'];
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
                <span class="material-symbols-sharp">category</span>
                <div class="middle">
                    <div class="left">
                        <h3>Total Products</h3>
                        <?php
                        $productsQuery = "SELECT COUNT(DISTINCT b.BatchID) as TotalProducts FROM warehouse_stock ws JOIN batch b ON ws.BatchID = b.BatchID";
                        if (isset($_SESSION['selected_warehouse_filter']) && $_SESSION['selected_warehouse_filter'] != '') {
                            $warehouseFilter = $_SESSION['selected_warehouse_filter'];
                            $productsQuery .= " WHERE ws.WarehouseID = '$warehouseFilter'";
                        }
                        $productsResult = $conn->query($productsQuery);
                        $totalProducts = 0;
                        if ($productsResult && $productsResult->num_rows > 0) {
                            $row = $productsResult->fetch_assoc();
                            $totalProducts = $row['TotalProducts'];
                        }
                        ?>
                        <h1><?php echo $totalProducts; ?></h1>
                    </div>
                </div>
                <small class="text-muted">In Current Inventory</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Warehouse Filter Form -->
    <div class="filters">
        <div class="filter-container">
            <form action="inventory.php?action=filter_warehouse" method="POST" class="warehouse-filter">
                <div class="form-control">
                    <label for="warehouse_id">Filter by Warehouse:</label>
                    <select name="warehouse_id" id="warehouse_id">
                        <option value="">All Warehouses</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['WarehouseID']; ?>" <?php echo (isset($_SESSION['selected_warehouse_filter']) && $_SESSION['selected_warehouse_filter'] == $warehouse['WarehouseID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($warehouse['City'] . ' (ID: ' . $warehouse['WarehouseID'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="inventory.php?action=clear_filter" class="btn btn-secondary">Clear Filter</a>
            </form>
        </div>
    </div>

    <div class="recent-orders">
        <h2>Current Inventory</h2>
        <table>
            <thead>
                <tr>
                    <th>Stock ID</th>
                    <th>Batch ID</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Warehouse</th>
                    <th>Entry Date</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($inventoryResult && $inventoryResult->num_rows > 0): ?>
                    <?php while ($item = $inventoryResult->fetch_assoc()): ?>
                        <?php
                        // Determine status based on expiry date
                        $status = 'normal';
                        $statusLabel = 'Normal';
                        $today = new DateTime();
                        $expiryDate = new DateTime($item['ExpiryDate']);
                        $daysUntilExpiry = $today->diff($expiryDate)->days;
                        
                        if ($today > $expiryDate) {
                            $status = 'expired';
                            $statusLabel = 'Expired';
                        } elseif ($daysUntilExpiry <= 30) {
                            $status = 'expiring';
                            $statusLabel = 'Expiring Soon';
                        }
                        ?>
                        <tr>
                            <td><?php echo $item['WarehouseStockID']; ?></td>
                            <td><?php echo $item['BatchID']; ?></td>
                            <td>
                                <?php echo $item['CropName'] ?? 'Unknown'; ?>
                                <?php if (!empty($item['CropVariety'])): ?>
                                    (Variety: <?php echo $item['CropVariety']; ?>)
                                <?php endif; ?>
                                <div class="product-details">
                                    <small>Type: <?php echo $item['CropType'] ?? 'N/A'; ?></small>
                                    <?php if (!empty($item['FarmerName'])): ?>
                                        <small>From: <?php echo $item['FarmerName']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo $item['Quantity']; ?> units</td>
                            <td><?php echo $item['WarehouseLocation'] . ' (' . $item['WarehouseID'] . ')'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($item['EntryDate'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($item['ExpiryDate'])); ?></td>
                            <td><span class="status <?php echo $status; ?>"><?php echo $statusLabel; ?></span></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="batch_details.php?id=<?php echo $item['BatchID']; ?>" class="btn-action view">
                                        <span class="material-symbols-sharp">visibility</span>
                                    </a>
                                    <a href="edit_stock.php?id=<?php echo $item['WarehouseStockID']; ?>" class="btn-action edit">
                                        <span class="material-symbols-sharp">edit</span>
                                    </a>
                                    <?php if ($status === 'expired'): ?>
                                        <a href="report_spoilage.php?batch_id=<?php echo $item['BatchID']; ?>" class="btn-action report">
                                            <span class="material-symbols-sharp">report</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No inventory items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Expiring Soon Section -->
    <?php if ($expiringCount > 0): ?>
        <div class="expiring-soon">
            <h2>Expiring Soon (Within 30 Days)</h2>
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
                    if (isset($_SESSION['selected_warehouse_filter']) && $_SESSION['selected_warehouse_filter'] != '') {
                        $warehouseFilter = $_SESSION['selected_warehouse_filter'];
                        $expiringItemsQuery .= " AND ws.WarehouseID = '$warehouseFilter'";
                    }
                    
                    $expiringItemsQuery .= " ORDER BY ws.ExpiryDate ASC";
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
        </div>
    <?php endif; ?>
</main>

<style>
/* Inventory Page Styles */
.filters {
    margin-bottom: 2rem;
}

.filter-container {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
}

.warehouse-filter {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.warehouse-filter .form-control {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.warehouse-filter select {
    padding: 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid var(--color-light);
    background: var(--color-white);
    flex: 1;
}

.btn {
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.4rem;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--color-primary);
    color: var(--color-white);
}

.btn-primary:hover {
    background: var(--color-primary-variant);
}

.btn-secondary {
    background: var(--color-light);
    color: var(--color-dark);
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-secondary:hover {
    background: var(--color-dark-variant);
    color: var(--color-white);
}

.product-details {
    display: flex;
    flex-direction: column;
    margin-top: 0.2rem;
}

.product-details small {
    color: var(--color-dark-variant);
    font-size: 0.75rem;
}

.status {
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
    font-size: 0.8rem;
}

.status.normal {
    background: var(--color-success);
    color: var(--color-white);
}

.status.expiring {
    background: var(--color-warning);
    color: var(--color-white);
}

.status.expired {
    background: var(--color-danger);
    color: var(--color-white);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.btn-action {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.8rem;
    height: 1.8rem;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-action.view {
    background: var(--color-primary);
    color: var(--color-white);
}

.btn-action.edit {
    background: var(--color-warning);
    color: var(--color-white);
}

.btn-action.report {
    background: var(--color-danger);
    color: var(--color-white);
}

.btn-action.priority {
    background: #ff9800;
    color: var(--color-white);
}

.btn-action:hover {
    opacity: 0.8;
    transform: scale(1.1);
}

.expiring-soon {
    margin-top: 2rem;
}

.expiring-soon h2 {
    margin-bottom: 0.8rem;
    color: var(--color-warning);
}

.expiring-soon table {
    background-color: var(--color-white);
    width: 100%;
    border-radius: var(--card-border-radius);
    padding: var(--card-padding);
    text-align: center;
    box-shadow: var(--box-shadow);
    transition: all 0.3s ease;
}

.expiring-soon table:hover {
    box-shadow: none;
}

.days-left {
    font-weight: 600;
}

.days-left.warning {
    color: var(--color-warning);
}

.days-left.critical {
    color: var(--color-danger);
}

.progress {
    position: relative;
    width: 92px;
    height: 92px;
    border-radius: 50%;
}

.progress svg {
    width: 7rem;
    height: 7rem;
}

.progress svg circle {
    fill: none;
    stroke: var(--color-primary);
    stroke-width: 14;
    stroke-linecap: round;
    transform: translate(5px, 5px);
    stroke-dasharray: 110;
    stroke-dashoffset: 92;
}

.progress .number {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.progress .number p {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--color-primary);
}

/* Responsive Design */
@media screen and (max-width: 1200px) {
    main {
        padding: 0 1rem;
    }
    
    .warehouse-filter {
        flex-direction: column;
        align-items: stretch;
    }
    
    .warehouse-filter .form-control {
        flex-direction: column;
        align-items: stretch;
    }
}

@media screen and (max-width: 768px) {
    .status {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-action {
        width: 1.5rem;
        height: 1.5rem;
        font-size: 0.7rem;
    }
}
</style>

<script>
// Inventory page script
document.addEventListener('DOMContentLoaded', function() {
    // For circle progress animation on warehouse capacity
    const progressCircles = document.querySelectorAll('.progress svg circle');
    progressCircles.forEach(circle => {
        // Get the percentage from the number inside
        const numberElement = circle.closest('.progress').querySelector('.number p');
        if (numberElement) {
            const percentage = parseFloat(numberElement.textContent);
            // Calculate stroke-dashoffset
            // 110 is the total circumference (stroke-dasharray value)
            // Subtract the percentage to fill the circle
            const offset = 110 - ((percentage / 100) * 110);
            circle.style.strokeDashoffset = offset;
            
            // Change color based on capacity
            if (percentage > 90) {
                circle.style.stroke = '#f44336'; // Red when nearly full
            } else if (percentage > 70) {
                circle.style.stroke = '#ff9800'; // Orange when over 70%
            }
        }
    });
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
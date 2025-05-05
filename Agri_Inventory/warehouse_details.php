<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Check if warehouse ID is provided
if (!isset($_GET['id'])) {
    // Redirect to warehouses page if no ID provided
    header("Location: warehouses.php");
    exit();
}

$warehouseId = sanitizeInput($_GET['id']);

// Get warehouse details
$query = "SELECT * FROM warehouse WHERE WarehouseID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $warehouseId);
$stmt->execute();
$result = $stmt->get_result();

// Check if warehouse exists
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Warehouse not found.";
    header("Location: warehouses.php");
    exit();
}

$warehouse = $result->fetch_assoc();
$stmt->close();

// Get warehouse inventory statistics
$statsQuery = "SELECT 
                COUNT(ws.WarehouseStockID) as TotalStockItems,
                SUM(ws.Quantity) as TotalQuantity,
                COUNT(DISTINCT b.BatchID) as TotalBatches
              FROM warehouse_stock ws
              LEFT JOIN batch b ON ws.BatchID = b.BatchID
              WHERE ws.WarehouseID = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("s", $warehouseId);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();
$statsStmt->close();

// Get warehouse stock items
$stockQuery = "SELECT ws.*, c.CropName, c.CropType, c.CropVariety, c.GrowingSeason
               FROM warehouse_stock ws
               LEFT JOIN batch b ON ws.BatchID = b.BatchID
               LEFT JOIN crop c ON b.BatchID = c.BatchID
               WHERE ws.WarehouseID = ?
               ORDER BY ws.EntryDate DESC";
$stockStmt = $conn->prepare($stockQuery);
$stockStmt->bind_param("s", $warehouseId);
$stockStmt->execute();
$stockResult = $stockStmt->get_result();

$stockItems = [];
while ($stockRow = $stockResult->fetch_assoc()) {
    $stockItems[] = $stockRow;
}
$stockStmt->close();

// Get climate logs
$climateQuery = "SELECT * FROM warehouse_climate_log 
                WHERE WarehouseID = ?
                ORDER BY RecordedAt DESC
                LIMIT 50";  // Limit to prevent too much data
$climateStmt = $conn->prepare($climateQuery);
$climateStmt->bind_param("s", $warehouseId);
$climateStmt->execute();
$climateResult = $climateStmt->get_result();

$climateLogs = [];
while ($climateRow = $climateResult->fetch_assoc()) {
    $climateLogs[] = $climateRow;
}
$climateStmt->close();

// Calculate climate averages
$avgClimateQuery = "SELECT 
                    AVG(Temperature) as avgTemperature,
                    MAX(Temperature) as maxTemperature,
                    MIN(Temperature) as minTemperature,
                    AVG(Humidity) as avgHumidity,
                    MAX(Humidity) as maxHumidity,
                    MIN(Humidity) as minHumidity
                  FROM warehouse_climate_log 
                  WHERE WarehouseID = ?";
$avgClimateStmt = $conn->prepare($avgClimateQuery);
$avgClimateStmt->bind_param("s", $warehouseId);
$avgClimateStmt->execute();
$avgClimateResult = $avgClimateStmt->get_result();
$climateStats = $avgClimateResult->fetch_assoc();
$avgClimateStmt->close();

// Get storage type name
$storageTypes = [
    1 => 'Cold Storage',
    2 => 'Dry Storage',
    3 => 'Controlled Atmosphere',
    4 => 'Frozen Storage',
    5 => 'Standard Warehouse'
];

$storageTypeName = $storageTypes[$warehouse['StorageType']] ?? 'Unknown';

// Set page title
$pageTitle = "Warehouse Details: " . $warehouseId;
include('includes/header.php');
?>

<main>
    <div class="page-header">
        <h1>Warehouse Details</h1>
        <div class="header-actions">
            <a href="warehouses.php" class="btn btn-secondary">Back to Warehouses</a>
            <a href="warehouses.php?action=edit&id=<?php echo $warehouseId; ?>" class="btn btn-primary">Edit Warehouse</a>
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
    
    <!-- Warehouse Information Card -->
    <div class="detail-card">
        <div class="card-header">
            <h2>Warehouse #<?php echo $warehouse['WarehouseID']; ?></h2>
            <span class="warehouse-type"><?php echo $storageTypeName; ?></span>
        </div>
        
        <div class="card-body">
            <div class="detail-section">
                <h3>Warehouse Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value"><?php echo $warehouse['Street'] . ', ' . $warehouse['City'] . ', ' . $warehouse['ZipCode']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Capacity:</span>
                        <span class="detail-value"><?php echo $warehouse['Capacity']; ?> units</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Storage Type:</span>
                        <span class="detail-value"><?php echo $storageTypeName; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Inventory Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['TotalStockItems'] ?? 0; ?></div>
                        <div class="stat-label">Stock Items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['TotalQuantity'] ?? 0; ?></div>
                        <div class="stat-label">Total Quantity</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format(($stats['TotalQuantity'] / $warehouse['Capacity']) * 100, 1); ?>%</div>
                        <div class="stat-label">Capacity Used</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['TotalBatches'] ?? 0; ?></div>
                        <div class="stat-label">Unique Batches</div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($climateStats)): ?>
            <div class="detail-section">
                <h3>Climate Conditions Summary</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Avg. Temperature:</span>
                        <span class="detail-value"><?php echo number_format($climateStats['avgTemperature'], 1); ?>°C</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Temperature Range:</span>
                        <span class="detail-value"><?php echo number_format($climateStats['minTemperature'], 1); ?>°C - <?php echo number_format($climateStats['maxTemperature'], 1); ?>°C</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Avg. Humidity:</span>
                        <span class="detail-value"><?php echo number_format($climateStats['avgHumidity'], 1); ?>%</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Humidity Range:</span>
                        <span class="detail-value"><?php echo number_format($climateStats['minHumidity'], 1); ?>% - <?php echo number_format($climateStats['maxHumidity'], 1); ?>%</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Warehouse Inventory -->
    <div class="data-table">
        <div class="table-header">
            <h2>Current Inventory</h2>
            <div class="search-container">
                <input type="text" id="inventorySearch" placeholder="Search inventory...">
            </div>
        </div>
        
        <?php if (empty($stockItems)): ?>
            <div class="no-data-message">
                <p>This warehouse currently has no inventory items.</p>
                <a href="inventory.php?warehouse=<?php echo $warehouseId; ?>" class="btn btn-primary">Add Inventory</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Stock ID</th>
                        <th>Batch ID</th>
                        <th>Crop</th>
                        <th>Quantity</th>
                        <th>Entry Date</th>
                        <th>Expiry Date</th>
                        <th>Storage Duration</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockItems as $item): ?>
                        <?php 
                            $entryDate = new DateTime($item['EntryDate']);
                            $expiryDate = new DateTime($item['ExpiryDate']);
                            $currentDate = new DateTime();
                            
                            $daysStored = $currentDate->diff($entryDate)->days;
                            $daysUntilExpiry = $currentDate->diff($expiryDate)->days;
                            $expiryStatus = '';
                            
                            if ($expiryDate < $currentDate) {
                                $expiryStatus = 'expired';
                            } elseif ($daysUntilExpiry <= 7) {
                                $expiryStatus = 'expiring-soon';
                            }
                        ?>
                        <tr class="<?php echo $expiryStatus; ?>">
                            <td><?php echo $item['WarehouseStockID']; ?></td>
                            <td><?php echo $item['BatchID']; ?></td>
                            <td>
                                <?php if (!empty($item['CropName'])): ?>
                                    <?php echo $item['CropName']; ?> 
                                    <span class="crop-details">
                                        (<?php echo $item['CropType']; ?>, Variety: <?php echo $item['CropVariety']; ?>)
                                    </span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['Quantity']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($item['EntryDate'])); ?></td>
                            <td class="<?php echo $expiryStatus; ?>">
                                <?php echo date('M j, Y', strtotime($item['ExpiryDate'])); ?>
                                <?php if ($expiryStatus === 'expired'): ?>
                                    <span class="status-badge expired">Expired</span>
                                <?php elseif ($expiryStatus === 'expiring-soon'): ?>
                                    <span class="status-badge expiring-soon">Expiring Soon</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $daysStored; ?> days
                                <?php if ($daysStored > 90): ?>
                                    <span class="status-badge warning">Long Storage</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <a href="batch_details.php?id=<?php echo $item['BatchID']; ?>" class="btn btn-primary btn-sm">View Batch</a>
                                <a href="inventory.php?action=edit&id=<?php echo $item['WarehouseStockID']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Climate Logs -->
    <?php if (!empty($climateLogs)): ?>
    <div class="data-table">
        <div class="table-header">
            <h2>Climate Logs</h2>
            <div class="search-container">
                <input type="text" id="climateSearch" placeholder="Search logs...">
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Recorded At</th>
                    <th>Temperature (°C)</th>
                    <th>Humidity (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($climateLogs as $log): ?>
                    <?php
                        $tempClass = '';
                        if ($log['Temperature'] > 30) {
                            $tempClass = 'high-temp';
                        } elseif ($log['Temperature'] < 0) {
                            $tempClass = 'low-temp';
                        }
                        
                        $humidityClass = '';
                        if ($log['Humidity'] > 75) {
                            $humidityClass = 'high-humidity';
                        } elseif ($log['Humidity'] < 30) {
                            $humidityClass = 'low-humidity';
                        }
                    ?>
                    <tr>
                        <td><?php echo $log['ClimateLogID']; ?></td>
                        <td><?php echo date('M j, Y H:i', strtotime($log['RecordedAt'])); ?></td>
                        <td class="<?php echo $tempClass; ?>"><?php echo number_format($log['Temperature'], 1); ?>°C</td>
                        <td class="<?php echo $humidityClass; ?>"><?php echo number_format($log['Humidity'], 1); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (count($climateLogs) >= 50): ?>
            <div class="table-footer">
                <p>Showing the 50 most recent climate logs. <a href="climate_logs.php?warehouse=<?php echo $warehouseId; ?>">View all logs</a></p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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

.warehouse-type {
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
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-item {
    text-align: center;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 600;
    color: #0d6efd;
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.no-data-message {
    text-align: center;
    padding: 30px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.expired {
    color: #dc3545;
}

.expiring-soon {
    color: #fd7e14;
}

.status-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 5px;
}

.status-badge.expired {
    background-color: #dc3545;
    color: white;
}

.status-badge.expiring-soon {
    background-color: #fd7e14;
    color: white;
}

.status-badge.warning {
    background-color: #ffc107;
    color: #212529;
}

.crop-details {
    font-size: 0.8rem;
    color: #6c757d;
}

.high-temp {
    color: #dc3545;
}

.low-temp {
    color: #0dcaf0;
}

.high-humidity {
    color: #0d6efd;
}

.low-humidity {
    color: #6c757d;
}

.table-footer {
    text-align: center;
    padding: 10px;
    background-color: #f8f9fa;
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality for inventory table
    const inventorySearch = document.getElementById('inventorySearch');
    if (inventorySearch) {
        inventorySearch.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.data-table:nth-of-type(2) tbody tr');
            
            tableRows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                const isVisible = text.indexOf(searchTerm) > -1;
                row.style.display = isVisible ? '' : 'none';
            });
        });
    }
    
    // Search functionality for climate logs table
    const climateSearch = document.getElementById('climateSearch');
    if (climateSearch) {
        climateSearch.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.data-table:nth-of-type(3) tbody tr');
            
            tableRows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                const isVisible = text.indexOf(searchTerm) > -1;
                row.style.display = isVisible ? '' : 'none';
            });
        });
    }
});
</script>

<?php
// Include footer
//include('includes/footer.php');
?>
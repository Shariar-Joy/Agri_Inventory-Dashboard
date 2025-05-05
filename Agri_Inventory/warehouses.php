<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new warehouse
        if ($_POST['action'] == 'add') {
            $warehouseId = generateID('WH', 8);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            $capacity = floatval($_POST['capacity']);
            $storageType = intval($_POST['storageType']);
            
            $query = "INSERT INTO warehouse (WarehouseID, Street, City, ZipCode, Capacity, StorageType) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssdi", $warehouseId, $street, $city, $zipCode, $capacity, $storageType);
            
            if ($stmt->execute()) {
                showSuccess("Warehouse added successfully!");
            } else {
                showError("Error adding warehouse: " . $stmt->error);
            }
            
            $stmt->close();
        }
        // Update warehouse
        else if ($_POST['action'] == 'edit' && isset($_POST['warehouseId'])) {
            $warehouseId = sanitizeInput($_POST['warehouseId']);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            $capacity = floatval($_POST['capacity']);
            $storageType = intval($_POST['storageType']);
            
            $query = "UPDATE warehouse SET Street = ?, City = ?, ZipCode = ?, Capacity = ?, StorageType = ? 
                      WHERE WarehouseID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssdis", $street, $city, $zipCode, $capacity, $storageType, $warehouseId);
            
            if ($stmt->execute()) {
                showSuccess("Warehouse updated successfully!");
            } else {
                showError("Error updating warehouse: " . $stmt->error);
            }
            
            $stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: warehouses.php");
    exit();
}

// Delete warehouse
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $warehouseId = sanitizeInput($_GET['id']);
    
    // Get the actual column name from batch table that refers to the warehouse
    // First, let's get the column names from the batch table
    $columnsQuery = "SHOW COLUMNS FROM batch";
    $columnsResult = $conn->query($columnsQuery);
    
    $warehouseColumn = null;
    if ($columnsResult && $columnsResult->num_rows > 0) {
        while ($column = $columnsResult->fetch_assoc()) {
            $columnName = $column['Field'];
            // Look for columns that might reference the warehouse
            if (stripos($columnName, 'warehouse') !== false || $columnName == 'WarehouseID') {
                $warehouseColumn = $columnName;
                break;
            }
        }
    }
    
    $canDelete = true;
    
    // If we found a potential warehouse column, check if it has associated batches
    if ($warehouseColumn) {
        $checkQuery = "SELECT COUNT(*) as count FROM batch WHERE " . $warehouseColumn . " = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $warehouseId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        
        if ($row['count'] > 0) {
            showError("Cannot delete warehouse. It has associated batches that must be deleted or transferred first.");
            $canDelete = false;
        }
    }
    
    // Also check warehouse_stock table
    $stockCheckQuery = "SELECT COUNT(*) as count FROM warehouse_stock WHERE WarehouseID = ?";
    $stockCheckStmt = $conn->prepare($stockCheckQuery);
    $stockCheckStmt->bind_param("s", $warehouseId);
    $stockCheckStmt->execute();
    $stockCheckResult = $stockCheckStmt->get_result();
    $stockRow = $stockCheckResult->fetch_assoc();
    
    if ($stockRow['count'] > 0) {
        showError("Cannot delete warehouse. It has associated stock items that must be deleted or transferred first.");
        $canDelete = false;
    }
    
    // Proceed with deletion if there are no associated records
    if ($canDelete) {
        // You might also need to check other tables that reference the warehouse
        
        // Proceed with deletion
        $query = "DELETE FROM warehouse WHERE WarehouseID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $warehouseId);
        
        if ($stmt->execute()) {
            showSuccess("Warehouse deleted successfully!");
        } else {
            showError("Error deleting warehouse: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    // Redirect
    header("Location: warehouses.php");
    exit();
}

// Edit warehouse - Get data for form
$editData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $warehouseId = sanitizeInput($_GET['id']);
    
    $query = "SELECT * FROM warehouse WHERE WarehouseID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $warehouseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Select warehouse as current
if (isset($_GET['action']) && $_GET['action'] == 'select' && isset($_GET['id'])) {
    $warehouseId = sanitizeInput($_GET['id']);
    
    // Store in session
    $_SESSION['selectedWarehouse'] = $warehouseId;
    
    showSuccess("Warehouse selected successfully!");
    
    // Redirect
    header("Location: warehouses.php");
    exit();
}

// Get all warehouses
$query = "SELECT * FROM warehouse ORDER BY City";
$result = $conn->query($query);
$warehouses = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $warehouses[] = $row;
    }
}

// Get warehouse climate data
$warehouseClimate = [];
if (!empty($warehouses)) {
    $climateQuery = "SELECT w.WarehouseID, w.City, AVG(wcl.Temperature) as AvgTemp, AVG(wcl.Humidity) as AvgHumidity 
                     FROM warehouse w
                     LEFT JOIN warehouse_climate_log wcl ON w.WarehouseID = wcl.WarehouseID
                     GROUP BY w.WarehouseID";
    $climateResult = $conn->query($climateQuery);
    
    if ($climateResult && $climateResult->num_rows > 0) {
        while ($row = $climateResult->fetch_assoc()) {
            $warehouseClimate[$row['WarehouseID']] = $row;
        }
    }
}

// Get warehouse stock count
$warehouseStock = [];
$stockQuery = "SELECT WarehouseID, COUNT(*) as StockCount, SUM(Quantity) as TotalQuantity 
               FROM warehouse_stock 
               GROUP BY WarehouseID";
$stockResult = $conn->query($stockQuery);

if ($stockResult && $stockResult->num_rows > 0) {
    while ($row = $stockResult->fetch_assoc()) {
        $warehouseStock[$row['WarehouseID']] = $row;
    }
}

// Storage type labels
$storageTypes = [
    1 => 'Dry Storage',
    2 => 'Cold Storage',
    3 => 'Freezer',
    4 => 'Climate Controlled'
];

// Include header
$pageTitle = "Warehouses";
include('includes/header.php');
?>

<main>
    <h1>Warehouse Management</h1>
    
    <div class="date">
        <input type="date" value="">
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
    
    <!-- Currently Selected Warehouse -->
    <?php if (isset($_SESSION['selectedWarehouse'])): ?>
        <?php foreach ($warehouses as $warehouse): ?>
            <?php if ($warehouse['WarehouseID'] == $_SESSION['selectedWarehouse']): ?>
                <div class="selected-warehouse">
                    <h3>Current Warehouse: <?php echo "Warehouse-" . $warehouse['WarehouseID'] . " (" . $warehouse['City'] . ")"; ?></h3>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Warehouse Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Warehouse' : 'Add New Warehouse'; ?></h2>
        <form method="POST" action="" id="warehouseForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="warehouseId" value="<?php echo $editData['WarehouseID']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="street">Street Address</label>
                    <input type="text" id="street" name="street" required 
                           value="<?php echo $editData ? $editData['Street'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" required 
                           value="<?php echo $editData ? $editData['City'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="zipCode">Zip Code</label>
                    <input type="text" id="zipCode" name="zipCode" required 
                           value="<?php echo $editData ? $editData['ZipCode'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="capacity">Storage Capacity (in tons)</label>
                    <input type="number" id="capacity" name="capacity" step="0.01" required 
                           value="<?php echo $editData ? $editData['Capacity'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="storageType">Storage Type</label>
                <select id="storageType" name="storageType" required>
                    <option value="">-- Select Storage Type --</option>
                    <?php foreach ($storageTypes as $id => $type): ?>
                        <option value="<?php echo $id; ?>" 
                            <?php echo ($editData && $editData['StorageType'] == $id) ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $editData ? 'Update Warehouse' : 'Add Warehouse'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="warehouses.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Warehouses List -->
    <div class="data-table">
        <div class="table-header">
            <h2>All Warehouses</h2>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search warehouses...">
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>City</th>
                    <th>Address</th>
                    <th>Capacity</th>
                    <th>Storage Type</th>
                    <th>Stock Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($warehouses)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No warehouses found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <tr>
                            <td><?php echo $warehouse['WarehouseID']; ?></td>
                            <td><?php echo $warehouse['City']; ?></td>
                            <td><?php echo $warehouse['Street'] . ', ' . $warehouse['ZipCode']; ?></td>
                            <td><?php echo number_format($warehouse['Capacity'], 2) . ' tons'; ?></td>
                            <td>
                                <?php echo isset($storageTypes[$warehouse['StorageType']]) ? 
                                         $storageTypes[$warehouse['StorageType']] : 'Unknown'; ?>
                            </td>
                            <td>
                                <?php 
                                    if (isset($warehouseStock[$warehouse['WarehouseID']])) {
                                        echo $warehouseStock[$warehouse['WarehouseID']]['StockCount'] . ' items (' . 
                                             number_format($warehouseStock[$warehouse['WarehouseID']]['TotalQuantity'], 2) . ' units)';
                                    } else {
                                        echo '0 items';
                                    }
                                ?>
                            </td>
                            <td class="action-buttons">
                                <a href="warehouses.php?action=select&id=<?php echo $warehouse['WarehouseID']; ?>" 
                                   class="btn btn-success btn-sm">Select</a>
                                <a href="warehouse_details.php?id=<?php echo $warehouse['WarehouseID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="warehouses.php?action=edit&id=<?php echo $warehouse['WarehouseID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="warehouses.php?action=delete&id=<?php echo $warehouse['WarehouseID']; ?>" 
                                   class="btn btn-danger btn-sm delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this warehouse?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- The search functionality JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('table tbody tr');
            
            tableRows.forEach(function(row) {
                let found = false;
                const cells = row.querySelectorAll('td');
                
                cells.forEach(function(cell) {
                    // Skip the actions column
                    if (!cell.classList.contains('action-buttons') && 
                        cell.textContent.toLowerCase().includes(searchTerm)) {
                        found = true;
                    }
                });
                
                if (found) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php
// Include footer
//include('includes/footer.php');
?>
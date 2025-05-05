<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Check if warehouse is selected
if (!isset($_SESSION['selectedWarehouse']) || empty($_SESSION['selectedWarehouse'])) {
    showError("Please select a warehouse first!");
    header("Location: warehouses.php");
    exit();
}

// Always source warehouse ID from session
$warehouseId = $_SESSION['selectedWarehouse'];

// Fetch warehouse details
$warehouseQuery = "SELECT * FROM warehouse WHERE WarehouseID = ?";
$warehouseStmt = $conn->prepare($warehouseQuery);
$warehouseStmt->bind_param("s", $warehouseId);
$warehouseStmt->execute();
$warehouseResult = $warehouseStmt->get_result();

// Check if warehouse exists
if ($warehouseResult->num_rows == 0) {
    showError("Selected warehouse not found!");
    header("Location: warehouses.php");
    exit();
}

$warehouse = $warehouseResult->fetch_assoc();
$warehouseStmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new batch
        if ($_POST['action'] == 'add') {
            $batchId = generateID('BCH', 8);
            $productionDate = sanitizeInput($_POST['productionDate']);
            $quantity = floatval($_POST['quantity']);
            $harvestId = sanitizeInput($_POST['harvestId']);
            $warehouseStockId = generateID('WST', 8);
            
            // Get Crop details
            $cropName = sanitizeInput($_POST['cropName']);
            $cropType = sanitizeInput($_POST['cropType']);
            $cropVariety = sanitizeInput($_POST['cropVariety']);
            $growingSeason = sanitizeInput($_POST['growingSeason']);
            
            // Calculate expiry date (6 months from production date)
            $expiryDate = date('Y-m-d', strtotime($productionDate . ' + 6 months'));

            // Begin transaction
            $conn->begin_transaction();

            try {
                // 1. First create warehouse stock entry with NULL BatchID
                $stockQuery = "INSERT INTO warehouse_stock 
                            (WarehouseStockID, Quantity, EntryDate, ExpiryDate, WarehouseID) 
                            VALUES (?, ?, ?, ?, ?)";
                $stockStmt = $conn->prepare($stockQuery);
                $stockStmt->bind_param("sdsss", $warehouseStockId, $quantity, $productionDate, $expiryDate, $warehouseId);
                $stockStmt->execute();
                $stockStmt->close();
                
                // 2. Insert batch with the new WarehouseStockID
                $batchQuery = "INSERT INTO batch 
                            (BatchID, BatchProductionDate, Quantity, HarvestID, WarehouseStockID) 
                            VALUES (?, ?, ?, ?, ?)";
                $batchStmt = $conn->prepare($batchQuery);
                $batchStmt->bind_param("ssdss", $batchId, $productionDate, $quantity, $harvestId, $warehouseStockId);
                $batchStmt->execute();
                $batchStmt->close();
                
                // 3. Update warehouse_stock to set BatchID
                $updateStockQuery = "UPDATE warehouse_stock SET BatchID = ? WHERE WarehouseStockID = ?";
                $updateStmt = $conn->prepare($updateStockQuery);
                $updateStmt->bind_param("ss", $batchId, $warehouseStockId);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Insert crop details
                $cropQuery = "INSERT INTO crop 
                            (BatchID, CropName, CropType, CropVariety, GrowingSeason) 
                            VALUES (?, ?, ?, ?, ?)";
                $cropStmt = $conn->prepare($cropQuery);
                $cropStmt->bind_param("sssss", $batchId, $cropName, $cropType, $cropVariety, $growingSeason);
                $cropStmt->execute();
                $cropStmt->close();
                
                // Commit transaction
                $conn->commit();
                showSuccess("Batch added successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error adding batch: " . $e->getMessage());
            }
        }
        // Update batch
        else if ($_POST['action'] == 'edit' && isset($_POST['batchId'])) {
            $batchId = sanitizeInput($_POST['batchId']);
            $productionDate = sanitizeInput($_POST['productionDate']);
            $quantity = floatval($_POST['quantity']);
            $harvestId = sanitizeInput($_POST['harvestId']);
            
            // Get Crop details
            $cropName = sanitizeInput($_POST['cropName']);
            $cropType = sanitizeInput($_POST['cropType']);
            $cropVariety = sanitizeInput($_POST['cropVariety']);
            $growingSeason = sanitizeInput($_POST['growingSeason']);
            
            // Calculate expiry date (6 months from production date)
            $expiryDate = date('Y-m-d', strtotime($productionDate . ' + 6 months'));
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update batch
                $batchQuery = "UPDATE batch 
                              SET BatchProductionDate = ?, Quantity = ?, HarvestID = ? 
                              WHERE BatchID = ?";
                $batchStmt = $conn->prepare($batchQuery);
                $batchStmt->bind_param("sdss", $productionDate, $quantity, $harvestId, $batchId);
                $batchStmt->execute();
                $batchStmt->close();
                
                // Update warehouse stock
                $stockQuery = "UPDATE warehouse_stock 
                              SET Quantity = ?, EntryDate = ?, ExpiryDate = ? 
                              WHERE BatchID = ?";
                $stockStmt = $conn->prepare($stockQuery);
                $stockStmt->bind_param("dsss", $quantity, $productionDate, $expiryDate, $batchId);
                $stockStmt->execute();
                $stockStmt->close();
                
                // Update crop details
                $cropQuery = "UPDATE crop 
                             SET CropName = ?, CropType = ?, CropVariety = ?, GrowingSeason = ? 
                             WHERE BatchID = ?";
                $cropStmt = $conn->prepare($cropQuery);
                $cropStmt->bind_param("sssss", $cropName, $cropType, $cropVariety, $growingSeason, $batchId);
                $cropStmt->execute();
                $cropStmt->close();
                
                // Commit transaction
                $conn->commit();
                showSuccess("Batch updated successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error updating batch: " . $e->getMessage());
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: batches.php");
    exit();
}

// Delete batch
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $batchId = sanitizeInput($_GET['id']);
    
    // Check if batch is used in purchases or shipments
    $checkQuery = "SELECT 
                   (SELECT COUNT(*) FROM batch_purchase WHERE BatchID = ?) as purchase_count,
                   (SELECT COUNT(*) FROM batch_shipment WHERE BatchID = ?) as shipment_count";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ss", $batchId, $batchId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($row['purchase_count'] > 0 || $row['shipment_count'] > 0) {
        showError("Cannot delete batch because it is associated with purchases or shipments.");
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // 1. First delete any nutritional_analysis records that reference this batch
            $deleteAnalysisQuery = "DELETE FROM nutritional_analysis WHERE BatchID = ?";
            $deleteAnalysisStmt = $conn->prepare($deleteAnalysisQuery);
            $deleteAnalysisStmt->bind_param("s", $batchId);
            $deleteAnalysisStmt->execute();
            $deleteAnalysisStmt->close();
            
            // 2. Delete crop details
            $deleteCropQuery = "DELETE FROM crop WHERE BatchID = ?";
            $deleteCropStmt = $conn->prepare($deleteCropQuery);
            $deleteCropStmt->bind_param("s", $batchId);
            $deleteCropStmt->execute();
            $deleteCropStmt->close();
            
            // 3. Get warehouse stock ID
            $stockIdQuery = "SELECT WarehouseStockID FROM batch WHERE BatchID = ?";
            $stockIdStmt = $conn->prepare($stockIdQuery);
            $stockIdStmt->bind_param("s", $batchId);
            $stockIdStmt->execute();
            $stockIdResult = $stockIdStmt->get_result();
            $stockIdRow = $stockIdResult->fetch_assoc();
            $warehouseStockId = $stockIdRow['WarehouseStockID'];
            $stockIdStmt->close();
            
            // 4. Delete batch
            $deleteBatchQuery = "DELETE FROM batch WHERE BatchID = ?";
            $deleteBatchStmt = $conn->prepare($deleteBatchQuery);
            $deleteBatchStmt->bind_param("s", $batchId);
            $deleteBatchStmt->execute();
            $deleteBatchStmt->close();
            
            // 5. Delete warehouse stock
            $deleteStockQuery = "DELETE FROM warehouse_stock WHERE WarehouseStockID = ?";
            $deleteStockStmt = $conn->prepare($deleteStockQuery);
            $deleteStockStmt->bind_param("s", $warehouseStockId);
            $deleteStockStmt->execute();
            $deleteStockStmt->close();
            
            // Commit transaction
            $conn->commit();
            showSuccess("Batch deleted successfully!");
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            showError("Error deleting batch: " . $e->getMessage());
        }
    }
    
    // Redirect
    header("Location: batches.php");
    exit();
}

// Edit batch - Get data for form
$editData = null;
$cropData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $batchId = sanitizeInput($_GET['id']);
    
    // Get batch data
    $query = "SELECT b.*, ws.EntryDate, ws.ExpiryDate
              FROM batch b
              JOIN warehouse_stock ws ON b.WarehouseStockID = ws.WarehouseStockID
              WHERE b.BatchID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
        
        // Get crop data
        $cropQuery = "SELECT * FROM crop WHERE BatchID = ?";
        $cropStmt = $conn->prepare($cropQuery);
        $cropStmt->bind_param("s", $batchId);
        $cropStmt->execute();
        $cropResult = $cropStmt->get_result();
        
        if ($cropResult->num_rows == 1) {
            $cropData = $cropResult->fetch_assoc();
        }
        
        $cropStmt->close();
    }
    
    $stmt->close();
}

// Get all batches for the selected warehouse
$query = "SELECT b.*, c.CropName, c.CropType, c.CropVariety, 
          ws.EntryDate, ws.ExpiryDate,
          h.FarmerID, f.Name as FarmerName
          FROM batch b
          JOIN crop c ON b.BatchID = c.BatchID
          JOIN warehouse_stock ws ON b.WarehouseStockID = ws.WarehouseStockID
          LEFT JOIN harvest_session h ON b.HarvestID = h.HarvestID
          LEFT JOIN farmer f ON h.FarmerID = f.FarmerID
          WHERE ws.WarehouseID = ?
          ORDER BY ws.EntryDate DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $warehouseId);
$stmt->execute();
$result = $stmt->get_result();
$batches = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
}
$stmt->close();

// Get all harvests for dropdown
$harvestQuery = "SELECT h.HarvestID, h.Day, h.Month, h.Year, f.Name as FarmerName
                FROM harvest_session h
                JOIN farmer f ON h.FarmerID = f.FarmerID
                ORDER BY h.Year DESC, h.Month DESC, h.Day DESC";
$harvestResult = $conn->query($harvestQuery);
$harvests = [];

if ($harvestResult && $harvestResult->num_rows > 0) {
    while ($row = $harvestResult->fetch_assoc()) {
        $harvests[] = $row;
    }
}

// Get crop types for dropdown
$cropTypes = [
    'Fruit' => ['Apple', 'Orange', 'Banana', 'Strawberry', 'Grape', 'Watermelon', 'Mango'],
    'Vegetable' => ['Tomato', 'Potato', 'Carrot', 'Onion', 'Lettuce', 'Cucumber', 'Pepper'],
    'Grain' => ['Wheat', 'Rice', 'Corn', 'Barley', 'Oats'],
    'Legume' => ['Beans', 'Peas', 'Lentils', 'Chickpeas', 'Soybeans']
];

// Get growing seasons for dropdown
$growingSeasons = ['Spring', 'Summer', 'Fall', 'Winter', 'Year-round'];

// Set page title
$pageTitle = "Batch Management";
include('includes/header.php');
?>

<main>
    <h1>Batch Management</h1>
    
    <div class="date">
        <input type="date" value="<?php echo date('Y-m-d'); ?>">
    </div>
    
    <!-- Display Selected Warehouse -->
    <div class="selected-warehouse">
        <h3>Current Warehouse: <?php echo isset($warehouse['WarehouseID']) ? "Warehouse-" . $warehouse['WarehouseID'] . " (" . $warehouse['City'] . ")" : "Not Selected"; ?></h3>
        <?php if (isset($warehouse['StorageType'])): ?>
        <p>Storage Type: <?php 
            $storageTypes = [
                1 => 'Dry Storage',
                2 => 'Cold Storage',
                3 => 'Freezer',
                4 => 'Climate Controlled'
            ];
            echo isset($storageTypes[$warehouse['StorageType']]) ? $storageTypes[$warehouse['StorageType']] : 'Unknown'; 
        ?> | Capacity: <?php echo number_format($warehouse['Capacity'], 2); ?> tons</p>
        <?php endif; ?>
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
    
    <!-- Batch Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Batch' : 'Add New Batch'; ?></h2>
        <form method="POST" action="" id="batchForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="batchId" value="<?php echo $editData['BatchID']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="productionDate">Production Date</label>
                    <input type="date" id="productionDate" name="productionDate" required 
                           value="<?php echo $editData ? $editData['BatchProductionDate'] : date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity (kg)</label>
                    <input type="number" id="quantity" name="quantity" step="0.01" min="0" required 
                           value="<?php echo $editData ? $editData['Quantity'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="harvestId">Harvest Session</label>
                <select id="harvestId" name="harvestId" required>
                    <option value="">-- Select Harvest --</option>
                    <?php foreach ($harvests as $harvest): ?>
                        <option value="<?php echo $harvest['HarvestID']; ?>" 
                            <?php echo ($editData && $editData['HarvestID'] == $harvest['HarvestID']) ? 'selected' : ''; ?>>
                            <?php echo $harvest['FarmerName'] . ' - ' . 
                                   $harvest['Day'] . '/' . $harvest['Month'] . '/' . $harvest['Year']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <h3>Crop Details</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="cropType">Crop Type</label>
                    <select id="cropType" name="cropType" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach (array_keys($cropTypes) as $type): ?>
                            <option value="<?php echo $type; ?>" 
                                <?php echo ($cropData && $cropData['CropType'] == $type) ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="cropName">Crop Name</label>
                    <select id="cropName" name="cropName" required>
                        <option value="">-- Select Name --</option>
                        <?php if ($cropData && isset($cropTypes[$cropData['CropType']])): ?>
                            <?php foreach ($cropTypes[$cropData['CropType']] as $name): ?>
                                <option value="<?php echo $name; ?>" 
                                    <?php echo ($cropData && $cropData['CropName'] == $name) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="cropVariety">Crop Variety</label>
                    <input type="text" id="cropVariety" name="cropVariety" required 
                           value="<?php echo $cropData ? $cropData['CropVariety'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="growingSeason">Growing Season</label>
                    <select id="growingSeason" name="growingSeason" required>
                        <option value="">-- Select Season --</option>
                        <?php foreach ($growingSeasons as $season): ?>
                            <option value="<?php echo $season; ?>" 
                                <?php echo ($cropData && $cropData['GrowingSeason'] == $season) ? 'selected' : ''; ?>>
                                <?php echo $season; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $editData ? 'Update Batch' : 'Add Batch'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="batches.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Batches List -->
    <div class="data-table">
        <div class="table-header">
            <h2>All Batches in <?php echo isset($warehouse['City']) ? $warehouse['City'] : 'Selected'; ?> Warehouse</h2>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search batches...">
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Batch ID</th>
                    <th>Crop</th>
                    <th>Variety</th>
                    <th>Farmer</th>
                    <th>Production Date</th>
                    <th>Quantity</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No batches found in this warehouse.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?php echo $batch['BatchID']; ?></td>
                            <td><?php echo $batch['CropName']; ?></td>
                            <td><?php echo $batch['CropVariety']; ?></td>
                            <td><?php echo $batch['FarmerName'] ?? 'N/A'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($batch['BatchProductionDate'])); ?></td>
                            <td><?php echo number_format($batch['Quantity'], 2) . ' kg'; ?></td>
                            <td>
                                <?php 
                                    $expiryDate = strtotime($batch['ExpiryDate']);
                                    $today = time();
                                    $daysLeft = round(($expiryDate - $today) / (60 * 60 * 24));
                                    
                                    $class = '';
                                    if ($daysLeft < 0) {
                                        $class = 'expired';
                                    } elseif ($daysLeft < 30) {
                                        $class = 'expiring-soon';
                                    }
                                    
                                    echo '<span class="' . $class . '">' . date('M d, Y', $expiryDate) . '</span>';
                                    if ($daysLeft > 0) {
                                        echo ' <small>(' . $daysLeft . ' days left)</small>';
                                    } else {
                                        echo ' <small>(expired)</small>';
                                    }
                                ?>
                            </td>
                            <td class="action-buttons">
                                <a href="batch_details.php?id=<?php echo $batch['BatchID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="batches.php?action=edit&id=<?php echo $batch['BatchID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="batches.php?action=delete&id=<?php echo $batch['BatchID']; ?>" 
                                   class="btn btn-danger btn-sm delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<style>
.expired {
    color: #ff0000;
    font-weight: bold;
}

.expiring-soon {
    color: #ff9900;
    font-weight: bold;
}

.selected-warehouse {
    background: var(--color-white);
    padding: 1rem;
    border-radius: var(--card-border-radius);
    margin-bottom: 1rem;
    box-shadow: var(--box-shadow);
}

.selected-warehouse h3 {
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}
</style>

<!-- Script for dynamic crop name dropdown based on crop type -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cropTypeSelect = document.getElementById('cropType');
    const cropNameSelect = document.getElementById('cropName');
    
    // Crop types and names
    const cropTypes = <?php echo json_encode($cropTypes); ?>;
    
    // Update crop names when crop type changes
    cropTypeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        
        // Clear current options
        cropNameSelect.innerHTML = '<option value="">-- Select Name --</option>';
        
        // Add new options based on selected type
        if (selectedType && cropTypes[selectedType]) {
            cropTypes[selectedType].forEach(name => {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                cropNameSelect.appendChild(option);
            });
        }
    });
    
    // Set initial options if editing
    if (cropTypeSelect.value) {
        cropTypeSelect.dispatchEvent(new Event('change'));
        
        // Set selected crop name if editing
        const cropNameValue = '<?php echo $cropData ? $cropData['CropName'] : ''; ?>';
        if (cropNameValue) {
            Array.from(cropNameSelect.options).forEach(option => {
                if (option.value === cropNameValue) {
                    option.selected = true;
                }
            });
        }
    }
    
    // Delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this batch? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
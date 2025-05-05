<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new shipment
        if ($_POST['action'] == 'add') {
            $shipmentId = generateID('SHP', 8);
            $day = intval($_POST['day']);
            $month = intval($_POST['month']);
            $year = intval($_POST['year']);
            $destination = sanitizeInput($_POST['destination']);
            $totalWeight = floatval($_POST['totalWeight']);
            $marketId = sanitizeInput($_POST['marketId']);
            $batchIds = isset($_POST['batchIds']) ? $_POST['batchIds'] : [];
            $vehicleIds = isset($_POST['vehicleIds']) ? $_POST['vehicleIds'] : [];
            $weights = isset($_POST['weights']) ? $_POST['weights'] : [];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert shipment
                $query = "INSERT INTO shipment (ShipmentID, Day, Month, Year, DestinationLocation, TotalWeight, MarketID) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("siiisds", $shipmentId, $day, $month, $year, $destination, $totalWeight, $marketId);
                $stmt->execute();
                $stmt->close();
                
                // Link batches to shipment
                if (!empty($batchIds)) {
                    $batchQuery = "INSERT INTO batch_shipment (ShipmentID, BatchID) VALUES (?, ?)";
                    $batchStmt = $conn->prepare($batchQuery);
                    
                    foreach ($batchIds as $batchId) {
                        $batchStmt->bind_param("ss", $shipmentId, $batchId);
                        $batchStmt->execute();
                    }
                    
                    $batchStmt->close();
                }
                
                // Add transport vehicles
                if (!empty($vehicleIds) && !empty($weights) && count($vehicleIds) === count($weights)) {
                    for ($i = 0; $i < count($vehicleIds); $i++) {
                        if (!empty($vehicleIds[$i]) && !empty($weights[$i])) {
                            $transportId = generateID('TRP', 8);
                            $vehicleId = sanitizeInput($vehicleIds[$i]);
                            $weight = floatval($weights[$i]);
                            
                            $transportQuery = "INSERT INTO shipment_transport 
                                              (ShipmentTransportID, ShipmentID, VehicleID, Weight) 
                                              VALUES (?, ?, ?, ?)";
                            $transportStmt = $conn->prepare($transportQuery);
                            $transportStmt->bind_param("sssd", $transportId, $shipmentId, $vehicleId, $weight);
                            $transportStmt->execute();
                            $transportStmt->close();
                        }
                    }
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Shipment added successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error adding shipment: " . $e->getMessage());
            }
        }
        // Update shipment
        else if ($_POST['action'] == 'edit' && isset($_POST['shipmentId'])) {
            $shipmentId = sanitizeInput($_POST['shipmentId']);
            $day = intval($_POST['day']);
            $month = intval($_POST['month']);
            $year = intval($_POST['year']);
            $destination = sanitizeInput($_POST['destination']);
            $totalWeight = floatval($_POST['totalWeight']);
            $marketId = sanitizeInput($_POST['marketId']);
            $batchIds = isset($_POST['batchIds']) ? $_POST['batchIds'] : [];
            $vehicleIds = isset($_POST['vehicleIds']) ? $_POST['vehicleIds'] : [];
            $weights = isset($_POST['weights']) ? $_POST['weights'] : [];
            $transportIds = isset($_POST['transportIds']) ? $_POST['transportIds'] : [];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update shipment
                $query = "UPDATE shipment 
                          SET Day = ?, Month = ?, Year = ?, DestinationLocation = ?, TotalWeight = ?, MarketID = ? 
                          WHERE ShipmentID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iiisdss", $day, $month, $year, $destination, $totalWeight, $marketId, $shipmentId);
                $stmt->execute();
                $stmt->close();
                
                // Delete existing batch links
                $deleteBatchQuery = "DELETE FROM batch_shipment WHERE ShipmentID = ?";
                $deleteBatchStmt = $conn->prepare($deleteBatchQuery);
                $deleteBatchStmt->bind_param("s", $shipmentId);
                $deleteBatchStmt->execute();
                $deleteBatchStmt->close();
                
                // Link batches to shipment
                if (!empty($batchIds)) {
                    $batchQuery = "INSERT INTO batch_shipment (ShipmentID, BatchID) VALUES (?, ?)";
                    $batchStmt = $conn->prepare($batchQuery);
                    
                    foreach ($batchIds as $batchId) {
                        $batchStmt->bind_param("ss", $shipmentId, $batchId);
                        $batchStmt->execute();
                    }
                    
                    $batchStmt->close();
                }
                
                // Delete existing transport links
                $deleteTransportQuery = "DELETE FROM shipment_transport WHERE ShipmentID = ?";
                $deleteTransportStmt = $conn->prepare($deleteTransportQuery);
                $deleteTransportStmt->bind_param("s", $shipmentId);
                $deleteTransportStmt->execute();
                $deleteTransportStmt->close();
                
                // Add transport vehicles
                if (!empty($vehicleIds) && !empty($weights) && count($vehicleIds) === count($weights)) {
                    for ($i = 0; $i < count($vehicleIds); $i++) {
                        if (!empty($vehicleIds[$i]) && !empty($weights[$i])) {
                            $transportId = !empty($transportIds[$i]) ? $transportIds[$i] : generateID('TRP', 8);
                            $vehicleId = sanitizeInput($vehicleIds[$i]);
                            $weight = floatval($weights[$i]);
                            
                            $transportQuery = "INSERT INTO shipment_transport 
                                              (ShipmentTransportID, ShipmentID, VehicleID, Weight) 
                                              VALUES (?, ?, ?, ?)";
                            $transportStmt = $conn->prepare($transportQuery);
                            $transportStmt->bind_param("sssd", $transportId, $shipmentId, $vehicleId, $weight);
                            $transportStmt->execute();
                            $transportStmt->close();
                        }
                    }
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Shipment updated successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error updating shipment: " . $e->getMessage());
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: shipments.php");
    exit();
}

// Delete shipment
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $shipmentId = sanitizeInput($_GET['id']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete transport links
        $deleteTransportQuery = "DELETE FROM shipment_transport WHERE ShipmentID = ?";
        $deleteTransportStmt = $conn->prepare($deleteTransportQuery);
        $deleteTransportStmt->bind_param("s", $shipmentId);
        $deleteTransportStmt->execute();
        $deleteTransportStmt->close();
        
        // Delete batch links
        $deleteBatchQuery = "DELETE FROM batch_shipment WHERE ShipmentID = ?";
        $deleteBatchStmt = $conn->prepare($deleteBatchQuery);
        $deleteBatchStmt->bind_param("s", $shipmentId);
        $deleteBatchStmt->execute();
        $deleteBatchStmt->close();
        
        // Delete shipment
        $deleteQuery = "DELETE FROM shipment WHERE ShipmentID = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("s", $shipmentId);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Commit transaction
        $conn->commit();
        showSuccess("Shipment deleted successfully!");
    } catch (Exception $e) {
        // Rollback in case of error
        $conn->rollback();
        showError("Error deleting shipment: " . $e->getMessage());
    }
    
    // Redirect
    header("Location: shipments.php");
    exit();
}

// Edit shipment - Get data for form
$editData = null;
$selectedBatches = [];
$transportData = [];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $shipmentId = sanitizeInput($_GET['id']);
    
    // Get shipment data
    $query = "SELECT * FROM shipment WHERE ShipmentID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $shipmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
        
        // Get selected batches
        $batchQuery = "SELECT BatchID FROM batch_shipment WHERE ShipmentID = ?";
        $batchStmt = $conn->prepare($batchQuery);
        $batchStmt->bind_param("s", $shipmentId);
        $batchStmt->execute();
        $batchResult = $batchStmt->get_result();
        
        while ($batchRow = $batchResult->fetch_assoc()) {
            $selectedBatches[] = $batchRow['BatchID'];
        }
        
        $batchStmt->close();
        
        // Get transport data
        $transportQuery = "SELECT * FROM shipment_transport WHERE ShipmentID = ?";
        $transportStmt = $conn->prepare($transportQuery);
        $transportStmt->bind_param("s", $shipmentId);
        $transportStmt->execute();
        $transportResult = $transportStmt->get_result();
        
        while ($transportRow = $transportResult->fetch_assoc()) {
            $transportData[] = $transportRow;
        }
        
        $transportStmt->close();
    }
    
    $stmt->close();
}

// Get all shipments
$query = "SELECT s.*, m.MarketName,
          (SELECT COUNT(*) FROM batch_shipment WHERE ShipmentID = s.ShipmentID) as BatchCount,
          (SELECT COUNT(*) FROM shipment_transport WHERE ShipmentID = s.ShipmentID) as VehicleCount
          FROM shipment s
          LEFT JOIN market m ON s.MarketID = m.MarketID
          ORDER BY s.Year DESC, s.Month DESC, s.Day DESC";
$result = $conn->query($query);
$shipments = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shipments[] = $row;
    }
}

// Get all markets for dropdown
$marketQuery = "SELECT MarketID, MarketName FROM market ORDER BY MarketName";
$marketResult = $conn->query($marketQuery);
$markets = [];

if ($marketResult && $marketResult->num_rows > 0) {
    while ($row = $marketResult->fetch_assoc()) {
        $markets[] = $row;
    }
}

// Get all vehicles for dropdown
$vehicleQuery = "SELECT VehicleID, VehicleType, LicensePlateNumber, Capacity FROM transport_vehicle ORDER BY VehicleType";
$vehicleResult = $conn->query($vehicleQuery);
$vehicles = [];

if ($vehicleResult && $vehicleResult->num_rows > 0) {
    while ($row = $vehicleResult->fetch_assoc()) {
        $vehicles[] = $row;
    }
}

// Get all available batches from selected warehouse
$batchesQuery = "SELECT b.BatchID, c.CropName, c.CropVariety, b.Quantity
                FROM batch b
                JOIN crop c ON b.BatchID = c.BatchID
                JOIN warehouse_stock ws ON b.BatchID = ws.BatchID
                WHERE b.BatchID NOT IN (SELECT BatchID FROM batch_shipment)";

// If editing, include currently selected batches
if ($editData) {
    $batchesQuery = "SELECT b.BatchID, c.CropName, c.CropVariety, b.Quantity
                    FROM batch b
                    JOIN crop c ON b.BatchID = c.BatchID
                    JOIN warehouse_stock ws ON b.BatchID = ws.BatchID
                    WHERE b.BatchID NOT IN (
                        SELECT BatchID FROM batch_shipment WHERE ShipmentID != '" . $editData['ShipmentID'] . "'
                    )";
}

$batchesResult = $conn->query($batchesQuery);
$availableBatches = [];

if ($batchesResult && $batchesResult->num_rows > 0) {
    while ($row = $batchesResult->fetch_assoc()) {
        $availableBatches[] = $row;
    }
}

// Set page title
$pageTitle = "Shipment Management";
include('includes/header.php');
?>

<main>
    <h1>Shipment Management</h1>
    
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
    
    <!-- Shipment Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Shipment' : 'Add New Shipment'; ?></h2>
        <form method="POST" action="" id="shipmentForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="shipmentId" value="<?php echo $editData['ShipmentID']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="day">Day</label>
                    <input type="number" id="day" name="day" required min="1" max="31" 
                           value="<?php echo $editData ? $editData['Day'] : date('d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="month">Month</label>
                    <input type="number" id="month" name="month" required min="1" max="12" 
                           value="<?php echo $editData ? $editData['Month'] : date('m'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="year">Year</label>
                    <input type="number" id="year" name="year" required min="2000" max="2100" 
                           value="<?php echo $editData ? $editData['Year'] : date('Y'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="destination">Destination Location</label>
                <input type="text" id="destination" name="destination" required 
                       value="<?php echo $editData ? $editData['DestinationLocation'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="totalWeight">Total Weight (kg)</label>
                <input type="number" id="totalWeight" name="totalWeight" step="0.01" min="0" required 
                       value="<?php echo $editData ? $editData['TotalWeight'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="marketId">Target Market</label>
                <select id="marketId" name="marketId" required>
                    <option value="">-- Select Market --</option>
                    <?php foreach ($markets as $market): ?>
                        <option value="<?php echo $market['MarketID']; ?>" 
                            <?php echo ($editData && $editData['MarketID'] == $market['MarketID']) ? 'selected' : ''; ?>>
                            <?php echo $market['MarketName']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <h3>Batches to Ship</h3>
            
            <div class="form-group">
                <div class="batch-selection">
                    <?php foreach ($availableBatches as $batch): ?>
                        <div class="batch-item">
                            <input type="checkbox" id="batch_<?php echo $batch['BatchID']; ?>" 
                                   name="batchIds[]" value="<?php echo $batch['BatchID']; ?>"
                                   <?php echo in_array($batch['BatchID'], $selectedBatches) ? 'checked' : ''; ?>>
                            <label for="batch_<?php echo $batch['BatchID']; ?>">
                                <?php echo $batch['BatchID'] . ' - ' . $batch['CropName'] . ' (' . $batch['CropVariety'] . ') - ' . number_format($batch['Quantity'], 2) . ' kg'; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($availableBatches)): ?>
                        <p class="text-muted">No available batches for shipment.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <h3>Transport Vehicles</h3>
            
            <div id="vehiclesContainer">
                <?php if (empty($transportData)): ?>
                    <!-- Default empty row -->
                    <div class="vehicle-row">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Vehicle</label>
                                <select name="vehicleIds[]" required>
                                    <option value="">-- Select Vehicle --</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <option value="<?php echo $vehicle['VehicleID']; ?>">
                                            <?php echo $vehicle['VehicleType'] . ' - ' . $vehicle['LicensePlateNumber'] . ' (Capacity: ' . number_format($vehicle['Capacity'], 2) . ' kg)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" name="weights[]" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group vehicle-btn-group">
                                <button type="button" class="btn btn-primary btn-sm add-vehicle">+</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Existing transport rows -->
                    <?php foreach ($transportData as $index => $transport): ?>
                        <div class="vehicle-row">
                            <div class="form-row">
                                <input type="hidden" name="transportIds[]" value="<?php echo $transport['ShipmentTransportID']; ?>">
                                
                                <div class="form-group">
                                    <label>Vehicle</label>
                                    <select name="vehicleIds[]" required>
                                        <option value="">-- Select Vehicle --</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['VehicleID']; ?>" 
                                                <?php echo ($transport['VehicleID'] == $vehicle['VehicleID']) ? 'selected' : ''; ?>>
                                                <?php echo $vehicle['VehicleType'] . ' - ' . $vehicle['LicensePlateNumber'] . ' (Capacity: ' . number_format($vehicle['Capacity'], 2) . ' kg)'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Weight (kg)</label>
                                    <input type="number" name="weights[]" step="0.01" min="0" required
                                           value="<?php echo $transport['Weight']; ?>">
                                </div>
                                
                                <div class="form-group vehicle-btn-group">
                                    <?php if ($index === 0): ?>
                                        <button type="button" class="btn btn-primary btn-sm add-vehicle">+</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-danger btn-sm remove-vehicle">-</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $editData ? 'Update Shipment' : 'Add Shipment'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="shipments.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Shipments List -->
    <div class="data-table">
        <div class="table-header">
            <div class="header-flex">
                <h2>All Shipments</h2>
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search shipments...">
                </div>
            </div>
        </div>
        
        <table id="shipmentTable">
            <thead>
                <tr>
                    <th>Shipment ID</th>
                    <th>Date</th>
                    <th>Destination</th>
                    <th>Market ID</th>
                    <th>Weight</th>
                    <th>Batches</th>
                    <th>Vehicles</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shipments)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No shipments found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shipments as $shipment): ?>
                        <tr>
                            <td><?php echo $shipment['ShipmentID']; ?></td>
                            <td><?php echo $shipment['Day'] . '/' . $shipment['Month'] . '/' . $shipment['Year']; ?></td>
                            <td><?php echo $shipment['DestinationLocation']; ?></td>
                            <td><?php echo $shipment['MarketID'] ?? 'N/A'; ?></td>
                            <td><?php echo number_format($shipment['TotalWeight'], 2) . ' kg'; ?></td>
                            <td><?php echo $shipment['BatchCount']; ?></td>
                            <td><?php echo $shipment['VehicleCount']; ?></td>
                            <td class="action-buttons">
                                <a href="shipment_details.php?id=<?php echo $shipment['ShipmentID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="shipments.php?action=edit&id=<?php echo $shipment['ShipmentID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="shipments.php?action=delete&id=<?php echo $shipment['ShipmentID']; ?>" 
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
.batch-selection {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--color-info-light);
    padding: 10px;
    border-radius: var(--border-radius-1);
}

.batch-item {
    margin-bottom: 5px;
}

.vehicle-btn-group {
    display: flex;
    align-items: end;
}

.vehicle-row {
    margin-bottom: 15px;
}

.header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-bottom: 10px;
}

.search-container {
    display: flex;
    align-items: center;
}

#searchInput {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    width: 250px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const shipmentForm = document.getElementById('shipmentForm');
    if (shipmentForm) {
        shipmentForm.addEventListener('submit', function(e) {
            const day = parseInt(document.getElementById('day').value);
            const month = parseInt(document.getElementById('month').value);
            const year = parseInt(document.getElementById('year').value);
            
            // Validate day based on month
            let maxDay = 31;
            if (month === 4 || month === 6 || month === 9 || month === 11) {
                maxDay = 30;
            } else if (month === 2) {
                maxDay = (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28;
            }
            
            if (day > maxDay) {
                e.preventDefault();
                alert(`Invalid date: ${month}/${day}/${year} - ${month} has max ${maxDay} days`);
                return;
            }
            
            // Check if at least one batch is selected
            const batchCheckboxes = document.querySelectorAll('input[name="batchIds[]"]:checked');
            if (batchCheckboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one batch to ship');
                return;
            }
        });
    }
    
    // Add vehicle row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-vehicle')) {
            const container = document.getElementById('vehiclesContainer');
            const vehicleRows = container.querySelectorAll('.vehicle-row');
            const lastRow = vehicleRows[vehicleRows.length - 1];
            
            const newRow = lastRow.cloneNode(true);
            const inputs = newRow.querySelectorAll('input[type="number"]');
            inputs.forEach(input => {
                input.value = '';
            });
            
            // Change the add button to remove button
            const btnGroup = newRow.querySelector('.vehicle-btn-group');
            btnGroup.innerHTML = '<button type="button" class="btn btn-danger btn-sm remove-vehicle">-</button>';
            
            container.appendChild(newRow);
        }
    });
    
    // Remove vehicle row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-vehicle')) {
            e.target.closest('.vehicle-row').remove();
        }
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('shipmentTable');
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = searchInput.value.toLowerCase();
        
        Array.from(rows).forEach(row => {
            let found = false;
            const cells = row.querySelectorAll('td');
            
            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchTerm)) {
                    found = true;
                }
            });
            
            if (found) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Display "No results" message if no matches found
        let visibleCount = 0;
        Array.from(rows).forEach(row => {
            if (row.style.display !== 'none') {
                visibleCount++;
            }
        });
        
        // Check if there are already no results row
        const noResultsRow = tbody.querySelector('.no-results');
        if (visibleCount === 0) {
            if (!noResultsRow) {
                const newRow = document.createElement('tr');
                newRow.className = 'no-results';
                const newCell = document.createElement('td');
                newCell.colSpan = 8;
                newCell.textContent = 'No matching shipments found.';
                newCell.style.textAlign = 'center';
                newRow.appendChild(newCell);
                tbody.appendChild(newRow);
            }
        } else if (noResultsRow) {
            noResultsRow.remove();
        }
    });
});
</script>

<?php
// Include footer
//include('includes/footer.php');
?>
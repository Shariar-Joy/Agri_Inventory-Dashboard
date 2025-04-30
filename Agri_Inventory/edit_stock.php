<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Edit Stock";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to inventory page if no ID is provided
    header('Location: inventory.php');
    exit;
}

$stockId = $_GET['id'];

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $warehouseId = isset($_POST['warehouse_id']) ? $_POST['warehouse_id'] : '';
    $quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
    $expiryDate = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
    $storageLocation = isset($_POST['storage_location']) ? trim($_POST['storage_location']) : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    // Validate required fields
    if (empty($warehouseId) || $quantity <= 0 || empty($expiryDate) || empty($status)) {
        $errorMessage = "Please fill in all required fields correctly.";
    } else {
        // Update warehouse stock
        $query = "UPDATE warehouse_stock 
                  SET WarehouseID = ?, 
                      Quantity = ?, 
                      ExpiryDate = ?, 
                      StorageLocation = ?, 
                      Status = ?,
                      LastUpdated = NOW()
                  WHERE WarehouseStockID = ?";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdssss", $warehouseId, $quantity, $expiryDate, $storageLocation, $status, $stockId);
        
        if ($stmt->execute()) {
            $successMessage = "Stock updated successfully!";
            
            // Log the activity
            $activityQuery = "INSERT INTO activity_log (UserID, ActivityType, Description, EntityID, EntityType) 
                             VALUES (?, 'UPDATE', ?, ?, 'WAREHOUSE_STOCK')";
            $activityStmt = $conn->prepare($activityQuery);
            $userId = $_SESSION['user_id'] ?? 1;
            $description = "Updated warehouse stock (ID: $stockId)";
            $activityStmt->bind_param("iss", $userId, $description, $stockId);
            $activityStmt->execute();
            
        } else {
            $errorMessage = "Error updating stock: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Get stock details
$query = "SELECT ws.*, b.BatchID, c.CropName, c.CropVariety 
          FROM warehouse_stock ws
          JOIN batch b ON ws.BatchID = b.BatchID
          LEFT JOIN crop c ON b.BatchID = c.BatchID
          WHERE ws.WarehouseStockID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $stockId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Redirect if stock not found
    header('Location: inventory.php');
    exit;
}

$stock = $result->fetch_assoc();
$stmt->close();

// Get all warehouses for dropdown
$warehouseQuery = "SELECT WarehouseID, City FROM warehouse ORDER BY City";
$warehouseResult = $conn->query($warehouseQuery);
$warehouses = [];
if ($warehouseResult) {
    while ($warehouse = $warehouseResult->fetch_assoc()) {
        $warehouses[] = $warehouse;
    }
}

// Include header
include('includes/header.php');
?>

<main>
    <div class="page-header">
        <h1>Edit Stock Item</h1>
        <nav class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &gt;
            <a href="inventory.php">Inventory</a> &gt;
            <span>Edit Stock</span>
        </nav>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert success">
            <span class="material-symbols-sharp">check_circle</span>
            <p><?php echo $successMessage; ?></p>
            <span class="close-btn">&times;</span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert danger">
            <span class="material-symbols-sharp">error</span>
            <p><?php echo $errorMessage; ?></p>
            <span class="close-btn">&times;</span>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <div class="card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-sharp">inventory</span>
                    Stock Information
                </h2>
                <p>Stock ID: <?php echo htmlspecialchars($stockId); ?></p>
            </div>
            <div class="card-body">
                <div class="stock-details">
                    <div class="stock-info">
                        <h3>Batch Information</h3>
                        <div class="info-group">
                            <p><strong>Batch ID:</strong> <?php echo htmlspecialchars($stock['BatchID']); ?></p>
                            <p><strong>Product:</strong> <?php echo htmlspecialchars($stock['CropName'] ?? 'N/A'); ?> 
                                <?php if (!empty($stock['CropVariety'])): ?>
                                    (<?php echo htmlspecialchars($stock['CropVariety']); ?>)
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" action="edit_stock.php?id=<?php echo htmlspecialchars($stockId); ?>" class="form-grid">
                    <div class="form-group">
                        <label for="warehouse_id">Warehouse Location <span class="required">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" required>
                            <option value="">Select Warehouse</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?php echo $warehouse['WarehouseID']; ?>" <?php echo ($warehouse['WarehouseID'] == $stock['WarehouseID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($warehouse['City'] . ' (' . $warehouse['WarehouseID'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="quantity">Quantity <span class="required">*</span></label>
                        <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" value="<?php echo htmlspecialchars($stock['Quantity']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="expiry_date">Expiry Date <span class="required">*</span></label>
                        <input type="date" name="expiry_date" id="expiry_date" value="<?php echo htmlspecialchars($stock['ExpiryDate']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="storage_location">Storage Location</label>
                        <input type="text" name="storage_location" id="storage_location" value="<?php echo htmlspecialchars($stock['StorageLocation'] ?? ''); ?>" placeholder="e.g., Section A, Shelf 3">
                    </div>

                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select name="status" id="status" required>
                            <option value="Available" <?php echo ($stock['Status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                            <option value="Reserved" <?php echo ($stock['Status'] == 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                            <option value="In Transit" <?php echo ($stock['Status'] == 'In Transit') ? 'selected' : ''; ?>>In Transit</option>
                            <option value="Quarantined" <?php echo ($stock['Status'] == 'Quarantined') ? 'selected' : ''; ?>>Quarantined</option>
                        </select>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">Update Stock</button>
                        <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>
                    <span class="material-symbols-sharp">history</span>
                    Stock History
                </h2>
            </div>
            <div class="card-body">
                <?php
                // Get stock history
                $historyQuery = "SELECT al.*, u.Username 
                              FROM activity_log al
                              LEFT JOIN users u ON al.UserID = u.UserID
                              WHERE al.EntityID = ? AND al.EntityType = 'WAREHOUSE_STOCK'
                              ORDER BY al.Timestamp DESC
                              LIMIT 10";
                $historyStmt = $conn->prepare($historyQuery);
                $historyStmt->bind_param("s", $stockId);
                $historyStmt->execute();
                $historyResult = $historyStmt->get_result();
                ?>

                <?php if ($historyResult->num_rows > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $historyResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($log['Timestamp'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['Username'] ?? 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($log['ActivityType']); ?></td>
                                    <td><?php echo htmlspecialchars($log['Description']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No history available for this stock item.</p>
                <?php endif; ?>
                <?php $historyStmt->close(); ?>
            </div>
        </div>
    </div>

    <?php if (strtotime($stock['ExpiryDate']) < strtotime('+30 days')): ?>
    <div class="alert warning expiry-alert">
        <span class="material-symbols-sharp">warning</span>
        <div>
            <h3>Expiration Warning</h3>
            <p>This stock is expiring soon (on <?php echo date('M d, Y', strtotime($stock['ExpiryDate'])); ?>). Consider prioritizing this item for shipment.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="action-panel">
        <h3>Additional Actions</h3>
        <div class="action-buttons">
            <a href="batch_details.php?id=<?php echo htmlspecialchars($stock['BatchID']); ?>" class="btn btn-info">
                <span class="material-symbols-sharp">info</span>
                View Batch Details
            </a>
            <a href="prioritize_shipment.php?stock_id=<?php echo htmlspecialchars($stockId); ?>" class="btn btn-warning">
                <span class="material-symbols-sharp">priority_high</span>
                Prioritize for Shipment
            </a>
            <button id="reportSpoilageBtn" class="btn btn-danger">
                <span class="material-symbols-sharp">delete</span>
                Report Spoilage
            </button>
        </div>
    </div>

    <!-- Spoilage Report Modal -->
    <div id="spoilageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Report Spoilage</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form action="report_spoilage.php" method="POST" id="spoilageForm">
                    <input type="hidden" name="stock_id" value="<?php echo htmlspecialchars($stockId); ?>">
                    <input type="hidden" name="batch_id" value="<?php echo htmlspecialchars($stock['BatchID']); ?>">
                    
                    <div class="form-group">
                        <label for="spoiled_quantity">Spoiled Quantity <span class="required">*</span></label>
                        <input type="number" name="spoiled_quantity" id="spoiled_quantity" step="0.01" min="0.01" max="<?php echo htmlspecialchars($stock['Quantity']); ?>" required>
                        <small>Maximum: <?php echo htmlspecialchars($stock['Quantity']); ?> units</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="cause">Cause of Spoilage <span class="required">*</span></label>
                        <select name="cause" id="cause" required>
                            <option value="">Select Cause</option>
                            <option value="Expiration">Expiration</option>
                            <option value="Temperature">Temperature Issues</option>
                            <option value="Humidity">Humidity Issues</option>
                            <option value="Contamination">Contamination</option>
                            <option value="Pest">Pest Damage</option>
                            <option value="Packaging">Packaging Failure</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea name="notes" id="notes" rows="4" placeholder="Provide any additional details about the spoilage..."></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" class="btn btn-primary">Submit Report</button>
                        <button type="button" class="btn btn-secondary cancel-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
.form-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

@media (min-width: 1100px) {
    .form-container {
        grid-template-columns: 2fr 1fr;
    }
}

.card {
    background: var(--color-white);
    border-radius: var(--card-border-radius);
    padding: 1.5rem;
    box-shadow: var(--box-shadow);
    transition: all 300ms ease;
}

.card:hover {
    box-shadow: none;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.2rem;
}

.card-header h2 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.2rem;
}

.stock-details {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--color-light);
}

.stock-info h3 {
    font-size: 1rem;
    margin-bottom: 0.7rem;
    color: var(--color-dark);
}

.info-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.info-group p {
    margin-bottom: 0.5rem;
    color: var(--color-dark-variant);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.2rem;
}

@media (min-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--color-dark);
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.7rem;
    border-radius: 0.4rem;
    background: var(--color-light);
    border: 1px solid transparent;
    color: var(--color-dark);
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--color-primary);
    background: var(--color-white);
}

.form-buttons {
    grid-column: 1 / -1;
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.7rem 1.2rem;
    border-radius: 0.4rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 300ms ease;
}

.btn-primary {
    background: var(--color-primary);
    color: white;
}

.btn-secondary {
    background: var(--color-dark-variant);
    color: white;
}

.btn-info {
    background: var(--color-info);
    color: white;
}

.btn-warning {
    background: var(--color-warning);
    color: white;
}

.btn-danger {
    background: var(--color-danger);
    color: white;
}

.btn:hover {
    opacity: 0.8;
    transform: translateY(-2px);
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.history-table th,
.history-table td {
    padding: 0.7rem;
    text-align: left;
    border-bottom: 1px solid var(--color-light);
}

.history-table th {
    font-weight: 600;
    color: var(--color-dark);
}

.history-table td {
    color: var(--color-dark-variant);
}

.alert {
    padding: 1rem;
    border-radius: var(--border-radius-1);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.alert.success {
    background: rgba(66, 186, 150, 0.1);
    color: var(--color-success);
}

.alert.danger {
    background: rgba(255, 0, 0, 0.1);
    color: var(--color-danger);
}

.alert.warning {
    background: rgba(255, 165, 0, 0.1);
    color: var(--color-warning);
}

.alert span {
    font-size: 1.5rem;
}

.close-btn {
    cursor: pointer;
    margin-left: auto;
}

.action-panel {
    background: var(--color-white);
    border-radius: var(--card-border-radius);
    padding: 1.5rem;
    box-shadow: var(--box-shadow);
    margin-top: 2rem;
}

.action-panel h3 {
    margin-bottom: 1rem;
    font-size: 1.1rem;
    color: var(--color-dark);
}

.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.expiry-alert {
    display: flex;
    align-items: flex-start;
    padding: 1.2rem;
}

.expiry-alert span {
    font-size: 2rem;
    margin-right: 1rem;
}

.expiry-alert h3 {
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: var(--color-white);
    margin: 10% auto;
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    width: 80%;
    max-width: 600px;
    animation: modalopen 0.3s;
}

@keyframes modalopen {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.7rem;
    border-bottom: 1px solid var(--color-light);
}

.modal-header h2 {
    font-size: 1.2rem;
    color: var(--color-dark);
}

.close {
    color: var(--color-dark-variant);
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--color-dark);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.breadcrumb {
    font-size: 0.9rem;
    color: var(--color-dark-variant);
}

.breadcrumb a {
    color: var(--color-primary);
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.required {
    color: var(--color-danger);
}

.no-data {
    text-align: center;
    color: var(--color-dark-variant);
    padding: 1rem;
}

small {
    display: block;
    margin-top: 0.25rem;
    color: var(--color-dark-variant);
    font-size: 0.8rem;
}
</style>

<script>
// Modal functionality
const modal = document.getElementById('spoilageModal');
const btn = document.getElementById('reportSpoilageBtn');
const closeBtn = document.getElementsByClassName('close')[0];
const cancelBtn = document.getElementsByClassName('cancel-modal')[0];

btn.onclick = function() {
    modal.style.display = 'block';
}

closeBtn.onclick = function() {
    modal.style.display = 'none';
}

cancelBtn.onclick = function() {
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Close alert messages
const alertCloseButtons = document.querySelectorAll('.alert .close-btn');
alertCloseButtons.forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});

// Form validation
document.getElementById('spoilageForm').addEventListener('submit', function(e) {
    const quantity = document.getElementById('spoiled_quantity').value;
    const cause = document.getElementById('cause').value;
    
    if (!quantity || !cause) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
    
    const maxQuantity = parseFloat(<?php echo $stock['Quantity']; ?>);
    if (parseFloat(quantity) > maxQuantity) {
        e.preventDefault();
        alert('Spoiled quantity cannot exceed current stock quantity.');
    }
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
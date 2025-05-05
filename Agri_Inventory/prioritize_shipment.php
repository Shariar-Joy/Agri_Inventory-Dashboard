<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Prioritize Shipment";

// Check if stock ID is provided
if (!isset($_GET['stock_id']) || empty($_GET['stock_id'])) {
    // Redirect to inventory page if no ID is provided
    header('Location: inventory.php');
    exit;
}

$stockId = $_GET['stock_id'];

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $priorityLevel = isset($_POST['priority_level']) ? intval($_POST['priority_level']) : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $targetShipDate = isset($_POST['target_ship_date']) ? $_POST['target_ship_date'] : '';
    
    // Validate required fields
    if ($priorityLevel < 1 || $priorityLevel > 5 || empty($reason) || empty($targetShipDate)) {
        $errorMessage = "Please fill in all required fields correctly.";
    } else {
        // Check if shipment_priority table exists
        $tableCheckQuery = "SHOW TABLES LIKE 'shipment_priority'";
        $tableCheckResult = $conn->query($tableCheckQuery);
        
        if ($tableCheckResult->num_rows === 0) {
            // Create the table if it doesn't exist
            $createTableQuery = "CREATE TABLE shipment_priority (
                PriorityID INT AUTO_INCREMENT PRIMARY KEY,
                WarehouseStockID VARCHAR(20) NOT NULL,
                PriorityLevel INT NOT NULL,
                Reason VARCHAR(100) NOT NULL,
                Notes TEXT,
                TargetShipDate DATE NOT NULL,
                CreatedBy INT,
                CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (WarehouseStockID) REFERENCES warehouse_stock(WarehouseStockID)
            )";
            
            if (!$conn->query($createTableQuery)) {
                $errorMessage = "Error creating shipment_priority table: " . $conn->error;
                // Include header
                include('includes/header.php');
                echo '<div class="alert danger"><span class="material-symbols-sharp">error</span><p>' . $errorMessage . '</p></div>';
                include('includes/footer.php');
                exit;
            }
        }
        
        // Check if priority already exists for this stock
        $checkQuery = "SELECT PriorityID FROM shipment_priority WHERE WarehouseStockID = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $stockId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update existing priority
            $updateQuery = "UPDATE shipment_priority 
                          SET PriorityLevel = ?, 
                              Reason = ?, 
                              Notes = ?, 
                              TargetShipDate = ?,
                              CreatedBy = ?,
                              CreatedAt = CURRENT_TIMESTAMP
                          WHERE WarehouseStockID = ?";
                          
            $updateStmt = $conn->prepare($updateQuery);
            $userId = $_SESSION['user_id'] ?? 1;
            $updateStmt->bind_param("isssss", $priorityLevel, $reason, $notes, $targetShipDate, $userId, $stockId);
            
            if ($updateStmt->execute()) {
                $successMessage = "Shipment priority updated successfully!";
            } else {
                $errorMessage = "Error updating priority: " . $updateStmt->error;
            }
            
            $updateStmt->close();
        } else {
            // Insert new priority
            $insertQuery = "INSERT INTO shipment_priority 
                          (WarehouseStockID, PriorityLevel, Reason, Notes, TargetShipDate, CreatedBy) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                          
            $insertStmt = $conn->prepare($insertQuery);
            $userId = $_SESSION['user_id'] ?? 1;
            $insertStmt->bind_param("sisssi", $stockId, $priorityLevel, $reason, $notes, $targetShipDate, $userId);
            
            if ($insertStmt->execute()) {
                $successMessage = "Stock item prioritized for shipment successfully!";
                
                // Update stock status to mark it as prioritized
                $updateStockQuery = "UPDATE warehouse_stock SET Status = 'Prioritized' WHERE WarehouseStockID = ?";
                $updateStockStmt = $conn->prepare($updateStockQuery);
                $updateStockStmt->bind_param("s", $stockId);
                $updateStockStmt->execute();
                $updateStockStmt->close();
                
                // Log the activity if activity_log table exists
                $tableCheckQuery = "SHOW TABLES LIKE 'activity_log'";
                $tableCheckResult = $conn->query($tableCheckQuery);
                
                if ($tableCheckResult->num_rows > 0) {
                    $activityQuery = "INSERT INTO activity_log (UserID, ActivityType, Description, EntityID, EntityType) 
                                    VALUES (?, 'PRIORITIZE', ?, ?, 'WAREHOUSE_STOCK')";
                    $activityStmt = $conn->prepare($activityQuery);
                    $description = "Prioritized stock for shipment (Priority: $priorityLevel)";
                    $activityStmt->bind_param("iss", $userId, $description, $stockId);
                    $activityStmt->execute();
                    $activityStmt->close();
                }
            } else {
                $errorMessage = "Error prioritizing stock: " . $insertStmt->error;
            }
            
            $insertStmt->close();
        }
        
        $checkStmt->close();
    }
}

// Get stock details
$query = "SELECT ws.*, b.BatchID, c.CropName, c.CropVariety, w.City as WarehouseLocation
          FROM warehouse_stock ws
          JOIN batch b ON ws.BatchID = b.BatchID
          LEFT JOIN crop c ON b.BatchID = c.BatchID
          LEFT JOIN warehouse w ON ws.WarehouseID = w.WarehouseID
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

// Get existing priority data if available
$priorityData = null;
$priorityQuery = "SELECT * FROM shipment_priority WHERE WarehouseStockID = ? ORDER BY CreatedAt DESC LIMIT 1";

// We need to check if the table exists first to avoid errors
$tableCheckQuery = "SHOW TABLES LIKE 'shipment_priority'";
$tableCheckResult = $conn->query($tableCheckQuery);

if ($tableCheckResult->num_rows > 0) {
    $priorityStmt = $conn->prepare($priorityQuery);
    $priorityStmt->bind_param("s", $stockId);
    $priorityStmt->execute();
    $priorityResult = $priorityStmt->get_result();
    
    if ($priorityResult->num_rows > 0) {
        $priorityData = $priorityResult->fetch_assoc();
    }
    
    $priorityStmt->close();
}

// Include header
include('includes/header.php');
?>

<main>
    <div class="page-header">
        <h1>Prioritize for Shipment</h1>
        <nav class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &gt;
            <a href="inventory.php">Inventory</a> &gt;
            <a href="edit_stock.php?id=<?php echo htmlspecialchars($stockId); ?>">Edit Stock</a> &gt;
            <span>Prioritize Shipment</span>
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

    <div class="card">
        <div class="card-header">
            <h2>
                <span class="material-symbols-sharp">priority_high</span>
                Prioritize Stock for Shipment
            </h2>
        </div>
        <div class="card-body">
            <div class="stock-details">
                <div class="stock-info">
                    <h3>Stock Information</h3>
                    <div class="info-group">
                        <p><strong>Stock ID:</strong> <?php echo htmlspecialchars($stockId); ?></p>
                        <p><strong>Batch ID:</strong> <?php echo htmlspecialchars($stock['BatchID']); ?></p>
                        <p><strong>Product:</strong> <?php echo htmlspecialchars($stock['CropName'] ?? 'N/A'); ?> 
                            <?php if (!empty($stock['CropVariety'])): ?>
                                (<?php echo htmlspecialchars($stock['CropVariety']); ?>)
                            <?php endif; ?>
                        </p>
                        <p><strong>Quantity:</strong> <?php echo htmlspecialchars($stock['Quantity']); ?></p>
                        <p><strong>Warehouse:</strong> <?php echo htmlspecialchars($stock['WarehouseLocation'] ?? 'N/A'); ?></p>
                        <p><strong>Expiry Date:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($stock['ExpiryDate']))); ?></p>
                    </div>
                </div>
            </div>

            <form method="POST" action="prioritize_shipment.php?stock_id=<?php echo htmlspecialchars($stockId); ?>" class="priority-form">
                <div class="form-group">
                    <label for="priority_level">Priority Level <span class="required">*</span></label>
                    <div class="priority-selector">
                        <input type="range" name="priority_level" id="priority_level" min="1" max="5" value="<?php echo $priorityData ? $priorityData['PriorityLevel'] : 3; ?>" required>
                        <div class="priority-labels">
                            <span>Low</span>
                            <span>Medium</span>
                            <span>High</span>
                        </div>
                        <div class="priority-value">
                            <span id="priorityValueDisplay"><?php echo $priorityData ? $priorityData['PriorityLevel'] : 3; ?></span>/5
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Priority <span class="required">*</span></label>
                    <select name="reason" id="reason" required>
                        <option value="">Select Reason</option>
                        <option value="Approaching Expiry" <?php echo ($priorityData && $priorityData['Reason'] == 'Approaching Expiry') ? 'selected' : ''; ?>>Approaching Expiry</option>
                        <option value="Customer Request" <?php echo ($priorityData && $priorityData['Reason'] == 'Customer Request') ? 'selected' : ''; ?>>Customer Request</option>
                        <option value="Storage Space Needed" <?php echo ($priorityData && $priorityData['Reason'] == 'Storage Space Needed') ? 'selected' : ''; ?>>Storage Space Needed</option>
                        <option value="Warehouse Consolidation" <?php echo ($priorityData && $priorityData['Reason'] == 'Warehouse Consolidation') ? 'selected' : ''; ?>>Warehouse Consolidation</option>
                        <option value="Quality Concerns" <?php echo ($priorityData && $priorityData['Reason'] == 'Quality Concerns') ? 'selected' : ''; ?>>Quality Concerns</option>
                        <option value="Inventory Optimization" <?php echo ($priorityData && $priorityData['Reason'] == 'Inventory Optimization') ? 'selected' : ''; ?>>Inventory Optimization</option>
                        <option value="Other" <?php echo ($priorityData && $priorityData['Reason'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="target_ship_date">Target Ship Date <span class="required">*</span></label>
                    <input type="date" name="target_ship_date" id="target_ship_date" value="<?php echo $priorityData ? $priorityData['TargetShipDate'] : date('Y-m-d', strtotime('+3 days')); ?>" required>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes" rows="4" placeholder="Any specific requirements or details for this shipment..."><?php echo $priorityData ? htmlspecialchars($priorityData['Notes']) : ''; ?></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-sharp">save</span>
                        <?php echo $priorityData ? 'Update Priority' : 'Set Priority'; ?>
                    </button>
                    <a href="edit_stock.php?id=<?php echo htmlspecialchars($stockId); ?>" class="btn btn-secondary">
                        <span class="material-symbols-sharp">cancel</span>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($stock['ExpiryDate']) && strtotime($stock['ExpiryDate']) < strtotime('+30 days')): ?>
    <div class="alert warning expiry-alert">
        <span class="material-symbols-sharp">warning</span>
        <div>
            <h3>Expiration Warning</h3>
            <p>This stock is expiring soon (on <?php echo date('M d, Y', strtotime($stock['ExpiryDate'])); ?>). Consider setting a high priority for shipment.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($priorityData): ?>
    <div class="card priority-history">
        <div class="card-header">
            <h2>
                <span class="material-symbols-sharp">history</span>
                Priority History
            </h2>
        </div>
        <div class="card-body">
            <?php
            // Check if activity_log table exists before querying
            $tableCheckQuery = "SHOW TABLES LIKE 'activity_log'";
            $tableCheckResult = $conn->query($tableCheckQuery);
            
            if ($tableCheckResult->num_rows > 0) {
                // Get priority history
                $historyQuery = "SELECT al.*, u.Username 
                              FROM activity_log al
                              LEFT JOIN users u ON al.UserID = u.UserID
                              WHERE al.EntityID = ? AND al.EntityType = 'WAREHOUSE_STOCK' AND al.ActivityType = 'PRIORITIZE'
                              ORDER BY al.Timestamp DESC
                              LIMIT 5";
                $historyStmt = $conn->prepare($historyQuery);
                $historyStmt->bind_param("s", $stockId);
                $historyStmt->execute();
                $historyResult = $historyStmt->get_result();
                
                if ($historyResult->num_rows > 0) {
                    ?>
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
                    <?php
                    $historyStmt->close();
                } else {
                    echo '<p class="no-data">No priority history available.</p>';
                }
            } else {
                echo '<div class="alert info">
                        <span class="material-symbols-sharp">info</span>
                        <p>Priority history tracking is not configured. Please set up the activity log database.</p>
                      </div>';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<style>
.card {
    background: var(--color-white);
    border-radius: var(--card-border-radius);
    padding: 1.5rem;
    box-shadow: var(--box-shadow);
    transition: all 300ms ease;
    margin-bottom: 2rem;
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

@media (min-width: 768px) {
    .info-group {
        grid-template-columns: 1fr 1fr 1fr;
    }
}

.info-group p {
    margin-bottom: 0.5rem;
    color: var(--color-dark-variant);
}

.priority-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.2rem;
}

@media (min-width: 768px) {
    .priority-form {
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

.btn:hover {
    opacity: 0.8;
    transform: translateY(-2px);
}

.priority-selector {
    position: relative;
    margin-bottom: 1.5rem;
}

.priority-selector input[type="range"] {
    width: 100%;
    margin-bottom: 0.5rem;
    -webkit-appearance: none;
    appearance: none;
    height: 8px;
    background: linear-gradient(to right, #4caf50, #ff9800, #f44336);
    border-radius: 5px;
    outline: none;
}

.priority-selector input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--color-primary);
    cursor: pointer;
    border: 2px solid white;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.priority-labels {
    display: flex;
    justify-content: space-between;
}

.priority-value {
    text-align: center;
    font-weight: bold;
    margin-top: 0.5rem;
    color: var(--color-primary);
}

.priority-value span {
    font-size: 1.2rem;
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

.alert.info {
    background: rgba(0, 123, 255, 0.1);
    color: #0056b3;
}

.alert span {
    font-size: 1.5rem;
}

.close-btn {
    cursor: pointer;
    margin-left: auto;
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

.no-data {
    text-align: center;
    color: var(--color-dark-variant);
    padding: 1rem;
}

.required {
    color: var(--color-danger);
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
</style>

<script>
// Priority slider value display
const prioritySlider = document.getElementById('priority_level');
const priorityDisplay = document.getElementById('priorityValueDisplay');

prioritySlider.addEventListener('input', function() {
    priorityDisplay.textContent = this.value;
    
    // Change color based on priority level
    const value = parseInt(this.value);
    let color;
    
    if (value <= 2) {
        color = '#4caf50'; // Green for low priority
    } else if (value <= 4) {
        color = '#ff9800'; // Orange for medium priority
    } else {
        color = '#f44336'; // Red for high priority
    }
    
    priorityDisplay.style.color = color;
});

// Trigger the event initially to set the correct color
prioritySlider.dispatchEvent(new Event('input'));

// Close alert messages
const alertCloseButtons = document.querySelectorAll('.alert .close-btn');
alertCloseButtons.forEach(button => {
    button.addEventListener('click', function() {
        this.parentElement.style.display = 'none';
    });
});

// Form validation
document.querySelector('.priority-form').addEventListener('submit', function(e) {
    const priorityLevel = document.getElementById('priority_level').value;
    const reason = document.getElementById('reason').value;
    const targetShipDate = document.getElementById('target_ship_date').value;
    
    if (!priorityLevel || !reason || !targetShipDate) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
    
    // Validate that ship date is not in the past
    const shipDate = new Date(targetShipDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (shipDate < today) {
        e.preventDefault();
        alert('Target ship date cannot be in the past.');
    }
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
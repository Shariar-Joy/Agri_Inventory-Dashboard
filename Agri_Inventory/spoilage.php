<?php
// Include database connection
require_once 'config.php';
// Add this function
function checkAndFixSpoilageTable($conn) {
    // Check if the table structure is already updated
    $checkQuery = "SHOW COLUMNS FROM spoilage_control LIKE 'HarvestSpoilage'";
    $result = $conn->query($checkQuery);
    
    if ($result && $result->num_rows == 0) {
        // Columns don't exist, add them
        $alterQuery = "ALTER TABLE spoilage_control 
                      ADD COLUMN HarvestSpoilage DECIMAL(5,2) DEFAULT NULL,
                      ADD COLUMN ReceivingSpoilage DECIMAL(5,2) DEFAULT NULL,
                      ADD COLUMN HandlingSpoilage DECIMAL(5,2) DEFAULT NULL,
                      ADD COLUMN DeliverySpoilage DECIMAL(5,2) DEFAULT NULL";
        
        if ($conn->query($alterQuery)) {
            return "Spoilage table structure updated successfully!";
        } else {
            return "Error updating table structure: " . $conn->error;
        }
    }
    
    return null; // No changes needed
}

// Check and fix table structure if needed
$tableUpdateMessage = checkAndFixSpoilageTable($conn);
if ($tableUpdateMessage) {
    $_SESSION['info_message'] = $tableUpdateMessage;
}

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Spoilage Management";

// Process form submission for adding a new spoilage record
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_spoilage'])) {
    $batchID = $_POST['batch_id'];
    $inspectionDate = $_POST['inspection_date'];
    $spoilagePercentage = $_POST['spoilage_percentage'];
    $spoilageCause = $_POST['spoilage_cause'];
    $actionTaken = $_POST['action_taken'];
    $notes = $_POST['notes'];
    $inspectedBy = $_SESSION['user_id']; // Current logged in user
    
    // Get the new spoilage section values
    $harvestSpoilage = isset($_POST['harvest_spoilage']) ? $_POST['harvest_spoilage'] : 0;
    $receivingSpoilage = isset($_POST['receiving_spoilage']) ? $_POST['receiving_spoilage'] : 0;
    $handlingSpoilage = isset($_POST['handling_spoilage']) ? $_POST['handling_spoilage'] : 0;
    $deliverySpoilage = isset($_POST['delivery_spoilage']) ? $_POST['delivery_spoilage'] : 0;
    
    // Generate a unique ID for the spoilage record
    $spoilageID = generateUniqueID('SPL');
    
    // Insert new spoilage record with the additional fields
    $query = "INSERT INTO spoilage_control (SpoilageID, BatchID, InspectionDate, SpoilagePercentage, 
                                         SpoilageCause, ActionTaken, InspectedBy, Notes,
                                         HarvestSpoilage, ReceivingSpoilage, HandlingSpoilage, DeliverySpoilage) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssdssssdddd", $spoilageID, $batchID, $inspectionDate, $spoilagePercentage, 
                     $spoilageCause, $actionTaken, $inspectedBy, $notes,
                     $harvestSpoilage, $receivingSpoilage, $handlingSpoilage, $deliverySpoilage);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Spoilage record added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding spoilage record: " . $conn->error;
    }
    
    // Redirect to prevent form resubmission
    header("Location: spoilage.php");
    exit();
}

// Get all spoilage records with batch and inspector details
$query = "SELECT sc.*, b.BatchID, c.CropName, c.CropType, u.username as InspectorName,
            DATE_FORMAT(sc.InspectionDate, '%M %d, %Y') as FormattedDate
          FROM spoilage_control sc
          JOIN batch b ON sc.BatchID = b.BatchID
          JOIN crop c ON b.BatchID = c.BatchID
          JOIN users u ON sc.InspectedBy = u.user_id
          ORDER BY sc.InspectionDate DESC";

$result = $conn->query($query);
$spoilageRecords = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $spoilageRecords[] = $row;
    }
}

// Get spoilage by cause for chart
$query = "SELECT SpoilageCause, COUNT(*) as Count, AVG(SpoilagePercentage) as AvgPercentage
          FROM spoilage_control
          GROUP BY SpoilageCause
          ORDER BY Count DESC";

$result = $conn->query($query);
$spoilageByCause = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $spoilageByCause[] = $row;
    }
}

// Get monthly spoilage data for trend chart
$query = "SELECT 
            DATE_FORMAT(InspectionDate, '%Y-%m') as Month,
            COUNT(*) as IncidentCount,
            AVG(SpoilagePercentage) as AvgPercentage
          FROM spoilage_control
          GROUP BY DATE_FORMAT(InspectionDate, '%Y-%m')
          ORDER BY Month ASC
          LIMIT 12";

$result = $conn->query($query);
$monthlySpoilage = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format the month for display
        $dateObj = DateTime::createFromFormat('Y-m', $row['Month']);
        $row['DisplayMonth'] = $dateObj->format('M Y');
        $monthlySpoilage[] = $row;
    }
}

// Get all batches for the dropdown in the form
$query = "SELECT b.BatchID, c.CropName, c.CropType
          FROM batch b
          JOIN crop c ON b.BatchID = c.BatchID
          ORDER BY b.BatchProductionDate DESC";

$result = $conn->query($query);
$batches = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
}

// Get average spoilage by stage for additional chart
$query = "SELECT 
            AVG(HarvestSpoilage) as AvgHarvest,
            AVG(ReceivingSpoilage) as AvgReceiving,
            AVG(HandlingSpoilage) as AvgHandling,
            AVG(DeliverySpoilage) as AvgDelivery
          FROM spoilage_control
          WHERE HarvestSpoilage IS NOT NULL 
            OR ReceivingSpoilage IS NOT NULL 
            OR HandlingSpoilage IS NOT NULL 
            OR DeliverySpoilage IS NOT NULL";

$result = $conn->query($query);
$stageSpoilage = [];

if ($result && $result->num_rows > 0) {
    $stageSpoilage = $result->fetch_assoc();
}

// Include header
include('includes/header.php');
?>

<main>
    <div class="page-header">
        <h1>Spoilage Management</h1>
        <button class="add-btn" id="addSpoilageBtn">
            <span class="material-symbols-sharp">add</span>
            <span>Add Record</span>
        </button>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success">
            <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert error">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Spoilage Overview -->
    <div class="spoilage-overview">
        <div class="insights">
            <div class="sales">
                <span class="material-symbols-sharp">error</span>
                <div class="middle">
                    <div class="left">
                        <h3>Total Spoilage Incidents</h3>
                        <h1><?php echo count($spoilageRecords); ?></h1>
                    </div>
                </div>
                <small class="text-muted">All Time</small>
            </div>

            <div class="expenses">
                <span class="material-symbols-sharp">delete</span>
                <div class="middle">
                    <div class="left">
                        <h3>Average Spoilage</h3>
                        <h1>
                            <?php 
                                $totalPercentage = 0;
                                foreach ($spoilageRecords as $record) {
                                    $totalPercentage += $record['SpoilagePercentage'];
                                }
                                echo count($spoilageRecords) > 0 ? number_format($totalPercentage / count($spoilageRecords), 2) . '%' : '0%';
                            ?>
                        </h1>
                    </div>
                </div>
                <small class="text-muted">Percentage</small>
            </div>

            <div class="income">
                <span class="material-symbols-sharp">assignment</span>
                <div class="middle">
                    <div class="left">
                        <h3>Most Common Cause</h3>
                        <h1>
                            <?php 
                                echo !empty($spoilageByCause) ? $spoilageByCause[0]['SpoilageCause'] : 'N/A';
                            ?>
                        </h1>
                    </div>
                </div>
                <small class="text-muted">
                    <?php 
                        echo !empty($spoilageByCause) ? $spoilageByCause[0]['Count'] . ' incidents' : '';
                    ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-container">
        <div class="chart">
            <h2>Spoilage by Cause</h2>
            <div id="spoilageByTypeChart"></div>
        </div>
        
        <div class="chart">
            <h2>Monthly Spoilage Trend</h2>
            <div id="spoilageTrendChart"></div>
        </div>
        
        <div class="chart">
            <h2>Spoilage by Stage</h2>
            <div id="spoilageByStageChart"></div>
        </div>
    </div>

    <!-- Spoilage Records Table -->
    <div class="recent-spoilage">
        <h2>Spoilage Records</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Batch</th>
                    <th>Crop</th>
                    <th>Inspection Date</th>
                    <th>Total %</th>
                    <th>Harvest %</th>
                    <th>Receiving %</th>
                    <th>Handling %</th>
                    <th>Delivery %</th>
                    <th>Cause</th>
                    <th>Action Taken</th>
                    <th>Inspector</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($spoilageRecords)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center;">No spoilage records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($spoilageRecords as $record): ?>
                        <tr>
                            <td><?php echo $record['SpoilageID']; ?></td>
                            <td><?php echo $record['BatchID']; ?></td>
                            <td><?php echo $record['CropName'] . ' (' . $record['CropType'] . ')'; ?></td>
                            <td><?php echo $record['FormattedDate']; ?></td>
                            <td><?php echo $record['SpoilagePercentage'] . '%'; ?></td>
                            <td><?php echo isset($record['HarvestSpoilage']) ? $record['HarvestSpoilage'] . '%' : 'N/A'; ?></td>
                            <td><?php echo isset($record['ReceivingSpoilage']) ? $record['ReceivingSpoilage'] . '%' : 'N/A'; ?></td>
                            <td><?php echo isset($record['HandlingSpoilage']) ? $record['HandlingSpoilage'] . '%' : 'N/A'; ?></td>
                            <td><?php echo isset($record['DeliverySpoilage']) ? $record['DeliverySpoilage'] . '%' : 'N/A'; ?></td>
                            <td><?php echo $record['SpoilageCause']; ?></td>
                            <td><?php echo $record['ActionTaken']; ?></td>
                            <td><?php echo $record['InspectorName']; ?></td>
                            <td>
                                <button class="view-btn" data-id="<?php echo $record['SpoilageID']; ?>">
                                    <span class="material-symbols-sharp">visibility</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Spoilage Modal -->
    <div id="addSpoilageModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add Spoilage Record</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="batch_id">Batch:</label>
                    <select name="batch_id" id="batch_id" required>
                        <option value="">Select Batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['BatchID']; ?>">
                                <?php echo $batch['BatchID'] . ' - ' . $batch['CropName'] . ' (' . $batch['CropType'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="inspection_date">Inspection Date:</label>
                    <input type="date" name="inspection_date" id="inspection_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="spoilage_percentage">Total Spoilage Percentage:</label>
                    <input type="number" name="spoilage_percentage" id="spoilage_percentage" min="0.01" max="100" step="0.01" required>
                </div>
                
                <!-- New fields for stage-specific spoilage -->
                <div class="section-heading">Spoilage by Stage (in %)</div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="harvest_spoilage">Harvest Spoilage:</label>
                        <input type="number" name="harvest_spoilage" id="harvest_spoilage" min="0" max="100" step="0.01" value="0">
                    </div>
                    
                    <div class="form-group half">
                        <label for="receiving_spoilage">Receiving Spoilage:</label>
                        <input type="number" name="receiving_spoilage" id="receiving_spoilage" min="0" max="100" step="0.01" value="0">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="handling_spoilage">Handling Spoilage:</label>
                        <input type="number" name="handling_spoilage" id="handling_spoilage" min="0" max="100" step="0.01" value="0">
                    </div>
                    
                    <div class="form-group half">
                        <label for="delivery_spoilage">Delivery Spoilage:</label>
                        <input type="number" name="delivery_spoilage" id="delivery_spoilage" min="0" max="100" step="0.01" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="spoilage_cause">Cause:</label>
                    <select name="spoilage_cause" id="spoilage_cause" required>
                        <option value="">Select Cause</option>
                        <option value="Temperature">Temperature Issues</option>
                        <option value="Humidity">Humidity Issues</option>
                        <option value="Transport Damage">Transport Damage</option>
                        <option value="Pest Damage">Pest Damage</option>
                        <option value="Harvesting Damage">Harvesting Damage</option>
                        <option value="Handling Damage">Handling Damage</option>
                        <option value="Storage Duration">Storage Duration</option>
                        <option value="Quality Issues">Quality Issues</option>
                        <option value="Packaging Failure">Packaging Failure</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="action_taken">Action Taken:</label>
                    <select name="action_taken" id="action_taken" required>
                        <option value="">Select Action</option>
                        <option value="Disposed">Disposed</option>
                        <option value="Composted">Composted</option>
                        <option value="Reprocessed">Reprocessed</option>
                        <option value="Discounted Sale">Discounted Sale</option>
                        <option value="Animal Feed">Converted to Animal Feed</option>
                        <option value="Partial Recovery">Partial Recovery</option>
                        <option value="None">None</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea name="notes" id="notes" rows="4"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_spoilage" class="btn btn-primary">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Spoilage Detail Modal -->
    <div id="viewSpoilageModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Spoilage Details</h2>
            <div id="spoilageDetails"></div>
        </div>
    </div>
</main>

<style>
/* Spoilage Page Styles */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.add-btn {
    display: flex;
    align-items: center;
    background: var(--color-primary);
    color: white;
    border: none;
    padding: 0.7rem 1.2rem;
    border-radius: var(--border-radius-1);
    cursor: pointer;
    transition: all 0.3s ease;
}

.add-btn:hover {
    background: var(--color-primary-variant);
}

.add-btn span {
    margin-right: 0.5rem;
}

.charts-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
    margin-bottom: 2rem;
}

.chart {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    transition: all 0.3s ease;
}

.chart h2 {
    margin-bottom: 1rem;
    color: var(--color-dark);
}

#spoilageByTypeChart, #spoilageTrendChart, #spoilageByStageChart {
    height: 300px;
    width: 100%;
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
    overflow-x: auto;
}

.recent-spoilage table:hover {
    box-shadow: none;
}

.recent-spoilage table tbody td {
    height: 2.8rem;
    border-bottom: 1px solid var(--color-light);
    color: var(--color-dark-variant);
}

.recent-spoilage tbody tr:last-child td {
    border: none;
}

.view-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--color-primary);
}

.view-btn:hover {
    color: var(--color-primary-variant);
}

/* Modal Styles */
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
    margin: 5% auto;
    padding: 2rem;
    border-radius: var(--card-border-radius);
    width: 80%;
    max-width: 800px;
    position: relative;
}

.close {
    color: var(--color-dark);
    position: absolute;
    top: 1rem;
    right: 1.5rem;
    font-size: 1.8rem;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--color-danger);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: var(--border-radius-1);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.7rem 1.5rem;
    border: none;
    border-radius: var(--border-radius-1);
    cursor: pointer;
    font-weight: 500;
}

.btn-primary {
    background: var(--color-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--color-primary-variant);
}

/* New form styles */
.section-heading {
    font-weight: 600;
    color: var(--color-dark);
    margin: 1.5rem 0 1rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5rem;
}

.form-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 0;
}

.form-group.half {
    flex: 1;
    margin-bottom: 1.5rem;
}

/* Alert Styles */
.alert {
    padding: 1rem;
    border-radius: var(--border-radius-1);
    margin-bottom: 1.5rem;
}

.alert.success {
    background-color: #d4edda;
    color: #155724;
}

.alert.error {
    background-color: #f8d7da;
    color: #721c24;
}

/* Spoilage Details Styles */
#spoilageDetails {
    margin-top: 1rem;
}

.detail-item {
    margin-bottom: 1rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5rem;
}

.detail-item:last-child {
    border-bottom: none;
}

.label {
    font-weight: 600;
    color: var(--color-dark);
}

.value {
    color: var(--color-dark-variant);
}

/* Responsive Adjustments */
@media screen and (max-width: 992px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .form-group.half {
        width: 100%;
    }
}

@media screen and (max-width: 768px) {
    .charts-container {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        padding: 1.5rem;
    }
    
    .recent-spoilage table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal functionality
    const addModal = document.getElementById('addSpoilageModal');
    const viewModal = document.getElementById('viewSpoilageModal');
    const addBtn = document.getElementById('addSpoilageBtn');
    const closeBtns = document.querySelectorAll('.close');
    
    addBtn.addEventListener('click', function() {
        addModal.style.display = 'block';
    });
    
    closeBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            addModal.style.display = 'none';
            viewModal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === addModal) {
            addModal.style.display = 'none';
        }
        if (event.target === viewModal) {
            viewModal.style.display = 'none';
        }
    });

    // Form validation to ensure stage spoilage doesn't exceed total
    const totalSpoilageInput = document.getElementById('spoilage_percentage');
    const harvestSpoilageInput = document.getElementById('harvest_spoilage');
    const receivingSpoilageInput = document.getElementById('receiving_spoilage');
    const handlingSpoilageInput = document.getElementById('handling_spoilage');
    const deliverySpoilageInput = document.getElementById('delivery_spoilage');
    
    const stageSpoilageInputs = [
        harvestSpoilageInput,
        receivingSpoilageInput,
        handlingSpoilageInput,
        deliverySpoilageInput
    ];
    
    stageSpoilageInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            validateStageSpoilage();
        });
    });
    
    totalSpoilageInput.addEventListener('input', function() {
        validateStageSpoilage();
    });
    
    function validateStageSpoilage() {
        const total = parseFloat(totalSpoilageInput.value) || 0;
        let stageTotal = 0;
        
        stageSpoilageInputs.forEach(function(input) {
            stageTotal += parseFloat(input.value) || 0;
        });
        
        if (stageTotal > total) {
            alert('Sum of stage spoilage percentages cannot exceed the total spoilage percentage');
            stageSpoilageInputs.forEach(function(input) {
                input.value = 0;
            });
        }
    }

    // View spoilage details
    const viewBtns = document.querySelectorAll('.view-btn');
    viewBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const spoilageId = this.getAttribute('data-id');
            
            // AJAX request to get spoilage details
            fetch('get_spoilage_details.php?id=' + spoilageId)
                .then(response => response.json())
                .then(data => {
                    const detailsContainer = document.getElementById('spoilageDetails');
                    
                    // Format the details HTML
                    let html = `
                        <div class="detail-item">
                            <span class="label">Spoilage ID:</span>
                            <span class="value">${data.SpoilageID}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Batch:</span>
                            <span class="value">${data.BatchID}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Crop:</span>
                            <span class="value">${data.CropName} (${data.CropType})</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Inspection Date:</span>
                            <span class="value">${data.FormattedDate}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Total Spoilage Percentage:</span>
                            <span class="value">${data.SpoilagePercentage}%</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Harvest Spoilage:</span>
                            <span class="value">${data.HarvestSpoilage ? data.HarvestSpoilage + '%' : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Receiving Spoilage:</span>
                            <span class="value">${data.ReceivingSpoilage ? data.ReceivingSpoilage + '%' : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Handling Spoilage:</span>
                            <span class="value">${data.HandlingSpoilage ? data.HandlingSpoilage + '%' : 'N/A'}</span>
                        </div>
                          <span class="label">Delivery Spoilage:</span>
                            <span class="value">${data.DeliverySpoilage ? data.DeliverySpoilage + '%' : 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Cause of Spoilage:</span>
                            <span class="value">${data.SpoilageCause}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Action Taken:</span>
                            <span class="value">${data.ActionTaken}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Inspector:</span>
                            <span class="value">${data.InspectorName}</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Notes:</span>
                            <span class="value">${data.Notes || 'No notes available'}</span>
                        </div>
                    `;
                    
                    detailsContainer.innerHTML = html;
                    viewModal.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching spoilage details:', error);
                });
        });
    });
    
    // Initialize Charts
    // 1. Spoilage by Cause Chart
    const spoilageByCauseData = <?php echo json_encode($spoilageByCause); ?>;
    
    if (spoilageByCauseData.length > 0) {
        const categories = spoilageByCauseData.map(item => item.SpoilageCause);
        const counts = spoilageByCauseData.map(item => parseInt(item.Count));
        const percentages = spoilageByCauseData.map(item => parseFloat(item.AvgPercentage).toFixed(2));
        
        const spoilageByTypeOptions = {
            series: [{
                name: 'Incidents',
                type: 'column',
                data: counts
            }, {
                name: 'Avg Percentage',
                type: 'line',
                data: percentages
            }],
            chart: {
                height: 300,
                type: 'line',
                toolbar: {
                    show: false
                }
            },
            stroke: {
                width: [0, 3]
            },
            dataLabels: {
                enabled: true,
                enabledOnSeries: [1]
            },
            labels: categories,
            xaxis: {
                type: 'category',
                labels: {
                    rotate: -45,
                    style: {
                        fontSize: '12px'
                    }
                }
            },
            yaxis: [{
                title: {
                    text: 'Incident Count'
                }
            }, {
                opposite: true,
                title: {
                    text: 'Avg Spoilage %'
                }
            }],
            colors: ['#008FFB', '#FF4560']
        };

        const spoilageByTypeChart = new ApexCharts(document.querySelector("#spoilageByTypeChart"), spoilageByTypeOptions);
        spoilageByTypeChart.render();
    }
    
    // 2. Monthly Spoilage Trend Chart
    const monthlySpoilageData = <?php echo json_encode($monthlySpoilage); ?>;
    
    if (monthlySpoilageData.length > 0) {
        const months = monthlySpoilageData.map(item => item.DisplayMonth);
        const incidents = monthlySpoilageData.map(item => parseInt(item.IncidentCount));
        const avgPercentages = monthlySpoilageData.map(item => parseFloat(item.AvgPercentage).toFixed(2));
        
        const spoilageTrendOptions = {
            series: [{
                name: 'Incidents',
                type: 'column',
                data: incidents
            }, {
                name: 'Avg Percentage',
                type: 'line',
                data: avgPercentages
            }],
            chart: {
                height: 300,
                type: 'line',
                toolbar: {
                    show: false
                }
            },
            stroke: {
                width: [0, 3]
            },
            dataLabels: {
                enabled: false
            },
            labels: months,
            xaxis: {
                type: 'category'
            },
            yaxis: [{
                title: {
                    text: 'Incident Count'
                }
            }, {
                opposite: true,
                title: {
                    text: 'Avg Spoilage %'
                }
            }],
            colors: ['#00E396', '#775DD0']
        };

        const spoilageTrendChart = new ApexCharts(document.querySelector("#spoilageTrendChart"), spoilageTrendOptions);
        spoilageTrendChart.render();
    }
    
    // 3. Spoilage by Stage Chart
    const stageSpoilageData = <?php echo json_encode($stageSpoilage); ?>;
    
    if (Object.keys(stageSpoilageData).length > 0) {
        const stageOptions = {
            series: [{
                name: 'Average Spoilage %',
                data: [
                    parseFloat(stageSpoilageData.AvgHarvest || 0).toFixed(2),
                    parseFloat(stageSpoilageData.AvgReceiving || 0).toFixed(2),
                    parseFloat(stageSpoilageData.AvgHandling || 0).toFixed(2),
                    parseFloat(stageSpoilageData.AvgDelivery || 0).toFixed(2)
                ]
            }],
            chart: {
                type: 'bar',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: true,
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    return val + '%';
                }
            },
            xaxis: {
                categories: ['Harvest', 'Receiving', 'Handling', 'Delivery'],
                title: {
                    text: 'Average Spoilage Percentage'
                }
            },
            colors: ['#FEB019']
        };

        const stageChart = new ApexCharts(document.querySelector("#spoilageByStageChart"), stageOptions);
        stageChart.render();
    }
});
</script>

<?php include('includes/footer.php'); ?>
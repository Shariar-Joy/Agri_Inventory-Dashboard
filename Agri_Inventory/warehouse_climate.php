<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Warehouse Climate Control";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new climate log entry
        if ($_POST['action'] === 'add_log') {
            $warehouseID = $_POST['warehouseID'];
            $temperature = $_POST['temperature'];
            $humidity = $_POST['humidity'];
            $recordedAt = $_POST['recordedAt'] ?: date('Y-m-d H:i:s');
            
            // Generate unique ClimateLogID
            $climateLogID = generateUniqueID('CL');
            
            $query = "INSERT INTO warehouse_climate_log (ClimateLogID, WarehouseID, RecordedAt, Temperature, Humidity) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssdd", $climateLogID, $warehouseID, $recordedAt, $temperature, $humidity);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Climate log added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding climate log: " . $conn->error;
            }
            $stmt->close();
            
            // Redirect to avoid form resubmission
            header("Location: warehouse_climate.php");
            exit();
        }
        
        // Delete climate log entry
        if ($_POST['action'] === 'delete_log' && isset($_POST['climateLogID'])) {
            $climateLogID = $_POST['climateLogID'];
            
            $query = "DELETE FROM warehouse_climate_log WHERE ClimateLogID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $climateLogID);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Climate log deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting climate log: " . $conn->error;
            }
            $stmt->close();
            
            // Redirect to avoid form resubmission
            header("Location: warehouse_climate.php");
            exit();
        }
    }
}

// Get all warehouses for dropdown
$query = "SELECT WarehouseID, CONCAT('Warehouse-', WarehouseID, ' (', City, ')') AS WarehouseName 
          FROM warehouse 
          ORDER BY City";
$warehousesResult = $conn->query($query);
$warehouses = [];
if ($warehousesResult && $warehousesResult->num_rows > 0) {
    while ($row = $warehousesResult->fetch_assoc()) {
        $warehouses[] = $row;
    }
}

// Get selected warehouse
$selectedWarehouse = isset($_GET['warehouse']) ? $_GET['warehouse'] : (isset($warehouses[0]) ? $warehouses[0]['WarehouseID'] : '');

// Get date filter
$dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get climate logs for selected warehouse
$climateLogs = [];
if ($selectedWarehouse) {
    $query = "SELECT cl.*, CONCAT('Warehouse-', w.WarehouseID, ' (', w.City, ')') AS WarehouseName
              FROM warehouse_climate_log cl
              JOIN warehouse w ON cl.WarehouseID = w.WarehouseID
              WHERE cl.WarehouseID = ? AND DATE(cl.RecordedAt) = ?
              ORDER BY cl.RecordedAt DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $selectedWarehouse, $dateFilter);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $climateLogs[] = $row;
        }
    }
    $stmt->close();
}

// Get temperature and humidity data for charts
$tempData = [];
$humidityData = [];
$timeLabels = [];

foreach ($climateLogs as $log) {
    $timeLabels[] = date('H:i', strtotime($log['RecordedAt']));
    $tempData[] = $log['Temperature'];
    $humidityData[] = $log['Humidity'];
}

// Reverse arrays to show chronological order
$timeLabels = array_reverse($timeLabels);
$tempData = array_reverse($tempData);
$humidityData = array_reverse($humidityData);

// Get warehouse temperature thresholds (this would come from a settings table in a real application)
// For now using hardcoded values based on common warehouse types
$warehouseThresholds = [
    'min_temp' => 2.0,
    'max_temp' => 25.0,
    'min_humidity' => 30.0,
    'max_humidity' => 70.0
];

// Include header
include('includes/header.php');
?>

<main>
    <h1>Warehouse Climate Control</h1>
    
    <div class="top-controls">
        <div class="date">
            <input type="date" id="date-filter" value="<?php echo $dateFilter; ?>">
        </div>
        <div class="refresh-button">
            <button id="refresh-btn">
                <span class="material-symbols-sharp">refresh</span> Refresh
            </button>
        </div>
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
        <div class="alert danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Warehouse Selection Form -->
    <div class="warehouse-selection">
        <form action="" method="GET" id="warehouse-form">
            <div class="form-control">
                <label for="warehouse">Select Warehouse:</label>
                <select name="warehouse" id="warehouse" required>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo $warehouse['WarehouseID']; ?>" <?php echo ($selectedWarehouse == $warehouse['WarehouseID']) ? 'selected' : ''; ?>>
                            <?php echo $warehouse['WarehouseName']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-control">
                <label for="date">Date:</label>
                <input type="date" name="date" id="date" value="<?php echo $dateFilter; ?>" required>
            </div>
            <button type="submit" class="btn">Apply Filter</button>
        </form>
    </div>

    <!-- Climate Monitor Dashboard -->
    <div class="climate-dashboard">
        <div class="climate-cards">
            <!-- Current Temperature Card -->
            <div class="climate-card">
                <div class="card-header">
                    <h3>Current Temperature</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($tempData)): ?>
                        <div class="climate-value">
                            <span class="value"><?php echo number_format(end($tempData), 1); ?></span>
                            <span class="unit">°C</span>
                        </div>
                        <?php 
                            $tempStatus = 'normal';
                            if (end($tempData) > $warehouseThresholds['max_temp']) {
                                $tempStatus = 'high';
                            } elseif (end($tempData) < $warehouseThresholds['min_temp']) {
                                $tempStatus = 'low';
                            }
                        ?>
                        <div class="status <?php echo $tempStatus; ?>">
                            <?php 
                                if ($tempStatus === 'high') {
                                    echo '<i class="material-symbols-sharp">warning</i> Above threshold';
                                } elseif ($tempStatus === 'low') {
                                    echo '<i class="material-symbols-sharp">warning</i> Below threshold';
                                } else {
                                    echo '<i class="material-symbols-sharp">check_circle</i> Normal range';
                                }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No data available</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="ranges">
                        <span>Min: <?php echo $warehouseThresholds['min_temp']; ?>°C</span>
                        <span>Max: <?php echo $warehouseThresholds['max_temp']; ?>°C</span>
                    </div>
                </div>
            </div>

            <!-- Current Humidity Card -->
            <div class="climate-card">
                <div class="card-header">
                    <h3>Current Humidity</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($humidityData)): ?>
                        <div class="climate-value">
                            <span class="value"><?php echo number_format(end($humidityData), 1); ?></span>
                            <span class="unit">%</span>
                        </div>
                        <?php 
                            $humidityStatus = 'normal';
                            if (end($humidityData) > $warehouseThresholds['max_humidity']) {
                                $humidityStatus = 'high';
                            } elseif (end($humidityData) < $warehouseThresholds['min_humidity']) {
                                $humidityStatus = 'low';
                            }
                        ?>
                        <div class="status <?php echo $humidityStatus; ?>">
                            <?php 
                                if ($humidityStatus === 'high') {
                                    echo '<i class="material-symbols-sharp">warning</i> Above threshold';
                                } elseif ($humidityStatus === 'low') {
                                    echo '<i class="material-symbols-sharp">warning</i> Below threshold';
                                } else {
                                    echo '<i class="material-symbols-sharp">check_circle</i> Normal range';
                                }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No data available</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="ranges">
                        <span>Min: <?php echo $warehouseThresholds['min_humidity']; ?>%</span>
                        <span>Max: <?php echo $warehouseThresholds['max_humidity']; ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Last Updated Card -->
            <div class="climate-card">
                <div class="card-header">
                    <h3>Last Updated</h3>
                </div>
                <div class="card-body last-updated">
                    <?php if (!empty($climateLogs)): ?>
                        <div class="update-time">
                            <span class="material-symbols-sharp">schedule</span>
                            <span><?php echo date('M d, Y H:i', strtotime($climateLogs[0]['RecordedAt'])); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No data available</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <button class="btn" id="add-reading-btn">
                        <span class="material-symbols-sharp">add</span> Add Reading
                    </button>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="climate-charts">
            <div class="chart-container">
                <h3>Temperature & Humidity (24 Hours)</h3>
                <canvas id="climateChart"></canvas>
            </div>
        </div>

        <!-- Climate Logs Table -->
        <div class="recent-logs">
            <h2>Climate Log Records</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Warehouse</th>
                        <th>Date & Time</th>
                        <th>Temperature (°C)</th>
                        <th>Humidity (%)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($climateLogs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No climate logs found for this date.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($climateLogs as $log): ?>
                            <tr>
                                <td><?php echo $log['ClimateLogID']; ?></td>
                                <td><?php echo $log['WarehouseName']; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($log['RecordedAt'])); ?></td>
                                <td class="<?php echo ($log['Temperature'] > $warehouseThresholds['max_temp'] || $log['Temperature'] < $warehouseThresholds['min_temp']) ? 'warning' : ''; ?>">
                                    <?php echo number_format($log['Temperature'], 1); ?>°C
                                </td>
                                <td class="<?php echo ($log['Humidity'] > $warehouseThresholds['max_humidity'] || $log['Humidity'] < $warehouseThresholds['min_humidity']) ? 'warning' : ''; ?>">
                                    <?php echo number_format($log['Humidity'], 1); ?>%
                                </td>
                                <td>
                                    <form method="post" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                        <input type="hidden" name="action" value="delete_log">
                                        <input type="hidden" name="climateLogID" value="<?php echo $log['ClimateLogID']; ?>">
                                        <button type="submit" class="btn-icon delete">
                                            <span class="material-symbols-sharp">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Reading Modal -->
    <div id="add-reading-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Add Climate Reading</h2>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_log">
                
                <div class="form-control">
                    <label for="modal-warehouse">Warehouse:</label>
                    <select name="warehouseID" id="modal-warehouse" required>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['WarehouseID']; ?>" <?php echo ($selectedWarehouse == $warehouse['WarehouseID']) ? 'selected' : ''; ?>>
                                <?php echo $warehouse['WarehouseName']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-control">
                    <label for="temperature">Temperature (°C):</label>
                    <input type="number" name="temperature" id="temperature" step="0.1" min="-20" max="50" required>
                </div>
                
                <div class="form-control">
                    <label for="humidity">Humidity (%):</label>
                    <input type="number" name="humidity" id="humidity" step="0.1" min="0" max="100" required>
                </div>
                
                <div class="form-control">
                    <label for="recordedAt">Date & Time:</label>
                    <input type="datetime-local" name="recordedAt" id="recordedAt" value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn cancel">Cancel</button>
                    <button type="submit" class="btn primary">Save Reading</button>
                </div>
            </form>
        </div>
    </div>
</main>

<!-- Include Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
// Chart initialization
document.addEventListener('DOMContentLoaded', function() {
    // Chart data
    const timeLabels = <?php echo json_encode($timeLabels); ?>;
    const tempData = <?php echo json_encode($tempData); ?>;
    const humidityData = <?php echo json_encode($humidityData); ?>;
    const minTemp = <?php echo $warehouseThresholds['min_temp']; ?>;
    const maxTemp = <?php echo $warehouseThresholds['max_temp']; ?>;
    const minHumidity = <?php echo $warehouseThresholds['min_humidity']; ?>;
    const maxHumidity = <?php echo $warehouseThresholds['max_humidity']; ?>;
    
    // Create chart if we have data
    if (timeLabels.length > 0) {
        const ctx = document.getElementById('climateChart').getContext('2d');
        const climateChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: timeLabels,
                datasets: [{
                    label: 'Temperature (°C)',
                    data: tempData,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y'
                }, {
                    label: 'Humidity (%)',
                    data: humidityData,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Time'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Temperature (°C)'
                        },
                        suggestedMin: Math.min(minTemp - 5, Math.min(...tempData) - 2),
                        suggestedMax: Math.max(maxTemp + 5, Math.max(...tempData) + 2)
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Humidity (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        suggestedMin: Math.min(minHumidity - 5, Math.min(...humidityData) - 2),
                        suggestedMax: Math.max(maxHumidity + 5, Math.max(...humidityData) + 2)
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    annotation: {
                        annotations: {
                            minTempLine: {
                                type: 'line',
                                yMin: minTemp,
                                yMax: minTemp,
                                borderColor: 'rgba(255, 99, 132, 0.5)',
                                borderWidth: 1,
                                borderDash: [6, 6],
                                label: {
                                    enabled: true,
                                    content: 'Min Temp'
                                }
                            },
                            maxTempLine: {
                                type: 'line',
                                yMin: maxTemp,
                                yMax: maxTemp,
                                borderColor: 'rgba(255, 99, 132, 0.5)',
                                borderWidth: 1,
                                borderDash: [6, 6],
                                label: {
                                    enabled: true,
                                    content: 'Max Temp'
                                }
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Modal functionality
    const modal = document.getElementById('add-reading-modal');
    const addBtn = document.getElementById('add-reading-btn');
    const closeSpan = document.getElementsByClassName('close')[0];
    const cancelBtn = document.querySelector('.btn.cancel');
    
    addBtn.onclick = function() {
        modal.style.display = 'block';
    }
    
    closeSpan.onclick = function() {
        modal.style.display = 'none';
    }
    
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            modal.style.display = 'none';
        }
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    
    // Auto-refresh
    const refreshBtn = document.getElementById('refresh-btn');
    
    refreshBtn.addEventListener('click', function() {
        location.reload();
    });
    
    // Form submission
    const warehouseForm = document.getElementById('warehouse-form');
    const warehouseSelect = document.getElementById('warehouse');
    const dateInput = document.getElementById('date');
    const dateFilter = document.getElementById('date-filter');
    
    dateFilter.addEventListener('change', function() {
        dateInput.value = this.value;
        warehouseForm.submit();
    });
});
</script>

<style>
/* Climate Control specific styles */
.climate-dashboard {
    margin-top: 2rem;
}

.climate-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.climate-card {
    background: var(--color-white);
    border-radius: var(--card-border-radius);
    padding: 1.5rem;
    box-shadow: var(--box-shadow);
    transition: all 0.3s ease;
}

.climate-card:hover {
    box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.1);
}

.climate-card .card-header h3 {
    margin-bottom: 1rem;
    font-size: 1rem;
    color: var(--color-dark);
}

.climate-card .card-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 8rem;
}

.climate-value {
    display: flex;
    align-items: baseline;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--color-dark);
}

.climate-value .unit {
    font-size: 1.2rem;
    margin-left: 0.3rem;
    color: var(--color-dark-variant);
}

.status {
    display: flex;
    align-items: center;
    margin-top: 1rem;
    padding: 0.3rem 0.8rem;
    border-radius: 0.4rem;
    font-size: 0.9rem;
}

.status i {
    margin-right: 0.5rem;
}

.status.normal {
    background-color: #e0f2f1;
    color: #00897b;
}

.status.high, .status.low {
    background-color: #ffebee;
    color: #c62828;
}

.update-time {
    display: flex;
    align-items: center;
    font-size: 1.2rem;
    color: var(--color-dark);
}

.update-time span {
    margin-right: 0.5rem;
}

.no-data {
    color: var(--color-dark-variant);
    font-style: italic;
}

.card-footer {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
}

.ranges {
    display: flex;
    justify-content: space-between;
    width: 100%;
    font-size: 0.8rem;
    color: var(--color-dark-variant);
}

.chart-container {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
    height: 300px;
}

.chart-container h3 {
    margin-bottom: 1rem;
    font-size: 1rem;
    color: var(--color-dark);
}

.climate-charts {
    margin-bottom: 2rem;
}

.top-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.refresh-button button {
    display: flex;
    align-items: center;
    background: var(--color-light);
    border: none;
    border-radius: var(--border-radius-1);
    padding: 0.5rem 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.refresh-button button:hover {
    background: var(--color-primary);
    color: var(--color-white);
}

.refresh-button button span {
    margin-right: 0.5rem;
}

.recent-logs td.warning {
    color: #c62828;
    font-weight: 600;
}

.warehouse-selection {
    background: var(--color-white);
    padding: 1.5rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
}

.warehouse-selection form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 100;
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
    padding: 2rem;
    border-radius: var(--card-border-radius);
    box-shadow: var(--box-shadow);
    width: 80%;
    max-width: 600px;
    position: relative;
}

.close {
    position: absolute;
    right: 1.5rem;
    top: 1.5rem;
    color: var(--color-dark-variant);
    font-size: 1.8rem;
    cursor: pointer;
}

.close:hover {
    color: var(--color-danger);
}

.form-control {
    margin-bottom: 1.5rem;
}

.form-control label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-control input,
.form-control select {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid var(--color-light);
    border-radius: var(--border-radius-1);
    background: var(--color-white);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

/* Alert styles */
.alert {
    padding: 1rem;
    border-radius: var(--border-radius-1);
    margin-bottom: 1.5rem;
}

.alert.success {
    background-color: #e0f2f1;
    color: #00897b;
    border-left: 4px solid #00897b;
}

.alert.danger {
    background-color: #ffebee;
    color: #c62828;
    border-left: 4px solid #c62828;
}

/* Button styles */
.btn {
    padding: 0.7rem 1.2rem;
    border: none;
    border-radius: var(--border-radius-1);
    background: var(--color-light);
    color: var(--color-dark);
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn:hover {
    background: var(--color-primary);
    color: var(--color-white);
}

.btn.primary {
    background: var(--color-primary);
    color: var(--color-white);
}

.btn.primary:hover {
    background: var(--color-primary-variant);
}

.btn.cancel {
    background: var(--color-light);
}

.btn.cancel:hover {
    background: var(--color-dark-variant);
    color: var(--color-white);
}

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-icon.delete {
    color: var(--color-danger);
}

.btn-icon.delete:hover {
    transform: scale(1.2);
}

/* Helper function - not visible in the UI */
<?php
// Function to generate a unique ID
//function generateUniqueID($prefix = '') {
//    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
//    $id = $prefix;
//    for ($i = 0; $i < 10; $i++) {
//        $id .= $chars[rand(0, strlen($chars) - 1)];
//    }
//    return $id;
//}

// Function to format currency
//*function formatCurrency($amount, $currency = 'USD') {
//    $formats = [
//        'USD' => ['symbol' => '$', 'decimals' => 2],
//        'EUR' => ['symbol' => '€', 'decimals' => 2],
//        'GBP' => ['symbol' => '£', 'decimals' => 2]
//    ];
    
//    $format = $formats[$currency] ?? $formats['USD'];
//   return $format['symbol'] . number_format($amount, $format['decimals']);
//}

// Function to check climate thresholds
function checkClimateThresholds($temperature, $humidity, $thresholds) {
    $status = [
        'temperature' => 'normal',
        'humidity' => 'normal'
    ];
    
    if ($temperature > $thresholds['max_temp']) {
        $status['temperature'] = 'high';
    } elseif ($temperature < $thresholds['min_temp']) {
        $status['temperature'] = 'low';
    }
    
    if ($humidity > $thresholds['max_humidity']) {
        $status['humidity'] = 'high';
    } elseif ($humidity < $thresholds['min_humidity']) {
        $status['humidity'] = 'low';
    }
    
    return $status;
}
?>

<?php include('includes/footer.php'); ?>
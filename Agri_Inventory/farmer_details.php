<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Get farmer ID from URL
if (!isset($_GET['id'])) {
    // Redirect if no ID provided
    header('Location: farmers.php');
    exit();
}

$farmerId = sanitizeInput($_GET['id']);

// Get farmer details
$query = "SELECT * FROM farmer WHERE FarmerID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $farmerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    // Farmer not found
    $_SESSION['error_message'] = "Farmer not found.";
    header('Location: farmers.php');
    exit();
}

$farmer = $result->fetch_assoc();
$stmt->close();

// Get contact numbers
$contactQuery = "SELECT ContactNumber FROM farmer_contact WHERE FarmerID = ?";
$contactStmt = $conn->prepare($contactQuery);
$contactStmt->bind_param("s", $farmerId);
$contactStmt->execute();
$contactResult = $contactStmt->get_result();

$contactNumbers = [];
while ($contactRow = $contactResult->fetch_assoc()) {
    $contactNumbers[] = $contactRow['ContactNumber'];
}
$contactStmt->close();

// Get harvest summary
$harvestSummaryQuery = "SELECT 
                          COUNT(*) as TotalHarvestSessions,
                          SUM(TotalHarvestQuantity) as TotalQuantity,
                          MAX(TotalHarvestQuantity) as MaxQuantity,
                          MIN(TotalHarvestQuantity) as MinQuantity,
                          AVG(TotalHarvestQuantity) as AvgQuantity
                        FROM harvest_session 
                        WHERE FarmerID = ?";
$harvestSummaryStmt = $conn->prepare($harvestSummaryQuery);
$harvestSummaryStmt->bind_param("s", $farmerId);
$harvestSummaryStmt->execute();
$harvestSummary = $harvestSummaryStmt->get_result()->fetch_assoc();
$harvestSummaryStmt->close();

// Get recent harvests
$recentHarvestsQuery = "SELECT 
                          h.*,
                          DATE_FORMAT(STR_TO_DATE(CONCAT(h.Year, '-', h.Month, '-', h.Day), '%Y-%m-%d'), '%d %M %Y') as HarvestDate
                        FROM harvest_session h
                        WHERE h.FarmerID = ?
                        ORDER BY h.Year DESC, h.Month DESC, h.Day DESC
                        LIMIT 10";
$recentHarvestsStmt = $conn->prepare($recentHarvestsQuery);
$recentHarvestsStmt->bind_param("s", $farmerId);
$recentHarvestsStmt->execute();
$recentHarvestsResult = $recentHarvestsStmt->get_result();

$recentHarvests = [];
while ($harvestRow = $recentHarvestsResult->fetch_assoc()) {
    $recentHarvests[] = $harvestRow;
}
$recentHarvestsStmt->close();

// Get batch information for each harvest
$batchCountQuery = "SELECT 
                      h.HarvestID,
                      COUNT(b.BatchID) as BatchCount,
                      SUM(b.Quantity) as BatchQuantity
                    FROM harvest_session h
                    LEFT JOIN batch b ON h.HarvestID = b.HarvestID
                    WHERE h.FarmerID = ?
                    GROUP BY h.HarvestID";
$batchCountStmt = $conn->prepare($batchCountQuery);
$batchCountStmt->bind_param("s", $farmerId);
$batchCountStmt->execute();
$batchCountResult = $batchCountStmt->get_result();

$batchCounts = [];
while ($batchRow = $batchCountResult->fetch_assoc()) {
    $batchCounts[$batchRow['HarvestID']] = [
        'count' => $batchRow['BatchCount'],
        'quantity' => $batchRow['BatchQuantity']
    ];
}
$batchCountStmt->close();

// Get yearly harvest data for chart
$yearlyHarvestQuery = "SELECT 
                        Year,
                        SUM(TotalHarvestQuantity) as YearlyQuantity
                      FROM harvest_session
                      WHERE FarmerID = ?
                      GROUP BY Year
                      ORDER BY Year";
$yearlyHarvestStmt = $conn->prepare($yearlyHarvestQuery);
$yearlyHarvestStmt->bind_param("s", $farmerId);
$yearlyHarvestStmt->execute();
$yearlyHarvestResult = $yearlyHarvestStmt->get_result();

$yearlyHarvest = [];
while ($yearlyRow = $yearlyHarvestResult->fetch_assoc()) {
    $yearlyHarvest[] = $yearlyRow;
}
$yearlyHarvestStmt->close();

// Get monthly harvest data for current year
$currentYear = date('Y');
$monthlyHarvestQuery = "SELECT 
                          Month,
                          SUM(TotalHarvestQuantity) as MonthlyQuantity
                        FROM harvest_session
                        WHERE FarmerID = ? AND Year = ?
                        GROUP BY Month
                        ORDER BY Month";
$monthlyHarvestStmt = $conn->prepare($monthlyHarvestQuery);
$monthlyHarvestStmt->bind_param("si", $farmerId, $currentYear);
$monthlyHarvestStmt->execute();
$monthlyHarvestResult = $monthlyHarvestStmt->get_result();

$monthlyHarvest = [];
for ($i = 1; $i <= 12; $i++) {
    $monthlyHarvest[$i] = 0;
}

while ($monthlyRow = $monthlyHarvestResult->fetch_assoc()) {
    $monthlyHarvest[$monthlyRow['Month']] = floatval($monthlyRow['MonthlyQuantity']);
}
$monthlyHarvestStmt->close();

// Get crop types from batches related to this farmer's harvests
$cropTypesQuery = "SELECT 
                     c.CropName,
                     c.CropType,
                     c.CropVariety,
                     COUNT(c.BatchID) as BatchCount,
                     SUM(b.Quantity) as TotalQuantity
                   FROM harvest_session h
                   JOIN batch b ON h.HarvestID = b.HarvestID
                   JOIN crop c ON b.BatchID = c.BatchID
                   WHERE h.FarmerID = ?
                   GROUP BY c.CropName, c.CropType, c.CropVariety
                   ORDER BY TotalQuantity DESC";
$cropTypesStmt = $conn->prepare($cropTypesQuery);
$cropTypesStmt->bind_param("s", $farmerId);
$cropTypesStmt->execute();
$cropTypesResult = $cropTypesStmt->get_result();

$cropTypes = [];
while ($cropRow = $cropTypesResult->fetch_assoc()) {
    $cropTypes[] = $cropRow;
}
$cropTypesStmt->close();

// Set page title
$pageTitle = "Farmer Details: " . $farmer['Name'];
include('includes/header.php');
?>

<main>
    <div class="breadcrumb">
        <a href="farmers.php">Farmers</a> &gt; <?php echo $farmer['Name']; ?>
    </div>

    <div class="content-header">
        <h1><?php echo $farmer['Name']; ?></h1>
        <div class="button-group">
            <a href="farmers.php?action=edit&id=<?php echo $farmerId; ?>" class="btn btn-primary">Edit Farmer</a>
            <a href="harvest_form.php?farmer_id=<?php echo $farmerId; ?>" class="btn btn-success">Add Harvest</a>
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

    <div class="page-content">
        <!-- Farmer Information Card -->
        <div class="card">
            <div class="card-header">
                <h2>Farmer Information</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <span class="info-label">Farmer ID:</span>
                            <span class="info-value"><?php echo $farmer['FarmerID']; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?php echo $farmer['Name']; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Address:</span>
                            <span class="info-value">
                                <?php echo $farmer['Street'] . ', ' . $farmer['City'] . ', ' . $farmer['ZipCode']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <span class="info-label">Contact Numbers:</span>
                            <span class="info-value">
                                <?php 
                                    if (empty($contactNumbers)) {
                                        echo "No contact numbers available";
                                    } else {
                                        echo implode(', ', $contactNumbers);
                                    }
                                ?>
                            </span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Total Harvest Sessions:</span>
                            <span class="info-value"><?php echo $harvestSummary['TotalHarvestSessions']; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Total Harvest Quantity:</span>
                            <span class="info-value">
                                <?php echo number_format($harvestSummary['TotalQuantity'] ?? 0, 2) . ' kg'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Harvest Summary Card -->
        <div class="card">
            <div class="card-header">
                <h2>Harvest Summary</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-card-title">Average Harvest</div>
                            <div class="stat-card-value">
                                <?php echo number_format($harvestSummary['AvgQuantity'] ?? 0, 2) . ' kg'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-card-title">Largest Harvest</div>
                            <div class="stat-card-value">
                                <?php echo number_format($harvestSummary['MaxQuantity'] ?? 0, 2) . ' kg'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-card-title">Smallest Harvest</div>
                            <div class="stat-card-value">
                                <?php echo number_format($harvestSummary['MinQuantity'] ?? 0, 2) . ' kg'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Harvest Chart -->
                <div class="chart-container">
                    <canvas id="monthlyHarvestChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Crop Types Card -->
        <div class="card">
            <div class="card-header">
                <h2>Crop Types</h2>
            </div>
            <div class="card-body">
                <?php if (empty($cropTypes)): ?>
                    <p>No crop data available for this farmer.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Crop Name</th>
                                    <th>Crop Type</th>
                                    <th>Variety</th>
                                    <th>Batch Count</th>
                                    <th>Total Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cropTypes as $crop): ?>
                                    <tr>
                                        <td><?php echo $crop['CropName']; ?></td>
                                        <td><?php echo $crop['CropType']; ?></td>
                                        <td><?php echo $crop['CropVariety']; ?></td>
                                        <td><?php echo $crop['BatchCount']; ?></td>
                                        <td><?php echo number_format($crop['TotalQuantity'], 2) . ' kg'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Harvests Card -->
        <div class="card">
            <div class="card-header">
                <h2>Recent Harvests</h2>
            </div>
            <div class="card-body">
                <?php if (empty($recentHarvests)): ?>
                    <p>No harvest data available for this farmer.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Harvest ID</th>
                                    <th>Date</th>
                                    <th>Quantity</th>
                                    <th>Batch Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentHarvests as $harvest): ?>
                                    <tr>
                                        <td><?php echo $harvest['HarvestID']; ?></td>
                                        <td><?php echo $harvest['HarvestDate']; ?></td>
                                        <td><?php echo number_format($harvest['TotalHarvestQuantity'], 2) . ' kg'; ?></td>
                                        <td>
                                            <?php 
                                                echo isset($batchCounts[$harvest['HarvestID']]) ? 
                                                    $batchCounts[$harvest['HarvestID']]['count'] : 0; 
                                            ?>
                                        </td>
                                        <td>
                                            <a href="harvest_details.php?id=<?php echo $harvest['HarvestID']; ?>" 
                                               class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($recentHarvests) < $harvestSummary['TotalHarvestSessions']): ?>
                        <div class="text-center mt-3">
                            <a href="harvest_history.php?farmer_id=<?php echo $farmerId; ?>" class="btn btn-secondary">
                                View All Harvests
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Harvest Chart
    const monthlyData = <?php echo json_encode(array_values($monthlyHarvest)); ?>;
    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    const monthlyHarvestChart = new Chart(
        document.getElementById('monthlyHarvestChart'),
        {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Monthly Harvest (kg) - <?php echo $currentYear; ?>',
                    data: monthlyData,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Harvest Quantity (kg)'
                        }
                    }
                }
            }
        }
    );
});
</script>

<style>
.breadcrumb {
    margin-bottom: 20px;
    background-color: #f8f9fa;
    padding: 10px 15px;
    border-radius: 4px;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.button-group {
    display: flex;
    gap: 10px;
}

.card {
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #ddd;
}

.card-header h2 {
    margin: 0;
    font-size: 1.25rem;
}

.card-body {
    padding: 20px;
}

.info-group {
    margin-bottom: 10px;
}

.info-label {
    font-weight: bold;
    margin-right: 10px;
}

.stat-card {
    background-color: #f8f9fa;
    border-radius: 4px;
    padding: 15px;
    text-align: center;
    margin-bottom: 20px;
}

.stat-card-title {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
}

.stat-card-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.chart-container {
    height: 300px;
    margin-top: 20px;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -15px;
    margin-left: -15px;
}

.col-md-4 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
    padding-right: 15px;
    padding-left: 15px;
}

.col-md-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding-right: 15px;
    padding-left: 15px;
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 0.75rem;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}

.table thead th {
    vertical-align: bottom;
    border-bottom: 2px solid #dee2e6;
}

.table-responsive {
    display: block;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.mt-3 {
    margin-top: 1rem;
}

.text-center {
    text-align: center;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

@media (max-width: 768px) {
    .col-md-4, .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .button-group {
        margin-top: 10px;
    }
}
</style>

<?php 
//include('includes/footer.php'); 
?>
<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Get harvest ID from URL
if (!isset($_GET['id'])) {
    // Redirect if no ID provided
    header('Location: harvests.php');
    exit();
}

$harvestId = sanitizeInput($_GET['id']);

// Get harvest details
$query = "SELECT h.*, 
            f.Name as FarmerName, f.FarmerID,
            DATE_FORMAT(STR_TO_DATE(CONCAT(h.Year, '-', h.Month, '-', h.Day), '%Y-%m-%d'), '%d %M %Y') as HarvestDate
          FROM harvest_session h
          JOIN farmer f ON h.FarmerID = f.FarmerID
          WHERE h.HarvestID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $harvestId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    // Harvest not found
    $_SESSION['error_message'] = "Harvest session not found.";
    header('Location: harvests.php');
    exit();
}

$harvest = $result->fetch_assoc();
$stmt->close();

// Get batches related to this harvest
$batchQuery = "SELECT b.*, c.CropName, c.CropType, c.CropVariety, c.GrowingSeason,
                ws.EntryDate, ws.ExpiryDate, ws.WarehouseID,
                w.City as WarehouseCity
              FROM batch b
              LEFT JOIN crop c ON b.BatchID = c.BatchID 
              LEFT JOIN warehouse_stock ws ON b.WarehouseStockID = ws.WarehouseStockID
              LEFT JOIN warehouse w ON ws.WarehouseID = w.WarehouseID
              WHERE b.HarvestID = ?
              ORDER BY b.BatchProductionDate DESC";
$batchStmt = $conn->prepare($batchQuery);
$batchStmt->bind_param("s", $harvestId);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();

$batches = [];
while ($batchRow = $batchResult->fetch_assoc()) {
    $batches[] = $batchRow;
}
$batchStmt->close();

// Get batch quantity summary
$totalBatchQuantity = 0;
foreach ($batches as $batch) {
    $totalBatchQuantity += $batch['Quantity'];
}

// Calculate remaining unprocessed quantity
$unprocessedQuantity = $harvest['TotalHarvestQuantity'] - $totalBatchQuantity;

// Get shipment data related to this harvest's batches
$shipmentQuery = "SELECT s.ShipmentID, s.Day, s.Month, s.Year, 
                  DATE_FORMAT(STR_TO_DATE(CONCAT(s.Year, '-', s.Month, '-', s.Day), '%Y-%m-%d'), '%d %M %Y') as ShipmentDate,
                  s.DestinationLocation, s.TotalWeight, m.MarketName,
                  b.BatchID
                FROM batch b
                JOIN batch_shipment bs ON b.BatchID = bs.BatchID
                JOIN shipment s ON bs.ShipmentID = s.ShipmentID
                JOIN market m ON s.MarketID = m.MarketID
                WHERE b.HarvestID = ?
                ORDER BY s.Year DESC, s.Month DESC, s.Day DESC";
$shipmentStmt = $conn->prepare($shipmentQuery);
$shipmentStmt->bind_param("s", $harvestId);
$shipmentStmt->execute();
$shipmentResult = $shipmentStmt->get_result();

$shipments = [];
while ($shipmentRow = $shipmentResult->fetch_assoc()) {
    if (!isset($shipments[$shipmentRow['ShipmentID']])) {
        $shipments[$shipmentRow['ShipmentID']] = [
            'ShipmentID' => $shipmentRow['ShipmentID'],
            'ShipmentDate' => $shipmentRow['ShipmentDate'],
            'DestinationLocation' => $shipmentRow['DestinationLocation'],
            'TotalWeight' => $shipmentRow['TotalWeight'],
            'MarketName' => $shipmentRow['MarketName'],
            'Batches' => []
        ];
    }
    $shipments[$shipmentRow['ShipmentID']]['Batches'][] = $shipmentRow['BatchID'];
}
$shipmentStmt->close();

// Get nutritional analysis data for batches
$analysisQuery = "SELECT na.*, 
                  DATE_FORMAT(STR_TO_DATE(CONCAT(na.Year, '-', na.Month, '-', na.Day), '%Y-%m-%d'), '%d %M %Y') as AnalysisDate,
                  b.BatchID
                FROM batch b
                LEFT JOIN nutritional_analysis na ON b.BatchID = na.BatchID
                WHERE b.HarvestID = ? AND na.AnalysisID IS NOT NULL";
$analysisStmt = $conn->prepare($analysisQuery);
$analysisStmt->bind_param("s", $harvestId);
$analysisStmt->execute();
$analysisResult = $analysisStmt->get_result();

$analyses = [];
while ($analysisRow = $analysisResult->fetch_assoc()) {
    $analyses[$analysisRow['BatchID']] = $analysisRow;
}
$analysisStmt->close();

// Set page title
$pageTitle = "Harvest Details";
include('includes/header.php');
?>

<main>
    <div class="breadcrumb">
        <a href="harvests.php">Harvests</a> &gt; 
        <a href="farmer_details.php?id=<?php echo $harvest['FarmerID']; ?>"><?php echo $harvest['FarmerName']; ?></a> &gt;
        Harvest #<?php echo $harvest['HarvestID']; ?>
    </div>

    <div class="content-header">
        <h1>Harvest Session Details</h1>
        <div class="button-group">
            <a href="batch_form.php?harvest_id=<?php echo $harvestId; ?>" class="btn btn-primary">Create Batch</a>
            <a href="harvest_edit.php?id=<?php echo $harvestId; ?>" class="btn btn-secondary">Edit Harvest</a>
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
        <!-- Harvest Information Card -->
        <div class="card">
            <div class="card-header">
                <h2>Harvest Information</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <span class="info-label">Harvest ID:</span>
                            <span class="info-value"><?php echo $harvest['HarvestID']; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Harvest Date:</span>
                            <span class="info-value"><?php echo $harvest['HarvestDate']; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Farmer:</span>
                            <span class="info-value">
                                <a href="farmer_details.php?id=<?php echo $harvest['FarmerID']; ?>">
                                    <?php echo $harvest['FarmerName']; ?> (<?php echo $harvest['FarmerID']; ?>)
                                </a>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <span class="info-label">Total Harvest Quantity:</span>
                            <span class="info-value"><?php echo number_format($harvest['TotalHarvestQuantity'], 2) . ' kg'; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Processed in Batches:</span>
                            <span class="info-value"><?php echo number_format($totalBatchQuantity, 2) . ' kg'; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Unprocessed Quantity:</span>
                            <span class="info-value <?php echo $unprocessedQuantity > 0 ? 'text-warning' : ''; ?>">
                                <?php echo number_format($unprocessedQuantity, 2) . ' kg'; ?>
                                <?php if ($unprocessedQuantity <= 0): ?>
                                    <span class="badge badge-success">Fully Processed</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Processing Progress Bar -->
                <?php if ($harvest['TotalHarvestQuantity'] > 0): ?>
                    <?php $processedPercentage = min(100, ($totalBatchQuantity / $harvest['TotalHarvestQuantity']) * 100); ?>
                    <div class="progress-container">
                        <div class="progress-label">Processing Progress</div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $processedPercentage; ?>%;">
                                <?php echo round($processedPercentage); ?>%
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Batches Card -->
        <div class="card">
            <div class="card-header">
                <h2>Batches</h2>
            </div>
            <div class="card-body">
                <?php if (empty($batches)): ?>
                    <div class="alert alert-info">
                        No batches have been created from this harvest yet.
                        <a href="batch_form.php?harvest_id=<?php echo $harvestId; ?>" class="btn btn-sm btn-primary ml-2">
                            Create Batch
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Batch ID</th>
                                    <th>Production Date</th>
                                    <th>Crop</th>
                                    <th>Quantity</th>
                                    <th>Warehouse</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batches as $batch): ?>
                                    <tr>
                                        <td><?php echo $batch['BatchID']; ?></td>
                                        <td><?php echo date('d M Y', strtotime($batch['BatchProductionDate'])); ?></td>
                                        <td>
                                            <?php if (!empty($batch['CropName'])): ?>
                                                <?php echo $batch['CropName']; ?> 
                                                (<?php echo $batch['CropType']; ?>, 
                                                Variety: <?php echo $batch['CropVariety']; ?>)
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($batch['Quantity'], 2) . ' kg'; ?></td>
                                        <td>
                                            <?php if (!empty($batch['WarehouseID'])): ?>
                                                <?php echo $batch['WarehouseID']; ?> 
                                                (<?php echo $batch['WarehouseCity']; ?>)
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($batch['ExpiryDate'])): ?>
                                                <?php 
                                                    $expiryDate = new DateTime($batch['ExpiryDate']);
                                                    $today = new DateTime();
                                                    $diff = $today->diff($expiryDate);
                                                    $daysRemaining = $expiryDate > $today ? $diff->days : -$diff->days;
                                                    
                                                    $expiryClass = '';
                                                    if ($daysRemaining < 0) {
                                                        $expiryClass = 'text-danger';
                                                    } elseif ($daysRemaining < 30) {
                                                        $expiryClass = 'text-warning';
                                                    }
                                                ?>
                                                <span class="<?php echo $expiryClass; ?>">
                                                    <?php echo date('d M Y', strtotime($batch['ExpiryDate'])); ?>
                                                    <?php if ($daysRemaining < 0): ?>
                                                        <span class="badge badge-danger">Expired</span>
                                                    <?php elseif ($daysRemaining < 30): ?>
                                                        <span class="badge badge-warning">
                                                            <?php echo $daysRemaining; ?> days left
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                // Check if batch is in any shipment
                                                $inShipment = false;
                                                foreach ($shipments as $shipment) {
                                                    if (in_array($batch['BatchID'], $shipment['Batches'])) {
                                                        $inShipment = true;
                                                        break;
                                                    }
                                                }
                                                
                                                if ($inShipment) {
                                                    echo '<span class="badge badge-success">Shipped</span>';
                                                } else {
                                                    echo '<span class="badge badge-info">In Stock</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="batch_details.php?id=<?php echo $batch['BatchID']; ?>" 
                                               class="btn btn-sm btn-primary">View</a>
                                            
                                            <?php if (!$inShipment): ?>
                                                <a href="batch_edit.php?id=<?php echo $batch['BatchID']; ?>" 
                                                   class="btn btn-sm btn-secondary">Edit</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Shipments Card (if any) -->
        <?php if (!empty($shipments)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Related Shipments</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Shipment ID</th>
                                    <th>Date</th>
                                    <th>Market</th>
                                    <th>Destination</th>
                                    <th>Total Weight</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shipments as $shipment): ?>
                                    <tr>
                                        <td><?php echo $shipment['ShipmentID']; ?></td>
                                        <td><?php echo $shipment['ShipmentDate']; ?></td>
                                        <td><?php echo $shipment['MarketName']; ?></td>
                                        <td><?php echo $shipment['DestinationLocation']; ?></td>
                                        <td><?php echo number_format($shipment['TotalWeight'], 2) . ' kg'; ?></td>
                                        <td>
                                            <a href="shipment_details.php?id=<?php echo $shipment['ShipmentID']; ?>" 
                                               class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Nutritional Analysis Card (if any) -->
        <?php if (!empty($analyses)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Nutritional Analysis</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Batch ID</th>
                                    <th>Analysis Date</th>
                                    <th>Calories</th>
                                    <th>Protein</th>
                                    <th>Vitamins</th>
                                    <th>Minerals</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analyses as $batchID => $analysis): ?>
                                    <tr>
                                        <td><?php echo $batchID; ?></td>
                                        <td><?php echo $analysis['AnalysisDate']; ?></td>
                                        <td><?php echo $analysis['Calories']; ?> kcal</td>
                                        <td><?php echo $analysis['Protein']; ?> g</td>
                                        <td><?php echo $analysis['Vitamins']; ?></td>
                                        <td><?php echo $analysis['Minerals']; ?></td>
                                        <td>
                                            <a href="analysis_details.php?id=<?php echo $analysis['AnalysisID']; ?>" 
                                               class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.breadcrumb {
    margin-bottom: a20px;
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

.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -15px;
    margin-left: -15px;
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

.progress-container {
    margin-top: 20px;
}

.progress-label {
    margin-bottom: 5px;
    font-weight: bold;
}

.progress {
    height: 20px;
    overflow: hidden;
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.progress-bar {
    display: flex;
    flex-direction: column;
    justify-content: center;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    background-color: #007bff;
    transition: width 0.6s ease;
    height: 100%;
}

.badge {
    display: inline-block;
    padding: 0.25em 0.4em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
    margin-left: 5px;
}

.badge-success {
    color: #fff;
    background-color: #28a745;
}

.badge-warning {
    color: #212529;
    background-color: #ffc107;
}

.badge-danger {
    color: #fff;
    background-color: #dc3545;
}

.badge-info {
    color: #fff;
    background-color: #17a2b8;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

.alert {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

.ml-2 {
    margin-left: 0.5rem;
}

.text-warning {
    color: #ffc107;
}

.text-danger {
    color: #dc3545;
}

.text-muted {
    color: #6c757d;
}

@media (max-width: 768px) {
    .col-md-6 {
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
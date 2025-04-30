<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Check if batch ID is provided
if (!isset($_GET['id'])) {
    showError("Batch ID is required");
    header("Location: batches.php");
    exit();
}

$batchId = sanitizeInput($_GET['id']);

// Get batch details
$query = "SELECT b.*, c.CropName, c.CropType, c.CropVariety, c.GrowingSeason,
          ws.EntryDate, ws.ExpiryDate, ws.WarehouseID,
          h.HarvestID, h.Day, h.Month, h.Year, h.TotalHarvestQuantity,
          f.FarmerID, f.Name as FarmerName, f.Street, f.City, f.ZipCode,
          w.City as WarehouseCity, w.Street as WarehouseStreet, w.ZipCode as WarehouseZipCode,
          w.StorageType
          FROM batch b
          JOIN crop c ON b.BatchID = c.BatchID
          JOIN warehouse_stock ws ON b.BatchID = ws.BatchID
          LEFT JOIN harvest_session h ON b.HarvestID = h.HarvestID
          LEFT JOIN farmer f ON h.FarmerID = f.FarmerID
          JOIN warehouse w ON ws.WarehouseID = w.WarehouseID
          WHERE b.BatchID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $batchId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    showError("Batch not found");
    header("Location: batches.php");
    exit();
}

$batch = $result->fetch_assoc();
$stmt->close();

// Get nutritional analysis if available
$analysisQuery = "SELECT * FROM nutritional_analysis WHERE BatchID = ? ORDER BY Year DESC, Month DESC, Day DESC LIMIT 1";
$analysisStmt = $conn->prepare($analysisQuery);
$analysisStmt->bind_param("s", $batchId);
$analysisStmt->execute();
$analysisResult = $analysisStmt->get_result();
$analysis = null;

if ($analysisResult->num_rows > 0) {
    $analysis = $analysisResult->fetch_assoc();
}
$analysisStmt->close();

// Get associated purchases
$purchaseQuery = "SELECT p.*, o.OrderID, o.OrderDate, c.Name as CustomerName
                 FROM batch_purchase bp
                 JOIN purchase p ON bp.PurchaseID = p.PurchaseID
                 JOIN order_table o ON p.OrderID = o.OrderID
                 LEFT JOIN customer c ON o.CustomerID = c.CustomerID
                 WHERE bp.BatchID = ?";
$purchaseStmt = $conn->prepare($purchaseQuery);
$purchaseStmt->bind_param("s", $batchId);
$purchaseStmt->execute();
$purchaseResult = $purchaseStmt->get_result();

$purchases = [];
while ($row = $purchaseResult->fetch_assoc()) {
    $purchases[] = $row;
}
$purchaseStmt->close();

// Get associated shipments
$shipmentQuery = "SELECT s.*, m.MarketName
                FROM batch_shipment bs
                JOIN shipment s ON bs.ShipmentID = s.ShipmentID
                LEFT JOIN market m ON s.MarketID = m.MarketID
                WHERE bs.BatchID = ?";
$shipmentStmt = $conn->prepare($shipmentQuery);
$shipmentStmt->bind_param("s", $batchId);
$shipmentStmt->execute();
$shipmentResult = $shipmentStmt->get_result();

$shipments = [];
while ($row = $shipmentResult->fetch_assoc()) {
    $shipments[] = $row;
}
$shipmentStmt->close();

// Set page title
$pageTitle = "Batch Details";
include('includes/header.php');

// Storage type labels
$storageTypes = [
    1 => 'Dry Storage',
    2 => 'Cold Storage',
    3 => 'Freezer',
    4 => 'Climate Controlled'
];
?>

<main>
    <h1>Batch Details</h1>
    
    <div class="date">
        <input type="date" value="<?php echo $batch['BatchProductionDate']; ?>" disabled>
    </div>
    
    <div class="details-container">
        <div class="details-header">
            <h2>Batch #<?php echo $batch['BatchID']; ?></h2>
            <div class="details-actions">
                <a href="batches.php?action=edit&id=<?php echo $batch['BatchID']; ?>" class="btn btn-primary">Edit Batch</a>
                <a href="batches.php" class="btn btn-primary">Back to Batches</a>
            </div>
        </div>
        
        <div class="details-section">
            <div class="details-row">
                <div class="details-col">
                    <h3>Batch Information</h3>
                    <p><strong>Batch ID:</strong> <?php echo $batch['BatchID']; ?></p>
                    <p><strong>Production Date:</strong> <?php echo date('F d, Y', strtotime($batch['BatchProductionDate'])); ?></p>
                    <p><strong>Quantity:</strong> <?php echo number_format($batch['Quantity'], 2) . ' kg'; ?></p>
                    <p><strong>Entry Date:</strong> <?php echo date('F d, Y', strtotime($batch['EntryDate'])); ?></p>
                    <p>
                        <strong>Expiry Date:</strong> 
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
                            
                            echo '<span class="' . $class . '">' . date('F d, Y', $expiryDate) . '</span>';
                            if ($daysLeft > 0) {
                                echo ' <small>(' . $daysLeft . ' days left)</small>';
                            } else {
                                echo ' <small>(expired)</small>';
                            }
                        ?>
                    </p>
                </div>
                
                <div class="details-col">
                    <h3>Crop Information</h3>
                    <p><strong>Crop Name:</strong> <?php echo $batch['CropName']; ?></p>
                    <p><strong>Crop Type:</strong> <?php echo $batch['CropType']; ?></p>
                    <p><strong>Variety:</strong> <?php echo $batch['CropVariety']; ?></p>
                    <p><strong>Growing Season:</strong> <?php echo $batch['GrowingSeason']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="details-section">
            <div class="details-row">
                <div class="details-col">
                    <h3>Warehouse Information</h3>
                    <p><strong>Warehouse ID:</strong> <?php echo $batch['WarehouseID']; ?></p>
                    <p><strong>Location:</strong> <?php echo $batch['WarehouseStreet'] . ', ' . $batch['WarehouseCity'] . ', ' . $batch['WarehouseZipCode']; ?></p>
                    <p><strong>Storage Type:</strong> <?php echo isset($storageTypes[$batch['StorageType']]) ? $storageTypes[$batch['StorageType']] : 'Unknown'; ?></p>
                </div>
                
                <div class="details-col">
                    <h3>Harvest Information</h3>
                    <?php if ($batch['HarvestID']): ?>
                        <p><strong>Harvest ID:</strong> <?php echo $batch['HarvestID']; ?></p>
                        <p><strong>Harvest Date:</strong> <?php echo $batch['Day'] . '/' . $batch['Month'] . '/' . $batch['Year']; ?></p>
                        <p><strong>Total Harvest:</strong> <?php echo number_format($batch['TotalHarvestQuantity'], 2) . ' kg'; ?></p>
                        <p><strong>Farmer:</strong> <?php echo $batch['FarmerName']; ?></p>
                        <p><strong>Farmer Location:</strong> <?php echo $batch['City'] . ', ' . $batch['ZipCode']; ?></p>
                    <?php else: ?>
                        <p>No harvest information available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($analysis): ?>
        <div class="details-section">
            <h3>Nutritional Analysis</h3>
            <div class="details-row">
                <div class="details-col">
                    <p><strong>Analysis ID:</strong> <?php echo $analysis['AnalysisID']; ?></p>
                    <p><strong>Analysis Date:</strong> <?php echo $analysis['Day'] . '/' . $analysis['Month'] . '/' . $analysis['Year']; ?></p>
                    <p><strong>Calories:</strong> <?php echo $analysis['Calories']; ?> kcal</p>
                    <p><strong>Protein:</strong> <?php echo $analysis['Protein']; ?> g</p>
                </div>
                <div class="details-col">
                    <p><strong>Vitamins:</strong> <?php echo $analysis['Vitamins']; ?></p>
                    <p><strong>Minerals:</strong> <?php echo $analysis['Minerals']; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Purchases -->
        <div class="details-section">
            <h3>Associated Purchases</h3>
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Purchase ID</th>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Purchase Date</th>
                        <th>Quantity</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No purchases found for this batch.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?php echo $purchase['PurchaseID']; ?></td>
                                <td><a href="order_details.php?id=<?php echo $purchase['OrderID']; ?>"><?php echo $purchase['OrderID']; ?></a></td>
                                <td><?php echo $purchase['CustomerName'] ?? 'N/A'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($purchase['PurchaseDate'])); ?></td>
                                <td><?php echo number_format($purchase['Quantity'], 2) . ' kg'; ?></td>
                                <td><?php echo formatCurrency($purchase['TotalPrice']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Shipments -->
        <div class="details-section">
            <h3>Associated Shipments</h3>
            <table class="details-table">
                <thead>
                    <tr>
                        <th>Shipment ID</th>
                        <th>Date</th>
                        <th>Destination</th>
                        <th>Market</th>
                        <th>Total Weight</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shipments)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No shipments found for this batch.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shipments as $shipment): ?>
                            <tr>
                                <td><a href="shipment_details.php?id=<?php echo $shipment['ShipmentID']; ?>"><?php echo $shipment['ShipmentID']; ?></a></td>
                                <td><?php echo $shipment['Day'] . '/' . $shipment['Month'] . '/' . $shipment['Year']; ?></td>
                                <td><?php echo $shipment['DestinationLocation']; ?></td>
                                <td><?php echo $shipment['MarketName'] ?? 'N/A'; ?></td>
                                <td><?php echo number_format($shipment['TotalWeight'], 2) . ' kg'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Batch History Button -->
        <div class="details-actions center">
            <a href="add_analysis.php?id=<?php echo $batch['BatchID']; ?>" class="btn btn-primary">
                <span class="material-symbols-sharp">analytics</span> Add Nutritional Analysis
            </a>
            <button onclick="printDetails()" class="btn btn-primary">
                <span class="material-symbols-sharp">print</span> Print Details
            </button>
        </div>
    </div>
</main>

<style>
.details-container {
    background: var(--color-white);
    padding: var(--card-padding);
    border-radius: var(--card-border-radius);
    margin-top: 1.5rem;
    box-shadow: var(--box-shadow);
}

.details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--color-info-light);
    padding-bottom: 1rem;
}

.details-actions {
    display: flex;
    gap: 1rem;
}

.details-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.details-section {
    margin-bottom: 2rem;
}

.details-section h3 {
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.details-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.details-table th, .details-table td {
    padding: 0.8rem;
    text-align: left;
    border-bottom: 1px solid var(--color-info-light);
}

.details-table th {
    background: var(--color-light);
    font-weight: 600;
}

.center {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
}

.expired {
    color: #ff0000;
    font-weight: bold;
}

.expiring-soon {
    color: #ff9900;
    font-weight: bold;
}

@media print {
    header, aside, .top, .right, .date, .details-actions, footer {
        display: none !important;
    }
    
    main {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .details-container {
        box-shadow: none !important;
        padding: 0 !important;
    }
    
    body {
        background: white !important;
    }
    
    .container {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
}

@media screen and (max-width: 768px) {
    .details-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}
</style>

<script>
function printDetails() {
    window.print();
}
</script>

<?php
// Include footer
include('includes/footer.php');
?>
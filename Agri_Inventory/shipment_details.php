<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Check if shipment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    showError("Shipment ID is required");
    header("Location: shipments.php");
    exit();
}

$shipmentId = sanitizeInput($_GET['id']);

// Get shipment details
$query = "SELECT s.*, m.MarketName 
          FROM shipment s
          LEFT JOIN market m ON s.MarketID = m.MarketID
          WHERE s.ShipmentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $shipmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    showError("Shipment not found");
    header("Location: shipments.php");
    exit();
}

$shipment = $result->fetch_assoc();
$stmt->close();

// Get associated batches
$batchQuery = "SELECT b.BatchID, b.Quantity, c.CropName, c.CropVariety, c.CropType
               FROM batch_shipment bs
               JOIN batch b ON bs.BatchID = b.BatchID
               JOIN crop c ON b.BatchID = c.BatchID
               WHERE bs.ShipmentID = ?";
$batchStmt = $conn->prepare($batchQuery);
$batchStmt->bind_param("s", $shipmentId);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();
$batches = [];

if ($batchResult && $batchResult->num_rows > 0) {
    while ($row = $batchResult->fetch_assoc()) {
        $batches[] = $row;
    }
}
$batchStmt->close();

// Get transport vehicles
$transportQuery = "SELECT st.*, tv.VehicleType, tv.LicensePlateNumber, tv.Capacity
                  FROM shipment_transport st
                  JOIN transport_vehicle tv ON st.VehicleID = tv.VehicleID
                  WHERE st.ShipmentID = ?";
$transportStmt = $conn->prepare($transportQuery);
$transportStmt->bind_param("s", $shipmentId);
$transportStmt->execute();
$transportResult = $transportStmt->get_result();
$transports = [];

if ($transportResult && $transportResult->num_rows > 0) {
    while ($row = $transportResult->fetch_assoc()) {
        $transports[] = $row;
    }
}
$transportStmt->close();

// Set page title
$pageTitle = "Shipment Details: " . $shipmentId;
include('includes/header.php');
?>

<main>
    <div class="breadcrumb">
        <a href="shipments.php">Shipments</a> &gt; <?php echo $shipmentId; ?>
    </div>

    <div class="detail-header">
        <h1>Shipment Details: <?php echo $shipmentId; ?></h1>
        <div class="detail-actions">
            <a href="shipments.php?action=edit&id=<?php echo $shipmentId; ?>" class="btn btn-primary">Edit Shipment</a>
            <a href="#" class="btn btn-secondary print-btn" onclick="window.print()">Print Details</a>
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

    <div class="detail-container">
        <div class="detail-section">
            <h2>Shipment Information</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Shipment ID</div>
                    <div class="detail-value"><?php echo $shipment['ShipmentID']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?php echo $shipment['Day'] . '/' . $shipment['Month'] . '/' . $shipment['Year']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Destination</div>
                    <div class="detail-value"><?php echo $shipment['DestinationLocation']; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Market</div>
                    <div class="detail-value"><?php echo $shipment['MarketName'] ?? 'N/A'; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Total Weight</div>
                    <div class="detail-value"><?php echo number_format($shipment['TotalWeight'], 2) . ' kg'; ?></div>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h2>Shipped Batches</h2>
            <?php if (empty($batches)): ?>
                <p class="text-muted">No batches associated with this shipment.</p>
            <?php else: ?>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Crop Name</th>
                            <th>Variety</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td><?php echo $batch['BatchID']; ?></td>
                                <td><?php echo $batch['CropName']; ?></td>
                                <td><?php echo $batch['CropVariety']; ?></td>
                                <td><?php echo $batch['CropType']; ?></td>
                                <td><?php echo number_format($batch['Quantity'], 2) . ' kg'; ?></td>
                                <td>
                                    <a href="batch_details.php?id=<?php echo $batch['BatchID']; ?>" class="btn btn-primary btn-sm">View Batch</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="detail-section">
            <h2>Transport Vehicles</h2>
            <?php if (empty($transports)): ?>
                <p class="text-muted">No transport vehicles associated with this shipment.</p>
            <?php else: ?>
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Transport ID</th>
                            <th>Vehicle Type</th>
                            <th>License Plate</th>
                            <th>Vehicle Capacity</th>
                            <th>Load Weight</th>
                            <th>Load %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transports as $transport): ?>
                            <tr>
                                <td><?php echo $transport['ShipmentTransportID']; ?></td>
                                <td><?php echo $transport['VehicleType']; ?></td>
                                <td><?php echo $transport['LicensePlateNumber']; ?></td>
                                <td><?php echo number_format($transport['Capacity'], 2) . ' kg'; ?></td>
                                <td><?php echo number_format($transport['Weight'], 2) . ' kg'; ?></td>
                                <td>
                                    <?php 
                                        $percentage = ($transport['Weight'] / $transport['Capacity']) * 100;
                                        echo number_format($percentage, 1) . '%';
                                        
                                        // Visual indicator for load percentage
                                        $colorClass = 'normal';
                                        if ($percentage > 95) {
                                            $colorClass = 'overloaded';
                                        } elseif ($percentage > 80) {
                                            $colorClass = 'high';
                                        } elseif ($percentage < 50) {
                                            $colorClass = 'low';
                                        }
                                    ?>
                                    <div class="load-bar">
                                        <div class="load-indicator <?php echo $colorClass; ?>" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.breadcrumb {
    margin-bottom: 20px;
    padding: 10px 0;
    border-bottom: 1px solid var(--color-info-light);
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.detail-actions {
    display: flex;
    gap: 10px;
}

.detail-container {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.detail-section {
    background-color: var(--color-white);
    padding: 20px;
    border-radius: var(--border-radius-1);
    box-shadow: var(--box-shadow);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.detail-item {
    border-bottom: 1px solid var(--color-light);
    padding-bottom: 10px;
}

.detail-label {
    font-weight: bold;
    color: var(--color-dark-variant);
    margin-bottom: 5px;
}

.detail-value {
    font-size: 1.1rem;
}

.detail-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.detail-table th, .detail-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--color-light);
}

.detail-table th {
    background-color: var(--color-light);
    font-weight: bold;
}

.load-bar {
    height: 10px;
    background-color: var(--color-light);
    border-radius: 5px;
    overflow: hidden;
    margin-top: 5px;
}

.load-indicator {
    height: 100%;
    border-radius: 5px;
}

.load-indicator.low {
    background-color: var(--color-success);
}

.load-indicator.normal {
    background-color: var(--color-primary);
}

.load-indicator.high {
    background-color: var(--color-warning);
}

.load-indicator.overloaded {
    background-color: var(--color-danger);
}

@media print {
    .sidebar, header, .breadcrumb, .detail-actions, footer {
        display: none !important;
    }
    
    main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    
    body {
        background-color: white !important;
    }
    
    .detail-section {
        box-shadow: none !important;
        margin-bottom: 20px !important;
        page-break-inside: avoid;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Any additional JavaScript can go here
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Assign Vehicle to Shipment";

// Check if vehicle ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: vehicles.php");
    exit;
}

$vehicleID = $_GET['id'];

// Get vehicle details
$query = "SELECT * FROM transport_vehicle WHERE VehicleID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $vehicleID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: vehicles.php");
    exit;
}

$vehicle = $result->fetch_assoc();
$stmt->close();

// Process form submission for assigning vehicle to shipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_vehicle') {
    $shipmentID = $_POST['shipment_id'] ?? '';
    $weight = $_POST['weight'] ?? 0;
    
    // Generate a unique shipment transport ID
    $shipmentTransportID = generateUniqueID('STR');
    
    // Insert new assignment
    $query = "INSERT INTO shipment_transport (ShipmentTransportID, ShipmentID, VehicleID, Weight) 
              VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssd", $shipmentTransportID, $shipmentID, $vehicleID, $weight);
    
    if ($stmt->execute()) {
        $successMessage = "Vehicle assigned to shipment successfully.";
    } else {
        $errorMessage = "Error assigning vehicle: " . $stmt->error;
    }
    
    $stmt->close();
}

// Get all shipments that don't have this vehicle assigned
$query = "SELECT s.*, m.MarketName 
          FROM shipment s
          LEFT JOIN market m ON s.MarketID = m.MarketID
          WHERE s.ShipmentID NOT IN (
              SELECT ShipmentID FROM shipment_transport WHERE VehicleID = ?
          )
          ORDER BY s.Year DESC, s.Month DESC, s.Day DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $vehicleID);
$stmt->execute();
$result = $stmt->get_result();
$availableShipments = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['ShipmentDate'] = sprintf("%04d-%02d-%02d", $row['Year'], $row['Month'], $row['Day']);
        $availableShipments[] = $row;
    }
}
$stmt->close();

// Get current assignments for this vehicle
$query = "SELECT st.*, s.Day, s.Month, s.Year, s.DestinationLocation, m.MarketName 
          FROM shipment_transport st
          JOIN shipment s ON st.ShipmentID = s.ShipmentID
          LEFT JOIN market m ON s.MarketID = m.MarketID
          WHERE st.VehicleID = ?
          ORDER BY s.Year DESC, s.Month DESC, s.Day DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $vehicleID);
$stmt->execute();
$result = $stmt->get_result();
$currentAssignments = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['ShipmentDate'] = sprintf("%04d-%02d-%02d", $row['Year'], $row['Month'], $row['Day']);
        $currentAssignments[] = $row;
    }
}
$stmt->close();

// Include header
include('includes/header.php');

// Helper function to generate a unique ID
function generateUniqueID($prefix, $length = 10) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = $prefix;
    for ($i = 0; $i < $length - strlen($prefix); $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $id;
}
?>

<main>
    <h1>Assign Vehicle to Shipment</h1>
    
    <div class="vehicle-details">
        <h2>Vehicle Information</h2>
        <div class="info-card">
            <div class="info-item">
                <span class="label">Vehicle ID:</span>
                <span class="value"><?php echo $vehicle['VehicleID']; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Type:</span>
                <span class="value"><?php echo $vehicle['VehicleType']; ?></span>
            </div>
            <div class="info-item">
                <span class="label">License Plate:</span>
                <span class="value"><?php echo $vehicle['LicensePlateNumber']; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Capacity:</span>
                <span class="value"><?php echo number_format($vehicle['Capacity'], 2); ?> metric tons</span>
            </div>
        </div>
    </div>
    
    <?php if (isset($successMessage)): ?>
        <div class="success-message"><?php echo $successMessage; ?></div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="error-message"><?php echo $errorMessage; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($availableShipments)): ?>
    <div class="add-form">
        <h2>Assign to Shipment</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="assign_vehicle">
            
            <div class="form-group">
                <label for="shipment_id">Select Shipment</label>
                <select name="shipment_id" id="shipment_id" required>
                    <option value="">Select Shipment</option>
                    <?php foreach ($availableShipments as $shipment): ?>
                        <option value="<?php echo $shipment['ShipmentID']; ?>">
                            <?php echo $shipment['ShipmentID']; ?> - 
                            <?php echo date('M d, Y', strtotime($shipment['ShipmentDate'])); ?> - 
                            <?php echo $shipment['DestinationLocation']; ?> 
                            (<?php echo $shipment['MarketName']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="weight">Cargo Weight (metric tons)</label>
                <input type="number" name="weight" id="weight" step="0.01" min="0.1" 
                       max="<?php echo $vehicle['Capacity']; ?>" required
                       placeholder="Enter cargo weight (max: <?php echo $vehicle['Capacity']; ?>)">
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="btn">Assign Vehicle</button>
                <a href="vehicles.php" class="btn btn-secondary">Back to Vehicles</a>
            </div>
        </form>
    </div>
    <?php else: ?>
        <div class="no-shipments-message">
            <p>There are no available shipments to assign this vehicle to.</p>
            <a href="shipments.php" class="btn">Create New Shipment</a>
            <a href="vehicles.php" class="btn btn-secondary">Back to Vehicles</a>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($currentAssignments)): ?>
    <div class="current-assignments">
        <h2>Current Assignments</h2>
        <table>
            <thead>
                <tr>
                    <th>Assignment ID</th>
                    <th>Shipment ID</th>
                    <th>Destination</th>
                    <th>Date</th>
                    <th>Market</th>
                    <th>Weight (tons)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($currentAssignments as $assignment): ?>
                    <tr>
                        <td><?php echo $assignment['ShipmentTransportID']; ?></td>
                        <td><?php echo $assignment['ShipmentID']; ?></td>
                        <td><?php echo $assignment['DestinationLocation']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($assignment['ShipmentDate'])); ?></td>
                        <td><?php echo $assignment['MarketName'] ?? 'N/A'; ?></td>
                        <td><?php echo number_format($assignment['Weight'], 2); ?></td>
                        <td>
                            <a href="javascript:void(0);" class="btn-delete" 
                               onclick="confirmRemove('<?php echo $assignment['ShipmentTransportID']; ?>')">
                                <span class="material-symbols-sharp">unlink</span> Remove
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<style>
    .vehicle-details {
        margin-bottom: 2rem;
    }
    
    .info-card {
        background: var(--color-white);
        padding: 1.5rem;
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-item .label {
        color: var(--color-dark-variant);
        font-size: 0.87rem;
        margin-bottom: 0.2rem;
    }
    
    .info-item .value {
        font-weight: 700;
        color: var(--color-dark);
    }
    
    .add-form, .current-assignments {
        background: var(--color-white);
        padding: 1.5rem;
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        margin-top: 2rem;
    }
    
    .add-form h2, .current-assignments h2 {
        margin-bottom: 1rem;
        color: var(--color-dark);
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-group input, .form-group select {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--color-light);
        border-radius: 0.4rem;
        background: var(--color-background);
    }
    
    .form-buttons {
        margin-top: 1.5rem;
        display: flex;
        gap: 0.5rem;
    }
    
    .success-message {
        background: var(--color-success);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.4rem;
        margin-bottom: 1rem;
    }
    
    .error-message {
        background: var(--color-danger);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.4rem;
        margin-bottom: 1rem;
    }
    
    .btn {
        background: var(--color-primary);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 0.4rem;
        cursor: pointer;
        transition: all 300ms ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn:hover {
        background: var(--color-primary-variant);
    }
    
    .btn-secondary {
        background: var(--color-dark-variant);
    }
    
    .btn-secondary:hover {
        background: var(--color-dark);
    }
    
    .btn-delete {
        color: var(--color-danger);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    
    .current-assignments table {
        width: 100%;
        margin-top: 1rem;
    }
    
    .no-shipments-message {
        background: var(--color-white);
        padding: 1.5rem;
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        margin-top: 2rem;
        text-align: center;
    }
    
    .no-shipments-message p {
        margin-bottom: 1rem;
    }
    
    .no-shipments-message .btn {
        margin: 0 0.5rem;
    }
</style>

<script>
    function confirmRemove(transportID) {
        if (confirm("Are you sure you want to remove this vehicle assignment?")) {
            window.location.href = "remove_vehicle_assignment.php?id=" + transportID + "&vehicle=<?php echo $vehicleID; ?>";
        }
    }
</script>

<?php 
//include('includes/footer.php'); 
?>
<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Transport Vehicles";

// Process form submission for adding a new vehicle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_vehicle') {
    $vehicleType = $_POST['vehicle_type'] ?? '';
    $licensePlate = $_POST['license_plate'] ?? '';
    $capacity = $_POST['capacity'] ?? 0;
    
    // Generate a unique vehicle ID
    $vehicleID = generateUniqueID('VEH');
    
    // Insert new vehicle
    $query = "INSERT INTO transport_vehicle (VehicleID, VehicleType, LicensePlateNumber, Capacity) 
              VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssd", $vehicleID, $vehicleType, $licensePlate, $capacity);
    
    if ($stmt->execute()) {
        $successMessage = "New vehicle added successfully.";
    } else {
        $errorMessage = "Error adding vehicle: " . $stmt->error;
    }
    
    $stmt->close();
}

// Delete a vehicle
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $vehicleID = $_GET['delete'];
    
    // First check if the vehicle is used in any shipment
    $query = "SELECT COUNT(*) as count FROM shipment_transport WHERE VehicleID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $vehicleID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $errorMessage = "Cannot delete vehicle. It is assigned to one or more shipments.";
    } else {
        // Delete the vehicle
        $query = "DELETE FROM transport_vehicle WHERE VehicleID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $vehicleID);
        
        if ($stmt->execute()) {
            $successMessage = "Vehicle deleted successfully.";
        } else {
            $errorMessage = "Error deleting vehicle: " . $stmt->error;
        }
    }
    $stmt->close();
}

// Get all vehicles
$query = "SELECT * FROM transport_vehicle ORDER BY VehicleType";
$result = $conn->query($query);
$vehicles = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
}

// Include header
include('includes/header.php');

// Helper function to generate a unique ID
//function generateUniqueID($prefix, $length = 10) {
//    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
//    $id = $prefix;
//    for ($i = 0; $i < $length - strlen($prefix); $i++) {
//        $id .= $characters[rand(0, strlen($characters) - 1)];
//    }
//    return $id;
//}
?>

<main>
    <h1>Transport Vehicles</h1>
    
    <div class="date">
        <input type="date" value="<?php echo date('Y-m-d'); ?>">
    </div>
    
    <?php if (isset($successMessage)): ?>
        <div class="success-message"><?php echo $successMessage; ?></div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="error-message"><?php echo $errorMessage; ?></div>
    <?php endif; ?>
    
    <div class="insights">
        <div class="sales">
            <span class="material-symbols-sharp">directions_car</span>
            <div class="middle">
                <div class="left">
                    <h3>Total Vehicles</h3>
                    <h1><?php echo count($vehicles); ?></h1>
                </div>
            </div>
            <small class="text-muted">Registered in the system</small>
        </div>
    </div>
    
    <div class="add-form">
        <h2>Add New Vehicle</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_vehicle">
            
            <div class="form-group">
                <label for="vehicle_type">Vehicle Type</label>
                <select name="vehicle_type" id="vehicle_type" required>
                    <option value="">Select Vehicle Type</option>
                    <option value="Truck">Truck</option>
                    <option value="Van">Van</option>
                    <option value="Refrigerated Truck">Refrigerated Truck</option>
                    <option value="Pickup">Pickup</option>
                    <option value="Trailer">Trailer</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="license_plate">License Plate Number</label>
                <input type="text" name="license_plate" id="license_plate" required placeholder="Enter license plate number">
            </div>
            
            <div class="form-group">
                <label for="capacity">Capacity (metric tons)</label>
                <input type="number" name="capacity" id="capacity" step="0.01" min="0.1" required placeholder="Enter vehicle capacity">
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="btn">Add Vehicle</button>
            </div>
        </form>
    </div>
    
    <div class="recent-orders vehicle-list">
        <h2>Available Vehicles</h2>
        <table>
            <thead>
                <tr>
                    <th>Vehicle ID</th>
                    <th>Type</th>
                    <th>License Plate</th>
                    <th>Capacity (metric tons)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehicles)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No vehicles found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td><?php echo $vehicle['VehicleID']; ?></td>
                            <td><?php echo $vehicle['VehicleType']; ?></td>
                            <td><?php echo $vehicle['LicensePlateNumber']; ?></td>
                            <td><?php echo number_format($vehicle['Capacity'], 2); ?></td>
                            <td>
                                <a href="javascript:void(0);" class="btn-delete" 
                                   onclick="confirmDelete('<?php echo $vehicle['VehicleID']; ?>')">
                                    <span class="material-symbols-sharp">delete</span>
                                </a>
                                <a href="assign_vehicle.php?id=<?php echo $vehicle['VehicleID']; ?>" class="btn-view">
                                    <span class="material-symbols-sharp">assignment</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<style>
    .vehicle-list {
        margin-top: 2rem;
    }
    
    .add-form {
        background: var(--color-white);
        padding: 1.5rem;
        border-radius: var(--card-border-radius);
        box-shadow: var(--box-shadow);
        margin-top: 2rem;
    }
    
    .add-form h2 {
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
    }
    
    .btn:hover {
        background: var(--color-primary-variant);
    }
    
    .btn-delete, .btn-view {
        color: var(--color-danger);
        cursor: pointer;
        margin-right: 0.5rem;
    }
    
    .btn-view {
        color: var(--color-primary);
    }
</style>

<script>
    function confirmDelete(vehicleID) {
        if (confirm("Are you sure you want to delete this vehicle?")) {
            window.location.href = "vehicles.php?delete=" + vehicleID;
        }
    }
</script>

<?php 
//include('includes/footer.php'); 
?>
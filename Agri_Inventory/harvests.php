<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new harvest
        if ($_POST['action'] == 'add') {
            $harvestId = generateID('HRV', 8);
            $day = intval($_POST['day']);
            $month = intval($_POST['month']);
            $year = intval($_POST['year']);
            $totalQuantity = floatval($_POST['totalQuantity']);
            $farmerId = sanitizeInput($_POST['farmerId']);
            
            $query = "INSERT INTO harvest_session (HarvestID, Day, Month, Year, TotalHarvestQuantity, FarmerID) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("siiids", $harvestId, $day, $month, $year, $totalQuantity, $farmerId);
            
            if ($stmt->execute()) {
                showSuccess("Harvest session added successfully!");
            } else {
                showError("Error adding harvest session: " . $stmt->error);
            }
            
            $stmt->close();
        }
        // Update harvest
        else if ($_POST['action'] == 'edit' && isset($_POST['harvestId'])) {
            $harvestId = sanitizeInput($_POST['harvestId']);
            $day = intval($_POST['day']);
            $month = intval($_POST['month']);
            $year = intval($_POST['year']);
            $totalQuantity = floatval($_POST['totalQuantity']);
            $farmerId = sanitizeInput($_POST['farmerId']);
            
            $query = "UPDATE harvest_session 
                      SET Day = ?, Month = ?, Year = ?, TotalHarvestQuantity = ?, FarmerID = ? 
                      WHERE HarvestID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiidss", $day, $month, $year, $totalQuantity, $farmerId, $harvestId);
            
            if ($stmt->execute()) {
                showSuccess("Harvest session updated successfully!");
            } else {
                showError("Error updating harvest session: " . $stmt->error);
            }
            
            $stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: harvests.php");
    exit();
}

// Delete harvest
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $harvestId = sanitizeInput($_GET['id']);
    
    // Check if harvest is used in any batches
    $checkQuery = "SELECT COUNT(*) as count FROM batch WHERE HarvestID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $harvestId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($row['count'] > 0) {
        showError("Cannot delete harvest session because it is associated with batches.");
    } else {
        $query = "DELETE FROM harvest_session WHERE HarvestID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $harvestId);
        
        if ($stmt->execute()) {
            showSuccess("Harvest session deleted successfully!");
        } else {
            showError("Error deleting harvest session: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    // Redirect
    header("Location: harvests.php");
    exit();
}

// Edit harvest - Get data for form
$editData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $harvestId = sanitizeInput($_GET['id']);
    
    $query = "SELECT * FROM harvest_session WHERE HarvestID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $harvestId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Get all harvests
$query = "SELECT h.*, f.Name as FarmerName, f.FarmerID,
          (SELECT COUNT(*) FROM batch WHERE HarvestID = h.HarvestID) as BatchCount
          FROM harvest_session h
          JOIN farmer f ON h.FarmerID = f.FarmerID
          ORDER BY h.Year DESC, h.Month DESC, h.Day DESC";
$result = $conn->query($query);
$harvests = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $harvests[] = $row;
    }
}

// Get all farmers for dropdown
$farmerQuery = "SELECT FarmerID, Name FROM farmer ORDER BY Name";
$farmerResult = $conn->query($farmerQuery);
$farmers = [];

if ($farmerResult && $farmerResult->num_rows > 0) {
    while ($row = $farmerResult->fetch_assoc()) {
        $farmers[] = $row;
    }
}

// Set page title
$pageTitle = "Harvest Management";
include('includes/header.php');
?>

<main>
    <h1>Harvest Management</h1>
    
    <div class="date">
        <input type="date" value="">
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
    
    <!-- Harvest Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Harvest Session' : 'Add New Harvest Session'; ?></h2>
        <form method="POST" action="" id="harvestForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="harvestId" value="<?php echo $editData['HarvestID']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="farmerId">Farmer</label>
                <select id="farmerId" name="farmerId" required>
                    <option value="">-- Select Farmer --</option>
                    <?php foreach ($farmers as $farmer): ?>
                        <option value="<?php echo $farmer['FarmerID']; ?>" 
                            <?php echo ($editData && $editData['FarmerID'] == $farmer['FarmerID']) ? 'selected' : ''; ?>>
                            <?php echo $farmer['Name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="day">Day</label>
                    <input type="number" id="day" name="day" required min="1" max="31" 
                           value="<?php echo $editData ? $editData['Day'] : date('d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="month">Month</label>
                    <input type="number" id="month" name="month" required min="1" max="12" 
                           value="<?php echo $editData ? $editData['Month'] : date('m'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="year">Year</label>
                    <input type="number" id="year" name="year" required min="2000" max="2100" 
                           value="<?php echo $editData ? $editData['Year'] : date('Y'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="totalQuantity">Total Harvest Quantity (kg)</label>
                <input type="number" id="totalQuantity" name="totalQuantity" step="0.01" min="0" required 
                       value="<?php echo $editData ? $editData['TotalHarvestQuantity'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $editData ? 'Update Harvest Session' : 'Add Harvest Session'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="harvests.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Harvests List -->
    <div class="data-table">
        <div class="table-header">
            <h2>All Harvest Sessions</h2>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search harvests...">
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Harvest ID</th>
                    <th>Farmer ID</th>
                    <th>Date</th>
                    <th>Quantity</th>
                    <th>Batch Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($harvests)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No harvest sessions found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($harvests as $harvest): ?>
                        <tr>
                            <td><?php echo $harvest['HarvestID']; ?></td>
                            <td><?php echo $harvest['FarmerID']; ?></td>
                            <td><?php echo $harvest['Day'] . '/' . $harvest['Month'] . '/' . $harvest['Year']; ?></td>
                            <td><?php echo number_format($harvest['TotalHarvestQuantity'], 2) . ' kg'; ?></td>
                            <td><?php echo $harvest['BatchCount']; ?></td>
                            <td class="action-buttons">
                                <a href="harvest_details.php?id=<?php echo $harvest['HarvestID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="harvests.php?action=edit&id=<?php echo $harvest['HarvestID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="harvests.php?action=delete&id=<?php echo $harvest['HarvestID']; ?>" 
                                   class="btn btn-danger btn-sm delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const harvestForm = document.getElementById('harvestForm');
    if (harvestForm) {
        harvestForm.addEventListener('submit', function(e) {
            const day = parseInt(document.getElementById('day').value);
            const month = parseInt(document.getElementById('month').value);
            const year = parseInt(document.getElementById('year').value);
            
            // Validate day based on month
            let maxDay = 31;
            if (month === 4 || month === 6 || month === 9 || month === 11) {
                maxDay = 30;
            } else if (month === 2) {
                maxDay = (year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0)) ? 29 : 28;
            }
            
            if (day > maxDay) {
                e.preventDefault();
                alert(`Invalid date: ${month}/${day}/${year} - ${month} has max ${maxDay} days`);
            }
        });
    }
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new farmer
        if ($_POST['action'] == 'add') {
            $farmerId = generateID('FRM', 8);
            $name = sanitizeInput($_POST['name']);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            $contactNumbers = isset($_POST['contactNumbers']) ? $_POST['contactNumbers'] : [];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert into farmer table
                $query = "INSERT INTO farmer (FarmerID, Name, Street, City, ZipCode) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $farmerId, $name, $street, $city, $zipCode);
                $stmt->execute();
                
                // Insert contact numbers
                if (!empty($contactNumbers)) {
                    $contactQuery = "INSERT INTO farmer_contact (FarmerID, ContactNumber) VALUES (?, ?)";
                    $contactStmt = $conn->prepare($contactQuery);
                    
                    foreach ($contactNumbers as $contactNumber) {
                        if (!empty($contactNumber)) {
                            $contactStmt->bind_param("ss", $farmerId, $contactNumber);
                            $contactStmt->execute();
                        }
                    }
                    
                    $contactStmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Farmer added successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error adding farmer: " . $e->getMessage());
            }
            
            $stmt->close();
        }
        // Update farmer
        else if ($_POST['action'] == 'edit' && isset($_POST['farmerId'])) {
            $farmerId = sanitizeInput($_POST['farmerId']);
            $name = sanitizeInput($_POST['name']);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            $contactNumbers = isset($_POST['contactNumbers']) ? $_POST['contactNumbers'] : [];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update farmer table
                $query = "UPDATE farmer SET Name = ?, Street = ?, City = ?, ZipCode = ? WHERE FarmerID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $name, $street, $city, $zipCode, $farmerId);
                $stmt->execute();
                
                // Delete existing contact numbers
                $deleteQuery = "DELETE FROM farmer_contact WHERE FarmerID = ?";
                $deleteStmt = $conn->prepare($deleteQuery);
                $deleteStmt->bind_param("s", $farmerId);
                $deleteStmt->execute();
                $deleteStmt->close();
                
                // Insert new contact numbers
                if (!empty($contactNumbers)) {
                    $contactQuery = "INSERT INTO farmer_contact (FarmerID, ContactNumber) VALUES (?, ?)";
                    $contactStmt = $conn->prepare($contactQuery);
                    
                    foreach ($contactNumbers as $contactNumber) {
                        if (!empty($contactNumber)) {
                            $contactStmt->bind_param("ss", $farmerId, $contactNumber);
                            $contactStmt->execute();
                        }
                    }
                    
                    $contactStmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Farmer updated successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error updating farmer: " . $e->getMessage());
            }
            
            $stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: farmers.php");
    exit();
}

// Delete farmer
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $farmerId = sanitizeInput($_GET['id']);
    
    // Check if farmer has harvests
    $checkQuery = "SELECT COUNT(*) as count FROM harvest_session WHERE FarmerID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $farmerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($row['count'] > 0) {
        showError("Cannot delete farmer because they have associated harvest sessions.");
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete contact numbers
            $deleteContactQuery = "DELETE FROM farmer_contact WHERE FarmerID = ?";
            $deleteContactStmt = $conn->prepare($deleteContactQuery);
            $deleteContactStmt->bind_param("s", $farmerId);
            $deleteContactStmt->execute();
            $deleteContactStmt->close();
            
            // Delete farmer
            $deleteFarmerQuery = "DELETE FROM farmer WHERE FarmerID = ?";
            $deleteFarmerStmt = $conn->prepare($deleteFarmerQuery);
            $deleteFarmerStmt->bind_param("s", $farmerId);
            $deleteFarmerStmt->execute();
            $deleteFarmerStmt->close();
            
            // Commit transaction
            $conn->commit();
            showSuccess("Farmer deleted successfully!");
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            showError("Error deleting farmer: " . $e->getMessage());
        }
    }
    
    // Redirect
    header("Location: farmers.php");
    exit();
}

// Edit farmer - Get data for form
$editData = null;
$contactNumbers = [];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $farmerId = sanitizeInput($_GET['id']);
    
    // Get farmer data
    $query = "SELECT * FROM farmer WHERE FarmerID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $farmerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
        
        // Get contact numbers
        $contactQuery = "SELECT ContactNumber FROM farmer_contact WHERE FarmerID = ?";
        $contactStmt = $conn->prepare($contactQuery);
        $contactStmt->bind_param("s", $farmerId);
        $contactStmt->execute();
        $contactResult = $contactStmt->get_result();
        
        while ($contactRow = $contactResult->fetch_assoc()) {
            $contactNumbers[] = $contactRow['ContactNumber'];
        }
        
        $contactStmt->close();
    }
    
    $stmt->close();
}

// Get all farmers
$query = "SELECT f.*, COUNT(h.HarvestID) as HarvestCount, 
          SUM(h.TotalHarvestQuantity) as TotalHarvest
          FROM farmer f
          LEFT JOIN harvest_session h ON f.FarmerID = h.FarmerID
          GROUP BY f.FarmerID
          ORDER BY f.Name";
$result = $conn->query($query);
$farmers = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $farmers[] = $row;
    }
}

// Set page title
$pageTitle = "Farmers Management";
include('includes/header.php');
?>

<main>
    <h1>Farmers Management</h1>
    
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
    
    <!-- Farmer Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Farmer' : 'Add New Farmer'; ?></h2>
        <form method="POST" action="" id="farmerForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="farmerId" value="<?php echo $editData['FarmerID']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="name">Farmer Name</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo $editData ? $editData['Name'] : ''; ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="street">Street Address</label>
                    <input type="text" id="street" name="street" required 
                           value="<?php echo $editData ? $editData['Street'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" required 
                           value="<?php echo $editData ? $editData['City'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="zipCode">Zip Code</label>
                <input type="text" id="zipCode" name="zipCode" required 
                       value="<?php echo $editData ? $editData['ZipCode'] : ''; ?>">
            </div>
            
            <div class="form-group" id="contactNumbersContainer">
                <label>Contact Numbers</label>
                <?php if (empty($contactNumbers)): ?>
                    <div class="contact-number-row">
                        <input type="text" name="contactNumbers[]" placeholder="Contact Number">
                        <button type="button" class="btn btn-primary add-contact">+</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($contactNumbers as $index => $number): ?>
                        <div class="contact-number-row">
                            <input type="text" name="contactNumbers[]" value="<?php echo $number; ?>" placeholder="Contact Number">
                            <?php if ($index === 0): ?>
                                <button type="button" class="btn btn-primary add-contact">+</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-danger remove-contact">-</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $editData ? 'Update Farmer' : 'Add Farmer'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="farmers.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Farmers List -->
    <div class="data-table">
        <div class="table-header">
            <div class="header-content">
                <h2>All Farmers</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search farmers...">
                </div>
            </div>
        </div>
        
        <table id="farmersTable">
            <thead>
                <tr>
                    <th>Farmer ID</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th>Harvest Count</th>
                    <th>Total Harvest</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($farmers)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No farmers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($farmers as $farmer): ?>
                        <tr>
                            <td><?php echo $farmer['FarmerID']; ?></td>
                            <td><?php echo $farmer['Name']; ?></td>
                            <td><?php echo $farmer['City'] . ', ' . $farmer['ZipCode']; ?></td>
                            <td>
                                <?php
                                // Get contact numbers
                                $contactQuery = "SELECT ContactNumber FROM farmer_contact WHERE FarmerID = ?";
                                $contactStmt = $conn->prepare($contactQuery);
                                $contactStmt->bind_param("s", $farmer['FarmerID']);
                                $contactStmt->execute();
                                $contactResult = $contactStmt->get_result();
                                
                                $contacts = [];
                                while ($contactRow = $contactResult->fetch_assoc()) {
                                    $contacts[] = $contactRow['ContactNumber'];
                                }
                                
                                echo implode(', ', $contacts);
                                $contactStmt->close();
                                ?>
                            </td>
                            <td><?php echo $farmer['HarvestCount']; ?></td>
                            <td><?php echo number_format($farmer['TotalHarvest'] ?? 0, 2) . ' kg'; ?></td>
                            <td class="action-buttons">
                                <a href="farmer_details.php?id=<?php echo $farmer['FarmerID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="farmers.php?action=edit&id=<?php echo $farmer['FarmerID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="farmers.php?action=delete&id=<?php echo $farmer['FarmerID']; ?>" 
                                   class="btn btn-danger btn-sm delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Script for dynamic contact number fields and search functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add contact number field
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-contact')) {
            const container = document.getElementById('contactNumbersContainer');
            const newRow = document.createElement('div');
            newRow.className = 'contact-number-row';
            newRow.innerHTML = `
                <input type="text" name="contactNumbers[]" placeholder="Contact Number">
                <button type="button" class="btn btn-danger remove-contact">-</button>
            `;
            container.appendChild(newRow);
        }
    });
    
    // Remove contact number field
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-contact')) {
            e.target.parentElement.remove();
        }
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const farmersTable = document.getElementById('farmersTable');
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = farmersTable.querySelectorAll('tbody tr');
        
        rows.forEach(function(row) {
            let found = false;
            const cells = row.querySelectorAll('td');
            
            cells.forEach(function(cell) {
                if (cell.textContent.toLowerCase().indexOf(searchTerm) > -1) {
                    found = true;
                }
            });
            
            if (found) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<style>
.contact-number-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.contact-number-row input {
    flex: 1;
}

.contact-number-row button {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

/* New styles for the search box beside All Farmers heading */
.table-header {
    margin-bottom: 15px;
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.search-box {
    flex: 0 0 250px;
}

.search-box input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>

<?php
// Include footer
//include('includes/footer.php');
?>
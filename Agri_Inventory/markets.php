<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new market
        if ($_POST['action'] == 'add') {
            $marketId = generateID('MKT', 8);
            $marketName = sanitizeInput($_POST['marketName']);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            
            $query = "INSERT INTO market (MarketID, MarketName, Street, City, ZipCode) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssss", $marketId, $marketName, $street, $city, $zipCode);
            
            if ($stmt->execute()) {
                showSuccess("Market added successfully!");
            } else {
                showError("Error adding market: " . $stmt->error);
            }
            
            $stmt->close();
        }
        // Update market
        else if ($_POST['action'] == 'edit' && isset($_POST['marketId'])) {
            $marketId = sanitizeInput($_POST['marketId']);
            $marketName = sanitizeInput($_POST['marketName']);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            
            $query = "UPDATE market 
                     SET MarketName = ?, Street = ?, City = ?, ZipCode = ? 
                     WHERE MarketID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssss", $marketName, $street, $city, $zipCode, $marketId);
            
            if ($stmt->execute()) {
                showSuccess("Market updated successfully!");
            } else {
                showError("Error updating market: " . $stmt->error);
            }
            
            $stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: markets.php");
    exit();
}

// Delete market
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $marketId = sanitizeInput($_GET['id']);
    
    // Check if market is used in shipments or orders
    $checkQuery = "SELECT 
                  (SELECT COUNT(*) FROM shipment WHERE MarketID = ?) as shipment_count,
                  (SELECT COUNT(*) FROM order_table WHERE MarketID = ?) as order_count";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ss", $marketId, $marketId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($row['shipment_count'] > 0 || $row['order_count'] > 0) {
        showError("Cannot delete market because it is associated with shipments or orders.");
    } else {
        $query = "DELETE FROM market WHERE MarketID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $marketId);
        
        if ($stmt->execute()) {
            showSuccess("Market deleted successfully!");
        } else {
            showError("Error deleting market: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    // Redirect
    header("Location: markets.php");
    exit();
}

// Edit market - Get data for form
$editData = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $marketId = sanitizeInput($_GET['id']);
    
    $query = "SELECT * FROM market WHERE MarketID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $marketId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
    }
    
    $stmt->close();
}

// Get all markets
$query = "SELECT m.*, 
          (SELECT COUNT(*) FROM shipment WHERE MarketID = m.MarketID) as ShipmentCount,
          (SELECT COUNT(*) FROM order_table WHERE MarketID = m.MarketID) as OrderCount
          FROM market m
          ORDER BY m.MarketName";
$result = $conn->query($query);
$markets = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $markets[] = $row;
    }
}

// Set page title
$pageTitle = "Market Management";
include('includes/header.php');
?>

<main>
    <h1>Market Management</h1>
    
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
    
    <!-- Market Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Market' : 'Add New Market'; ?></h2>
        <form method="POST" action="" id="marketForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="marketId" value="<?php echo $editData['MarketID']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="marketName">Market Name</label>
                <input type="text" id="marketName" name="marketName" required 
                       value="<?php echo $editData ? $editData['MarketName'] : ''; ?>">
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
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $editData ? 'Update Market' : 'Add Market'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="markets.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Markets List -->
    <div class="data-table">
        <div class="table-header">
            <h2>All Markets</h2>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search markets...">
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Market ID</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Shipments</th>
                    <th>Orders</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($markets)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No markets found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($markets as $market): ?>
                        <tr>
                            <td><?php echo $market['MarketID']; ?></td>
                            <td><?php echo $market['MarketName']; ?></td>
                            <td><?php echo $market['City'] . ', ' . $market['ZipCode']; ?></td>
                            <td><?php echo $market['ShipmentCount']; ?></td>
                            <td><?php echo $market['OrderCount']; ?></td>
                            <td class="action-buttons">
                                <a href="market_details.php?id=<?php echo $market['MarketID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="markets.php?action=edit&id=<?php echo $market['MarketID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="markets.php?action=delete&id=<?php echo $market['MarketID']; ?>" 
                                   class="btn btn-danger btn-sm delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php
// Include footer
include('includes/footer.php');
?>
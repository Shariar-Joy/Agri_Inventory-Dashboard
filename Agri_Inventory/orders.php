<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new order
        if ($_POST['action'] == 'add') {
            $orderId = generateID('ORD', 8);
            $marketId = sanitizeInput($_POST['marketId']);
            $customerId = sanitizeInput($_POST['customerId']);
            $orderDate = sanitizeInput($_POST['orderDate']);
            
            // Purchase details
            $cropNames = isset($_POST['cropNames']) ? $_POST['cropNames'] : [];
            $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
            $unitPrices = isset($_POST['unitPrices']) ? $_POST['unitPrices'] : [];
            $totalPrices = isset($_POST['totalPrices']) ? $_POST['totalPrices'] : [];
            
            // Calculate total amount
            $totalAmount = 0;
            if (!empty($totalPrices)) {
                foreach ($totalPrices as $price) {
                    $totalAmount += floatval($price);
                }
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert order
                $query = "INSERT INTO order_table (OrderID, MarketID, CustomerID, OrderDate, TotalAmount) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssd", $orderId, $marketId, $customerId, $orderDate, $totalAmount);
                $stmt->execute();
                $stmt->close();
                
                // Insert purchases
                if (!empty($cropNames) && !empty($quantities) && !empty($unitPrices) && !empty($totalPrices)) {
                    for ($i = 0; $i < count($cropNames); $i++) {
                        if (!empty($cropNames[$i]) && !empty($quantities[$i]) && !empty($unitPrices[$i]) && !empty($totalPrices[$i])) {
                            $purchaseId = generateID('PCH', 8);
                            $cropName = sanitizeInput($cropNames[$i]);
                            $quantity = floatval($quantities[$i]);
                            $unitPrice = floatval($unitPrices[$i]);
                            $totalPrice = floatval($totalPrices[$i]);
                            
                            $purchaseQuery = "INSERT INTO purchase 
                                (PurchaseID, PurchaseDate, CropName, Quantity, UnitPrice, TotalPrice, OrderID) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $purchaseStmt = $conn->prepare($purchaseQuery);
                            $purchaseStmt->bind_param("sssddds", $purchaseId, $orderDate, $cropName, $quantity, $unitPrice, $totalPrice, $orderId);
                            $purchaseStmt->execute();
                            $purchaseStmt->close();
                            
                            // Link batches to purchase if needed (based on crop availability)
                            $availableBatches = getBatchesByCrop($conn, $cropName, $quantity);
                            $remainingQuantity = $quantity;

                            foreach ($availableBatches as $batch) {
                                if ($remainingQuantity <= 0) break;
                                
                                $allocated = min($remainingQuantity, $batch['available_quantity']);
                                
                                // Insert into batch_purchase with allocated quantity
                                $batchLinkQuery = "INSERT INTO batch_purchase (BatchID, PurchaseID, Quantity) VALUES (?, ?, ?)";
                                $batchLinkStmt = $conn->prepare($batchLinkQuery);
                                $batchLinkStmt->bind_param("ssd", $batch['BatchID'], $purchaseId, $allocated);
                                $batchLinkStmt->execute();
                                $batchLinkStmt->close();
                                
                                $remainingQuantity -= $allocated;
                            }
                        }
                    }
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Order added successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error adding order: " . $e->getMessage());
            }
        }
        // Update order
        else if ($_POST['action'] == 'edit' && isset($_POST['orderId'])) {
            $orderId = sanitizeInput($_POST['orderId']);
            $marketId = sanitizeInput($_POST['marketId']);
            $customerId = sanitizeInput($_POST['customerId']);
            $orderDate = sanitizeInput($_POST['orderDate']);
            
            // Purchase details
            $purchaseIds = isset($_POST['purchaseIds']) ? $_POST['purchaseIds'] : [];
            $cropNames = isset($_POST['cropNames']) ? $_POST['cropNames'] : [];
            $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
            $unitPrices = isset($_POST['unitPrices']) ? $_POST['unitPrices'] : [];
            $totalPrices = isset($_POST['totalPrices']) ? $_POST['totalPrices'] : [];
            
            // Calculate total amount
            $totalAmount = 0;
            if (!empty($totalPrices)) {
                foreach ($totalPrices as $price) {
                    $totalAmount += floatval($price);
                }
            }
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update order
                $query = "UPDATE order_table 
                          SET MarketID = ?, CustomerID = ?, OrderDate = ?, TotalAmount = ? 
                          WHERE OrderID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssds", $marketId, $customerId, $orderDate, $totalAmount, $orderId);
                $stmt->execute();
                $stmt->close();
                
                // Delete existing purchases and batch links
                $deletePurchaseQuery = "DELETE FROM purchase WHERE OrderID = ?";
                $deletePurchaseStmt = $conn->prepare($deletePurchaseQuery);
                $deletePurchaseStmt->bind_param("s", $orderId);
                $deletePurchaseStmt->execute();
                $deletePurchaseStmt->close();
                
                // Insert purchases
                if (!empty($cropNames) && !empty($quantities) && !empty($unitPrices) && !empty($totalPrices)) {
                    for ($i = 0; $i < count($cropNames); $i++) {
                        if (!empty($cropNames[$i]) && !empty($quantities[$i]) && !empty($unitPrices[$i]) && !empty($totalPrices[$i])) {
                            $purchaseId = (!empty($purchaseIds[$i])) ? $purchaseIds[$i] : generateID('PCH', 8);
                            $cropName = sanitizeInput($cropNames[$i]);
                            $quantity = floatval($quantities[$i]);
                            $unitPrice = floatval($unitPrices[$i]);
                            $totalPrice = floatval($totalPrices[$i]);
                            
                            $purchaseQuery = "INSERT INTO purchase 
                                            (PurchaseID, PurchaseDate, CropName, Quantity, UnitPrice, TotalPrice, OrderID) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $purchaseStmt = $conn->prepare($purchaseQuery);
                            $purchaseStmt->bind_param("sssdds", $purchaseId, $orderDate, $cropName, $quantity, $unitPrice, $totalPrice, $orderId);
                            $purchaseStmt->execute();
                            $purchaseStmt->close();
                            
                            // Link batches to purchase if needed (based on crop availability)
                            $availableBatches = getBatchesByCrop($conn, $cropName, $quantity);
                            $remainingQuantity = $quantity;
                            
                            foreach ($availableBatches as $batch) {
                                if ($remainingQuantity <= 0) break;
                                
                                $batchLinkQuery = "INSERT INTO batch_purchase (BatchID, PurchaseID) VALUES (?, ?)";
                                $batchLinkStmt = $conn->prepare($batchLinkQuery);
                                $batchLinkStmt->bind_param("ss", $batch['BatchID'], $purchaseId);
                                $batchLinkStmt->execute();
                                $batchLinkStmt->close();
                                
                                // Update remaining quantity
                                $batchQuantity = min($remainingQuantity, $batch['Quantity']);
                                $remainingQuantity -= $batchQuantity;
                            }
                        }
                    }
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Order updated successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error updating order: " . $e->getMessage());
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: orders.php");
    exit();
}

// Delete order
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $orderId = sanitizeInput($_GET['id']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete purchases (which will cascade delete batch_purchase)
        $deletePurchaseQuery = "DELETE FROM purchase WHERE OrderID = ?";
        $deletePurchaseStmt = $conn->prepare($deletePurchaseQuery);
        $deletePurchaseStmt->bind_param("s", $orderId);
        $deletePurchaseStmt->execute();
        $deletePurchaseStmt->close();
        
        // Delete order
        $deleteOrderQuery = "DELETE FROM order_table WHERE OrderID = ?";
        $deleteOrderStmt = $conn->prepare($deleteOrderQuery);
        $deleteOrderStmt->bind_param("s", $orderId);
        $deleteOrderStmt->execute();
        $deleteOrderStmt->close();
        
        // Commit transaction
        $conn->commit();
        showSuccess("Order deleted successfully!");
    } catch (Exception $e) {
        // Rollback in case of error
        $conn->rollback();
        showError("Error deleting order: " . $e->getMessage());
    }
    
    // Redirect
    header("Location: orders.php");
    exit();
}

// Helper function to get available batches for a crop type
function getBatchesByCrop($conn, $cropName, $requiredQuantity) {
    // Query to get batches with remaining quantity
    $query = "SELECT 
                b.BatchID, 
                (b.Quantity - IFNULL(SUM(bp.allocated), 0)) AS available_quantity
              FROM batch b
              JOIN crop c ON b.BatchID = c.BatchID
              JOIN warehouse_stock ws ON b.BatchID = ws.BatchID
              LEFT JOIN (
                  SELECT BatchID, SUM(Quantity) AS allocated 
                  FROM batch_purchase 
                  GROUP BY BatchID
              ) bp ON b.BatchID = bp.BatchID
              WHERE c.CropName = ?
              GROUP BY b.BatchID
              HAVING available_quantity > 0
              ORDER BY ws.ExpiryDate ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $cropName);
    $stmt->execute();
    $result = $stmt->get_result();
    $batches = [];
    
    $totalQuantity = 0;
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
        $totalQuantity += $row['available_quantity'];
        
        if ($totalQuantity >= $requiredQuantity) {
            break;
        }
    }
    
    $stmt->close();
    return $batches;
}

// Edit order - Get data for form
$editData = null;
$purchaseData = [];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $orderId = sanitizeInput($_GET['id']);
    
    // Get order data
    $query = "SELECT * FROM order_table WHERE OrderID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
        
        // Get purchase data
        $purchaseQuery = "SELECT * FROM purchase WHERE OrderID = ?";
        $purchaseStmt = $conn->prepare($purchaseQuery);
        $purchaseStmt->bind_param("s", $orderId);
        $purchaseStmt->execute();
        $purchaseResult = $purchaseStmt->get_result();
        
        while ($purchaseRow = $purchaseResult->fetch_assoc()) {
            $purchaseData[] = $purchaseRow;
        }
        
        $purchaseStmt->close();
    }
    
    $stmt->close();
}

// Get all orders
$query = "SELECT o.*, m.MarketName, c.Name as CustomerName
          FROM order_table o
          LEFT JOIN market m ON o.MarketID = m.MarketID
          LEFT JOIN customer c ON o.CustomerID = c.CustomerID
          ORDER BY o.OrderDate DESC";
$result = $conn->query($query);
$orders = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Get all markets for dropdown
$marketQuery = "SELECT MarketID, MarketName FROM market ORDER BY MarketName";
$marketResult = $conn->query($marketQuery);
$markets = [];

if ($marketResult && $marketResult->num_rows > 0) {
    while ($row = $marketResult->fetch_assoc()) {
        $markets[] = $row;
    }
}

// Get all customers for dropdown
$customerQuery = "SELECT CustomerID, Name FROM customer ORDER BY Name";
$customerResult = $conn->query($customerQuery);
$customers = [];

if ($customerResult && $customerResult->num_rows > 0) {
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Get available crop types
$cropQuery = "SELECT DISTINCT CropName FROM crop";
$cropResult = $conn->query($cropQuery);
$cropTypes = [];

if ($cropResult && $cropResult->num_rows > 0) {
    while ($row = $cropResult->fetch_assoc()) {
        $cropTypes[] = $row['CropName'];
    }
}

// Set page title
$pageTitle = "Order Management";
include('includes/header.php');
?>

<main>
    <h1>Order Management</h1>
    
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
    
    <!-- Order Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Order' : 'Add New Order'; ?></h2>
        <form method="POST" action="" id="orderForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="orderId" value="<?php echo $editData['OrderID']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="customerId">Customer</label>
                    <select id="customerId" name="customerId" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['CustomerID']; ?>" 
                                <?php echo ($editData && $editData['CustomerID'] == $customer['CustomerID']) ? 'selected' : ''; ?>>
                                <?php echo $customer['Name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="marketId">Market</label>
                    <select id="marketId" name="marketId" required>
                        <option value="">-- Select Market --</option>
                        <?php foreach ($markets as $market): ?>
                            <option value="<?php echo $market['MarketID']; ?>" 
                                <?php echo ($editData && $editData['MarketID'] == $market['MarketID']) ? 'selected' : ''; ?>>
                                <?php echo $market['MarketName']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="orderDate">Order Date</label>
                <input type="date" id="orderDate" name="orderDate" required 
                       value="<?php echo $editData ? $editData['OrderDate'] : date('Y-m-d'); ?>">
            </div>
            
            <h3>Purchase Items</h3>
            
            <div id="purchaseItems">
                <?php if (empty($purchaseData)): ?>
                    <!-- Default empty purchase row -->
                    <div class="purchase-row">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Crop</label>
                                <select name="cropNames[]" class="crop-select" required>
                                    <option value="">-- Select Crop --</option>
                                    <?php foreach ($cropTypes as $crop): ?>
                                        <option value="<?php echo $crop; ?>"><?php echo $crop; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Quantity (kg)</label>
                                <input type="number" name="quantities[]" class="quantity-input" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Unit Price ($)</label>
                                <input type="number" name="unitPrices[]" class="unit-price-input" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Total Price ($)</label>
                                <input type="number" name="totalPrices[]" class="total-price-input" step="0.01" min="0" readonly>
                            </div>
                            
                            <div class="form-group purchase-btn-group">
                                <button type="button" class="btn btn-primary btn-sm add-purchase">+</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Existing purchase rows -->
                    <?php foreach ($purchaseData as $index => $purchase): ?>
                        <div class="purchase-row">
                            <div class="form-row">
                                <input type="hidden" name="purchaseIds[]" value="<?php echo $purchase['PurchaseID']; ?>">
                                
                                <div class="form-group">
                                    <label>Crop</label>
                                    <select name="cropNames[]" class="crop-select" required>
                                        <option value="">-- Select Crop --</option>
                                        <?php foreach ($cropTypes as $crop): ?>
                                            <option value="<?php echo $crop; ?>" 
                                                <?php echo ($purchase['CropName'] == $crop) ? 'selected' : ''; ?>>
                                                <?php echo $crop; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Quantity (kg)</label>
                                    <input type="number" name="quantities[]" class="quantity-input" step="0.01" min="0" required
                                           value="<?php echo $purchase['Quantity']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Unit Price ($)</label>
                                    <input type="number" name="unitPrices[]" class="unit-price-input" step="0.01" min="0" required
                                           value="<?php echo $purchase['UnitPrice']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Total Price ($)</label>
                                    <input type="number" name="totalPrices[]" class="total-price-input" step="0.01" min="0" readonly
                                           value="<?php echo $purchase['TotalPrice']; ?>">
                                </div>
                                
                                <div class="form-group purchase-btn-group">
                                    <?php if ($index === 0): ?>
                                        <button type="button" class="btn btn-primary btn-sm add-purchase">+</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-danger btn-sm remove-purchase">-</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="form-group total-section">
                <h3>Order Total: <span id="orderTotal"><?php echo formatCurrency($editData ? $editData['TotalAmount'] : 0); ?></span></h3>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $editData ? 'Update Order' : 'Add Order'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="orders.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Orders List -->
    <div class="data-table">
        <div class="table-header">
            <h2>All Orders</h2>
            <div class="search-container">
                <input type="text" id="searchInput" placeholder="Search orders...">
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Market</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No orders found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo $order['OrderID']; ?></td>
                            <td><?php echo $order['CustomerName'] ?? 'N/A'; ?></td>
                            <td><?php echo $order['MarketName'] ?? 'N/A'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                            <td>
                                <?php
                                // Get purchase count
                                $purchaseCountQuery = "SELECT COUNT(*) as count FROM purchase WHERE OrderID = ?";
                                $purchaseCountStmt = $conn->prepare($purchaseCountQuery);
                                $purchaseCountStmt->bind_param("s", $order['OrderID']);
                                $purchaseCountStmt->execute();
                                $purchaseCountResult = $purchaseCountStmt->get_result()->fetch_assoc();
                                echo $purchaseCountResult['count'];
                                $purchaseCountStmt->close();
                                ?>
                            </td>
                            <td><?php echo formatCurrency($order['TotalAmount']); ?></td>
                            <td class="action-buttons">
                                <a href="order_details.php?id=<?php echo $order['OrderID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="orders.php?action=edit&id=<?php echo $order['OrderID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="orders.php?action=delete&id=<?php echo $order['OrderID']; ?>" 
                                   class="btn btn-danger btn-sm delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<style>
.purchase-btn-group {
    display: flex;
    align-items: end;
}

.purchase-row {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--color-info-light);
}

.total-section {
    padding: 1rem;
    background: var(--color-light);
    border-radius: var(--border-radius-1);
    margin-top: 1rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calculate total price when quantity or unit price changes
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('quantity-input') || e.target.classList.contains('unit-price-input')) {
            const row = e.target.closest('.purchase-row');
            const quantityInput = row.querySelector('.quantity-input');
            const unitPriceInput = row.querySelector('.unit-price-input');
            const totalPriceInput = row.querySelector('.total-price-input');
            
            const quantity = parseFloat(quantityInput.value) || 0;
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const totalPrice = quantity * unitPrice;
            
            totalPriceInput.value = totalPrice.toFixed(2);
            
            // Update order total
            updateOrderTotal();
        }
    });
    
    // Add purchase row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-purchase')) {
            const container = document.getElementById('purchaseItems');
            const purchaseRows = container.querySelectorAll('.purchase-row');
            const lastRow = purchaseRows[purchaseRows.length - 1];
            
            const newRow = lastRow.cloneNode(true);
            const inputs = newRow.querySelectorAll('input[type="number"]');
            inputs.forEach(input => {
                input.value = '';
            });
            
            // Reset select
            const selects = newRow.querySelectorAll('select');
            selects.forEach(select => {
                select.selectedIndex = 0;
            });
            
            // Change the add button to remove button
            const btnGroup = newRow.querySelector('.purchase-btn-group');
            btnGroup.innerHTML = '<button type="button" class="btn btn-danger btn-sm remove-purchase">-</button>';
            
            container.appendChild(newRow);
        }
    });
    
    // Remove purchase row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-purchase')) {
            e.target.closest('.purchase-row').remove();
            updateOrderTotal();
        }
    });
    
    // Update order total
    function updateOrderTotal() {
        const totalPriceInputs = document.querySelectorAll('.total-price-input');
        let orderTotal = 0;
        
        totalPriceInputs.forEach(input => {
            orderTotal += parseFloat(input.value) || 0;
        });
        
        document.getElementById('orderTotal').textContent = '$' + orderTotal.toFixed(2);
    }
    
    // Initialize total prices
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(input => {
        input.dispatchEvent(new Event('input'));
    });
    
    // Form validation
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', function(e) {
            // Check if at least one purchase item is added
            const cropSelects = document.querySelectorAll('.crop-select');
            if (cropSelects.length === 0) {
                e.preventDefault();
                alert('Please add at least one purchase item');
                return;
            }
            
            // Check if all required fields are filled
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('invalid');
                    isValid = false;
                } else {
                    field.classList.remove('invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields');
            }
            
            // Check if total amount is greater than zero
            const totalElement = document.getElementById('orderTotal');
            const totalAmount = parseFloat(totalElement.textContent.replace('$', '')) || 0;
            
            if (totalAmount <= 0) {
                e.preventDefault();
                alert('Total order amount must be greater than zero');
            }
        });
    }
    
    // Initialize search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
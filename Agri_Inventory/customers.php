<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        // Add new customer
        if ($_POST['action'] == 'add') {
            $customerId = generateID('CST', 8);
            $name = sanitizeInput($_POST['name']);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            $contactNumbers = isset($_POST['contactNumbers']) ? $_POST['contactNumbers'] : [];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Insert into customer table
                $query = "INSERT INTO customer (CustomerID, Name, Street, City, ZipCode) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $customerId, $name, $street, $city, $zipCode);
                $stmt->execute();
                
                // Insert contact numbers
                if (!empty($contactNumbers)) {
                    $contactQuery = "INSERT INTO customer_contact (CustomerID, ContactNumber) VALUES (?, ?)";
                    $contactStmt = $conn->prepare($contactQuery);
                    
                    foreach ($contactNumbers as $contactNumber) {
                        if (!empty($contactNumber)) {
                            $contactStmt->bind_param("ss", $customerId, $contactNumber);
                            $contactStmt->execute();
                        }
                    }
                    
                    $contactStmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Customer added successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error adding customer: " . $e->getMessage());
            }
            
            $stmt->close();
        }
        // Update customer
        else if ($_POST['action'] == 'edit' && isset($_POST['customerId'])) {
            $customerId = sanitizeInput($_POST['customerId']);
            $name = sanitizeInput($_POST['name']);
            $street = sanitizeInput($_POST['street']);
            $city = sanitizeInput($_POST['city']);
            $zipCode = sanitizeInput($_POST['zipCode']);
            $contactNumbers = isset($_POST['contactNumbers']) ? $_POST['contactNumbers'] : [];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update customer table
                $query = "UPDATE customer SET Name = ?, Street = ?, City = ?, ZipCode = ? WHERE CustomerID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $name, $street, $city, $zipCode, $customerId);
                $stmt->execute();
                
                // Delete existing contact numbers
                $deleteQuery = "DELETE FROM customer_contact WHERE CustomerID = ?";
                $deleteStmt = $conn->prepare($deleteQuery);
                $deleteStmt->bind_param("s", $customerId);
                $deleteStmt->execute();
                $deleteStmt->close();
                
                // Insert new contact numbers
                if (!empty($contactNumbers)) {
                    $contactQuery = "INSERT INTO customer_contact (CustomerID, ContactNumber) VALUES (?, ?)";
                    $contactStmt = $conn->prepare($contactQuery);
                    
                    foreach ($contactNumbers as $contactNumber) {
                        if (!empty($contactNumber)) {
                            $contactStmt->bind_param("ss", $customerId, $contactNumber);
                            $contactStmt->execute();
                        }
                    }
                    
                    $contactStmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                showSuccess("Customer updated successfully!");
            } catch (Exception $e) {
                // Rollback in case of error
                $conn->rollback();
                showError("Error updating customer: " . $e->getMessage());
            }
            
            $stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: customers.php");
    exit();
}

// Delete customer
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $customerId = sanitizeInput($_GET['id']);
    
    // Check if customer has orders
    $checkQuery = "SELECT COUNT(*) as count FROM order_table WHERE CustomerID = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $customerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($row['count'] > 0) {
        showError("Cannot delete customer because they have associated orders.");
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete contact numbers
            $deleteContactQuery = "DELETE FROM customer_contact WHERE CustomerID = ?";
            $deleteContactStmt = $conn->prepare($deleteContactQuery);
            $deleteContactStmt->bind_param("s", $customerId);
            $deleteContactStmt->execute();
            $deleteContactStmt->close();
            
            // Delete customer
            $deleteCustomerQuery = "DELETE FROM customer WHERE CustomerID = ?";
            $deleteCustomerStmt = $conn->prepare($deleteCustomerQuery);
            $deleteCustomerStmt->bind_param("s", $customerId);
            $deleteCustomerStmt->execute();
            $deleteCustomerStmt->close();
            
            // Commit transaction
            $conn->commit();
            showSuccess("Customer deleted successfully!");
        } catch (Exception $e) {
            // Rollback in case of error
            $conn->rollback();
            showError("Error deleting customer: " . $e->getMessage());
        }
    }
    
    // Redirect
    header("Location: customers.php");
    exit();
}

// Edit customer - Get data for form
$editData = null;
$contactNumbers = [];
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $customerId = sanitizeInput($_GET['id']);
    
    // Get customer data
    $query = "SELECT * FROM customer WHERE CustomerID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $editData = $result->fetch_assoc();
        
        // Get contact numbers
        $contactQuery = "SELECT ContactNumber FROM customer_contact WHERE CustomerID = ?";
        $contactStmt = $conn->prepare($contactQuery);
        $contactStmt->bind_param("s", $customerId);
        $contactStmt->execute();
        $contactResult = $contactStmt->get_result();
        
        while ($contactRow = $contactResult->fetch_assoc()) {
            $contactNumbers[] = $contactRow['ContactNumber'];
        }
        
        $contactStmt->close();
    }
    
    $stmt->close();
}

// Get all customers
$query = "SELECT c.*, COUNT(o.OrderID) as OrderCount, SUM(o.TotalAmount) as TotalSpent
          FROM customer c
          LEFT JOIN order_table o ON c.CustomerID = o.CustomerID
          GROUP BY c.CustomerID
          ORDER BY c.Name";
$result = $conn->query($query);
$customers = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Set page title
$pageTitle = "Customer Management";
include('includes/header.php');
?>

<main>
    <h1>Customer Management</h1>
    
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
    
    <!-- Customer Form -->
    <div class="form-container">
        <h2><?php echo $editData ? 'Edit Customer' : 'Add New Customer'; ?></h2>
        <form method="POST" action="" id="customerForm">
            <input type="hidden" name="action" value="<?php echo $editData ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="customerId" value="<?php echo $editData['CustomerID']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="name">Customer Name</label>
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
                    <?php echo $editData ? 'Update Customer' : 'Add Customer'; ?>
                </button>
                <?php if ($editData): ?>
                    <a href="customers.php" class="btn btn-danger">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Customers List -->
    <div class="data-table">
        <div class="table-header">
            <div class="header-with-search">
                <h2>All Customers</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search customers...">
                </div>
            </div>
        </div>
        
        <table id="customersTable">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No customers found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo $customer['CustomerID']; ?></td>
                            <td><?php echo $customer['Name']; ?></td>
                            <td><?php echo $customer['City'] . ', ' . $customer['ZipCode']; ?></td>
                            <td>
                                <?php
                                // Get contact numbers
                                $contactQuery = "SELECT ContactNumber FROM customer_contact WHERE CustomerID = ?";
                                $contactStmt = $conn->prepare($contactQuery);
                                $contactStmt->bind_param("s", $customer['CustomerID']);
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
                            <td><?php echo $customer['OrderCount']; ?></td>
                            <td><?php echo formatCurrency($customer['TotalSpent'] ?? 0); ?></td>
                            <td class="action-buttons">
                                <a href="customer_details.php?id=<?php echo $customer['CustomerID']; ?>" 
                                   class="btn btn-primary btn-sm">View</a>
                                <a href="customers.php?action=edit&id=<?php echo $customer['CustomerID']; ?>" 
                                   class="btn btn-primary btn-sm">Edit</a>
                                <a href="customers.php?action=delete&id=<?php echo $customer['CustomerID']; ?>" 
                                   class="btn btn-danger btn-sm delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Script for dynamic contact number fields -->
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
    const customersTable = document.getElementById('customersTable');
    const tableRows = customersTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    searchInput.addEventListener('keyup', function() {
        const searchText = searchInput.value.toLowerCase();
        
        for (let i = 0; i < tableRows.length; i++) {
            const row = tableRows[i];
            let found = false;
            
            // Skip the "No customers found" row
            if (row.cells.length === 1 && row.cells[0].getAttribute('colspan')) {
                continue;
            }
            
            // Search through all cells except the last one (actions)
            for (let j = 0; j < row.cells.length - 1; j++) {
                const cellText = row.cells[j].textContent.toLowerCase();
                if (cellText.includes(searchText)) {
                    found = true;
                    break;
                }
            }
            
            row.style.display = found ? '' : 'none';
        }
        
        // Show "No results" message if all rows are hidden
        let visibleRows = 0;
        for (let i = 0; i < tableRows.length; i++) {
            if (tableRows[i].style.display !== 'none') {
                visibleRows++;
            }
        }
        
        // Check if we need to add or remove the "no results" row
        const noResultsRow = document.getElementById('noResultsRow');
        if (visibleRows === 0 && !noResultsRow) {
            const tbody = customersTable.getElementsByTagName('tbody')[0];
            const newRow = document.createElement('tr');
            newRow.id = 'noResultsRow';
            newRow.innerHTML = '<td colspan="7" style="text-align: center;">No customers found matching your search.</td>';
            tbody.appendChild(newRow);
        } else if (visibleRows > 0 && noResultsRow) {
            noResultsRow.remove();
        }
    });
    
    // Confirm delete
    const deleteButtons = document.querySelectorAll('.delete-btn');
    
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this customer?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<style>
/* Styles for contact number rows */
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

/* Additional CSS for the search box positioning */
.header-with-search {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.search-box {
    flex: 0 0 auto;
    margin-left: 20px;
}

.search-box input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 200px;
}

/* Ensure responsive design for smaller screens */
@media (max-width: 768px) {
    .header-with-search {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-box {
        margin-left: 0;
        margin-top: 10px;
        width: 100%;
    }
    
    .search-box input {
        width: 100%;
    }
}
</style>

<?php
// Include footer
//include('includes/footer.php');
?>
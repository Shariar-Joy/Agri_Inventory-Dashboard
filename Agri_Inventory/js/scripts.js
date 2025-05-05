// DOM References
const sideMenu = document.querySelector("aside");
const menuBtn = document.querySelector("#menu-btn");
const closeBtn = document.querySelector("#close-btn");
const themeToggler = document.querySelector(".theme-toggler");

// Show sidebar
menuBtn.addEventListener("click", () => {
    sideMenu.style.display = "block";
});

// Close sidebar
closeBtn.addEventListener("click", () => {
    sideMenu.style.display = "none";
});

// Change theme
themeToggler.addEventListener("click", () => {
    document.body.classList.toggle("dark-theme-variables");
    
    themeToggler.querySelector("span:nth-child(1)").classList.toggle("active");
    themeToggler.querySelector("span:nth-child(2)").classList.toggle("active");
});

// Set current date in date input
document.addEventListener("DOMContentLoaded", function() {
    const dateInput = document.querySelector('.date input[type="date"]');
    if (dateInput) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        dateInput.value = `${year}-${month}-${day}`;
    }

    // Add product button
    const addProductBtn = document.querySelector('.add-product');
    if (addProductBtn) {
        addProductBtn.addEventListener('click', function() {
            window.location.href = 'batches.php?action=add';
        });
    }

    // Attach event handlers to delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });

    // Warehouse selection
    const warehouseSelect = document.querySelector('#warehouse-select');
    if (warehouseSelect) {
        warehouseSelect.addEventListener('change', function() {
            window.location.href = `select_warehouse.php?id=${this.value}`;
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    }
    
    // Initialize search functionality for warehouse table
    initializeWarehouseSearch();
});

// Form validation functions
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('invalid');
            isValid = false;
        } else {
            field.classList.remove('invalid');
        }
    });
    
    return isValid;
}

// Data table search functionality
function searchTable(tableId, searchInputId) {
    const input = document.getElementById(searchInputId);
    if (!input) return;
    
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');
    
    // Start from 1 to skip the header row
    for (let i = 1; i < rows.length; i++) {
        let txtValue = "";
        const cells = rows[i].getElementsByTagName('td');
        
        // Skip the last column (actions)
        for (let j = 0; j < cells.length - 1; j++) {
            txtValue += cells[j].textContent || cells[j].innerText;
        }
        
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            rows[i].style.display = "";
        } else {
            rows[i].style.display = "none";
        }
    }
}

// Initialize warehouse search functionality
function initializeWarehouseSearch() {
    const warehouseSearchInput = document.getElementById('warehouseSearchInput');
    if (warehouseSearchInput) {
        warehouseSearchInput.addEventListener('keyup', function() {
            searchTable('warehouseTable', 'warehouseSearchInput');
        });
        
        // Trigger search on page load if there's any input value
        if (warehouseSearchInput.value) {
            searchTable('warehouseTable', 'warehouseSearchInput');
        }
    }
}

// Backward compatibility for old search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const table = searchInput.closest('.data-table').querySelector('table');
            const filter = searchInput.value.toUpperCase();
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                let txtValue = "";
                const td = tr[i].getElementsByTagName('td');
                
                for (let j = 0; j < td.length - 1; j++) { // Exclude action column
                    txtValue += td[j].textContent || td[j].innerText;
                }
                
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        });
    }
});
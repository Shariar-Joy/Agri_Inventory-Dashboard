<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Default page title
if (!isset($pageTitle)) {
    $pageTitle = "Agricultural Inventory System";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?> - AgriInventory System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <div class="container">
    <!-- SIDEBAR -->
    <aside>
      <div class="top">
        <div class="logo">
          <h2>AgriInventory</span></h2>
        </div>
        <div class="close" id="close-btn">
          <span class="material-symbols-sharp">close</span>
        </div>
      </div>

      <div class="sidebar">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">dashboard</span>
          <h3>Dashboard</h3>
        </a>
        <a href="warehouses.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'warehouses.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">warehouse</span>
          <h3>Warehouses</h3>
        </a>
        <a href="inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">inventory</span>
          <h3>Inventory</h3>
        </a>
        <a href="warehouse_climate.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'warehouse_climate.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">thermostat</span>
          <h3>Climate Log</h3>
        </a>
        <a href="farmers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'farmers.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">agriculture</span>
          <h3>Farmers</h3>
        </a>
        <a href="harvests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'harvests.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">grass</span>
          <h3>Harvests</h3>
        </a>
        <a href="batches.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'batches.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">inventory_2</span>
          <h3>Batches</h3>
        </a>
        <a href="shipments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shipments.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">local_shipping</span>
          <h3>Shipments</h3>
        </a>
        <a href="vehicles.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'vehicles.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">directions_car</span>
          <h3>Vehicles</h3>
        </a>
        <a href="customers.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">group</span>
          <h3>Customers</h3>
        </a>
        <a href="orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">shopping_cart</span>
          <h3>Orders</h3>
        </a>
        <a href="markets.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'markets.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">store</span>
          <h3>Markets</h3>
        </a>
        <a href="spoilage.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'spoilage.php' ? 'active' : ''; ?>">
          <span class="material-symbols-sharp">recycling</span>
          <h3>Spoilage</h3>
        </a>
        <a href="logout.php">
          <span class="material-symbols-sharp"><b>logout</b></span>
          <h3><b>Logout</b></h3>
        </a>
      </div>
    </aside>
    <!-- END OF SIDEBAR -->

    <!-- RIGHT SECTION -->
    <div class="right">
      <div class="top">
        <button id="menu-btn">
          <span class="material-symbols-sharp">menu</span>
        </button>
        <div class="theme-toggler">
          <span class="material-symbols-sharp active">light_mode</span>
          <span class="material-symbols-sharp">dark_mode</span>
        </div>
        <div class="profile">
          <div class="info">
            <p>Hey, <b><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></b></p>
            <small class="text-muted"><?php echo isset($_SESSION['role']) ? ucfirst(htmlspecialchars($_SESSION['role'])) : 'User'; ?></small>
          </div>
          <div class="profile-photo">
            <img src="images/profile-1.gif" alt="Profile Photo">
          </div>
        </div>
      </div>
      <!-- END OF TOP -->
<div class="recent-updates">
        <h2>Recent Updates</h2>
        <div class="updates">
          <?php
          // Check if activity_log table exists before querying
          $tableCheckQuery = "SHOW TABLES LIKE 'activity_log'";
          $tableExists = $conn->query($tableCheckQuery);
          
          if ($tableExists && $tableExists->num_rows > 0) {
            // Get recent updates from the database
            $updateQuery = "SELECT a.event_type, a.event_time, a.entity_id, u.username 
                           FROM activity_log a 
                           JOIN users u ON a.user_id = u.user_id 
                           ORDER BY a.event_time DESC LIMIT 3";
            $updateResult = $conn->query($updateQuery);
            
            if ($updateResult && $updateResult->num_rows > 0) {
              while ($update = $updateResult->fetch_assoc()) {
                echo '<div class="update">
                      <div class="profile-photo">
                        <img src="images/profile-1.jpg" alt="Profile Photo">
                      </div>
                      <div class="message">
                        <p><b>' . htmlspecialchars($update['username']) . '</b> ' . htmlspecialchars($update['event_type']) . '</p>
                        <small class="text-muted">' . date('M d, H:i', strtotime($update['event_time'])) . '</small>
                      </div>
                    </div>';
              }
            } else {
              // Show default updates if no data
              displayDefaultUpdates();
            }
          } else {
            // Show default updates if table doesn't exist
            displayDefaultUpdates();
          }
          
          // Function to display default updates
          function displayDefaultUpdates() {
            ?>
            <div class="update">
              <div class="profile-photo">
                <img src="images/farmer_1.jpg" alt="Profile Photo">
              </div>
              <div class="message">
                <p><b>Joynal</b> delivered his harvest to Warehouse B</p>
                <small class="text-muted">2 Minutes Ago</small>
              </div>
            </div>
            <div class="update">
              <div class="profile-photo">
                <img src="images/farmer_2.avif" alt="Profile Photo">
              </div>
              <div class="message">
                <p><b>Korim</b> placed a new order for tomatoes</p>
                <small class="text-muted">10 Minutes Ago</small>
              </div>
            </div>
            <div class="update">
              <div class="profile-photo">
                <img src="images/farmer_3.png" alt="Profile Photo">
              </div>
              <div class="message">
                <p><b>Rohim</b> shipped potatoes to Market East</p>
                <small class="text-muted">45 Minutes Ago</small>
              </div>
            </div>
          <?php } ?>
        </div>
      </div>
      <!-- END OF RECENT UPDATES -->

      <div class="sales-analytics">
        <h2>Inventory Analytics</h2>
        <?php
        // Get inventory analytics
        try {
          $analyticsQuery = "SELECT 
                            (SELECT COUNT(*) FROM batch) as total_batches,
                            (SELECT COUNT(*) FROM warehouse) as total_warehouses,
                            (SELECT COUNT(*) FROM harvest_session WHERE YEAR(STR_TO_DATE(CONCAT(Year, '-', Month, '-', Day), '%Y-%m-%d')) = YEAR(CURRENT_DATE())) as harvests_this_year,
                            (SELECT COUNT(*) FROM order_table WHERE MONTH(OrderDate) = MONTH(CURRENT_DATE())) as orders_this_month";
          $analyticsResult = $conn->query($analyticsQuery);
          
          if ($analyticsResult && $analyticsResult->num_rows > 0) {
            $analytics = $analyticsResult->fetch_assoc();
          } else {
            // Default values if query fails
            $analytics = [
              'total_batches' => 0,
              'total_warehouses' => 0,
              'harvests_this_year' => 0,
              'orders_this_month' => 0
            ];
          }
        } catch (Exception $e) {
          // Default values if query throws exception
          $analytics = [
            'total_batches' => 0,
            'total_warehouses' => 0,
            'harvests_this_year' => 0,
            'orders_this_month' => 0
          ];
        }
        ?>
        <div class="item online">
          <div class="icon">
            <span class="material-symbols-sharp">inventory_2</span>
          </div>
          <div class="right">
            <div class="info">
              <h3>TOTAL BATCHES</h3>
              <small class="text-muted">All Time</small>
            </div>
            <h3><?php echo $analytics['total_batches']; ?></h3>
          </div>
        </div>
        <div class="item offline">
          <div class="icon">
            <span class="material-symbols-sharp">warehouse</span>
          </div>
          <div class="right">
            <div class="info">
              <h3>WAREHOUSES</h3>
              <small class="text-muted">All Time</small>
            </div>
            <h3><?php echo $analytics['total_warehouses']; ?></h3>
          </div>
        </div>
        <div class="item customers">
          <div class="icon">
            <span class="material-symbols-sharp">grass</span>
          </div>
          <div class="right">
            <div class="info">
              <h3>HARVESTS</h3>
              <small class="text-muted">This Year</small>
            </div>
            <h3><?php echo $analytics['harvests_this_year']; ?></h3>
          </div>
        </div>
        <div class="item add-product">
          <div>
            <span class="material-symbols-sharp">add</span>
            <h3>Add Product</h3>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="js/script.js"></script>
</body>
</html>
<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Check if ID parameter exists
if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No spoilage ID provided']);
    exit;
}

$spoilageId = $_GET['id'];

// Get spoilage details
$query = "SELECT sc.*, b.BatchID, c.CropName, c.CropType, u.username as InspectorName,
            DATE_FORMAT(sc.InspectionDate, '%M %d, %Y') as FormattedDate
          FROM spoilage_control sc
          JOIN batch b ON sc.BatchID = b.BatchID
          JOIN crop c ON b.BatchID = c.BatchID
          JOIN users u ON sc.InspectedBy = u.user_id
          WHERE sc.SpoilageID = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $spoilageId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $spoilageData = $result->fetch_assoc();
    echo json_encode($spoilageData);
} else {
    echo json_encode(['error' => 'Spoilage record not found']);
}

$stmt->close();
$conn->close();
?>
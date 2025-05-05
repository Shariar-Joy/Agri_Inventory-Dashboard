<?php
// Include database connection
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Check if batch ID is provided
if (!isset($_GET['id'])) {
    showError("Batch ID is required");
    header("Location: batches.php");
    exit();
}

$batchId = sanitizeInput($_GET['id']);

// Get batch details
$query = "SELECT b.*, c.CropName, c.CropType, c.CropVariety
          FROM batch b
          JOIN crop c ON b.BatchID = c.BatchID
          WHERE b.BatchID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $batchId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    showError("Batch not found");
    header("Location: batches.php");
    exit();
}

$batch = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $analysisId = generateID('ANL', 8);
    $calories = floatval($_POST['calories']);
    $protein = floatval($_POST['protein']);
    $vitamins = sanitizeInput($_POST['vitamins']);
    $minerals = sanitizeInput($_POST['minerals']);
    $day = intval($_POST['day']);
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    
    $query = "INSERT INTO nutritional_analysis 
              (AnalysisID, BatchID, Calories, Protein, Vitamins, Minerals, Day, Month, Year)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssddssiii", $analysisId, $batchId, $calories, $protein, $vitamins, $minerals, $day, $month, $year);
    
    if ($stmt->execute()) {
        showSuccess("Nutritional analysis added successfully!");
        header("Location: batch_details.php?id=" . $batchId);
        exit();
    } else {
        showError("Error adding nutritional analysis: " . $stmt->error);
    }
    
    $stmt->close();
}

// Set page title
$pageTitle = "Add Nutritional Analysis";
include('includes/header.php');
?>

<main>
    <h1>Add Nutritional Analysis</h1>
    
    <div class="date">
        <input type="date" value="<?php echo date('Y-m-d'); ?>">
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="form-container">
        <div class="batch-info">
            <h2>Batch Information</h2>
            <p><strong>Batch ID:</strong> <?php echo $batch['BatchID']; ?></p>
            <p><strong>Crop:</strong> <?php echo $batch['CropName'] . ' (' . $batch['CropVariety'] . ')'; ?></p>
            <p><strong>Type:</strong> <?php echo $batch['CropType']; ?></p>
        </div>
        
        <form method="POST" action="">
            <h3>Nutritional Analysis Details</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="calories">Calories (kcal per 100g)</label>
                    <input type="number" id="calories" name="calories" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="protein">Protein (g per 100g)</label>
                    <input type="number" id="protein" name="protein" step="0.01" min="0" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="vitamins">Vitamins (comma separated list)</label>
                <textarea id="vitamins" name="vitamins" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="minerals">Minerals (comma separated list)</label>
                <textarea id="minerals" name="minerals" rows="3" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="day">Day</label>
                    <input type="number" id="day" name="day" required min="1" max="31" value="<?php echo date('d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="month">Month</label>
                    <input type="number" id="month" name="month" required min="1" max="12" value="<?php echo date('m'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="year">Year</label>
                    <input type="number" id="year" name="year" required min="2000" max="2100" value="<?php echo date('Y'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Analysis</button>
                <a href="batch_details.php?id=<?php echo $batchId; ?>" class="btn btn-danger">Cancel</a>
            </div>
        </form>
    </div>
</main>

<style>
.batch-info {
    background: var(--color-light);
    padding: 1rem;
    border-radius: var(--border-radius-1);
    margin-bottom: 1.5rem;
}

.batch-info h2 {
    margin-bottom: 0.5rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
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
});
</script>

<?php
//include('includes/footer.php');
?>
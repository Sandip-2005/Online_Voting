<?php

// 1. HELPER FUNCTIONS (The "How")
// ===================================================================

/**
 * Fetches the current voting schedule from the database.
 * @param mysqli $conn The database connection object.
 * @return array The schedule data, or an empty array if none is set.
 */
function get_current_schedule(mysqli $conn): array {
    $result = $conn->query("SELECT * FROM voting_schedule LIMIT 1");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : [];
}

/**
 * Validates the schedule input from the form.
 * @param array $data The $_POST data from the form.
 * @return string|null An error message if validation fails, otherwise null.
 */
function validate_schedule(array $data): ?string {
    if (empty($data['start_date']) || empty($data['end_date']) || empty($data['daily_start_time']) || empty($data['daily_end_time'])) {
        return "❌ All fields are required.";
    }
    if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
        return "❌ End date cannot be before the start date.";
    }
    if (strtotime($data['daily_end_time']) <= strtotime($data['daily_start_time'])) {
        return "❌ Daily end time must be after the daily start time.";
    }
    return null; // All checks passed
}

/**
 * Handles the form submission to update the voting schedule.
 * @param mysqli $conn The database connection object.
 * @param array $data The $_POST data from the form.
 * @return array An array containing the feedback message and its type.
 */
function handle_schedule_update(mysqli $conn, array $data): array {
    // First, validate the submitted data
    $validationError = validate_schedule($data);
    if ($validationError) {
        return ['text' => $validationError, 'type' => 'error'];
    }

    // If validation passes, update the database within a transaction
    $conn->begin_transaction();
    try {
        $conn->query("TRUNCATE TABLE voting_schedule");

        $stmt = $conn->prepare("INSERT INTO voting_schedule (start_date, end_date, daily_start_time, daily_end_time) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $data['start_date'], $data['end_date'], $data['daily_start_time'], $data['daily_end_time']);
        $stmt->execute();
        
        $conn->commit();
        
        return ['text' => '✅ Voting schedule set successfully!', 'type' => 'success'];
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return ['text' => '❌ A database error occurred: ' . $e->getMessage(), 'type' => 'error'];
    }
}


// 2. MAIN SCRIPT LOGIC (The "What")
// ===================================================================

$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = null;

// If the form was submitted, handle the update process
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = handle_schedule_update($conn, $_POST);
}

// Fetch the current schedule to display in the form
// This runs after the update, so it always shows the latest data
$currentSchedule = get_current_schedule($conn);

?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Panel - Set Voting Schedule</title>
<style>
/* Your existing CSS is great, no changes needed */
body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f5f7fa, #c3cfe2); font-family: 'Segoe UI', sans-serif; }
.schedule-container { background: white; padding: 2rem 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
h2 { text-align: center; margin-bottom: 2rem; color: #2c3e50; font-weight: 600; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.form-group { display: flex; flex-direction: column; }
label { margin-bottom: 0.5rem; color: #34495e; font-weight: 500; font-size: 0.9rem; }
input[type="date"], input[type="time"] { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
button { width: 100%; padding: 0.9rem; background: #27ae60; border: none; border-radius: 6px; color: white; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: background 0.3s; margin-top: 0.5rem; }
button:hover { background: #219150; }
.message { margin-bottom: 1.5rem; padding: 0.8rem 1rem; border-left: 5px solid; border-radius: 4px; }
.message.success { background: #d4edda; border-color: #28a745; color: #155724; }
.message.error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
</style>
</head>
<body>
<div class="schedule-container">
    <h2>Set Voting Schedule</h2>
    
    <?php if ($message): ?>
    <div class="message <?php echo htmlspecialchars($message['type']); ?>">
        <?php echo htmlspecialchars($message['text']); ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($currentSchedule['start_date'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($currentSchedule['end_date'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="daily_start_time">Daily Start Time (e.g., 6 AM):</label>
                <input type="time" id="daily_start_time" name="daily_start_time" value="<?php echo htmlspecialchars($currentSchedule['daily_start_time'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="daily_end_time">Daily End Time (e.g., 6 PM):</label>
                <input type="time" id="daily_end_time" name="daily_end_time" value="<?php echo htmlspecialchars($currentSchedule['daily_end_time'] ?? ''); ?>" required>
            </div>
        </div>
        <button type="submit">Set Schedule</button>
    </form>
</div>
</body>
</html>
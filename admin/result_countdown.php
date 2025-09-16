<?php

// 1. HELPER FUNCTIONS
// ===================================================================

/**
 * Fetches the end date and time of the latest voting schedule.
 * @param mysqli $conn The database connection object.
 * @return DateTime|null A DateTime object of the voting end time, or null if not set.
 */
function get_voting_schedule_end(mysqli $conn): ?DateTime {
    $result = $conn->query("SELECT end_date, daily_end_time FROM voting_schedule ORDER BY id DESC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        if (!empty($row['end_date']) && !empty($row['daily_end_time'])) {
            return new DateTime($row['end_date'] . ' ' . $row['daily_end_time']);
        }
    }
    return null;
}

/**
 * Fetches the current result publication schedule.
 * @param mysqli $conn The database connection object.
 * @return array The schedule data as an associative array, or an empty array if not set.
 */
function get_result_schedule(mysqli $conn): array {
    $result = $conn->query("SELECT * FROM result_schedule LIMIT 1");
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : [];
}

/**
 * Handles the form submission logic for setting or resetting the schedule.
 * @param mysqli $conn The database connection object.
 * @param DateTime|null $votingEndDateTime The end time of the voting schedule.
 * @return array An array containing the message and message type.
 */
function handle_form_submission(mysqli $conn, ?DateTime $votingEndDateTime): array {
    // --- Handle Reset Request ---
    if (isset($_POST['reset'])) {
        if ($conn->query("TRUNCATE TABLE result_schedule")) {
            return ['text' => '✅ Result schedule has been successfully reset.', 'type' => 'success'];
        }
        return ['text' => '❌ Could not reset the schedule.', 'type' => 'error'];
    }

    // --- Handle Set Schedule Request ---
    $publishDate = $_POST['date'] ?? '';
    $publishTime = $_POST['time'] ?? '';

    if (empty($publishDate) || empty($publishTime)) {
        return ['text' => '❌ Date and time fields are required.', 'type' => 'error'];
    }

    if (!$votingEndDateTime) {
        return ['text' => '❌ Cannot set result schedule because a voting schedule is not set.', 'type' => 'error'];
    }

    $resultPublishDateTime = new DateTime("$publishDate $publishTime");

    if ($resultPublishDateTime <= $votingEndDateTime) {
        $formattedEndTime = $votingEndDateTime->format('M d, Y h:i A');
        return ['text' => "❌ Result time must be after the voting period ends ($formattedEndTime).", 'type' => 'error'];
    }

    // Use a transaction to safely update the schedule
    $conn->begin_transaction();
    try {
        $conn->query("TRUNCATE TABLE result_schedule");
        $stmt = $conn->prepare("INSERT INTO result_schedule (date, time) VALUES (?, ?)");
        $stmt->bind_param("ss", $publishDate, $publishTime);
        $stmt->execute();
        $conn->commit();
        return ['text' => '✅ Result schedule set successfully!', 'type' => 'success'];
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return ['text' => '❌ A database error occurred: ' . $e->getMessage(), 'type' => 'error'];
    }
}


// 2. MAIN LOGIC
// ===================================================================

$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = null;
$votingEndDateTime = get_voting_schedule_end($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = handle_form_submission($conn, $votingEndDateTime);
}

// Always fetch the latest schedule to display in the form
$currentSchedule = get_result_schedule($conn);

?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Panel - Set Result Schedule</title>
<style>
/* Your existing CSS is great, no changes needed */
body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f5f7fa, #c3cfe2); font-family: 'Segoe UI', sans-serif; }
.schedule-container { background: white; padding: 2rem 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
h2 { text-align: center; margin-bottom: 2rem; color: #2c3e50; font-weight: 600; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.form-group { display: flex; flex-direction: column; }
label { margin-bottom: 0.5rem; color: #34495e; font-weight: 500; font-size: 0.9rem; }
input[type="date"], input[type="time"] { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
button { width: 100%; padding: 0.9rem; background: #27ae60; border: none; border-radius: 6px; color: white; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: background 0.3s; }
button:hover { background: #219150; }
button:disabled, button:disabled:hover { background: #bdc3c7; cursor: not-allowed; }
.button-group { display: flex; gap: 1rem; margin-top: 1.5rem; }
button.reset-button { background: #e74c3c; }
button.reset-button:hover { background: #c0392b; }
.message { margin-bottom: 1.5rem; padding: 0.8rem 1rem; border-left: 5px solid; border-radius: 4px; }
.message.success { background: #d4edda; border-color: #28a745; color: #155724; }
.message.error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
</style>
</head>
<body>
<div class="schedule-container">
    <h2>Set Result Schedule</h2>
    
    <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($message['type']); ?>">
            <?php echo htmlspecialchars($message['text']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$votingEndDateTime): ?>
        <div class="message error">
            A complete voting schedule must be set before you can schedule the result publication.
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="date">Publish Date:</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($currentSchedule['date'] ?? ''); ?>" required <?php if (!$votingEndDateTime) echo 'disabled'; ?>>
            </div>
            <div class="form-group">
                <label for="time">Publish Time:</label>
                <input type="time" id="time" name="time" value="<?php echo htmlspecialchars($currentSchedule['time'] ?? ''); ?>" required <?php if (!$votingEndDateTime) echo 'disabled'; ?>>
            </div>
        </div>
        <div class="button-group">
            <button type="submit" <?php if (!$votingEndDateTime) echo 'disabled'; ?>>Set Schedule</button>
            <button type="submit" name="reset" class="reset-button" <?php if (empty($currentSchedule)) echo 'disabled'; ?>>Reset</button>
        </div>
    </form>
</div>
</body>
</html>
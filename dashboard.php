<?php

ini_set('session.cookie_lifetime', 0); // Session ends when the browser closes.
session_start();

// Set a 30-minute inactivity timeout.sandip

$timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Redirect to login if the user is not authenticated.
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username_email = $_SESSION['username']; // User's email is used as the username.

// --- 2. DATABASE CONNECTION ---
$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. CORE LOGIC & DATA FETCHING ---

/**
 * Fetches voting and result schedules to determine the current state of the application.
 * @param mysqli $conn The database connection object.
 * @return array An associative array containing the current status for voting and results.
 */
function get_application_status($conn)
{
    $timezone = new DateTimeZone('Asia/Kolkata');
    $now = new DateTime("now", $timezone);

    // --- Default States ---
    $status = [
        'voting_status' => 'ENDED',
        'voting_target_timestamp' => 0,
        'voting_message' => 'Voting is currently closed.',
        'is_voting_open' => false,
        'result_status' => 'NOT_SET',
        'result_target_timestamp' => 0,
        'result_message' => 'Result publish date is not set.',
        'is_result_published' => false,
    ];

    // --- Voting Status Logic ---
    $schedule_result = $conn->query("SELECT * FROM voting_schedule ORDER BY id DESC LIMIT 1");
    if ($schedule = $schedule_result->fetch_assoc()) {
        $start_date = new DateTime($schedule['start_date'] . ' ' . $schedule['daily_start_time'], $timezone);
        $end_date = new DateTime($schedule['end_date'] . ' ' . $schedule['daily_end_time'], $timezone);
        
        if ($now < $start_date) {
            $status['voting_status'] = 'NOT_YET_STARTED';
            $status['voting_target_timestamp'] = $start_date->getTimestamp();
            $status['voting_message'] = 'Voting starts in: ';
        } elseif ($now > $end_date) {
            $status['voting_status'] = 'ENDED';
            $status['voting_message'] = 'Voting has ended.';
        } else {
            $today_start = new DateTime($now->format('Y-m-d') . ' ' . $schedule['daily_start_time'], $timezone);
            $today_end = new DateTime($now->format('Y-m-d') . ' ' . $schedule['daily_end_time'], $timezone);

            if ($now >= $today_start && $now < $today_end) {
                $status['voting_status'] = 'OPEN';
                $status['voting_target_timestamp'] = $today_end->getTimestamp();
                $status['voting_message'] = 'Voting ends in: ';
                $status['is_voting_open'] = true;
            } elseif ($now < $today_start) {
                $status['voting_status'] = 'CLOSED_TODAY';
                $status['voting_target_timestamp'] = $today_start->getTimestamp();
                $status['voting_message'] = 'Voting opens today in: ';
            } else {
                $tomorrow_start = (clone $today_start)->modify('+1 day');
                if ($tomorrow_start < $end_date) {
                    $status['voting_status'] = 'CLOSED_TODAY';
                    $status['voting_target_timestamp'] = $tomorrow_start->getTimestamp();
                    $status['voting_message'] = 'Voting opens tomorrow in: ';
                } else {
                    $status['voting_status'] = 'ENDED';
                    $status['voting_message'] = 'Voting has ended.';
                }
            }
        }
    }
    
    // --- Result Status Logic ---
    $result_schedule_result = $conn->query("SELECT * FROM result_schedule ORDER BY id DESC LIMIT 1");
    if ($result_schedule = $result_schedule_result->fetch_assoc()) {
        $publish_datetime = new DateTime($result_schedule['date'] . ' ' . $result_schedule['time'], $timezone);
        if ($now < $publish_datetime) {
            $status['result_status'] = 'PENDING';
            $status['result_target_timestamp'] = $publish_datetime->getTimestamp();
            $status['result_message'] = 'Results will be published in: ';
        } else {
            $status['result_status'] = 'PUBLISHED';
            $status['result_message'] = 'Results are now available!';
            $status['is_result_published'] = true;
        }
    }
    
    return $status;
}

// Get the current state of the application (voting open/closed, results published/pending).
$app_status = get_application_status($conn);

// Handle vote submission.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['candidate_id'])) {
    // Double-check on the server side that voting is open.
    if ($app_status['is_voting_open']) {
        $candidate_id = intval($_POST['candidate_id']);

        // Check if the user has already voted.
        $check_stmt = $conn->prepare("SELECT party FROM registration WHERE email = ?");
        $check_stmt->bind_param("s", $username_email);
        $check_stmt->execute();
        $user_vote = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        // If the party field is empty, they haven't voted yet.
        if (empty($user_vote['party'])) {
            // Get the party of the selected candidate
            $cand_stmt = $conn->prepare("SELECT party FROM candidate WHERE id = ?");
            $cand_stmt->bind_param("i", $candidate_id);
            $cand_stmt->execute();
            $candidate = $cand_stmt->get_result()->fetch_assoc();
            $cand_stmt->close();

            if ($candidate) {
                // Record the vote.
                $update_stmt = $conn->prepare("UPDATE registration SET party = ? WHERE email = ?");
                $party_to_save = strtolower($candidate['party']);
                $update_stmt->bind_param("ss", $party_to_save, $username_email);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
    }
    // Redirect to avoid form resubmission.
    header("Location: dashboard.php");
    exit();
}

// Fetch user data (display name and voted party) in a single query.
$stmt = $conn->prepare("SELECT username, party FROM registration WHERE email = ?");
$stmt->bind_param("s", $username_email);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$display_name = $user_data['username'] ?? $username_email;
$voted_party = $user_data['party'] ?? null;

// Get polling station ID from GET request, if available.
$polling_station_id = isset($_GET['polling_station_id']) ? (int)$_GET['polling_station_id'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .party-logo { width: 140px; height: 140px; object-fit: contain; border-radius: 50%; border: 2px solid #000; }
        .timer-container { position: fixed; top: 15px; right: 15px; background: #f8f9fa; padding: 10px 15px; border-radius: 5px; border: 1px solid #dee2e6; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 1050; }
    </style>
</head>
<body>
    <div class="timer-container">
        <strong><span id="voting-timer"></span></strong><br>
        <strong><span id="result-timer"></span></strong>
    </div>

    <div class="container text-center mt-5">
        <h1>Welcome, <?php echo htmlspecialchars($display_name); ?>!</h1>

        <div class="row justify-content-center mt-5">
            <?php
            $parties = [
                ['code' => 'pjb', 'name' => 'PJB', 'logo' => 'assets/img/pjb.png'],
                ['code' => 'cmt', 'name' => 'CMT', 'logo' => 'assets/img/cmt.png'],
                ['code' => 'mpc', 'name' => 'MPC', 'logo' => 'assets/img/mpc.png'],
            ];
            foreach ($parties as $party) { ?>
                <div class="col-md-3 mb-4">
                    <div class="card shadow">
                        <div class="card-body">
                            <img src="<?php echo $party['logo']; ?>" alt="<?php echo $party['name']; ?> Logo" class="party-logo mb-3">
                            <h4 class="card-title"><?php echo $party['name']; ?></h4>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php if (!empty($voted_party)): ?>
            <div class="alert alert-success mt-3">
                ✅ You have already voted for <strong><?php echo strtoupper(htmlspecialchars($voted_party)); ?></strong>.
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <?php if ($app_status['is_result_published']): ?>
                <a href="result.php" class="btn btn-info">View Results</a>
            <?php endif; ?>
            <a href="edit_profile.php" class="btn btn-secondary ms-2">Edit Profile</a>
            <a href="logout.php" class="btn btn-danger ms-2">Logout</a>
        </div>

        <h4 class="mb-3 mt-5">Search Polling Station</h4>
        <div class="mb-3">
            <form method="GET">
                <div class="input-group">
                    <select class="form-select" name="polling_station_id">
                        <option value="">Select Polling Station</option>
                        <?php
                        $stations_result = $conn->query("SELECT id, name FROM polling_station");
                        while ($station = $stations_result->fetch_assoc()) {
                            $selected = ($polling_station_id == $station['id']) ? "selected" : "";
                            echo "<option value='{$station['id']}' $selected>" . htmlspecialchars($station['name']) . "</option>";
                        }
                        ?>
                    </select>
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </div>
            </form>
        </div>

        <?php if ($polling_station_id): ?>
            <?php
            // Fetch station name for the header.
            $station_stmt = $conn->prepare("SELECT name FROM polling_station WHERE id = ?");
            $station_stmt->bind_param("i", $polling_station_id);
            $station_stmt->execute();
            $station_name = $station_stmt->get_result()->fetch_assoc()['name'] ?? "Selected Station";
            $station_stmt->close();

            // Fetch candidates for the selected station.
            $cand_stmt = $conn->prepare("SELECT id, name, picture, party FROM candidate WHERE polling_station_id = ?");
            $cand_stmt->bind_param("i", $polling_station_id);
            $cand_stmt->execute();
            $candidates_result = $cand_stmt->get_result();
            ?>
            <h3 class='mt-5'>Candidates for <?php echo htmlspecialchars($station_name); ?></h3>

            <?php if ($candidates_result->num_rows > 0): ?>
                <h5 class='mb-4'>Select a candidate to vote</h5>
                <table class='table table-bordered mt-4 mb-5'>
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Photo</th><th>Party Logo</th><th>Party</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    while ($candidate = $candidates_result->fetch_assoc()) {
                        $party_logo = "assets/img/" . strtolower($candidate['party']) . ".png";
                        $candidate_photo = !empty($candidate['picture']) ? "admin/" . $candidate['picture'] : "assets/img/default.png";
                    ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($candidate['name']); ?></td>
                            <td><img src="<?php echo htmlspecialchars($candidate_photo); ?>" width='50' height='50' style='object-fit:cover;'></td>
                            <td><img src="<?php echo htmlspecialchars($party_logo); ?>" width='50'></td>
                            <td><?php echo htmlspecialchars(strtoupper($candidate['party'])); ?></td>
                            <td>
                                <?php if (empty($voted_party)): ?>
                                    <form method='POST' style='margin:0;'>
                                        <input type='hidden' name='candidate_id' value='<?php echo $candidate['id']; ?>'>
                                        <button type='submit' class='btn btn-success vote-btn' <?php echo $app_status['is_voting_open'] ? '' : 'disabled'; ?>>Vote</button>
                                    </form>
                                <?php else: ?>
                                    <button class='btn btn-secondary' disabled>Already Voted</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class='mt-4'>No candidates found for this polling station.</p>
            <?php endif; $cand_stmt->close(); ?>
        <?php endif; ?>
    </div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    /**
     * Creates a generic countdown timer.
     * @param {Object} config - The configuration for the timer.
     * @param {string} config.elementId - The ID of the HTML element to update.
     * @param {number} config.targetTimestamp - The Unix timestamp (in seconds) to count down to.
     * @param {string} config.status - The current status ('OPEN', 'PENDING', etc.).
     * @param {string} config.message - The base message to display (e.g., "Voting ends in: ").
     * @param {string} config.endMessage - The message to display when the timer is not active.
     * @param {Function} [config.onTick] - Optional callback function that runs on each tick.
     */
    function createCountdown(config) {
        const timerElement = document.getElementById(config.elementId);
        if (!timerElement) return;

        const targetTime = config.targetTimestamp * 1000;

        if (config.status === 'ENDED' || config.status === 'PUBLISHED' || config.status === 'NOT_SET' || targetTime === 0) {
            const icon = (config.status === 'PUBLISHED' || config.status === 'OPEN') ? '✅' : '❌';
            timerElement.innerHTML = `${icon} ${config.endMessage}`;
            if (config.onTick) config.onTick();
            return;
        }

        const interval = setInterval(() => {
            const timeLeft = targetTime - new Date().getTime();

            if (timeLeft <= 0) {
                clearInterval(interval);
                location.reload(); // Reload the page to get new status from the server
                return;
            }

            const d = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
            const h = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            let countdownText = (d > 0 ? d + "d " : "") + `${h}h ${m}m ${s}s`;
            const icon = (config.status === 'OPEN') ? '✅' : '⏳';
            timerElement.innerHTML = `${icon} ${config.message}${countdownText}`;

            if (config.onTick) config.onTick();

        }, 1000);
    }

    const voteButtons = document.querySelectorAll(".vote-btn");
    
    // --- Initialize Voting Timer ---
    createCountdown({
        elementId: 'voting-timer',
        targetTimestamp: <?php echo $app_status['voting_target_timestamp']; ?>,
        status: "<?php echo $app_status['voting_status']; ?>",
        message: "<?php echo $app_status['voting_message']; ?>",
        endMessage: "<?php echo $app_status['voting_message']; ?>",
        onTick: function() {
            // Enable/disable vote buttons based on the live status from PHP.
            const isVotingOpen = "<?php echo $app_status['voting_status']; ?>" === 'OPEN';
            voteButtons.forEach(btn => btn.disabled = !isVotingOpen);
        }
    });

    // --- Initialize Result Timer ---
    createCountdown({
        elementId: 'result-timer',
        targetTimestamp: <?php echo $app_status['result_target_timestamp']; ?>,
        status: "<?php echo $app_status['result_status']; ?>",
        message: "<?php echo $app_status['result_message']; ?>",
        endMessage: "<?php echo $app_status['result_message']; ?>"
    });
});
</script>

</body>
</html>
<?php
$conn->close();
?>
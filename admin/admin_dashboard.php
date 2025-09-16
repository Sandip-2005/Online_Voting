<?php
session_start();

// 1. INITIALIZATION & SECURITY
// ===================================
if (!isset($_SESSION['username'])) {
    header("Location: admin_login.php");
    exit();
}

// Enable error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database Connection
$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// 2. HELPER FUNCTIONS
// ===================================

/**
 * Handles the logic for adding a new candidate from a POST request.
 * @param mysqli $conn The database connection object.
 * @return array An array with a 'success' or 'error' key and a message.
 */
function handle_add_candidate($conn)
{
    // Basic validation
    $required_fields = ['candidate_name', 'candidate_email', 'candidate_phone', 'party', 'station_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            return ['error' => 'All fields are required.'];
        }
    }
    if (empty($_FILES['candidate_picture']) || $_FILES['candidate_picture']['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Candidate picture is required.'];
    }

    $name       = $_POST['candidate_name'];
    $email      = $_POST['candidate_email'];
    $phone      = $_POST['candidate_phone'];
    $party      = $_POST['party'];
    $station_id = (int)$_POST['station_id'];
    $picture    = $_FILES['candidate_picture'];

    // Check 1: Ensure a party has only one candidate per polling station
    $stmt = $conn->prepare("SELECT id FROM candidate WHERE polling_station_id = ? AND party = ?");
    $stmt->bind_param("is", $station_id, $party);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['error' => 'This party already has a candidate in this polling station.'];
    }
    $stmt->close();

    // Check 2: Check if candidate email is already registered
    $stmt = $conn->prepare("SELECT id FROM candidate WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return ['error' => 'A candidate with this email address already exists.'];
    }
    $stmt->close();

    // Handle File Upload
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $image_info = getimagesize($picture["tmp_name"]);
    if (!$image_info) {
        return ['error' => 'Uploaded file is not a valid image.'];
    }
    $file_extension = strtolower(pathinfo($picture["name"], PATHINFO_EXTENSION));
    $target_file_path = $target_dir . uniqid('cand_', true) . '.' . $file_extension;

    if (!move_uploaded_file($picture["tmp_name"], $target_file_path)) {
        return ['error' => 'Failed to upload candidate picture.'];
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO candidate (name, email, phone, party, polling_station_id, picture) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssis", $name, $email, $phone, $party, $station_id, $target_file_path);
    if ($stmt->execute()) {
        return ['success' => 'Candidate added successfully!'];
    } else {
        return ['error' => 'Database error: Could not add candidate.'];
    }
}

/**
 * Fetches vote counts for all parties in a single, efficient query.
 * @param mysqli $conn The database connection object.
 * @return array An associative array of party codes to vote counts.
 */
function get_vote_counts($conn)
{
    $counts = ['pjb' => 0, 'cmt' => 0, 'mpc' => 0]; // Default counts
    $sql = "SELECT party, COUNT(*) as vote_count FROM registration WHERE party IN ('pjb', 'cmt', 'mpc') GROUP BY party";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $counts[$row['party']] = $row['vote_count'];
    }
    return $counts;
}


// 3. PAGE LOGIC & DATA FETCHING
// ===================================

$feedback_message = null;
// Handle form submission if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_candidate'])) {
    $feedback_message = handle_add_candidate($conn);
}

// Fetch all data needed for the page
$vote_counts = get_vote_counts($conn);
$polling_stations = $conn->query("SELECT id, name FROM polling_station")->fetch_all(MYSQLI_ASSOC);
$all_candidates = $conn->query("SELECT c.*, p.name AS station_name FROM candidate c JOIN polling_station p ON c.polling_station_id = p.id ORDER BY c.name ASC");
$all_users = $conn->query("SELECT * FROM registration ORDER BY username ASC");

// Handle search for candidates by polling station
$searched_candidates = null;
$selected_station_id = null;
if (isset($_GET['polling_station_id']) && !empty($_GET['polling_station_id'])) {
    $selected_station_id = (int)$_GET['polling_station_id'];
    $stmt = $conn->prepare("SELECT c.*, p.name AS station_name FROM candidate c JOIN polling_station p ON c.polling_station_id = p.id WHERE c.polling_station_id = ? ORDER BY c.name ASC");
    $stmt->bind_param("i", $selected_station_id);
    $stmt->execute();
    $searched_candidates = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            font-family: sans-serif;
        }

        .dashboard-card {
            margin-top: 40px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            border: none;
        }

        .party-card {
            transition: transform 0.2s ease-in-out;
        }

        .party-card:hover {
            transform: translateY(-5px);
        }

        .table thead {
            background-color: #0d6efd;
            color: white;
        }

        .table-responsive {
            max-height: 400px;
        }

        img.candidate-pic {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
        }

        .btn-xs {
            --bs-btn-padding-y: .1rem;
            --bs-btn-padding-x: .2rem;
            --bs-btn-font-size: 1.1rem;
        }
    </style>
</head>

<body>

    <div class="container mb-5">
        <div class="dashboard-card bg-white p-4 p-md-5">
            <h1 class="mb-4 text-center text-primary">Admin Dashboard</h1>

            <?php if ($feedback_message): ?>
                <div class="alert <?= isset($feedback_message['success']) ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars(array_values($feedback_message)[0]) ?>
                </div>
            <?php endif; ?>

            <h4 class="mb-4 text-center">Party Vote Counts</h4>
            <div class="row mb-5 justify-content-center">
                <?php foreach ($vote_counts as $code => $count): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card party-card text-center shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-uppercase"><?= htmlspecialchars($code) ?></h5>
                                <p class="display-6 fw-bold text-success"><?= $count ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-center gap-3 mb-5">
                <button type="button" class="btn btn-xs btn-success" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                    <i class="bi bi-person-plus-fill"></i> Add Candidate
                </button>

                <button type="button" class="btn btn-xs btn-info text-white" data-bs-toggle="modal" data-bs-target="#addCountdownModal">
                    <i class="bi bi-hourglass-split"></i> Set Voting Time
                </button>

                <button type="button" class="btn btn-xs btn-primary" data-bs-toggle="modal" data-bs-target="#resultModal">
                    <i class="bi bi-clipboard-check-fill"></i> Set Result Time
                </button>
            </div>

            <h4 class="mb-3 mt-5">All Registered Candidates</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Picture</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Party</th>
                            <th>Polling Station</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($all_candidates->num_rows > 0): $i = 1;
                            while ($row = $all_candidates->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><img src="<?= htmlspecialchars($row['picture']) ?>" class="candidate-pic" alt="Candidate Picture"></td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= strtoupper(htmlspecialchars($row['party'])) ?></td>
                                    <td><?= htmlspecialchars($row['station_name']) ?></td>
                                    <td><a href="edit_candidate.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning text-white">Edit</a></td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No candidates have been registered yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h4 class="mb-3 mt-5">Search Candidates by Polling Station</h4>
            <form method="GET" class="mb-3">
                <div class="input-group">
                    <select class="form-select" name="polling_station_id" onchange="this.form.submit()">
                        <option value="">-- Select a Polling Station --</option>
                        <?php foreach ($polling_stations as $station): ?>
                            <option value="<?= $station['id'] ?>" <?= ($selected_station_id == $station['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($station['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($searched_candidates): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Picture</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Party</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($searched_candidates->num_rows > 0): $i = 1;
                                while ($row = $searched_candidates->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><img src="<?= htmlspecialchars($row['picture']) ?>" class="candidate-pic" alt="Candidate Picture"></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><?= strtoupper(htmlspecialchars($row['party'])) ?></td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No candidates found for this polling station.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h4 class="mb-3 mt-5">Registered Users</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Voter ID</th>
                            <th>Email</th>
                            <th>Voted For</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($all_users->num_rows > 0): $i = 1;
                            while ($row = $all_users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= htmlspecialchars($row['voter_id']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td>
                                        <?php if (!empty($row['party'])): ?>
                                            <span class="badge bg-success"><?= strtoupper(htmlspecialchars($row['party'])) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Voted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="edit_user.php?email=<?= urlencode($row['email']) ?>" class="btn btn-sm btn-warning text-white">Edit</a></td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No users have registered yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCandidateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content"><?php include('add_candidates.php'); ?></div>
        </div>
    </div>
    <div class="modal fade" id="addCountdownModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content"><?php include('countdown.php'); ?></div>
        </div>
    </div>
    <div class="modal fade" id="resultModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content"><?php include('result_countdown.php'); ?></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php $conn->close(); ?>
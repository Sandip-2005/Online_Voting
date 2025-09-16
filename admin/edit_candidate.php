<?php
session_start();

// 1. HELPER FUNCTIONS (The "How")
// ===================================================================

/**
 * Handles the process of uploading a new candidate picture.
 * If successful, it deletes the old picture.
 * @param string $old_picture_path The path to the current picture to be replaced.
 * @return array An array with either a 'path' key on success or an 'error' key on failure.
 */
function handle_picture_upload(string $old_picture_path): array {
    if (!isset($_FILES['candidate_picture']) || $_FILES['candidate_picture']['error'] !== UPLOAD_ERR_OK) {
        return ['path' => $old_picture_path]; // No new file uploaded, return old path
    }

    $file = $_FILES['candidate_picture'];
    $target_dir = "uploads/";

    // Validate if it's a real image
    if (!getimagesize($file["tmp_name"])) {
        return ['error' => 'File is not a valid image.'];
    }

    // Create a unique filename
    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_path = $target_dir . uniqid('candidate_', true) . '.' . $extension;

    // Attempt to move the file
    if (move_uploaded_file($file["tmp_name"], $new_path)) {
        // Success: delete the old picture if it exists and is not a default placeholder
        if (!empty($old_picture_path) && file_exists($old_picture_path)) {
            unlink($old_picture_path);
        }
        return ['path' => $new_path];
    }

    return ['error' => 'Sorry, there was an error uploading your file.'];
}

/**
 * Handles the logic for updating a candidate's details in the database.
 * @param mysqli $conn The database connection.
 * @param int $id The ID of the candidate to update.
 * @return string|null An error message string on failure, or null on success.
 */
function handle_candidate_update(mysqli $conn, int $id): ?string {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $party = trim($_POST['party']);
    $station_id = (int)$_POST['station_id'];

    // First, get the old picture path
    $old_picture_path = $conn->query("SELECT picture FROM candidate WHERE id = $id")->fetch_column();

    // Handle the picture upload
    $upload_result = handle_picture_upload($old_picture_path);
    if (isset($upload_result['error'])) {
        return $upload_result['error']; // Return error message from upload
    }
    $picture_path = $upload_result['path'];

    // Check if another candidate from the same party exists at the new polling station
    $stmt = $conn->prepare("SELECT id FROM candidate WHERE polling_station_id = ? AND party = ? AND id != ?");
    $stmt->bind_param("isi", $station_id, $party, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return 'This polling station already has a candidate for this party!';
    }
    $stmt->close();

    // Proceed with the update
    $stmt = $conn->prepare("UPDATE candidate SET name=?, email=?, phone=?, party=?, polling_station_id=?, picture=? WHERE id=?");
    $stmt->bind_param("ssssisi", $name, $email, $phone, $party, $station_id, $picture_path, $id);
    $stmt->execute();
    
    header("Location: admin_dashboard.php?status=updated");
    exit();
}

/**
 * Handles the logic for deleting a candidate.
 * @param mysqli $conn The database connection.
 * @param int $id The ID of the candidate to delete.
 * @param string $picture_path The path of the candidate's picture file.
 */
function handle_candidate_delete(mysqli $conn, int $id, string $picture_path): void {
    // Delete the picture file from the server
    if (!empty($picture_path) && file_exists($picture_path)) {
        unlink($picture_path);
    }

    // Delete the record from the database
    $stmt = $conn->prepare("DELETE FROM candidate WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: admin_dashboard.php?status=deleted");
    exit();
}


// 2. MAIN SCRIPT LOGIC (The "What")
// ===================================================================

// Use a single database connection
$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch candidate ID from URL
$candidate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$candidate_id) {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle POST requests
$error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        $error_message = handle_candidate_update($conn, $candidate_id);
    } elseif (isset($_POST['delete'])) {
        $candidate_picture = $conn->query("SELECT picture FROM candidate WHERE id = $candidate_id")->fetch_column();
        handle_candidate_delete($conn, $candidate_id, $candidate_picture);
    }
}

// Fetch current candidate data for the form
$stmt = $conn->prepare("SELECT * FROM candidate WHERE id = ?");
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

// Redirect if candidate doesn't exist
if (!$candidate) {
    header("Location: admin_dashboard.php");
    exit();
}

// Fetch all polling stations for the dropdown
$stations = $conn->query("SELECT * FROM polling_station")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Candidate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-container { max-width: 800px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="container form-container">
    <h2 class="mb-4 text-center text-primary">Edit Candidate</h2>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Candidate Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($candidate['name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Candidate Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($candidate['email']); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label">Candidate Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($candidate['phone']); ?>" required pattern="\d{10}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Party</label>
                <select name="party" class="form-select" required>
                    <option value="pjb" <?= $candidate['party'] == 'pjb' ? 'selected' : '' ?>>PJB</option>
                    <option value="cmt" <?= $candidate['party'] == 'cmt' ? 'selected' : '' ?>>CMT</option>
                    <option value="mpc" <?= $candidate['party'] == 'mpc' ? 'selected' : '' ?>>MPC</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Polling Station</label>
                <select name="station_id" class="form-select" required>
                    <?php foreach ($stations as $station): ?>
                        <option value="<?= $station['id']; ?>" <?= $candidate['polling_station_id'] == $station['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($station['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mt-4">
                <label class="form-label">Current Picture</label><br>
                <img src="<?= !empty($candidate['picture']) && file_exists($candidate['picture']) ? htmlspecialchars($candidate['picture']) : '../assets/img/default.png'; ?>" alt="Candidate Picture" width="100" class="img-thumbnail mb-2">
            </div>
            <div class="col-md-6 mt-4">
                <label class="form-label">Upload New Picture (optional)</label>
                <input type="file" name="candidate_picture" class="form-control" accept="image/*">
            </div>
            <div class="col-12 d-flex justify-content-center gap-2 mt-5">
                <button type="submit" name="update" class="btn btn-success">Update Candidate</button>
                <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this candidate? This action cannot be undone.');">Delete Candidate</button>
            </div>
        </div>
    </form>
</div>
</body>
</html>
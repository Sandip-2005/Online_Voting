<?php
session_start();
// Use a single, consistent database connection from an include file
include('../dbconnect.php');

// ===================================================================
//  1. HELPER FUNCTIONS (The "How")
// ===================================================================

/**
 * Fetches a user's complete data by their email address.
 * @param mysqli $conn The database connection.
 * @param string $email The user's unique email.
 * @return array|null The user's data as an array, or null if not found.
 */
function get_user_by_email(mysqli $conn, string $email): ?array {
    $stmt = $conn->prepare("SELECT * FROM registration WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Handles the logic for updating a user's details, identified by their email.
 * @param mysqli $conn The database connection.
 * @param string $email The user's unique email.
 */
function handle_user_update(mysqli $conn, string $email): void {
    $sql = "UPDATE registration SET username=?, voter_id=?, dob=?, gender=?, village=?, post=?, district=?, pincode=?, state=?, phone=? WHERE email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssss", // 11 string placeholders
        $_POST['username'],
        $_POST['voter_id'],
        $_POST['dob'],
        $_POST['gender'],
        $_POST['village'],
        $_POST['post'],
        $_POST['district'],
        $_POST['pincode'],
        $_POST['state'],
        $_POST['phone'],
        $email // The email for the WHERE clause
    );
    $stmt->execute();

    header("Location: admin_dashboard.php?status=user_updated");
    exit();
}

/**
 * Handles the logic for deleting a user, identified by their email.
 * @param mysqli $conn The database connection.
 * @param string $email The user's unique email.
 */
function handle_user_delete(mysqli $conn, string $email): void {
    $stmt = $conn->prepare("DELETE FROM registration WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    header("Location: admin_dashboard.php?status=user_deleted");
    exit();
}

// ===================================================================
//  2. MAIN SCRIPT LOGIC (The "What")
// ===================================================================

// Get user email from the URL and validate it.
$user_email = $_GET['email'] ?? '';
if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle form submission by calling the appropriate function
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update'])) {
        handle_user_update($conn, $user_email);
    } elseif (isset($_POST['delete'])) {
        handle_user_delete($conn, $user_email);
    }
}

// Fetch user data to display in the form
$user = get_user_by_email($conn, $user_email);

// If no user is found with that email, redirect back to the dashboard.
if (!$user) {
    header("Location: admin_dashboard.php?error=user_not_found");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-container { max-width: 900px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container form-container">
        <h2 class="mb-4 text-center text-primary">Edit User: <?= htmlspecialchars($user['username']); ?></h2>
        
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email (Cannot be changed)</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label for="voter_id" class="form-label">Voter ID</label>
                    <input type="text" id="voter_id" name="voter_id" class="form-control" value="<?= htmlspecialchars($user['voter_id']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" id="dob" name="dob" class="form-control" value="<?= htmlspecialchars($user['dob']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label">Gender</label>
                    <select id="gender" name="gender" class="form-select" required>
                        <option value="Male"   <?= $user['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $user['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other"  <?= $user['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="village" class="form-label">Village</label>
                    <input type="text" id="village" name="village" class="form-control" value="<?= htmlspecialchars($user['village']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="post" class="form-label">Post</label>
                    <input type="text" id="post" name="post" class="form-control" value="<?= htmlspecialchars($user['post']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="district" class="form-label">District</label>
                    <input type="text" id="district" name="district" class="form-control" value="<?= htmlspecialchars($user['district']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="pincode" class="form-label">Pincode</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" value="<?= htmlspecialchars($user['pincode']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="state" class="form-label">State</label>
                    <input type="text" id="state" name="state" class="form-control" value="<?= htmlspecialchars($user['state']); ?>" required>
                </div>

                <div class="col-12 text-center mt-4 d-flex justify-content-center gap-2">
                    <button type="submit" name="update" class="btn btn-success">Update User</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to permanently delete this user?');">Delete User</button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
<?php
session_start();
include 'dbconnect.php';

// 1. AUTHENTICATION & SECURITY
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// 2. FETCH USER DATA
$stmt = $conn->prepare("SELECT * FROM registration WHERE email = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: User not found.");
}
$user = $result->fetch_assoc();
$stmt->close();

$error = '';
$success = '';

// 3. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- PART A: Update Basic Profile Information ---
    $username = trim($_POST['username']);
    $dob      = trim($_POST['dob']);
    $gender   = trim($_POST['gender']);
    $village  = trim($_POST['village']);
    $post     = trim($_POST['post']);
    $district = trim($_POST['district']);
    $pincode  = trim($_POST['pincode']);
    $state    = trim($_POST['state']);
    $phone    = trim($_POST['phone']);

    $update_stmt = $conn->prepare(
        "UPDATE registration SET username = ?, dob = ?, gender = ?, village = ?, post = ?, district = ?, pincode = ?, state = ?, phone = ? WHERE id = ?"
    );
    $update_stmt->bind_param(
        "sssssssssi",
        $username, $dob, $gender, $village, $post, $district, $pincode, $state, $phone, $user['id']
    );

    if ($update_stmt->execute()) {
        $success = "✅ Profile details updated successfully!";
        // Refresh user data array after update to show new values in the form.
        $user = array_merge($user, $_POST);
    } else {
        $error = "❌ Error updating profile details.";
    }
    $update_stmt->close();

    // --- PART B: Handle Password Change (Plain Text Method) ---
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        
        // INSECURE: Comparing the submitted password directly with the plain text password from the database.
        if ($current_password !== $user['password']) {
            $error = "❌ Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "❌ New password and confirm password do not match.";
        } else {
            // INSECURE: Storing the new password directly in the database without hashing.
            $pass_stmt = $conn->prepare("UPDATE registration SET password = ? WHERE id = ?");
            $pass_stmt->bind_param("si", $new_password, $user['id']);

            if ($pass_stmt->execute()) {
                $success .= " ✅ Password changed successfully!";
            } else {
                $error = "❌ Error changing password.";
            }
            $pass_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-container { max-width: 700px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-title { text-align: center; margin-bottom: 30px; }
        .form-section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <div class="container form-container">
        <h2 class="form-title">Edit Profile</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="voter_id" class="form-label">Voter ID</label>
                    <input type="text" class="form-control" name="voter_id" id="voter_id" value="<?= htmlspecialchars($user['voter_id']) ?>" readonly>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" name="dob" id="dob" value="<?= htmlspecialchars($user['dob']) ?>" required>
                </div>
            </div>
            <div class="row mb-3">
                 <div class="col-md-6">
                    <label for="gender" class="form-label">Gender</label>
                    <select class="form-select" name="gender" id="gender" required>
                        <option value="Male"   <?= ($user['gender'] ?? '') == 'Male'   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($user['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other"  <?= ($user['gender'] ?? '') == 'Other'  ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" id="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                </div>
            </div>

            <h5 class="mt-4">Address</h5>
            <div class="row mb-3">
                <div class="col-md-6"><label for="village" class="form-label">Village</label><input type="text" class="form-control" name="village" id="village" value="<?= htmlspecialchars($user['village']) ?>" required></div>
                <div class="col-md-6"><label for="post" class="form-label">Post</label><input type="text" class="form-control" name="post" id="post" value="<?= htmlspecialchars($user['post']) ?>" required></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6"><label for="district" class="form-label">District</label><input type="text" class="form-control" name="district" id="district" value="<?= htmlspecialchars($user['district']) ?>" required></div>
                <div class="col-md-3"><label for="pincode" class="form-label">Pincode</label><input type="text" class="form-control" name="pincode" id="pincode" value="<?= htmlspecialchars($user['pincode']) ?>" required></div>
                <div class="col-md-3"><label for="state" class="form-label">State</label><input type="text" class="form-control" name="state" id="state" value="<?= htmlspecialchars($user['state']) ?>" required></div>
            </div>

            <div class="form-section">
                <h5>Change Password</h5>
                <p class="text-muted small">⚠️ Leave these fields blank if you don't want to change your password.</p>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" name="current_password" id="current_password">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" id="new_password">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary px-5">Update Profile</button>
                <a href="dashboard.php" class="btn btn-secondary ms-2">Back to Dashboard</a>
            </div>
        </form>
    </div>
</body>
</html>
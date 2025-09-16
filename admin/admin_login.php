<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['login'])) {
  $new_username = trim($_POST['username']);
  $password = trim($_POST['password']);

  // Corrected SQL query and parameter binding
  $sql = "SELECT * FROM admin WHERE (username = ? OR email = ?) AND password = ?";
  $stmt = $conn->prepare($sql);

  // The same variable ($new_username) is used for both username and email
  $stmt->bind_param("sss", $new_username, $new_username, $password);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $_SESSION['username'] = $user['username'];
    header("Location: admin_dashboard.php");
    exit();
  } else {
    echo "<script>alert('Invalid username/email or password'); window.location='admin_login.php';</script>";
  }

  $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin_login.css"> <!-- fixed path -->
</head>

<body>
  <div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4 rounded-4 login-card">
      <h2 class="text-center mb-4 text-primary"><i class="fas fa-user-shield me-2"></i>Admin Login</h2>

      <form action="admin_login.php" method="POST" novalidate>
        <div class="mb-3">
          <label for="username" class="form-label">Email or Username</label>
          <input type="text" class="form-control" id="username" name="username" placeholder="Enter email or username" required>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
        </div>

        <button type="submit" name="login" class="btn btn-primary w-100">Login</button>

        <div class="text-center mt-3">
          <small>or login as user <a href="../login.php" class="text-decoration-none text-primary">User Login</a></small>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

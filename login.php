<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

  
    $sql = "SELECT * FROM registration 
            WHERE (username = ? OR email = ?) AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        $_SESSION['username'] = $user['email'];

        header("Location: dashboard.php");
        exit();
    } else {
        echo "<script>alert('Invalid username/email or password'); window.location='login.php';</script>";
    }

    $stmt->close();
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/login.css">

</head>
<body>

  <div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg p-4 rounded-4" style="width: 100%; max-width: 400px;">
      <h2 class="text-center mb-4 text-primary"><i class="fas fa-sign-in-alt me-2"></i>Login</h2>

      <form action="login.php" method="POST">
        <div class="mb-3">
          <label for="username" class="form-label"><i class="fas fa-user"></i> Email or Username</label>
          <input type="text" class="form-control" id="username" name="username" required>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <button type="submit" name="login" class="btn btn-primary w-100">Login</button>

        <div class="text-center mt-3">
          <small>Don't have an account? <a href="registration.php" class="text-decoration-none text-primary">Register</a></small><br>
          <small>or</small><br>
          <small>login as admin <a href="admin/admin_login.php" class="text-decoration-none text-primary">Login</a></small>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

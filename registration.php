<?php
session_start();

include('dbconnect.php');

$conn = new mysqli("localhost", "root", "", "assignment");

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name       = $_POST['name'];
  $email      = $_POST['email'];
  $voter_id   = $_POST['voter_id'];
  $dob        = $_POST['dob'];
  $gender     = $_POST['gender'];
  $village    = $_POST['village'];
  $post       = $_POST['post'];
  $district   = $_POST['district'];
  $pincode    = $_POST['pincode'];
  $state      = $_POST['state'];
  $phone      = $_POST['phone'];
  $password   = $_POST['password'];
  $confirm    = $_POST['confirm'];


  if (
    empty($name) || empty($email) || empty($voter_id) ||
    empty($dob) || empty($gender) ||
    empty($village) || empty($post) || empty($district) || empty($pincode) ||
    empty($state) || empty($phone) || empty($password) || empty($confirm)
  ) {
    echo "<script>alert('All fields are required.');</script>";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Invalid email format.');</script>";
  } elseif (!preg_match('/^\d{10}$/', $phone)) {
    echo "<script>alert('Invalid phone number.');</script>";
  } elseif ($password !== $confirm) {
    echo "<script>alert('Passwords do not match.');</script>";
  } else {
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM registration WHERE voter_id = ? OR email = ? OR phone = ?");
    $stmt_check->bind_param("sss", $voter_id, $email, $phone);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
      echo "<script>alert('Voter ID, email, or phone number already exists.');</script>";
    } else {
      // SECURE: Hash the password before storing it
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);

      $sql = "INSERT INTO registration 
                (username, email, voter_id, dob, gender, village, post, district, pincode, state, phone, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssssssssssss", $name, $email, $voter_id, $dob, $gender, $village, $post, $district, $pincode, $state, $phone, $password);

      if ($stmt->execute()) {
        echo "<script>alert('Registration Successful!'); window.location='login.php';</script>";
      } else {
        echo "<script>alert('Error: Could not register user.');</script>";
      }

      $stmt->close();
    }
  }
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registration Page</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- <link rel="stylesheet" href="../assets/css/registration.css"> -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    @keyframes gradientAnimation {
      0% {
        background-position: 0% 50%;
      }

      50% {
        background-position: 100% 50%;
      }

      100% {
        background-position: 0% 50%;
      }
    }

    body {
      min-height: 100vh;
      margin: 0;
      padding: 0;
      background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
      background-size: 400% 400%;
      animation: gradientAnimation 25s ease infinite;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .card {
      background: rgba(255, 255, 255, 0.85);
      border: none;
      border-radius: 1rem;
      backdrop-filter: blur(10px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
      background-color: #e74c3c;
      border: none;
      transition: background-color 0.3s ease;
    }

    .btn-primary:hover {
      background-color: #c0392b;
    }

    input:focus,
    select:focus {
      border-color: #e74c3c;
      box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
    }

    fieldset {
      border: 1px solid #dee2e6;
      padding: 1rem;
      margin-top: 1rem;
      background-color: rgba(248, 249, 250, 0.7);
    }

    legend {
      font-size: 1rem;
      font-weight: 500;
      color: #c0392b;
    }
  </style>
</head>

<body>

  <div class="container d-flex justify-content-center align-items-center min-vh-100 my-5">
    <div class="card p-4 shadow-lg rounded-4" style="width: 100%; max-width: 600px;">
      <h2 class="text-center mb-4 text-primary">Create Account</h2>

      <form id="registrationForm" method="POST" novalidate>
        <div class="row g-3">

          <div class="col-md-6">
            <label for="name" class="form-label"><i class="fas fa-user"></i> Full Name</label>
            <input type="text" class="form-control" id="name" name="name" required>
            <div class="text-danger small mt-1" id="error-name"></div>
          </div>

          <div class="col-md-6">
            <label for="voter_id" class="form-label"><i class="fas fa-id-card"></i> Voter ID</label>
            <input type="text" class="form-control" id="voter_id" name="voter_id" required>
            <div class="text-danger small mt-1" id="error-voter_id"></div>
          </div>

          <div class="col-md-6">
            <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email</label>
            <input type="email" class="form-control" id="email" name="email" required>
            <div class="text-danger small mt-1" id="error-email"></div>
          </div>

          <div class="col-md-6">
            <label for="dob" class="form-label"><i class="fas fa-calendar-alt"></i> Date of Birth</label>
            <input type="date" class="form-control" id="dob" name="dob" required>
            <div class="text-danger small mt-1" id="error-dob"></div>
          </div>

          <div class="col-md-6">
            <label for="gender" class="form-label"><i class="fas fa-venus-mars"></i> Gender</label>
            <select id="gender" name="gender" class="form-select" required>
              <option value="">Select gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
            <div class="text-danger small mt-1" id="error-gender"></div>
          </div>

          <fieldset class="border rounded-3 p-3 mt-3">
            <legend class="float-none w-auto px-2 text-primary fw-semibold"><i class="fas fa-address-book"></i> Address</legend>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="village" class="form-label"><i class="fas fa-building"></i> Village</label>
                <input type="text" class="form-control" id="village" name="village" required>
                <div class="text-danger small mt-1" id="error-village"></div>
              </div>

              <div class="col-md-6">
                <label for="post" class="form-label"><i class="fas fa-mail-bulk"></i> Post</label>
                <input type="text" class="form-control" id="post" name="post" required>
                <div class="text-danger small mt-1" id="error-post"></div>
              </div>

              <div class="col-md-6">
                <label for="district" class="form-label"><i class="fas fa-city"></i> District</label>
                <input type="text" class="form-control" id="district" name="district" required>
                <div class="text-danger small mt-1" id="error-district"></div>
              </div>

              <div class="col-md-6">
                <label for="pincode" class="form-label"><i class="fas fa-thumbtack"></i> Pin Code</label>
                <input type="text" class="form-control" id="pincode" name="pincode" required>
                <div class="text-danger small mt-1" id="error-pincode"></div>
              </div>

              <div class="col-md-12">
                <label for="state" class="form-label"><i class="fas fa-map-marker-alt"></i> State</label>
                <input type="text" class="form-control" id="state" name="state" required>
                <div class="text-danger small mt-1" id="error-state"></div>
              </div>
            </div>
          </fieldset>

          <div class="col-md-12">
            <label for="phone" class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
            <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{10}" title="Please enter a 10-digit phone number">
            <div class="text-danger small mt-1" id="error-phone"></div>
          </div>


          <div class="col-md-6">
            <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
            <div class="text-danger small mt-1" id="error-password"></div>
          </div>
          <div class="col-md-6">
            <label for="confirm" class="form-label"><i class="fas fa-lock"></i> Confirm Password</label>
            <input type="password" class="form-control" id="confirm" name="confirm" required>
            <div class="text-danger small mt-1" id="error-confirm"></div>
          </div>
        </div>

        <div class="mt-4 d-grid">
          <button type="submit" class="btn btn-primary">Register</button>
        </div>

        <div class="text-center mt-3">
          <small class="text-muted">Already have an account? <a href="login.php">Login</a></small>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
      let valid = true;

      const fields = ['name', 'email', 'voter_id', 'dob', 'gender', 'village', 'post', 'district', 'pincode', 'state', 'phone', 'password', 'confirm'];

      fields.forEach(id => {
        const errorDiv = document.getElementById('error-' + id);
        if (errorDiv) errorDiv.innerText = '';
      });

      const getVal = id => document.getElementById(id).value.trim();

      fields.forEach(id => {
        if (!getVal(id)) {
          const errorDiv = document.getElementById('error-' + id);
          if (errorDiv) errorDiv.innerText = 'This field is required';
          valid = false;
        }
      });

      const email = getVal('email');
      if (email && !/^\S+@\S+\.\S+$/.test(email)) {
        document.getElementById('error-email').innerText = 'Enter a valid email';
        valid = false;
      }

      const dobVal = getVal('dob');
      if (dobVal) {
        const dob = new Date(dobVal);
        const today = new Date();
        const age = today.getFullYear() - dob.getFullYear();
        const dob18 = new Date(dob);
        dob18.setFullYear(dob.getFullYear() + 18);
        if (age < 18 || (age === 18 && today < dob18)) {
          document.getElementById('error-dob').innerText = 'You must be at least 18 years old';
          valid = false;
        }
      }

      const phone = getVal('phone');
      if (phone && !/^\d{10}$/.test(phone)) {
        document.getElementById('error-phone').innerText = 'Enter a valid 10-digit phone number';
        valid = false;
      }

      const password = getVal('password');
      const confirm = getVal('confirm');
      if (password && confirm && password !== confirm) {
        document.getElementById('error-confirm').innerText = 'Passwords do not match';
        valid = false;
      }


      if (!valid) {
        e.preventDefault();
      }

    });
  </script>


</body>

</html>
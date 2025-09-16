<?php
session_start();
$conn = new mysqli("localhost", "root", "", "assignment");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$parties = [
    'pjb' => 'PJB',
    'cmt' => 'CMT',
    'mpc' => 'MPC'
];

// Fetch vote counts for all parties
$vote_counts = [];
foreach ($parties as $code => $name) {
    $vote_counts[$name] = 0; // Initialize with 0 votes
}

$sql = "SELECT party, COUNT(*) as count FROM registration WHERE party IS NOT NULL AND party != '' GROUP BY party";
$query_result = $conn->query($sql);

if ($query_result && $query_result->num_rows > 0) {
    while ($row = $query_result->fetch_assoc()) {
        // Check if the party from the DB exists in our defined parties array
        if (isset($parties[$row['party']])) {
            $party_name = $parties[$row['party']];
            $vote_counts[$party_name] = $row['count'];
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Overall Vote Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="mb-4 text-center">Overall Vote Results</h2>
        <table class="table table-bordered table-striped text-center">
            <thead>
                <tr>
                    <th>Party</th>
                    <th>Votes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vote_counts as $party => $count): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($party); ?></td>
                        <td><?php echo $count; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
<?php
include 'dbconnect.php';

header('Content-Type: application/json');

// Query to get incident counts grouped by type
$query = "SELECT incident_type, COUNT(*) AS count FROM incident_reports GROUP BY incident_type";
$result = mysqli_query($conn, $query);

$labels = [];
$counts = [];

while ($row = mysqli_fetch_assoc($result)) {
    $labels[] = $row['incident_type'];
    $counts[] = (int)$row['count'];
}

// Return as JSON
echo json_encode([
    'labels' => $labels,
    'counts' => $counts
]);
?>

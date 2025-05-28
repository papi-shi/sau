<?php
// get_incIDent.php - API endpoint to get incIDent details
require_once 'test.php';

// Check if ID is provIDed
if (!isset($_GET['ID']) || empty($_GET['ID'])) {
    echo json_encode(['error' => 'No incIDent ID provIDed']);
    exit;
}

$ID = intval($_GET['ID']);
$conn = connectDB();

// Prepare and execute query
$query = "SELECT * FROM incIDents WHERE ID = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $ID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'IncIDent not found']);
    exit;
}

// Return incIDent data as JSON
$incIDent = $result->fetch_assoc();
echo json_encode($incIDent);
<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "incident_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM incidents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $incident = $result->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update logic (from the previous code)
}

?>
<!-- Edit form HTML -->
<form method="POST">
    <label>Incident Type:</label>
    <select name="INCIDENT_TYPE" required>
        <option value="IUUF" <?= $incident['INCIDENT_TYPE'] == 'IUUF' ? 'selected' : '' ?>>IUUF</option>
        <option value="ILLEGAL DRUGS" <?= $incident['INCIDENT_TYPE'] == 'ILLEGAL DRUGS' ? 'selected' : '' ?>>ILLEGAL DRUGS</option>
        <!-- Add other options similarly -->
    </select><br><br>
    
    <!-- Other fields here (same as the original form) -->

    <button type="submit" name="update" value="<?= $incident['ID'] ?>">Update Incident</button>
</form>

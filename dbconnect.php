<?php

ob_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "incidents";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        // [Previous POST handling code remains the same...]
    }
}

// Fetch all incidents with all fields - REMOVED image_path if it doesn't exist in your table
$sql = "SELECT id, INCIDENT_TYPE, description, date_time, report, 
       personnel_name, personnel_image, incident_image, location, quantity, 
       value, weight, latitude, longitude, goods_vessel_type, 
       other_vessel_type, port_origin, port_destination, flag_registry, flag_convenience,
       name, age, birth_date, sex, civil_status, 
       citizenship, occupation, status, person_status, updated_at, created_at 
       FROM incidents ORDER BY date_time DESC";
$result = $conn->query($sql);

// Fetch daily incidents - REMOVED image_path
$daily_incidents = [];
$daily_sql = "SELECT id, INCIDENT_TYPE, description, date_time, report, 
       personnel_name, personnel_image, incident_image, location, quantity, 
       value, weight, latitude, longitude, goods_vessel_type, 
       other_vessel_type, port_origin, port_destination, flag_registry, flag_convenience,
       name, age, birth_date, sex, civil_status, 
       citizenship, occupation, status, updated_at, created_at 
       FROM incidents WHERE DATE(date_time) = CURDATE() ORDER BY date_time DESC";
$daily_result = $conn->query($daily_sql);

if ($daily_result === false) {
    die("Error in daily incidents query: " . $conn->error);
}

while ($row = $daily_result->fetch_assoc()) {
    $daily_incidents[] = $row;
}

// Fetch monthly incidents - REMOVED image_path
$monthly_reports = [];
if (isset($_GET['filter']) && isset($_GET['month']) && isset($_GET['year'])) {
    $month = (int)$_GET['month'];
    $year = (int)$_GET['year'];

    $monthly_sql = "SELECT id, INCIDENT_TYPE, description, date_time, report, 
           personnel_name, personnel_image, incident_image, location, quantity, 
           value, weight, latitude, longitude, goods_vessel_type, 
           other_vessel_type, port_origin, port_destination, flag_registry, flag_convenience,
           name, age, birth_date, sex, civil_status, 
           citizenship, occupation, status, updated_at, created_at 
           FROM incidents WHERE MONTH(date_time) = ? AND YEAR(date_time) = ? ORDER BY date_time DESC";
    $stmt = $conn->prepare($monthly_sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $month, $year);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $monthly_result = $stmt->get_result();

    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_reports[] = $row;
    }
} else {
    // If no filter is applied, fetch all incidents for the monthly report - REMOVED image_path
    $all_monthly_sql = "SELECT id, INCIDENT_TYPE, description, date_time, report, 
           personnel_name, personnel_image, incident_image, location, quantity, 
           value, weight, latitude, longitude, goods_vessel_type, 
           other_vessel_type, port_origin, port_destination, flag_registry, flag_convenience,
           name, age, birth_date, sex, civil_status, 
           citizenship, occupation, status, updated_at, created_at 
           FROM incidents ORDER BY date_time DESC";
    $all_monthly_result = $conn->query($all_monthly_sql);
    
    if ($all_monthly_result === false) {
        die("Error in all monthly incidents query: " . $conn->error);
    }
    
    while ($row = $all_monthly_result->fetch_assoc()) {
        $monthly_reports[] = $row;
    }
}

// Fetch chart data
$chart_sql = "SELECT INCIDENT_TYPE, COUNT(*) AS count FROM incidents GROUP BY INCIDENT_TYPE";
$chart_result = $conn->query($chart_sql);
$chart_data = [];
if ($chart_result) {
    while ($row = $chart_result->fetch_assoc()) {
        $chart_data[] = $row;
    }
}

$result = $conn->query("SELECT COUNT(*) AS total_ap FROM incidents ");
if ($result) {
    $row = $result->fetch_assoc();
    $data['ap'] = $row['total_ap'];
    $result->free();
}
// Count LEO's with different statuses
$result = $conn->query("SELECT COUNT(*) AS total_leos FROM incidents");
if ($result) {
    $row = $result->fetch_assoc();
    $data['leos'] = $row['total_leos'];
    $result->free();
}

// Count incidents with status 'FOR FILLING'
$result = $conn->query("SELECT COUNT(*) AS filing_count FROM incidents WHERE status = 'FOR FILLING'");
if ($result) {
    $row = $result->fetch_assoc();
    $data['filing'] = $row['filing_count'];
    $result->free();
}

// Count incidents with status 'FILED'
$result = $conn->query("SELECT COUNT(*) AS filed_count FROM incidents WHERE status = 'FILED'");
if ($result) {
    $row = $result->fetch_assoc();
    $data['filed'] = $row['filed_count'];
    $result->free();
}

// Count incidents with status 'CLOSED'
$result = $conn->query("SELECT COUNT(*) AS closed_count FROM incidents WHERE status = 'CLOSED'");
if ($result) {
    $row = $result->fetch_assoc();
    $data['closed'] = $row['closed_count'];
    $result->free();
}

// Monthly trends data
$monthly_trends_sql = "SELECT YEAR(date_time) AS year, MONTH(date_time) AS month, COUNT(*) AS count FROM incidents GROUP BY YEAR(date_time), MONTH(date_time) ORDER BY YEAR(date_time), MONTH(date_time)";
$monthly_trends_result = $conn->query($monthly_trends_sql);
$months = [];
$incident_counts = [];

if ($monthly_trends_result) {
    while ($row = $monthly_trends_result->fetch_assoc()) {
        $months[] = date('F Y', strtotime("{$row['year']}-{$row['month']}-01"));
        $incident_counts[] = $row['count'];
    }
}

// Status breakdown data
$status_sql = "SELECT status, COUNT(*) AS count FROM incidents GROUP BY status";
$status_result = $conn->query($status_sql);
$status_data = [];
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        $status_data[] = $row;
    }
}


ob_end_flush();
?>
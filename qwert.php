<?php
// config.php - Simple database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'incident_db');
define('UPLOAD_DIR', 'uploads/');

// Start a basic session
session_start();

// Functions
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

function validateImage($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check file type by extension
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array(strtolower($ext), $allowed_extensions)) {
        return "Invalid file type. Only JPG, PNG and GIF are allowed.";
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return "File is too large. Maximum size is 5MB.";
    }
    
    return true;
}

function saveBase64Image($base64data) {
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64data)) {
        return false;
    }
    
    // Create uploads directory if it doesn't exist
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    
    $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64data));
    $filename = UPLOAD_DIR . 'personnel_' . uniqid() . '.png';
    
    if (!file_put_contents($filename, $image_data)) {
        return false;
    }
    
    return $filename;
}

function handleSubmission() {
    $conn = connectDB();
    $errors = [];
    
    // Gather form inputs
    $incident_type = $_POST['INCIDENT_TYPE'] ?? '';
    
    if (isset($_POST['description']) && $_POST['description'] === 'Others') {
        if (isset($_POST['custom_description']) && !empty($_POST['custom_description'])) {
            $description = $_POST['custom_description'];
        } else {
            $errors[] = "Custom description is required.";
        }
    } else if (isset($_POST['description'])) {
        $description = $_POST['description'];
    } else {
        $errors[] = "Description is required.";
    }
    
    if (isset($_POST['date_time']) && !empty($_POST['date_time'])) {
        $date_time = $_POST['date_time'];
        $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $date_time);
        if (!$date_obj) {
            $errors[] = "Invalid date format.";
        } else {
            $date_time = $date_obj->format('Y-m-d H:i:s');
        }
    } else {
        $errors[] = "Date and time are required.";
    }
    
    $report = isset($_POST['report']) ? $_POST['report'] : '';
    $personnel_name = isset($_POST['personnel_name']) ? $_POST['personnel_name'] : '';
    $location = isset($_POST['location']) ? $_POST['location'] : '';
    $lat = isset($_POST['latitude']) ? $_POST['latitude'] : '';
    $lng = isset($_POST['longitude']) ? $_POST['longitude'] : '';
    
    // Validation
    if (empty($incident_type)) {
        $errors[] = "Incident type is required.";
    }
    
    if (empty($report)) {
        $errors[] = "Report details are required.";
    }
    
    if (empty($personnel_name)) {
        $errors[] = "Personnel name is required.";
    }
    
    if (empty($location)) {
        $errors[] = "Location is required.";
    }
    
    if (empty($lat) || empty($lng)) {
        $errors[] = "Please select a location on the map.";
    }
    
    
    if (!empty($errors)) {
        return $errors;
    }
    
    // Create uploads directory if it doesn't exist
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    
    // Process personnel image (webcam)
    $personnel_image_path = "";
    if (isset($_POST['camera_image_data']) && !empty($_POST['camera_image_data'])) {
        $personnel_image_path = saveBase64Image($_POST['camera_image_data']);
        if ($personnel_image_path === false) {
            $errors[] = "Failed to save personnel image.";
        }
    }
    
    // Process incident image (upload)
    $incident_image_path = "";
    if (isset($_FILES['incident_image']) && $_FILES['incident_image']['error'] === UPLOAD_ERR_OK) {
        $validation = validateImage($_FILES['incident_image']);
        if ($validation === true) {
            $tmp = $_FILES['incident_image']['tmp_name'];
            $ext = pathinfo($_FILES['incident_image']['name'], PATHINFO_EXTENSION);
            $filename = UPLOAD_DIR . 'incident_' . uniqid() . '.' . $ext;
            
            if (move_uploaded_file($tmp, $filename)) {
                $incident_image_path = $filename;
            } else {
                $errors[] = "Failed to save incident image: " . error_get_last()['message'];
            }
        } else {
            $errors[] = $validation;
        }
    } else if (isset($_FILES['incident_image'])) {
        $errors[] = "Error uploading incident image: " . $_FILES['incident_image']['error'];
    } else {
        $errors[] = "Incident image is required.";
    }
    
    if (!empty($errors)) {
        return $errors;
    }
    
    // Check if the incidents table exists, if not create it
    $check_table = $conn->query("SHOW TABLES LIKE 'incidents'");
    if ($check_table->num_rows == 0) {
        $create_table = "CREATE TABLE incidents (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            INCIDENT_TYPE VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL,
            date_time DATETIME NOT NULL,
            report TEXT NOT NULL,
            personnel_name VARCHAR(100) NOT NULL,
            personnel_image VARCHAR(255),
            incident_image VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($create_table)) {
            $errors[] = "Failed to create incidents table: " . $conn->error;
            return $errors;
        }
    }
    
    // Insert into database
    try {
        $query = "INSERT INTO incidents (INCIDENT_TYPE, description, date_time, report, personnel_name, personnel_image, incident_image, location, latitude, longitude, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $errors[] = "Prepare failed: " . $conn->error;
            return $errors;
        }
        
        $stmt->bind_param("ssssssssdd", $incident_type, $description, $date_time, $report, $personnel_name, $personnel_image_path, $incident_image_path, $location, $lat, $lng);
        
        if (!$stmt->execute()) {
            $errors[] = "Execute failed: " . $stmt->error;
            return $errors;
        }
        
        if ($stmt->affected_rows > 0) {
            $_SESSION['success_message'] = "Incident report submitted successfully.";
            return [];
        } else {
            $errors[] = "Failed to submit report: No rows affected";
            return $errors;
        }
    } catch (Exception $e) {
        $errors[] = "Database error occurred: " . $e->getMessage();
        return $errors;
    }
}

function handleDelete() {
    $conn = connectDB();
    $errors = [];
    
    $delete_id = isset($_POST['delete_id']) ? $_POST['delete_id'] : null;
    
    if (!$delete_id) {
        $errors[] = "Invalid request: No ID provided.";
        return $errors;
    }
    
    try {
        // Get image paths before deleting
        $stmt = $conn->prepare("SELECT personnel_image, incident_image FROM incidents WHERE id = ?");
        if (!$stmt) {
            $errors[] = "Prepare failed: " . $conn->error;
            return $errors;
        }
        
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM incidents WHERE id = ?");
            if (!$stmt) {
                $errors[] = "Prepare failed: " . $conn->error;
                return $errors;
            }
            
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                // Delete image files
                if (!empty($row['personnel_image']) && file_exists($row['personnel_image'])) {
                    unlink($row['personnel_image']);
                }
                if (!empty($row['incident_image']) && file_exists($row['incident_image'])) {
                    unlink($row['incident_image']);
                }
                
                $_SESSION['success_message'] = "Incident report deleted successfully.";
                return [];
            } else {
                $errors[] = "Failed to delete report. No rows affected.";
                return $errors;
            }
        } else {
            $errors[] = "Incident not found.";
            return $errors;
        }
    } catch (Exception $e) {
        $errors[] = "Database error occurred: " . $e->getMessage();
        return $errors;
    }
}

// New function to get monthly report data
function getMonthlyReportData($month, $year) {
    $conn = connectDB();
    $data = [
        'incidents' => [],
        'total' => 0,
        'by_type' => [],
        'by_description' => [],
        'locations' => []
    ];
    
    // Get all incidents for the month
    $start_date = sprintf("%04d-%02d-01", $year, $month);
    $end_date = date("Y-m-t", strtotime($start_date));
    
    $query = "SELECT * FROM incidents WHERE date_time BETWEEN ? AND ? ORDER BY date_time";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['incidents'][] = $row;
        $data['total']++;
        
        // Count by incident type
        if (!isset($data['by_type'][$row['INCIDENT_TYPE']])) {
            $data['by_type'][$row['INCIDENT_TYPE']] = 0;
        }
        $data['by_type'][$row['INCIDENT_TYPE']]++;
        
        // Count by description
        if (!isset($data['by_description'][$row['description']])) {
            $data['by_description'][$row['description']] = 0;
        }
        $data['by_description'][$row['description']]++;
        
        // Add location data
        $data['locations'][] = [
            'lat' => $row['latitude'],
            'lng' => $row['longitude'],
            'type' => $row['INCIDENT_TYPE'],
            'description' => $row['description'],
            'date' => date('M d, Y h:i A', strtotime($row['date_time']))
        ];
    }
    
    // Get daily distribution
    $data['daily_stats'] = [];
    $current_date = new DateTime($start_date);
    $end_datetime = new DateTime($end_date);
    
    while ($current_date <= $end_datetime) {
        $day = $current_date->format('Y-m-d');
        $data['daily_stats'][$day] = 0;
        $current_date->modify('+1 day');
    }
    
    foreach ($data['incidents'] as $incident) {
        $day = date('Y-m-d', strtotime($incident['date_time']));
        $data['daily_stats'][$day]++;
    }
    
    return $data;
}

// New function to get quarterly report data
function getQuarterlyReportData($quarter, $year) {
    $months = [];
    switch ($quarter) {
        case 1:
            $months = [1, 2, 3];
            break;
        case 2:
            $months = [4, 5, 6];
            break;
        case 3:
            $months = [7, 8, 9];
            break;
        case 4:
            $months = [10, 11, 12];
            break;
    }
    
    $data = [
        'incidents' => [],
        'total' => 0,
        'by_type' => [],
        'by_description' => [],
        'locations' => [],
        'monthly_stats' => [],
        'monthly_breakdown' => []
    ];
    
    foreach ($months as $month) {
        $month_data = getMonthlyReportData($month, $year);
        $data['incidents'] = array_merge($data['incidents'], $month_data['incidents']);
        $data['total'] += $month_data['total'];
        $data['locations'] = array_merge($data['locations'], $month_data['locations']);
        
        // Add monthly statistics
        $month_name = date('F', mktime(0, 0, 0, $month, 1));
        $data['monthly_stats'][$month_name] = $month_data['total'];
        $data['monthly_breakdown'][$month_name] = $month_data['by_type'];
        
        // Merge type and description counts
        foreach ($month_data['by_type'] as $type => $count) {
            if (!isset($data['by_type'][$type])) {
                $data['by_type'][$type] = 0;
            }
            $data['by_type'][$type] += $count;
        }
        
        foreach ($month_data['by_description'] as $desc => $count) {
            if (!isset($data['by_description'][$desc])) {
                $data['by_description'][$desc] = 0;
            }
            $data['by_description'][$desc] += $count;
        }
    }
    
    return $data;
}

// New function to get yearly report data
function getYearlyReportData($year) {
    $data = [
        'incidents' => [],
        'total' => 0,
        'by_type' => [],
        'by_description' => [],
        'locations' => [],
        'monthly_stats' => [],
        'quarterly_stats' => [
            'Q1' => 0,
            'Q2' => 0,
            'Q3' => 0,
            'Q4' => 0
        ],
        'quarterly_breakdown' => [
            'Q1' => [],
            'Q2' => [],
            'Q3' => [],
            'Q4' => []
        ]
    ];
    
    // Get data for each quarter
    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $quarter_data = getQuarterlyReportData($quarter, $year);
        $data['incidents'] = array_merge($data['incidents'], $quarter_data['incidents']);
        $data['total'] += $quarter_data['total'];
        $data['locations'] = array_merge($data['locations'], $quarter_data['locations']);
        
        // Add quarterly statistics
        $q_name = "Q$quarter";
        $data['quarterly_stats'][$q_name] = $quarter_data['total'];
        $data['quarterly_breakdown'][$q_name] = $quarter_data['by_type'];
        
        // Add monthly statistics
        foreach ($quarter_data['monthly_stats'] as $month => $count) {
            $data['monthly_stats'][$month] = $count;
        }
        
        // Merge type and description counts
        foreach ($quarter_data['by_type'] as $type => $count) {
            if (!isset($data['by_type'][$type])) {
                $data['by_type'][$type] = 0;
            }
            $data['by_type'][$type] += $count;
        }
        
        foreach ($quarter_data['by_description'] as $desc => $count) {
            if (!isset($data['by_description'][$desc])) {
                $data['by_description'][$desc] = 0;
            }
            $data['by_description'][$desc] += $count;
        }
    }
    
    return $data;
}

// Process form submissions
$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $errors = handleSubmission();
        if (empty($errors)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } elseif (isset($_POST['delete'])) {
        $errors = handleDelete();
        if (empty($errors)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Create database and table if they don't exist
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (!$conn->query($create_db)) {
    die("Error creating database: " . $conn->error);
}

$conn->select_db(DB_NAME);

// Create incidents table if not exists
$create_table = "CREATE TABLE IF NOT EXISTS incidents (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    INCIDENT_TYPE VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    date_time DATETIME NOT NULL,
    report TEXT NOT NULL,
    personnel_name VARCHAR(100) NOT NULL,
    personnel_image VARCHAR(255),
    incident_image VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($create_table)) {
    die("Error creating table: " . $conn->error);
}

// Handle report generation requests
$view_report = isset($_GET['view_report']) ? $_GET['view_report'] : '';
$report_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$report_quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil(date('n') / 3);
$report_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Check if we need to show a report
$show_report = false;
$report_data = null;
$report_title = '';

if ($view_report == 'monthly') {
    $show_report = true;
    $report_data = getMonthlyReportData($report_month, $report_year);
    $report_title = 'Monthly Report: ' . date('F Y', mktime(0, 0, 0, $report_month, 1, $report_year));
} elseif ($view_report == 'quarterly') {
    $show_report = true;
    $report_data = getQuarterlyReportData($report_quarter, $report_year);
    $report_title = 'Quarterly Report: Q' . $report_quarter . ' ' . $report_year;
} elseif ($view_report == 'yearly') {
    $show_report = true;
    $report_data = getYearlyReportData($report_year);
    $report_title = 'Yearly Report: ' . $report_year;
}

// Regular incident listing with pagination and search
if (!$show_report) {
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Search/Filter
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';

    $conn = connectDB();
    $where_clause = "1=1";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $where_clause .= " AND (description LIKE ? OR report LIKE ? OR personnel_name LIKE ? OR location LIKE ?)";
        $search_param = "%$search%";
        $params[] = &$search_param;
        $params[] = &$search_param;
        $params[] = &$search_param;
        $params[] = &$search_param;
        $types .= "ssss";
    }

    if (!empty($filter_type)) {
        $where_clause .= " AND INCIDENT_TYPE = ?";
        $params[] = &$filter_type;
        $types .= "s";
    }

    // Get incidents count for pagination
    $count_query = "SELECT COUNT(*) FROM incidents WHERE $where_clause";
    $stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_incidents = $stmt->get_result()->fetch_row()[0];
    $total_pages = ceil($total_incidents / $per_page);

    // Get incidents with pagination
    $query = "SELECT * FROM incidents WHERE $where_clause ORDER BY date_time DESC LIMIT ?, ?";
    $stmt = $conn->prepare($query);
    $types .= "ii";
    $params[] = &$offset;
    $params[] = &$per_page;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $incidents = $stmt->get_result();

    // Get incident types for filter dropdown
    $incident_types = $conn->query("SELECT DISTINCT INCIDENT_TYPE FROM incidents ORDER BY INCIDENT_TYPE");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Reporting - Coast Guard Sub-Station Cawit</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        :root {
            --primary-color: #0c1e3c;
            --secondary-color: #ff6f00;
            --text-color: #ffffff;
            --border-color: #ff6f00;
            --background-glass: rgba(255, 255, 255, 0.07);
            --danger-color: #dc3545;
            --success-color: #28a745;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom, var(--primary-color), #00122e);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .header {
            background-color: rgba(0, 0, 0, 0.4);
            padding: 1rem;
            text-align: center;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            width: 60px;
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        h1, h2, h3 {
            color: var(--secondary-color);
            text-shadow: 1px 1px 3px #000;
            margin-bottom: 1rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 992px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: var(--background-glass);
            border-radius: 12px;
            border: 2px solid var(--border-color);
            box-shadow: 0 0 25px rgba(255, 111, 0, 0.4);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #aaa;
            background-color: rgba(255, 255, 255, 0.9);
            color: #333;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 10px 20px;
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #ff8f00;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .map-container {
            width: 100%;
            height: 300px;
            margin: 15px 0;
        }
        
        .camera-box {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        video, .preview-img {
            width: 100%;
            max-height: 300px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background-color: #000;
            margin-bottom: 15px;
        }
        
        .camera-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        th {
            background-color: rgba(0, 0, 0, 0.3);
            color: var(--secondary-color);
        }
        
        tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid var(--border-color);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            margin: 20px 0;
            padding: 0;
        }
        
        .pagination li {
            margin: 0 5px;
        }
        
        .pagination a {
            display: block;
            padding: 8px 12px;
            background-color: var(--background-glass);
            color: var(--text-color);
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background-color: var(--secondary-color);
        }
        
        .alert {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            border: 1px solid var(--danger-color);
            color: #ffcccc;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            border: 1px solid var(--success-color);
            color: #ccffcc;
        }
        
        .hidden {
            display: none;
        }
        
        .search-filter {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .search-filter {
                grid-template-columns: 1fr;
            }
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);overflow-y: auto;
            padding: 60px 0;
        }
        
        .modal-content {
            background: var(--primary-color);
            border: 2px solid var(--border-color);
            box-shadow: 0 0 25px rgba(255, 111, 0, 0.4);
            border-radius: 12px;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: var(--text-color);
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            color: var(--secondary-color);
            cursor: pointer;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            overflow-x: auto;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            margin-right: 5px;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid transparent;
        }
        
        .tab.active {
            background-color: var(--secondary-color);
            border-bottom: none;
            color: white;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .chart-container {
            height: 300px;
            margin: 20px 0;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            padding: 10px;
        }
        
        .map-visualization {
            width: 100%;
            height: 350px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .print-btn {
            position: absolute;
            top: 10px;
            right: 50px;
            color: var(--secondary-color);
            cursor: pointer;
            font-size: 20px;
        }
        
        @media print {
            body {
                background: white;
                color: black;
            }
            
            .chart-container {
                height: auto;
                margin: 10px 0;
                page-break-inside: avoid;
            }
            
            .card {
                border: 1px solid #ccc;
                box-shadow: none;
                background: white;
                color: black;
                page-break-inside: avoid;
            }
            
            .no-print {
                display: none !important;
            }
            
            .header, .modal-close, .print-btn, .tabs {
                display: none;
            }
            
            .modal-content {
                border: none;
                box-shadow: none;
                background: white;
                color: black;
            }
            
            h1, h2, h3 {
                color: #000;
                text-shadow: none;
            }
            
            .tab-content {
                display: block !important;
            }
            
            table {
                border: 1px solid #000;
            }
            
            th, td {
                border: 1px solid #000;
                color: #000;
                background: none;
            }
        }
        
        /* Report styling */
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background-color: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .summary-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .summary-title {
            font-size: 0.9rem;
            color: var(--text-color);
        }
        
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        /* Navigation bar */
        nav {
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px 0;
        }
        
        nav ul {
            display: flex;
            justify-content: center;
            list-style: none;
            flex-wrap: wrap;
        }
        
        nav li {
            margin: 0 15px;
        }
        
        nav a {
            color: var(--text-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        
        nav a:hover {
            color: var(--secondary-color);
        }
        
        nav a i {
            margin-right: 5px;
        }
        
        /* Top incidents list */
        .top-incidents {
            margin-top: 20px;
        }
        
        .incident-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .incident-label {
            width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .incident-bar-outer {
            flex-grow: 1;
            background-color: rgba(255, 255, 255, 0.1);
            height: 20px;
            border-radius: 10px;
            margin: 0 10px;
        }
        
        .incident-bar-inner {
            height: 100%;
            border-radius: 10px;
            background-color: var(--secondary-color);
        }
        
        .incident-value {
            width: 30px;
            text-align: right;
        }
        
        /* Heat calendar for incident distribution */
        .heat-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
            margin: 20px 0;
        }
        
        .heat-day {
            aspect-ratio: 1;
            padding: 5px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: transform 0.2s;
        }
        
        .heat-day:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        /* Incident markers */
        .marker-pin {
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            background: #c30b82;
            position: absolute;
            transform: rotate(-45deg);
            left: 50%;
            top: 50%;
            margin: -15px 0 0 -15px;
        }
        
        .marker-pin::after {
            content: '';
            width: 24px;
            height: 24px;
            margin: 3px 0 0 3px;
            background: #fff;
            position: absolute;
            border-radius: 50%;
        }
        
        .custom-div-icon i {
            position: absolute;
            width: 22px;
            font-size: 14px;
            left: 0;
            right: 0;
            margin: 10px auto;
            text-align: center;
            transform: rotate(45deg);
            color: #333;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="coast_guard_logo.png" alt="Coast Guard Logo" class="logo">
        <h1>Incident Reporting System</h1>
        <h3>Coast Guard Sub-Station Cawit</h3>
    </div>
    
    <nav>
        <ul>
            <li><a href="qwert.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="qwert" id="add-incident-btn"><i class="fas fa-plus-circle"></i> Add Incident</a></li>
            <li><a href="qwert" id="monthly-report-btn"><i class="fas fa-calendar-alt"></i> Monthly Report</a></li>
            <li><a href="qwert" id="quarterly-report-btn"><i class="fas fa-chart-bar"></i> Quarterly Report</a></li>
            <li><a href="qwert" id="yearly-report-btn"><i class="fas fa-chart-line"></i> Yearly Report</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo htmlspecialchars($_SESSION['success_message']); 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Display content based on view -->
        <?php if ($show_report): ?>
            <!-- Report view -->
            <div class="card">
                <h2><?php echo htmlspecialchars($report_title); ?></h2>
                
                <!-- Report summary section -->
                <div class="report-summary">
                    <div class="summary-card">
                        <div class="summary-number"><?php echo $report_data['total']; ?></div>
                        <div class="summary-title">Total Incidents</div>
                    </div>
                    
                    <?php if ($view_report == 'yearly'): ?>
                        <div class="summary-card">
                            <div class="summary-number">
                                <?php 
                                    $max_quarter = array_keys($report_data['quarterly_stats'], max($report_data['quarterly_stats']))[0];
                                    echo $max_quarter;
                                ?>
                            </div>
                            <div class="summary-title">Highest Incident Quarter</div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($report_data['by_type'])): ?>
                        <div class="summary-card">
                            <div class="summary-number">
                                <?php 
                                    $max_type = array_keys($report_data['by_type'], max($report_data['by_type']))[0];
                                    echo htmlspecialchars($max_type);
                                ?>
                            </div>
                            <div class="summary-title">Most Common Incident Type</div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($report_data['by_description'])): ?>
                        <div class="summary-card">
                            <div class="summary-number">
                                <?php 
                                    $max_desc = array_keys($report_data['by_description'], max($report_data['by_description']))[0];
                                    echo htmlspecialchars(substr($max_desc, 0, 15)) . (strlen($max_desc) > 15 ? '...' : '');
                                ?>
                            </div>
                            <div class="summary-title">Most Common Description</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Charts and visualizations -->
                <div class="tabs">
                    <div class="tab active" data-tab="incident-types">Incident Types</div>
                    <div class="tab" data-tab="time-distribution">Time Distribution</div>
                    <div class="tab" data-tab="location-map">Location Map</div>
                    <div class="tab" data-tab="detailed-list">Detailed List</div>
                </div>
                
                <!-- Incident Types Tab -->
                <div class="tab-content active" id="incident-types">
                    <div class="grid">
                        <div class="chart-container">
                            <canvas id="typePieChart"></canvas>
                        </div>
                        <div class="chart-container">
                            <canvas id="descriptionPieChart"></canvas>
                        </div>
                    </div>
                    
                    <h3>Top Incident Types</h3>
                    <div class="top-incidents">
                        <?php 
                            // Sort incidents by frequency
                            arsort($report_data['by_type']);
                            $max_value = max($report_data['by_type']);
                            $i = 0;
                            foreach($report_data['by_type'] as $type => $count):
                                if ($i++ >= 5) break; // Show only top 5
                        ?>
                            <div class="incident-bar">
                                <div class="incident-label"><?php echo htmlspecialchars($type); ?></div>
                                <div class="incident-bar-outer">
                                    <div class="incident-bar-inner" style="width: <?php echo ($count / $max_value * 100); ?>%"></div>
                                </div>
                                <div class="incident-value"><?php echo $count; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Time Distribution Tab -->
                <div class="tab-content" id="time-distribution">
                    <div class="chart-container">
                        <canvas id="timeDistributionChart"></canvas>
                    </div>
                    
                    <?php if ($view_report == 'monthly'): ?>
                        <h3>Daily Incident Distribution</h3>
                        <div class="heat-calendar">
                            <?php 
                                $days_in_month = date('t', mktime(0, 0, 0, $report_month, 1, $report_year));
                                $first_day = date('N', mktime(0, 0, 0, $report_month, 1, $report_year));
                                
                                // Add empty cells for days before month starts
                                for ($i = 1; $i < $first_day; $i++) {
                                    echo '<div></div>';
                                }
                                
                                // Add days of month
                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    $date_key = sprintf('%04d-%02d-%02d', $report_year, $report_month, $day);
                                    $incidents = isset($report_data['daily_stats'][$date_key]) ? $report_data['daily_stats'][$date_key] : 0;
                                    $intensity = min($incidents * 20, 100);
                                    
                                    echo '<div class="heat-day" style="background-color: rgba(255, 111, 0, ' . ($intensity / 100) . ');">' . $day . '<br><small>' . $incidents . '</small></div>';
                                }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Location Map Tab -->
                <div class="tab-content" id="location-map">
                    <div id="report-map" class="map-visualization"></div>
                    
                    <div class="legend">
                        <?php
                            // Color legend for incident types
                            $unique_types = array_keys($report_data['by_type']);
                            $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#2ECC71', '#9B59B6'];
                            
                            for ($i = 0; $i < count($unique_types) && $i < count($colors); $i++):
                        ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: <?php echo $colors[$i]; ?>"></div>
                            <span><?php echo htmlspecialchars($unique_types[$i]); ?></span>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Detailed List Tab -->
                <div class="tab-content" id="detailed-list">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Date/Time</th>
                                    <th>Location</th>
                                    <th>Personnel</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($report_data['incidents'] as $incident): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($incident['INCIDENT_TYPE']); ?></td>
                                    <td><?php echo htmlspecialchars($incident['description']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($incident['date_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                    <td><?php echo htmlspecialchars($incident['personnel_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <button onclick="window.history.back()" class="btn no-print"><i class="fas fa-arrow-left"></i> Back</button>
                <span class="print-btn no-print" onclick="window.print()"><i class="fas fa-print"></i></span>
            </div>
        <?php else: ?>
            <!-- Regular View (Incident Listing) -->
            <div class="search-filter">
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get" id="search-form">
                    <input type="text" name="search" placeholder="Search incidents..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
                
                <select name="filter_type" form="search-form" onchange="document.getElementById('search-form').submit()">
                    <option value="">All Incident Types</option>
                    <?php while ($type = $incident_types->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($type['INCIDENT_TYPE']); ?>" <?php echo ($filter_type == $type['INCIDENT_TYPE']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['INCIDENT_TYPE']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <button type="submit" form="search-form" class="btn"><i class="fas fa-search"></i> Search</button>
            </div>
            
            <div class="card">
                <h2>Incident Reports</h2>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Date/Time</th>
                                <th>Location</th>
                                <th>Personnel</th>
                                <th>Images</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($incidents->num_rows > 0): ?>
                                <?php while ($incident = $incidents->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $incident['ID']; ?></td>
                                        <td><?php echo htmlspecialchars($incident['INCIDENT_TYPE']); ?></td>
                                        <td><?php echo htmlspecialchars($incident['description']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($incident['date_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($incident['location']); ?></td>
                                        <td><?php echo htmlspecialchars($incident['personnel_name']); ?></td>
                                        <td>
                                            <?php if (!empty($incident['personnel_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($incident['personnel_image']); ?>" class="thumb" alt="Personnel">
                                            <?php endif; ?>
                                            <?php if (!empty($incident['incident_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($incident['incident_image']); ?>" class="thumb" alt="Incident">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn view-incident" data-id="<?php echo $incident['ID']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this report?');">
                                                <input type="hidden" name="delete_id" value="<?php echo $incident['ID']; ?>">
                                                <button type="submit" name="delete" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No incidents found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>" 
                               class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add/Edit Incident Modal -->
    <div id="incident-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2>Add New Incident Report</h2>
            
            <form method="post" enctype="multipart/form-data" id="incident-form">
                <input type="hidden" name="add" value="1">
                
                <div class="grid">
                    <div>
                        <div class="form-group">
                            <label for="incident_type">Incident Type</label>
                            <select name="INCIDENT_TYPE" id="incident_type" required>
                                <option value="">Select Type</option>
                                <option value="Maritime Accident">Maritime Accident</option>
                                <option value="Pollution Incident">Pollution Incident</option>
                                <option value="Illegal Fishing">Illegal Fishing</option>
                                <option value="Search and Rescue">Search and Rescue</option>
                                <option value="Maritime Law Violation">Maritime Law Violation</option>
                                <option value="Medical Emergency">Medical Emergency</option>
                                <option value="Natural Disaster">Natural Disaster</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <select name="description" id="description" required>
                                <option value="">Select Description</option>
                                <option value="Vessel Collision">Vessel Collision</option>
                                <option value="Vessel Grounding">Vessel Grounding</option>
                                <option value="Man Overboard">Man Overboard</option>
                                <option value="Oil Spill">Oil Spill</option>
                                <option value="Dynamite Fishing">Dynamite Fishing</option>
                                <option value="Use of Illegal Nets">Use of Illegal Nets</option>
                                <option value="Drowning">Drowning</option>
                                <option value="Storm Damage">Storm Damage</option>
                                <option value="Others">Others (specify)</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="custom_description_group" style="display: none;">
                            <label for="custom_description">Specify Description</label>
                            <input type="text" name="custom_description" id="custom_description">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_time">Date and Time</label>
                            <input type="datetime-local" name="date_time" id="date_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="personnel_name">Reporting Personnel</label>
                            <input type="text" name="personnel_name" id="personnel_name" required>
                        </div>
                    </div>
                    
                    <div>
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" name="location" id="location" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Location on Map</label>
                            <div id="map" class="map-container"></div>
                            <input type="hidden" name="latitude" id="latitude" required>
                            <input type="hidden" name="longitude" id="longitude" required>
                        </div>
                    </div>
                </div>
                
                <div class="grid">
                    <div>
                        <h3>Personnel Image</h3>
                        <div class="camera-box">
                            <video id="video" autoplay style="display: block;"></video>
                            <img id="preview" class="preview-img" style="display: none;">
                            <input type="hidden" name="camera_image_data" id="camera_image_data">
                            
                            <div class="camera-controls">
                                <button type="button" id="start-camera" class="btn"><i class="fas fa-camera"></i> Start Camera</button>
                                <button type="button" id="take-photo" class="btn" disabled><i class="fas fa-camera-retro"></i> Take Photo</button>
                                <button type="button" id="retake-photo" class="btn" disabled><i class="fas fa-redo"></i> Retake</button>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3>Incident Image</h3>
                        <div class="form-group">
                            <input type="file" name="incident_image" id="incident_image" accept="image/*" required>
                            <div id="incident-preview" style="margin-top: 10px;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="report">Detailed Report</label>
                            <textarea name="report" id="report" required></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn"><i class="fas fa-save"></i> Submit Report</button>
            </form>
        </div>
    </div>
    
    <!-- View Incident Modal -->
    <div id="view-incident-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2>Incident Details</h2>
            <div id="incident-details"></div>
            <span class="print-btn" onclick="printIncidentDetails()"><i class="fas fa-print"></i></span>
        </div>
    </div>
    
    <!-- Report Selection Modal -->
    <div id="report-selection-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div id="report-selection-content"></div>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        // Global variables
        let map, marker;
        const defaultLocation = [6.900923, 122.087026]; // Default Zamboanga City coordinates
        let videoStream = null;
        
        // DOM Element References
        document.addEventListener('DOMContentLoaded', function() {
            // Modal handling
            const incidentModal = document.getElementById('incident-modal');
            const viewIncidentModal = document.getElementById('view-incident-modal');
            const reportSelectionModal = document.getElementById('report-selection-modal');
            const closeButtons = document.querySelectorAll('.modal-close');
            
            // Buttons
            const addIncidentBtn = document.getElementById('add-incident-btn');
            const monthlyReportBtn = document.getElementById('monthly-report-btn');
            const quarterlyReportBtn = document.getElementById('quarterly-report-btn');
            const yearlyReportBtn = document.getElementById('yearly-report-btn');
            const viewIncidentBtns = document.querySelectorAll('.view-incident');
            
            // Camera controls
            const video = document.getElementById('video');
            const preview = document.getElementById('preview');
            const startCameraBtn = document.getElementById('start-camera');
            const takePhotoBtn = document.getElementById('take-photo');
            const retakePhotoBtn = document.getElementById('retake-photo');
            const cameraImageData = document.getElementById('camera_image_data');
            
            // File input preview
            const incidentImageInput = document.getElementById('incident_image');
            const incidentPreview = document.getElementById('incident-preview');
            
            // Description handling
            const descriptionSelect = document.getElementById('description');
            const customDescriptionGroup = document.getElementById('custom_description_group');
            
            // Tab handling
            const tabs = document.querySelectorAll('.tab');
            
            // Initialize map if we're on the add incident form// Initialize map if we're on the add incident form
            if (document.getElementById('map')) {
                initMap();
            }
            
            // Initialize report map if we're viewing a report
            if (document.getElementById('report-map')) {
                initReportMap();
            }
            
            // Initialize charts if we're viewing a report
            if (document.getElementById('typePieChart')) {
                initCharts();
            }
            
            // Modal open/close handling
            if (addIncidentBtn) {
                addIncidentBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    incidentModal.style.display = 'block';
                    
                    // Initialize map when modal opens
                    setTimeout(() => {
                        if (map) map.invalidateSize();
                    }, 100);
                });
            }
            
            if (monthlyReportBtn) {
                monthlyReportBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    showReportSelectionModal('monthly');
                });
            }
            
            if (quarterlyReportBtn) {
                quarterlyReportBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    showReportSelectionModal('quarterly');
                });
            }
            
            if (yearlyReportBtn) {
                yearlyReportBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    showReportSelectionModal('yearly');
                });
            }
            
            viewIncidentBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const incidentId = this.getAttribute('data-id');
                    fetchIncidentDetails(incidentId);
                });
            });
            
            closeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.closest('.modal').style.display = 'none';
                    
                    // Stop camera if open
                    if (videoStream) {
                        stopCamera();
                    }
                });
            });
            
            // Close modal if clicking outside the content
            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    e.target.style.display = 'none';
                    
                    // Stop camera if open
                    if (videoStream) {
                        stopCamera();
                    }
                }
            });
            
            // Camera handling
            if (startCameraBtn) {
                startCameraBtn.addEventListener('click', startCamera);
            }
            
            if (takePhotoBtn) {
                takePhotoBtn.addEventListener('click', takePhoto);
            }
            
            if (retakePhotoBtn) {
                retakePhotoBtn.addEventListener('click', retakePhoto);
            }
            
            // File input preview
            if (incidentImageInput) {
                incidentImageInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            incidentPreview.innerHTML = `<img src="${e.target.result}" style="max-width: 100%; max-height: 200px; border-radius: 5px;">`;
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Custom description handling
            if (descriptionSelect) {
                descriptionSelect.addEventListener('change', function() {
                    if (this.value === 'Others') {
                        customDescriptionGroup.style.display = 'block';
                        document.getElementById('custom_description').setAttribute('required', 'required');
                    } else {
                        customDescriptionGroup.style.display = 'none';
                        document.getElementById('custom_description').removeAttribute('required');
                    }
                });
            }
            
            // Tab handling
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Update active tab
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update active content
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');
                    
                    // Refresh map if it's the map tab
                    if (tabId === 'location-map') {
                        setTimeout(() => {
                            if (window.reportMap) window.reportMap.invalidateSize();
                        }, 100);
                    }
                });
            });
        });
        
        // Initialize Leaflet map for incident reporting
        function initMap() {
            map = L.map('map').setView(defaultLocation, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Add marker on click
            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                
                // Update or create marker
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }
                
                // Reverse geocoding to get address
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(response => response.json())
                    .then(data => {
                        const address = data.display_name || `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                        document.getElementById('location').value = address;
                    })
                    .catch(error => {
                        console.error('Error with reverse geocoding:', error);
                        document.getElementById('location').value = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}`;
                    });
            });
        }
        
        // Initialize map for report visualization
        function initReportMap() {
            // Create map
            window.reportMap = L.map('report-map').setView(defaultLocation, 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(window.reportMap);
            
            // Add incident markers from PHP data
            const locationData = <?php echo json_encode($report_data['locations'] ?? []); ?>;
            
            // Define icon colors by incident type
            const uniqueTypes = [...new Set(locationData.map(item => item.type))];
            const typeColors = {};
            const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#2ECC71', '#9B59B6'];
            
            uniqueTypes.forEach((type, index) => {
                typeColors[type] = colors[index % colors.length];
            });
            
            // Add markers
            const markers = [];
            locationData.forEach(location => {
                const color = typeColors[location.type] || '#FF6384';
                
                const incidentIcon = L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div class="marker-pin" style="background-color: ${color};"></div><i class="fa fa-exclamation-triangle"></i>`,
                    iconSize: [30, 42],
                    iconAnchor: [15, 42]
                });
                
                const marker = L.marker([location.lat, location.lng], {icon: incidentIcon})
                    .bindPopup(`
                        <strong>${location.type}</strong><br>
                        ${location.description}<br>
                        <small>${location.date}</small>
                    `);
                
                markers.push(marker);
                marker.addTo(window.reportMap);
            });
            
            // Fit map to markers if there are any
            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                window.reportMap.fitBounds(group.getBounds().pad(0.1));
            }
        }
        
        // Initialize Charts.js visualizations
        function initCharts() {
            // Data preparation for incident type pie chart
            const typeData = <?php echo json_encode($report_data['by_type'] ?? []); ?>;
            const typeLabels = Object.keys(typeData);
            const typeValues = Object.values(typeData);
            const typeColors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', 
                '#FF9F40', '#2ECC71', '#9B59B6', '#607D8B', '#E91E63'
            ];
            
            // Create type pie chart
            const typeCtx = document.getElementById('typePieChart').getContext('2d');
            new Chart(typeCtx, {
                type: 'pie',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        data: typeValues,
                        backgroundColor: typeColors.slice(0, typeLabels.length),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#333'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Incidents by Type',
                            color: '#333',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
            
            // Data preparation for description pie chart
            const descData = <?php echo json_encode($report_data['by_description'] ?? []); ?>;
            const descLabels = Object.keys(descData);
            const descValues = Object.values(descData);
            const descColors = [
                '#4BC0C0', '#FF6384', '#FFCE56', '#36A2EB', '#9966FF', 
                '#2ECC71', '#FF9F40', '#9B59B6', '#607D8B', '#E91E63'
            ];
            
            // Create description pie chart
            const descCtx = document.getElementById('descriptionPieChart').getContext('2d');
            new Chart(descCtx, {
                type: 'pie',
                data: {
                    labels: descLabels,
                    datasets: [{
                        data: descValues,
                        backgroundColor: descColors.slice(0, descLabels.length),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#333'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Incidents by Description',
                            color: '#333',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
            
            // Time distribution chart (line or bar)
            let timeLabels = [];
            let timeData = [];
            let chartTitle = '';
            
            // Different data based on report type
            <?php if ($view_report == 'monthly'): ?>
                timeLabels = <?php 
                    $daily_labels = array_keys($report_data['daily_stats'] ?? []);
                    $formatted_labels = array_map(function($date) {
                        return date('d', strtotime($date));
                    }, $daily_labels);
                    echo json_encode($formatted_labels); 
                ?>;
                timeData = <?php echo json_encode(array_values($report_data['daily_stats'] ?? [])); ?>;
                chartTitle = 'Daily Incident Count';
            <?php elseif ($view_report == 'quarterly'): ?>
                timeLabels = <?php echo json_encode(array_keys($report_data['monthly_stats'] ?? [])); ?>;
                timeData = <?php echo json_encode(array_values($report_data['monthly_stats'] ?? [])); ?>;
                chartTitle = 'Monthly Incident Count';
            <?php elseif ($view_report == 'yearly'): ?>
                timeLabels = ['Q1', 'Q2', 'Q3', 'Q4'];
                timeData = [
                    <?php echo $report_data['quarterly_stats']['Q1'] ?? 0; ?>,
                    <?php echo $report_data['quarterly_stats']['Q2'] ?? 0; ?>,
                    <?php echo $report_data['quarterly_stats']['Q3'] ?? 0; ?>,
                    <?php echo $report_data['quarterly_stats']['Q4'] ?? 0; ?>
                ];
                chartTitle = 'Quarterly Incident Count';
            <?php endif; ?>
            
            // Create time distribution chart
            const timeCtx = document.getElementById('timeDistributionChart').getContext('2d');
            new Chart(timeCtx, {
                type: 'bar',
                data: {
                    labels: timeLabels,
                    datasets: [{
                        label: 'Incidents',
                        data: timeData,
                        backgroundColor: 'rgba(255, 111, 0, 0.5)',
                        borderColor: 'rgba(255, 111, 0, 1)',
                        borderWidth: 1,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#333'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#333'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: chartTitle,
                            color: '#333',
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
        }
        
        // Camera handling functions
        function startCamera() {
            // Set up constraints
            const constraints = {
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            };
            
            // Get video stream
            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    video.srcObject = stream;
                    videoStream = stream;
                    video.style.display = 'block';
                    preview.style.display = 'none';
                    document.getElementById('start-camera').disabled = true;
                    document.getElementById('take-photo').disabled = false;
                    document.getElementById('retake-photo').disabled = true;
                })
                .catch(function(err) {
                    console.error('Error accessing camera:', err);
                    alert('Could not access camera. Please ensure camera permissions are granted.');
                });
        }
        
        function takePhoto() {
            if (!videoStream) return;
            
            // Create canvas and draw video frame
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Get image data and set to preview
            const imageData = canvas.toDataURL('image/png');
            preview.src = imageData;
            video.style.display = 'none';
            preview.style.display = 'block';
            
            // Set hidden input value
            document.getElementById('camera_image_data').value = imageData;
            
            // Update button states
            document.getElementById('take-photo').disabled = true;
            document.getElementById('retake-photo').disabled = false;
        }
        
        function retakePhoto() {
            // Reset preview and camera
            video.style.display = 'block';
            preview.style.display = 'none';
            document.getElementById('camera_image_data').value = '';
            
            // Update button states
            document.getElementById('take-photo').disabled = false;
            document.getElementById('retake-photo').disabled = true;
        }
        
        function stopCamera() {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
                video.srcObject = null;
                
                // Reset button states
                if (document.getElementById('start-camera')) {
                    document.getElementById('start-camera').disabled = false;
                }
                if (document.getElementById('take-photo')) {
                    document.getElementById('take-photo').disabled = true;
                }
                if (document.getElementById('retake-photo')) {
                    document.getElementById('retake-photo').disabled = true;
                }
            }
        }
        
        // Fetch incident details
        function fetchIncidentDetails(id) {
            fetch(`get_incident.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    const modal = document.getElementById('view-incident-modal');
                    const detailsContainer = document.getElementById('incident-details');
                    
                    // Format date
                    const date = new Date(data.date_time);
                    const formattedDate = date.toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Generate HTML for incident details
                    let html = `
                        <div class="card print-friendly">
                            <div class="grid">
                                <div>
                                    <h3>Incident Information</h3>
                                    <p><strong>Type:</strong> ${data.INCIDENT_TYPE}</p>
                                    <p><strong>Description:</strong> ${data.description}</p>
                                    <p><strong>Date/Time:</strong> ${formattedDate}</p>
                                    <p><strong>Location:</strong> ${data.location}</p>
                                    <p><strong>Coordinates:</strong> ${data.latitude}, ${data.longitude}</p>
                                    <p><strong>Reported by:</strong> ${data.personnel_name}</p>
                                </div>
                                <div>
                                    <h3>Images</h3>
                                    <div class="grid">
                                        ${data.personnel_image ? `
                                            <div>
                                                <p><strong>Personnel:</strong></p>
                                                <img src="${data.personnel_image}" style="max-width: 100%; border-radius: 8px;">
                                            </div>
                                        ` : ''}
                                        ${data.incident_image ? `
                                            <div>
                                                <p><strong>Incident:</strong></p>
                                                <img src="${data.incident_image}" style="max-width: 100%; border-radius: 8px;">
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            
                            <h3>Incident Report</h3>
                            <p>${data.report.replace(/\n/g, '<br>')}</p>
                            
                            <div class="map-container" id="incident-detail-map"></div>
                        </div>
                    `;
                    
                    detailsContainer.innerHTML = html;
                    modal.style.display = 'block';
                    
                    // Initialize map for this incident
                    setTimeout(() => {
                        const incidentMap = L.map('incident-detail-map').setView([data.latitude, data.longitude], 15);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(incidentMap);
                        
                        L.marker([data.latitude, data.longitude]).addTo(incidentMap)
                            .bindPopup(`<b>${data.INCIDENT_TYPE}</b><br>${data.description}`).openPopup();
                    }, 100);
                })
                .catch(error => {
                    console.error('Error fetching incident details:', error);
                    alert('Error loading incident details. Please try again.');
                });
        }
        
        // Print incident details
        function printIncidentDetails() {
            window.print();
        }
        
        // Show report selection modal
        function showReportSelectionModal(reportType) {
            const modal = document.getElementById('report-selection-modal');
            const contentContainer = document.getElementById('report-selection-content');
            let title, options;
            
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;
            const currentQuarter = Math.ceil(currentMonth / 3);
            
            switch (reportType) {
                case 'monthly':
                    title = 'Monthly Report';
                    
                    // Generate month options
                    let monthOptions = '';
                    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                        'July', 'August', 'September', 'October', 'November', 'December'];
                    
                    for (let i = 1; i <= 12; i++) {
                        const selected = i === currentMonth ? 'selected' : '';
                        monthOptions += `<option value="${i}" ${selected}>${monthNames[i-1]}</option>`;
                    }
                    
                    options = `
                        <div class="form-group">
                            <label for="report-month">Month:</label>
                            <select id="report-month" name="month">
                                ${monthOptions}
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'quarterly':
                    title = 'Quarterly Report';
                    
                    // Generate quarter options
                    let quarterOptions = '';
                    for (let i = 1; i <= 4; i++) {
                        const selected = i === currentQuarter ? 'selected' : '';
                        quarterOptions += `<option value="${i}" ${selected}>Quarter ${i}</option>`;
                    }
                    
                    options = `
                        <div class="form-group">
                            <label for="report-quarter">Quarter:</label>
                            <select id="report-quarter" name="quarter">
                                ${quarterOptions}
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'yearly':
                    title = 'Yearly Report';
                    options = ''; // No additional options needed
                    break;
                    
                default:
                    return;
            }
            
            // Generate year options (current year and 5 years back)
            let yearOptions = '';
            for (let y = currentYear; y >= currentYear - 5; y--) {
                const selected = y === currentYear ? 'selected' : '';
                yearOptions += `<option value="${y}" ${selected}>${y}</option>`;
            }
            
            const yearSelect = `
                <div class="form-group">
                    <label for="report-year">Year:</label>
                    <select id="report-year" name="year">
                        ${yearOptions}
                    </select>
                </div>
            `;
            
            const html = `
                <h2>${title} Selection</h2>
                <form action="qwert.php" method="get">
                    <input type="hidden" name="view_report" value="${reportType}">
                    ${options}
                    ${yearSelect}
                    <button type="submit" class="btn"><i class="fas fa-chart-line"></i> Generate Report</button>
                </form>
            `;
            
            contentContainer.innerHTML = html;
            modal.style.display = 'block';
        }
    </script>
</body>
</html>
<?php
// Start session for success messages
session_start();

// Buffer output to prevent "headers already sent" errors
ob_start();

// Include your HTML file after starting output buffering
include 'test.html';
include 'qwe.php';  
// config.php - Simple database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'incidents');
define('UPLOAD_DIR', 'uploads/');
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
    
    // Check file type by MIME type
    if (!in_array($file['type'], $allowed_types)) {
        // Fallback to extension check
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($ext, $allowed_extensions)) {
            return "Invalid file type. Only JPG, PNG and GIF are allowed.";
        }
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
    $incident_type = isset($_POST['INCIDENT_TYPE']) ? htmlspecialchars($_POST['INCIDENT_TYPE']) : '';

    if (isset($_POST['description']) && $_POST['description'] === 'Others') {
        if (isset($_POST['custom_description']) && !empty($_POST['custom_description'])) {
            $description = htmlspecialchars($_POST['custom_description']);
        } else {
            $errors[] = "Custom description is required.";
        }
    } else if (isset($_POST['description'])) {
        $description = htmlspecialchars($_POST['description']);
    } else {
        $errors[] = "Description is required.";
    }

      
    
    // Safely fetch and sanitize POST data
    $report = isset($_POST['report']) ? htmlspecialchars(trim($_POST['report']), ENT_QUOTES, 'UTF-8') : '';
    $personnel_name = isset($_POST['personnel_name']) ? htmlspecialchars(trim($_POST['personnel_name']), ENT_QUOTES, 'UTF-8') : '';
    $location = isset($_POST['location']) ? htmlspecialchars(trim($_POST['location']), ENT_QUOTES, 'UTF-8') : '';
    $lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $goods_vessel_type = isset($_POST['goods_vessel_type']) ? htmlspecialchars(trim($_POST['goods_vessel_type']), ENT_QUOTES, 'UTF-8') : '';
    $other_vessel_type = isset($_POST['other_vessel_type']) ? htmlspecialchars(trim($_POST['other_vessel_type']), ENT_QUOTES, 'UTF-8') : '';
    $port_origin = isset($_POST['port_origin']) ? htmlspecialchars(trim($_POST['port_origin']), ENT_QUOTES, 'UTF-8') : '';
    $port_destination = isset($_POST['port_destination']) ? htmlspecialchars(trim($_POST['port_destination']), ENT_QUOTES, 'UTF-8') : '';
    $flag_registry = isset($_POST['flag_registry']) ? htmlspecialchars(trim($_POST['flag_registry']), ENT_QUOTES, 'UTF-8') : '';
    $flag_convenience = isset($_POST['flag_convenience']) ? htmlspecialchars(trim($_POST['flag_convenience']), ENT_QUOTES, 'UTF-8') : '';
    $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : '';
    $age = isset($_POST['age']) ? intval($_POST['age']) : null;
    $birth_date = isset($_POST['birth_date']) ? htmlspecialchars(trim($_POST['birth_date']), ENT_QUOTES, 'UTF-8') : '';
    $sex = isset($_POST['sex']) ? htmlspecialchars(trim($_POST['sex']), ENT_QUOTES, 'UTF-8') : '';
    $civil_status = isset($_POST['civil_status']) ? htmlspecialchars(trim($_POST['civil_status']), ENT_QUOTES, 'UTF-8') : '';
    $citizenship = isset($_POST['citizenship']) ? htmlspecialchars(trim($_POST['citizenship']), ENT_QUOTES, 'UTF-8') : '';
    $occupation = isset($_POST['occupation']) ? htmlspecialchars(trim($_POST['occupation']), ENT_QUOTES, 'UTF-8') : '';
    $occupation = isset($_POST['quantity']) ? htmlspecialchars(trim($_POST['quantity']), ENT_QUOTES, 'UTF-8') : '';
    $occupation = isset($_POST['value']) ? htmlspecialchars(trim($_POST['value']), ENT_QUOTES, 'UTF-8') : '';
    $occupation = isset($_POST['weight']) ? htmlspecialchars(trim($_POST['weight']), ENT_QUOTES, 'UTF-8') : '';

    // FIX: Add date_time value - either from the form or use current timestamp
    $date_time = isset($_POST['date_time']) ? htmlspecialchars(trim($_POST['date_time']), ENT_QUOTES, 'UTF-8') : date('Y-m-d H:i:s');
    
    // Current timestamp for updated_at
    $current_timestamp = date('Y-m-d H:i:s');
    
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
    } else if (isset($_FILES['incident_image']) && $_FILES['incident_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Error uploading incident image: " . $_FILES['incident_image']['error'];
    } else if (!isset($_FILES['incident_image']) || $_FILES['incident_image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Incident image is required.";
    }
    
    if (!empty($errors)) {
        return $errors;
    }
    
    // Insert into database
    try {
        // Count all the parameters to make sure they match 
        // We need 24 parameters for the bind_param
        $query = "INSERT INTO incidents (INCIDENT_TYPE, description, date_time, report, personnel_name, personnel_image, incident_image, location, latitude, longitude, 
        goods_vessel_type, other_vessel_type, port_origin, port_destination, flag_registry, flag_convenience, name, age, birth_date, sex, civil_status, citizenship, 
        occupation, quantity, value, weight, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $errors[] = "Prepare failed: " . $conn->error;
            return $errors;
        }
        $stmt->bind_param(
            "sssssssssssssssssssssssssss", 
            $incident_type, 
            $description, 
            $date_time, 
            $report, 
            $personnel_name, 
            $personnel_image_path, 
            $incident_image_path, 
            $location, 
            $lat, 
            $lng, 
            $goods_vessel_type, 
            $other_vessel_type, 
            $port_origin, 
            $port_destination, 
            $flag_registry, 
            $flag_convenience, 
            $name, 
            $age, 
            $birth_date, 
            $sex, 
            $civil_status, 
            $citizenship, 
            $occupation, 
            $quantity,
            $value,
            $weight,
            $current_timestamp
        );
        
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
    
    $delete_id = isset($_POST['delete_id']) ? intval($_POST['delete_id']) : null;
    
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
    
// Query for human trafficking incidents only, WITH date filter
$query = "SELECT * FROM incidents WHERE INCIDENT_TYPE = 'ILLEGAL DRUGS' AND date_time BETWEEN ? AND ? ORDER BY date_time DESC";
$stmt = $conn->prepare($query);

// Bind the two parameters: start_date and end_date
$stmt->bind_param("ss", $start_date, $end_date);

// Execute the prepared statement
$stmt->execute();

// Get the result set
$result = $stmt->get_result();

// Initialize data array
$data = [
    'incidents' => [],
    'total' => 0,
    'by_type' => [],
    'by_description' => [],
    'locations' => [],
    'daily_stats' => [],
    'age_groups' => [
        '10-17' => 0,
        '18-30' => 0,
        '31-50' => 0,
        '51-70' => 0,
        '71-80' => 0,
        '81-100' => 0,
        'Unknown' => 0
    ]
];

// Fetch all incidents
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

    // --- New Feature: Count based on Victim Age Group ---
    if (isset($row['victim_age']) && is_numeric($row['victim_age'])) {
        $age = (int)$row['victim_age'];

        if ($age >= 10 && $age <= 17) {
            $data['age_groups']['10-17']++;
        } elseif ($age >= 18 && $age <= 30) {
            $data['age_groups']['18-30']++;
        } elseif ($age >= 31 && $age <= 50) {
            $data['age_groups']['31-50']++;
        } elseif ($age >= 51 && $age <= 70) {
            $data['age_groups']['51-70']++;
        } elseif ($age >= 71 && $age <= 80) {
            $data['age_groups']['71-80']++;
        } elseif ($age >= 81 && $age <= 100) {
            $data['age_groups']['81-100']++;
        } else {
            $data['age_groups']['Unknown']++;
        }
    } else {
        $data['age_groups']['Unknown']++;
    }
}

// Generate daily stats between start_date and end_date
$current_date = new DateTime($start_date);
$end_datetime = new DateTime($end_date);

while ($current_date <= $end_datetime) {
    $day = $current_date->format('Y-m-d');
    $data['daily_stats'][$day] = 0;
    $current_date->modify('+1 day');
}

// Fill daily stats
foreach ($data['incidents'] as $incident) {
    $day = date('Y-m-d', strtotime($incident['date_time']));
    if (isset($data['daily_stats'][$day])) {
        $data['daily_stats'][$day]++;
    }
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
        default:
            $months = [1, 2, 3]; // Default to Q1
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
            // Clear any output buffers before redirecting
            ob_end_clean();
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        }
    } elseif (isset($_POST['delete'])) {
        $errors = handleDelete();
        if (empty($errors)) {
            // Clear any output buffers before redirecting
            ob_end_clean();
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
$create_db = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`";
if (!$conn->query($create_db)) {
    die("Error creating database: " . $conn->error);
}

$conn->select_db(DB_NAME);

// Add missing table creation query
$create_table = "CREATE TABLE IF NOT EXISTS `incidents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `INCIDENT_TYPE` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `date_time` DATETIME NOT NULL,
    `report` TEXT NOT NULL,
    `personnel_name` VARCHAR(255) NOT NULL,
    `personnel_image` VARCHAR(255) DEFAULT NULL,
    `incident_image` VARCHAR(255) DEFAULT NULL,
    `location` VARCHAR(255) NOT NULL,
    `latitude` DECIMAL(10,8) NOT NULL,
    `longitude` DECIMAL(11,8) NOT NULL,
    `goods_vessel_type` VARCHAR(255) DEFAULT NULL,
    `other_vessel_type` VARCHAR(255) DEFAULT NULL,
    `port_origin` VARCHAR(255) DEFAULT NULL,
    `port_destination` VARCHAR(255) DEFAULT NULL,
    `flag_registry` VARCHAR(255) DEFAULT NULL,
    `flag_convenience` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `age` VARCHAR(10) DEFAULT NULL,
    `birth_date` VARCHAR(50) DEFAULT NULL,
    `sex` VARCHAR(50) DEFAULT NULL,
    `civil_status` VARCHAR(50) DEFAULT NULL,
    `citizenship` VARCHAR(255) DEFAULT NULL,
    `occupation` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (!$conn->query($create_table)) {
    die("Error creating table: " . $conn->error);
}

    // Define age groups
    $ageGroups = [
        '10-17' => ['min' => 10, 'max' => 17],
        '18-25' => ['min' => 18, 'max' => 25],
        '26-35' => ['min' => 26, 'max' => 35],
        '36-50' => ['min' => 36, 'max' => 50],
        '51-70' => ['min' => 51, 'max' => 70],
        '71-85' => ['min' => 71, 'max' => 85],
        '86-100' => ['min' => 86, 'max' => 100]
    ];

    // Initialize counters for each age group
    $ageStats = [];
    foreach ($ageGroups as $group => $range) {
        $ageStats[$group] = 0;
    }

// Query to get all incidents with age information
$query = "SELECT age FROM incidents WHERE INCIDENT_TYPE = 'ILLEGAL DRUGS' AND age IS NOT NULL";
$result = $conn->query($query);

// Count incidents by age group
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $age = intval($row['age']);
        
        foreach ($ageGroups as $group => $range) {
            if ($age >= $range['min'] && $age <= $range['max']) {
                $ageStats[$group]++;
                break;
            }
        }
    }
}

// Prepare data for the chart
$chartLabels = array_keys($ageStats);
$chartData = array_values($ageStats);

// Convert to JSON for JavaScript
$chartLabelsJSON = json_encode($chartLabels);
$chartDataJSON = json_encode($chartData);

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
    $search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
    $filter_type = isset($_GET['filter_type']) ? htmlspecialchars($_GET['filter_type']) : '';

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


// Get overall totals
$queryStats = "SELECT 
    SUM(CAST(quantity AS DECIMAL(10,2))) AS total_quantity, 
    SUM(CAST(value AS DECIMAL(15,2))) AS total_value, 
    SUM(CAST(weight AS DECIMAL(10,2))) AS total_weight 
    FROM incidents 
    WHERE INCIDENT_TYPE = 'ILLEGAL DRUGS'";
$resultStats = $conn->query($queryStats);
$stats = $resultStats->fetch_assoc();

// Get total value per description
$queryPerDescription = "SELECT description, SUM(CAST(value AS DECIMAL(15,2))) AS total_value 
                        FROM incidents 
                        WHERE INCIDENT_TYPE = 'ILLEGAL DRUGS' 
                        GROUP BY description 
                        ORDER BY total_value DESC";
$resultPerDescription = $conn->query($queryPerDescription);

$descLabels = [];
$descValues = [];

while ($row = $resultPerDescription->fetch_assoc()) {
    $descLabels[] = $row['description'];
    $descValues[] = (float)$row['total_value'];
}

$totalEstimatedValue = array_sum($descValues);

// Get top 5 frequent locations
$queryFreq = "SELECT location, COUNT(*) as count 
              FROM incidents 
              WHERE INCIDENT_TYPE = 'ILLEGAL DRUGS' 
              GROUP BY location 
              ORDER BY count DESC 
              LIMIT 5";
$resultFreq = $conn->query($queryFreq);
$locations = [];
$counts = [];
while ($row = $resultFreq->fetch_assoc()) {
    $locations[] = $row['location'];
    $counts[] = $row['count'];
}
// Flush the output buffer at the end of the script
ob_end_flush();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Incident Report Charts</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
            margin-bottom: 40px;
            background-color: black;
            height: 950px;
            width: 85%;
            align-items: center;
            margin-left: 8.5%;
        }
        .chart-box {
            flex: 1 1 calc(33% - 20px);
            min-width: 300px;
            border: 1px solid #ccc;
            padding: 50px;
            border-radius: 8px;
            background: #f9f9f9;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        canvas {
            width: 100% !important;
            height: 300px !important;
        }
        h3 {
            text-align: center;
            margin-bottom: 10px;
        }
        h2{
            margin-left: 35%;
        }
    </style>
</head>
<body>

<h2>Summary Charts (ILLEGAL DRUGS Incidents)</h2>
<div class="chart-container" style="display: flex; flex-wrap: wrap; gap: 20px;">
    <div class="chart-box" style="width: 300px;">
        <h3>Total Value (₱)</h3>
        <canvas id="valueChart"></canvas>
    </div>
    <div class="chart-box" style="width: 300px;">
        <h3>Total Quantity (pcs)</h3>
        <canvas id="quantityChart"></canvas>
    </div>
    <div class="chart-box" style="width: 300px;">
        <h3>Total Weight (kg)</h3>
        <canvas id="weightChart"></canvas>
    </div>
    <div class="chart-box" style="width: 100%;">
        <h3>Estimated Value (₱)</h3>
        <p style="text-align: center; font-size: 18px; margin-top: 10px; color: #000;">
            <strong>Total Estimated Value:</strong> ₱ <?php echo number_format($totalEstimatedValue, 2); ?>
            <canvas id="valuePerDescriptionChart"></canvas>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const totalValue = <?php echo $stats['total_value'] ?? 0; ?>;
const totalQuantity = <?php echo $stats['total_quantity'] ?? 0; ?>;
const totalWeight = <?php echo $stats['total_weight'] ?? 0; ?>;

const descLabels = <?php echo json_encode($descLabels); ?>;
const descValues = <?php echo json_encode($descValues); ?>;

new Chart(document.getElementById('valueChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Value (₱)'],
        datasets: [{
            label: 'Total Value',
            data: [totalValue],
            backgroundColor: '#FF6347',
            borderColor: '#B22222',
            borderWidth: 2
        }]
    },
    options: {
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return '₱ ' + ctx.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱ ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

new Chart(document.getElementById('quantityChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Quantity (pcs)'],
        datasets: [{
            label: 'Total Quantity',
            data: [totalQuantity],
            backgroundColor: '#4682B4',
            borderColor: '#1E90FF',
            borderWidth: 2
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' pcs';
                    }
                }
            }
        }
    }
});

new Chart(document.getElementById('weightChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Weight (kg)'],
        datasets: [{
            label: 'Total Weight',
            data: [totalWeight],
            backgroundColor: '#32CD32',
            borderColor: '#228B22',
            borderWidth: 2
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' kg';
                    }
                }
            }
        }
    }
});

// New bar chart for value per description
new Chart(document.getElementById('valuePerDescriptionChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: descLabels,
        datasets: [{
            label: 'Total Value (₱)',
            data: descValues,
            backgroundColor: '#8A2BE2',
            borderColor: '#4B0082',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return '₱ ' + ctx.parsed.y.toLocaleString();
                    }
                }
            },
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                ticks: {
                    autoSkip: false,
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱ ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>
<!DOCTYPE html>
<html lang="en">

<body>
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
                
                
            </div>
        <?php else: ?>
            <!-- Regular View (Incident Listing) -->
           <div style="border: 3px solid black; margin-bottom: 50px;">

           </div>
               <!-- PRINT BUTTON -->
<button onclick="printIncidentReport()" class="btn btn-primary" style="margin-bottom: 10px;">
    <i class="fas fa-print"></i> Print Report
</button>
 
    
    <script>
        // Create the bar chart using Chart.js
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('ageChart').getContext('2d');
            
            const ageChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo $chartLabelsJSON; ?>,
                    datasets: [{
                        label: 'Number of Human Trafficking Incidents',
                        data: <?php echo $chartDataJSON; ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Age Group (years)'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Human Trafficking Incidents by Age Group',
                            font: {
                                size: 18
                            }
                        },
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
<div>

<div>
    
</div>
    
</div>
<!-- WRAPPED CONTENT FOR PRINTING -->
<div id="printable-report">
<div class="table-responsive">
    <h2>All Incidents</h2>
    <table class="table table-striped table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>Incident Type</th>
                <th>Description</th>
                <th>Date & Time</th>
                <th>Location</th>
                <th>Reporting Personnel</th>
                <th>Images</th>
                <th>quantity</th>
                <th>value</th>
                <th>weight</th>
                <th>actions</th>
                   
            </tr>
        </thead>
        <tbody>
            <?php
            // Query for all incidents
            
$query = "SELECT * FROM incidents WHERE INCIDENT_TYPE = 'ILLEGAL DRUGS' ORDER BY date_time DESC";
$incidents = $conn->query($query);
            
            if ($incidents->num_rows > 0):
                while ($incident = $incidents->fetch_assoc()):
            ?>
                <tr>
                    <td><?php echo $incident['id']; ?></td>
                    <td><?php echo htmlspecialchars($incident['INCIDENT_TYPE']); ?></td>
                    <td><?php echo htmlspecialchars($incident['description']); ?></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($incident['date_time'])); ?></td>
                    <td>
                        <?php echo htmlspecialchars($incident['location']); ?>
                        <br>
                        <small>
                            <a href="https://maps.google.com/?q=<?php echo $incident['latitude']; ?>,<?php echo $incident['longitude']; ?>" target="_blank">
                                View on Map <i class="fas fa-map-marker-alt"></i>
                            </a>
                        </small>
                    </td>
                    <td><?php echo htmlspecialchars($incident['personnel_name']); ?></td>
                    <td>
                        <div class="image-thumbnails">
                            <?php if (!empty($incident['personnel_image'])): ?>
                                <a href="<?php echo htmlspecialchars($incident['personnel_image']); ?>" data-lightbox="images-<?php echo $incident['id']; ?>" data-title="Personnel Image">
                                    <img src="<?php echo htmlspecialchars($incident['personnel_image']); ?>" class="thumb" alt="Personnel">
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($incident['incident_image'])): ?>
                                <a href="<?php echo htmlspecialchars($incident['incident_image']); ?>" data-lightbox="images-<?php echo $incident['id']; ?>" data-title="Incident Image">
                                    <img src="<?php echo htmlspecialchars($incident['incident_image']); ?>" class="thumb" alt="Incident">
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($incident['quantity'] ?? 'N/A'); ?></td>
    <td><?php echo htmlspecialchars($incident['value'] ?? 'N/A'); ?></td>
    <td><?php echo htmlspecialchars($incident['weight'] ?? 'N/A'); ?></td>
    
    
                    <td>
                        <div class="btn-group">
                       <button 
  type="button" 
  class="btn btn-info btn-sm view-details"
  data-id="<?php echo $incident['id']; ?>"
  data-incident-image="<?php echo htmlspecialchars($incident['incident_image']); ?>"
  data-personnel-image="<?php echo htmlspecialchars($incident['personnel_image']); ?>"
  data-quantity="<?php echo htmlspecialchars($incident['quantity'] ?? 'N/A'); ?>"
  data-value="<?php echo htmlspecialchars($incident['value'] ?? 'N/A'); ?>"
  data-weight="<?php echo htmlspecialchars($incident['weight'] ?? 'N/A'); ?>"
  data-report="<?php echo htmlspecialchars($incident['report'] ?? 'No report available.'); ?>"
>
<i class="fas fa-eye"></i> View Details
                               </button>
                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this report?');">
                                <input type="hidden" name="delete_id" value="<?php echo $incident['id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php 
                endwhile; 
            else: 
            ?>
                <tr>
                    <td colspan="8" class="text-center">No incidents found</td>
                </tr>
            <?php endif; ?>

            <!-- Modal -->
<!-- Modal -->
<div id="profileModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>

    <div class="profile-header">
      <h2>Details</h2>
    </div>

    <div class="profile-body">
      <!-- LEFT SIDE: Images -->
      <div class="profile-photos">
        <div class="photo-block">
          <h3>Evidence Image</h3>
          <img id="incidentImage" src="" alt="Incident Image">
        </div>
        
        <div class="photo-block">
          <h3>Reporting Personnel</h3>
          <img id="personnelImage" src="" alt="Personnel Image">
        </div>
      </div>

      <!-- RIGHT SIDE: Details -->
      <di class="profile-details">
        <p><strong>quantity:</strong> <span id="profilequantity"></span></p>
        <p><strong>value:</strong> <span id="profilevalue"></span></p>
        <p><strong>weight:</strong> <span id="profileweight"></span></p>

        <!-- REPORT SECTION -->
        <div class="report-section">
          <h3>Report</h3>
          <p id="profileReport"></p>
        </div>
        <div>
                
        </div>
      </div>
    </div>
  </div>
</div>


        </tbody>
    </table>
    <!-- Modal Structure -->

</div>
<style>
.modal {
  display: none;
  position: fixed;
  z-index: 999;
  padding-top: 50px;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.8);
}
.modal-content {
  background: #f9f9f9;
  margin: auto;
  padding: 40px;
  border: 2px solid #000;
  width: 800px;
  font-family: 'Georgia', serif;
  color: #333;
  position: relative;
}
.close {
  color: #aaa;
  position: absolute;
  right: 20px;
  top: 10px;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}
.close:hover,
.close:focus {
  color: black;
}
.profile-header h2 {
  text-align: center;
  font-size: 32px;
  margin-bottom: 20px;
  border-bottom: 2px solid black;
  display: inline-block;
  padding-bottom: 5px;
}
.profile-body {
  display: flex;
  gap: 30px;
  margin-top: 20px;
}
.profile-photos {
  flex: 0 0 250px;
}
.photo-block {
  margin-bottom: 20px;
  text-align: center;
}
.photo-block img {
  width: 100%;
  height: auto;
  border: 2px solid black;
  object-fit: cover;
}
.profile-details {
  flex: 1;
  font-size: 18px;
  line-height: 1.6;
}
.profile-details p {
  margin: 5px 0;
}
.report-section {
  margin-top: 20px;
  padding: 10px;
  background-color: #eee;
  border: 1px solid #ccc;
  max-height: 200px;
  overflow-y: auto;
}
.report-section h3 {
  margin-top: 0;
  font-size: 20px;
  text-align: center;
}
.view-button {
  background-color: orange;
  color: white;
  border: none;
  padding: 10px 20px;
  margin: 30px;
  cursor: pointer;
  font-size: 18px;
  border-radius: 5px;
}
.view-button:hover {
  background-color: darkorange;
}
</style>



<!-- Add CSS for thumbnail styling -->
<style>

    
    .thumb {
        width: 50px;
        height: 50px;
        object-fit: cover;
        margin: 2px;
        border: 1px solid #ddd;
        border-radius: 4px;
        transition: transform 0.2s ease;
    }
    
    .thumb:hover {
        transform: scale(1.1);
    }
    
    .image-thumbnails {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .btn-group {
        display: flex;
        gap: 5px;
    }
</style>

<!-- Modals for incident details (placed outside the loop) -->
<?php
// Reset the data pointer to the beginning of the result set
if ($incidents->num_rows > 0) {
    // If using mysqli, reset the internal pointer of the result set
    $incidents->data_seek(0);
    
    // Loop through results again to create modals outside of table
    while ($incident = $incidents->fetch_assoc()):
?>
<div class="modal fade" id="detailsModal<?php echo $incident['ID']; ?>" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel<?php echo $incident['ID']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel<?php echo $incident['ID']; ?>">
                    Incident Details #<?php echo $incident['ID']; ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-bordered">
                            <tr>
                                <th>Incident Type</th>
                                <td><?php echo htmlspecialchars($incident['INCIDENT_TYPE']); ?></td>
                            </tr>
                            <tr>
                                <th>Description</th>
                                <td><?php echo htmlspecialchars($incident['description']); ?></td>
                            </tr>
                            <tr>
                                <th>Date & Time</th>
                                <td><?php echo date('M d, Y h:i A', strtotime($incident['date_time'])); ?></td>
                            </tr>
                            <tr>
                                <th>Location</th>
                                <td>
                                    <?php echo htmlspecialchars($incident['location']); ?>
                                    <br>
                                    <a href="https://maps.google.com/?q=<?php echo $incident['latitude']; ?>,<?php echo $incident['longitude']; ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                        View on Map <i class="fas fa-map-marker-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th>Reporting Personnel</th>
                                <td><?php echo htmlspecialchars($incident['personnel_name']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Detailed Report</h6>
                        <div class="card">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($incident['report'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Images</h6>
                        <div class="d-flex flex-wrap">
                            <?php if (!empty($incident['personnel_image'])): ?>
                                <div class="mr-3 mb-3">
                                    <p>Personnel Image:</p>
                                    <a href="<?php echo htmlspecialchars($incident['personnel_image']); ?>" data-lightbox="modal-images-<?php echo $incident['ID']; ?>">
                                        <img src="<?php echo htmlspecialchars($incident['personnel_image']); ?>" class="img-thumbnail" style="max-height: 200px;" alt="Personnel">
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($incident['incident_image'])): ?>
                                <div>
                                    <p>Incident Image:</p>
                                    <a href="<?php echo htmlspecialchars($incident['incident_image']); ?>" data-lightbox="modal-images-<?php echo $incident['ID']; ?>">
                                        <img src="<?php echo htmlspecialchars($incident['incident_image']); ?>" class="img-thumbnail" style="max-height: 200px;" alt="Incident">
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary update-incident" data-id="<?php echo $incident['ID']; ?>">Update</button>
            </div>
        </div>
    </div>
</div>
<?php 
    endwhile;
}
?>

<script>
// Open the Modal with Data
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('incidentImage').src = this.getAttribute('data-incident-image') || 'default-incident.jpg';
        document.getElementById('personnelImage').src = this.getAttribute('data-personnel-image') || 'default-personnel.jpg';
        
        document.getElementById('profilequantity').innerText = this.getAttribute('data-quantity') || 'N/A';
        document.getElementById('profilevalue').innerText = this.getAttribute('data-value') || 'N/A';
        document.getElementById('profileweight').innerText = this.getAttribute('data-weight') || '';
        document.getElementById('profileReport').innerText = this.getAttribute('data-report') || 'No report available.';
        
        document.getElementById('profileModal').style.display = 'block';
    });
});

// Close Modal
document.querySelector('.close').addEventListener('click', function() {
    document.getElementById('profileModal').style.display = 'none';
});

// Close if click outside the modal
window.addEventListener('click', function(event) {
  if (event.target == document.getElementById('profileModal')) {
    document.getElementById('profileModal').style.display = "none";
  }
});
</script>



<!-- JavaScript for handling modal interactions -->
<script>
$(document).ready(function() {
  // View details button click handler
  $('.view-details').on('click', function() {
    const incidentId = $(this).data('id');
    $('#detailsModal' + incidentId).modal('show');
  });
});
</script>

<script>
function printIncidentReport() {
    var printableContent = document.getElementById('printable-report').cloneNode(true);

    // Remove the Actions column from print if needed
    let actionsHeader = printableContent.querySelector('th:last-child');
    if (actionsHeader) actionsHeader.remove();

    let rows = printableContent.querySelectorAll('tbody tr');
    rows.forEach(row => {
        let lastCell = row.querySelector('td:last-child');
        if (lastCell) lastCell.remove();
    });

    var printWindow = window.open('', '', 'width=1000,height=700');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Incident Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                img.thumb { max-width: 100px; height: auto; margin: 5px; }
                h2 { text-align: center; }
            </style>
        </head>
        <body>
            ${printableContent.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>
                
                <div class="pagination">
    <ul style="list-style: none; display: flex; gap: 5px; padding-left: 0; align-items: center;">

        <!-- First and Previous buttons -->
        <?php if ($page > 1): ?>
            <li><a href="?page=1&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>"
                   style="padding: 6px 10px; border: 1px solid #ccc; text-decoration: none;">« First</a></li>
            <li><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>"
                   style="padding: 6px 10px; border: 1px solid #ccc; text-decoration: none;">‹ Prev</a></li>
        <?php endif; ?>

        <!-- Page number buttons -->
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>"
                   style="padding: 6px 12px; border: 1px solid #ccc; text-decoration: none;
                          <?php echo ($i == $page) ? 'background-color: #007bff; color: white;' : 'background-color: white; color: black;'; ?>">
                   <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <!-- Next and Last buttons -->
        <?php if ($page < $total_pages): ?>
            <li><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>"
                   style="padding: 6px 10px; border: 1px solid #ccc; text-decoration: none;">Next ›</a></li>
            <li><a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter_type=<?php echo urlencode($filter_type); ?>"
                   style="padding: 6px 10px; border: 1px solid #ccc; text-decoration: none;">Last »</a></li>
        <?php endif; ?>

        <!-- Jump to Page input -->
        <li style="margin-left: 10px;">
            <form method="get" style="display: flex; gap: 5px; align-items: center;">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" 
                       style="width: 60px; padding: 5px;" placeholder="Page">
                <button type="submit" style="padding: 5px 10px; border: 1px solid #ccc; background: #007bff; color: white;">Go</button>
            </form>
        </li>
    </ul>
</div>



            </div>
        <?php endif; ?>
    </div>
    





    

    
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


</body>
</html>


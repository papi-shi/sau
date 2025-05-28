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
define('DB_NAME', 'incident_db');
define('UPLOAD_DIR', 'uploads/');

// Drug value estimation constants - adding prices per gram/unit
define('DRUG_VALUES', json_encode([
    'Methamphetamine (Shabu)' => 5000, // Php per gram
    'Marijuana' => 300,               // Php per gram
    'Cocaine' => 8000,                // Php per gram  
    'Ecstasy' => 2500,                // Php per tablet
    'Heroin' => 10000,                // Php per gram
    'Others' => 3000                  // Default value per gram
]));

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

    // Drug quantity and estimated value (new fields)
    $drug_quantity = isset($_POST['drug_quantity']) ? floatval($_POST['drug_quantity']) : 0;
    $drug_unit = isset($_POST['drug_unit']) ? htmlspecialchars($_POST['drug_unit']) : 'grams';
    $estimated_value = 0;
    
    // Calculate estimated value if incident type is ILLEGAL DRUGS
    if ($incident_type === 'ILLEGAL DRUGS' && $drug_quantity > 0) {
        $drug_values = json_decode(DRUG_VALUES, true);
        $value_per_unit = isset($drug_values[$description]) ? $drug_values[$description] : $drug_values['Others'];
        $estimated_value = $drug_quantity * $value_per_unit;
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
    
    // Validate drug quantity if incident type is ILLEGAL DRUGS
    if ($incident_type === 'ILLEGAL DRUGS' && $drug_quantity <= 0) {
        $errors[] = "Valid drug quantity is required for illegal drugs incidents.";
    }
    
    if (!empty($errors)) {
        return $errors;
    }
    
    // Create uploads directory if it doesn't exist
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    
    // Process personnel image (from base64 string)
    $personnel_image_path = "";
    if (isset($_POST['personnel_image']) && !empty($_POST['personnel_image'])) {
        $personnel_image_path = saveBase64Image($_POST['personnel_image']);
        if (!$personnel_image_path) {
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
        // Add the drug quantity, unit, and estimated value fields to the query
        $query = "INSERT INTO incidents (INCIDENT_TYPE, description, date_time, report, personnel_name, personnel_image, incident_image, location, latitude, longitude, 
        goods_vessel_type, other_vessel_type, port_origin, port_destination, flag_registry, flag_convenience, name, age, birth_date, sex, civil_status, citizenship, 
        occupation, drug_quantity, drug_unit, estimated_value, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $errors[] = "Prepare failed: " . $conn->error;
            return $errors;
        }
        
        $stmt->bind_param(
            "sssssssssssssssssssssssdds", 
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
            $drug_quantity,
            $drug_unit,
            $estimated_value,
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

// Function to get drug statistics
function getDrugStatistics() {
    $conn = connectDB();
    $stats = [
        'by_type' => [],
        'total_value' => 0,
        'total_quantity' => 0,
        'count' => 0
    ];
    
    $query = "SELECT description, SUM(drug_quantity) as total_quantity, SUM(estimated_value) as total_value, COUNT(*) as count 
              FROM incidents 
              WHERE INCIDENT_TYPE = 'ILLEGAL DRUGS' 
              GROUP BY description 
              ORDER BY total_value DESC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stats['by_type'][$row['description']] = [
                'quantity' => floatval($row['total_quantity']),
                'value' => floatval($row['total_value']),
                'count' => intval($row['count'])
            ];
            
            $stats['total_value'] += floatval($row['total_value']);
            $stats['total_quantity'] += floatval($row['total_quantity']);
            $stats['count'] += intval($row['count']);
        }
    }
    
    return $stats;
}

// New function to get monthly report data
function getMonthlyReportData($month, $year) {
    $conn = connectDB();
    
    // Get all incidents for the month
    $start_date = sprintf("%04d-%02d-01", $year, $month);
    $end_date = date("Y-m-t", strtotime($start_date));
    
    // Query for illegal drugs incidents only, WITH date filter
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
        ],
        'drug_stats' => [
            'by_description' => [],
            'total_value' => 0,
            'total_quantity' => 0
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
        if (isset($row['age']) && is_numeric($row['age'])) {
            $age = (int)$row['age'];

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
        
        // Track drug statistics
        if (isset($row['description']) && isset($row['drug_quantity']) && isset($row['estimated_value'])) {
            $description = $row['description'];
            $quantity = floatval($row['drug_quantity']);
            $value = floatval($row['estimated_value']);
            
            if (!isset($data['drug_stats']['by_description'][$description])) {
                $data['drug_stats']['by_description'][$description] = [
                    'quantity' => 0,
                    'value' => 0,
                    'count' => 0
                ];
            }
            
            $data['drug_stats']['by_description'][$description]['quantity'] += $quantity;
            $data['drug_stats']['by_description'][$description]['value'] += $value;
            $data['drug_stats']['by_description'][$description]['count']++;
            
            $data['drug_stats']['total_quantity'] += $quantity;
            $data['drug_stats']['total_value'] += $value;
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
        'monthly_breakdown' => [],
        'drug_stats' => [
            'by_description' => [],
            'total_value' => 0,
            'total_quantity' => 0
        ]
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
        
        // Merge drug statistics
        foreach ($month_data['drug_stats']['by_description'] as $desc => $stats) {
            if (!isset($data['drug_stats']['by_description'][$desc])) {
                $data['drug_stats']['by_description'][$desc] = [
                    'quantity' => 0,
                    'value' => 0,
                    'count' => 0
                ];
            }
            
            $data['drug_stats']['by_description'][$desc]['quantity'] += $stats['quantity'];
            $data['drug_stats']['by_description'][$desc]['value'] += $stats['value'];
            $data['drug_stats']['by_description'][$desc]['count'] += $stats['count'];
        }
        
        $data['drug_stats']['total_quantity'] += $month_data['drug_stats']['total_quantity'];
        $data['drug_stats']['total_value'] += $month_data['drug_stats']['total_value'];
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
        ],
        'drug_stats' => [
            'by_description' => [],
            'total_value' => 0,
            'total_quantity' => 0
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
        
        // Merge drug statistics
        foreach ($quarter_data['drug_stats']['by_description'] as $desc => $stats) {
            if (!isset($data['drug_stats']['by_description'][$desc])) {
                $data['drug_stats']['by_description'][$desc] = [
                    'quantity' => 0,
                    'value' => 0,
                    'count' => 0
                ];
            }
            
            $data['drug_stats']['by_description'][$desc]['quantity'] += $stats['quantity'];
            $data['drug_stats']['by_description'][$desc]['value'] += $stats['value'];
            $data['drug_stats']['by_description'][$desc]['count'] += $stats['count'];
        }
        
        $data['drug_stats']['total_quantity'] += $quarter_data['drug_stats']['total_quantity'];
        $data['drug_stats']['total_value'] += $quarter_data['drug_stats']['total_value'];
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

// Flush the output buffer at the end of the script
ob_end_flush();

?>

<!-- Drug Visualization Enhancement - Add to your HTML file -->
<div id="drug-visualization-section" style="display: none; margin-top: 20px;">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Estimated Drug Value</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>Quantity: <span id="drug-quantity-display">0</span> <span id="drug-unit-display">grams</span></h4>
                            </div>
                            <div class="col-md-6">
                                <h4>Estimated Value: ₱<span id="estimated-value-display">0.00</span></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Drug Values per Gram/Unit</h5>
                </div>
                <div class="card-body">
                    <canvas id="drug-value-chart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add this to your form where it makes sense - likely after the incident type selection -->

<div class="card mb-4">
    <div class="card-header">
        <h4>Incident Details</h4>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="INCIDENT_TYPE">Incident Type <span class="text-danger">*</span></label>
                    <select class="form-control" name="INCIDENT_TYPE" id="INCIDENT_TYPE" required>
                        <option value="">-- Select Incident Type --</option>
                        <option value="ILLEGAL DRUGS">ILLEGAL DRUGS</option>
                        <option value="SMUGGLING">SMUGGLING</option>
                        <option value="TRAFFICKING">TRAFFICKING</option>
                        <option value="FISHERY/MARITIME">FISHERY/MARITIME</option>
                        <option value="CUSTOMS">CUSTOMS</option>
                        <option value="IMMIGRATION">IMMIGRATION</option>
                        <option value="OTHERS">OTHERS</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="description">Description <span class="text-danger">*</span></label>
                    <select class="form-control" name="description" id="description" required>
                        <option value="">-- Select Description --</option>
                    </select>
                </div>
                <div class="form-group" id="custom_description_div" style="display: none;">
                    <label for="custom_description">Custom Description <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="custom_description" id="custom_description">
                </div>
            </div>
        </div>
        
        <!-- Add Drug-specific fields that will show only for ILLEGAL DRUGS incident type -->
        <div class="row drug-field" style="display: none;">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="drug_quantity">Drug Quantity <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="drug_quantity" id="drug_quantity" step="0.01" min="0" value="0">
                        <div class="input-group-append">
                            <select class="form-control" name="drug_unit" id="drug_unit">
                                <option value="grams">grams</option>
                                <option value="kilograms">kilograms</option>
                                <option value="tablets">tablets</option>
                                <option value="pieces">pieces</option>
                            </select>
                        </div>
                    </div>
                    <small class="form-text text-muted">Enter the quantity of drugs seized</small>
                </div>
            </div>
            <div class="col-md-6">
                <!-- This will be filled with the calculated value -->
                <div class="form-group">
                    <label>Estimated Value</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">₱</span>
                        </div>
                        <input type="text" class="form-control" id="estimated_value_display" readonly>
                    </div>
                    <small class="form-text text-muted">Automatically calculated based on drug type and quantity</small>
                </div>
            </div>
        </div>
        
        <!-- This hidden field will store the actual estimated value -->
        <input type="hidden" name="estimated_value" id="estimated_value" value="0">
    </div>
</div>

<!-- Add placeholder for the drug visualization section -->
<div id="drug-visualization-section"></div>

<!-- Add a modal for displaying drug statistics -->
<div class="modal fade" id="drugStatsModal" tabindex="-1" role="dialog" aria-labelledby="drugStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="drugStatsModalLabel">Drug Seizure Statistics</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6>Total Drug Seizures Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <h4 id="total-seizure-count">0</h4>
                                        <p>Total Seizures</p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <h4 id="total-seizure-quantity">0</h4>
                                        <p>Total Quantity (grams)</p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <h4>₱<span id="total-seizure-value">0</span></h4>
                                        <p>Total Value</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Seizures by Drug Type</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="drug-seizure-chart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Value by Drug Type</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="drug-value-total-chart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6>Detailed Drug Seizure Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Drug Type</th>
                                                <th>Seizure Count</th>
                                                <th>Total Quantity</th>
                                                <th>Value per Gram/Unit</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody id="drug-stats-table-body">
                                            <!-- Drug statistics will be inserted here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Link to Chart.js library (Add this if not already included) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Add this script to your JS section -->
<script>
// Drug values as defined in the PHP constants
const drugValues = {
    'Methamphetamine (Shabu)': 5000,
    'Marijuana': 300,
    'Cocaine': 8000,
    'Ecstasy': 2500,
    'Heroin': 10000,
    'Others': 3000
};

// Initialize global variables for charts
let drugValueChart = null;
let drugSeizureChart = null;
let drugValueTotalChart = null;

// Function to initialize drug value bar chart
function initDrugValueChart() {
    const ctx = document.getElementById('drug-value-chart').getContext('2d');
    
    // Extract drug types and values for the chart
    const drugTypes = Object.keys(drugValues);
    const values = Object.values(drugValues);
    
    // Create a bar chart
    drugValueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: drugTypes,
            datasets: [{
                label: 'Value (PHP) per Gram/Unit',
                data: values,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
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
                        text: 'PHP Value'
                    }
                }
            }
        }
    });
}

// Function to calculate estimated value based on drug type and quantity
function calculateEstimatedValue() {
    const incidentType = document.querySelector('select[name="INCIDENT_TYPE"]').value;
    const description = document.querySelector('select[name="description"]').value;
    const drugQuantity = parseFloat(document.querySelector('input[name="drug_quantity"]').value) || 0;
    const drugUnit = document.querySelector('select[name="drug_unit"]').value;
    
    // Display section only if Illegal Drugs is selected
    const visualizationSection = document.getElementById('drug-visualization-section');
    
    if (incidentType === 'ILLEGAL DRUGS') {
        visualizationSection.style.display = 'block';
        
        // Update quantity and unit display
        document.getElementById('drug-quantity-display').textContent = drugQuantity.toFixed(2);
        document.getElementById('drug-unit-display').textContent = drugUnit;
        
        // Calculate estimated value
        let valuePerUnit = drugValues[description] || drugValues['Others'];
        const estimatedValue = drugQuantity * valuePerUnit;
        
        // Update estimated value display
        document.getElementById('estimated-value-display').textContent = estimatedValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        
        // Update hidden input field for estimated value
        const estimatedValueInput = document.querySelector('input[name="estimated_value"]');
        if (!estimatedValueInput) {
            // Create the field if it doesn't exist
            const newInput = document.createElement('input');
            newInput.type = 'hidden';
            newInput.name = 'estimated_value';
            newInput.value = estimatedValue;
            document.querySelector('form').appendChild(newInput);
        } else {
            estimatedValueInput.value = estimatedValue;
        }
        
        // Highlight the selected drug in the chart
        if (drugValueChart) {
            // Reset all bar colors
            drugValueChart.data.datasets[0].backgroundColor = [
                'rgba(255, 99, 132, 0.3)',
                'rgba(54, 162, 235, 0.3)',
                'rgba(255, 206, 86, 0.3)',
                'rgba(75, 192, 192, 0.3)',
                'rgba(153, 102, 255, 0.3)',
                'rgba(255, 159, 64, 0.3)'
            ];
            
            // Highlight selected drug
            const index = Object.keys(drugValues).indexOf(description);
            if (index >= 0) {
                drugValueChart.data.datasets[0].backgroundColor[index] = 'rgba(255, 99, 132, 0.9)';
            }
            
            drugValueChart.update();
        }
    } else {
        visualizationSection.style.display = 'none';
    }
}

// Function to initialize drug statistics charts in the modal
function initDrugStatisticsCharts(stats) {
    // Clear previous charts
    if (drugSeizureChart) {
        drugSeizureChart.destroy();
    }
    
    if (drugValueTotalChart) {
        drugValueTotalChart.destroy();
    }
    
    // Get drug types and counts
    const drugTypes = Object.keys(stats.by_type);
    const counts = drugTypes.map(type => stats.by_type[type].count);
    const values = drugTypes.map(type => stats.by_type[type].value);
    
    // Create color arrays
    const backgroundColors = [
        'rgba(255, 99, 132, 0.6)',
        'rgba(54, 162, 235, 0.6)',
        'rgba(255, 206, 86, 0.6)',
        'rgba(75, 192, 192, 0.6)',
        'rgba(153, 102, 255, 0.6)',
        'rgba(255, 159, 64, 0.6)'
    ];
    
    const borderColors = [
        'rgba(255, 99, 132, 1)',
        'rgba(54, 162, 235, 1)',
        'rgba(255, 206, 86, 1)',
        'rgba(75, 192, 192, 1)',
        'rgba(153, 102, 255, 1)',
        'rgba(255, 159, 64, 1)'
    ];
    
    // Create seizures by drug type chart
    const seizureCtx = document.getElementById('drug-seizure-chart').getContext('2d');
    drugSeizureChart = new Chart(seizureCtx, {
        type: 'bar',
        data: {
            labels: drugTypes,
            datasets: [{
                label: 'Number of Seizures',
                data: counts,
                backgroundColor: backgroundColors.slice(0, drugTypes.length),
                borderColor: borderColors.slice(0, drugTypes.length),
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
                        text: 'Count'
                    }
                }
            }
        }
    });
    
    // Create value by drug type chart
    const valueCtx = document.getElementById('drug-value-total-chart').getContext('2d');
    drugValueTotalChart = new Chart(valueCtx, {
        type: 'pie',
        data: {
            labels: drugTypes,
            datasets: [{
                label: 'Total Value (PHP)',
                data: values,
                backgroundColor: backgroundColors.slice(0, drugTypes.length),
                borderColor: borderColors.slice(0, drugTypes.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            return `Value: ₱${value.toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });
    
    // Update summary statistics
    document.getElementById('total-seizure-count').textContent = stats.count.toLocaleString();
    document.getElementById('total-seizure-quantity').textContent = stats.total_quantity.toLocaleString();
    document.getElementById('total-seizure-value').textContent = stats.total_value.toLocaleString();
    
    // Update detailed table
    const tableBody = document.getElementById('drug-stats-table-body');
    tableBody.innerHTML = '';
    
    for (const [drugType, data] of Object.entries(stats.by_type)) {
        const row = document.createElement('tr');
        
        // Drug type
        const typeCell = document.createElement('td');
        typeCell.textContent = drugType;
        row.appendChild(typeCell);
        
        // Seizure count
        const countCell = document.createElement('td');
        countCell.textContent = data.count;
        row.appendChild(countCell);
        
        // Quantity
        const quantityCell = document.createElement('td');
        quantityCell.textContent = `${data.quantity.toLocaleString()} grams`;
        row.appendChild(quantityCell);
        
        // Value per unit
        const valuePerUnitCell = document.createElement('td');
        valuePerUnitCell.textContent = `₱${drugValues[drugType] ? drugValues[drugType].toLocaleString() : 'N/A'}`;
        row.appendChild(valuePerUnitCell);
        
        // Total value
        const valueCell = document.createElement('td');
        valueCell.textContent = `₱${data.value.toLocaleString()}`;
        row.appendChild(valueCell);
        
        tableBody.appendChild(row);
    }
}

// Function to fetch drug statistics via AJAX
function fetchDrugStatistics() {
    // Create a stats button in the navbar
    const navbarNav = document.querySelector('.navbar-nav');
    if (navbarNav && !document.getElementById('drug-stats-btn')) {
        const li = document.createElement('li');
        li.className = 'nav-item';
        
        const button = document.createElement('button');
        button.className = 'btn btn-success ml-2';
        button.id = 'drug-stats-btn';
        button.textContent = 'Drug Statistics';
        button.addEventListener('click', function() {
            // Fetch drug statistics via AJAX
            fetch('?fetchDrugStats=1')
                .then(response => response.json())
                .then(data => {
                    initDrugStatisticsCharts(data);
                    $('#drugStatsModal').modal('show');
                })
                .catch(error => console.error('Error fetching drug statistics:', error));
        });
        
        li.appendChild(button);
        navbarNav.appendChild(li);
    }
}

// Event listeners for form fields
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the drug value chart
    initDrugValueChart();
    
    // Add drug statistics button
    fetchDrugStatistics();
    
    // Add hidden field for estimated value if it doesn't exist
    if (!document.querySelector('input[name="estimated_value"]')) {
        const estimatedValueInput = document.createElement('input');
        estimatedValueInput.type = 'hidden';
        estimatedValueInput.name = 'estimated_value';
        estimatedValueInput.value = '0';
        document.querySelector('form').appendChild(estimatedValueInput);
    }

    // Add event listeners to relevant form fields
    const incidentTypeSelect = document.querySelector('select[name="INCIDENT_TYPE"]');
    const descriptionSelect = document.querySelector('select[name="description"]');
    const drugQuantityInput = document.querySelector('input[name="drug_quantity"]');
    const drugUnitSelect = document.querySelector('select[name="drug_unit"]');
    
    if (incidentTypeSelect) {
        incidentTypeSelect.addEventListener('change', function() {
            // Show/hide drug related fields based on incident type
            const drugFields = document.querySelectorAll('.drug-field');
            
            if (this.value === 'ILLEGAL DRUGS') {
                drugFields.forEach(field => field.style.display = 'block');
                calculateEstimatedValue();
            } else {
                drugFields.forEach(field => field.style.display = 'none');
                document.getElementById('drug-visualization-section').style.display = 'none';
            }
        });
        
        // Trigger initial state
        incidentTypeSelect.dispatchEvent(new Event('change'));
    }
    
    // Add event listeners to update the estimated value when inputs change
    if (descriptionSelect) {
        descriptionSelect.addEventListener('change', calculateEstimatedValue);
    }
    
    if (drugQuantityInput) {
        drugQuantityInput.addEventListener('input', calculateEstimatedValue);
    }
    
    if (drugUnitSelect) {
        drugUnitSelect.addEventListener('change', calculateEstimatedValue);
    }
});
</script>
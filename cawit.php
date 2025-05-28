<?php
// Start session for success messages
session_start();
ob_start(); // Buffer output to prevent "headers already sent" errors

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'incidents');
define('UPLOAD_DIR', 'uploads/');

// --- Connect to DB ---
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Could not connect to database.");
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// --- Get incident by ID ---
function getIncidentDetails($id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM incidents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $data;
}

function getTotalWorth($substation = 'cawit') {
    $conn = connectDB();
    $query = "SELECT SUM(worth) as total_worth FROM incidents WHERE substation = ? AND worth IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $substation);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $data['total_worth'] ?? 0;
}

// --- Handle AJAX request ---
if (isset($_GET['action']) && $_GET['action'] === 'get_incident' && isset($_GET['id'])) {
    $incident = getIncidentDetails(intval($_GET['id']));
    header('Content-Type: application/json');
    echo json_encode($incident);
    exit;
}

function validateImage($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

    // Basic checks
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return "Invalid file upload.";
    }

    if ($file['size'] > $max_size) {
        return "File too large. Max size is 5MB.";
    }

    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        return "Invalid file extension. Only JPG, PNG, and GIF are allowed.";
    }

    // Simple MIME type validation by file signature
    $file_signatures = [
        'jpg'  => "\xFF\xD8\xFF",
        'jpeg' => "\xFF\xD8\xFF",
        'png'  => "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A",
        'gif'  => "GIF"
    ];

    $file_content = file_get_contents($file['tmp_name']);
    $valid = false;

    foreach ($file_signatures as $type => $signature) {
        if (substr($file_content, 0, strlen($signature)) === $signature) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        return "Invalid file content. File doesn't match expected image format.";
    }

    return true;
}

// --- Save base64 image ---
function saveBase64Image($base64data) {
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64data, $matches)) {
        return false;
    }

    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64data));
    $extension = strtolower($matches[1]);
    $filename = UPLOAD_DIR . 'personnel_' . uniqid() . '.' . $extension;

    return file_put_contents($filename, $image_data) ? $filename : false;
}

// --- Main form submission handler ---
function handleSubmission() {
    $conn = connectDB();
    $errors = [];
    $stmt = null;

    // Validate required fields
    $required_fields = [
        'INCIDENT_TYPE' => 'Incident type',
        'description' => 'Description',
        'report' => 'Report',
        'personnel_name' => 'Personnel name',
        'location' => 'Location',
        'status' => 'Case status',
        'substation' => 'Substation',
        'station' => 'Station'
    ];

    foreach ($required_fields as $field => $name) {
        if (empty($_POST[$field])) {
            $errors[] = "$name is required.";
        }
    }

    // Validate location coordinates
    if (empty($_POST['latitude']) || empty($_POST['longitude'])) {
        $errors[] = "Map location is required.";
    }

    // Process description
    $description = '';
    if ($_POST['description'] === 'Others') {
        $description = !empty($_POST['custom_description']) ? htmlspecialchars($_POST['custom_description']) : '';
        if (!$description) $errors[] = "Custom description is required.";
    } else {
        $description = htmlspecialchars($_POST['description'] ?? '');
    }

    // Sanitize all input
    $incident_type = htmlspecialchars(trim($_POST['INCIDENT_TYPE'] ?? ''));
    $substation = htmlspecialchars(trim($_POST['substation'] ?? 'cawit'));
    $station = htmlspecialchars(trim($_POST['station'] ?? 'zamboanga'));
    $report = htmlspecialchars(trim($_POST['report'] ?? ''), ENT_QUOTES);
    $personnel_name = htmlspecialchars(trim($_POST['personnel_name'] ?? ''), ENT_QUOTES);
    $location = htmlspecialchars(trim($_POST['location'] ?? ''), ENT_QUOTES);
    $lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $status = htmlspecialchars(trim($_POST['status'] ?? ''), ENT_QUOTES);
    $person_status = htmlspecialchars(trim($_POST['person_status'] ?? ''), ENT_QUOTES);
    $vessels_status = htmlspecialchars(trim($_POST['vessels_status'] ?? ''), ENT_QUOTES);
    
    // Only process worth if incident type is IUUF
    $worth = null;
    if ($incident_type === 'IUUF' && isset($_POST['worth'])) {
        $worth = floatval(str_replace(',', '', $_POST['worth']));
    }

    // Handle date/time
    $date_time = !empty($_POST['incident_date_time']) ? 
        date('Y-m-d H:i:s', strtotime($_POST['incident_date_time'])) : 
        date('Y-m-d H:i:s');

    // Smuggling fields
    $goods_vessel_type = htmlspecialchars(trim($_POST['goods_vessel_type'] ?? ''), ENT_QUOTES);
    $other_vessel_type = htmlspecialchars(trim($_POST['other_vessel_type'] ?? ''), ENT_QUOTES);
    $port_origin = htmlspecialchars(trim($_POST['port_origin'] ?? ''), ENT_QUOTES);
    $port_destination = htmlspecialchars(trim($_POST['port_destination'] ?? ''), ENT_QUOTES);
    $flag_registry = htmlspecialchars(trim($_POST['flag_registry'] ?? ''), ENT_QUOTES);
    $flag_convenience = htmlspecialchars(trim($_POST['flag_convenience'] ?? ''), ENT_QUOTES);

    // Illegal drugs fields
    $quantity = htmlspecialchars(trim($_POST['quantity'] ?? ''), ENT_QUOTES);
    $value = isset($_POST['value']) ? floatval($_POST['value']) : null;
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : null;

    // Human trafficking fields
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES);
    $age = isset($_POST['age']) ? intval($_POST['age']) : null;
    $birth_date = !empty($_POST['birth_date']) ? date('Y-m-d H:i:s', strtotime($_POST['birth_date'])) : null;
    $sex = htmlspecialchars(trim($_POST['sex'] ?? ''), ENT_QUOTES);
    $civil_status = htmlspecialchars(trim($_POST['civil_status'] ?? ''), ENT_QUOTES);
    $citizenship = htmlspecialchars(trim($_POST['citizenship'] ?? ''), ENT_QUOTES);
    $occupation = htmlspecialchars(trim($_POST['occupation'] ?? ''), ENT_QUOTES);
    $vessel_type_ht = htmlspecialchars(trim($_POST['vessel_type_ht'] ?? ''), ENT_QUOTES);
    $other_vessel_type_ht = htmlspecialchars(trim($_POST['other_vessel_type_ht'] ?? ''), ENT_QUOTES);

    // Handle webcam image
    $personnel_image_path = '';
    if (!empty($_POST['camera_image_data'])) {
        $personnel_image_path = saveBase64Image($_POST['camera_image_data']);
        if (!$personnel_image_path) $errors[] = "Failed to save personnel image.";
    }

    // Handle incident image
    $incident_image_path = '';
    if (!empty($_FILES['incident_image']['tmp_name'])) {
        $validation = validateImage($_FILES['incident_image']);
        if ($validation === true) {
            $ext = pathinfo($_FILES['incident_image']['name'], PATHINFO_EXTENSION);
            $filename = UPLOAD_DIR . 'incident_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['incident_image']['tmp_name'], $filename)) {
                $errors[] = "Failed to save incident image.";
            } else {
                $incident_image_path = $filename;
            }
        } else {
            $errors[] = $validation;
        }
    } else {
        $errors[] = "Incident image is required.";
    }

    // If errors, return
    if (!empty($errors)) {
        $conn->close();
        return $errors;
    }

    // Insert to DB
    try {
        $query = "INSERT INTO incidents (
            incident_type, description, substation, station, status, person_status, vessels_status, worth, personnel_name, location, latitude, 
            longitude, report, incident_image, personnel_image, goods_vessel_type, 
            other_vessel_type, port_origin, port_destination, flag_registry, 
            flag_convenience, quantity, value, weight, name, age, birth_date, sex, 
            civil_status, citizenship, occupation, vessel_type_ht, other_vessel_type_ht,
            date_time, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $bind_result = $stmt->bind_param(
            "ssssssssssssssssssssssssssssssssss", 
            $incident_type, $description, $substation, $station, $status, $person_status, $vessels_status, $worth, $personnel_name, $location, $lat,
            $lng, $report, $incident_image_path, $personnel_image_path, $goods_vessel_type,
            $other_vessel_type, $port_origin, $port_destination, $flag_registry,
            $flag_convenience, $quantity, $value, $weight, $name, $age, $birth_date, $sex,
            $civil_status, $citizenship, $occupation, $vessel_type_ht, $other_vessel_type_ht,
            $date_time
        );

        if ($bind_result === false) {
            throw new Exception("Bind failed: " . $stmt->error);
        }

        $execute_result = $stmt->execute();
        if ($execute_result === false) {
            throw new Exception("Execution failed: " . $stmt->error);
        }

        if ($stmt->affected_rows > 0) {
            $_SESSION['success_message'] = "Incident report submitted successfully.";
            $stmt->close();
            $conn->close();
            return [];
        } else {
            $errors[] = "Failed to submit report.";
            $stmt->close();
            $conn->close();
            return $errors;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $errors[] = "A database error occurred: " . $e->getMessage();
        if ($stmt) $stmt->close();
        $conn->close();
        return $errors;
    }
}

// [Rest of your existing functions (handleDelete, getMonthlyReportData, etc.) remain the same]
function handleDelete() {
    $conn = connectDB();
    $errors = [];
    $stmt = null;
    
    $delete_id = isset($_POST['delete_id']) ? intval($_POST['delete_id']) : null;
    
    if (!$delete_id) {
        $conn->close();
        $errors[] = "Invalid request.";
        return $errors;
    }
    
    try {
        // First get the incident to delete image files
        $stmt = $conn->prepare("SELECT personnel_image, incident_image FROM incidents WHERE id = ?");
        if (!$stmt) {
            $conn->close();
            $errors[] = "Database error occurred.";
            return $errors;
        }
        
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            
            // Delete the database record
            $stmt = $conn->prepare("DELETE FROM incidents WHERE id = ?");
            if (!$stmt) {
                $conn->close();
                $errors[] = "Database error occurred.";
                return $errors;
            }
            
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                // Delete associated image files
                if (!empty($row['personnel_image']) && file_exists($row['personnel_image'])) {
                    @unlink($row['personnel_image']);
                }
                if (!empty($row['incident_image']) && file_exists($row['incident_image'])) {
                    @unlink($row['incident_image']);
                }
                
                $_SESSION['success_message'] = "Incident report deleted successfully.";
                $stmt->close();
                $conn->close();
                return [];
            } else {
                $stmt->close();
                $conn->close();
                $errors[] = "Failed to delete report.";
                return $errors;
            }
        } else {
            $stmt->close();
            $conn->close();
            $errors[] = "Incident not found.";
            return $errors;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $errors[] = "Database error occurred.";
        if ($stmt) $stmt->close();
        $conn->close();
        return $errors;
    }
}

// --- Get monthly report data ---
function getMonthlyReportData($month, $year, $substation = 'cawit') {
    $conn = connectDB();
    $data = [
        'incidents' => [],
        'total' => 0,
        'by_type' => [],
        'by_description' => [],
        'locations' => [],
        'daily_stats' => []
    ];
    
    $start_date = sprintf("%04d-%02d-01", $year, $month);
    $end_date = date("Y-m-t", strtotime($start_date));
    
    $query = "SELECT * FROM incidents WHERE date_time BETWEEN ? AND ? AND substation = ? ORDER BY date_time";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $start_date, $end_date, $substation);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['incidents'][] = $row;
        $data['total']++;
        
        // Count by type
        if (!isset($data['by_type'][$row['INCIDENT_TYPE']])) {
            $data['by_type'][$row['INCIDENT_TYPE']] = 0;
        }
        $data['by_type'][$row['INCIDENT_TYPE']]++;
        
        // Count by description
        if (!isset($data['by_description'][$row['description']])) {
            $data['by_description'][$row['description']] = 0;
        }
        $data['by_description'][$row['description']]++;
        
        // Prepare location data
        $data['locations'][] = [
            'lat' => $row['latitude'],
            'lng' => $row['longitude'],
            'type' => $row['INCIDENT_TYPE'],
            'description' => $row['description'],
            'date' => date('M d, Y h:i A', strtotime($row['date_time']))
        ];
    }
    
    // Initialize daily stats for all days in month
    $current_date = new DateTime($start_date);
    $end_datetime = new DateTime($end_date);
    
    while ($current_date <= $end_datetime) {
        $day = $current_date->format('Y-m-d');
        $data['daily_stats'][$day] = 0;
        $current_date->modify('+1 day');
    }
    
    // Populate daily stats with actual counts
    foreach ($data['incidents'] as $incident) {
        $day = date('Y-m-d', strtotime($incident['date_time']));
        if (isset($data['daily_stats'][$day])) {
            $data['daily_stats'][$day]++;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return $data;
}

// --- Get quarterly report data ---
function getQuarterlyReportData($quarter, $year, $substation = 'cawit') {
    $months = [];
    switch ($quarter) {
        case 1: $months = [1, 2, 3]; break;
        case 2: $months = [4, 5, 6]; break;
        case 3: $months = [7, 8, 9]; break;
        case 4: $months = [10, 11, 12]; break;
        default: $months = [1, 2, 3]; break;
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
        $month_data = getMonthlyReportData($month, $year, $substation);
        $data['incidents'] = array_merge($data['incidents'], $month_data['incidents']);
        $data['total'] += $month_data['total'];
        $data['locations'] = array_merge($data['locations'], $month_data['locations']);
        
        $month_name = date('F', mktime(0, 0, 0, $month, 1));
        $data['monthly_stats'][$month_name] = $month_data['total'];
        $data['monthly_breakdown'][$month_name] = $month_data['by_type'];
        
        // Aggregate by type
        foreach ($month_data['by_type'] as $type => $count) {
            if (!isset($data['by_type'][$type])) {
                $data['by_type'][$type] = 0;
            }
            $data['by_type'][$type] += $count;
        }
        
        // Aggregate by description
        foreach ($month_data['by_description'] as $desc => $count) {
            if (!isset($data['by_description'][$desc])) {
                $data['by_description'][$desc] = 0;
            }
            $data['by_description'][$desc] += $count;
        }
    }
    
    return $data;
}

// --- Get yearly report data ---
function getYearlyReportData($year, $substation = 'cawit') {
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
    
    for ($quarter = 1; $quarter <= 4; $quarter++) {
        $quarter_data = getQuarterlyReportData($quarter, $year, $substation);
        $data['incidents'] = array_merge($data['incidents'], $quarter_data['incidents']);
        $data['total'] += $quarter_data['total'];
        $data['locations'] = array_merge($data['locations'], $quarter_data['locations']);
        
        $q_name = "Q$quarter";
        $data['quarterly_stats'][$q_name] = $quarter_data['total'];
        $data['quarterly_breakdown'][$q_name] = $quarter_data['by_type'];
        
        foreach ($quarter_data['monthly_stats'] as $month => $count) {
            $data['monthly_stats'][$month] = $count;
        }
        
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
// Database is already initialized via the connectDB() function
// Process form submissions
$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $errors = handleSubmission();
        if (empty($errors)) {
            ob_end_clean();
            header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']) . "?success=1");
            exit();
        }
    } elseif (isset($_POST['delete'])) {
        $errors = handleDelete();
        if (empty($errors)) {
            ob_end_clean();
            header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']));
            exit();
        }
    }
}

// [Rest of your existing code for report generation and incident listing]
// Handle report generation requests
$view_report = isset($_GET['view_report']) ? $_GET['view_report'] : '';
$report_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$report_quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil(date('n') / 3);
$report_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$substation = 'cawit'; // Only show reports for cawit substation

// Check if we need to show a report
$show_report = false;
$report_data = null;
$report_title = '';

if ($view_report == 'monthly') {
    $show_report = true;
    $report_data = getMonthlyReportData($report_month, $report_year, $substation);
    $report_title = 'Monthly Report: ' . date('F Y', mktime(0, 0, 0, $report_month, 1, $report_year)) . ' - ' . strtoupper($substation);
} elseif ($view_report == 'quarterly') {
    $show_report = true;
    $report_data = getQuarterlyReportData($report_quarter, $report_year, $substation);
    $report_title = 'Quarterly Report: Q' . $report_quarter . ' ' . $report_year . ' - ' . strtoupper($substation);
} elseif ($view_report == 'yearly') {
    $show_report = true;
    $report_data = getYearlyReportData($report_year, $substation);
    $report_title = 'Yearly Report: ' . $report_year . ' - ' . strtoupper($substation);
}

// Regular incident listing with pagination and search
if (!$show_report) {
    $conn = connectDB();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Search/Filter
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $filter_type = isset($_GET['filter_type']) ? $conn->real_escape_string($_GET['filter_type']) : '';

    // Build query safely - only show incidents from cawit substation
    $where = ["substation = 'cawit'"];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $where[] = "(description LIKE ? OR report LIKE ? OR personnel_name LIKE ? OR location LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, array_fill(0, 4, $search_param));
        $types .= str_repeat('s', 4);
    }

    if (!empty($filter_type)) {
        $where[] = "INCIDENT_TYPE = ?";
        $params[] = $filter_type;
        $types .= 's';
    }

    $where_clause = implode(' AND ', $where);

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
    $types .= "ii";
    $params[] = $offset;
    $params[] = $per_page;
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $incidents = $stmt->get_result();

    // Get incident types for filter dropdown (only from cawit substation)
    $incident_types = $conn->query("SELECT DISTINCT INCIDENT_TYPE FROM incidents WHERE substation = 'cawit' ORDER BY INCIDENT_TYPE");
    $conn->close();
}

// Get statistics for dashboard (only for cawit substation)
$counts = [
    'FOR FILLING' => 0,
    'FILED' => 0,
    'CLOSED' => 0
];

$conn = connectDB();
$sql = "SELECT status, COUNT(*) as count FROM incidents WHERE substation = 'cawit' GROUP BY status";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = strtoupper($row['status']);
        if (isset($counts[$status])) {
            $counts[$status] = $row['count'];
        }
    }
}

// Close database connection
$conn->close();

// Display any errors
if (!empty($errors)) {
    echo '<div class="alert alert-danger"><ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error) . '</li>';
    }
    echo '</ul></div>';
}

include 'test.html';
?>

<!-- JavaScript to handle the worth field visibility -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const incidentTypeSelect = document.getElementById('incident_type');
    const worthField = document.querySelector('.worth-field');

    // Function to toggle worth field visibility
    function toggleWorthField() {
        if (incidentTypeSelect.value === 'IUUF') {
            worthField.style.display = 'block';
            worthField.setAttribute('required', 'required');
        } else {
            worthField.style.display = 'none';
            worthField.removeAttribute('required');
            worthField.value = '';
        }
    }

    // Initial setup
    toggleWorthField();

    // Add event listener for incident type changes
    incidentTypeSelect.addEventListener('change', toggleWorthField);

    // Format worth input with commas
    if (worthField) {
        worthField.addEventListener('input', function(e) {
            let value = e.target.value.replace(/,/g, '');
            if (!isNaN(value) && value !== '') {
                e.target.value = parseFloat(value).toLocaleString('en-US');
            }
        });
    }
});
</script>

<!-- Date Time Picker in Form -->


<!-- [Rest of your HTML remains the same] -->
 <body>
    <div class="header">
       
        <h1>Incident Reporting System</h1>
        <h3>Coast Guard Sub-Station cawit</h3>
        <!-- Add this in your report summary section -->

    </div>
    
    <nav>
        <ul>
            <li><a href="cawit.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="cawit.php" id="add-incident-btn"><i class="fas fa-plus-circle"></i> Add Incident</a></li>
            <li><a href="cawit.php" id="monthly-report-btn"><i class="fas fa-calendar-alt"></i> Monthly Report</a></li>
            <li><a href="cawit.php" id="quarterly-report-btn"><i class="fas fa-chart-bar"></i> Quarterly Report</a></li>
            <li><a href="cawit.php" id="yearly-report-btn"><i class="fas fa-chart-line"></i> Yearly Report</a></li>
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
    // Initialize and validate data
    if (!isset($report_data['by_type']) || !is_array($report_data['by_type']) || empty($report_data['by_type'])) {
        // Show message when no data is available
        ?>
        <div class="no-data-message">
            <div class="incident-label">No incident data available</div>
            <div class="incident-bar-outer">
                <div class="incident-bar-inner" style="width: 0%"></div>
            </div>
            <div class="incident-value">0</div>
        </div>
        <?php
    } else {
        // Process data when available
        arsort($report_data['by_type']);
        $max_value = max($report_data['by_type']);
        $i = 0;
        
        foreach($report_data['by_type'] as $type => $count):
            if ($i++ >= 5) break; // Show only top 5
        ?>
            <div class="incident-bar">
                <div class="incident-label"><?php echo htmlspecialchars($type); ?></div>
                <div class="incident-bar-outer">
                    <div class="incident-bar-inner" style="width: <?php echo $max_value > 0 ? ($count / $max_value * 100) : 0; ?>%"></div>
                </div>
                <div class="incident-value"><?php echo $count; ?></div>
            </div>
        <?php 
        endforeach;
    }
    ?>
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
               <!-- PRINT BUTTON -->
<button onclick="printIncidentReport()" class="btn btn-primary" style="margin-bottom: 10px;">
    <i class="fas fa-print"></i> Print Report
</button>

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
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Query for all incidents
            $query = "SELECT * FROM incidents ORDER BY date_time DESC";
          
            
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
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-info btn-sm view-details" data-id="<?php echo $incident['id']; ?>">
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
        </tbody>
    </table>
</div>

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
                    
                    <div class="col-md-6">
                        <!-- Only show smuggling details if it's a smuggling incident -->
                        <?php if ($incident['INCIDENT_TYPE'] === 'SMUGGLING'): ?>
                        <h6>Smuggling Details</h6>
                        <table class="table table-bordered">
                            <tr>
                                <th>Type of Goods/Vessel</th>
                                <td>
                                    <?php 
                                        echo htmlspecialchars($incident['goods_vessel_type'] ?? 'N/A'); 
                                        if (($incident['goods_vessel_type'] ?? '') === 'OTHERS' && !empty($incident['other_vessel_type'])) {
                                            echo " (" . htmlspecialchars($incident['other_vessel_type']) . ")";
                                        }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Port of Origin</th>
                                <td><?php echo htmlspecialchars($incident['port_origin'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Port of Destination</th>
                                <td><?php echo htmlspecialchars($incident['port_destination'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Flag of Registry</th>
                                <td><?php echo htmlspecialchars($incident['flag_registry'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <th>Flag of Convenience</th>
                                <td><?php echo htmlspecialchars($incident['flag_convenience'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                        <?php endif; ?>
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

<!-- JavaScript for handling modal interactions -->
<script>
$(document).ready(function() {
  // View details button click handler
  $('.view-details').on('click', function() {
    const incidentId = $(this).data('id');
    $('#detailsModal' + incidentId).modal('show');
  });
  
  // Handle the update button click
  $('.update-incident').on('click', function() {
    const incidentId = $(this).data('id');
    // Redirect to update page
    window.location.href = 'update_incident.php?id=' + incidentId;
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
                            <option value="IUUF">IUUF</option>
                            <option value="ILLEGAL DRUGS">ILLEGAL DRUGS</option>
                            <option value="SMUGGLING">SMUGGLING</option>
                            <option value="HUMAN TRAFFICKING">HUMAN TRAFFICKING</option>
                            <option value="ITOFP">ITOFP</option>
                            <option value="ARMED ROBBERY">ARMED ROBBERY</option>
                            <option value="CBRNE">CBRNE</option>
                            <option value="MARITIME TERRORISM">MARITIME TERRORISM</option>
                            <option value="PIRACY">PIRACY</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>


                    <div class="form-group">
                        <label for="description">Description</label>
                        <select name="description" id="description" required>
                            <option value="">Select Description</option>
                            <option value="Others">Others (specify)</option>
                        </select>
                    </div>

                     <div class="substation-highlight">
                        <div class="form-group">
                            <label for="substation"><i class="fas fa-map-marker-alt"></i> Substation</label>
                            <select name="substation" id="substation" required>
                                <option value="cawit" selected>cawit</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                       <label for="station">Station</label>
                         <select name="station" id="station" required>
                        <option value="zamboanga" selected>zamboanga</option>
                           </select>
                   </div>

                    <!-- CASE STATUS ADDED HERE -->
                    <div class="form-group">
                        <label for="status">Case Status</label>
                        <select name="status" id="status" required>
                            <option value="">Select Status</option>
                            <option value="FOR FILLING">FOR FILLING</option>
                            <option value="FILED">FILED</option>
                            <option value="CLOSED">CLOSED</option>
                        </select>
                    </div>

                    <!-- Person Status field -->
<div class="form-group">
    <label for="person_status">Person Status</label>
    <select name="person_status" id="person_status">
        <option value="">Select Status</option>
        <option value="APPREHENDED">APPREHENDED</option>
        <option value="VICTIM">VICTIM</option>
    </select>
</div>

<div class="form-group">
    <label for="vessels_status">vessel Status</label>
    <select name="vessels_status" id="vessels_status">
        <option value="">Select Status</option>
        <option value="APPREHENDED">APPREHENDED</option>
        <option value="VICTIM">VICTIM</option>
    </select>
</div>

<div class="form-group" id="WORTH_field" style="display: none;">
    <label for="WORTH_input">Total Worth of Confiscated Items</label>
    <input type="text" name="worth" id="WORTH_input">
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const incidentTypeSelect = document.getElementById('incident_type');
    const worthField = document.getElementById('WORTH_field');

    function toggleWorthField() {
        if (incidentTypeSelect.value === 'IUUF') {
            worthField.style.display = 'block';
            worthField.querySelector('input').setAttribute('required', 'required');
        } else {
            worthField.style.display = 'none';
            worthField.querySelector('input').removeAttribute('required');
            worthField.querySelector('input').value = '';
        }
    }

    // Initial check
    toggleWorthField();

    // Add event listener for changes
    incidentTypeSelect.addEventListener('change', toggleWorthField);
});
</script>
                    <div class="form-group" id="custom_description_group" style="display: none;">
                        <label for="custom_description">Specify Description</label>
                        <input type="text" name="custom_description" id="custom_description">
                    </div>
                    
                    <!-- Smuggling-specific fields -->
                    <div id="smuggling_fields" style="display: none;">
                        <div class="form-group">
                            <label for="goods_vessel_type">Type of Goods/Vessel</label>
                            <select name="goods_vessel_type" id="goods_vessel_type">
                                <option value="">Select Type</option>
                                <option value="CARGO">CARGO</option>
                                <option value="MOTOR TANKER">MOTOR TANKER</option>
                                <option value="CARSHIP">CARSHIP</option>
                                <option value="YACHT">YACHT</option>
                                <option value="OTHERS">OTHERS</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="other_vessel_type_group" style="display: none;">
                            <label for="other_vessel_type">Specify Vessel Type</label>
                            <input type="text" name="other_vessel_type" id="other_vessel_type">
                        </div>
                        
                        <div class="form-group">
                            <label for="port_origin">Port of Origin</label>
                            <input type="text" name="port_origin" id="port_origin">
                        </div>
                        
                        <div class="form-group">
                            <label for="port_destination">Port of Destination</label>
                            <input type="text" name="port_destination" id="port_destination">
                        </div>
                        
                        <div class="form-group">
                            <label for="flag_registry">Flag of Registry</label>
                            <input type="text" name="flag_registry" id="flag_registry">
                        </div>
                        
                        <div class="form-group">
                            <label for="flag_convenience">Flag of Convenience</label>
                            <input type="text" name="flag_convenience" id="flag_convenience">
                        </div>
                    </div>
                    
                    <div id="illegalDrugsFields" style="display:none;">
                        <div class="form-group">
                            <label for="quantity_input">Quantity</label>
                            <input type="text" name="quantity" id="quantity_input">
                        </div>
                        <div class="form-group">
                            <label for="value_input">Value</label>
                            <input type="text" name="value" id="value_input">
                        </div>
                        <div class="form-group">
                            <label for="weight_input">Weight</label>
                            <input type="text" name="weight" id="weight_input">
                        </div>
                    </div>

                    <!-- Human Trafficking-specific fields -->
                    <div id="human_trafficking_fields" style="display: none;">
                        <div class="form-group">
                            <label for="name_input">Name</label>
                            <input type="text" name="name" id="name_input">
                        </div>
                        <div class="form-group">
                            <label for="age_input">Age</label>
                            <input type="text" name="age" id="age_input">
                        </div>
                        <div class="form-group">
                            <label for="birth_date">Birth Date</label>
                            <input type="datetime-local" name="birth_date" id="birth_date">
                        </div>
                        <div class="form-group">
                            <label for="sex_select">Sex</label>
                            <select name="sex" id="sex_select">
                                <option value="">Select Sex</option>
                                <option value="MALE">Male</option>
                                <option value="FEMALE">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="civil_status_select">Civil Status</label>
                            <select name="civil_status" id="civil_status_select">
                                <option value="">Select Civil Status</option>
                                <option value="SINGLE">Single</option>
                                <option value="MARRIED">Married</option>
                                <option value="SEPARATED">Separated</option>
                                <option value="WIDOW">Widow</option>
                                <option value="WIDOWER">Widower</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="citizenship_select">Citizenship</label>
                            <select name="citizenship" id="citizenship_select">
                                <option value="">Select Citizenship</option>
                                <option value="FILIPINO">Filipino</option>
                                <option value="FOREIGNER">Foreigner</option>
                                <option value="OTHERS">Others</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="occupation_input">Occupation</label>
                            <input type="text" name="occupation" id="occupation_input">
                        </div>
                        <div class="form-group">
                            <label for="vessel_type_ht">Vessel Type</label>
                            <select name="vessel_type_ht" id="vessel_type_ht">
                                <option value="">Select Vessel Type</option>
                                <option value="CARGO">CARGO</option>
                                <option value="MOTOR TANKER">MOTOR TANKER</option>
                                <option value="YACHT">YACHT</option>
                                <option value="YACHT">PASSENGER VESSELS</option>
                                <option value="OTHERS">OTHERS</option>
                            </select>
                        </div>
                        <div class="form-group" id="other_vessel_type_ht_group" style="display: none;">
                            <label for="other_vessel_type_ht">Specify Vessel Type</label>
                            <input type="text" name="other_vessel_type_ht" id="other_vessel_type_ht">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="incident_date_time">Date and Time</label>
                        <input type="datetime-local" name="incident_date_time" id="incident_date_time" required>
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


<!-- JavaScript to Populate Description Based on Incident Type -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Define all incident descriptions in one place
        const incidentDescriptions = {
            "IUUF": ["DYNAMITE FISHING", "MODIFIED DANISH SEINE", "CYANIDE FISHING", "COMMERCIAL VESSELS AND MUNICIPAL WATERS", "FAILING TO REPORT CATHCHES", "KEEPING UNDERSIZED FISH", "FISHING IN CLOSE AREAS", "UNAUTHORIZED TRANSSHIPMENT"],
            "ILLEGAL DRUGS": ["Methamphetamine Hydrochloride (Shabu, Ice, Meth)", "Coca leaf and its derivatives: Cocaine", "Marijuana", "Synthetic Cannabinoids"],
            "SMUGGLING": ["RICE SMUGGLING", "FUEL SMUGGLING", "SUGAR SMUGGLING", "CIGARETTE SMUGGLING", "AGRICULTURAL PRODUCTS"],
            "HUMAN TRAFFICKING": ["RESCUED", "APPR"],
            "ITOFP": [""],
            "ARMED ROBBERY": ["BANK ROBBERY", "COMMERCIAL ROBBERY", "ATM ROBBERY", "VIHICLE ROBBERY", "ARMED ROBBERY WITH EDGE WEAPONS"],
            "CBRNE": ["CHEMICAL INCIDENTS", "BIOLOGICAL INCIDENTS", "RADIOLOGICAL INCIDENTS", "NUCLEAR INCIDENTS", "EXPLOSIVES INCIDENTS"],
            "MARITIME TERRORISM": ["BOMBING", "HIJACKING", "HOSTAGE SITUATION"],
            "PIRACY": ["ATTACT PIRACY", "KIDNAP AND RANSOM PIRACY"],
            "Other": ["Others (specify)"]
        };
        
        // Get DOM elements
        const incidentTypeSelect = document.getElementById('incident_type');
        const descriptionSelect = document.getElementById('description');
        const customDescriptionGroup = document.getElementById('custom_description_group');
        const smugglingFields = document.getElementById('smuggling_fields');
        const humanTraffickingFields = document.getElementById('human_trafficking_fields');
        const goodsVesselTypeSelect = document.getElementById('goods_vessel_type');
        const otherVesselTypeGroup = document.getElementById('other_vessel_type_group');
        const vesselTypeHtSelect = document.getElementById('vessel_type_ht');
        const otherVesselTypeHtGroup = document.getElementById('other_vessel_type_ht_group');
        
        // Handle incident type changes
        incidentTypeSelect.addEventListener('change', function() {
            // Hide all specific fields first
            smugglingFields.style.display = 'none';
            humanTraffickingFields.style.display = 'none';
            
           // Show fields based on selected incident type
if (this.value === 'SMUGGLING') {
    smugglingFields.style.display = 'block';
} else {
    smugglingFields.style.display = 'none';
}

if (this.value === 'HUMAN TRAFFICKING') {
    humanTraffickingFields.style.display = 'block';
} else {
    humanTraffickingFields.style.display = 'none';
}

if (this.value === 'ILLEGAL DRUGS') {
    illegalDrugsFields.style.display = 'block';
} else {
    illegalDrugsFields.style.display = 'none';
}


            
            // Update description options based on incident type
            updateDescriptionOptions(this.value);
        });
        
        // Handle description selection
        descriptionSelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                customDescriptionGroup.style.display = 'block';
            } else {
                customDescriptionGroup.style.display = 'none';
            }
        });

        // Format worth input with commas
document.getElementById('WORTH_input').addEventListener('input', function(e) {
    let value = e.target.value.replace(/,/g, '');
    if (!isNaN(value) && value !== '') {
        e.target.value = parseFloat(value).toLocaleString('en-US');
    }
});
        
        // Handle goods/vessel type selection for smuggling
        goodsVesselTypeSelect.addEventListener('change', function() {
            if (this.value === 'OTHERS') {
                otherVesselTypeGroup.style.display = 'block';
            } else {
                otherVesselTypeGroup.style.display = 'none';
            }
        });
        
        // Handle vessel type selection for human trafficking
        vesselTypeHtSelect.addEventListener('change', function() {
            if (this.value === 'OTHERS') {
                otherVesselTypeHtGroup.style.display = 'block';
            } else {
                otherVesselTypeHtGroup.style.display = 'none';
            }
        });
        
        // Function to update description options based on incident type
        function updateDescriptionOptions(incidentType) {
            // Clear existing options
            descriptionSelect.innerHTML = '<option value="">Select Description</option>';
            
            // Add specific options based on incident type
            if (incidentDescriptions[incidentType]) {
                incidentDescriptions[incidentType].forEach(desc => {
                    const option = document.createElement('option');
                    option.value = desc;
                    option.textContent = desc;
                    descriptionSelect.appendChild(option);
                });
                
                // If not "Other", add "Others (specify)" as an option
                if (incidentType !== "Other") {
                    const othersOption = document.createElement('option');
                    othersOption.value = "Others";
                    othersOption.textContent = "Others (specify)";
                    descriptionSelect.appendChild(othersOption);
                }
            }
            
            // Show/hide custom description field
            if (incidentType === "Other") {
                customDescriptionGroup.style.display = "block";
            } else {
                customDescriptionGroup.style.display = "none";
            }
        }
        
        // Camera functionality
        const video = document.getElementById('video');
        const preview = document.getElementById('preview');
        const takePhotoBtn = document.getElementById('take-photo');
        const retakePhotoBtn = document.getElementById('retake-photo');
        const cameraImageData = document.getElementById('camera_image_data');
        
        // Initialize camera
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    video.srcObject = stream;
                    takePhotoBtn.disabled = false;
                })
                .catch(function(error) {
                    console.error('Camera error:', error);
                });
        }
        
        // Take photo button
        takePhotoBtn.addEventListener('click', function() {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const imageData = canvas.toDataURL('image/png');
            preview.src = imageData;
            cameraImageData.value = imageData;
            
            video.style.display = 'none';
            preview.style.display = 'block';
            retakePhotoBtn.disabled = false;
        });
        
        // Retake photo button
        retakePhotoBtn.addEventListener('click', function() {
            video.style.display = 'block';
            preview.style.display = 'none';
            cameraImageData.value = '';
        });
        
        // Incident image preview
        document.getElementById('incident_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '200px';
                    
                    const previewDiv = document.getElementById('incident-preview');
                    previewDiv.innerHTML = '';
                    previewDiv.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Initialize map (placeholder - actual implementation would be needed)
        const mapContainer = document.getElementById('map');
        // Map initialization would go here
        
        // Initial descriptions update if needed
        if (incidentTypeSelect.value) {
            updateDescriptionOptions(incidentTypeSelect.value);
        }
    });
</script>


            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add/Edit Incident Modal -->
    <!-- Your Modal Structure -->
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
                            <option value="IUUF">IUUF</option>
                            <option value="ILLEGAL DRUGS">ILLEGAL DRUGS</option>
                            <option value="SMUGGLING">SMUGGLING</option>
                            <option value="HUMAN TRAFFICKING">HUMAN TRAFFICKING</option>
                            <option value="ITOFP">ITOFP</option>
                            <option value="ARMED ROBBERY">ARMED ROBBERY</option>
                            <Option value="CBRNE">CBRNE</Option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <select name="description" id="description" required>
                            <option value="">Select Description</option>
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

<!-- JavaScript to Populate Description Based on Incident Type -->


    
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
const defaultLocation = [6.900923, 122.087026]; // Default zamboanga City coordinates
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
    
    // Initialize map if we're on the add incident form
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
            
            // Auto-start camera when modal opens
            if (startCameraBtn && !videoStream) {
                startCamera();
            }
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
                    incidentPreview.innerHTML = `<img src="${e.target.result}" style="max-width: 100%; max-height: 300px; border-radius: 5px;">`;
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
    
    // Auto-start camera if available and in the right context
    if (video && startCameraBtn && incidentModal && incidentModal.style.display === 'block') {
        startCamera();
    }
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
            const video = document.getElementById('video');
            const preview = document.getElementById('preview');
            
            video.srcObject = stream;
            videoStream = stream;
            video.style.display = 'block';
            preview.style.display = 'none';
            
            if (document.getElementById('start-camera')) {
                document.getElementById('start-camera').disabled = true;
            }
            if (document.getElementById('take-photo')) {
                document.getElementById('take-photo').disabled = false;
            }
            if (document.getElementById('retake-photo')) {
                document.getElementById('retake-photo').disabled = true;
            }
        })
        .catch(function(err) {
            console.error('Error accessing camera:', err);
            alert('Could not access camera. Please ensure camera permissions are granted.');
        });
}

function takePhoto() {
    if (!videoStream) return;
    
    const video = document.getElementById('video');
    const preview = document.getElementById('preview');
    
    // Create canvas and draw video frame
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Get image data and set to preview
    const imageData = canvas.toDataURL('image/jpeg');
    preview.src = imageData;
    video.style.display = 'none';
    preview.style.display = 'block';
    
    // Set hidden input value
    document.getElementById('camera_image_data').value = imageData;
    
    // Update button states
    if (document.getElementById('take-photo')) {
        document.getElementById('take-photo').disabled = true;
    }
    if (document.getElementById('retake-photo')) {
        document.getElementById('retake-photo').disabled = false;
    }
}

function retakePhoto() {
    const video = document.getElementById('video');
    const preview = document.getElementById('preview');
    
    // Reset preview and restart camera
    video.style.display = 'block';
    preview.style.display = 'none';
    document.getElementById('camera_image_data').value = '';
    
    // If stream was stopped, restart it
    if (!videoStream || videoStream.getTracks()[0].readyState === 'ended') {
        startCamera();
    } else {
        // Just update UI if stream is still active
        if (document.getElementById('take-photo')) {
            document.getElementById('take-photo').disabled = false;
        }
        if (document.getElementById('retake-photo')) {
            document.getElementById('retake-photo').disabled = true;
        }
    }
}

function stopCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
        
        const video = document.getElementById('video');
        if (video) {
            video.srcObject = null;
        }
        
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
                            <p><strong>Person Status:</strong> ${data.person_status || 'Not specified'}</p>
                            <p><strong>Person Status:</strong> ${data.vessels_status || 'Not specified'}</p>
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
        <form action="cawit.php" method="get">
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
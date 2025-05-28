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
       
        $query = "INSERT INTO incidents (INCIDENT_TYPE, description, date_time, report, personnel_name, personnel_image, incident_image, location, latitude, longitude, 
        goods_vessel_type, other_vessel_type, port_origin, port_destination, flag_registry, flag_convenience, name, age, birth_date, sex, civil_status, citizenship, 
        occupation, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $errors[] = "Prepare failed: " . $conn->error;
            return $errors;
        }
        $stmt->bind_param(
            "ssssssssssssssssssssssss", 
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
$query = "SELECT * FROM incidents WHERE INCIDENT_TYPE = 'HUMAN TRAFFICKING' AND date_time BETWEEN ? AND ? ORDER BY date_time DESC";
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
        '1-17' => 0,
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

        if ($age >= 1 && $age <= 17) {
            $data['age_groups']['1-17']++;
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
    '1-17' => ['min' => 1, 'max' => 17],
    '18-25' => ['min' => 18, 'max' => 25],
    '26-35' => ['min' => 26, 'max' => 35],
    '36-50' => ['min' => 36, 'max' => 50],
    '51-70' => ['min' => 51, 'max' => 70],
    '71-85' => ['min' => 71, 'max' => 85],
    '86-100' => ['min' => 86, 'max' => 100]
];

// Initialize counters for each age group and gender combination
$ageStatsMale = [];
$ageStatsFemale = [];
$ageStatsOther = []; // For other or unspecified gender

foreach ($ageGroups as $group => $range) {
    $ageStatsMale[$group] = 0;
    $ageStatsFemale[$group] = 0;
    $ageStatsOther[$group] = 0;
}

// Query to get all incidents with age and sex information
$query = "SELECT age, sex FROM incidents WHERE INCIDENT_TYPE = 'HUMAN TRAFFICKING' AND age IS NOT NULL";
$result = $conn->query($query);

// Count incidents by age group and gender
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $age = intval($row['age']);
        $sex = strtoupper(trim($row['sex'])); // Normalize sex data to uppercase for consistency
        
        foreach ($ageGroups as $group => $range) {
            if ($age >= $range['min'] && $age <= $range['max']) {
                if ($sex == 'MALE') {
                    $ageStatsMale[$group]++;
                } else if ($sex == 'FEMALE') {
                    $ageStatsFemale[$group]++;
                } else {
                    $ageStatsOther[$group]++; // For other or unspecified gender
                }
                break;
            }
        }
    }
}

// Calculate totals by gender
$totalMale = array_sum($ageStatsMale);
$totalFemale = array_sum($ageStatsFemale);
$totalOther = array_sum($ageStatsOther);
$totalAll = $totalMale + $totalFemale + $totalOther;

// Prepare data for the charts
$chartLabels = array_keys($ageGroups);
$chartDataMale = array_values($ageStatsMale);
$chartDataFemale = array_values($ageStatsFemale);
$chartDataOther = array_values($ageStatsOther);

// Convert to JSON for JavaScript
$chartLabelsJSON = json_encode($chartLabels);
$chartDataMaleJSON = json_encode($chartDataMale);
$chartDataFemaleJSON = json_encode($chartDataFemale);
$chartDataOtherJSON = json_encode($chartDataOther);

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
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Human Trafficking Incidents - Age & Gender Statistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="trafficking_statistics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="page-wrapper">
        <header class="dashboard-header">
            <h1 class="dashboard-title">Human Trafficking Incidents - Age & Gender Distribution</h1>
        </header>
        
        <section class="visualization-data-container">
            <div class="visualization-panel">
                <div class="chart-wrapper">
                    <canvas id="ageChart"></canvas>
                </div>
            </div>
            
            <div class="data-panel">
                <table class="data-table">
                    <thead class="data-table-header">
                        <tr>
                            <th>Age Group</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th>Other/Unspecified</th>
                            <th>Total</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($ageGroups as $group => $range) {
                            $groupTotal = $ageStatsMale[$group] + $ageStatsFemale[$group] + $ageStatsOther[$group];
                            $percentage = $totalAll > 0 ? round(($groupTotal / $totalAll) * 100, 2) : 0;
                            echo "<tr>
                                    <td>{$group} years</td>
                                    <td>{$ageStatsMale[$group]}</td>
                                    <td>{$ageStatsFemale[$group]}</td>
                                    <td>{$ageStatsOther[$group]}</td>
                                    <td>{$groupTotal}</td>
                                    <td>{$percentage}%</td>
                                  </tr>";
                        }
                        ?>
                        <tr class="total-row">
                            <td><strong>Total</strong></td>
                            <td><strong><?php echo $totalMale; ?></strong></td>
                            <td><strong><?php echo $totalFemale; ?></strong></td>
                            <td><strong><?php echo $totalOther; ?></strong></td>
                            <td><strong><?php echo $totalAll; ?></strong></td>
                            <td><strong>100%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="visualization-panel">
             <div class="data-panel">
                <div class="summary-section">
                    <h3>Gender Distribution Summary</h3>
                    <div class="summary-stats">
                        <div class="stat-card male">
                            <div class="stat-icon"><i class="fas fa-male"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalMale; ?></div>
                                <div class="stat-label">Male Victims</div>
                                <div class="stat-percentage"><?php echo $totalAll > 0 ? round(($totalMale / $totalAll) * 100, 1) : 0; ?>%</div>
                            </div>
                        </div>
                        
                        <div class="stat-card female">
                            <div class="stat-icon"><i class="fas fa-female"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalFemale; ?></div>
                                <div class="stat-label">Female Victims</div>
                                <div class="stat-percentage"><?php echo $totalAll > 0 ? round(($totalFemale / $totalAll) * 100, 1) : 0; ?>%</div>
                            </div>
                        </div>
                        
                        <div class="stat-card other">
                            <div class="stat-icon"><i class="fas fa-user"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalOther; ?></div>
                                <div class="stat-label">Other/Unspecified</div>
                                <div class="stat-percentage"><?php echo $totalAll > 0 ? round(($totalOther / $totalAll) * 100, 1) : 0; ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
           </div>
        </section>
                     
                </div>
            </div>
            
        
        <div class="navigation-panel">
            <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Back to Incidents</a>
        </div>
    </div>
    
    <script>
        // Create the bar chart using Chart.js
        document.addEventListener('DOMContentLoaded', function() {
            const ageChartCtx = document.getElementById('ageChart').getContext('2d');
       
            
            // Register custom plugin to add data values on top of bars
            Chart.register({
                id: 'chartJsPluginDataLabels',
                afterDatasetsDraw(chart) {
                    if (chart.canvas.id !== 'ageChart') return;
                    
                    const ctx = chart.ctx;
                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        if (!meta.hidden) {
                            meta.data.forEach((element, index) => {
                                // Get value
                                const data = dataset.data[index];
                                if (data === 0) return; // Skip zero values
                                
                                const position = element.getCenterPoint();
                                
                                // Draw value on top of bar
                                ctx.fillStyle = 'white';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';
                                ctx.font = "bold 12px 'Poppins', sans-serif";
                                ctx.fillText(data, position.x, position.y - 15);
                            });
                        }
                    });
                }
            });
            
            // Age Chart with gender breakdown
            const ageChart = new Chart(ageChartCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo $chartLabelsJSON; ?>,
                    datasets: [
                        {
                            label: 'Male',
                            data: <?php echo $chartDataMaleJSON; ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            borderRadius: 5,
                            maxBarThickness: 30
                        },
                        {
                            label: 'Female',
                            data: <?php echo $chartDataFemaleJSON; ?>,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            borderRadius: 5,
                            maxBarThickness: 30
                        },
                        {
                            label: 'Other/Unspecified',
                            data: <?php echo $chartDataOtherJSON; ?>,
                            backgroundColor: 'rgba(153, 102, 255, 0.7)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1,
                            borderRadius: 5,
                            maxBarThickness: 30
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents',
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    family: "'Poppins', sans-serif"
                                }
                            },
                            ticks: {
                                precision: 0,
                                color: 'white',
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 14
                                },
                                // Ensure Y-axis shows integer values only
                                callback: function(value) {
                                    if (Math.floor(value) === value) {
                                        return value;
                                    }
                                }
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Age Group (years)',
                                color: 'white',
                                font: {
                                    weight: 'bold',
                                    family: "'Poppins', sans-serif"
                                }
                            },
                            ticks: {
                                color: 'white',
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 14
                                }
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Human Trafficking Incidents by Age Group',
                            color: 'white',
                            font: {
                                size: 18,
                                weight: 'bold',
                                family: "'Poppins', sans-serif"
                            },
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        legend: {
                            display: true,
                            labels: {
                                color: 'white',
                                font: {
                                    family: "'Poppins', sans-serif"
                                },
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(22, 33, 62, 0.9)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            bodyFont: {
                                size: 14,
                                family: "'Poppins', sans-serif"
                            },
                            padding: 12,
                            cornerRadius: 6,
                            displayColors: false,
                            titleFont: {
                                family: "'Poppins', sans-serif"
                            },
                            callbacks: {
                                label: function(context) {
                                    return `Incidents: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
<style>
/* Base styles */
body {
    background-color: #1a1a2e;
    color: #ffffff;
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    line-height: 1.6;
}
/* Main wrapper */
.page-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.dashboard-header {
    background-color: #16213e;
    border-radius: 15px;
    padding: 25px 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
    border-left: 5px solid #4361ee;
}
.dashboard-title {
    color: #ffffff;
    font-size: 2.2rem;
    font-weight: 700;
    text-align: center;
    margin: 0;
    letter-spacing: 1px;
}
.visualization-panel {
    background-color: #16213e;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    border-left: 5px solid #4361ee;
}
.chart-wrapper {
    background-color: #0f3460;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.data-panel {
    background-color: #16213e;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    border-left: 5px solid #4361ee;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
}
.data-table-header {
    background-color: #4361ee;
}
.data-table-header th {
    color: #ffffff;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.data-table tbody tr {
    background-color: #0f3460;
    transition: background-color 0.3s ease;
}
.data-table tbody tr:nth-child(odd) {
    background-color: #1a1a2e;
}
.data-table tbody tr:hover {
    background-color: #283d66;
}
.data-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #2a3c6b;
}
.total-row {
    background-color: #4361ee !important;
    color: #ffffff;
    font-weight: 600;
}
.total-row td {
    padding: 15px;
}
.navigation-panel {
    text-align: center;
    margin-top: 20px;
    margin-bottom: 30px;
}
.back-button {
    background-color: #4361ee;
    color: #ffffff;
    border: none;
    padding: 12px 30px;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}
.back-button:hover {
    background-color: #3a56d4;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
    color: #ffffff;
    text-decoration: none;
}
.back-button:active {
    transform: translateY(1px);
}
.back-button i {
    margin-right: 8px;
}
@media (max-width: 768px) {
    .dashboard-title {
        font-size: 1.8rem;
    }
    
    .visualization-panel,
    .data-panel,
    .dashboard-header {
        padding: 15px;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px;
        font-size: 14px;
    }
}
canvas {
    max-width: 100%;
    height: 442px;
}
</style>
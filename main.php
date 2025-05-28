<?php
include 'sidebar.html';
include 'dbconnect.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maritime Incident Reporting System</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Base Styles */
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .dashboard {
            padding: 15px;
            margin-left: 250px; /* Sidebar width */
            transition: margin-left 0.3s;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        
        .status-label {
            font-weight: bold;
            color: #333;
        }
        
        .status-value {
            margin-left: 5px;
            color: #007bff;
        }
        
        /* Metrics Container */
        .metrics-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .metric-box {
            background-color: #001f3f;
            color: white;
            border-radius: 8px;
            padding: 15px;
            flex: 1 1 200px;
            min-width: 200px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .metric-box:hover {
            transform: translateY(-5px);
        }
        
        .metric-icon {
            font-size: 24px;
            margin-bottom: 10px;
            color: #66c2ff;
        }
        
        .metric-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .metric-value {
            font-size: 24px;
            color: #66c2ff;
            margin-bottom: 8px;
        }
        
        .metric-values {
            font-size: 20px;
            color: #66c2ff;
            padding-top: 5px;
        }
        
        .case-details {
            margin-top: 10px;
            font-size: 12px;
        }
        
        /* Charts Container */
        .chart-containers {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            width: 100%;
        }
        
        .chart-boxs {
            flex: 1 1 100%;
            min-width: 300px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* Responsive Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Navigation */
        nav ul {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 0;
            list-style: none;
        }
        
        nav li a {
            display: block;
            padding: 8px 15px;
            background: #001f3f;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        nav li a:hover {
            background: #003366;
        }
        
        /* Forms */
        .form-group {
            margin-bottom: 15px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 800px;
            border-radius: 8px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Grid Layout */
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        /* Footer */
        footer {
            background: #001f3f;
            color: white;
            padding: 15px;
            text-align: center;
            margin-left: 250px;
        }
        
        /* Media Queries */
        @media (min-width: 768px) {
            .dashboard {
                padding: 20px;
            }
            
            .grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .metric-box {
                flex: 1 1 250px;
            }
            
            .chart-boxs {
                flex: 1 1 calc(50% - 15px);
            }
        }
        
        @media (min-width: 992px) {
            .dashboard {
                padding: 25px;
            }
            
            .metric-title {
                font-size: 16px;
            }
            
            .metric-value {
                font-size: 28px;
            }
            
            .chart-boxs {
                flex: 1 1 calc(33% - 15px);
            }
        }
        
        @media (max-width: 767px) {
            .dashboard {
                margin-left: 0;
            }
            
            footer {
                margin-left: 0;
            }
            
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1001;
                background: #001f3f;
                color: white;
                border: none;
                padding: 8px 15px;
                border-radius: 4px;
                cursor: pointer;
            }
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
        }
        
        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .dashboard {
                margin-left: 0;
                padding: 0;
            }
            
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle no-print" onclick="toggleSidebar()">☰ Menu</button>
    
    <!-- Rest of your PHP/HTML content remains the same -->
    <?php
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "incidents";
    
    // Improved error handling for database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $incident_types = [
        'Piracy' => 'fa-crosshairs',
        'CBRNE' => 'fa-fish',
        'Smuggling' => 'fa-box-open',
        'Human Trafficking' => 'fa-users',
        'Armed Robbery' => 'fa-oil-can',
        'Maritime Terrorism' => 'fa-bomb',
        'Illegal Drugs' => 'fa-prescription-bottle',
        'ITOFP' => 'fa-trash-alt',
        'IUUF' => 'fa-trash-alt'
    ];
    
    $incidents_by_type = [];
    $incident_counts = [];
    $frequent_data = [];
    
    function getMostFrequent($array) {
        if (empty($array)) {
            return 'N/A';
        }
        $counts = array_count_values($array);
        if (empty($counts)) {
            return 'N/A';
        }
        arsort($counts);
        return key($counts);
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
    
    foreach (array_keys($incident_types) as $type) {
        $stmt = $conn->prepare("SELECT id, description, date_time, personnel_name, location FROM incidents WHERE LOWER(incident_type) = LOWER(?)");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $type);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $incident_counts[$type] = $result->num_rows;
        $locations = [];
        $descriptions = [];
        $incidents_by_type[$type] = [];
    
        while ($row = $result->fetch_assoc()) {
            $incidents_by_type[$type][] = $row;
            $locations[] = $row['location'];
            $descriptions[] = $row['description'];
        }
    
        $frequent_data[$type] = [
            'location' => getMostFrequent($locations),
            'description' => getMostFrequent($descriptions)
        ];
    
        $stmt->close();
    }
    
    // Initialize dashboard data
    $data = [
        'total_fine' => 0,
        'leos' => 0,
        'filing' => 0,
        'filed' => 0,
        'closed' => 0,
        'ap_count' => 0,
        'process_ap' => 0,
        'closed_ap' => 0,
        'ships' => 0,
        'human_trafficking' => [
            'victims' => 0,
            'male' => 0,
            'female' => 0,
            'minor' => 0
        ],
        'chart_data' => [2, 7, 5, 6]
    ];
    
    $leos_by_location = [];
    
    $sql = "SELECT station, substation, COUNT(*) AS total FROM incidents GROUP BY station, substation";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $station = $row['station'] ?: 'Unknown Station';
            $substation = $row['substation'] ?: 'Unknown Substation';
            $key = $station . ' - ' . $substation;
            $leos_by_location[$key] = $row['total'];
        }
        $result->free();
    }
    
    // Get total LEOs count
    $result = $conn->query("SELECT COUNT(*) AS total_leos FROM incidents");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['leos'] = $row['total_leos'] ?? 0;
        $result->free();
    }
    
    // Count incidents with status 'FOR FILLING'
    $result = $conn->query("SELECT COUNT(*) AS filing_count FROM incidents WHERE status = 'FOR FILLING'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['filing'] = $row['filing_count'] ?? 0;
        $result->free();
    }
    
    // Count incidents with status 'FILED'
    $result = $conn->query("SELECT COUNT(*) AS filed_count FROM incidents WHERE status = 'FILED'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['filed'] = $row['filed_count'] ?? 0;
        $result->free();
    }
    
    // Count incidents with status 'CLOSED'
    $result = $conn->query("SELECT COUNT(*) AS closed_count FROM incidents WHERE status = 'CLOSED'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['closed'] = $row['closed_count'] ?? 0;
        $result->free();
    }
    
    // Added missing queries for AP (Apprehended Person) counts
    $result = $conn->query("SELECT COUNT(*) AS ap_count FROM incidents where person_status = 'apprehended'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['ap_count'] = $row['ap_count'];
        $result->free();
    }
    
    $result = $conn->query("SELECT COUNT(*) AS process_ap FROM apprehended_persons WHERE status = 'processing'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['process_ap'] = $row['process_ap'];
        $result->free();
    }
    
    $result = $conn->query("SELECT COUNT(*) AS closed_ap FROM apprehended_persons WHERE status = 'closed'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['closed_ap'] = $row['closed_ap'];
        $result->free();
    }
    
    // Get ship count
    $result = $conn->query("SELECT COUNT(*) AS ships FROM incidents WHERE vessels_status = 'apprehended'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['ships'] = $row['ships'];
        $result->free();
    }
    
    // Calculate total worth from 'worth' column for IUUF category
    $result = $conn->query("SELECT SUM(CAST(worth AS DECIMAL(15,2))) AS total_worth FROM incidents WHERE INCIDENT_TYPE = 'IUUF'");
    if ($result) {
        $row = $result->fetch_assoc();
        $data['total_worth'] = $row['total_worth'] ?? 0;
        $result->free();
    } else {
        $data['total_worth'] = 0;
    }
    
    // Close connection
    $conn->close();
    
    function format_number($number) {
        return number_format($number, 2);
    }
    ?>
    
    <div class="dashboard">
        <div class="metrics-container">
            <!-- IUUF Worth -->
            <div class="metric-box">
                <div class="metric-icon"><i class="fas fa-coins"></i></div>
                <div class="metric-content">
                    <div class="metric-title">IUUF TOTAL WORTH OF CONFISCATED ITEMS</div>
                    <div class="metric-values">₱<?php echo format_number($data['total_worth']); ?></div>
                </div>
            </div>
            
            <!-- LEO's -->
            <div class="metric-box">
                <div class="metric-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="metric-content">
                    <div class="metric-title">Total LEOs</div>
                    <div class="metric-value"><?php echo $data['leos']; ?></div>
                    <div class="case-details">
                        <span class="status-label">Filing:</span>
                        <span class="status-value"><?php echo htmlspecialchars($data['filing'] ?? 0); ?></span><br>
                        <span class="status-label">Filed:</span>
                        <span class="status-value"><?php echo htmlspecialchars($data['filed'] ?? 0); ?></span><br>
                        <span class="status-label">Closed:</span>
                        <span class="status-value"><?php echo htmlspecialchars($data['closed'] ?? 0); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- No. A.P -->
            <div class="metric-box">
                <div class="metric-icon"><i class="fas fa-users"></i></div>
                <div class="metric-content">
                    <div class="metric-title">No. A.P</div>
                    <div class="metric-value"><?php echo $data['ap_count']; ?></div>
                </div>
            </div>
            
            <!-- No. A.V -->
            <div class="metric-box">
                <div class="metric-icon"><i class="fas fa-ship"></i></div>
                <div class="metric-content">
                    <div class="metric-title">No. A.V</div>
                    <div class="metric-value"><?php echo $data['ships']; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Estimated Value Section -->
        <div style="width: 100%; margin-bottom: 20px;">
            <select id="leo-origin" onchange="showLEOStationDetail(this)" style="width: 100%; padding: 8px; border-radius: 4px;">
                <option value="">-- Select Location --</option>
                <?php foreach ($leos_by_location as $location => $count): ?>
                    <option value="<?php echo htmlspecialchars($location); ?>">
                        <?php echo htmlspecialchars($location); ?> (<?php echo $count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="chart-boxs">
            <h3 style="text-align: center; font-size: 20px; color: #333; font-weight: 700;">
                💊 Estimated Value of Illegal Drugs
            </h3>
            <p style="text-align: center; font-size: 16px; margin-top: 10px; color: #000;">
                <strong>Total Estimated Value:</strong> <span style="color: red;">₱ <?php echo number_format($totalEstimatedValue, 2); ?></span>
            </p>
            <canvas id="valuePerDescriptionChart"></canvas>
        </div>
        
        <script>
        // PHP to JS data injection
        const descLabels = <?php echo json_encode($descLabels); ?>;
        const descValues = <?php echo json_encode($descValues); ?>;
        
        // Dynamically generate color palette based on label count
        function generateColors(count, opacity = 1) {
            const baseColors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                '#FF9F40', '#C9CBCF', '#66BB6A', '#D32F2F', '#5C6BC0'
            ];
            return Array.from({ length: count }, (_, i) => {
                const color = baseColors[i % baseColors.length];
                return opacity < 1 ? color.replace(')', `, ${opacity})`).replace('rgb', 'rgba') : color;
            });
        }
        
        function showLEOStationDetail(select) {
            const output = document.getElementById('leo-location-output');
            const value = select.value;
            if (value === "") {
                output.textContent = "";
            } else {
                output.textContent = "Data from: " + value;
            }
        }
        
        const backgroundColors = generateColors(descLabels.length);
        const borderColors = generateColors(descLabels.length);
        
        // Chart initialization
        new Chart(document.getElementById('valuePerDescriptionChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: descLabels,
                datasets: [{
                    label: 'Total Value (₱)',
                    data: descValues,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: ctx => '₱ ' + ctx.parsed.y.toLocaleString()
                        }
                    },
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Estimated Value Per Description',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        anchor: 'center',
                        align: 'center',
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: value => '₱ ' + value.toLocaleString()
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 30,
                            font: {
                                size: 11,
                                weight: 'bold'
                            },
                            callback: function(val, index) {
                                const label = this.getLabelForValue(val);
                                return label.length > 25 ? label.slice(0, 25) + '…' : label;
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => '₱ ' + value.toLocaleString()
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
        </script>
        
        <!-- Crime Category List with Reset Button -->
        <div class="crimes-box" style="margin-bottom: 20px;">
            <h4>Maritime Crime Categories</h4>
            <button id="reset-btn" onclick="hideAllTables()" class="btn">Reset</button>
            <div class="crimes-list" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                <?php foreach ($incident_types as $type => $icon): ?>
                    <?php $safeType = preg_replace('/\s+/', '_', $type); ?>
                    <div class="crime-item" onclick="showCategoryTable('<?= $safeType ?>')" style="background: #001f3f; color: white; padding: 10px; border-radius: 4px; cursor: pointer; flex: 1 1 150px;">
                        <i class="fas <?= $icon ?>"></i> <?= $type ?>
                        <span class="badge"><?= $incident_counts[$type] ?? 0 ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Incident Tables Per Category -->
        <?php
        // Navigation buttons for incident categories
        echo '<div class="incident-category-buttons" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">';
        foreach ($incident_types as $type => $icon): 
            $safeType = preg_replace('/\s+/', '_', $type);
        ?>
            <a href="<?= $safeType ?>.php" class="category-button" style="background: #001f3f; color: white; padding: 10px; border-radius: 4px; text-decoration: none; flex: 1 1 150px;">
                <i class="fa <?= $icon ?>"></i>
                <span><?= $type ?></span>
                <span class="count-badge"><?= $incident_counts[$type] ?></span>
            </a>
        <?php 
        endforeach;
        echo '</div>';
        
        // Original incident table section
        foreach ($incident_types as $type => $icon): 
            $safeType = preg_replace('/\s+/', '_', $type);
            $freq = $frequent_data[$type];
        ?>
            <div id="table_<?= $safeType ?>" class="incident-table-container" style="display: none; margin-bottom: 30px;">
                <div class="freq-info" id="freq_<?= $safeType ?>" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <strong>Most Frequent Location:</strong> <?= htmlspecialchars($freq['location']) ?><br>
                    <strong>Most Frequent Description:</strong> <?= htmlspecialchars($freq['description']) ?><br>
                    <strong>Total <?= $type ?> Incidents:</strong> <?= $incident_counts[$type] ?>
                </div>
                
                <h4><?= $type ?> Incidents</h4>
                <div class="table-responsive">
                    <table class="incident-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Date/Time</th>
                                <th>Personnel Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($incidents_by_type[$type])): ?>
                                <?php foreach ($incidents_by_type[$type] as $incident): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($incident['description']) ?></td>
                                        <td><?= date('F j, Y, g:i A', strtotime($incident['date_time'])) ?></td>
                                        <td><?= htmlspecialchars($incident['personnel_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No incidents reported under <?= $type ?>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Incident Type Distribution Charts -->
        <div class="chart-containers">
            <div class="chart-boxs">
                <h2 style="text-align: center; font-size: 20px;">Incident Type Distribution</h2>
                <canvas id="incidentChart"></canvas>
            </div>
            <div class="chart-boxs">
                <h2 style="text-align: center; font-size: 20px;">Monthly Incident Trends</h2>
                <canvas id="monthlyTrendsChart"></canvas>
            </div>
        </div>
        
        <!-- Include the rest of your content here -->
        <!-- ... -->
        
    </div> <!-- End of dashboard -->
    
    <footer class="sticky-footer">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Copyright &copy; Philippine Coast Guard District Southwestern Mindanao 2025</span>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript for handling responsive behavior -->
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Show/hide category tables
        function showCategoryTable(category) {
            hideAllTables();
            const selectedTable = document.getElementById('table_' + category);
            const freqInfo = document.getElementById('freq_' + category);
        
            if (selectedTable) {
                selectedTable.style.display = 'block';
                selectedTable.scrollIntoView({ behavior: 'smooth' });
            }
            if (freqInfo) {
                freqInfo.style.display = 'block';
            }
        }
        
        function hideAllTables() {
            document.querySelectorAll('.incident-table-container').forEach(table => {
                table.style.display = 'none';
            });
            document.querySelectorAll('.freq-info').forEach(info => {
                info.style.display = 'none';
            });
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Define a consistent color palette
            var colorPalette = [
                'rgba(255, 99, 132, 0.8)',   // red
                'rgba(54, 162, 235, 0.8)',   // blue
                'rgba(75, 192, 192, 0.8)',   // green
                'rgba(255, 159, 64, 0.8)',   // orange
                'rgba(255, 205, 86, 0.8)',   // yellow
                'rgba(153, 102, 255, 0.8)',  // purple
                'rgba(201, 203, 207, 0.8)',  // grey
                'rgba(255, 99, 255, 0.8)',   // pink
                'rgba(99, 255, 132, 0.8)',   // light green
                'rgba(54, 99, 235, 0.8)',    // dark blue
                'rgba(192, 75, 75, 0.8)',    // brown
                'rgba(235, 162, 54, 0.8)'    // bronze
            ];
            
            // Get the chart data and ensure it's properly parsed
            var chartData = <?php echo json_encode($chart_data); ?>;
            
            // Incident Chart (Pie)
            if (document.getElementById('incidentChart')) {
                var ctx = document.getElementById('incidentChart').getContext('2d');
                
                // Extract data from chartData
                var labels = chartData.map(item => item.INCIDENT_TYPE);
                var counts = chartData.map(item => item.count);
                
                // Ensure we have enough colors for all incident types
                var chartColors = [];
                for (var i = 0; i < labels.length; i++) {
                    chartColors.push(colorPalette[i % colorPalette.length]);
                }
                
                new Chart(ctx, {
                    type: 'pie',
                    data: { 
                        labels: labels, 
                        datasets: [{ 
                            data: counts, 
                            backgroundColor: chartColors 
                        }] 
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
            
            // Monthly Trends Chart (Bar)
            if (document.getElementById('monthlyTrendsChart')) {
                var incidentBarCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
                
                // Extract data from chartData (same data as pie chart)
                var labels = chartData.map(item => item.INCIDENT_TYPE);
                var counts = chartData.map(item => item.count);
                
                // Use the same colors for each incident type as in the pie chart
                var barColors = [];
                for (var i = 0; i < labels.length; i++) {
                    barColors.push(colorPalette[i % colorPalette.length]);
                }
                
                new Chart(incidentBarCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Number of Incidents',
                            data: counts,
                            backgroundColor: barColors,
                            borderColor: 'rgba(19, 44, 168, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { 
                            title: { 
                                display: true, 
                                text: 'Monthly Incident Trends' 
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: { 
                            y: { 
                                beginAtZero: true, 
                                ticks: { 
                                    stepSize: 1 
                                } 
                            } 
                        }
                    }
                });
            }
        });
        
        // Make sure all charts resize when window resizes
        window.addEventListener('resize', function() {
            if (window.myCharts) {
                window.myCharts.forEach(chart => {
                    chart.resize();
                });
            }
        });
    </script>
    
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</body>
</html>
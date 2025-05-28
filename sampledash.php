<?php
// Sample data - replace with database connections in production
$data = [
    'total_fine' => 0,
    'leos' => 30,
    'process_leos' => 25,
    'closed_leos' => 5,
    'ap_count' => 17,
    'process_ap' => 14,
    'closed_ap' => 3,
    'ships' => 0,
    'human_trafficking' => [
        'victims' => 0,
        'male' => 0,
        'female' => 0,
        'minor' => 0
    ],
    'estimated_values' => [
        'shabu' => 1241059380,
        'cigarettes' => 0,
        'cocaine' => 0,
        'illegal_drugs' => 0,
        'ukay_ukay' => 0,
        'gasoline' => 0,
        'diesel' => 0,
        'illegal_substance' => 0,
        'marijuana' => 0
    ],
    'chart_data' => [2, 7, 5, 6]
];

// Format numbers with commas
function format_number($number) {
    return number_format($number);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maritime Security Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: #f0f0f0;
        }
        
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #f2efe6;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #e6e2d3;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo {
            width: 70px;
            height: 70px;
            margin-right: 20px;
        }
        
        .title {
            font-size: 32px;
            color: #333;
            font-weight: bold;
        }
        
        .metrics-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .metric-box {
            flex: 1;
            min-width: 200px;
            background-color: #f9f7f0;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
            display: flex;
        }
        
        .metric-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .metric-content {
            flex: 1;
        }
        
        .metric-title {
            font-size: 14px;
            color: #555;
            margin-bottom: 5px;
        }
        
        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        
        .case-details {
            display: flex;
            flex-direction: column;
        }
        
        .case-row {
            display: flex;
            margin-top: 5px;
        }
        
        .case-label {
            font-size: 12px;
            margin-right: 10px;
            width: 70px;
        }
        
        .case-value {
            font-size: 12px;
            font-weight: bold;
        }
        
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter {
            flex: 1;
            padding: 10px;
            background-color: #e6e2d3;
            border-radius: 5px;
            text-align: center;
        }
        
        .content-section {
            margin-bottom: 20px;
            background-color: #f9f7f0;
            padding: 15px;
            border-radius: 5px;
        }
        
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .human-trafficking {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .trafficking-row {
            display: flex;
            align-items: center;
        }
        
        .trafficking-icon {
            width: 40px;
            height: 40px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .trafficking-label {
            font-size: 14px;
            color: #555;
            width: 80px;
        }
        
        .trafficking-value {
            font-size: 22px;
            font-weight: bold;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .value-highlight {
            background-color: #f0f7e6;
            padding: 15px;
            text-align: center;
            margin: 15px auto;
            width: 70%;
            border: 1px solid #ddd;
        }
        
        .highlight-title {
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .highlight-value {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }
        
        .bar-chart {
            display: flex;
            align-items: flex-end;
            height: 200px;
            gap: 20px;
            margin-top: 20px;
        }
        
        .bar {
            flex: 1;
            background-color: #66c2ff;
            margin-bottom: 30px;
            position: relative;
            min-width: 30px;
        }
        
        .bar-label {
            position: absolute;
            bottom: -25px;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
        }
        
        .donut-chart {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            position: relative;
            border-radius: 50%;
            background: conic-gradient(
                #333 0% 25%, 
                #666 25% 50%, 
                #999 50% 75%, 
                #ccc 75% 100%
            );
        }
        
        .donut-hole {
            position: absolute;
            width: 120px;
            height: 120px;
            background-color: #f9f7f0;
            border-radius: 50%;
            top: 40px;
            left: 40px;
        }
        
        .donut-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 10px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            margin-right: 5px;
        }
        
        .two-column {
            display: flex;
            gap: 20px;
        }
        
        .column {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .two-column {
                flex-direction: column;
            }
            
            .metrics-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <div class="logo">
                <img src="maritime_logo.png" alt="Maritime Security Logo" style="width: 100%; height: auto;">
            </div>
            <h1 class="title">MARITIME SECURITY DASHBOARD</h1>
        </div>
        
        <div class="metrics-container">
            <div class="metric-box">
                <div class="metric-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-title">IUUF TOTAL FINE</div>
                    <div class="metric-value"><?php echo $data['total_fine']; ?></div>
                </div>
            </div>
            
            <div class="metric-box">
                <div class="metric-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-title">LEO's</div>
                    <div class="metric-value"><?php echo $data['leos']; ?></div>
                    <div class="case-details">
                        <div class="case-row">
                            <span class="case-label">Process</span>
                            <span class="case-value"><?php echo $data['process_leos']; ?></span>
                        </div>
                        <div class="case-row">
                            <span class="case-label">Closed</span>
                            <span class="case-value"><?php echo $data['closed_leos']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="metric-box">
                <div class="metric-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-title">No. A.P</div>
                    <div class="metric-value"><?php echo $data['ap_count']; ?></div>
                    <div class="case-details">
                        <div class="case-row">
                            <span class="case-label">Process</span>
                            <span class="case-value"><?php echo $data['process_ap']; ?></span>
                        </div>
                        <div class="case-row">
                            <span class="case-label">Closed</span>
                            <span class="case-value"><?php echo $data['closed_ap']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="metric-box">
                <div class="metric-icon">
                    <i class="fas fa-ship"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-title">No. A.P</div>
                    <div class="metric-value"><?php echo $data['ships']; ?></div>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter">
                DISTRICTS <i class="fas fa-chevron-down"></i>
            </div>
            <div class="filter">
                TYPE OF INCIDENTS <i class="fas fa-chevron-down"></i>
            </div>
            <div class="filter">
                MONTH <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        
        <div class="two-column">
            <div class="column">
                <div class="content-section">
                    <h2 class="section-title">FOR HUMAN TRAFFICKING</h2>
                    <div class="human-trafficking">
                        <div class="trafficking-row">
                            <div class="trafficking-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="trafficking-label">No. Victims</div>
                            <div class="trafficking-value"><?php echo $data['human_trafficking']['victims']; ?></div>
                        </div>
                        <div class="trafficking-row">
                            <div class="trafficking-icon">
                                <i class="fas fa-male"></i>
                            </div>
                            <div class="trafficking-label">No. Male</div>
                            <div class="trafficking-value"><?php echo $data['human_trafficking']['male']; ?></div>
                        </div>
                        <div class="trafficking-row">
                            <div class="trafficking-icon">
                                <i class="fas fa-female"></i>
                            </div>
                            <div class="trafficking-label">No. Female</div>
                            <div class="trafficking-value"><?php echo $data['human_trafficking']['female']; ?></div>
                        </div>
                        <div class="trafficking-row">
                            <div class="trafficking-icon">
                                <i class="fas fa-child"></i>
                            </div>
                            <div class="trafficking-label">No. Minor</div>
                            <div class="trafficking-value"><?php echo $data['human_trafficking']['minor']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="column">
                <div class="content-section">
                    <h2 class="section-title">ESTIMATED VALUE</h2>
                    
                    <div class="value-highlight">
                        <div class="highlight-title">SHABU</div>
                        <div class="highlight-value">VALUE: <?php echo format_number($data['estimated_values']['shabu']); ?></div>
                    </div>
                    
                    <div class="bar-chart">
                        <?php
                        $max_value = max($data['estimated_values']);
                        foreach ($data['estimated_values'] as $key => $value):
                            $height = $max_value > 0 ? ($value / $max_value) * 100 : 0;
                            if ($key == 'shabu') {
                                $color = '#66c2ff';
                            } else {
                                $color = '#ddd';
                            }
                        ?>
                        <div class="bar" style="height: <?php echo $height; ?>%; background-color: <?php echo $color; ?>">
                            <div class="bar-label"><?php echo strtoupper($key); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="two-column">
            <div class="column">
                <div class="content-section">
                    <h2 class="section-title">TYPE OF THREATS</h2>
                    <!-- Add your threats content here -->
                </div>
            </div>
            
            <div class="column">
                <div class="content-section">
                    <h2 class="section-title">TYPE OF OPERATIONS</h2>
                    <div style="display: flex; justify-content: center;">
                        <div class="donut-chart">
                            <div class="donut-hole"></div>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <?php
                        $colors = ['#333', '#666', '#999', '#ccc'];
                        $values = $data['chart_data'];
                        for ($i = 0; $i < count($values); $i++):
                        ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: <?php echo $colors[$i]; ?>"></div>
                            <div><?php echo $values[$i]; ?></div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // You can add JavaScript functionality here
        // For example, to make filters interactive or update data dynamically
    </script>
</body>
</html>
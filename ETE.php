
<?php
// Database configuration
$host = "localhost";
$username = "root"; // Change as needed
$password = ""; // Change as needed
$database = "expiration_system"; // Create this database

// Create connection
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add this at the top of your PHP script
if (file_exists('last_update.txt')) {
    $lastUpdate = filemtime('last_update.txt');
    if (time() - $lastUpdate > 86400) { // 24 hours passed
        updateDaysRemaining($conn);
        file_put_contents('last_update.txt', time());
    }
} else {
    updateDaysRemaining($conn);
    file_put_contents('last_update.txt', time());
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `$database`";
if (!$conn->query($sql)) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($database);

// Create table if it doesn't exist (updated with days_remaining and updated_at)
$sql = "CREATE TABLE IF NOT EXISTS personnel (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    ste DATE NOT NULL,
    ete DATE NOT NULL,
    process VARCHAR(255) NOT NULL,
    days_remaining INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if (!$conn->query($sql)) {
    die("Error creating table: " . $conn->error);
}

// Function to update days remaining for all personnel
function updateDaysRemaining($conn) {
    $sql = "UPDATE personnel SET days_remaining = DATEDIFF(ete, CURDATE())";
    if (!$conn->query($sql)) {
        error_log("Error updating days remaining: " . $conn->error);
    }
}

// Update days remaining at the start
updateDaysRemaining($conn);

// Initialize variables
$name = $ste = $ete = $process = "";
$nameErr = $steErr = $eteErr = $processErr = "";
$message = "";
$editMode = false;
$editId = 0;

// Sanitize input function
function test_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Handle form submission for adding personnel
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_personnel"])) {
    // Validate name
    if (empty($_POST["name"])) {
        $nameErr = "Name is required";
    } else {
        $name = test_input($_POST["name"]);
    }

    // Validate STE
    if (empty($_POST["ste"])) {
        $steErr = "Starting Time Enlisted is required";
    } else {
        $ste = test_input($_POST["ste"]);
    }

    // Validate ETE
    if (empty($_POST["ete"])) {
        $eteErr = "Expiration Time Enlisted is required";
    } else {
        $ete = test_input($_POST["ete"]);
        if (!empty($ste) && strtotime($ete) <= strtotime($ste)) {
            $eteErr = "Expiration date must be after starting date";
        }
    }

    // Validate process
    if (empty($_POST["process"])) {
        $processErr = "Process is required";
    } else {
        $process = test_input($_POST["process"]);
    }

    // Insert data into database if valid
    if (empty($nameErr) && empty($steErr) && empty($eteErr) && empty($processErr)) {
        $sql = "INSERT INTO personnel (name, ste, ete, process, days_remaining) VALUES (?, ?, ?, ?, DATEDIFF(?, CURDATE()))";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("sssss", $name, $ste, $ete, $process, $ete);
            if ($stmt->execute()) {
                $message = "New personnel added successfully";
                $name = $ste = $ete = $process = ""; // Clear form fields
            } else {
                $message = "Error executing insert: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "Error preparing insert: " . $conn->error;
        }
    }
}

// Handle form submission for updating personnel
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_personnel"])) {
    $editId = intval($_POST["edit_id"]);

    // Validate name
    if (empty($_POST["edit_name"])) {
        $nameErr = "Name is required";
    } else {
        $name = test_input($_POST["edit_name"]);
    }

    // Validate STE
    if (empty($_POST["edit_ste"])) {
        $steErr = "Starting Time Enlisted is required";
    } else {
        $ste = test_input($_POST["edit_ste"]);
    }

    // Validate ETE
    if (empty($_POST["edit_ete"])) {
        $eteErr = "Expiration Time Enlisted is required";
    } else {
        $ete = test_input($_POST["edit_ete"]);
        if (!empty($ste) && strtotime($ete) <= strtotime($ste)) {
            $eteErr = "Expiration date must be after starting date";
        }
    }

    // Validate process
    if (empty($_POST["edit_process"])) {
        $processErr = "Process is required";
    } else {
        $process = test_input($_POST["edit_process"]);
    }

    // Update data in database if valid
    if (empty($nameErr) && empty($steErr) && empty($eteErr) && empty($processErr)) {
        $sql = "UPDATE personnel SET name=?, ste=?, ete=?, process=?, days_remaining=DATEDIFF(?, CURDATE()) WHERE id=?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("sssssi", $name, $ste, $ete, $process, $ete, $editId);
            if ($stmt->execute()) {
                $message = "Personnel record updated successfully";
                $name = $ste = $ete = $process = ""; // Clear form fields
                $editMode = false;
                $editId = 0;
                // Redirect to clear the GET parameters and show the main page
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $message = "Error executing update: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "Error preparing update: " . $conn->error;
        }
    }
}

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["edit"])) {
    $editId = intval($_GET["edit"]);
    $sql = "SELECT * FROM personnel WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $name = $row["name"];
            $ste = $row["ste"];
            $ete = $row["ete"];
            $process = $row["process"];
            $editMode = true;
        }
        $stmt->close();
    }
}

// Handle delete
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    $sql = "DELETE FROM personnel WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Record deleted successfully";
        } else {
            $message = "Error deleting record: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Error preparing delete: " . $conn->error;
    }
}

// Fetch all records
$sql = "SELECT *, 
        DATEDIFF(ete, ste) AS total_days,
        CONCAT(
            FLOOR(DATEDIFF(ete, ste)/365), ' years, ',
            FLOOR((DATEDIFF(ete, ste)%365)/30), ' months, ',
            (DATEDIFF(ete, ste)%365)%30, ' days'
        ) AS duration
        FROM personnel ORDER BY ete ASC";
$result = $conn->query($sql);
?>

<script>
    function printPersonnelTable() {
        var printContents = document.getElementById("personnelTable").outerHTML;
        var originalContents = document.body.innerHTML;
        // Create a new window for printing
        var printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print Personnel List</title>');
        printWindow.document.write('<style>table { width: 100%; border-collapse: collapse; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #f2f2f2; }</style></head><body>');
        printWindow.document.write('<h1>Personnel List</h1>');
        printWindow.document.write(printContents);
        printWindow.document.write('</body></html>');
        printWindow.document.close(); // Close the document
        printWindow.print(); // Trigger the print dialog
    }
</script>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiration Time Monitoring System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #333;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], input[type="date"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .error {
            color: red;
            font-size: 14px;
        }
        
        .success {
            color: green;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .btn:hover {
            background-color: #45a049;
        }
        
        .btn-cancel {
            background-color: #f44336;
        }
        
        .btn-cancel:hover {
            background-color: #d32f2f;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table, th, td {
            border: 1px solid #ddd;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .action-btn {
            background-color: #f44336;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            margin-right: 5px;
        }
        
        .action-btn:hover {
            background-color: #d32f2f;
        }
        
        .edit-btn {
            background-color: #2196F3;
        }
        
        .edit-btn:hover {
            background-color: #0b7dda;
        }
        
        .expired {
            background-color: #ffebee;
        }
        
        .expiring-soon {
            background-color: #fff8e1;
        }
        
        /* Process color coding */
        .process-process {
            background-color: #c8e6c9; /* Green for Process */
        }
        
        .process-compliance {
            background-color: #fff9c4; /* Yellow for Compliance */
        }
        
        .process-explanation {
            background-color: #ffcdd2; /* Red for Explanation */
        }
        
        /* Tab styling */
        .tab-container {
            margin-top: 20px;
        }
        
        .tab-header {
            display: flex;
            border-bottom: 1px solid #ddd;
        }
        
        .tab-btn {
            background-color: #f1f1f1;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 10px 20px;
            transition: 0.3s;
            font-size: 16px;
            border-radius: 5px 5px 0 0;
        }
        
        .tab-btn:hover {
            background-color: #ddd;
        }
        
        .tab-btn.active {
            background-color: #4CAF50;
            color: white;
        }
        
        .tab-content {
            display: none;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Search styling */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-container input[type="text"] {
            padding: 8px;
            width: 300px;
        }
        
        .search-container select {
            padding: 8px;
            width: 150px;
        }
        
        /* Statistics styling */
        .statistics {
            margin-top: 30px;
        }
        
        .stat-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
        }
        
        .stat-box {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            border-radius: 5px;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-box h3 {
            margin-top: 0;
            font-size: 16px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Expiration Time Monitoring System</h1>
        
        <?php if (!empty($message)) : ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="tab-container">
            <div class="tab-header">
                <button class="tab-btn <?php echo (!$editMode) ? 'active' : ''; ?>" onclick="openTab(event, 'add-tab')">Add Personnel</button>
                <button class="tab-btn" onclick="openTab(event, 'view-tab')">View Personnel</button>
                <button class="tab-btn" onclick="openTab(event, 'stats-tab')">Statistics</button>
                <?php if ($editMode): ?>
                <button class="tab-btn active">Edit Personnel</button>
                <?php endif; ?>
            </div>
            
            <div id="add-tab" class="tab-content <?php echo (!$editMode) ? 'active' : ''; ?>">
                <h2>Add New Personnel</h2>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" value="<?php echo ($editMode) ? '' : $name; ?>">
                        <span class="error"><?php echo (!$editMode) ? $nameErr : ''; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="ste">Starting Time Enlisted (STE):</label>
                        <input type="date" id="ste" name="ste" value="<?php echo ($editMode) ? '' : $ste; ?>">
                        <span class="error"><?php echo (!$editMode) ? $steErr : ''; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="ete">Expiration Time Enlisted (ETE):</label>
                        <input type="date" id="ete" name="ete" value="<?php echo ($editMode) ? '' : $ete; ?>">
                        <span class="error"><?php echo (!$editMode) ? $eteErr : ''; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="process">Process:</label>
                        <select id="process" name="process">
                            <option value="" <?php if(($editMode) ? '' : $process == "") echo "selected"; ?>>-- Select Process --</option>
                            <option value="Process" <?php if((!$editMode) && $process == "Process") echo "selected"; ?>>Process</option>
                            <option value="Compliance" <?php if((!$editMode) && $process == "Compliance") echo "selected"; ?>>Compliance</option>
                            <option value="Explanation" <?php if((!$editMode) && $process == "Explanation") echo "selected"; ?>>Explanation</option>
                        </select>
                        <span class="error"><?php echo (!$editMode) ? $processErr : ''; ?></span>
                    </div>
                    
                    <button type="submit" name="add_personnel" class="btn">Add Personnel</button>
                </form>
            </div>
            
            <?php if ($editMode): ?>
            <div id="edit-tab" class="tab-content active">
                <h2>Edit Personnel</h2>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
                    
                    <div class="form-group">
                        <label for="edit_name">Name:</label>
                        <input type="text" id="edit_name" name="edit_name" value="<?php echo $name; ?>">
                        <span class="error"><?php echo $nameErr; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_ste">Starting Time Enlisted (STE):</label>
                        <input type="date" id="edit_ste" name="edit_ste" value="<?php echo $ste; ?>">
                        <span class="error"><?php echo $steErr; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_ete">Expiration Time Enlisted (ETE):</label>
                        <input type="date" id="edit_ete" name="edit_ete" value="<?php echo $ete; ?>">
                        <span class="error"><?php echo $eteErr; ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_process">Process:</label>
                        <select id="edit_process" name="edit_process">
                            <option value="" <?php if($process == "") echo "selected"; ?>>-- Select Process --</option>
                            <option value="Process" <?php if($process == "Process") echo "selected"; ?>>Process</option>
                            <option value="Compliance" <?php if($process == "Compliance") echo "selected"; ?>>Compliance</option>
                            <option value="Explanation" <?php if($process == "Explanation") echo "selected"; ?>>Explanation</option>
                        </select>
                        <span class="error"><?php echo $processErr; ?></span>
                    </div>
                    
                    <button type="submit" name="update_personnel" class="btn">Update Personnel</button>
                    <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn btn-cancel">Cancel</a>
                </form>
            </div>
            <?php endif; ?>
            
            <div id="view-tab" class="tab-content">
                <h2>Personnel List</h2>
                <div class="search-container">
                    <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Search for names...">
                    <select id="filterProcess" onchange="filterTable()">
                        <option value="">All Processes</option>
                        <option value="Process">Process</option>
                        <option value="Compliance">Compliance</option>
                        <option value="Explanation">Explanation</option>
                    </select>
                    <select id="filterStatus" onchange="filterTable()">
                        <option value="">All Statuses</option>
                        <option value="expired">Expired</option>
                        <option value="expiring-soon">Expiring Soon</option>
                        <option value="active">Active</option>
                    </select>
                    <button onclick="printPersonnelTable()" class="btn">🖨️ Print Table</button>
                </div>

                <table id="personnelTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>STE (Starting Time Enlisted)</th>
                            <th>ETE (Expiration Time Enlisted)</th>
                            <th>Process</th>
                            <th>Duration</th>
                            <th>Days Remaining</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $daysRemaining = isset($row["days_remaining"]) ? $row["days_remaining"] : "N/A";
                            $totalDuration = isset($row["duration"]) ? $row["duration"] : "N/A";

                            $rowClass = "";
                            if ($daysRemaining !== "N/A") {
                                if ((int)$daysRemaining < 0) {
                                    $rowClass = "expired";
                                } elseif ((int)$daysRemaining <= 30) {
                                    $rowClass = "expiring-soon";
                                }
                            }

                            $processClass = "";
                            if (isset($row["process"])) {
                                $process = strtolower($row["process"]);
                                $processClass = "process-" . $process;
                            }

                            echo "<tr class='$rowClass'>";
                            echo "<td>" . (isset($row["id"]) ? htmlspecialchars($row["id"]) : "N/A") . "</td>";
                            echo "<td>" . (isset($row["name"]) ? htmlspecialchars($row["name"]) : "N/A") . "</td>";
                            echo "<td>" . (isset($row["ste"]) ? htmlspecialchars($row["ste"]) : "N/A") . "</td>";
                            echo "<td>" . (isset($row["ete"]) ? htmlspecialchars($row["ete"]) : "N/A") . "</td>";
                            echo "<td class='$processClass'>" . (isset($row["process"]) ? htmlspecialchars($row["process"]) : "N/A") . "</td>";
                            echo "<td>" . htmlspecialchars($totalDuration) . "</td>";
                            echo "<td>" . htmlspecialchars($daysRemaining) . ($daysRemaining !== "N/A" ? " days" : "") . "</td>";
                            echo "<td>
                                    <a href='?edit=" . htmlspecialchars($row["id"]) . "' class='action-btn edit-btn'>Edit</a>
                                    <a href='?delete=" . htmlspecialchars($row["id"]) . "' class='action-btn' onclick='return confirm(\"Are you sure you want to delete this record?\")'>Delete</a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No records found</td></tr>";
                    }
                    if ($result) {
                        $result->data_seek(0); // Reset the pointer for other uses
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            
            <div id="stats-tab" class="tab-content">
                <h2>Statistics</h2>
                <div class="statistics">
                    <?php
                    // Prepare statistics
                    $totalCount = $result ? $result->num_rows : 0;
                    
                    // Count by process
                    $sql = "SELECT process, COUNT(*) as count FROM personnel GROUP BY process";
                    $processStats = $conn->query($sql);
                    $processCounts = [];
                    if ($processStats && $processStats->num_rows > 0) {
                        while ($row = $processStats->fetch_assoc()) {
                            $processCounts[$row['process']] = $row['count'];
                        }
                    }
                    
                    // Count expired records
                    $sql = "SELECT COUNT(*) as count FROM personnel WHERE days_remaining < 0";
                    $expiredStats = $conn->query($sql);
                    $expiredCount = ($expiredStats && $expiredStats->num_rows > 0) ? $expiredStats->fetch_assoc()['count'] : 0;
                    
                    // Count records expiring in 30 days
                    $sql = "SELECT COUNT(*) as count FROM personnel WHERE days_remaining >= 0 AND days_remaining <= 30";
                    $expiringStats = $conn->query($sql);
                    $expiringCount = ($expiringStats && $expiringStats->num_rows > 0) ? $expiringStats->fetch_assoc()['count'] : 0;
                    ?>
                    
                    <div class="stat-container">
                        <div class="stat-box">
                            <h3>Total Personnel</h3>
                            <div class="stat-number"><?php echo $totalCount; ?></div>
                        </div>
                        
                        <div class="stat-box">
                            <h3>Expired</h3>
                            <div class="stat-number expired"><?php echo $expiredCount; ?></div>
                        </div>
                        
                        <div class="stat-box">
                            <h3>Expiring in 30 Days</h3>
                            <div class="stat-number expiring-soon"><?php echo $expiringCount; ?></div>
                        </div>
                        
                        <?php foreach ($processCounts as $processName => $count): ?>
                            <div class="stat-box">
                                <h3><?php echo htmlspecialchars($processName); ?></h3>
                                <div class="stat-number process-<?php echo strtolower(htmlspecialchars($processName)); ?>"><?php echo $count; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add JavaScript for tab switching and filtering -->
    <script>
        // Function to switch between tabs
        function openTab(evt, tabName) {
            var i, tabContent, tabBtns;
            
            // Hide all tab content
            tabContent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabContent.length; i++) {
                tabContent[i].style.display = "none";
            }
            
            // Remove active class from all tab buttons
            tabBtns = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tabBtns.length; i++) {
                tabBtns[i].className = tabBtns[i].className.replace(" active", "");
            }
            
            // Show the current tab and add active class to the button
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
        
        // Function to search the table
        function searchTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("personnelTable");
            tr = table.getElementsByTagName("tr");
            
            for (i = 1; i < tr.length; i++) { // Start from 1 to skip header row
                td = tr[i].getElementsByTagName("td")[1]; // Name column
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
        
        // Function to filter the table by process and status
        function filterTable() {
            var filterProcess, filterStatus, table, tr, tdProcess, i;
            filterProcess = document.getElementById("filterProcess").value;
            filterStatus = document.getElementById("filterStatus").value;
            table = document.getElementById("personnelTable");
            tr = table.getElementsByTagName("tr");
            
            for (i = 1; i < tr.length; i++) { // Start from 1 to skip header row
                tdProcess = tr[i].getElementsByTagName("td")[4]; // Process column
                
                if (tdProcess) {
                    var processValue = tdProcess.textContent || tdProcess.innerText;
                    var rowClass = tr[i].className;
                    var showByProcess = (filterProcess === "" || processValue === filterProcess);
                    var showByStatus = true;
                    
                    if (filterStatus === "expired") {
                        showByStatus = rowClass.includes("expired");
                    } else if (filterStatus === "expiring-soon") {
                        showByStatus = rowClass.includes("expiring-soon");
                    } else if (filterStatus === "active") {
                        showByStatus = !rowClass.includes("expired") && !rowClass.includes("expiring-soon");
                    }
                    
                    if (showByProcess && showByStatus) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
        
        // Make sure the right tab is open on page load
        document.addEventListener("DOMContentLoaded", function() {
            // If there's a URL parameter, open the appropriate tab
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit')) {
                // Edit tab is already active due to PHP
            } else if (urlParams.has('delete')) {
                openTab({currentTarget: document.querySelector(".tab-btn[onclick*='view-tab']")}, 'view-tab');
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>

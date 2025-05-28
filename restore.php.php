<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pcg_marslec";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function storeIncidentData($dataType, $incidentCount) {
    global $conn;
    // Sanitize input
    $dataType = $conn->real_escape_string($dataType);
    $incidentCount = (int)$incidentCount; // Ensure it's an integer

    // Validate incident count
    if ($incidentCount < 0) {
        return json_encode(["status" => "error", "message" => "Incident count must be a non-negative number."]);
    }

    // Determine the table name
    switch ($dataType) {
        case "IUUF":
            $tableName = "iuuf_incidents";
            break;
        case "ILLEGAL DRUGS":
            $tableName = "illegal_drugs_incidents";
            break;
        case "SMUGGLING":
            $tableName = "smuggling_incidents";
            break;
        case "HUMAN TRAFFICKING":
            $tableName = "human_trafficking_incidents";
            break;
        case "ITOFP":
            $tableName = "itofp_incidents";
            break;
        case "ARMED ROBBERY":
            $tableName = "armed_robbery_incidents";
            break;
        default:
            return json_encode(["status" => "error", "message" => "Invalid data type."]);
    }

    // Check if the table exists, if not create it
    $checkTableSql = "SHOW TABLES LIKE '$tableName'";
    $checkTableResult = $conn->query($checkTableSql);

    if ($checkTableResult->num_rows == 0) {
        $createTableSql = "CREATE TABLE $tableName (id INT AUTO_INCREMENT PRIMARY KEY, incident_count INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)";
        if ($conn->query($createTableSql) !== TRUE) {
            return json_encode(["status" => "error", "message" => "Error creating table: " . $conn->error]);
        }
    }

    // Insert a new row with the incident count using prepared statements
    $insertSql = "INSERT INTO $tableName (incident_count) VALUES (?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param("i", $incidentCount);

    if ($stmt->execute()) {
        return json_encode(["status" => "success", "message" => "Incident count for $dataType stored successfully."]);
    } else {
        return json_encode(["status" => "error", "message" => "Error storing incident count: " . $stmt->error]);
    }
}

// Handle form submission for incident data via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "store_incident") {
    $incidentType = $_POST["incident_type"];
    $incidentCount = $_POST["incident_count"];
    echo storeIncidentData($incidentType, $incidentCount);
    exit(); // Stop further PHP execution as this is an AJAX request
}

function displayIncidentData() {
    global $conn;
    $incidentTypes = [
        "IUUF",
        "ILLEGAL DRUGS",
        "SMUGGLING",
        "HUMAN TRAFFICKING",
        "ITOFP",
        "ARMED ROBBERY"
    ];

    $output = "<h2>Current Incident Counts</h2>";
    $output .= "<table border='1'>";
    $output .= "<tr><th>Incident Type</th><th>Total Incidents</th><th>Action</th></tr>";

    foreach ($incidentTypes as $type) {
        // Construct the correct table name based on the incident type
        $tableName = strtolower(str_replace(' ', '_', $type)) . "_incidents";
        $sql = "SELECT ID, incident_count FROM $tableName"; // Fetch the ID and incident count
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $output .= "<tr><td>$type</td><td>" . $row["incident_count"] . "</td><td><a href='?delete_ID=" . $row["ID"] . "&table_name=$tableName'>Delete</a></td></tr>";
            }
        } else {
            $output .= "<tr><td>$type</td><td>0</td><td><a href='?delete_id=0&table_name=$tableName'>Delete</a></td></tr>";
        }
    }
    $output .= "</table>";
    return $output;
}

// Handle request to get updated incident data via AJAX
if (isset($_GET["action"]) && $_GET["action"] == "get_incident_data") {
    echo displayIncidentData();
    exit();
}

// Delete task if delete button is clicked
if (isset($_GET['delete_id']) && isset($_GET['table_name'])) {
    $delete_id = $_GET['delete_id'];
    $tableName = $_GET['table_name'];

    // Ensure the delete_id is an integer
    if (is_numeric($delete_id)) {
        $sql = "DELETE FROM $tableName WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
    }
}

// Fetch tasks from the database
$sql = "SELECT * FROM armed_robbery_incidents"; // This can be adjusted based on your needs
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Date and Time</title>
    <script>
        function updateTime() {
            const now = new Date();
            const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
            const date = now.toLocaleDateString('en-US', options).replace(',', '');
            const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

            document.getElementById('date').innerHTML = "Today is " + date;
            document.getElementById('time').innerHTML = "The time is " + time;
        }

        setInterval(updateTime, 1000); // Update the time every second
    </script>
</head>
<body onload="updateTime()">
    <div id="date"></div>
    <div id="time"></div>

    <div style="text-align: center;">
        <h1>COASTGUARD CAWIT SUB-STATION</h1>
    </div>

    <div class="container">
        <div class="form-container">
            <h1>Incident Data Entry</h1>
            <form id="incidentForm" method="post">
                <input type="hidden" name="action" value="store_incident">
                <label for="incident_type">Select Incident Type:</label>
                <select name="incident_type" id="incident_type">
                    <option value="IUUF">IUUF</option>
                    <option value="ILLEGAL DRUGS">Illegal Drugs</option>
                    <option value="SMUGGLING">Smuggling</option>
                    <option value="HUMAN TRAFFICKING">Human Trafficking</option>
                    <option value="ITOFP">ITOFP</option>
                    <option value="ARMED ROBBERY">Armed Robbery</option>
                </select>
                <br><br>
                <label for="incident_count">Number of Incidents:</label>
                <input type="number" name="incident_count" id="incident_count" required>
                <br><br>
                <button style="color: black; background-color: orangered;" type="submit">SUBMIT</button>
                <div id="formResult"></div>
            </form>
        </div>

        <div id="dataDisplay" class="data-display">
            <?php echo displayIncidentData(); ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const incidentForm = document.getElementById('incidentForm');
            const dataDisplay = document.getElementById('dataDisplay');
            const formResultDiv = document.getElementById('formResult');

            incidentForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent default form submission

                const formData = new FormData(incidentForm);

                fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    formResultDiv.textContent = data.message;
                    if (data.status === 'success') {
                        // Reload the incident data display
                        fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?action=get_incident_data')
                            .then(response => response.text())
                            .then(html => {
                                dataDisplay.innerHTML = html;
                            })
                            .catch(error => {
                                console.error('Error fetching updated data:', error);
                            });
                        // Optionally clear the form
                        incidentForm.reset();
                    } else if (data.status === 'error') {
                        console.error('Error storing data:', data.message);
                    }
                })
                .catch(error => {
                    console.error('There was an error with the AJAX request:', error);
                    formResultDiv.textContent = 'An error occurred while storing data.';
                });
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>
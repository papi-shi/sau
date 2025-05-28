<style>
    body {
        background-color: #001f3f;
        color: #ffffff;
        font-family: 'Segoe UI', sans-serif;
        padding: 20px;
    }

    .incident-table-container {
        border: 3px solid orange;
        border-radius: 12px;
        padding: 10px;
        overflow-x: auto;
        position: relative;
        animation: lights 3s infinite linear;
    }

    @keyframes lights {
        0% { box-shadow: 0 0 10px orange; }
        50% { box-shadow: 0 0 20px #ff851b; }
        100% { box-shadow: 0 0 10px orange; }
    }

    table.incident-table {
        width: 100%;
        border-collapse: collapse;
        background-color: #0c2e4c;
    }

    .incident-table th, .incident-table td {
        border: 1px solid #ff851b;
        padding: 10px;
        text-align: left;
    }

    .incident-table th {
        background-color: #003366;
        color: orange;
    }

    .incident-table tr:hover {
        background-color: rgba(255, 133, 27, 0.2);
    }

    .incident-image, .personnel-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 6px;
    }
</style>

<div class="incident-table-container">
    <table class="incident-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Incident Type</th>
                <th>Description</th>
                <th>Date & Time</th>
                <th>Report</th>
                <th>Personnel</th>
                <th>Personnel Photo</th>
                <th>Incident Photo</th>
                <th>Location</th>
                <th>Coordinates</th>
                <th>Submitted At</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $conn = connectDB();
            $result = $conn->query("SELECT * FROM incidents ORDER BY date_time DESC");
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['INCIDENT_TYPE']}</td>";
                echo "<td>{$row['description']}</td>";
                echo "<td>" . date("M d, Y h:i A", strtotime($row['date_time'])) . "</td>";
                echo "<td>{$row['report']}</td>";
                echo "<td>{$row['personnel_name']}</td>";
                echo "<td><img src='{$row['personnel_image']}' class='personnel-image'></td>";
                echo "<td><img src='{$row['incident_image']}' class='incident-image'></td>";
                echo "<td>{$row['location']}</td>";
                echo "<td>{$row['latitude']}, {$row['longitude']}</td>";
                echo "<td>" . date("M d, Y h:i A", strtotime($row['created_at'])) . "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

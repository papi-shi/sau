<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'incident_db');

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $conn->real_escape_string($_POST['role']);
    $station_name = $conn->real_escape_string($_POST['station_name']);
    $personnel_name = $conn->real_escape_string($_POST['personnel_name']);
    $rank = $conn->real_escape_string($_POST['rank']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    // Check if username already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $_SESSION['message'] = "Username already exists. Please choose another.";
        $_SESSION['message_type'] = "danger";
    } else {
        // Insert new user with pending status
        $stmt = $conn->prepare("INSERT INTO users (role, station_name, personnel_name, rank, username, password, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssssss", $role, $station_name, $personnel_name, $rank, $username, $password);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Account created successfully! Please wait for administrator approval.";
            $_SESSION['message_type'] = "success";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION['message'] = "Error creating account: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        
        .account-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 100%;
            max-width: 500px;
        }
        
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: #007bff;
            outline: none;
        }
        
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #0056b3;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: #007bff;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .role-fields {
            display: none;
        }
        
        .role-fields.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="account-container">
        <h2>Create Account</h2>
        
        <?php
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
        ?>
        
        <form action="create_account.php" method="POST">
            <div class="form-group">
                <label for="role">Account Type</label>
                <select name="role" id="role" required onchange="toggleRoleFields()">
                    <option value="">Select Account Type</option>
                    <option value="station">Station</option>
                    <option value="substation">Substation</option>
                </select>
            </div>
            
            <div id="station-fields" class="role-fields">
                <div class="form-group">
                    <label for="station_name">Station Name</label>
                    <input type="text" name="station_name" id="station_name">
                </div>
            </div>
            
            <div id="substation-fields" class="role-fields">
                <div class="form-group">
                    <label for="substation_name">Substation Name</label>
                    <input type="text" name="station_name" id="substation_name">
                </div>
            </div>
            
            <div class="form-group">
                <label for="personnel_name">Personnel Name</label>
                <input type="text" name="personnel_name" id="personnel_name" required>
            </div>
            
            <div class="form-group">
                <label for="rank">Rank/Position</label>
                <input type="text" name="rank" id="rank" required>
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            
            <button type="submit">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    
    <script>
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            
            // Hide all role fields
            document.querySelectorAll('.role-fields').forEach(field => {
                field.classList.remove('active');
            });
            
            // Show fields for selected role
            if (role === 'station') {
                document.getElementById('station-fields').classList.add('active');
                document.getElementById('station_name').required = true;
                document.getElementById('substation_name').required = false;
            } else if (role === 'substation') {
                document.getElementById('substation-fields').classList.add('active');
                document.getElementById('substation_name').required = true;
                document.getElementById('station_name').required = false;
            } else {
                document.getElementById('station_name').required = false;
                document.getElementById('substation_name').required = false;
            }
        }
    </script>
</body>
</html>
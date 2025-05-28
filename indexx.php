<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Secure Login</title>
  <link rel="stylesheet" href="styless.css">
</head>
<body>
  <div class="login-container">
    <form id="loginForm" action="login.php" method="POST">
      <h2>Login</h2>
      
      <div class="input-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
      </div>

      <div class="input-group password-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <span class="toggle-password" onclick="togglePassword()">👁️</span>
      </div>

      <div class="options">
        <label><input type="checkbox" name="remember"> Remember Me</label>
      </div>

      <button type="submit">Login</button>

      <p class="error" id="errorMsg"></p>
    </form>
  </div>

  <script src="script.js"></script>
</body>
</html>

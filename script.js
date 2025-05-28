function togglePassword() {
  const passwordInput = document.getElementById("password");
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
  } else {
    passwordInput.type = "password";
  }
}

// Optional client-side validation
document.getElementById("loginForm").addEventListener("submit", function (e) {
  const username = document.getElementById("username").value.trim();
  const password = document.getElementById("password").value.trim();

  if (!username || !password) {
    e.preventDefault();
    document.getElementById("errorMsg").textContent = "Please fill in all fields.";
  }
});

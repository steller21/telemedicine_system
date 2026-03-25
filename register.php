<?php
include("config/db.php");

if (isset($_POST['register'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = md5($_POST['password']);
    $role = $_POST['role'];

    // Check if email already exists
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");

    if ($check->num_rows > 0) {
        echo "⚠️ Email already exists!";
    } else {

        $sql = "INSERT INTO users (name, email, password, role) 
                VALUES ('$name', '$email', '$password', '$role')";

        if ($conn->query($sql) === TRUE) {
            echo "✅ Registered Successfully!";
        } else {
            echo "❌ Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>

<h2>Register</h2>

<form method="POST">
    <input type="text" name="name" placeholder="Enter Name" required><br><br>
    
    <input type="email" name="email" placeholder="Enter Email" required><br><br>
    
    <input type="password" name="password" placeholder="Enter Password" required><br><br>

    <select name="role">
        <option value="patient">Patient</option>
        <option value="doctor">Doctor</option>
    </select><br><br>

    <button type="submit" name="register">Register</button>
</form>

</body>
</html>
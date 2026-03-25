<?php
session_start();
require_once("config/db.php");

if (isset($_POST['login'])) {

    $email = $_POST['email'];
    $password = md5($_POST['password']);

    $sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        if ($user['role'] == 'patient') {
            header("Location: patient/dashboard.php");
        } else {
            header("Location: doctor/dashboard.php");
        }

    } else {
        echo "❌ Invalid Email or Password!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>

<h2>Login</h2>

<form method="POST">
    <input type="email" name="email" placeholder="Enter Email" required><br><br>
    
    <input type="password" name="password" placeholder="Enter Password" required><br><br>

    <button type="submit" name="login">Login</button>
</form>

</body>
</html>
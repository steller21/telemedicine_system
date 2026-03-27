<?php
require_once("config/db.php");

if (isset($_POST['register'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $check = $stmt->get_result();

    if ($check->num_rows > 0) {
        echo "⚠️ Email already exists!";
    } else {
        // Insert into users
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            // Insert into patients or doctors
            if ($role == 'patient') {
                $p_stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                $p_stmt->bind_param("i", $user_id);
                $p_stmt->execute();
            }

            if ($role == 'doctor') {
                $d_stmt = $conn->prepare("INSERT INTO doctors (user_id) VALUES (?)");
                $d_stmt->bind_param("i", $user_id);
                $d_stmt->execute();
            }

            echo "✅ Registered Successfully!";
        } else {
            echo "❌ Registration failed. Please try again.";
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
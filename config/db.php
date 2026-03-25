<?php
$conn = new mysqli("localhost", "root", "", "telemedicine_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
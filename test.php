<?php
require 'config/db.php';
$conn->query('ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL');
echo "Done";
?>

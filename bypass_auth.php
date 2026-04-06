<?php
session_start();
$_SESSION['user_id'] = 1; // Assuming user ID 1 exists and is a student for the test
$_SESSION['role'] = 'siswa';
header('Location: siswa/kartu.php');

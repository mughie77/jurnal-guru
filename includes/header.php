<?php
// Ini akan menjadi header standar untuk semua halaman yang memerlukan otentikasi.
// auth.php akan dipanggil sebelum file ini di setiap halaman role.
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aplikasi Jurnal Mengajar</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* General styling */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }

        /* Sidebar styling for Admin/Waka */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            padding: 1rem;
            background-color: #212529; /* Dark */
            color: #fff;
            transition: all 0.3s;
            z-index: 1030;
        }

        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: #343a40;
        }

        .sidebar .nav-link .fa {
            margin-right: 10px;
        }

        .sidebar-header {
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #495057;
            text-align: center;
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        /* Toggle Styles */
        body.sidebar-toggled .sidebar {
            margin-left: -260px;
        }
        body.sidebar-toggled .main-content {
            margin-left: 0;
        }

        /* Card styling for Guru Dashboard */
        .guru-dashboard .card-menu {
            text-decoration: none;
            color: #212529;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .guru-dashboard .card-menu:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15);
        }

        .guru-dashboard .card-icon {
            font-size: 4rem;
            color: #0d6efd;
        }

        /* General Card */
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,0.05);
        }

        #sidebarToggle {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.25rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                left: -260px; /* Hidden by default */
                margin-left: 0;
            }
            body.sidebar-toggled .sidebar {
                left: 0;
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar akan dimasukkan di sini jika diperlukan -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Konten utama akan dimulai di sini -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggle" class="btn btn-link">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($_SESSION['nama_lengkap']); ?></span>
                                <i class="fa fa-user-circle"></i>
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="<?= BASE_URL ?>logout.php">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">
                    <!-- Begin Page Content -->
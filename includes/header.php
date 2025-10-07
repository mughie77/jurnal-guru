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
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            padding: 1rem;
            background-color: #212529;
            color: #fff;
            overflow-y: auto;
            z-index: 1040; /* Pastikan di atas overlay */
            transition: transform 0.3s ease-in-out;
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
            transition: margin-left 0.3s ease-in-out;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1030;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .sidebar-overlay.show {
                display: block;
            }
            .guru-dashboard .main-content {
                 margin-left: 0 !important;
            }
        }

        /* Guru Dashboard specific overrides */
         .guru-dashboard .main-content {
            margin-left: 0;
        }
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
    </style>
</head>
<body class="<?= strpos($_SERVER['REQUEST_URI'], 'guru/index.php') !== false ? 'guru-dashboard' : '' ?>">
    <div class="sidebar-overlay"></div>
    <div id="wrapper">
        <!-- Sidebar akan dimasukkan di sini jika diperlukan -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <!-- Sidebar Toggle (tampak di mobile) -->
                    <button id="sidebarToggle" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Guest'); ?></span>
                                <i class="fa fa-user-circle"></i>
                            </a>
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
                    <!-- Konten dimulai di sini -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (sidebarToggle && sidebar && overlay) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
});
</script>
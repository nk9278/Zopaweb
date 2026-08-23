<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZopaWeb Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">

<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar bg-dark text-white p-3 min-vh-100" style="width: 250px;">
        <h4 class="mb-4 text-center fw-bold">ZopaWeb Admin</h4>
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a href="/admin/dashboard.php" class="nav-link text-white"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            </li>
            <li class="nav-item mb-2">
                <a href="/admin/users.php" class="nav-link text-white"><i class="bi bi-people me-2"></i>Users</a>
            </li>
            <li class="nav-item mb-2">
                <a href="/admin/websites.php" class="nav-link text-white"><i class="bi bi-globe me-2"></i>Websites</a>
            </li>
            <li class="nav-item mb-2">
                <a href="/admin/template_categories.php" class="nav-link text-white"><i class="bi bi-tags me-2"></i>Categories</a>
            </li>
            <li class="nav-item mb-2">
                <a href="/admin/templates.php" class="nav-link text-white"><i class="bi bi-palette me-2"></i>Templates</a>
            </li>
            <li class="nav-item mb-2">
                <a href="/admin/settings.php" class="nav-link text-white"><i class="bi bi-gear me-2"></i>Settings</a>
            </li>
            <li class="nav-item mt-4">
                <a href="/auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4">
            <div class="container-fluid">
                <span class="navbar-brand mb-0 h1">Admin Dashboard</span>
                <div class="d-flex align-items-center">
                    <span class="me-3">Hello, <?= escape($_SESSION['name'] ?? 'Admin') ?></span>
                </div>
            </div>
        </nav>
        <div class="p-4">
            <?php display_flash_message(); ?>

<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
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
<body class="admin-body">

<div class="admin-wrapper d-flex">

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay d-lg-none" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside class="admin-sidebar d-flex flex-column flex-shrink-0 text-white" id="adminSidebar">
        <div class="sidebar-header d-flex align-items-center p-3 border-bottom border-secondary">
            <i class="bi bi-gem text-primary fs-4 me-2"></i>
            <span class="fs-5 fw-bold brand-text">ZopaWeb Admin</span>
            <button class="btn btn-sm btn-link text-white d-lg-none ms-auto p-0" id="closeSidebarBtn">
                <i class="bi bi-x-lg fs-5"></i>
            </button>
        </div>

        <div class="sidebar-scrollable flex-grow-1 p-3">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="/admin/dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : 'text-white' ?>">
                        <i class="bi bi-grid-1x2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item mt-3 mb-1"><small class="text-uppercase text-secondary fw-bold px-3">Management</small></li>
                <li>
                    <a href="/admin/users.php" class="nav-link <?= strpos($current_page, 'user') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-people"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/websites.php" class="nav-link <?= strpos($current_page, 'website') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-globe"></i>
                        <span>Websites</span>
                    </a>
                </li>

                <li class="nav-item mt-3 mb-1"><small class="text-uppercase text-secondary fw-bold px-3">Catalog</small></li>
                <li>
                    <a href="/admin/templates.php" class="nav-link <?= strpos($current_page, 'template') !== false && strpos($current_page, 'categories') === false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-palette"></i>
                        <span>Templates</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/template_categories.php" class="nav-link <?= strpos($current_page, 'template_categories') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>

                <li class="nav-item mt-3 mb-1"><small class="text-uppercase text-secondary fw-bold px-3">Billing</small></li>
                <li>
                    <a href="/admin/subscriptions.php" class="nav-link <?= strpos($current_page, 'subscription') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-card-checklist"></i>
                        <span>Subscriptions</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/payments.php" class="nav-link <?= strpos($current_page, 'payment') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-receipt"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/domains.php" class="nav-link <?= strpos($current_page, 'domain') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-hdd-network"></i>
                        <span>Domains</span>
                    </a>
                </li>

                <li class="nav-item mt-3 mb-1"><small class="text-uppercase text-secondary fw-bold px-3">Ecosystem</small></li>
                <li>
                    <a href="/admin/creators.php" class="nav-link <?= strpos($current_page, 'creator') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-stars"></i>
                        <span>Creators</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/commissions.php" class="nav-link <?= strpos($current_page, 'commission') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-wallet2"></i>
                        <span>Commissions</span>
                    </a>
                </li>

                <li class="nav-item mt-3 mb-1"><small class="text-uppercase text-secondary fw-bold px-3">System</small></li>
                <li>
                    <a href="/admin/enquiries.php" class="nav-link <?= strpos($current_page, 'enquir') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-envelope"></i>
                        <span>Enquiries</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/activity_logs.php" class="nav-link <?= strpos($current_page, 'activity_log') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/settings.php" class="nav-link <?= strpos($current_page, 'settings') !== false ? 'active' : 'text-white' ?>">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer p-3 border-top border-secondary">
            <a href="/auth/logout.php" class="nav-link text-danger d-flex align-items-center">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="admin-main-content flex-grow-1 d-flex flex-column">

        <!-- Topbar -->
        <header class="admin-topbar bg-white shadow-sm px-3 py-2 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-lg-none me-2" id="openSidebarBtn">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="d-none d-md-block text-muted small fw-medium text-uppercase tracking-wider">
                    ZopaWeb Management
                </div>
            </div>

            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 32px; height: 32px; font-size: 14px;">
                            <?= substr(escape($_SESSION['name'] ?? 'A'), 0, 1) ?>
                        </div>
                        <span class="d-none d-md-inline text-dark fw-medium"><?= escape($_SESSION['name'] ?? 'Admin') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/admin/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="p-4 flex-grow-1 overflow-auto bg-light">
            <?php display_flash_message(); ?>

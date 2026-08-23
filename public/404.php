<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - ZopaWeb</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .error-page {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .error-content {
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <h1 class="display-1 fw-bold text-primary">404</h1>
            <h3 class="mb-4">Oops! This page doesn't exist.</h3>
            <p class="text-muted mb-4">The page you are looking for might have been removed or is temporarily unavailable.</p>
            <div>
                <a href="javascript:history.back()" class="btn btn-outline-secondary me-2">Go Back</a>
                <?php if (is_logged_in()): ?>
                    <a href="<?= has_role('admin') ? '/admin/dashboard.php' : '/user/dashboard.php' ?>" class="btn btn-primary">Go to Dashboard</a>
                <?php else: ?>
                    <a href="/auth/login.php" class="btn btn-primary">Log In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

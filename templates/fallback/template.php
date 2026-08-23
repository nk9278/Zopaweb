<?php
// templates/fallback/template.php
http_response_code(503);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Unavailable</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; color: #343a40; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; text-align: center; }
        .container { max-width: 600px; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; margin-top: 0; }
        p { color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Under Maintenance</h1>
        <p>This website is currently being updated or is temporarily unavailable. Please check back later.</p>
    </div>
</body>
</html>

<?php
session_start();
require_once '../includes/encryption.php';

// Mock DB read/write for settings for the scope of this file
function get_setting($key) {
    $file = '../storage/' . $key . '.txt';
    return file_exists($file) ? file_get_contents($file) : null;
}

function update_setting($key, $value) {
    file_put_contents('../storage/' . $key . '.txt', $value);
}

$error = '';
$success = '';
$status = 'Not Configured';

// Handle legacy migration on load if a key is provided
try {
    $current_token = get_setting('hostinger_api_token');
    if ($current_token && strpos($current_token, 'v1:') !== 0) {
        // Legacy plaintext token found, attempt migration
        $encrypted = encrypt_secret($current_token);
        update_setting('hostinger_api_token', $encrypted);
        $current_token = $encrypted;
    }
} catch (Exception $e) {
    if ($current_token && strpos($current_token, 'v1:') !== 0) {
        $error = "SECRET MIGRATION = BLOCKED — ENCRYPTION KEY REQUIRED. Legacy token retained but not encrypted.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_token') {
        $new_token = trim($_POST['api_token']);

        if (!empty($new_token)) {
            try {
                $encrypted_token = encrypt_secret($new_token);
                update_setting('hostinger_api_token', $encrypted_token);
                $success = "Token updated successfully.";
            } catch (Exception $e) {
                $error = "Failed to encrypt token: " . htmlspecialchars($e->getMessage());
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'test_connection') {
        try {
            $token_payload = get_setting('hostinger_api_token');
            if (!$token_payload) {
                $status = 'Not Configured';
            } else {
                // We decrypt here only for the API call
                $decrypted_token = decrypt_secret($token_payload);

                // Mock API call
                if ($decrypted_token) {
                     $status = 'Connected';
                     $success = "Connection test passed.";
                } else {
                     $status = 'Authentication Failed';
                     $error = "Token is empty.";
                }

                // Clear from memory explicitly
                unset($decrypted_token);
            }
        } catch (Exception $e) {
            $status = 'Authentication Failed';
            $error = htmlspecialchars($e->getMessage());
        }
    }
}

$has_token = false;
$token_payload = get_setting('hostinger_api_token');
if ($token_payload) {
    if (strpos($token_payload, 'v1:') === 0) {
        $has_token = true; // Properly configured
    } else {
        // Unmigrated legacy token
        $has_token = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hostinger Integration</title>
</head>
<body>
    <h1>Hostinger Integration</h1>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?php echo $success; ?></p>
    <?php endif; ?>

    <p>Status: <strong><?php echo htmlspecialchars($status); ?></strong></p>

    <form method="POST">
        <label>API Token:</label>
        <?php if ($has_token): ?>
            <div>Configured: **************</div>
            <p>Leave blank to keep existing token.</p>
        <?php endif; ?>
        <input type="text" name="api_token" value="" autocomplete="off">
        <button type="submit" name="action" value="update_token">Update Token</button>
    </form>

    <form method="POST" style="margin-top: 20px;">
        <button type="submit" name="action" value="test_connection">Test Connection</button>
    </form>
</body>
</html>

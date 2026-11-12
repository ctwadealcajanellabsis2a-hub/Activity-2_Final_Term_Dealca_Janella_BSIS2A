<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = isset($_POST['form_type']) ? $_POST['form_type'] : '';

    if ($form_type === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (!empty($username) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'approved') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Your account is pending admin approval or has been rejected.";
                }
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Please fill in all fields.";
        }
        $action = 'login';
    } 
    elseif ($form_type === 'register') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (!empty($username) && !empty($password) && !empty($confirm_password)) {
            if ($password === $confirm_password) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                
                if ($stmt->rowCount() > 0) {
                    $error = "Username is already taken.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $status = 'pending'; 
                    $role = 'customer';

                    $insertStmt = $pdo->prepare("INSERT INTO users (username, password, status, role) VALUES (?, ?, ?, ?)");
                    if ($insertStmt->execute([$username, $hashed_password, $status, $role])) {
                        $success = "Registration successful! Your account is pending admin approval.";
                    } else {
                        $error = "Something went wrong. Please try again.";
                    }
                }
            } else {
                $error = "Passwords do not match.";
            }
        } else {
            $error = "Please fill in all fields.";
        }
        $action = 'register';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coffee Shop - <?= $action === 'register' ? 'Register' : 'Sign In' ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php if ($action === 'register'): ?>
            <h2>☕ Create Account</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="index.php?action=register" method="POST">
                <input type="hidden" name="form_type" value="register">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit">Register</button>
            </form>

            <div class="switch-link">
                Already have an account? <a href="index.php?action=login">Sign In</a>
            </div>

        <?php else: ?>
            <h2>☕ Coffee Shop Login</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="index.php?action=login" method="POST">
                <input type="hidden" name="form_type" value="login">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit">Sign In</button>
            </form>

            <div class="switch-link">
                Don't have an account? <a href="index.php?action=register">Register here</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
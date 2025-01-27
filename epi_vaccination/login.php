<?php
session_start();
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $userType = $_POST['userType'];

    if ($password == "1") { // Simple password check for testing
        switch($userType) {
            case 'patient':
                $stmt = $pdo->prepare("SELECT * FROM Child WHERE child_name = ? OR child_id = ?");
                break;
            case 'doctor':
            case 'nurse':
                $stmt = $pdo->prepare("SELECT * FROM Healthcare_Professional WHERE (name = ? OR id = ?) AND role = ?");
                break;
            case 'admin':
                if ($username == "admin") {
                    $_SESSION['user_type'] = 'admin';
                    header("Location: admin_dashboard.php");
                    exit();
                }
                break;
        }

        if (isset($stmt)) {
            if ($userType == 'doctor' || $userType == 'nurse') {
                $stmt->execute([$username, $username, ucfirst($userType)]);
            } else {
                $stmt->execute([$username, $username]);
            }
            
            if ($row = $stmt->fetch()) {
                $_SESSION['user_id'] = $row[0];
                $_SESSION['username'] = $userType == 'patient' ? $row['child_name'] : $row['name'];
                $_SESSION['user_type'] = $userType;
                
                header("Location: {$userType}_dashboard.php");
                exit();
            }
        }
    }
    $error = "Invalid credentials";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - EPI Vaccination System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(52, 73, 94, 0.25);
            border-color: #34495e;
        }
        .btn-login {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: bold;
            width: 100%;
            margin-top: 15px;
        }
        .btn-login:hover {
            background: #34495e;
            color: white;
        }
        .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ddd;
        }
        .input-group-text {
            background: #f8f9fa;
            border-radius: 10px 0 0 10px;
            border: 1px solid #ddd;
            border-right: none;
        }
        .input-group .form-control {
            border-radius: 0 10px 10px 0;
            margin-bottom: 0;
        }
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .system-title {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        .login-subtitle {
            font-size: 1rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2 class="system-title">
                    <i class="fas fa-syringe"></i> EPI Vaccination System
                </h2>
                <div class="login-subtitle">Login to access your account</div>
            </div>
            <div class="login-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Username or ID:
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-id-card"></i>
                            </span>
                            <input type="text" name="username" class="form-control" 
                                   placeholder="Enter name or ID" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password:
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter password" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-user-tag"></i> Login as:
                        </label>
                        <select name="userType" class="form-select" required>
                            <option value="patient">Patient</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
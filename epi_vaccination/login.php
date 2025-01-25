<!-- login.php -->
<?php
session_start();
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $userType = $_POST['userType'];

    if ($password == "password") { // Simple password check for testing
        switch($userType) {
            case 'patient':
                $stmt = $pdo->prepare("SELECT * FROM Child WHERE child_name = ?");
                break;
            case 'doctor':
            case 'nurse':
                $stmt = $pdo->prepare("SELECT * FROM Healthcare_Professional WHERE name = ? AND role = ?");
                break;
            case 'admin':
                if ($username == "admin") { // Simple admin check
                    $_SESSION['user_type'] = 'admin';
                    header("Location: admin_dashboard.php");
                    exit();
                }
                break;
        }

        if (isset($stmt)) {
            if ($userType == 'doctor' || $userType == 'nurse') {
                $stmt->execute([$username, ucfirst($userType)]);
            } else {
                $stmt->execute([$username]);
            }
            
            if ($row = $stmt->fetch()) {
                $_SESSION['user_id'] = $row[0]; // First column is ID
                $_SESSION['username'] = $username;
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
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Login</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label>Username:</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Password:</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Login as:</label>
                                <select name="userType" class="form-control" required>
                                    <option value="patient">Patient</option>
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
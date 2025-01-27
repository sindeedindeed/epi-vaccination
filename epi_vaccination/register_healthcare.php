<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if(isset($_POST['register_professional'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Healthcare_Professional 
            (name, role, years_of_experience, qualification, contact_info)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['role'],
            $_POST['years_of_experience'],
            $_POST['qualification'],
            $_POST['contact_info']
        ]);
        $success = "Healthcare professional registered successfully!";
    } catch(PDOException $e) {
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register Healthcare Professional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: #34495e;
            color: white;
            font-weight: bold;
        }
        .btn {
            border-radius: 20px;
        }
        .alert {
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 10px;
            padding: 10px 15px;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-user-md"></i> Register Healthcare Professional</h2>
                <div>
                    <a href="admin_dashboard.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Registration Form</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="Doctor">Doctor</option>
                            <option value="Nurse">Nurse</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" name="years_of_experience" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contact Info</label>
                        <input type="text" name="contact_info" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="register_professional" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Register Professional
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['doctor', 'nurse'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Child (child_name, dob, age, address, guardian_name, contact_no)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // Calculate age from DOB
        $dob = new DateTime($_POST['dob']);
        $today = new DateTime();
        $age = $dob->diff($today)->y;
        
        $stmt->execute([
            $_POST['child_name'],
            $_POST['dob'],
            $age,
            $_POST['address'],
            $_POST['guardian_name'],
            $_POST['contact_no']
        ]);
        
        $success = "Patient successfully registered!";
    } catch(PDOException $e) {
        $error = "Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>New Patient Enrollment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .enrollment-header {
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
            border: 1px solid #ddd;
            padding: 10px 15px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(52, 73, 94, 0.25);
            border-color: #34495e;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }
        .readonly-field {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="enrollment-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-user-plus"></i> New Patient Enrollment</h2>
                <a href="<?php echo $_SESSION['user_type']; ?>_dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-medical"></i> Enrollment Form</h3>
                    </div>
                    <div class="card-body">
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

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Child Name:</label>
                                <input type="text" name="child_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Date of Birth:</label>
                                <input type="date" name="dob" class="form-control" required 
                                       onchange="calculateAge(this.value)">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Age:</label>
                                <input type="text" id="age" class="form-control readonly-field" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address:</label>
                                <textarea name="address" class="form-control" required rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Guardian Name:</label>
                                <input type="text" name="guardian_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Contact Number:</label>
                                <input type="text" name="contact_no" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Enrolled By:</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" 
                                       class="form-control readonly-field" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Staff ID:</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>" 
                                       class="form-control readonly-field" readonly>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Register Patient
                                </button>
                                <a href="<?php echo $_SESSION['user_type']; ?>_dashboard.php" 
                                   class="btn btn-secondary">
                                   <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function calculateAge(dob) {
            const birthDate = new Date(dob);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            document.getElementById('age').value = age + ' years';
        }
    </script>
</body>
</html>
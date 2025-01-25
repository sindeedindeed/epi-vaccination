<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['doctor', 'nurse'])) {
    header("Location: login.php");
    exit();
}

$showSuccess = false;  // Initialize flag

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
        $showSuccess = true;  // Set flag when registration successful
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
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3>New Enrollment</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label>Child Name:</label>
                                <input type="text" name="child_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Date of Birth:</label>
                                <input type="date" name="dob" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Address:</label>
                                <textarea name="address" class="form-control" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label>Guardian Name:</label>
                                <input type="text" name="guardian_name" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Contact Number:</label>
                                <input type="text" name="contact_no" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label>Enrolled By:</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" 
                                       class="form-control" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label>Staff ID:</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>" 
                                       class="form-control" readonly>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Register Patient</button>
                                <a href="<?php echo $_SESSION['user_type']; ?>_dashboard.php" 
                                   class="btn <?php echo $showSuccess ? 'btn-success' : 'btn-secondary'; ?>">
                                   Back
                                </a>
                            </div>

                            <?php if ($showSuccess): ?>
                                <div class="alert alert-success mt-3">
                                    Patient successfully registered! You can go back or register another patient.
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
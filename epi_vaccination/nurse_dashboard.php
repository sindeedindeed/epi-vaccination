<!-- nurse_dashboard.php -->
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header("Location: login.php");
    exit();
}

// Get all visited patients that need vaccination
$stmt = $pdo->prepare("
    SELECT 
        c.child_id,
        c.child_name,
        v.name AS vaccine_name,
        vc.name AS centre_name,
        vac.due_date,
        hp.name AS doctor_name
    FROM Vaccination vac
    JOIN Child c ON vac.child_id = c.child_id
    JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
    JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
    JOIN Healthcare_Professional hp ON vac.healthcare_professional_id = hp.id
    WHERE vac.status = 'visited'
    ORDER BY vac.due_date ASC
");
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nurse Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Welcome, Nurse <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <div>
                <a href="new_enrollment.php" class="btn btn-success me-2">Register New Patient</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <!-- Patients Requiring Vaccination -->
        <div class="card">
            <div class="card-header">
                <h3>Patients Requiring Vaccination</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Vaccine</th>
                            <th>Centre</th>
                            <th>Due Date</th>
                            <th>Prescribed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['child_id']); ?></td>
                            <td><?php echo htmlspecialchars($patient['child_name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['vaccine_name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['centre_name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['due_date']); ?></td>
                            <td><?php echo htmlspecialchars($patient['doctor_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

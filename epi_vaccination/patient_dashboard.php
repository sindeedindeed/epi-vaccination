<!-- patient_dashboard.php -->
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'patient') {
    header("Location: login.php");
    exit();
}

// Get patient's vaccination records with all related info
$stmt = $pdo->prepare("
    SELECT 
        c.child_name,
        v.name AS vaccine_name,
        vc.name AS centre_name,
        hp.name AS healthcare_prof_name,
        vac.date_administered,
        vac.due_date,
        vac.status
    FROM Child c
    LEFT JOIN Vaccination vac ON c.child_id = vac.child_id
    LEFT JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
    LEFT JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
    LEFT JOIN Healthcare_Professional hp ON vac.healthcare_professional_id = hp.id
    WHERE c.child_name = ?
    ORDER BY vac.due_date ASC
");

$stmt->execute([$_SESSION['username']]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get next due date
$nextDue = null;
foreach ($records as $record) {
    if (strtotime($record['due_date']) > time()) {
        $nextDue = $record;
        break;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>

                <?php if ($nextDue): ?>
                <div class="alert alert-warning">
                    <h4>Next Vaccination Due:</h4>
                    <p>Vaccine: <?php echo htmlspecialchars($nextDue['vaccine_name']); ?></p>
                    <p>Date: <?php echo htmlspecialchars($nextDue['due_date']); ?></p>
                    <p>Centre: <?php echo htmlspecialchars($nextDue['centre_name']); ?></p>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3>Your Vaccination Records</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Vaccine</th>
                                    <th>Centre</th>
                                    <th>Healthcare Professional</th>
                                    <th>Date Administered</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['vaccine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['centre_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['healthcare_prof_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['date_administered']); ?></td>
                                    <td><?php echo htmlspecialchars($record['due_date']); ?></td>
                                    <td><?php echo htmlspecialchars($record['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
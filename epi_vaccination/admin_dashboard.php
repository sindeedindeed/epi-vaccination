<!-- admin_dashboard.php -->
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get all data with joins
$stmt = $pdo->prepare("
    SELECT 
        c.child_id,
        c.child_name,
        c.dob,
        c.age,
        c.guardian_name,
        c.contact_no,
        v.name AS vaccine_name,
        v.dose,
        vc.name AS centre_name,
        vc.location,
        hp.name AS healthcare_prof_name,
        hp.role AS healthcare_prof_role,
        vac.date_administered,
        vac.due_date,
        vac.status
    FROM Child c
    LEFT JOIN Vaccination vac ON c.child_id = vac.child_id
    LEFT JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
    LEFT JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
    LEFT JOIN Healthcare_Professional hp ON vac.healthcare_professional_id = hp.id
    ORDER BY c.child_id, vac.date_administered DESC
");
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Admin Dashboard</h2>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Complete Vaccination Records</h3>
            </div>
            <div class="card-body">
                <table id="adminTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Child ID</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Guardian</th>
                            <th>Contact</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Centre</th>
                            <th>Healthcare Professional</th>
                            <th>Role</th>
                            <th>Date Administered</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['child_id']); ?></td>
                            <td><?php echo htmlspecialchars($record['child_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['age']); ?></td>
                            <td><?php echo htmlspecialchars($record['guardian_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['contact_no']); ?></td>
                            <td><?php echo htmlspecialchars($record['vaccine_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['dose']); ?></td>
                            <td><?php echo htmlspecialchars($record['centre_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['healthcare_prof_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['healthcare_prof_role']); ?></td>
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

    <!-- jQuery and DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#adminTable').DataTable({
                pageLength: 25,
                scrollX: true
            });
        });
    </script>
</body>
</html>
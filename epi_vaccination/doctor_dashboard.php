<!-- doctor_dashboard.php -->
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['prescribe'])) {
        $stmt = $pdo->prepare("
            INSERT INTO Vaccination (child_id, vaccine_id, healthcare_professional_id, 
                                   centre_id, date_administered, due_date, status)
            VALUES (?, ?, ?, ?, CURDATE(), ?, ?)
        ");
        
        $status = isset($_POST['visited']) ? 'visited' : 'pending';
        $stmt->execute([
            $_POST['child_id'],
            $_POST['vaccine_id'],
            $_SESSION['user_id'],
            $_POST['centre_id'],
            $_POST['due_date'],
            $status
        ]);
    }
}

// Get all patients
$stmt = $pdo->prepare("SELECT * FROM Child");
$stmt->execute();
$patients = $stmt->fetchAll();

// Get all vaccines
$stmt = $pdo->prepare("SELECT * FROM Vaccine");
$stmt->execute();
$vaccines = $stmt->fetchAll();

// Get all centres
$stmt = $pdo->prepare("SELECT * FROM Vaccination_Centre");
$stmt->execute();
$centres = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Doctor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Welcome, Dr. <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <div>
                <a href="new_enrollment.php" class="btn btn-success me-2">Register New Patient</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>

        <!-- Prescribe Vaccine Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Prescribe Vaccine</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="prescribe" value="1">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Select Patient:</label>
                            <select name="child_id" class="form-control" required>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['child_id']; ?>">
                                        <?php echo htmlspecialchars($patient['child_name']); ?> 
                                        (ID: <?php echo $patient['child_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Select Vaccine:</label>
                            <select name="vaccine_id" class="form-control" required>
                                <?php foreach ($vaccines as $vaccine): ?>
                                    <option value="<?php echo $vaccine['vaccine_id']; ?>">
                                        <?php echo htmlspecialchars($vaccine['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Select Centre:</label>
                            <select name="centre_id" class="form-control" required>
                                <?php foreach ($centres as $centre): ?>
                                    <option value="<?php echo $centre['centre_id']; ?>">
                                        <?php echo htmlspecialchars($centre['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Due Date:</label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="visited" class="form-check-input" id="visited">
                                <label class="form-check-label" for="visited">Visited</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Prescribe Vaccine</button>
                </form>
            </div>
        </div>

        <!-- View Prescribed Vaccines -->
        <div class="card">
            <div class="card-header">
                <h3>Prescribed Vaccines</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Vaccine</th>
                            <th>Centre</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT 
                                c.child_name,
                                v.name AS vaccine_name,
                                vc.name AS centre_name,
                                vac.due_date,
                                vac.status
                            FROM Vaccination vac
                            JOIN Child c ON vac.child_id = c.child_id
                            JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
                            JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
                            WHERE vac.healthcare_professional_id = ?
                            ORDER BY vac.due_date DESC
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        while ($row = $stmt->fetch()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['centre_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
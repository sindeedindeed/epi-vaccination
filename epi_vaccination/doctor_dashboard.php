<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

// Handle visit confirmation
if(isset($_POST['confirm_visit'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE Vaccination 
            SET status = 'visited' 
            WHERE child_id = ? AND vaccine_id = ?
        ");
        $stmt->execute([$_POST['child_id'], $_POST['vaccine_id']]);
        $success = "Visit confirmed successfully!";
    } catch(PDOException $e) {
        $error = "Visit confirmation failed: " . $e->getMessage();
    }
}

// Handle prescription
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['prescribe'])) {
    $stmt = $pdo->prepare("
        INSERT INTO Vaccination (child_id, vaccine_id, healthcare_professional_id, 
                               centre_id, date_administered, due_date, status, dose_number)
        VALUES (?, ?, ?, ?, CURDATE(), ?, 'pending', 1)
    ");
    
    try {
        $stmt->execute([
            $_POST['child_id'],
            $_POST['vaccine_id'],
            $_SESSION['user_id'],
            $_POST['centre_id'],
            $_POST['due_date']
        ]);
        $success = "Vaccine prescribed successfully!";
    } catch(PDOException $e) {
        $error = "Prescription failed: " . $e->getMessage();
    }
}

// Get all vaccines
$stmt = $pdo->prepare("SELECT * FROM Vaccine");
$stmt->execute();
$vaccines = $stmt->fetchAll();

// Get all centres
$stmt = $pdo->prepare("SELECT * FROM Vaccination_Centre");
$stmt->execute();
$centres = $stmt->fetchAll();

// Function to get dose progress bar
function getDoseProgressBar($taken, $total) {
    $percentage = ($taken / $total) * 100;
    $progressClass = $taken == $total ? 'bg-success' : 'bg-primary';
    return "
        <div class='progress' style='height: 20px;'>
            <div class='progress-bar $progressClass' role='progressbar' 
                 style='width: $percentage%;' 
                 aria-valuenow='$percentage' 
                 aria-valuemin='0' 
                 aria-valuemax='100'>
                $taken/$total
            </div>
        </div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Doctor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .doctor-header {
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
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.075);
        }
        .badge {
            padding: 8px 12px;
            border-radius: 20px;
        }
        .table-success {
            background-color: #d4edda !important;
        }
        .btn {
            border-radius: 20px;
        }
        .btn-confirm-visit {
            background-color: #3498db;
            color: white;
            padding: 5px 15px;
            margin-left: 10px;
            transition: all 0.3s ease;
        }
        .btn-confirm-visit:hover {
            background-color: #2980b9;
            color: white;
            transform: translateY(-1px);
        }
        .alert {
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .progress {
            border-radius: 10px;
            margin-top: 5px;
        }
        .progress-bar {
            font-size: 0.8rem;
            font-weight: bold;
        }
        .dose-complete {
            color: #28a745;
            font-weight: bold;
        }
        .dose-incomplete {
            color: #dc3545;
            font-weight: bold;
        }
        .status-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="doctor-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-user-md"></i> Welcome, Dr. <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
                <div>
                    <a href="new_enrollment.php" class="btn btn-light me-2">
                        <i class="fas fa-user-plus"></i> Register New Patient
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

        <!-- Prescribe Vaccine Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-prescription"></i> Prescribe Vaccine</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="prescribe" value="1">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Select Patient:</label>
                            <select name="child_id" class="form-control" required>
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM Child ORDER BY child_name");
                                $stmt->execute();
                                while ($patient = $stmt->fetch()):
                                ?>
                                    <option value="<?php echo $patient['child_id']; ?>">
                                        <?php echo htmlspecialchars($patient['child_name']); ?> 
                                        (ID: <?php echo $patient['child_id']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Select Vaccine:</label>
                            <select name="vaccine_id" class="form-control" required>
                                <?php foreach ($vaccines as $vaccine): ?>
                                    <option value="<?php echo $vaccine['vaccine_id']; ?>">
                                        <?php echo htmlspecialchars($vaccine['name']); ?> 
                                        (<?php echo $vaccine['dose']; ?> doses)
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
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-prescription-bottle-medical"></i> Prescribe Vaccine
                    </button>
                </form>
            </div>
        </div>

        <!-- Patient List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Patient List</h3>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Guardian</th>
                            <th>Contact</th>
                            <th>Vaccine</th>
                            <th>Doses</th>
                            <th>Centre</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT 
                                c.*,
                                v.name AS vaccine_name,
                                v.vaccine_id,
                                v.dose AS total_doses,
                                vac.dose_number AS doses_taken,
                                vac.due_date,
                                vac.status,
                                vc.name AS centre_name
                            FROM Child c
                            LEFT JOIN Vaccination vac ON c.child_id = vac.child_id
                            LEFT JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
                            LEFT JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
                            ORDER BY c.child_id DESC
                        ");
                        $stmt->execute();
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $rowClass = ($row['status'] == 'vaccinated') ? 'table-success' : '';
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo htmlspecialchars($row['child_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['age']); ?></td>
                                <td><?php echo htmlspecialchars($row['guardian_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['vaccine_name'] ?? 'Not prescribed'); ?></td>
                                <td>
                                    <?php if ($row['total_doses']): ?>
                                        <div class="<?php echo $row['doses_taken'] == $row['total_doses'] ? 'dose-complete' : 'dose-incomplete'; ?>">
                                            Doses: <?php echo $row['doses_taken']; ?>/<?php echo $row['total_doses']; ?>
                                        </div>
                                        <?php echo getDoseProgressBar($row['doses_taken'], $row['total_doses']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not prescribed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['centre_name'] ?? 'Not set'); ?></td>
                                <td><?php echo $row['due_date'] ? date('Y-m-d', strtotime($row['due_date'])) : 'Not set'; ?></td>
                                <td>
                                    <div class="status-container">
                                        <?php if (!$row['status']): ?>
                                            <span class="badge bg-secondary">Pending Prescription</span>
                                        <?php elseif ($row['status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending Visit</span>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="child_id" value="<?php echo $row['child_id']; ?>">
                                                <input type="hidden" name="vaccine_id" value="<?php echo $row['vaccine_id']; ?>">
                                                <button type="submit" name="confirm_visit" class="btn btn-confirm-visit btn-sm">
                                                    <i class="fas fa-check"></i> Confirm Visit
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] == 'visited'): ?>
                                            <span class="badge bg-primary">Visit Confirmed</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Vaccinated</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
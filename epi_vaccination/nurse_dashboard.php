<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header("Location: login.php");
    exit();
}

// Handle vaccination confirmation
if(isset($_POST['confirm_vaccination'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE Vaccination 
            SET status = 'vaccinated',
                dose_number = ?
            WHERE child_id = ? AND vaccine_id = ?
        ");
        $stmt->execute([$_POST['dose_number'], $_POST['child_id'], $_POST['vaccine_id']]);
        $success = "Vaccination confirmed successfully!";
    } catch(PDOException $e) {
        $error = "Vaccination confirmation failed: " . $e->getMessage();
    }
}

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
    <title>Nurse Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .nurse-header {
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
        .btn-confirm {
            padding: 5px 15px;
            font-size: 0.9em;
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
        .dose-selector {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background-color: #f8f9fa;
        }
        .dose-radio {
            margin-right: 15px;
        }
        .dose-complete {
            color: #28a745;
            font-weight: bold;
        }
        .dose-incomplete {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="nurse-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-user-nurse"></i> Welcome, Nurse <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
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

        <!-- Patients Requiring Vaccination -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-syringe"></i> Patients Requiring Vaccination</h3>
            </div>
            <div class="card-body">
                <table id="pendingTable" class="table table-hover">
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
                            <th>Prescribed By</th>
                            <th>Action</th>
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
                                vc.name AS centre_name,
                                hp.name AS prescribed_by,
                                vac.due_date,
                                vac.status
                            FROM Child c
                            JOIN Vaccination vac ON c.child_id = vac.child_id
                            JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
                            JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
                            JOIN Healthcare_Professional hp ON vac.healthcare_professional_id = hp.id
                            WHERE vac.status = 'visited'
                            ORDER BY vac.due_date ASC
                        ");
                        $stmt->execute();
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $nextDose = ($row['doses_taken'] ?? 0) + 1;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['child_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['age']); ?></td>
                                <td><?php echo htmlspecialchars($row['guardian_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                                <td>
                                    <div class="<?php echo $row['doses_taken'] == $row['total_doses'] ? 'dose-complete' : 'dose-incomplete'; ?>">
                                        Current: <?php echo $row['doses_taken']; ?>/<?php echo $row['total_doses']; ?>
                                    </div>
                                    <?php echo getDoseProgressBar($row['doses_taken'], $row['total_doses']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['centre_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['due_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['prescribed_by']); ?></td>
                                <td>
                                    <?php if ($nextDose <= $row['total_doses']): ?>
                                        <form method="POST" class="dose-selector">
                                            <input type="hidden" name="child_id" value="<?php echo $row['child_id']; ?>">
                                            <input type="hidden" name="vaccine_id" value="<?php echo $row['vaccine_id']; ?>">
                                            <div class="mb-2">
                                                <label>Select Dose:</label>
                                                <select name="dose_number" class="form-select form-select-sm" required>
                                                    <?php for ($i = $nextDose; $i <= $row['total_doses']; $i++): ?>
                                                        <option value="<?php echo $i; ?>">
                                                            Dose <?php echo $i; ?> of <?php echo $row['total_doses']; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <button type="submit" name="confirm_vaccination" 
                                                    class="btn btn-success btn-sm btn-confirm">
                                                <i class="fas fa-check"></i> Confirm Vaccination
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-success">All doses completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vaccination History -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Vaccination History</h3>
            </div>
            <div class="card-body">
                <table id="historyTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
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
                                c.child_id,
                                c.child_name,
                                v.name AS vaccine_name,
                                v.dose AS total_doses,
                                vac.dose_number AS doses_taken,
                                vc.name AS centre_name,
                                vac.due_date,
                                vac.status
                            FROM Child c
                            JOIN Vaccination vac ON c.child_id = vac.child_id
                            JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
                            JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
                            WHERE vac.status = 'vaccinated'
                            ORDER BY vac.due_date DESC
                        ");
                        $stmt->execute();
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <tr class="table-success">
                                <td><?php echo htmlspecialchars($row['child_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                                <td>
                                    <div class="dose-complete">
                                        <?php echo $row['doses_taken']; ?>/<?php echo $row['total_doses']; ?>
                                    </div>
                                    <?php echo getDoseProgressBar($row['doses_taken'], $row['total_doses']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['centre_name']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['due_date'])); ?></td>
                                <td>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle"></i> Vaccinated
                                    </span>
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
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#pendingTable').DataTable({
                pageLength: 10,
                order: [[8, 'asc']], // Sort by due date
                language: {
                    search: "Search pending vaccinations:"
                }
            });

            $('#historyTable').DataTable({
                pageLength: 10,
                order: [[5, 'desc']], // Sort by due date descending
                language: {
                    search: "Search vaccination history:"
                }
            });
        });
    </script>
</body>
</html>
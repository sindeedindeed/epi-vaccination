<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle vaccination record deletion
if(isset($_POST['delete_vaccination'])) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM Vaccination 
            WHERE child_id = ? AND vaccine_id = ?
        ");
        $stmt->execute([$_POST['child_id'], $_POST['vaccine_id']]);
        $success = "Vaccination record deleted successfully!";
    } catch(PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Handle patient deletion
if(isset($_POST['delete_patient'])) {
    try {
        // First delete all vaccination records for this patient
        $stmt = $pdo->prepare("DELETE FROM Vaccination WHERE child_id = ?");
        $stmt->execute([$_POST['child_id']]);
        
        // Then delete the patient
        $stmt = $pdo->prepare("DELETE FROM Child WHERE child_id = ?");
        $stmt->execute([$_POST['child_id']]);
        
        $success = "Patient and all associated records deleted successfully!";
    } catch(PDOException $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// Get monthly vaccination statistics
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT child_id) as total_vaccinated
    FROM Vaccination
    WHERE status = 'vaccinated'
    AND MONTH(date_administered) = MONTH(CURRENT_DATE())
    AND YEAR(date_administered) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$monthlyStats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .stats-card {
            background: linear-gradient(45deg, #2c3e50, #3498db);
            color: white;
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            font-size: 3rem;
            opacity: 0.8;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
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
        .btn {
            border-radius: 20px;
        }
        .btn-delete {
            padding: 5px 15px;
            font-size: 0.9em;
        }
        .btn-delete-vaccination {
            background-color: #e74c3c;
            color: white;
        }
        .btn-delete-vaccination:hover {
            background-color: #c0392b;
            color: white;
        }
        .btn-delete-patient {
            background-color: #c0392b;
            color: white;
        }
        .btn-delete-patient:hover {
            background-color: #922b21;
            color: white;
        }
        .alert {
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .dataTables_filter input {
            border-radius: 20px;
            padding: 5px 15px;
            margin-left: 10px;
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: 15px;
            padding: 5px 10px;
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-user-shield"></i> Admin Dashboard</h2>
                <div>
                    <a href="register_healthcare.php" class="btn btn-light me-2">
                        <i class="fas fa-user-md"></i> Register Healthcare Professional
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
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

        <!-- Monthly Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <i class="fas fa-syringe stats-icon"></i>
                            </div>
                            <div class="col">
                                <h6 class="card-title mb-0">Vaccinations This Month</h6>
                                <div class="stats-number"><?php echo $monthlyStats['total_vaccinated']; ?></div>
                                <p class="mb-0">Children Vaccinated</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vaccination Records -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-table"></i> Complete Vaccination Records</h3>
            </div>
            <div class="card-body">
                <table id="adminTable" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Child ID</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Guardian</th>
                            <th>Contact</th>
                            <th>Vaccine</th>
                            <th>Centre</th>
                            <th>Healthcare Professional</th>
                            <th>Due Date</th>
                            <th>Status</th>
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
                                vc.name AS centre_name,
                                hp.name AS healthcare_prof_name,
                                vac.due_date,
                                vac.status
                            FROM Child c
                            LEFT JOIN Vaccination vac ON c.child_id = vac.child_id
                            LEFT JOIN Vaccine v ON vac.vaccine_id = v.vaccine_id
                            LEFT JOIN Vaccination_Centre vc ON vac.centre_id = vc.centre_id
                            LEFT JOIN Healthcare_Professional hp ON vac.healthcare_professional_id = hp.id
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
                                <td><?php echo htmlspecialchars($row['centre_name'] ?? 'Not set'); ?></td>
                                <td><?php echo htmlspecialchars($row['healthcare_prof_name'] ?? 'Not assigned'); ?></td>
                                <td><?php echo $row['due_date'] ? date('Y-m-d', strtotime($row['due_date'])) : 'Not set'; ?></td>
                                <td>
                                    <?php if (!$row['status']): ?>
                                        <span class="badge bg-secondary">Pending Prescription</span>
                                    <?php elseif ($row['status'] == 'pending'): ?>
                                        <span class="badge bg-warning">Pending Visit</span>
                                    <?php elseif ($row['status'] == 'visited'): ?>
                                        <span class="badge bg-primary">Visit Confirmed</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Vaccinated</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($row['vaccine_id']): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this vaccination record?');">
                                            <input type="hidden" name="child_id" value="<?php echo $row['child_id']; ?>">
                                            <input type="hidden" name="vaccine_id" value="<?php echo $row['vaccine_id']; ?>">
                                            <button type="submit" name="delete_vaccination" class="btn btn-delete-vaccination btn-sm me-1">
                                                <i class="fas fa-syringe"></i> Delete Vaccination
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this patient? This will remove all vaccination records for this patient.');">
                                        <input type="hidden" name="child_id" value="<?php echo $row['child_id']; ?>">
                                        <button type="submit" name="delete_patient" class="btn btn-delete-patient btn-sm">
                                            <i class="fas fa-user-times"></i> Delete Patient
                                        </button>
                                    </form>
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
            $('#adminTable').DataTable({
                pageLength: 25,
                scrollX: true,
                order: [[0, 'desc']],
                language: {
                    search: "Search records:"
                }
            });
        });
    </script>
</body>
</html>
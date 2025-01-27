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
$today = new DateTime();
foreach ($records as $record) {
    if ($record['due_date']) {
        $dueDate = new DateTime($record['due_date']);
        if ($dueDate >= $today || !$nextDue) {
            $nextDue = $record;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .patient-header {
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
        .due-date-alert {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .due-date-future {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .due-date-past {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.075);
        }
        .badge {
            padding: 8px 12px;
            border-radius: 20px;
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
        .btn {
            border-radius: 20px;
        }
    </style>
</head>
<body>
    <div class="patient-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($nextDue): ?>
            <?php
            $dueDate = new DateTime($nextDue['due_date']);
            $interval = $today->diff($dueDate);
            $isPastDue = $dueDate < $today;
            $alertClass = $isPastDue ? 'due-date-past' : 'due-date-future';
            $icon = $isPastDue ? 'exclamation-triangle' : 'calendar-check';
            ?>
            <div class="due-date-alert <?php echo $alertClass; ?>">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-<?php echo $icon; ?> fa-3x"></i>
                    </div>
                    <div class="col">
                        <h4 class="mb-0">
                            <?php if ($isPastDue): ?>
                                Vaccination Overdue!
                            <?php else: ?>
                                Upcoming Vaccination
                            <?php endif; ?>
                        </h4>
                        <p class="mb-0">
                            <strong>Vaccine:</strong> <?php echo htmlspecialchars($nextDue['vaccine_name']); ?><br>
                            <strong>Due Date:</strong> <?php echo date('d-M-Y', strtotime($nextDue['due_date'])); ?><br>
                            <strong>Centre:</strong> <?php echo htmlspecialchars($nextDue['centre_name']); ?><br>
                            <?php if ($isPastDue): ?>
                                <span class="text-danger">
                                    Overdue by <?php echo $interval->days; ?> days
                                </span>
                            <?php else: ?>
                                <span class="text-success">
                                    Due in <?php echo $interval->days; ?> days
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Your Vaccination Records</h3>
            </div>
            <div class="card-body">
                <table id="vaccinationTable" class="table table-hover">
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
                            <td><?php echo $record['date_administered'] ? date('d-M-Y', strtotime($record['date_administered'])) : '-'; ?></td>
                            <td>
                                <?php 
                                if ($record['due_date']) {
                                    $dueDate = new DateTime($record['due_date']);
                                    $isPastDue = $dueDate < $today;
                                    echo '<span class="' . ($isPastDue ? 'text-danger' : 'text-success') . ' fw-bold">';
                                    echo date('d-M-Y', strtotime($record['due_date']));
                                    echo '</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($record['status'] == 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($record['status'] == 'visited'): ?>
                                    <span class="badge bg-primary">Visit Confirmed</span>
                                <?php elseif ($record['status'] == 'vaccinated'): ?>
                                    <span class="badge bg-success">Vaccinated</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Started</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
            $('#vaccinationTable').DataTable({
                pageLength: 10,
                order: [[4, 'asc']], // Sort by due date
                language: {
                    search: "Search records:"
                }
            });
        });
    </script>
</body>
</html>
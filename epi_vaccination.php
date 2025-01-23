<?php
session_start();

// Database credentials
$host = 'localhost';
$username_db = 'root'; // Default username for XAMPP
$password_db = '';     // Default password for XAMPP
$database = 'epi_vaccination'; // Your database name

// Connect to the database
$conn = new mysqli($host, $username_db, $password_db, $database);

// Check connection
if ($conn->connect_error) {
    die("<div class='alert alert-danger'>Connection failed: " . htmlspecialchars($conn->connect_error) . "</div>");
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Destroy the session and redirect to the login page
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Initialize variables
$login_error = '';
$query_error = '';
$query_results = [];
$child_info = [];
$assigned_doctor = [];
$due_vaccines = [];
$all_children = [];
$all_healthcare_professionals = [];
$new_entry_error = '';
$new_entry_success = '';
// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $input_username = trim($_POST['username_id']);
    $login_type = $_POST['login_type'] ?? '';

    // Validate login type
    if (!in_array($login_type, ['patient', 'healthcare_professional', 'admin'])) {
        $login_error = "Invalid login type selected.";
    }

    // Ensure Username/ID is not empty
    if (empty($input_username)) {
        $login_error = "Please enter a valid Username or ID.";
    }

    // Proceed if no errors
    if (empty($login_error)) {
        if ($login_type === 'patient') {
            // Determine if input is Child ID or Child Name
            if (is_numeric($input_username)) {
                $child_id = (int)$input_username;
                $stmt = $conn->prepare("SELECT * FROM Child WHERE child_id = ?");
                $stmt->bind_param("i", $child_id);
            } else {
                $child_name = $input_username;
                $stmt = $conn->prepare("SELECT * FROM Child WHERE child_name = ?");
                $stmt->bind_param("s", $child_name);
            }

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $child_info = $result->fetch_assoc();
                    $_SESSION['username_id'] = $input_username;
                    $_SESSION['login_type'] = $login_type;
                    $_SESSION['child_id'] = $child_info['child_id'];
                    
                    // Fetch Assigned Doctor
                    $child_id = $child_info['child_id'];
                    $stmt_doctor = $conn->prepare("
                        SELECT Healthcare_Professional.name, Healthcare_Professional.contact_info
                        FROM Vaccination
                        JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                        WHERE Vaccination.child_id = ?
                        ORDER BY Vaccination.due_date DESC
                        LIMIT 1
                    ");
                    $stmt_doctor->bind_param("i", $child_id);
                    if ($stmt_doctor->execute()) {
                        $result_doctor = $stmt_doctor->get_result();
                        if ($result_doctor->num_rows === 1) {
                            $assigned_doctor = $result_doctor->fetch_assoc();
                        }
                    }
                    $stmt_doctor->close();

                    // Fetch Due Vaccine Dates
                    $stmt_due = $conn->prepare("
                        SELECT Vaccine.name AS vaccine_name, Vaccination.due_date
                        FROM Vaccination
                        JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                        WHERE Vaccination.child_id = ?
                        ORDER BY Vaccination.due_date ASC
                    ");
                    $stmt_due->bind_param("i", $child_id);
                    if ($stmt_due->execute()) {
                        $result_due = $stmt_due->get_result();
                        while ($row_due = $result_due->fetch_assoc()) {
                            $due_vaccines[] = $row_due;
                        }
                    }
                    $stmt_due->close();

                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $login_error = "Invalid Username/ID. No matching child found.";
                }
            }
            $stmt->close();
        } elseif ($login_type === 'healthcare_professional') {
            if (is_numeric($input_username)) {
                $prof_id = (int)$input_username;
                $stmt = $conn->prepare("SELECT * FROM Healthcare_Professional WHERE id = ?");
                $stmt->bind_param("i", $prof_id);
            } else {
                $prof_name = $input_username;
                $stmt = $conn->prepare("SELECT * FROM Healthcare_Professional WHERE name = ?");
                $stmt->bind_param("s", $prof_name);
            }

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $prof_info = $result->fetch_assoc();
                    $_SESSION['username_id'] = $input_username;
                    $_SESSION['login_type'] = $login_type;
                    $_SESSION['professional_id'] = $prof_info['id'];
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $login_error = "Invalid Username/ID. No matching professional found.";
                }
            }
            $stmt->close();
        } elseif ($login_type === 'admin') {
            if ($input_username === 'admin') {
                $_SESSION['username_id'] = $input_username;
                $_SESSION['login_type'] = $login_type;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $login_error = "Invalid admin username.";
            }
        }
    }
}
// Handle New Entry Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_entry_submit'])) {
    // Retrieve and sanitize form data
    $selected_professionals = isset($_POST['professionals']) ? $_POST['professionals'] : [];
    $selected_centres = isset($_POST['centres']) ? $_POST['centres'] : [];
    $selected_vaccines = isset($_POST['vaccines']) ? $_POST['vaccines'] : [];
    $child_name = trim($_POST['child_name']);
    $age = trim($_POST['age']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $guardian_name = trim($_POST['guardian_name']);
    $date = trim($_POST['date']);

    // Validate required fields
    if (empty($child_name) || empty($age) || empty($address) || empty($phone) || empty($guardian_name) || empty($date)) {
        $new_entry_error = "Please fill in all required fields.";
    } else {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert into Child table
            $stmt = $conn->prepare("INSERT INTO Child (child_name, age, address, contact_no, guardian_name, dob) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissss", $child_name, $age, $address, $phone, $guardian_name, $date);
            $stmt->execute();
            $new_child_id = $conn->insert_id;
            $stmt->close();

            // Insert vaccination records
            foreach ($selected_vaccines as $vaccine_id) {
                foreach ($selected_professionals as $prof_id) {
                    foreach ($selected_centres as $centre_id) {
                        // Get max record_id and increment
                        $result = $conn->query("SELECT MAX(record_id) as max_id FROM Vaccination");
                        $row = $result->fetch_assoc();
                        $new_record_id = ($row['max_id'] ?? 0) + 1;

                        $stmt = $conn->prepare("INSERT INTO Vaccination (record_id, child_id, vaccine_id, healthcare_professional_id, centre_id, date_administered, due_date) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL 1 MONTH))");
                        $stmt->bind_param("iiiiiss", $new_record_id, $new_child_id, $vaccine_id, $prof_id, $centre_id, $date, $date);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            // Commit transaction
            $conn->commit();
            $new_entry_success = "New child entry has been successfully added with Child ID: " . $new_child_id;

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $new_entry_error = "Failed to add new child entry: " . $e->getMessage();
        }
    }
}

// Handle Query Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query']) && isset($_SESSION['login_type'])) {
    $search_type = $_POST['search_type'];
    $search_value = trim($_POST['search_value']);

    if (empty($search_value)) {
        $query_error = "Please enter a value to search.";
    } else {
        $login_type = $_SESSION['login_type'];

        if ($login_type == 'healthcare_professional') {
            $professional_id = $_SESSION['professional_id'];

            if ($search_type === 'child_id') {
                $stmt = $conn->prepare("
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, 
                           Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
                    FROM Child
                    JOIN Vaccination ON Child.child_id = Vaccination.child_id
                    JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                    JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                    WHERE Child.child_id = ? AND Healthcare_Professional.id = ?
                ");
                $stmt->bind_param("ii", $search_value, $professional_id);
            } elseif ($search_type === 'child_name') {
                $like_search = "%" . $search_value . "%";
                $stmt = $conn->prepare("
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, 
                           Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
                    FROM Child
                    JOIN Vaccination ON Child.child_id = Vaccination.child_id
                    JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                    JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                    WHERE Child.child_name LIKE ? AND Healthcare_Professional.id = ?
                ");
                $stmt->bind_param("si", $like_search, $professional_id);
            }
        } elseif ($login_type == 'admin') {
            if ($search_type === 'child_id') {
                $stmt = $conn->prepare("
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, 
                           Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
                    FROM Child
                    JOIN Vaccination ON Child.child_id = Vaccination.child_id
                    JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                    JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                    WHERE Child.child_id = ?
                ");
                $stmt->bind_param("i", $search_value);
            } elseif ($search_type === 'child_name') {
                $like_search = "%" . $search_value . "%";
                $stmt = $conn->prepare("
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, 
                           Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
                    FROM Child
                    JOIN Vaccination ON Child.child_id = Vaccination.child_id
                    JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                    JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                    WHERE Child.child_name LIKE ?
                ");
                $stmt->bind_param("s", $like_search);
            }
        }

        if (isset($stmt) && $stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $query_results[] = $row;
            }
            if (empty($query_results)) {
                $query_error = "No records found matching your search criteria.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EPI Vaccination Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <style>
        .form-check { margin-bottom: 10px; }
        .card { margin-bottom: 20px; }
        .alert { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <?php if (!isset($_SESSION['username_id'])): ?>
            <!-- Login Form -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <h2 class="text-center">Login</h2>
                    <?php if (!empty($login_error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="username_id" class="form-label">Username/ID</label>
                                    <input type="text" class="form-control" id="username_id" name="username_id" required>
                                </div>
                                <div class="mb-3">
                                    <label for="login_type" class="form-label">Login Type</label>
                                    <select class="form-control" id="login_type" name="login_type" required>
                                        <option value="" disabled selected>Select Login Type</option>
                                        <option value="patient">Patient</option>
                                        <option value="healthcare_professional">Healthcare Professional</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary" name="login">Login</button>
                            </form>
                        </div>
                    </div>

                    <!-- New Entry Form -->
                    <div class="mt-5">
                        <h2 class="text-center">New Entry</h2>
                        <?php if (isset($new_entry_error) && !empty($new_entry_error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($new_entry_error); ?></div>
                        <?php endif; ?>
                        <?php if (isset($new_entry_success) && !empty($new_entry_success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($new_entry_success); ?></div>
                        <?php endif; ?>
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <!-- Healthcare Professionals Checklist -->
                                    <div class="mb-3">
                                        <label class="form-label">Select Healthcare Professional</label>
                                        <?php
                                        $stmt = $conn->prepare("SELECT id, name, role FROM Healthcare_Professional");
                                        $stmt->execute();
                                        $result_professionals = $stmt->get_result();
                                        ?>
                                        <div class="form-check">
                                            <?php while ($professional = $result_professionals->fetch_assoc()): ?>
                                                <input class="form-check-input" type="checkbox" name="professionals[]" 
                                                       value="<?php echo htmlspecialchars($professional['id']); ?>" 
                                                       id="prof_<?php echo htmlspecialchars($professional['id']); ?>">
                                                <label class="form-check-label" for="prof_<?php echo htmlspecialchars($professional['id']); ?>">
                                                    <?php echo htmlspecialchars($professional['name']) . ' (' . htmlspecialchars($professional['role']) . ')'; ?>
                                                </label><br>
                                            <?php endwhile; ?>
                                        </div>
                                        <?php $stmt->close(); ?>
                                    </div>

                                    <!-- Vaccination Centers Checklist -->
                                    <div class="mb-3">
                                        <label class="form-label">Select Vaccination Centre</label>
                                        <?php
                                        $stmt = $conn->prepare("SELECT centre_id, name FROM Vaccination_Centre");
                                        $stmt->execute();
                                        $result_centres = $stmt->get_result();
                                        ?>
                                        <div class="form-check">
                                            <?php while ($centre = $result_centres->fetch_assoc()): ?>
                                                <input class="form-check-input" type="checkbox" name="centres[]" 
                                                       value="<?php echo htmlspecialchars($centre['centre_id']); ?>" 
                                                       id="centre_<?php echo htmlspecialchars($centre['centre_id']); ?>">
                                                <label class="form-check-label" for="centre_<?php echo htmlspecialchars($centre['centre_id']); ?>">
                                                    <?php echo htmlspecialchars($centre['name']); ?>
                                                </label><br>
                                            <?php endwhile; ?>
                                        </div>
                                        <?php $stmt->close(); ?>
                                    </div>

                                    <!-- Vaccines Checklist -->
                                    <div class="mb-3">
                                        <label class="form-label">Select Vaccines</label>
                                        <?php
                                        $stmt = $conn->prepare("SELECT vaccine_id, name FROM Vaccine");
                                        $stmt->execute();
                                        $result_vaccines = $stmt->get_result();
                                        ?>
                                        <div class="form-check">
                                            <?php while ($vaccine = $result_vaccines->fetch_assoc()): ?>
                                                <input class="form-check-input" type="checkbox" name="vaccines[]" 
                                                       value="<?php echo htmlspecialchars($vaccine['vaccine_id']); ?>" 
                                                       id="vaccine_<?php echo htmlspecialchars($vaccine['vaccine_id']); ?>">
                                                <label class="form-check-label" for="vaccine_<?php echo htmlspecialchars($vaccine['vaccine_id']); ?>">
                                                    <?php echo htmlspecialchars($vaccine['name']); ?>
                                                </label><br>
                                            <?php endwhile; ?>
                                        </div>
                                        <?php $stmt->close(); ?>
                                    </div>

                                    <!-- Child Information Fields -->
                                    <div class="mb-3">
                                        <label for="child_name" class="form-label">Child Name</label>
                                        <input type="text" class="form-control" id="child_name" name="child_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="age" class="form-label">Age</label>
                                        <input type="number" class="form-control" id="age" name="age" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="address" name="address" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="text" class="form-control" id="phone" name="phone" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="guardian_name" class="form-label">Guardian Name</label>
                                        <input type="text" class="form-control" id="guardian_name" name="guardian_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="date" name="date" required>
                                    </div>
                                    <button type="submit" class="btn btn-success" name="new_entry_submit">Add New Entry</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Logged In Content -->
            <div class="d-flex justify-content-between align-items-center">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username_id']); ?>!</h2>
                <a href="?action=logout" class="btn btn-danger">Logout</a>
            </div>

            <?php if ($_SESSION['login_type'] === 'patient' && !empty($due_vaccines)): ?>
                <!-- Due Vaccine Dates Display -->
                <div class="alert alert-warning mt-3">
                    <h4 class="alert-heading">Upcoming Vaccines:</h4>
                    <ul class="mb-0">
                        <?php foreach ($due_vaccines as $due): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($due['vaccine_name']); ?></strong> - 
                                Due on <?php echo htmlspecialchars(date("F j, Y", strtotime($due['due_date']))); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['login_type'] === 'patient' && !empty($child_info)): ?>
                <!-- Child Information Display -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="mb-0">Child Information</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th>Child ID</th>
                                <td><?php echo htmlspecialchars($child_info['child_id']); ?></td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td><?php echo htmlspecialchars($child_info['child_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Date of Birth</th>
                                <td><?php echo htmlspecialchars($child_info['dob']); ?></td>
                            </tr>
                            <tr>
                                <th>Age</th>
                                <td><?php echo htmlspecialchars($child_info['age']); ?></td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                <td><?php echo htmlspecialchars($child_info['address']); ?></td>
                            </tr>
                            <tr>
                                <th>Guardian Name</th>
                                <td><?php echo htmlspecialchars($child_info['guardian_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Contact Number</th>
                                <td><?php echo htmlspecialchars($child_info['contact_no']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if (!empty($assigned_doctor)): ?>
                    <!-- Assigned Doctor Information -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h4 class="mb-0">Assigned Healthcare Professional</h4>
                        </div>
                        <div class="card-body">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($assigned_doctor['name']); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($assigned_doctor['contact_info']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($_SESSION['login_type'] === 'healthcare_professional' || $_SESSION['login_type'] === 'admin'): ?>
                <!-- Search Form for Healthcare Professionals and Admin -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="mb-0">Search Records</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="mb-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <select class="form-control" name="search_type" required>
                                        <option value="child_id">Search by Child ID</option>
                                        <option value="child_name">Search by Child Name</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="search_value" required 
                                           placeholder="Enter search value">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100" name="query">Search</button>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($query_error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($query_error); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($query_results)): ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Child ID</th>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Guardian</th>
                                        <th>Contact</th>
                                        <th>Vaccine</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($query_results as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['child_id']); ?></td>
                                            <td><?php echo htmlspecialchars($result['child_name']); ?></td>
                                            <td><?php echo htmlspecialchars($result['age']); ?></td>
                                            <td><?php echo htmlspecialchars($result['guardian_name']); ?></td>
                                            <td><?php echo htmlspecialchars($result['contact_no']); ?></td>
                                            <td><?php echo htmlspecialchars($result['vaccine_name']); ?></td>
                                            <td><?php echo htmlspecialchars($result['due_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

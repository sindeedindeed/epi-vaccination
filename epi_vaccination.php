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
                // Treat as Child ID
                $child_id = (int)$input_username;
                $stmt = $conn->prepare("
                    SELECT * FROM Child
                    WHERE child_id = ?
                ");
                $stmt->bind_param("i", $child_id);
            } else {
                // Treat as Child Name
                $child_name = $input_username;
                $stmt = $conn->prepare("
                    SELECT * FROM Child
                    WHERE child_name = ?
                ");
                $stmt->bind_param("s", $child_name);
            }

            // Execute the query
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $child_info = $result->fetch_assoc();
                    // Store necessary info in session
                    $_SESSION['username_id'] = $input_username;
                    $_SESSION['login_type'] = $login_type;
                    $_SESSION['child_id'] = $child_info['child_id']; // Store Child ID
                    
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
            } else {
                $login_error = "Error executing query: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } elseif ($login_type === 'healthcare_professional') {
            // Determine if input is Professional ID or Name
            if (is_numeric($input_username)) {
                // Treat as Professional ID
                $prof_id = (int)$input_username;
                $stmt = $conn->prepare("
                    SELECT * FROM Healthcare_Professional
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $prof_id);
            } else {
                // Treat as Professional Name
                $prof_name = $input_username;
                $stmt = $conn->prepare("
                    SELECT * FROM Healthcare_Professional
                    WHERE name = ?
                ");
                $stmt->bind_param("s", $prof_name);
            }

            // Execute the query
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $prof_info = $result->fetch_assoc();
                    // Store necessary info in session
                    $_SESSION['username_id'] = $input_username;
                    $_SESSION['login_type'] = $login_type;
                    $_SESSION['professional_id'] = $prof_info['id']; // Store Professional ID
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $login_error = "Invalid Username/ID. No matching professional found.";
                }
            } else {
                $login_error = "Error executing query: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } elseif ($login_type === 'admin') {
            // Simple admin authentication (username must be 'admin')
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

// Handle Query Submission (only for healthcare professionals and admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query']) && isset($_SESSION['login_type'])) {
    $search_type = $_POST['search_type'];
    $search_value = trim($_POST['search_value']);

    if (empty($search_value)) {
        $query_error = "Please enter a value to search.";
    } else {
        // Check login type
        $login_type = $_SESSION['login_type'];

        if ($login_type == 'patient') {
            // Patients should already have their child_id stored in session
            $child_id = $_SESSION['child_id'];

            // Retrieve only the specific child's info
            $stmt = $conn->prepare("
                SELECT * FROM Child
                WHERE child_id = ?
            ");
            $stmt->bind_param("i", $child_id);

        } elseif ($login_type == 'healthcare_professional') {
            // Healthcare professionals have access only to their assigned children
            $professional_id = $_SESSION['professional_id'];

            if ($search_type === 'child_id') {
                $stmt = $conn->prepare("
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
                    FROM Child
                    JOIN Vaccination ON Child.child_id = Vaccination.child_id
                    JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                    JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                    WHERE Child.child_id = ?
                      AND Healthcare_Professional.id = ?
                ");
                $stmt->bind_param("ii", $search_value, $professional_id);
            } elseif ($search_type === 'child_name') {
                $like_search = "%" . $search_value . "%";
                $stmt = $conn->prepare("
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
                    FROM Child
                    JOIN Vaccination ON Child.child_id = Vaccination.child_id
                    JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                    JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                    WHERE Child.child_name LIKE ?
                      AND Healthcare_Professional.id = ?
                ");
                $stmt->bind_param("si", $like_search, $professional_id);
            } elseif ($search_type === 'professional_id') {
                $stmt = $conn->prepare("
                    SELECT * FROM Healthcare_Professional
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $search_value);
            } elseif ($search_type === 'professional_name') {
                $like_search = "%" . $search_value . "%";
                $stmt = $conn->prepare("
                    SELECT * FROM Healthcare_Professional
                    WHERE name LIKE ?
                ");
                $stmt->bind_param("s", $like_search);
            } else {
                $query_error = "Invalid search type selected for healthcare professional.";
            }
        } elseif ($login_type == 'admin') {
            // Admin can search all children and healthcare professionals
            if ($search_type === 'child_id') {
                $stmt = $conn->prepare("
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
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
                    SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
                    FROM Child
                    JOIN Vaccination ON Child.child_id = Vaccination.child_id
                    JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
                    JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
                    WHERE Child.child_name LIKE ?
                ");
                $stmt->bind_param("s", $like_search);
            } elseif ($search_type === 'professional_id') {
                $stmt = $conn->prepare("
                    SELECT * FROM Healthcare_Professional
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $search_value);
            } elseif ($search_type === 'professional_name') {
                $like_search = "%" . $search_value . "%";
                $stmt = $conn->prepare("
                    SELECT * FROM Healthcare_Professional
                    WHERE name LIKE ?
                ");
                $stmt->bind_param("s", $like_search);
            } else {
                $query_error = "Invalid search type selected for admin.";
            }
        } else {
            $query_error = "Invalid login type.";
        }

        // Execute the query if no errors
        if (empty($query_error)) {
            if (isset($stmt) && $stmt->execute()) {
                $result = $stmt->get_result();
                if ($login_type === 'healthcare_professional' && in_array($search_type, ['child_id', 'child_name'])) {
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $query_results[] = $row;
                        }
                    } else {
                        $query_error = "No records found matching your search criteria.";
                    }
                } elseif ($login_type === 'admin' && in_array($search_type, ['child_id', 'child_name'])) {
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $query_results[] = $row;
                        }
                    } else {
                        $query_error = "No records found matching your search criteria.";
                    }
                } else {
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $query_results[] = $row;
                        }
                    } else {
                        $query_error = "No records found matching your search criteria.";
                    }
                }
            } else {
                $query_error = "Error executing query: " . htmlspecialchars($stmt->error);
            }
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
}
// If logged in as patient and child_info is already set in session (from login)
if (isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'patient' && isset($_SESSION['child_id'])) {
    if (empty($child_info)) {
        // Fetch the child info based on child_id stored in session
        $child_id = $_SESSION['child_id'];
        $stmt = $conn->prepare("
            SELECT * FROM Child
            WHERE child_id = ?
        ");
        $stmt->bind_param("i", $child_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $child_info = $result->fetch_assoc();
            }
        }
        $stmt->close();

        // Fetch Assigned Doctor
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
    }
}

// If logged in as healthcare_professional, fetch assigned children
if (isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'healthcare_professional' && isset($_SESSION['professional_id'])) {
    $professional_id = $_SESSION['professional_id'];
    $stmt_assigned = $conn->prepare("
        SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date
        FROM Child
        JOIN Vaccination ON Child.child_id = Vaccination.child_id
        JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
        WHERE Vaccination.healthcare_professional_id = ?
        ORDER BY Vaccination.due_date ASC
    ");
    $stmt_assigned->bind_param("i", $professional_id);
    if ($stmt_assigned->execute()) {
        $result_assigned = $stmt_assigned->get_result();
        while ($row_assigned = $result_assigned->fetch_assoc()) {
            $assigned_children[] = $row_assigned;
        }
    }
    $stmt_assigned->close();
}

// If logged in as admin, fetch all children and all healthcare professionals
if (isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'admin') {
    // Fetch all children with vaccination info
    $stmt_all_children = $conn->prepare("
        SELECT Child.*, Vaccine.name AS vaccine_name, Vaccination.due_date, Healthcare_Professional.name AS doctor_name, Healthcare_Professional.contact_info AS doctor_contact
        FROM Child
        JOIN Vaccination ON Child.child_id = Vaccination.child_id
        JOIN Vaccine ON Vaccination.vaccine_id = Vaccine.vaccine_id
        JOIN Healthcare_Professional ON Vaccination.healthcare_professional_id = Healthcare_Professional.id
        ORDER BY Child.child_id ASC
    ");
    if ($stmt_all_children->execute()) {
        $result_all_children = $stmt_all_children->get_result();
        while ($row_all_children = $result_all_children->fetch_assoc()) {
            $all_children[] = $row_all_children;
        }
    }
    $stmt_all_children->close();

    // Fetch all healthcare professionals
    $stmt_all_profs = $conn->prepare("
        SELECT * FROM Healthcare_Professional
        ORDER BY id ASC
    ");
    if ($stmt_all_profs->execute()) {
        $result_all_profs = $stmt_all_profs->get_result();
        while ($row_all_profs = $result_all_profs->fetch_assoc()) {
            $all_healthcare_professionals[] = $row_all_profs;
        }
    }
    $stmt_all_profs->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EPIVaccination Portal</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <style>
        /* Optional: Additional styling for better visualization */
        .due-vaccine-list li {
            margin-bottom: 5px;
        }
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
                                <!-- Username/ID Field -->
                                <div class="mb-3">
                                    <label for="username_id" class="form-label">Username/ID</label>
                                    <input type="text" class="form-control" id="username_id" name="username_id" required>
                                </div>
                                <!-- Login Type Selection -->
                                <div class="mb-3">
                                    <label for="login_type" class="form-label">Login Type</label>
                                    <select class="form-select" id="login_type" name="login_type" required>
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
                </div>
            </div>
        <?php else: ?>
            <!-- Logged In Content -->
            <div class="d-flex justify-content-between align-items-center">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username_id']); ?>!</h2>
                <a href="?action=logout" class="btn btn-danger">Logout</a>
            </div>

            <?php if ($_SESSION['login_type'] === 'patient' && !empty($due_vaccines)): ?>
                <!-- Highlighted Due Vaccine Dates -->
                <div class="alert alert-warning mt-3" role="alert">
                    <h4 class="alert-heading">Upcoming Vaccines:</h4>
                    <ul class="mb-0 due-vaccine-list">
                        <?php foreach ($due_vaccines as $due): ?>
                            <li><strong><?php echo htmlspecialchars($due['vaccine_name']); ?></strong> - Due on <?php echo htmlspecialchars(date("F j, Y", strtotime($due['due_date']))); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif ($_SESSION['login_type'] === 'patient'): ?>
                <div class="alert alert-info mt-3" role="alert">
                    No upcoming vaccines scheduled.
                </div>
            <?php endif; ?>

            <hr>

            <?php if ($_SESSION['login_type'] === 'patient'): ?>
                <!-- Display Child Information Directly -->
                <?php if (!empty($child_info)): ?>
                    <h4>Your Child's Information:</h4>
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Child ID</th>
                                <th>Child Name</th>
                                <th>Date of Birth</th>
                                <th>Age</th>
                                <th>Address</th>
                                <th>Guardian Name</th>
                                <th>Contact No</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($child_info['child_id']); ?></td>
                                <td><?php echo htmlspecialchars($child_info['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($child_info['dob']); ?></td>
                                <td><?php echo htmlspecialchars($child_info['age']); ?></td>
                                <td><?php echo htmlspecialchars($child_info['address']); ?></td>
                                <td><?php echo htmlspecialchars($child_info['guardian_name']); ?></td>
                                <td><?php echo htmlspecialchars($child_info['contact_no']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No child information available.</div>
                <?php endif; ?>

                <!-- Display Assigned Doctor Information -->
                <?php if (!empty($assigned_doctor)): ?>
                    <h4>Assigned Doctor:</h4>
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Doctor Name</th>
                                <th>Contact Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($assigned_doctor['name']); ?></td>
                                <td><?php echo htmlspecialchars($assigned_doctor['contact_info']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No assigned doctor information available.</div>
                <?php endif; ?>

            <?php elseif ($_SESSION['login_type'] === 'healthcare_professional'): ?>
                <!-- Query Form for Healthcare Professionals -->
                <div class="card mb-4">
                    <div class="card-header">Search Information</div>
                    <div class="card-body">
                        <?php if (!empty($query_error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($query_error); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="search_type" class="form-label">Search By</label>
                                <select class="form-select" id="search_type" name="search_type" required>
                                    <option value="" disabled selected>Select search type</option>
                                    <option value="child_id">Child ID</option>
                                    <option value="child_name">Child Name</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="search_value" class="form-label">Search Value</label>
                                <input type="text" class="form-control" id="search_value" name="search_value" required>
                            </div>
                            <button type="submit" class="btn btn-primary" name="query">Search</button>
                        </form>
                    </div>
                </div>

                <!-- Display Assigned Children -->
                <?php if (!empty($assigned_children)): ?>
                    <h4>Children Assigned to You:</h4>
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Child ID</th>
                                <th>Child Name</th>
                                <th>Date of Birth</th>
                                <th>Age</th>
                                <th>Address</th>
                                <th>Guardian Name</th>
                                <th>Contact No</th>
                                <th>Vaccine Name</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_children as $child): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($child['child_id']); ?></td>
                                    <td><?php echo htmlspecialchars($child['child_name']); ?></td>
                                    <td><?php echo htmlspecialchars($child['dob']); ?></td>
                                    <td><?php echo htmlspecialchars($child['age']); ?></td>
                                    <td><?php echo htmlspecialchars($child['address']); ?></td>
                                    <td><?php echo htmlspecialchars($child['guardian_name']); ?></td>
                                    <td><?php echo htmlspecialchars($child['contact_no']); ?></td>
                                    <td><?php echo htmlspecialchars($child['vaccine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($child['due_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">No children assigned to you.</div>
                <?php endif; ?>

                <!-- Display Query Results -->
                <?php if (!empty($query_results)): ?>
                    <h4>Search Results:</h4>
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Child ID</th>
                                <th>Child Name</th>
                                <th>Date of Birth</th>
                                <th>Age</th>
                                <th>Address</th>
                                <th>Guardian Name</th>
                                <th>Contact No</th>
                                <th>Vaccine Name</th>
                                <th>Due Date</th>
                                <th>Assigned Doctor</th>
                                <th>Doctor Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($query_results as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['child_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['child_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['dob']); ?></td>
                                    <td><?php echo htmlspecialchars($row['age']); ?></td>
                                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                                    <td><?php echo htmlspecialchars($row['guardian_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                    <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['doctor_contact']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif (isset($query_results) && empty($query_results) && empty($query_error)): ?>
                    <div class="alert alert-info">No results to display.</div>
                <?php endif; ?>

            <?php elseif ($_SESSION['login_type'] === 'admin'): ?>
                <!-- Admin Content -->

                <div class="row">
                    <div class="col-md-12">
                        <h4>All Children Information:</h4>
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Child ID</th>
                                    <th>Child Name</th>
                                    <th>Date of Birth</th>
                                    <th>Age</th>
                                    <th>Address</th>
                                    <th>Guardian Name</th>
                                    <th>Contact No</th>
                                    <th>Vaccine Name</th>
                                    <th>Due Date</th>
                                    <th>Assigned Doctor</th>
                                    <th>Doctor Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_children as $child): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($child['child_id']); ?></td>
                                        <td><?php echo htmlspecialchars($child['child_name']); ?></td>
                                        <td><?php echo htmlspecialchars($child['dob']); ?></td>
                                        <td><?php echo htmlspecialchars($child['age']); ?></td>
                                        <td><?php echo htmlspecialchars($child['address']); ?></td>
                                        <td><?php echo htmlspecialchars($child['guardian_name']); ?></td>
                                        <td><?php echo htmlspecialchars($child['contact_no']); ?></td>
                                        <td><?php echo htmlspecialchars($child['vaccine_name']); ?></td>
                                        <td><?php echo htmlspecialchars($child['due_date']); ?></td>
                                        <td><?php echo htmlspecialchars($child['doctor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($child['doctor_contact']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-12">
                        <h4>All Healthcare Professionals:</h4>
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Years of Experience</th>
                                    <th>Qualification</th>
                                    <th>Contact Info</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_healthcare_professionals as $prof): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prof['id']); ?></td>
                                        <td><?php echo htmlspecialchars($prof['name']); ?></td>
                                        <td><?php echo htmlspecialchars($prof['role']); ?></td>
                                        <td><?php echo htmlspecialchars($prof['years_of_experience']); ?></td>
                                        <td><?php echo htmlspecialchars($prof['qualification']); ?></td>
                                        <td><?php echo htmlspecialchars($prof['contact_info']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </body>
    </tbody>
</table>
<?php endif; ?>

</div>
</body>
</html>
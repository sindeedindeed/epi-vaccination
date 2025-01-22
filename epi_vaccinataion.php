<?php
session_start();

// Database credentials
$host = 'localhost';
$username_db = 'root'; // Default username for XAMPP
$password_db = '';     // Default password for XAMPP
$database = 'epi_vaccination'; // Replace with your database name

// Connect to the database
$conn = new mysqli($host, $username_db, $password_db, $database);

// Check connection
if ($conn->connect_error) {
    die("<div class='alert alert-danger'>Connection failed: " . htmlspecialchars($conn->connect_error) . "</div>");
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $input_username = trim($_POST['username']);

    // Define allowed usernames (for simplicity, allow any non-empty username)
    if (!empty($input_username)) {
        // Optionally, you can implement more robust authentication here
        $_SESSION['username'] = $input_username;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $login_error = "Please enter a valid username.";
    }
}

// Handle Query Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query'])) {
    $search_type = $_POST['search_type'];
    $search_value = trim($_POST['search_value']);
    $query_error = "";
    $query_results = [];

    if (empty($search_value)) {
        $query_error = "Please enter a value to search.";
    } else {
        if ($search_type === 'child_id') {
            $stmt = $conn->prepare("
                SELECT 
                    c.id AS ChildID,
                    c.child_name,
                    c.dob,
                    TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) AS child_age,
                    c.address,
                    c.guardian_name,
                    c.contact_no,
                    v.name AS VaccineName,
                    v.dose,
                    vp.role AS HealthcareRole,
                    vp.years_of_experience,
                    vp.qualification,
                    vp.contact_info,
                    vc.name AS CentreName,
                    vc.location,
                    va.date_administered,
                    va.due_date
                FROM Child c
                JOIN vaccination va ON c.id = va.child_id
                JOIN vaccine v ON va.vaccine_id = v.vaccine_id
                JOIN healthcare_professional vp ON va.healthcare_professional_id = vp.id
                JOIN vaccination_centre vc ON va.centre_id = vc.centre_id
                WHERE c.id = ?
                ORDER BY va.date_administered DESC
            ");
            $stmt->bind_param("i", $search_value);
        } elseif ($search_type === 'child_name') {
            $stmt = $conn->prepare("
                SELECT 
                    c.id AS ChildID,
                    c.child_name,
                    c.dob,
                    TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) AS child_age,
                    c.address,
                    c.guardian_name,
                    c.contact_no,
                    v.name AS VaccineName,
                    v.dose,
                    vp.role AS HealthcareRole,
                    vp.years_of_experience,
                    vp.qualification,
                    vp.contact_info,
                    vc.name AS CentreName,
                    vc.location,
                    va.date_administered,
                    va.due_date
                FROM Child c
                JOIN vaccination va ON c.id = va.child_id
                JOIN vaccine v ON va.vaccine_id = v.vaccine_id
                JOIN healthcare_professional vp ON va.healthcare_professional_id = vp.id
                JOIN vaccination_centre vc ON va.centre_id = vc.centre_id
                WHERE c.child_name LIKE ?
                ORDER BY va.date_administered DESC
            ");
            $like_search = "%$search_value%";
            $stmt->bind_param("s", $like_search);
        } else {
            $query_error = "Invalid search type selected.";
        }

        if (empty($query_error)) {
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $query_results[] = $row;
                    }
                } else {
                    $query_error = "No records found matching your search criteria.";
                }
            } else {
                $query_error = "Error executing query: " . htmlspecialchars($stmt->error);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPIVaccination Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">

        <?php if (!isset($_SESSION['username'])): ?>
            <!-- Login Form -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <h2 class="text-center">Login</h2>
                    <?php if (isset($login_error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
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
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <a href="?action=logout" class="btn btn-danger">Logout</a>
            </div>

            <hr>

            <!-- Query Form -->
            <div class="card mb-4">
                <div class="card-header">Query Child Vaccination Information</div>
                <div class="card-body">
                    <?php if (isset($query_error) && !empty($query_error)): ?>
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
                            <th>Dose</th>
                            <th>Healthcare Role</th>
                            <th>Years of Experience</th>
                            <th>Qualification</th>
                            <th>Healthcare Contact Info</th>
                            <th>Centre Name</th>
                            <th>Location</th>
                            <th>Date Administered</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($query_results as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['ChildID']); ?></td>
                                <td><?php echo htmlspecialchars($row['child_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['dob']); ?></td>
                                <td><?php echo htmlspecialchars($row['child_age']); ?></td>
                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                <td><?php echo htmlspecialchars($row['guardian_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['VaccineName']); ?></td>
                                <td><?php echo htmlspecialchars($row['dose']); ?></td>
                                <td><?php echo htmlspecialchars($row['HealthcareRole']); ?></td>
                                <td><?php echo htmlspecialchars($row['years_of_experience']); ?></td>
                                <td><?php echo htmlspecialchars($row['qualification']); ?></td>
                                <td><?php echo htmlspecialchars($row['contact_info']); ?></td>
                                <td><?php echo htmlspecialchars($row['CentreName']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo htmlspecialchars($row['date_administered']); ?></td>
                                <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (isset($query_results) && empty($query_results) && empty($query_error)): ?>
                <div class="alert alert-info">No results to display.</div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</body>
</html>
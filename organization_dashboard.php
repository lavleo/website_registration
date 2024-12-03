<!---------->
<!-- PHP --->
<!---------->
<?php
    ini_set('display_errors', '0');
    session_start();

    // Check for session `tab_token` in the URL, which is used to retrieve `user_id` and `login_type` based on it
    if (!isset($_GET['tab_token']) || !isset($_SESSION["user_id_{$_GET['tab_token']}"]) || $_SESSION["login_type_{$_GET['tab_token']}"] !== "organization") 
    {
        header("Location: login.php");
        exit();
    }

    // Session variables
    $tab_token = $_GET['tab_token'];
    $user_id = $_SESSION["user_id_$tab_token"];
    $user_name = $_SESSION["user_name_$tab_token"];
    $conn = new mysqli("localhost", "root", "", "web_reg");

    // View active shifts
    $activeShifts = $conn->query("SELECT * FROM shifts WHERE organization_id = $user_id");

    // Handle logout
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['logout'])) 
    {
        unset($_SESSION["user_id_$tab_token"]);
        unset($_SESSION["login_type_$tab_token"]);
        header("Location: login.php");
        exit();
    }

    // Handle shift cancellation
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_shift'])) 
    {
        // Ensure ID is an integer
        $shift_id = intval($_POST['shift_id']);
        
        // Verify the administrator is the owner of the given shift
        $stmt = $conn->prepare("SELECT * FROM shifts WHERE organization_id = ? AND id = ?");
        $stmt->bind_param("ii", $user_id, $shift_id);
        $stmt->execute();
        $validShift = $stmt->fetch();
        $stmt->close();

        // Validate input
        if (!$validShift) 
        {
            echo "<p class='error'>Invalid shift or unauthorized action.</p>";
        }
        else
        {
            // Delete the shift and all its associated registrations
            $stmt = $conn->prepare("DELETE FROM registrations WHERE shift_id = ?");
            $stmt->bind_param("i", $shift_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM shifts WHERE id = ?");
            $stmt->bind_param("i", $shift_id);
            $stmt->execute();
            $stmt->close();

            echo "<p class='success'>Shift canceled successfully!</p>";
            header("Location: organization_dashboard.php?tab_token=$tab_token");
        }
    }

    // Handle new shift entry
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shift'])) 
    {
        $date = $_POST['date'];
        $time = $_POST['time'];
        $location = $_POST['location'];
        $work_type = $_POST['work_type'];
        $max_slots = $_POST['max_slots'];

        // Query to determine if an identical shift already exists for this organization
        $stmt = $conn->prepare("SELECT id FROM shifts WHERE date = ? AND time = ? AND location = ? AND work_type = ? AND organization_id = ?");
        $stmt->bind_param("ssssi", $date, $time, $location, $work_type, $user_id);
        $stmt->execute();
        $stmt->store_result();
        $identicalShift = $stmt->num_rows > 0;
        $stmt->close();

        // Validate shift entry
        if (DateTime::createFromFormat('Y-m-d', $date) === false) 
        {
            echo "<p class='error'>The date entered does not match the YY-MM-DD format.</p>";
        }
        else if (strtotime($time) === false) 
        {
            echo "<p class='error'>The time entered is invalid.</p>";
        }
        else if (preg_match("/^[a-zA-Z0-9\\s]+(\\,)? [a-zA-Z\\s]+(\\,)? [A-Z]{2} [0-9]{5,6}$/", $location) === 0) 
        {
            echo "<p class='error'>Please enter a valid US address (e.g. 123 Almeda St, Boulder, CO 80222).</p>";
        }
        else if (empty($work_type) === true) 
        {
            echo "<p class='error'>A work type was not provided.</p>";
        }
        else if (filter_var($max_slots, FILTER_VALIDATE_INT) === false || $max_slots <= 0) 
        {
            echo "<p class='error'>The max slots field must be a positive integer.</p>";
        }
        else if ($identicalShift)
        {
            echo "<p class='error'>This shift already exists.</p>";
        }
        else
        {
            $stmt = $conn->prepare("INSERT INTO shifts (date, time, location, work_type, max_slots, slots_filled, organization_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("ssssii", $date, $time, $location, $work_type, $max_slots, $user_id);

            if ($stmt->execute()) 
            {
                echo "<p class='success'>Shift added successfully!</p>";
                header("Location: organization_dashboard.php?tab_token=$tab_token");
            } 
            else 
            {
                echo "<p class='error'>Error adding shift. Please try again.</p>";
            }

            $stmt->close();
        }
    }

    // Batch import shifts from CSV
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_csv'])) 
    {
        if ($_FILES['csv_file']['error'] == UPLOAD_ERR_OK && mime_content_type($_FILES['csv_file']['tmp_name']) === "text/csv") 
        {
            // Open the uploaded CSV file
            $file = fopen($_FILES['csv_file']['tmp_name'], "r");
    
            while (($data = fgetcsv($file, 1000, ",")) !== FALSE) 
            {
                // Expecting [date, time, location, work_type, max_slots]
                list($date, $time, $location, $work_type, $max_slots) = $data;
    
                // Query to determine if an identical shift already exists for this organization
                $stmt = $conn->prepare("SELECT id FROM shifts WHERE date = ? AND time = ? AND location = ? AND work_type = ? AND organization_id = ?");
                $stmt->bind_param("ssssi", $date, $time, $location, $work_type, $user_id);
                $stmt->execute();
                $stmt->store_result();
                $identicalShift = $stmt->num_rows > 0;
                $stmt->close();

                // Validate fields
                if (DateTime::createFromFormat('Y-m-d', $date) === false) 
                {
                    continue;
                }
                else if (strtotime($time) === false) 
                {
                    continue;
                }
                else if (preg_match("/^[a-zA-Z0-9\\s]+(\\,)? [a-zA-Z\\s]+(\\,)? [A-Z]{2} [0-9]{5,6}$/", $location) === false) 
                {
                    continue;
                }
                else if (empty($work_type) === true) 
                {
                    continue;
                }
                else if (filter_var($max_slots, FILTER_VALIDATE_INT) === false || $max_slots <= 0) 
                {
                    continue;
                }
                else if ($identicalShift)
                {
                    continue;
                }
                else
                {
                    // If all validations pass, proceed to insert the shift into the database
                    $stmt = $conn->prepare("INSERT INTO shifts (date, time, location, work_type, max_slots, organization_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssii", $date, $time, $location, $work_type, $max_slots, $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
    
            fclose($file);
            echo "<p class='success'>Shifts imported successfully!</p>";
            header("Location: organization_dashboard.php?tab_token=$tab_token");
        } 
        else 
        {
            echo "<p class='error'>Error uploading file.</p>";
        }
    }
?>

<!---------->
<!-- HTML -->
<!---------->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Organization Dashboard</title>
        <link rel="stylesheet" type="text/css" href="css/orginization_dashboard.css">
    </head>

    <!-- Display organization name -->
    <h1>Organization Dashboard: <?php echo $user_name; ?></h1>

    <body>
         <!-- Logout Button -->
        <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
            <button type="submit" name="logout">Logout</button>
        </form>

        <div class="container">
            <h2>Manage Shifts</h2>
            <!-- Add New Shift Form -->
            <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                <input type="date" name="date" required>
                <input type="time" name="time" required>
                <input type="text" name="location" placeholder="Location" required>
                <input type="text" name="work_type" placeholder="Work Type" required>
                <input type="number" name="max_slots" placeholder="Max Slots" min="1" required>
                <button type="submit" name="add_shift">Add Shift</button>
            </form>

            <!-- CSV Import Form -->
            <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv" required>
                <button type="submit" name="import_csv">Import Shifts from CSV</button>
            </form>

            <!-- Display shifts created by the organization in this tab only -->
            <h3>Your Created Shifts</h3>
            <div class="shifts">  
                <?php while ($shift = $activeShifts->fetch_assoc()) 
                      { ?>
                        <div class='shift'>
                            <p>Date: <?php echo $shift['date']; ?></p>
                            <p>Time: <?php echo $shift['time']; ?></p>
                            <p>Location: <?php echo $shift['location']; ?></p>
                            <p>Work Type: <?php echo $shift['work_type']; ?></p>
                            <p>Slots Filled: <?php echo $shift['slots_filled'], "/", $shift['max_slots']; ?></p>
                            <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                                <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                                <button type="submit" name="cancel_shift">Cancel Shift</button>
                            </form>
                        </div>
                <?php } ?>
            </div>
        </div>
    </body>
</html>

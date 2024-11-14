<!---------->
<!-- PHP --->
<!---------->
<?php
    session_start();

    // Check for `tab_token` in the URL and retrieve `user_id` and `login_type` based on it
    if (!isset($_GET['tab_token'])) 
    {
        header("Location: login.php");
        exit();
    }

    $tab_token = $_GET['tab_token'];

    // Ensure the session contains the expected user data for this `tab_token`
    if (!isset($_SESSION["user_id_$tab_token"]) || $_SESSION["login_type_$tab_token"] !== "organization") 
    {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION["user_id_$tab_token"];
    $conn = new mysqli("localhost", "root", "", "web_reg");

    // Handle new shift entry
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shift'])) 
    {
        if ($_POST['tab_token'] !== $tab_token) 
        {
            header("Location: login.php");
            exit();
        }

        $date = $_POST['date'];
        $time = $_POST['time'];
        $location = $_POST['location'];
        $work_type = $_POST['work_type'];
        $max_slots = $_POST['max_slots'];

        $stmt = $conn->prepare("INSERT INTO shifts (date, time, location, work_type, max_slots, slots_filled, organization_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
        $stmt->bind_param("ssssii", $date, $time, $location, $work_type, $max_slots, $user_id);

        if ($stmt->execute()) 
        {
            echo "<p class='success'>Shift added successfully!</p>";
        } 
        else 
        {
            echo "<p class='error'>Error adding shift. Please try again.</p>";
        }

        $stmt->close();
    }

    // Batch import shifts from CSV
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_csv'])) 
    {
        if ($_POST['tab_token'] !== $tab_token) 
        {
            header("Location: login.php");
            exit();
        }

        if ($_FILES['csv_file']['error'] == UPLOAD_ERR_OK) 
        {
            $file = fopen($_FILES['csv_file']['tmp_name'], "r");

            while (($data = fgetcsv($file, 1000, ",")) !== FALSE) 
            {
                $stmt = $conn->prepare("INSERT INTO shifts (date, time, location, work_type, max_slots, slots_filled, organization_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
                $stmt->bind_param("ssssii", $data[0], $data[1], $data[2], $data[3], $data[4], $user_id);
                $stmt->execute();
            }

            fclose($file);
            echo "<p class='success'>Shifts imported successfully!</p>";
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
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body>
        <div class="container">
            <h2>Manage Shifts</h2>

            <!-- Add New Shift Form -->
            <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                <input type="hidden" name="tab_token" value="<?php echo htmlspecialchars($tab_token); ?>">
                <input type="date" name="date" required>
                <input type="time" name="time" required>
                <input type="text" name="location" placeholder="Location" required>
                <input type="text" name="work_type" placeholder="Work Type" required>
                <input type="number" name="max_slots" placeholder="Max Slots" min="1" required>
                <button type="submit" name="add_shift">Add Shift</button>
            </form>

            <!-- CSV Import Form -->
            <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>" enctype="multipart/form-data">
                <input type="hidden" name="tab_token" value="<?php echo htmlspecialchars($tab_token); ?>">
                <input type="file" name="csv_file" accept=".csv" required>
                <button type="submit" name="import_csv">Import Shifts from CSV</button>
            </form>

            <!-- Display shifts created by the organization in this tab only -->
            <h3>Your Created Shifts</h3>
                <?php
                    $result = $conn->query("SELECT * FROM shifts WHERE organization_id = $user_id");
                    while ($shift = $result->fetch_assoc()) 
                    {
                        echo "<div class='shift'>
                                <p>Date: {$shift['date']}</p>
                                <p>Time: {$shift['time']}</p>
                                <p>Location: {$shift['location']}</p>
                                <p>Work Type: {$shift['work_type']}</p>
                                <p>Slots Filled: {$shift['slots_filled']}/{$shift['max_slots']}</p>
                              </div>";
                    }
                ?>
        </div>
    </body>
</html>

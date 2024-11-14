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
    if (!isset($_SESSION["user_id_$tab_token"]) || $_SESSION["login_type_$tab_token"] !== "user") 
    {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION["user_id_$tab_token"];
    $conn = new mysqli("localhost", "root", "", "web_reg");

    // Fetch corporate_user_check for the user
    $query = $conn->prepare("SELECT corporate_user_check FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $query->bind_result($corporate_user_check);
    $query->fetch();
    $query->close();

    // Search and display shifts
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) 
    {
        $date = $_POST['date'];
        $time = $_POST['time'];
        $location = $_POST['location'];
        $work_type = $_POST['work_type'];

        $search_query = "SELECT * FROM shifts WHERE max_slots > slots_filled";
        if ($date) $search_query .= " AND date = '$date'";
        if ($time) $search_query .= " AND time = '$time'";
        if ($location) $search_query .= " AND location LIKE '%$location%'";
        if ($work_type) $search_query .= " AND work_type LIKE '%$work_type%'";

        $shifts = $conn->query($search_query);
    } 
    else 
    {
        $shifts = $conn->query("SELECT * FROM shifts WHERE max_slots > slots_filled");
    }

    // Handle shift registration
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_shift'])) 
    {
        $shift_id = $_POST['shift_id'];
        $group_size = $corporate_user_check ? intval($_POST['group_size']) : 1;

        $stmt = $conn->prepare("SELECT max_slots, slots_filled FROM shifts WHERE id = ?");
        $stmt->bind_param("i", $shift_id);
        $stmt->execute();
        $stmt->bind_result($max_slots, $slots_filled);
        $stmt->fetch();
        $stmt->close();

        if ($slots_filled + $group_size <= $max_slots) 
        {
            // Show waiver and register user
            echo "<div class='waiver'>
                    <p>This shift may involve certain risks, such as injuries or accidents. By signing up, you affirm that all members are at least 18 years of age. Please sign to confirm.</p>
                    <form action='user_dashboard.php?tab_token=$tab_token' method='POST'>
                        <input type='hidden' name='confirm_shift' value='$shift_id'>
                        <input type='hidden' name='group_size' value='$group_size'>
                        <button type='submit'>Accept & Register</button>
                    </form>
                </div>";
        } 
        else 
        {
            echo "<p class='error'>Shift is full. Please select a different shift.</p>";
        }
    }

    // Confirm shift registration
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_shift'])) 
    {
        $shift_id = $_POST['confirm_shift'];
        $group_size = $_POST['group_size'];

        $stmt = $conn->prepare("INSERT INTO registrations (user_id, shift_id, group_size) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $user_id, $shift_id, $group_size);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE shifts SET slots_filled = slots_filled + ? WHERE id = ?");
        $stmt->bind_param("ii", $group_size, $shift_id);
        $stmt->execute();
        $stmt->close();

        echo "<p class='success'>Registration confirmed!</p>";
    }
?>

<!---------->
<!-- HTML -->
<!---------->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>User Dashboard</title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <body>
        <div class="container">
            <h2>Available Shifts</h2>
            <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                <input type="date" name="date" placeholder="Date">
                <input type="time" name="time" placeholder="Time">
                <input type="text" name="location" placeholder="Location">
                <input type="text" name="work_type" placeholder="Work Type">
                <button type="submit" name="search">Search</button>
            </form>
            
            <div class="shift-list">
                <?php while ($shift = $shifts->fetch_assoc()) { ?>
                    <div class="shift">
                        <p>Date: <?php echo $shift['date']; ?></p>
                        <p>Time: <?php echo $shift['time']; ?></p>
                        <p>Location: <?php echo $shift['location']; ?></p>
                        <p>Work Type: <?php echo $shift['work_type']; ?></p>
                        <p>Available Slots: <?php echo $shift['max_slots'] - $shift['slots_filled']; ?></p>
                        <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                            <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                            <?php if ($corporate_user_check) { ?>
                                <input type="number" name="group_size" placeholder="Group Size" min="1" required>
                            <?php } ?>
                            <button type="submit" name="register_shift">Sign Up</button>
                        </form>
                    </div>
                <?php } ?>
            </div>

            <h2>Your Active Registrations</h2>
            <div class="registrations">  
                <?php 
                    $result = $conn->query("SELECT shifts.date, shifts.time, shifts.location, shifts.work_type, registrations.group_size 
                    FROM registrations 
                    JOIN shifts ON registrations.shift_id = shifts.id 
                    WHERE registrations.user_id = $user_id");
                    while ($registration = $result->fetch_assoc()) { ?>
                        <div class="registration">
                            <p>Date: <?php echo $registration['date']; ?></p>
                            <p>Time: <?php echo $registration['time']; ?></p>
                            <p>Location: <?php echo $registration['location']; ?></p>
                            <p>Work Type: <?php echo $registration['work_type']; ?></p>
                            <p>Group Size: <?php echo $registration['group_size']; ?></p>
                        </div>
                <?php } ?>
            </div>
        </div>
    </body>
</html>

<!---------->
<!-- PHP --->
<!---------->
<?php
    //ini_set('display_errors', '0');
    session_start();

    // Check for the session `tab_token` in the URL and retrieve `user_id` and `login_type` based on it
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
    $user_name = $_SESSION["user_name_$tab_token"];
    $conn = new mysqli("localhost", "root", "", "web_reg");

    // View active registrations
    $activeRegistrations = $conn->query(
    "SELECT shifts.date, shifts.time, shifts.location, shifts.work_type, registrations.shift_id, registrations.group_size, organizations.organization_name 
            FROM registrations
            JOIN shifts ON registrations.shift_id = shifts.id
            JOIN organizations ON shifts.organization_id = organizations.id
            WHERE registrations.user_id = $user_id"
    );

    // Check if the user is a corporate user
    $query = $conn->prepare("SELECT corporate_user_check FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $query->bind_result($corporate_user_check);
    $query->fetch();
    $query->close();

    // Handle logout
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['logout'])) 
    {
        unset($_SESSION["user_id_$tab_token"]);
        unset($_SESSION["login_type_$tab_token"]);
        header("Location: login.php");
        exit();
    }

    // Handle cancellation of a registration
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_registration'])) 
    {
        // Ensure ID is an integer
        $shift_id = intval($_POST['shift_id']); 

        // Verify the user is registered for the given shift
        $stmt = $conn->prepare("SELECT group_size FROM registrations WHERE user_id = ? AND shift_id = ?");
        $stmt->bind_param("ii", $user_id, $shift_id);
        $stmt->execute();
        $stmt->bind_result($group_size);
        $validShift = $stmt->fetch();
        $stmt->close();

        // Validate input
        if (!$validShift) 
        {
            echo "<p class='error'>Invalid registration or unauthorized action.</p>";
        }
        else
        {
            // Delete the registration
            $stmt = $conn->prepare("DELETE FROM registrations WHERE user_id = ? AND shift_id = ?");
            $stmt->bind_param("ii", $user_id, $shift_id);
            $stmt->execute();
            $stmt->close();
        
            // Update shift slots
            $stmt = $conn->prepare("UPDATE shifts SET slots_filled = slots_filled - ? WHERE id = ?");
            $stmt->bind_param("ii", $group_size, $shift_id);
            $stmt->execute();
            $stmt->close();
        
            echo "<p class='success'>Registration canceled successfully!</p>";
            header("Location: user_dashboard.php?tab_token=$tab_token");
        }
    }

    // Search and display shifts
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) 
    {
        $date = $_POST['date'];
        $time = $_POST['time'];
        $location = $_POST['location'];
        $work_type = $_POST['work_type'];
    
        // Retrieve the shift information, as well as the name of the sponsor organization. (Don't include shifts the user has registered for.)
        $search_query = "SELECT shifts.*, organizations.organization_name 
                         FROM shifts 
                         JOIN organizations ON shifts.organization_id = organizations.id 
                         WHERE shifts.max_slots > shifts.slots_filled 
                         AND shifts.id NOT IN (SELECT shift_id FROM registrations WHERE user_id = ?)";
    
        $params = [$user_id];
        $types = "i";
    
        // Account for search filters dynamically (if any)
        if ($date) 
        {
            $search_query .= " AND date = ?";
            $params[] = $date;
            $types .= "s";
        }
        if ($time) 
        {
            $search_query .= " AND time = ?";
            $params[] = $time;
            $types .= "s";
        }
        if ($location) 
        {
            $search_query .= " AND location LIKE ?";
            $params[] = "%$location%";
            $types .= "s";
        }
        if ($work_type) 
        {
            $search_query .= " AND work_type LIKE ?";
            $params[] = "%$work_type%";
            $types .= "s";
        }
    
        $stmt = $conn->prepare($search_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $shifts = $stmt->get_result();
        $stmt->close();
    }
    else
    {
       // Retrieve the shift information any time the page loads
       $shifts = $conn->query(
    "SELECT shifts.*, organizations.organization_name 
            FROM shifts 
            JOIN organizations ON shifts.organization_id = organizations.id 
            WHERE shifts.max_slots > shifts.slots_filled 
            AND shifts.id NOT IN (SELECT shift_id FROM registrations WHERE user_id = $user_id)"
       );
    }

    // Handle shift registration
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_shift'])) 
    {
        // Sanitize input
        $shift_id = intval($_POST['shift_id']);
        $group_size = $corporate_user_check ? intval($_POST['group_size']) : 1;
        $signature = trim($_POST['signature']);
        
        // Query to verify shift validity and availability
        $stmt = $conn->prepare("SELECT max_slots, slots_filled, date, time FROM shifts WHERE id = ?");
        $stmt->bind_param("i", $shift_id);
        $stmt->execute();
        $stmt->bind_result($max_slots, $slots_filled, $date, $time);
        $validShift = $stmt->fetch();
        $stmt->close();

        // Query to check if the user is already registered for a conflicting shift
        $stmt = $conn->prepare("SELECT registrations.id FROM registrations 
                                JOIN shifts ON registrations.shift_id = shifts.id 
                                WHERE registrations.user_id = ? AND shifts.date = ? AND shifts.time = ?");
        $stmt->bind_param("iss", $user_id, $date, $time);
        $stmt->execute();
        $stmt->store_result();
        $conflictingShift = $stmt->num_rows > 0;
        $stmt->close();

        // Validate input
        if ($signature !== $user_name) 
        {
            echo "<p class='error'>Signature does not match the account name. Please sign the waiver before registering.</p>";
        }
        else if (!$validShift) 
        {
            echo "<p class='error'>Invalid shift selection.</p>";
        }
        else if ($conflictingShift) 
        {
            echo "<p class='error'>You are already registered for a shift at this date and time.</p>";
        }
        else if ($group_size <= 0) 
        {
            echo "<p class='error'>Invalid group size.</p>";
        }    
        else if ($slots_filled + $group_size > $max_slots) 
        {
            echo "<p class='error'>This shift is full. Please try again later or reduce the number of members being registered.</p>";
        }
        else 
        {
            // Register for the shift
            $stmt = $conn->prepare("INSERT INTO registrations (user_id, shift_id, group_size) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $user_id, $shift_id, $group_size);
            $stmt->execute();
            $stmt->close();
    
            // Update shift slots
            $stmt = $conn->prepare("UPDATE shifts SET slots_filled = slots_filled + ? WHERE id = ?");
            $stmt->bind_param("ii", $group_size, $shift_id);
            $stmt->execute();
            $stmt->close();
    
            echo "<p class='success'>Successfully registered for the shift!</p>";
            header("Location: user_dashboard.php?tab_token=$tab_token");
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
        <title>User Dashboard</title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <!-- Display user name -->
    <h1>User Dashboard: <?php echo $user_name; ?></h1>

    <body>
         <!-- Logout Button -->
        <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
            <button type="submit" name="logout">Logout</button>
        </form>

        <div class="container">
             <!-- Search Available Shifts -->
            <h2>Search Shifts</h2>
            <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                <input type="date" name="date" placeholder="Date">
                <input type="time" name="time" placeholder="Time">
                <input type="text" name="location" placeholder="Location">
                <input type="text" name="work_type" placeholder="Work Type">
                <button type="submit" name="search">Search</button>
            </form>
            
            <!-- Displays Available Shifts and Registration button -->
            <h2>Available Shifts</h2>
            <div class="shift-list">
                <?php while ($shift = $shifts->fetch_assoc()) 
                      { ?>
                        <div class="shift">
                            <p><b>Organization: <?php echo $shift['organization_name']; ?></b></p>
                            <p>Date: <?php echo $shift['date']; ?></p>
                            <p>Time: <?php echo $shift['time']; ?></p>
                            <p>Location: <?php echo $shift['location']; ?></p>
                            <p>Work Type: <?php echo $shift['work_type']; ?></p>
                            <p>Available Slots: <?php echo $shift['max_slots'] - $shift['slots_filled']; ?></p>
                            <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                                <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                                <?php if ($corporate_user_check) 
                                      { ?>
                                        <input type="number" name="group_size" placeholder="Group Size" min="1" required>
                                <?php } ?>
                                <label><br><br>
                                    I, <input type="text" name="signature" placeholder="Your Full Name" required> understand that this shift may involve certain risks, such as injuries or accidents. By signing up, I affirm that I, and all members that I may be registering on behalf of, are at least 18 years of age.
                                </label>
                                <button type="submit" name="register_shift">Sign Up</button>
                            </form>
                        </div>
                <?php } ?>
            </div>

             <!-- Displays Shifts the User has registered for and Cancel button-->
            <h2>Your Active Registrations</h2>
            <div class="registration-list">  
                <?php while ($registration = $activeRegistrations->fetch_assoc()) 
                      { ?>
                        <div class="registration">
                            <p><b>Organization: <?php echo $registration['organization_name']; ?></b></p>
                            <p>Date: <?php echo $registration['date']; ?></p>
                            <p>Time: <?php echo $registration['time']; ?></p>
                            <p>Location: <?php echo $registration['location']; ?></p>
                            <p>Work Type: <?php echo $registration['work_type']; ?></p>
                            <p>Group Size: <?php echo $registration['group_size']; ?></p>
                            <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                                <input type="hidden" name="shift_id" value="<?php echo $registration['shift_id']; ?>">
                                <button type="submit" name="cancel_registration">Cancel Registration</button>
                            </form>
                        </div>
                <?php } ?>
            </div>
        </div>
    </body>
</html>

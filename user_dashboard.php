<!---------->
<!-- PHP --->
<!---------->
<?php
    // Disable error messages and start the session
    ini_set('display_errors', '0');
    session_start();

    // Check for session `tab_token` in the URL, which is used to retrieve `user_id` and `login_type` based on it
    if (!isset($_GET['tab_token']) || !isset($_SESSION["user_id_{$_GET['tab_token']}"]) || $_SESSION["login_type_{$_GET['tab_token']}"] !== "user") 
    {
        header("Location: login.php");
        exit();
    }

    // Session variables
    $tab_token = $_GET['tab_token'];
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

     // Check if the user is opted into marketing emails
     $query = $conn->prepare("SELECT marketing_opt_in FROM users WHERE id = ?");
     $query->bind_param("i", $user_id);
     $query->execute();
     $query->bind_result($marketing_opt_in);
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

    // Handle password change
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) 
    {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        // Retrieve the user's current hashed password from the database
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        // Validate input
        if (!password_verify($current_password, $hashed_password)) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                        <div class='modal-message error'>
                            <p>Current Password is incorrect.</p>
                        </div>
                  </div>";
        } 
        else if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$/", $new_password))  
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                        <div class='modal-message error'>
                            <p>Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.</p>
                        </div>
                  </div>";
        } 
        else if ($new_password !== $confirm_new_password) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                        <div class='modal-message error'>
                            <p>Passwords do not match.</p>
                        </div>
                  </div>";
        } 
        else 
        {
            // Update the user's password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_hashed_password, $user_id);

            if ($stmt->execute()) 
            {
                echo "<div class='modal-overlay' onclick='dismissModal()'>
                        <div class='modal-message success'>
                            <p>Password changed sucessfully.</p>
                        </div>
                      </div>";
            } 
            else 
            {
                echo "<div class='modal-overlay' onclick='dismissModal()'>
                        <div class='modal-message error'>
                            <p>Error changing password, please try again later.</p>
                        </div>
                      </div>";
            }

            $stmt->close();
        }
    }
    
    // Handle change in account preferences
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_preferences'])) 
    {
        $corporateCheck = isset($_POST['corporate_user_check']) ? 1 : 0; 
        $marketingCheck = isset($_POST['marketing_opt_in']) ? 1 : 0; 

        // Toggle for corporate user check
        if ($corporateCheck) 
        {
            $stmt = $conn->prepare("UPDATE users SET corporate_user_check = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        else
        {
            $stmt = $conn->prepare("UPDATE users SET corporate_user_check = 0 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    
        // Toggle for marketing opt-in
        if ($marketingCheck) 
        {
            $stmt = $conn->prepare("UPDATE users SET marketing_opt_in = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        else
        {
            $stmt = $conn->prepare("UPDATE users SET marketing_opt_in = 0 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }

        echo "<div class='modal-overlay' onclick='dismissModal()'>
                <div class='modal-message success'>
                    <p>Successfully changed preferences.</p>
                </div>
              </div>";
        header("Refresh: 1; URL=user_dashboard.php?tab_token=$tab_token");
    }

    // Handle account deletion
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account'])) 
    {
        if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== "yes") 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Please check the box confirming you understand what account deletion entails.</p>
                    </div>
                  </div>";
        } 
        else 
        {
            // Remove active registrations first
            $stmt = $conn->prepare("DELETE FROM registrations WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // Delete the user account
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // Destroy the session and redirect
            session_destroy();
            header("Location: login.php");
            exit();
        }
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
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Invalid registration or unauthorized action.</p>
                    </div>
                  </div>";
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
        
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message success'>
                        <p>Registration canceled successfully!</p>
                    </div>
                  </div>";
            header("Refresh: 1; URL=user_dashboard.php?tab_token=$tab_token");
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
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Signature does not match the account name. Please sign the waiver before registering.</p>
                    </div>
                 </div>";
        }
        else if (!$validShift) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Invalid shift selection.</p>
                    </div>
                 </div>";
        }
        else if ($conflictingShift) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>You are already registered for a shift at this date and time.</p>
                    </div>
                 </div>";
        }
        else if ($group_size <= 0) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Invalid group size.</p>
                    </div>
                 </div>";
        }    
        else if ($slots_filled + $group_size > $max_slots) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>This shift is full. Please try again later or reduce the number of members being registered.</p>
                    </div>
                 </div>";
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
    
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message success'>
                        <p>Successfully registered for the shift!</p>
                    </div>
                  </div>";
            header("Refresh: 1; URL=user_dashboard.php?tab_token=$tab_token");
        }        
    }

    $conn->close();
?>

<!---------->
<!-- HTML -->
<!---------->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>User Dashboard</title>
        <link rel="stylesheet" href="css/dashboard.css">
    </head>

    <!-- Top Bar -->
    <header>
        <h1>Welcome, <?php echo $user_name; ?>!</h1>
        <img src="images/u2.png" alt="Logo" />         
         <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
            <button type="submit" name="logout">Logout</button>
        </form>
    </header>

    <!-- Banner-image -->
     <div class="banner-image">
        <img src="images/CollaborationBanner.jpg" alt="Banner Image" />
    </div>

    <body>
        <div class="container">
            <!-- Choose an active tab -->
            <div class="tabs">
                <div class="tab">Search Shifts</div>
                <div class="tab">Active Registrations</div>
                <div class="tab">Account Management</div>
            </div>

            <div class="content">
                 <!-- Displays available Shifts the user can register for-->
                <div class="content-section" hidden="hidden">
                    <h2>Search Shifts</h2>
                    <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                        <input type="date" name="date" placeholder="Date">
                        <input type="time" name="time" placeholder="Time">
                        <input type="text" name="location" placeholder="Location">
                        <input type="text" name="work_type" placeholder="Work Type">
                        <button type="submit" name="search">Search</button>
                    </form>
                    
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
                                                    <input type="number" id="group-size" name="group_size" placeholder="Group Size" min="1" required>
                                        <?php } ?>
                                        <label>
                                            I, <input type="text" id="signature" name="signature" placeholder="Your Name" required> understand that this shift may involve certain risks, such as injuries or accidents. By signing up, I affirm that I—and all members that I may be registering on behalf of—are at least 18 years of age.
                                        </label>
                                        <button type="submit" name="register_shift">Sign Up</button>
                                    </form>
                                </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Displays Shifts the User has registered for and Cancel button-->
                <div class="content-section" hidden="hidden">
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

                <!-- Allows the user to make changes regarding their account-->
                <div class="content-section" hidden="hidden">
                    <h2>Change Password</h2>
                    <form method="POST" id="passwordForm" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                        <label for="current_password">Current Password:</label>
                        <input type="password" name="current_password" id="current_password" placeholder="Enter current password" required>
                        <label for="new_password">New Password:</label>
                        <input type="password" name="new_password" id="password" placeholder="Password" onkeyup='check();' pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$" title="Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)" required>
                        <input type="password" name="confirm_new_password" id="confirm_password" placeholder="Confirm Password" onkeyup='check();' required>
                        <span id='errorMessage'></span>
                        <button type="submit" name="change_password">Change Password</button>
                    </form>

                    <h2>Account Preferences</h2>
                    <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                        <!-- Change corporate user preference-->
                        <?php if ($corporate_user_check) 
                              { ?>
                                <label>
                                    <input type="checkbox" name="corporate_user_check" value="1" checked> I am a corporate user registering a group.
                                </label>
                        <?php } 
                              else 
                              { ?>
                                <label>
                                    <input type="checkbox" name="corporate_user_check" value="1"> I am a corporate user registering a group.
                                </label>
                        <?php } ?>

                         <!-- Change marketing opt in-->
                        <?php if ($marketing_opt_in) 
                              { ?>
                                <label>
                                    <input type="checkbox" name="marketing_opt_in" value="1" checked> I would like to receive marketing emails.
                                </label>
                        <?php } 
                              else 
                              { ?>
                                <label>
                                    <input type="checkbox" name="marketing_opt_in" value="1"> I would like to receive marketing emails.
                                </label>
                        <?php } ?>
                        <button type="submit" name="change_preferences">Change Preferences</button>
                    </form>

                    <h2 id="delete">Delete Account</h2>
                    <form method="POST" action="user_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>" onsubmit="return confirmDeletion();">
                        <label>
                            <input type="checkbox" name="confirm_delete" value="yes">
                             I confirm that I want to delete my account. I understand that once my account is deleted, any and all data associated with my account cannot be retrieved.
                        </label>
                        <button type="submit" name="delete_account" id="delete">Delete My Account</button>
                    </form>
                </div>
            </div>
        </div>
    </body>

    <script>
        // Indicate if passwords match
        var check = function() 
        {
            if (document.getElementById('password').value == document.getElementById('confirm_password').value) 
            {
                document.getElementById('errorMessage').style.color = 'green';
                document.getElementById('errorMessage').innerHTML = 'Passwords Matching';
            }
            else 
            {
                document.getElementById('errorMessage').style.color = 'red';
                document.getElementById('errorMessage').innerHTML = 'Passwords not matching';
            }
        }

        // To prevent user from submitting form if passwords don't match
        document.getElementById('passwordForm').addEventListener('submit', function(event) 
        {
            const newPassword = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) 
            {
                // Prevent form submission
                event.preventDefault();

                // Optional: Focus the first mismatched input field
                document.getElementById('confirm_password').focus();
            }
        });

        // For switching tabs
        document.addEventListener("DOMContentLoaded", () => 
        {
            const tabs = document.querySelectorAll(".tab");
            const contentSections = document.querySelectorAll(".content-section");

            tabs.forEach((tab, index) => 
            {
                tab.addEventListener("click", () => 
                {
                    // Remove active class from all tabs and sections
                    tabs.forEach(t => t.classList.remove("active"));
                    contentSections.forEach(section => section.style.display = "none");

                    // Activate clicked tab and corresponding section
                    tab.classList.add("active");
                    contentSections[index].style.display = "block";
                });
            });

            // Default to first tab active
            tabs[0].classList.add("active");
            contentSections[0].style.display = "block";
        });

        // Function to remove the modal overlay
        function dismissModal() 
        {
            const modalOverlay = document.querySelector(".modal-overlay");
            if (modalOverlay) 
            {
                modalOverlay.remove(); // Remove the overlay from the DOM
            }
        }

        // Add an event listener to dismiss the modal when clicking on the overlay
        document.addEventListener("DOMContentLoaded", () => 
        {
            const overlay = document.querySelector(".modal-overlay");
            if (overlay) 
            {
                overlay.addEventListener("click", dismissModal);
            }
        });

        // Browser confirmation for delete
        function confirmDeletion() 
        {
            return confirm("Are you sure you want to delete your account? This action cannot be undone.");
        }

        // Stop form resubmission popup on page refresh
        if ( window.history.replaceState ) 
        {
            window.history.replaceState( null, null, window.location.href );
        }
    </script>
</html>

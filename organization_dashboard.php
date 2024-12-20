<!---------->
<!-- PHP --->
<!---------->
<?php
    // Disable error messages and start the session
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

    // Check if the user is opted into marketing emails
    $query = $conn->prepare("SELECT marketing_opt_in FROM organizations WHERE id = ?");
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
        $stmt = $conn->prepare("SELECT password FROM organizations WHERE id = ?");
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
        $marketingCheck = isset($_POST['marketing_opt_in']) ? 1 : 0; 

        // Toggle for marketing opt-in
        if ($marketingCheck) 
        {
            $stmt = $conn->prepare("UPDATE organizations SET marketing_opt_in = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        else
        {
            $stmt = $conn->prepare("UPDATE organizations SET marketing_opt_in = 0 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }

        echo "<div class='modal-overlay' onclick='dismissModal()'>
                <div class='modal-message success'>
                    <p>Successfully changed preferences.</p>
                </div>
            </div>";
        header("Refresh: 1; URL=organization_dashboard.php?tab_token=$tab_token");
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
             // Fetch all shifts associated with the organization
             $stmt = $conn->prepare("SELECT id FROM shifts WHERE organization_id = ?");
             $stmt->bind_param("i", $user_id);
             $stmt->execute();
             $result = $stmt->get_result();
             $shift_ids = [];
             while ($row = $result->fetch_assoc()) 
             {
                 $shift_ids[] = $row['id'];
             }
             $stmt->close();
 
             // Delete all registrations associated with these shifts
             if (!empty($shift_ids)) 
             {
                 $placeholders = implode(',', array_fill(0, count($shift_ids), '?'));
                 $types = str_repeat('i', count($shift_ids));
 
                 $stmt = $conn->prepare("DELETE FROM registrations WHERE shift_id IN ($placeholders)");
                 $stmt->bind_param($types, ...$shift_ids);
                 $stmt->execute();
                 $stmt->close();
             }
 
             // Delete all shifts associated with the organization
             $stmt = $conn->prepare("DELETE FROM shifts WHERE organization_id = ?");
             $stmt->bind_param("i", $user_id);
             $stmt->execute();
             $stmt->close();
 
             // Delete the organization account
             $stmt = $conn->prepare("DELETE FROM organizations WHERE id = ?");
             $stmt->bind_param("i", $user_id);
             $stmt->execute();
             $stmt->close();

            session_destroy();
            header("Location: login.php");
            exit();
        }
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
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Invalid shift or unauthorized action.</p>
                    </div>
                  </div>";
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

            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message success'>
                        <p>Shift canceled successfully!.</p>
                    </div>
                  </div>";
            header("Refresh: 1; URL=organization_dashboard.php?tab_token=$tab_token");
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
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>The date entered does not match the YY-MM-DD format.</p>
                    </div>
                  </div>";
        }
        else if (strtotime($time) === false) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>The time entered is invalid. Please match HH:MM:SS format.</p>
                    </div>
                  </div>";            
        }
        else if (preg_match("/^[a-zA-Z0-9\\s]+(\\,)? [a-zA-Z\\s]+(\\,)? [A-Z]{2} [0-9]{5,6}$/", $location) === 0) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Please enter a valid US address (e.g. 123 Almeda St, Boulder, CO 80222).</p>
                    </div>
                  </div>";            
        }
        else if (empty($work_type) === true) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>A work type was not provided.</p>
                    </div>
                  </div>";            
        }
        else if (filter_var($max_slots, FILTER_VALIDATE_INT) === false || $max_slots <= 0) 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>The max slots field must be a positive integer.</p>
                    </div>
                  </div>";            
        }
        else if ($identicalShift)
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>This shift already exists.</p>
                    </div>
                  </div>";            
        }
        else
        {
            $stmt = $conn->prepare("INSERT INTO shifts (date, time, location, work_type, max_slots, slots_filled, organization_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("ssssii", $date, $time, $location, $work_type, $max_slots, $user_id);

            if ($stmt->execute()) 
            {
                echo "<div class='modal-overlay' onclick='dismissModal()'>
                        <div class='modal-message success'>
                            <p>Shift added successfully!</p>
                        </div>
                      </div>";
                header("Refresh: 1; URL=organization_dashboard.php?tab_token=$tab_token");
            } 
            else 
            {
                echo "<div class='modal-overlay' onclick='dismissModal()'>
                        <div class='modal-message error'>
                            <p>Error adding shift. Please try again.</p>
                        </div>
                      </div>";                 
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
            $shiftCounter = 0;

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
                else if (preg_match("/^[a-zA-Z0-9\\s]+(\\,)? [a-zA-Z\\s]+(\\,)? [A-Z]{2} [0-9]{5,6}$/", $location) === 0) 
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
                    $shiftCounter = $shiftCounter + 1;
                }
            }
    
            fclose($file);
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message success'>
                        <p>$shiftCounter shifts imported successfully!</p>
                    </div>
                  </div>";
            header("Refresh: 1; URL=organization_dashboard.php?tab_token=$tab_token");
        } 
        else 
        {
            echo "<div class='modal-overlay' onclick='dismissModal()'>
                    <div class='modal-message error'>
                        <p>Error uploading file.</p>
                    </div>
                  </div>";
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
        <title>Organization Dashboard</title>
        <link rel="stylesheet" href="css/dashboard.css">
    </head>

    <!-- Top Bar -->
    <header>
        <h1><?php echo $user_name; ?></h1>
        <img src="images/u2.png" alt="Logo" />         
         <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
            <button type="submit" name="logout">Logout</button>
        </form>
    </header>

    <!-- Banner-image -->
    <div class="banner-image">
        <img src="images/MeetingBanner.jpg" alt="Banner Image" />
    </div>

    <body>
        <div class="container">
            <!-- Choose an active tab -->
            <div class="tabs">
                <div class="tab">Add a Shift</div>
                <div class="tab">Created Shifts</div>
                <div class="tab">Account Management</div>
            </div>

            <div class="content">
                <!-- Add a New Shift Form -->
                <div class="content-section" hidden="hidden">
                    <h2>Add a Shift</h2>
                    <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                        <input type="date" name="date" required>
                        <input type="time" name="time" required>
                        <input type="text" name="location" placeholder="Location" required>
                        <input type="text" name="work_type" placeholder="Work Type" required>
                        <input type="number" name="max_slots" placeholder="Max Slots" min="1" required>
                        <button type="submit" name="add_shift">Add Shift</button>
                    </form>

                    <h2>Import Shifts</h2>
                    <p id="note"><i>Expecting</i> Date: [YYYY-MM-DD], Time: [HH:MM], Location: [Street, City, 2-letter-state_5-digit-zip], Work Type: [Anything], Max Slots: [Positive-int]</p>
                    <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>" enctype="multipart/form-data">
                        <input type="file" name="csv_file" accept=".csv" required>
                        <button type="submit" name="import_csv">Import Shifts from CSV</button>
                    </form>
                </div>

                <!-- Display shifts created by the organization in this tab only -->
                <div class="content-section" hidden="hidden">
                    <h2>Your Created Shifts</h2>
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

                <!-- Allows the user to make changes regarding their account-->
                <div class="content-section" hidden="hidden">
                    <h2>Change Password</h2>
                    <form method="POST" id="passwordForm" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
                        <label for="current_password">Current Password:</label>
                        <input type="password" name="current_password" id="current_password" placeholder="Enter current password" required>
                        <label for="new_password">New Password:</label>
                        <input type="password" name="new_password" id="password" placeholder="Password" onkeyup='check();' pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$" title="Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)" required>
                        <input type="password" name="confirm_new_password" id="confirm_password" placeholder="Confirm Password" onkeyup='check();' required>
                        <span id='errorMessage'></span>
                        <button type="submit" name="change_password">Change Password</button>
                    </form>

                    <h2>Account Preferences</h2>
                    <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>">
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
                    <form method="POST" action="organization_dashboard.php?tab_token=<?php echo htmlspecialchars($tab_token); ?>" onsubmit="return confirmDeletion();">
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

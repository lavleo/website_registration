<!-- Start output buffering -->
<?php ob_start(); ?> 

<!---------->
<!-- HTML -->
<!---------->
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Register</title>
        <link rel="stylesheet" href="css/login.css">
    </head>

    <!-- Company logo -->
    <div class="center-image">
        <img src="images/u2.png" alt="Logo" />
    </div>

    <body>
        <div class="container">
            <!-- User Registration Form -->
            <div id="user" class="tab-content active">
                <h2>User Registration</h2>
                <form action="register.php" method="POST">
                    <input type="hidden" name="registration_type" value="user">
                    <input type="text" name="first_name" placeholder="First Name" required>
                    <input type="text" name="last_name" placeholder="Last Name" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="tel" name="phone" placeholder="Phone Number: 000-000-0000" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" title="Must follow the 000-000-0000 format." required>
                    <input type="number" name="age" placeholder="Age" min="18" required>
                    <input type="password" name="password" id="password" placeholder="Password" onkeyup='check();' pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$" title="Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)" required>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" onkeyup='check();' required>
                    <span id='message'></span>
                    <br><br>
                    <label>
                        <input type="checkbox" name="marketing_opt_in" value="1"> I would like to receive marketing emails.
                    </label>
                    <label>
                        <input type="checkbox" name="corporate_user_check" value="1"> I am a corporate user registering a group.
                    </label>
                    <button type="submit">Register</button>
                </form>
            </div>

            <!-- Organization Registration Form -->
            <div id="organization" class="tab-content" hidden="hidden">
                <h2>Organization Registration</h2>
                <form action="register.php" method="POST">
                    <input type="hidden" name="registration_type" value="organization">
                    <input type="text" name="organization_name" placeholder="Organization Name" required>
                    <input type="email" name="email" placeholder="Organization Email" required>
                    <input type="tel" name="phone" placeholder="Organization Phone Number: 000-000-000" pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}" title="Must follow the 000-000-0000 format." required>
                    <input type="password" name="password" id="org_password" placeholder="Password" onkeyup='check();' pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$" title="Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)" required>
                    <input type="password" name="confirm_password" id="org_confirm_password" placeholder="Confirm Password" onkeyup='check();' required>
                    <span id='org_message'></span>
                    <br><br>
                    <label>
                        <input type="checkbox" name="marketing_opt_in" value="1"> I would like to receive marketing emails.
                    </label>
                    <button type="submit">Register</button>
                </form>
            </div>

            <!-- Choose registration type -->
            <div class="tabs">
                <button class="tab-button active" id="userButton" onclick="showTab('user')" hidden="hidden">User Registration</button>
                <button class="tab-button" id="organizationButton" onclick="showTab('organization')">Organization Registration</button>
            </div>
        </div>
            
        <script>
            // Toggle for user/organization registration
            function showTab(tabName) 
            {
                if (tabName == "organization")
                {
                    document.getElementById("user").setAttribute("hidden", "hidden");
                    document.getElementById("organization").removeAttribute("hidden");
                    document.getElementById("organizationButton").setAttribute("hidden", "hidden");
                    document.getElementById("userButton").removeAttribute("hidden");

                }
                else if (tabName == "user")
                {
                    document.getElementById("user").removeAttribute("hidden");
                    document.getElementById("organization").setAttribute("hidden", "hidden");
                    document.getElementById("organizationButton").removeAttribute("hidden");
                    document.getElementById("userButton").setAttribute("hidden", "hidden");
                }
            }

            // Indicate if passwords match
            var check = function() 
            {
                // User password
                if (document.getElementById('password').value == document.getElementById('confirm_password').value) 
                {
                    document.getElementById('message').style.color = 'green';
                    document.getElementById('message').innerHTML = 'Passwords Matching';
                }
                else 
                {
                    document.getElementById('message').style.color = 'red';
                    document.getElementById('message').innerHTML = 'Passwords not matching';
                }
                
                // Organization password
                if (document.getElementById('org_password').value == document.getElementById('org_confirm_password').value)
                {
                    document.getElementById('org_message').style.color = 'green';
                    document.getElementById('org_message').innerHTML = 'Passwords Matching';
                } 
                else 
                {                  
                    document.getElementById('org_message').style.color = 'red';
                    document.getElementById('org_message').innerHTML = 'Passwords not matching';
                }
            }
        </script>

        <!-- Login here button -->
        <p>Already have an account? <a href = "login.php">Login here</a></p>
        <br><br> 
    </body>
</html>

<!---------->
<!-- PHP --->
<!---------->
<?php
    // Start output buffering and the session
    session_start();

    // Disable error messages from printing to the page
    ini_set('display_errors', '0');

    // Define helper function for validation
    function validate_registration_form($type, $data) 
    {
        $errors = [];
        $conn = new mysqli("localhost", "root", "", "web_reg");

        if ($type === 'user') 
        {
            // Validate first name and last name
            if (empty($data['first_name']) || strlen($data['first_name']) > 50) 
            {
                $errors[] = "First name is required and must be less than 50 characters.";
            }

            if (empty($data['last_name']) || strlen($data['last_name']) > 50) 
            {
                $errors[] = "Last name is required and must be less than 50 characters.";
            }

            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) 
            {
                $errors[] = "Invalid email format.";
            }

            // Validate phone number
            if (!preg_match("/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/", $data['phone'])) 
            {
                $errors[] = "Phone number must follow the format 000-000-0000.";
            }

            // Validate age
            if (!filter_var($data['age'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 18]])) 
            {
                $errors[] = "Age must be 18 or older.";
            }

            // Validate password
            if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$/", $data['password'])) 
            {
                $errors[] = "Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.";
            }

            // Confirm the password
            if ($data['password'] !== $data['confirm_password']) 
            {
                $errors[] = "Passwords do not match. Please try again.";
            }

            // Ensure that the email/password fields are not duplicates
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
            $stmt->bind_param("ss", $data['email'], $data['phone']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) 
            {
                $errors[] = "A user with this email or phone number already exists.";
            }
            $stmt->close();
        } 
        else if ($type === 'organization') 
        {
            // Validate organization name
            if (empty($data['organization_name']) || strlen($data['organization_name']) > 100) 
            {
                $errors[] = "Organization name is required and must be less than 100 characters.";
            }

            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) 
            {
                $errors[] = "Invalid email format.";
            }

            // Validate phone number
            if (!preg_match("/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/", $data['phone'])) 
            {
                $errors[] = "Phone number must follow the format 000-000-0000.";
            }

            // Validate password
            if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$/", $data['password'])) 
            {
                $errors[] = "Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character.";
            }

            // Confirm the password
            if ($data['password'] !== $data['confirm_password']) 
            {
                $errors[] = "Passwords do not match. Please try again.";
            }

            // Ensure that the email/password fields are not duplicates
            $stmt = $conn->prepare("SELECT id FROM organizations WHERE email = ? OR phone = ?");
            $stmt->bind_param("ss", $data['email'], $data['phone']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) 
            {
                $errors[] = "An organization with this email or phone number already exists.";
            }
            $stmt->close();
        } 
        else 
        {
            $errors[] = "Invalid registration type.";
        }

        $conn->close();

        return $errors;
    }

    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] === "POST") 
    {
        // Validate registration type
        $registration_type = $_POST['registration_type'] ?? null;

        // Sanitize input data
        $data = array_map('htmlspecialchars', $_POST);

        // Perform validation
        $errors = validate_registration_form($registration_type, $data);

        // Proceed with user insertions into the DB
        if (empty($errors)) 
        {
            $conn = new mysqli("localhost", "root", "", "web_reg");
            $data['corporate_user_check'] = isset($data['corporate_user_check']) ? 1 : 0; 
            $data['marketing_opt_in'] = isset($data['marketing_opt_in']) ? 1 : 0; 

            if ($registration_type === "user") 
            {
                // Insert the new user
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, age, password, marketing_opt_in, corporate_user_check) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssisii", $data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['age'], $hashed_password, $data['marketing_opt_in'], $data['corporate_user_check']);
                $stmt->execute();
                $stmt->close();

                echo "<p id='success'>Registration successful! Redirecting to login...</p>";
                header("Refresh: 2; URL=login.php");
            } 
            else if ($registration_type === "organization") 
            {
                // Insert the new organization
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO organizations (organization_name, email, phone, password, marketing_opt_in) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $data['organization_name'], $data['email'], $data['phone'], $hashed_password, $data['marketing_opt_in']);
                $stmt->execute();
                $stmt->close();

                echo "<p id='success'>Registration successful! Redirecting to login...</p>";
                header("Refresh: 2; URL=login.php");
            }

            $conn->close();
        }
        else
        {
            // Display errors
            foreach ($errors as $error) 
            {
                echo "<p id='error'>$error</p>";
            }
        }
    }
?>

<!-- Flush the output buffer and send content to the browser -->
<?php ob_end_flush(); ?>
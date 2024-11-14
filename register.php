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
                    <input type="password" name="password" placeholder="Password" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$" title="Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)" required>
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
                    <input type="password" name="password" placeholder="Password" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,24}$" title="Password must be 8-24 characters long, with at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)" required>
                    <label>
                        <input type="checkbox" name="marketing_opt_in" value="1"> I would like to receive marketing emails
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
        </script>

        <!-- Login here button -->
        <p>Already have an account? <a href = "login.php">Login here</a></p> 
    </body>
</html>

<!---------->
<!-- PHP --->
<!---------->
<?php
    // Disable PHP error messages from printing to the page
    ini_set('display_errors', '0');

    // Method for registering
    if ($_SERVER["REQUEST_METHOD"] == "POST") 
    {
        $registration_type = $_POST['registration_type'];
        $conn = new mysqli("localhost", "root", "", "web_reg");

        // Fetch fields based on type of Registration
        if ($registration_type === "user") 
        {
            // Extract fields
            $first_name = htmlspecialchars($_POST['first_name']);
            $last_name = htmlspecialchars($_POST['last_name']);
            $email = htmlspecialchars($_POST['email']);
            $phone = htmlspecialchars($_POST['phone']);
            $age = $_POST['age'];
            $password = $_POST['password'];
            $marketing_opt_in = isset($_POST['marketing_opt_in']) ? 1 : 0; 
            $corporate_user_check = isset($_POST['corporate_user_check']) ? 1 : 0; 

            // Check for duplicate email
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                echo "<p id='error'>A user with this email already exists. Please use a different email.</p>";
                $stmt->close();
                $conn->close();
                exit();
            }
            $stmt->close();

            // Check for duplicate phone number
            $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                echo "<p id='error'>A user with this phone number already exists. Please use a different phone number.</p>";
                $stmt->close();
                $conn->close();
                exit();
            }
            $stmt->close();

            // If no duplicates, proceed with registration
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, age, password, marketing_opt_in, corporate_user_check) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssisii", $first_name, $last_name, $email, $phone, $age, $hashed_password, $marketing_opt_in, $corporate_user_check);

            if ($stmt->execute()) 
            {
                header("Refresh: 2; URL=login.php");
                echo "<p id='success'>Registration successful! Redirecting to login...</p>";
            } 
            else 
            {
                echo "<p id='error'>Error: Could not register. Please try again.</p>";
            }
        
            $stmt->close();
        }
        else
        {
            // Extract fields
            $organization_name = htmlspecialchars($_POST['organization_name']);
            $email = htmlspecialchars($_POST['email']);
            $phone = htmlspecialchars($_POST['phone']);
            $password = $_POST['password'];
    
            // Check for duplicate email
            $stmt = $conn->prepare("SELECT id FROM organizations WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                echo "<p id='error'>An organization with this email already exists. Please use a different email.</p>";
                $stmt->close();
                $conn->close();
                exit();
            }
            $stmt->close();

            // Check for duplicate phone number
            $stmt = $conn->prepare("SELECT id FROM organizations WHERE phone = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                echo "<p id='error'>A organization with this phone number already exists. Please use a different phone number.</p>";
                $stmt->close();
                $conn->close();
                exit();
            }
            $stmt->close();

            // If no duplicates, proceed with registration
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO organizations (organization_name, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $organization_name, $email, $phone, $hashed_password);
    
            if ($stmt->execute()) 
            {
                header("Refresh: 2; URL=login.php");
                echo "<p id='success'>Registration successful! Redirecting to login...</p>";
            } 
            else 
            {
                echo "<p id='error'>Error: Could not register. Please try again.</p>";
            }
    
            $stmt->close();
        }

        // Close connection
        $conn->close();
    }
?>

<!-- Flush the output buffer and send content to the browser -->
<?php ob_end_flush(); ?>
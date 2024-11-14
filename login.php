<!---------->
<!-- HTML -->
<!---------->
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Volunteer Here!</title>
        <link rel="stylesheet" type="text/css" href="css/login.css">
    </head>

    <!-- Company logo -->
    <div class="center-image">
        <img src="images/u2.png" alt="Logo" />
    </div>

    <body>
        <div class="container">
            <!-- User Login Form -->
            <div id="user" class="tab-content active">
                <h2>User Login</h2>
                <form action="login.php" method="POST">
                    <input type="hidden" name="login_type" value="user">
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>

            <!-- Organization Login Form -->
            <div id="organization" class="tab-content" hidden="hidden">
                <h2>Organization Login</h2>
                <form action="login.php" method="POST">
                    <input type="hidden" name="login_type" value="organization">
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>

             <!-- Choose login type -->
            <div class="tabs">
                <button class="tab-button" id="userButton" onclick="showTab('user')" hidden="hidden">User Login</button>
                <button class="tab-button" id="organizationButton" onclick="showTab('organization')" >Organization Login</button>
            </div>
        </div>

        <script>
            // Toggle for user/organization login
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

         <!-- Register here button -->
         <p>Don't have an account? <a href = "register.php">Register here</a></p> 
    </body>
</html>

<!---------->
<!-- PHP --->
<!---------->
<?php
    // Disable PHP error messages from printing to the page
    ini_set('display_errors', '0');
    session_start();

    // Method for logging in
    if ($_SERVER["REQUEST_METHOD"] == "POST") 
    {
        // Extract Fields
        $email = htmlspecialchars($_POST['email']);
        $password = $_POST['password'];
        $login_type = $_POST['login_type'];
        $conn = new mysqli("localhost", "root", "", "web_reg");

        // Fetch email, password based on type of Login
        if ($login_type === "user") 
        {
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
        } 
        else 
        {
            $stmt = $conn->prepare("SELECT id, password FROM organizations WHERE email = ?");
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($id, $hashed_password);

        // Verify Login
        if ($stmt->fetch() && password_verify($password, $hashed_password)) 
        {
            // Generate a unique token for the tab session
            $tab_token = bin2hex(random_bytes(16));
        
            // Store user_id and login_type in the session under tab-specific keys
            $_SESSION["user_id_$tab_token"] = $id;
            $_SESSION["login_type_$tab_token"] = $login_type;

            // Redirect to the appropriate dashboard with the token in the URL
            $redirect_page = $login_type === "user" ? "user_dashboard.php" : "organization_dashboard.php";
            header("Location: $redirect_page?tab_token=$tab_token");
            exit();
        }
        else 
        {
            echo "<p id='error'>Invalid login.</p>";
        }
        
        // Close connection
        $stmt->close();
        $conn->close();
    }
?>
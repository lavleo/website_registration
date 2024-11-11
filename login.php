<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" type="text/css" href="css/login.css" />
</head>
<body>
    <div class="center-image">
        <img src="images/u2.png" alt="Logo" />
    </div>
    <form action="" method="post">
        <h2>Login</h2>
        <?php if (isset($_GET['error'])) { ?>
            <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php } ?>
        <label for="email">Email</label>
        <input type="text" name="email" placeholder="email@address.com" required><br>
        
        <label for="password">Password</label>
        <input type="password" name="password" placeholder="Password" required><br>
        
        <button type="submit">Login</button>
    </form>

<?php 
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    $conn = new mysqli("localhost", "root", "", "weg_reg");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password);
    
    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
        $_SESSION['user_id'] = $id;
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: login.php?error=Invalid login.");
        exit();
    }
    
    $stmt->close();
    $conn->close();
}
?>

</body>
</html>

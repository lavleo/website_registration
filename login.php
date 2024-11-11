<!DOCTYPE html>
<html>
<link rel="stylesheet" type="text/css" href="login.css" />
<head>
    <title>LOGIN</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>

<body>
    <form action="login.php" method="post">
        <h2>LOGIN</h2> <?php if (isset($_GET['error'])) { ?> <p class="error"><?php echo $_GET['error']; ?></p>
        <?php } ?> <label>Email</label> <input type="text" name="uname" placeholder="email@address.com"><br>
        <label>Password</label> <input type="password" name="password" placeholder="Password"><br> <button
            type="submit">Login</button>
    </form>
</body>

<?php 
ini_set('display_errors', '0');
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];
    
    $conn = new mysqli("localhost", "root", "", "weg_reg");
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password);
    
    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
        $_SESSION['user_id'] = $id;
        header("Location: dashboard.php");
    } else {
        echo "Invalid login.";
    }
    

    $stmt->close();
    $conn->close();
} ?>

</html>
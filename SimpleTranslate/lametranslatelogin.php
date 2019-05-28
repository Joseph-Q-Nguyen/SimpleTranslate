<?php
    require_once 'login.php';
    require_once 'common_functions.php';

    $conn = new mysqli($hn, $un, $pw, $db);
    if ($conn->connect_error) die($conn->connect_error);
   
    ini_set('session.gc_maxlifetime', 600); # 10 minute timeout
    session_start();
    if (time() - $_SESSION['last_active_time'] > ini_get('session.gc_maxlifetime'))  destroy_session_and_data();

    if ($_GET['loggout'] == true)
        echo '<p style="color: green"; margin-bottom: 0; padding-top:0> You have been successfully logged out</p>';
    if ($_GET['signedout'] == true)
        echo '<p style="color: red"; margin-bottom: 0; padding-top:0> You have been logged out due to an error, please sign in again </p>';

    # new user being created
    if (isset($_POST['newusername']) && isset($_POST['email']) && isset($_POST['newpassword'])) {
        
        $newusername = sanitizeMySQL($conn, sanitizeString($_POST['newusername']));
        $email = sanitizeMySQL($conn, sanitizeString($_POST['email']));
        $newpassword = sanitizeMySQL($conn, sanitizeString($_POST['newpassword']));
        $saltPrefix = "1234";
        $saltSuffix = "5678";
        $hashString = $saltPrefix . $newpassword . $saltSuffix;
        
        $token = hash('ripemd128', $hashString);
        if (trim($_POST['newusername']) === '' || trim($_POST['email']) === '' || trim($_POST['newpassword']) === '')
            echo '<p style="color: red"; margin-bottom: 0; padding-top:0> One or more field is empty </p>';
        else if (strlen($newusername) > 32 || strlen($email) > 32 || strlen($newpassword) > 32)
            echo '<p style="color: red"; margin-bottom: 0; padding-top:0> Entries should be less than 32 characters </p>';
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            echo '<p style="color: red"; margin-bottom: 0; padding-top:0> Invalid email format </p>';
        else if (!userNameAvailable($conn, $newusername))
            echo '<p style="color: red"; margin-bottom: 0; padding-top:0> Username is already taken </p>';
        else if (!emailAvailable($conn, $email))
            echo '<p style="color: red"; margin-bottom: 0; padding-top:0> Email is already used </p>';
        else {
            $query = "INSERT INTO users(username, email, password) VALUES('$newusername', '$email', '$token')";
            $result = $conn->query($query);
            if (!$result) die($conn->error);
            #else $result->close();
            echo '<p style="color: green"; margin-bottom: 0; padding-top:0> Account Creation Success </p>';
        }
    }
    # user just logged in
    if (isset($_POST['username']) && isset($_POST['password'])) {
        if (trim($_POST['username']) === '' || trim($_POST['password']) === '')
            echo '<p style="color: red"; margin-bottom: 0; padding-top:0> One or more field is empty </p>';
        else {
            $username = sanitizeMySQL($conn, sanitizeString($_POST['username']));
            $password = sanitizeMySQL($conn, sanitizeString($_POST['password']));
            
            $query = "SELECT * FROM users WHERE username ='". $username . "'";
            $result = $conn->query($query);
            if (!$result) die($conn->error);
            else if ($result->num_rows) {
                $row = $result->fetch_array(MYSQLI_NUM);
                $result->close();
                
                $saltPrefix = "1234";
                $saltSuffix = "5678";
                $hashString = $saltPrefix . $password . $saltSuffix;
                
                $token = hash('ripemd128', $hashString);
                
                if ($token == $row[2]) {
                    session_start();
                    $str = "You are logged in as Name: ". $row[0] . ", " . "Email: ". $row[1];
                    echo '<p style="color: green"; margin-bottom: 0; padding-top:0>'. $str. '</p>';

                    $_SESSION['username'] = $_POST['username'];
                    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'];
                    $_SESSION['last_active_time'] = time();
                    
                }
                else
                    echo '<p style="color: red"; margin-bottom: 0; padding-top:0> Invalid username / password combination </p>';
            }
            else
                echo '<p style="color: red"; margin-bottom: 0; padding-top:0> Invalid username / password combination </p>';
        }
    }
    
    # user not logged in
    if (!issetAndNotEmpty($_SESSION['username'])) {   
        echo <<<_TEXT
            <html>
                <head>
                    <title>LameTranslate</title>
                </head>
                    <body>
                    Sign in
                    <form method="post" action="lametranslatelogin.php"enctype="multipart/form-data">
                        Username:<br>
                        <input type = "text" name = "username" required><br>
                        Password:<br>
                        <input type="password" name="password" required><br>
                        <input type="submit" value ="Log In">
                    </form>
        
                    Register
                    <form method="post" action="lametranslatelogin.php" enctype="multipart/form-data" onsubmit="return validate(this)">
                        Username:<br>
                        <input type = "text" name = "newusername" required><br>
                        Email:<br>
                        <input type = "email" name = "email" required><br>
                        Password:<br>
                        <input type = "password" name = "newpassword" required><br>
                        <input type="submit"value ="Create Account">
                    </form>
                
                    <script type="text/javascript" src="validate_functions.js"></script>
                    <script type="text/javascript">
                        function validate(form) {
                            fail = ""
                            fail += validateUsername(form.newusername.value)
                            fail += validatePassword(form.newpassword.value)
                            fail += validateEmail(form.email.value)

                            if (fail == "")  return true 
                            else { alert(fail); return false }
                        }
                    </script>
                </body>
            </html>
        _TEXT;
    }
    
    # user is logged in with session
    if (isset($_SESSION['username'])) {
        if ($_SESSION['ua'] != $_SERVER['HTTP_USER_AGENT']) differentUser();
        else if ($_SESSION['ip'] != $_SERVER['REMOTE_ADDR']) differentUser();
        else {
            header("Location: lametranslate.php");
        }
    }
    
    $result->close();
    $conn->close();

    function differentUser() {
        destroy_session_and_data();
        header("Location: lametranslatelogin.php?signedout=true");
    }
?>

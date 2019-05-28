<?php
    # https://www.texelate.co.uk/blog/how-to-test-for-empty-strings-in-php
    function issetAndNotEmpty($var) {
        $var = trim($var);
        if(isset($var) === true && $var === '') {
            return FALSE;
        }
        return TRUE;
    }
    
    function userNameAvailable($conn, $un) {
        $query = "SELECT * FROM users WHERE username = '". $un . "'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);
        if (mysqli_num_rows($result)) {
            $result->close();
            return FALSE;
        }
        $result->close();
        return TRUE;
    }
    
    function emailAvailable($conn, $em) {
        $query = "SELECT * FROM users WHERE email = '". $em . "'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);
        if (mysqli_num_rows($result)) {
            $result->close();
            return FALSE;
        }
        $result->close();
        return TRUE;
    }
    
    function getEmail($conn, $userName) {
        $query = "SELECT * FROM users WHERE username = '". $userName . "'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);
        $row = $result->fetch_array(MYSQLI_NUM);
        $result->close();
        return $row[1];
    }
    
    
    function sanitizeString($var) {
        $var = strip_tags($var);
        $var = htmlentities($var);
        return $var;
    }
    
    function sanitizeMySQL($connection, $var) {
        $var = $connection->real_escape_string($var);
        $var = sanitizeString($var);
        return $var;
    }
   
    function mysql_entities_fix_string($conn, $string) {
        return htmlentities(mysql_fix_string($conn, $string));
    }
    
    function mysql_fix_string($conn, $string) {
        if (get_magic_quotes_gpc())
            $string = stripslashes($string);
        return $conn->real_escape_string($string);
    }
    
    function destroy_session_and_data() {
        session_start();
        $_SESSION = array(); // Delete all the information in the array
        session_destroy();
    }
?>


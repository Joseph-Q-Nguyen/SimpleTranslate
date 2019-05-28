<?php
    require_once 'login.php';
    require_once 'common_functions.php';

    $conn = new mysqli($hn, $un, $pw, $db);
    if ($conn->connect_error) die($conn->connect_error);

    ini_set('session.gc_maxlifetime', 600); # 10 minute timeout
    session_start();

    if (time() - $_SESSION['last_active_time'] > ini_get('session.gc_maxlifetime'))  destroy_session_and_data();

    # log user out
    if (isset($_POST['exit'])) {
        destroy_session_and_data();
        header("Location: lametranslate.php");
    }

    $original_text = "Enter your text that you would like to translate!";    
    $select1Str = "<option value=\"any\" id=\"any\">Any</option>
                    <option value=\"secret\" id=\"secret\">Secret</option>";
    $select2Str = "<option value=\"secret\" id=\"secret\">Secret</option>                    
                    <option value=\"any\" id=\"any\">Any</option>";
    if (isset($_POST) && count($_POST) != 0) {
        if (isset($_POST['submit1']) || isset($_POST['submit2'])) {
            if (is_uploaded_file($_FILES['txtfile1']['tmp_name']) && $_FILES['txtfile1']['type'] != 'text/plain' || is_uploaded_file($_FILES['txtfile2']['tmp_name'])  && $_FILES['txtfile2']['type'] != 'text/plain')
                        echo '<p style="color: red"; margin-bottom: 0; padding-top:0> Submission Error: Submission type must be of .txt </p>';
            else {
                $username = $_SESSION['username'];
                $filename = $_POST['submit1'] ? $_FILES['txtfile1']['name'] : $_FILES['txtfile2']['name'];

                move_uploaded_file($_FILES['filename']['tmp_name'], $filename);
            
                $file_content = file_get_contents($filename);
                $file_content = str_replace("\n", "~",$file_content); # replace each newline character with ~ to made parsing easier
                $file_content = sanitizeMySQL($conn, sanitizeString($file_content));       
                $username = sanitizeMySQL($conn, sanitizeString($username));
                $filename = sanitizeMySQL($conn, sanitizeString($filename));

                if (isset($_POST['submit1']) && !is_uploaded_file($_FILES['txtfile1']['tmp_name']) || isset($_POST['submit2']) && !is_uploaded_file($_FILES['txtfile2']['tmp_name'])) {} #do nothing if no file is uploaded
                else if ($_POST['submit1'] && txtfile1Exists($conn, $username, $filename) || $_POST['submit2'] && txtfile2Exists($conn, $username, $filename)) 
                           echo "<p style=\"color: red\"; margin-bottom: 0; padding-top:0>$filename already exists</p>";
                else {
                    $query = $_POST['submit1'] ? "INSERT INTO uploads1(username, translation1, translation_name) VALUES('$username', '$file_content', '$filename')" : "INSERT INTO uploads2(username, translation2, translation_name) VALUES('$username', '$file_content', '$filename')";
                    $result = $conn->query($query);
                    if (!$result) die($conn->error);
                    echo "<p style=\"color: green\"; margin-bottom: 0; padding-top:0> Succesfully uploaded $filename </p>";
                }
            }
        }
        else {
            $original_text = $_POST['contentfrom'];
            $translated = $original_text;
           
            $select1Str = "<option value=\"any\" id=\"any\">Any</option>";
            $secretIsSelected = ($_POST['langfrom'] === 'secret') ? "<option selected value=\"secret\" id=\"secret\">Secret</option>" : "<option value=\"secret\" id=\"secret\">Secret</option>";
            $select1Str .= $secretIsSelected;

            $select2Str = "<option value=\"secret\" id=\"secret\">Secret</option>";
            $anyIsSelected = ($_POST['langto'] === 'any') ? "<option selected value=\"any\" id=\"any\">Any</option>" : "<option value=\"any\" id=\"any\">Any</option>";
            $select2Str .= $anyIsSelected;

            if ($_POST['langfrom'] === 'any' && $_POST['langto'] === 'secret') 
                $translated = translateToSecret($_POST['contentfrom']);  
            else if ($_POST['langfrom'] === 'secret' && $_POST['langto'] === 'any')
                $translated = translateFromSecret($_POST['contentfrom']); 
            else if ($_POST['langfrom'] === $_POST['langto']) {} # dont do anything with translation
            else if ($_POST['langfrom'] === 'any' || $_POST['langfrom'] === 'secret' || $_POST['langto'] === 'secret' || $_POST['langto'] === 'any'){
                # if one of the default translation is chosen with personal translation, give an error
                echo "<p style=\"color: red\"; margin-bottom: 0; padding-top:0>Translation Error: You must choose both of your own translations</p>";
            }
            else { # apply translation algorithmn
                $t1Content = getTranslation1ByName($conn, $_POST['langfrom']);
                $t2Content = getTranslation2ByName($conn, $_POST['langto']);
                $t1Content = sanitizeMySQL($conn, sanitizeString($t1Content));
                $t2Content = sanitizeMySQL($conn, sanitizeString($t2Content));

                $t1Array = explode("~", $t1Content);
                $t2Array = explode("~", $t2Content);

                if (count($t1Array) != count($t2Array))
                    echo "<p style=\"color: red\"; margin-bottom: 0; padding-top:0>Translation Error: Translation files are not compatible, check your syntax<br>
                    1) Make sure thats each word is separated by ONE and ONLY ONE newline character<br>
                    2) Make sure that each word is mapped to another word on the other text file i.e. the number of words match<br>
                    3) Make sure that there are no newline characters at the beginning and end of textfiles<br>
                    4) Make sure that every word is unique, this translator is not sophisticated enough to handle multiple translations</p>";
                else {
                    $translated = "";
                    $translationArray = array();
                    $inputArray = explode(" ", $original_text);

                    # first map each word with itself in the case that user didn't give translation for a word
                    for ($i = 0; $i < count($inputArray); $i++) {
                        $inputArray[$i] = strtolower($inputArray[$i]);
                        $translationArray[$inputArray[$i]] = $inputArray[$i];
                    }


                    # then map the translation, overriding any existing untranslated values
                    for ($i = 0; $i < count($t1Array); $i++) {
                        $translationArray[strtolower($t1Array[$i])] = strtolower($t2Array[$i]);
                    }

                    # add each input word into string, replaceing it with translation if exists
                    foreach ($inputArray as $word)
                        $translated .= $translationArray[strtolower($word)] . " ";
                }
            }
           
            # sanitize variables that will be used in form
            $original_text = sanitizeString($original_text);
            $translated = sanitizeString($translated);
        }
    }
    
    $str = "You are not logged in click <a href=\"lametranslatelogin.php\">here</a> to login or register<br>";
    $upload1Str = "<br>";
    $upload2Str = "<br>";
    if (isset($_SESSION['username'])) {
        if ($_SESSION['ua'] != $_SERVER['HTTP_USER_AGENT']) differentUser();
        else if ($_SESSION['ip'] != $_SERVER['REMOTE_ADDR']) differentUser();
        else {
            $username = $_SESSION['username'];
            $str = "<p style=\"color: green\"; margin-bottom: 0; padding-top:0>You are logged in as username: $username</p>". 
            "<form method=\"post\" action=\"lametranslate.php\"enctype=\"multipart/form-data\">
             <input type=\"hidden\" name=\"exit\">
            <input type=\"submit\" value =\"Log Out\">
            </form>";
            #echo '<p style="color: green"; margin-bottom: 0; padding-top:0>'. $str . '</p>';
            $upload1Str = "Upload a Language File to Translate from <input type=\"file\" name=\"txtfile1\" id=\"txtfile1\"accept=\".txt\"><input type=\"submit\" name=\"submit1\" value =\"Upload\"><br>";
            $upload2Str = "Upload a Language File to Translate to <input type=\"file\" name=\"txtfile2\" id=\"txtfile2\"accept=\".txt\"><input type=\"submit\" name=\"submit2\" value =\"Upload\"><br>";

            $translations1 = getTranslations1($conn, $username);
            $translations2 = getTranslations2($conn, $username);

            foreach ($translations1 as $t) {
                if ($_POST['langfrom'] === $t)
                    $select1Str .= "<option selected value=\"$t\" id=\"$t\">$t</option>";
                else
                    $select1Str .= "<option value=\"$t\" id=\"$t\">$t</option>";
            } 

            foreach ($translations2 as $t) {
                if ($_POST['langto'] === $t)
                    $select2Str .= "<option selected value=\"$t\" id=\"$t\">$t</option>";
                else
                    $select2Str .= "<option value=\"$t\" id=\"$t\">$t</option>";
            } 

            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['last_active_time'] = time();
        }
    }

        
     echo <<<_TEXT
            <html>
                <head>
                    <title>
                        LameTranslate
                    </title>
                </head>
                <body>
                    <p>Welcome To Lame Translate!<br>
                        $str
                        How to use translator:<br>  
                        &nbsp;&nbsp;&nbsp;&nbsp;First, upload a text file of a language of your choice to translate from<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;Next, upload a textfile of a language of your choice to translate to<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;Then type your entry in the text field and click translate to get a translation<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;The text files will consist of words seperated by new lines, each word will be considered mapped to the respective word from the other textfile<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;If no translation is given, then the default translation will be from English to a Secret language (see if you can found out what it is!)<br> 
                        &nbsp;&nbsp;&nbsp;&nbsp;Your uploaded files will be saved in the selection drop down menus<br>  
                    </p>
                    <form method="post" action="lametranslate.php"enctype="multipart/form-data">
                        Language to Translate From: 
                        <select name="langfrom">
                            $select1Str;
                        </select>
                        $upload1Str
                        Language to Translate To:
                        <select name="langto">
                            $select2Str;
                        </select>
                        $upload2Str
                        <textarea name="contentfrom" rows="10" cols="50">$original_text
                        </textarea>
                        <textarea name="contentto" rows="10" cols="50" readonly>$translated
                        </textarea><br>
                        <input type="submit" value ="Translate">
                    </form>
                </body>
            </html>
        _TEXT;

    function translateToSecret($string) {
        $split = str_split($string);
        for ($i = 0; $i < count($split); $i++)
            $split[$i] = convertToSecret($split[$i]);
        return implode($split);
    }


    function convertToSecret($char) {
        if ($char === " ")
            return $char;
        else {
            $ascii = ord($char);
            return chr($ascii + 1);
        }
    }

    function translateFromSecret($string) {
        $split = str_split($string);
        for ($i = 0; $i < count($split); $i++)
            $split[$i] = convertFromSecret($split[$i]);
        return implode($split);
    }


    function convertFromSecret($char) {
        if ($char === " ")
            return $char;
        else {
            $ascii = ord($char);
            return chr($ascii - 1);
        }
    }

    function differentUser() {
        destroy_session_and_data();
        header("Location: lametranslate.php?signedout=true");
    }

    function txtfile1Exists($conn, $username, $filename) {
        $query = "SELECT * FROM uploads1 WHERE username='$username' AND translation_name='$filename'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);
        if (mysqli_num_rows($result)) {
            $result->close();
            return TRUE;
        }
        $result->close();
        return FALSE;
    }    

    function txtfile2Exists($conn, $username, $filename) {
        $query = "SELECT * FROM uploads2 WHERE username = '$username' AND translation_name = '$filename'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);
        if (mysqli_num_rows($result)) {
            $result->close();
            return TRUE;
        }
        $result->close();
        return FALSE;
    }  

    function getTranslations1($conn, $username) {
        $query = "SELECT * FROM uploads1 WHERE username = '". $username . "'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);

        $arr = array();
        $rows = $result->num_rows;
        for ($j = 0 ; $j < $rows ; ++$j) {
            $result->data_seek($j);
            $row = $result->fetch_array(MYSQLI_ASSOC);
            array_push($arr, $row['translation_name']);
        }
        $result->close();
        return $arr;
    }

    function getTranslations2($conn, $username) {
        $query = "SELECT * FROM uploads2 WHERE username = '". $username . "'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);

        $arr = array();
        $rows = $result->num_rows;
        for ($j = 0 ; $j < $rows ; ++$j) {
            $result->data_seek($j);
            $row = $result->fetch_array(MYSQLI_ASSOC);
            array_push($arr, $row['translation_name']);
        }
        $result->close();
        return $arr;
    }

    function getTranslation1ByName($conn, $name) {
        $query = "SELECT * FROM uploads1 WHERE translation_name = '$name'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);
       
        $row = $result->fetch_array(MYSQLI_NUM);
        $result->close();
        return $row[1];
    }

    function getTranslation2ByName($conn, $name) {
        $query = "SELECT * FROM uploads2 WHERE translation_name = '$name'";
        $result = $conn->query($query);
        if (!$result) die($conn->error);
       
        $row = $result->fetch_array(MYSQLI_NUM);
        $result->close();
        return $row[1];
    }
 
?>

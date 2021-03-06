<?php
session_start();
$_SESSION['Authenticated'] = false;
include "db_connection.php";

try {
    if (isset($_REQUEST['checkAccount'])) {
        $acc = $_REQUEST['checkAccount'];
        $query = $connection->prepare("select account from users where account = :acc");
        $query->execute(array('acc' => $acc));
        
        if ($query->rowCount() == 0)
            echo 'YES';
        else 
            echo 'NO'; 
        
        exit();
    }
}
catch (Exception $e) {
    echo 'Failed';
    exit();
}

try {
    if (!isset($_POST['account']) || !isset($_POST['pwd']) || !isset($_POST['re_pwd']) 
        || !isset($_POST['full_name']) || !isset($_POST['phone'])|| !isset($_POST['city'])) {
        header("Location: index.php");
        exit();
    }
    
    if (empty($_POST['account']) || empty($_POST['pwd'])|| empty($_POST['re_pwd'])
        || empty($_POST['full_name'])|| empty($_POST['phone'])|| empty($_POST['city'])) {   
        throw new Exception('Please input all information.');
    }
    
    if ($_POST['pwd'] != $_POST['re_pwd']) {
        throw new Exception('Password mismatch');
    }

    $acc = $_POST['account'];
    $pwd = $_POST['pwd'];
    $fname = $_POST['full_name'];
    $phone = $_POST['phone'];
    $city = $_POST['city'];
    
    
    $query = $connection->prepare("select account from users where account = :acc");
    $query->execute(array('acc' => $acc));

    if ($query->rowCount() == 0) 
    {
        $salt = strval(rand(1000,9999));
        
        $hashvalue = hash('sha256', $salt . $pwd);
        $query = $connection->prepare("insert into users (account, password, salt, full_name, phone_number, city) 
                                         values (:acc, :pwd, :salt, :fname, :phone, :city)");
        $query->execute(array('acc' => $acc, 'pwd' => $hashvalue, 'salt' => $salt, 'fname' => $fname, 'phone' => $phone, 'city' => $city));
        
        echo <<<EOT
            <!DOCTYPE html>
            <html>
                <body>
                    <script>
                        alert("Register Success! Please login.");
                        window.location.replace("index.php");
                    </script>
                </body>
            </html>
        EOT;
        exit();
    }
    else if ($query->rowCount() != 0)
        throw new Exception("Account has been registered!");
    else
        throw new Exception("Login failed.");
}

catch(Exception $e)
{
    $msg=$e->getMessage();
    session_unset(); 
    session_destroy(); 

    echo <<<EOT
      <!DOCTYPE html>
      <html>
          <body>
              <script>
                  alert("$msg");
                  window.location.replace("index.php");
              </script>
          </body>
      </html>
    EOT;
}

?>
<?php 

class DbHandler
{
    private $con;
    private $userId;

    function setUserId($userId)
    {
        $this->userId = $userId;
    }

    function getUserId()
    {
        return $this->userId;
    }

    function __construct()
    {
        require 'DbCon.php';
        $db = new DbCon;
        $this->con = $db->Connect();
    }

    function registerUser($name,$email,$password)
    {
        if(!$this->isEmailExist($email))
        {
            $hashPassword = password_hash($password, PASSWORD_DEFAULT);
            $username = str_replace(' ','',$name);
            $username = $username.rand(10,99999999);
            $query = "INSERT INTO users (name,username,email,password) VALUES (?,?,?,?)";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('ssss',$name,$username,$email,$hashPassword);
            if ($stmt->execute()) 
            {
                $uid = $stmt->insert_id;
                $this->setUserId($uid);
                $code = password_hash($email.time(),PASSWORD_DEFAULT);
                $code = str_replace('/','socialcodia',$code);
                $codeForEmailVerification = 1;
                if($this->sendCodeToserver($code,$codeForEmailVerification))
                {
                    return USER_CREATED;
                }
            }
            else
                return FAILED_TO_CREATE_ACCOUNT;
        }
        else
            return EMAIL_ALREADY_EXIST;
    }

    function login($email,$password)
    {
        if($this->isEmailExist($email))
        {
            $hashPassword = $this->getPasswordByEmail($email);
            if(password_verify($password, $hashPassword))
            {
                if($this->isEmailVerified($email))
                {
                    return LOGIN_SUCCESS;
                }
                else
                    return EMAIL_NOT_VERIFEIED;
            }
            else
                return WRONG_PASSWORD;
        }
        else
            return EMAIL_NOT_EXIST;
    }

    function verifyEmail($email,$code)
    {
        $user = $this->getUserByEmail($email);
        $userId = $user['id'];
        $this->setUserId($userId);
        $dbCode = $this->getCode(1);
        if($dbCode==$code)
        {
            if(!$this->isEmailVerified($email))
            {
                if($this->setEmailIsVerfied($email))
                    return EMAIL_VERIFIED;
                else
                    return EMAIL_VERIFICATION_FAILED;
            }
            else
                return EMAIL_ALREADY_VERIFIED;
        }
        else
            return INVAILID_CODE;
    }

    function getUserByEmail($email)
    {
        $query = "SELECT id,name,email,bio,image,verified FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($id,$name,$email,$bio,$image,$verified);
        $stmt->fetch();
        $user['id'] = $id;
        $user['name'] = $name;
        $user['email'] = $email;
        $user['bio'] = $bio;
        if(!empty($image))
            $user['image'] = $image;
        $user['image'] = WEBSITE_DOMAIN."/image/user.png";
        $user['verified'] = $verified;
        return $user;
    }

    function sendCodeToserver($code,$codeType)
    {
        $userId = $this->getUserId();
        $query = "INSERT INTO codes (userId,codeType,code) VALUES(?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('sss',$userId,$codeType,$code);
        return ($stmt->execute()) ? true : false;
    }

    function getCode($codeType)
    {
        $userId = $this->getUserId();
        $query ="SELECT code FROM codes WHERE userId=? AND codeType=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$userId,$codeType);
        $stmt->execute();
        $stmt->bind_result($code);
        $stmt->fetch();
        return $code;
    }

    function setEmailIsVerfied($email)
    {
        $status = 1;
        $query = "UPDATE users SET status=? WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$status,$email);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function getPasswordByEmail($email)
    {
        $query = "SELECT password FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($password);
        $stmt->fetch();
        return $password;
    }

    function getEmailByUsername($username)
    {
        $query = "SELECT email FROM users WHERE username=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$username);
        $stmt->execute();
        $stmt->bind_result($email);
        $stmt->fetch();
        return $email;
    }

    function isEmailVerified($email)
    {
        $query = "SELECT status FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($status);
        $stmt->fetch();
        return $status;
    }

    function isEmailExist($email)
    {
        $query = "SELECT id FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0;
    }

    function isEmailValid($email)
    {
        if(filter_var($email,FILTER_VALIDATE_EMAIL))
            return true;
        else
            return false;
    }

}
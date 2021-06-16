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
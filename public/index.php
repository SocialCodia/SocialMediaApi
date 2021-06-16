<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Firebase\JWT\JWT;

require '../vendor/autoload.php';
require '../include/DbHandler.php';
require '../include/Constants.php';

$app = new \Slim\App([
	'settings'=>[
		'displayErrorDetails' => true,
		'debug' =>true
	]
]);


$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");

    return $response;
});


$app->post('/register',function(Request $request, Response $response)
{
	if(!haveEmptyParameter(array('name','email','password'),$request,$response))
	{
		$db = new DbHandler;
		$requestParameter = $request->getParsedBody();
		$name = $requestParameter['name'];
		$email = $requestParameter['email'];
		$password = $requestParameter['password'];
		if(!$db->isEmailValid($email))
			return returnResponse(true,'Invalid Email Adress',$response);
		if(strlen($name)<4)
			return  returnResponse(true,'Name too Short',$response);
		if(strlen($name)>40)
			return  returnResponse(true,'Name too Long',$response);
		$result = $db->registerUser($name,$email,$password);
		if($result==EMAIL_ALREADY_EXIST)
			return returnResponse(true,EMAIL_ALREADY_EXIST,$response);
		else if($result==FAILED_TO_CREATE_ACCOUNT)
			return returnResponse(true,FAILED_TO_CREATE_ACCOUNT,$response);
		else if($result==USER_CREATED)
		{
			$code = $db->getCode(1);
			if(sentVerificationMail($name,$email,$code))
				return returnResponse(false,"Verification Email Has Been Sent Your Email Address",$response);
			else
				return returnResponse(true,"Account Created But Failed To Sent Verification Email",$response);
		}

	}
});

$app->post('/login',function (Request $request,Response $response)
{
	if(!haveEmptyParameter(array('email','password'),$request,$response))
	{
		$db = new DbHandler;
		$requestParameter = $request->getParsedBody();
		$email = $requestParameter['email'];
		$password = $requestParameter['password'];
		if(!$db->isEmailValid($email))
			return returnResponse(true,'Invalid Email Address',$response);
		if(strlen($password)<8)
			return returnResponse(true,'Password too Short',$response);
		$result = $db->login($email,$password);
		if($result==EMAIL_NOT_VERIFEIED)
			return returnResponse(true,EMAIL_NOT_VERIFEIED,$response);
		if($result==WRONG_PASSWORD)
			return returnResponse(true,WRONG_PASSWORD,$response);
		if($result==EMAIL_NOT_EXIST)
			return returnResponse(true,EMAIL_NOT_EXIST,$response);
		if($result==LOGIN_SUCCESS)
		{
			$user = $db->getUserByEmail($email);
			$user['token'] = getToken($user['id']);
			$resp = array();
			$resp['error'] = false;
			$resp['message'] = LOGIN_SUCCESS;
			$resp['user'] = $user;
			$response->write(json_encode($resp));
			return $response->withHeader('Content-Type','application/json')
					->withStatus(200);
		}
	}
});

function sentVerificationMail($name,$email,$code)
{
	$mailSubject = "Verification Mail For ".WEBSITE_NAME;
	$mailBody = "Click Here To Verify Your Account ".WEBSITE_DOMAIN."/verifyEmail/".$code;
	if(sendMail($name,$email,$mailSubject,$mailBody))
		return true;
	else
		return false;
}

function sendMail($name,$email,$mailSubject,$mailBody)
{
    $websiteEmail = WEBSITE_EMAIL;
    $websiteEmailPassword = WEBSITE_EMAIL_PASSWORD;
    $websiteName = WEBSITE_NAME;
    $websiteOwnerName = WEBSITE_OWNER_NAME;
    $mail = new PHPMailer;
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host=SMTP_HOST;
    $mail->Port=SMTP_PORT;
    $mail->SMPTSecure=SMTP_SECURE;
    $mail->SMTPAuth=true;
    $mail->SMTPOptions = array();
    $mail->Username = $websiteEmail;
    $mail->Password = $websiteEmailPassword;
    $mail->addAddress($email,$name);
    $mail->isHTML();
    $mail->Subject=$mailSubject;
    $mail->Body=$mailBody;
    $mail->From=$websiteEmail;
    $mail->FromName=$websiteName;
    if($mail->send())
    {
        return true;
    }
    return false;
}


function haveEmptyParameter($requiredParameter,$request,$response)
{
	$error = false;
	$errorParameter = '';
	$requestParameter = $request->getParsedBody();
	foreach($requiredParameter as $required)
	{
		if(!isset($requestParameter[$required]) || strlen($requestParameter[$required])<1)
		{
			$error = true;
			$errorParameter .= $required.', ';
		}
	}
	if($error)
	{
		return returnResponse(true,'Required Parameters '.$errorParameter.'is missing',$response);
	}
	return $error;
}


function returnResponse($error,$message,$response)
{
	$resp = array();
	$resp['error'] = $error;
	$resp['message'] = $message;
	$response->write(json_encode($resp));
	return $response->withHeader('Content-Type','application/json')
			->withStatus(200);
}


function getToken($userId)
{
	$payload = array(
		"iss" => "socialcodia.com",
		"iat" => time(),
		"userId" => $userId
	);
	$key = JWT_SECRET_KEY;
	$token = JWT::encode($payload,$key);
	return $token;
}


$app->run();
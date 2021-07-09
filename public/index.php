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

$app->post('/verifyEmail',function(Request $request,Response $response)
{
	if(!haveEmptyParameter(array('email','code'),$request,$response))
	{
		$requestParameter = $request->getParsedBody();
		$email = $requestParameter['email'];
		$code = $requestParameter['code'];
		$decEmail = decrypt($email);
		$db = new DbHandler;
		if(!$db->isEmailValid($decEmail))
			return returnResponse(true,EMAIL_NOT_VALID,$response);

		if(!$db->isEmailExist($decEmail))
			return returnResponse(true,EMAIL_NOT_EXIST,$response);
		$result = $db->verifyEmail($decEmail,$code);
		if($result==INVAILID_CODE)
			return returnResponse(true,INVAILID_CODE,$response);
		if($result==EMAIL_ALREADY_VERIFIED)
			return returnResponse(true,EMAIL_ALREADY_VERIFIED,$response);
		if($result==EMAIL_VERIFICATION_FAILED)
			return returnResponse(true,EMAIL_VERIFICATION_FAILED,$response);
		if($result==EMAIL_VERIFIED)
			return returnResponse(false,EMAIL_VERIFIED,$response);
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
		
		if(strlen($password)<8)
			return returnResponse(true,'Password too Short',$response);

		if(!$db->isEmailValid($email))
		{
			$email = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($email))))));
			$email = str_replace(' ','',$email);
			$email = $db->getEmailByUsername($email);
		}

		if(!empty($email))
		{
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
		else
			return returnResponse(true,'User Not Found',$response);


	}
});

function sentVerificationMail($name,$email,$code)
{
	$websiteDomain = WEBSITE_DOMAIN_CLIENT;
	$endPoint = 'verifyEmail/';
	$websiteName = WEBSITE_NAME;
	$websiteOwnerName = WEBSITE_OWNER_NAME;
	$emailEncrypted = encrypt($email);
	$mailSubject = "Verify Your Email Address For ".WEBSITE_NAME;
	$mailBody= <<<HERE
    <body style="background-color: #f4f4f4; margin: 0 !important; padding: 0 !important;">
    <!-- HIDDEN PREHEADER TEXT -->
    <div style="display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: 'Lato', Helvetica, Arial, sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;"> We're thrilled to have you here! Get ready to dive into your new account. </div>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <!-- LOGO -->
        <tr>
            <td bgcolor="#FFA73B" align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td align="center" valign="top" style="padding: 40px 10px 40px 10px;"> </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#FFA73B" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="center" valign="top" style="padding: 40px 20px 20px 20px; border-radius: 4px 4px 0px 0px; color: #111111; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 48px; font-weight: 400; letter-spacing: 4px; line-height: 48px;">
                            <h1 style="font-size: 48px; font-weight: 400; margin: 2;">Welcome!</h1><img src=" https://img.icons8.com/clouds/100/000000/handshake.png" width="125" height="120" style="display: block; border: 0px;" />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 20px 30px 40px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">We're excited to have you get started. First, you need to confirm your account. Just press the button below.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td bgcolor="#ffffff" align="center" style="padding: 20px 30px 60px 30px;">
                                        <table border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td align="center" style="border-radius: 3px;" bgcolor="#FFA73B"><a href="$websiteDomain$endPoint$emailEncrypted/$code" target="_blank" style="font-size: 20px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; color: #ffffff; text-decoration: none; padding: 15px 25px; border-radius: 2px; border: 1px solid #FFA73B; display: inline-block;">Confirm Account</a></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr> <!-- COPY -->
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 0px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">If that doesn't work, copy and paste the following link in your browser:</p>
                        </td>
                    </tr> <!-- COPY -->
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 20px 30px 20px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;"><a href="#" target="_blank" style="color: #FFA73B;">$websiteDomain$endPoint$emailEncrypted/$code</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 20px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">If you have any questions, just reply to this email—we're always happy to help out.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 40px 30px; border-radius: 0px 0px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">$websiteOwnerName,<br>$websiteName Team</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 30px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#FFECD1" align="center" style="padding: 30px 30px 30px 30px; border-radius: 4px 4px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <h2 style="font-size: 20px; font-weight: 400; color: #111111; margin: 0;">Need more help?</h2>
                            <p style="margin: 0;"><a href="$websiteDomain" target="_blank" style="color: #FFA73B;">We&rsquo;re here to help you out</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    </body>
    HERE;;
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

function encrypt($email)
{
	$encEmail = openssl_encrypt($email,'AES-128-ECB',null);
	$encEmail = str_replace('/','socialcodia',$encEmail);
	$encEmail = str_replace('+','socialmedia',$encEmail);
	return $encEmail;
}

function decrypt($email)
{
	$email = str_replace('socialcodia','/',$email);
	$email = str_replace('socialmedia','+',$email);
	$decEmail = openssl_decrypt($email,'AES-128-ECB',null);
	return $decEmail;
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
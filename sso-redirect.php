<?php

/**
 * sso login script for ilias
 */

require_once("Services/Init/classes/class.ilInitialisation.php");
// set context to APACHE_SSO to avoid redirect to normal login.php
ilContext::init(ilContext::CONTEXT_APACHE_SSO);
ilInitialisation::initILIAS();

/* @global $ilias */
// get the credentials service url of the foreign system
$getCredentialsUrl = $ilias->ini->readVariable("sso", "get_credentials_url");
$webProxyBaseUrl = $ilias->ini->readVariable("webproxy", "base_url");
// get token parameter and reference id from the request
$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : false;
$referenceId = (int) $_REQUEST['ref_id'];

if (($getCredentialsUrl === false) || ($webProxyBaseUrl === false) || ($token === false) || ($referenceId === 0)) {
    exit(1);
}

// exchange token to user credentials from the foreign system
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $getCredentialsUrl . "?token=" . $token);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// grab URL and pass it to the browser
$responseContent = curl_exec($ch);
// close cURL resource, and free up system resources
curl_close($ch);

if ($responseContent === false) {
    // there is an error - redirect the user to
    exit(1);
}
$response = json_decode($responseContent);

// configure credentials
include_once './Services/Authentication/classes/Frontend/class.ilAuthFrontendCredentials.php';
$credentials = new ilAuthFrontendCredentials();
$credentials->setUsername($response->username);
$credentials->setPassword($response->password);
$credentials->setCaptchaCode('');


include_once './Services/Authentication/classes/Provider/class.ilAuthProviderFactory.php';
$provider_factory = new ilAuthProviderFactory();
$providers = $provider_factory->getProviders($credentials);

include_once './Services/Authentication/classes/class.ilAuthStatus.php';
$status = ilAuthStatus::getInstance();

include_once './Services/Authentication/classes/Frontend/class.ilAuthFrontendFactory.php';
$frontend_factory = new ilAuthFrontendFactory();
$frontend_factory->setContext(ilAuthFrontendFactory::CONTEXT_STANDARD_FORM);
$frontend = $frontend_factory->getFrontend(
    $GLOBALS['DIC']['ilAuthSession'],
    $status,
    $credentials,
    $providers
);

// authenticate
$frontend->authenticate();

switch($status->getStatus())
{
    case ilAuthStatus::STATUS_AUTHENTICATED:
        header('Location: ' . rtrim($webProxyBaseUrl, '/') . '/ilias.php?baseClass=ilSAHSPresentationGUI&ref_id=' . $referenceId);
        exit();

    case ilAuthStatus::STATUS_CODE_ACTIVATION_REQUIRED:
        echo "STATUS_CODE_ACTIVATION_REQUIRED";
        break;

    case ilAuthStatus::STATUS_ACCOUNT_MIGRATION_REQUIRED:
        echo "STATUS_ACCOUNT_MIGRATION_REQUIRED";
        break;

    case ilAuthStatus::STATUS_AUTHENTICATION_FAILED:
        ilUtil::sendFailure($status->getTranslatedReason());
        echo "STATUS_AUTHENTICATION_FAILED";
        break;
}

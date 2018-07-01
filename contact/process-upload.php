<?php

// If the request is not AJAX, stop it
//if (!isAjaxRequest()) {
//    echo 'Nice try';
//    exit;
//}

// Load the language file
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$lang_url = (curlEnabled()) ? $protocol.$_SERVER['HTTP_HOST'].str_replace(basename($_SERVER['PHP_SELF']), $configs['lang_path'], $_SERVER['REQUEST_URI']) : $configs['lang_path'];
$lang = (curlEnabled()) ? curl_file_get_contents($lang_url) : file_get_contents($lang_url);
$lang = json_decode($lang);

// Check if data has been sent
if (empty($_POST) && empty($_FILES)) {
    echo json_encode(array('errors' => array($lang->file->server_limit)));
    exit;
}

// Set up PHP_EOL
if (!defined('PHP_EOL')) {
    define('PHP_EOL', "\r\n");
}

// Create the upload directory if it doesn't exist
$dir = $configs['upload_dir'];
if (!file_exists($dir) && !is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Grab form data
$fdata = $_POST;

/* Start processing data */

// Validate form
$errors = getValidationErrors();
if (!empty($errors)) {
    echo json_encode(array('errors' => $errors));
    // Remove uploaded files
    removeUploadsFromServer();
    exit;
}

// Send emails
if ($configs['email_to_owner']) {
    sendEmailWithInformations();
} else {
    emailSuccessfullySent();
}

?>

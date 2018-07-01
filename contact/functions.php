<?php
// Init the PHP mail instance
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

/* Functions */

/* Validate Form Data */
function getValidationErrors()
{
    global $lang, $fdata, $configs, $fields;

    // List of error messages to keep
    $errors = array();

    // Loop through each of our form fields
    foreach ($fields as $field) {
        $fieldName = $field['name'];
        $value = isset($fdata[$fieldName]) ? $fdata[$fieldName] : null;

        // Check if required
        if ($field['required'] && (($field['type'] != 'file' && empty($value)) ||
                                  ($field['type'] == 'file' && (!isset($_FILES[$fieldName]) || empty($_FILES[$fieldName]['name'][0]))))) {
            array_push($errors, paramsIntoString($lang->required_field, array($field['display_name'])));
        } elseif ($field['type'] != 'file' && empty($value)) {
            continue;
        }

        // Fix url
        if ($field['type'] == 'url' && !empty($value)) {
            $prot = substr($value, 0, 7);
            $protS = substr($value, 0, 8);
            if ($prot != 'http://' && $protS != 'https://') {
                $value = 'http://'.$value;
            }
        }

        // Now checks based on field type
        switch ($field['type']) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    array_push($errors, paramsIntoString($lang->email_invalid, array($field['display_name'])));
                }
                break;
            case 'number':
                if (!is_numeric($value)) {
                    array_push($errors, paramsIntoString($lang->number_invalid, array($field['display_name'])));
                }
                break;
            case 'tel':
                if (!is_numeric($value)) {
                    array_push($errors, paramsIntoString($lang->number_invalid, array($field['display_name'])));
                }
                break;
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    array_push($errors, paramsIntoString($lang->url_invalid, array($field['display_name'])));
                }
                break;
            case 'file':
                // Loop through any files and attempt to upload them
                // Report any errors when files are uploaded
                if (isset($_FILES[$fieldName]) && !empty($_FILES[$fieldName]['name'][0])) {
                    $upload_errs = filesUploaded($_FILES[$fieldName], $field);
                    if (!empty($upload_errs)) {
                        for ($j = 0; $j < count($upload_errs); $j++) {
                            array_push($errors, $upload_errs[$j]);
                        }
                    }
                }
                break;
        }
    }

    // Check if reCAPTCHA has been used
    if ($configs['recaptcha_enabled']) {
        $captcha = $_POST['g-recaptcha-response'];

        // If there's no value, tell the user it's not set
        if (!$captcha) {
            array_push($errors, $lang->recaptcha->empty);
        } else {
            // Verify the reCAPTCHA code
            $recaptcha = "https://www.google.com/recaptcha/api/siteverify?secret={$configs['recaptcha_secret']}&response={$captcha}&remoteip={$_SERVER['REMOTE_ADDR']}";

            // Get recaptcha
            $response = (curlEnabled()) ? curl_file_get_contents($recaptcha) : file_get_contents($recaptcha);
            $response = json_decode($response);

            // If the response comes back negative, it's a bot, error out
            if (!empty($response)) {
                if (!$response->success) {
                    removeUploadsFromServer();
                    array_push($errors, paramsIntoString($lang->recaptcha->bot, $response->{'error-codes'}));
                }
            } else {
                removeUploadsFromServer();
                array_push($errors, paramsIntoString($lang->recaptcha->no_response, array($recaptcha)));
            }
        }
    }

    // Return the errors
    return $errors;
}

/* Replace params into string */
function paramsIntoString($string, $params)
{
    for ($i = 0; $i < count($params); $i++) {
        $string = str_replace('{{'.($i + 1).'}}', $params[$i], $string);
    }

    return $string;
}

/* Upload files */
function filesUploaded($files, $field)
{
    global $lang, $fdata, $configs;

    // Store any errors in here
    $errors = array();

    // Store the total size of files
    $sizes = 0;

    // Loop through files
    for ($i = 0; $i < count($files['name']); $i++) {
        // Check the file errors
        if ($files['error'][$i] !== 0) {
            array_push($errors, paramsIntoString($lang->file->generic, array($files['name'][$i], $files['error'][$i])));
            continue;
        }

        // Check if the file type is in our "allowed" list
        $nameExploded = explode(".", $files['name'][$i]);
        $extension = strtolower(end($nameExploded));
        // If "allowed_types" array is setted for this input, use it. Otherwise use the default one: $configs['upload_types']
        $arrAllowedTypes = $configs['upload_types'];
        if (isset($field['allowed_types']) && count($field['allowed_types']) > 0) {
            $arrAllowedTypes = $field['allowed_types'];
        }
        if (!in_array($extension, $arrAllowedTypes)) {
            array_push($errors, paramsIntoString($lang->file->type, array($files['name'][$i])));
            continue;
        }

        // If the file doesn't exist, store it
        $target_path = $configs['upload_dir'].str_replace("/", "", $files['name'][$i]);
        if (!file_exists($target_path)) {
            // If it fails to save, error out
            if (!move_uploaded_file($files['tmp_name'][$i], $target_path)) {
                array_push($errors, paramsIntoString($lang->file->error, array($files['name'][$i])));
                continue;
            }
        } else {
            $exploded = explode('.', str_replace("/", "", $files['name'][$i])); // break up file name from extension
            $filetitle = array_slice($exploded, 0, -1); // remove extension from exploded and assign to filetitle
            $filetitle = implode('', $filetitle); // keep just the filename
            $newfilename = $filetitle.'-'.round(microtime(true)).'.'.end($exploded); // add a timestamp
            $target_path = $configs['upload_dir'].$newfilename; // set the new file path and name for storage
            $files['name'][$i] = $newfilename; // update file for the email to send the correct one
            // if it fails to save, error out
            if (!move_uploaded_file($files['tmp_name'][$i], $target_path)) {
                array_push($errors, paramsIntoString($lang->file->error, array($files['name'][$i])));
                continue;
            }
        }

        // Up the "sizes" variable so we can check total filesize for files
        $sizes += $files['size'][$i];
    }

    // Check final files size is less than our limit option
    $mb = 1048576; // bytes in a megabyte
    if ($sizes / $mb > $configs['upload_limit']) {
        array_push($errors, paramsIntoString($lang->file->size, array($configs['upload_limit'])));
    }

    // If there are errors, we'll need to remove ALL files
    if (!empty($errors)) {
        removeUploadsFromServer();
    }

    return $errors;
}

/* Remove files uploaded to the server */
function removeUploadsFromServer()
{
    global $configs;

    // Loop through files
    foreach ($_FILES as $files) {
        if (!empty($files['name'][0])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = $configs['upload_dir'].str_replace("/", "", $files['name'][$i]);

                // If exists, delete it
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}

/* Check if the current request is in ajax */
function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/* Check if server supports cURL */
function curlEnabled()
{
    global $protocol, $configs;

    if ($configs['curl_enabled'] && function_exists('curl_version')) {
        $content = curl_file_get_contents($protocol.$_SERVER['HTTP_HOST']);
        $enabled = ($content) ? true : false;

        return $enabled;
    }

    return false;
}

/* Alternative function to file_get_contents() */
function curl_file_get_contents($url)
{
    $curl = curl_init();
    $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';

    curl_setopt($curl, CURLOPT_URL, $url);               //The URL to fetch. This can also be set when initializing a session with curl_init().
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);    //TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly.
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);       //The number of seconds to wait while trying to connect.

    curl_setopt($curl, CURLOPT_USERAGENT, $userAgent); //The contents of the "User-Agent: " header to be used in a HTTP request.
    curl_setopt($curl, CURLOPT_FAILONERROR, true);     //To fail silently if the HTTP code returned is greater than or equal to 400.
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);  //To follow any "Location: " header that the server sends as part of the HTTP header.
    curl_setopt($curl, CURLOPT_AUTOREFERER, true);     //To automatically set the Referer: field in requests where it follows a Location: redirect.
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);           //The maximum number of seconds to allow cURL functions to execute.

    $contents = curl_exec($curl);
    curl_close($curl);

    return $contents;
}

/* Get the field name of the user email to use as "Reply to" for sending the informations  */
function getUserEmailFieldName()
{
    global $fields;

    foreach ($fields as $field) {
        if (isset($field['is_user_email']) && $field['is_user_email'] == true) {
            return $field['name'];
        }
    }

    return null;
}

/* Get the field name of the user email to use as "To" for sending the informations  */
function getCustomEmailToFieldName()
{
    global $fields;

    foreach ($fields as $field) {
        if (isset($field['email_to_this']) && $field['email_to_this'] == true) {
            return $field['name'];
        }
    }

    return null;
}

/* Get the field name of the user email to use as "From" for sending the informations */
function getCustomEmailFromFieldName()
{
    global $fields;

    foreach ($fields as $field) {
        if (isset($field['email_from_this']) && $field['email_from_this'] == true) {
            return $field['name'];
        }
    }

    return null;
}

/* Send email to Owner */
function sendEmailWithInformations()
{
    global $configs, $fdata, $lang;

    $mail = new PHPMailer();

    // If we're using SMTP
    if ($configs['smtp_enabled']) {
        $mail->isSMTP();
        $mail->Host = $configs['smtp_host'];
        $mail->SMTPAuth = $configs['smtp_auth'];
        $mail->Username = $configs['smtp_username'];
        $mail->Password = $configs['smtp_password'];
        $mail->SMTPSecure = $configs['smtp_secure'];
        $mail->Port = $configs['smtp_port'];
    }

    // Character encoding
    $mail->CharSet = 'UTF-8';

    // Mail format
    $mail->isHTML(true);

    // From
    $customEmailFromFieldName = getCustomEmailFromFieldName();
    if ($customEmailFromFieldName != null) {
        $mail->From = $fdata[$customEmailFromFieldName];
        $mail->FromName = '';
    } else {
        $mail->From = $configs['from_address'];
        $mail->FromName = $configs['from_name'];
    }

    // To
    $customEmailToFieldName = getCustomEmailToFieldName();
    if ($customEmailToFieldName != null) {
        $mail->addAddress($fdata[$customEmailToFieldName]);
    } else {
        foreach ($configs['to_addresses'] as $address) {
            $mail->addAddress($address);
        }
    }

    // Cc
    if (isset($configs['cc_addresses'])) {
        foreach ($configs['cc_addresses'] as $address) {
            $mail->addCC($address);
        }
    }

    // Bcc
    if (isset($configs['bcc_addresses'])) {
        foreach ($configs['bcc_addresses'] as $address) {
            $mail->addBCC($address);
        }
    }

    // Reply to
    if ($customEmailToFieldName == null && $customEmailFromFieldName == null) {
        $userEmailFieldName = getUserEmailFieldName();
        if ($userEmailFieldName != null) {
            $mail->addReplyTo($fdata[$userEmailFieldName]);
        }
    }

    // Add all files to the email
    foreach ($_FILES as $files) {
        if (!empty($files['name'][0])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                // Define file path
                $path = __DIR__.'/'.$configs['upload_dir'].str_replace("/", "", $files['name'][$i]);

                // Add the files to the mail
                $mail->addAttachment($path, str_replace("/", "", $files['name'][$i]));
            }
        }
    }

    // Set subject
    $mail->Subject = $lang->emails_info->subject;

    // Set content
    $htmlContent = $lang->emails_info->body.getHtmlEmailDataContent(true);
    $mail->msgHTML($htmlContent);
    $mail->Body = $htmlContent;

    // Successfully sent email
    if ($mail->send()) {
        emailSuccessfullySent();
    } else {
        // Error sending email
        echo json_encode(array('errors' => array(paramsIntoString($lang->emails_info->error, array($mail->ErrorInfo)))));

        // Remove uploaded files from the server
        removeUploadsFromServer();
    }
}

/* Build email content */
function getHtmlEmailDataContent($forOwner)
{
    global $fdata, $configs, $fields, $lang;

    $content = "";

    // Loop through fields
    foreach ($fields as $field) {
        $fieldName = $field['name'];

        if ($field['type'] == 'file') {
            continue;
        }

        $value = isset($fdata[$fieldName]) ? $fdata[$fieldName] : null;

        switch ($field['type']) {
            case 'textarea':
                $value = nl2br($value);
                break;
        }

        $content .= "<strong>".$field['display_name'].":</strong> ".$value."<br />";
    }

    if($configs['user_ip_address'] && $forOwner) {
        $content .= "<br />".paramsIntoString($lang->emails_info->body_ip_address, array(getClientIp()));
    }

    $content .= "<br /><br />";

    return $content;
}

/* Email successfully sent, do some stuff */
function emailSuccessfullySent()
{
    global $lang, $configs, $fdata;

    // If we're not keeping the files, remove them from the server
    if (!$configs['upload_keep_files']) {
        removeUploadsFromServer();
    }

    if ($configs['email_confirm']) {
        sendConfirmationEmailToUser();
    }

    // Redirect to custom "success" page if it's been set
    if (!empty($configs['success_link'])) {
        echo json_encode(array('redirectUrl' => array($configs['success_link'])));
        exit;
    }

    // If no redirect has been set, echo out the success message
    echo json_encode(array('success' => array($lang->success_text)));
}

/* Send confirmation email to user */
function sendConfirmationEmailToUser()
{
    global $configs, $fdata, $lang;

    $mail = new PHPMailer();

    // If we're using SMTP
    if ($configs['smtp_enabled']) {
        $mail->isSMTP();
        $mail->Host = $configs['smtp_host'];
        $mail->SMTPAuth = $configs['smtp_auth'];
        $mail->Username = $configs['smtp_username'];
        $mail->Password = $configs['smtp_password'];
        $mail->SMTPSecure = $configs['smtp_secure'];
        $mail->Port = $configs['smtp_port'];
    }

    // Character encoding
    $mail->CharSet = 'UTF-8';

    // Mail format
    $mail->isHTML(true);

    // From
    $mail->From = $configs['from_address'];
    $mail->FromName = $configs['from_name'];

    // To
    $customEmailFromFieldName = getCustomEmailFromFieldName();
    if ($customEmailFromFieldName != null) {
        $mail->addAddress($fdata[$customEmailFromFieldName]);
    } else {
        $userEmailFieldName = getUserEmailFieldName();
        if ($userEmailFieldName != null) {
            $mail->addAddress($fdata[$userEmailFieldName]);
        } else {
            return;
        }
    }

    // Set subject
    $mail->Subject = $lang->emails_info->confirmation->subject;

    // Set content
    $htmlContent = $lang->emails_info->confirmation->body;

    if($configs['email_confirm_html_copy']) {
        $htmlContent .= $lang->emails_info->confirmation->body_html_copy.getHtmlEmailDataContent(false);
    }

    $mail->msgHTML($htmlContent);
    $mail->Body = $htmlContent;

    // Send email
    $mail->send();
}

/* Function to get the client IP address */
function getClientIp()
{
    $ipaddress = '';
    if(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

?>
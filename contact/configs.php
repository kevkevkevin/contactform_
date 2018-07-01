<?php

error_reporting(E_ALL);

$configs['from_name']				= 'Block Studio';								
$configs['from_address']			= 'info@block-s.com';							
$configs['to_addresses']			= array('info@block-s.com');	



$configs['lang_path']				= 'lang/en.json';								
$configs['recaptcha_enabled']		= false;										
$configs['recaptcha_secret']		= 'YOUR_SECRET_HERE';							
$configs['success_link']			= '';											
$configs['curl_enabled']			= true;	




// Generic email configs
$configs['email_to_owner']			= true;										
$configs['email_confirm']			= true;											
$configs['email_confirm_html_copy']	= false;										
$configs['cc_addresses']			= array();										
$configs['bcc_addresses']			= array();										
$configs['user_ip_address']			= false;	




// SMTP configs
$configs['smtp_enabled']			= true;										
$configs['smtp_host']				= 'smtp1.servage.net';											
$configs['smtp_auth']				= true;											
$configs['smtp_username']			= 'info@block-s.com';											
$configs['smtp_password']			= 'Blockx2018x';											
$configs['smtp_secure']				= 'tls';										
$configs['smtp_port']				= 25;										
$configs['upload_dir']				= 'uploads/';									
$configs['upload_limit']			= 10;											
$configs['upload_keep_files']		= false;										
$configs['upload_types']			= array('jpeg',	'jpg', 'png', 'gif',			
										'doc', 'docx', 'xls', 'xlsx',
										'pdf', 'txt', 'rtf', 'zip', 'rar',
										'mp3','mp4','mov','wav'
									   );


?>
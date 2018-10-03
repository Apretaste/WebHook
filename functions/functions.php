<?php

$_SERVER['ROOT_DIRECTORY'] = "/root/to/webhook/directory/";

require_once($_SERVER['ROOT_DIRECTORY'] . 'functions/config/config.php');

require_once($_SERVER['ROOT_DIRECTORY'] . 'functions/mailpop.php');


function PrintCurrentDateTime(){
$date = new DateTime();
$date = $date->format("y:m:d h:i:s");
echo $date."\n";
}



function GetAllNewMessagesAndCallFunction($mailServer,$mailPort,$ssl,$mailUsername,$mailPassword,$mailBox,$WebHookurl,$extraParameters,$method,$deleteAll=false){
	//PrintCurrentDateTime();
	echo "start imap login\n";
	$connection = pop3_login($mailServer,$mailPort,$mailUsername,$mailPassword,$mailBox,$ssl); 
	//PrintCurrentDateTime();
	echo "imap loguin ended\n";
	$server = "{". $mailServer. ":" . $mailPort . "}";
	//PrintCurrentDateTime();
	echo "start imap folder List\n";
	$FoldersList = pop3_folders_list($connection, $server);
	$TrashMailBox = "";
	$trashName = "trash";
	//PrintCurrentDateTime();

	for($i=0;$i<count($FoldersList);$i++){
		$tmpMailBox = explode($server,$FoldersList[$i]);
		$tmpMailBox = $tmpMailBox[1];
		if(strpos(strtoupper($tmpMailBox),strtoupper($trashName)) !== false){
			$TrashMailBox = $tmpMailBox;
			$i = count($FoldersList);
		}
	}

	$breakFile = $_SERVER['breakFile'];
	if( !file_exists($breakFile) ){
		touch($breakFile);
		file_put_contents($breakFile, "0");
	}
	$breakFileValue = file_get_contents($breakFile);
	while($breakFileValue != "1"){
		$breakFileValue = file_get_contents($breakFile);
		usleep(2000);
		$status = pop3_stat($connection);
		PrintCurrentDateTime();
		echo("New Messages: " . $status["Unread"] . "\n");
		imap_check($connection);
		$MessageListSorted = GetNewMessages($connection);
		$messagesToBeDeleted = [];
		if( $status["Unread"] > 0){
			for($i=0;$i<$MessageListSorted["count"]; $i++){
				$mail = imap_headerinfo ( $connection , $MessageListSorted["sorted"][$i] );
				$mailheaders = $mail;
				$headers = imap_fetchheader($connection , $MessageListSorted["sorted"][$i]);
				preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)\r\n/m', $headers, $matches);
				$mailheaders->toadress = $matches[2][10];

				$sender = $mail->sender[0]->mailbox . "@" . $mail->sender[0]->host;
				var_dump("sender: " . $sender);
				$from = $mail->from[0]->mailbox . "@" . $mail->from[0]->host;
				var_dump("from: " . $from);
				$reply_to = $mail->reply_to[0]->mailbox . "@" . $mail->reply_to[0]->host;
				var_dump("reply_to: " . $reply_to);
				$to = $mailheaders->toadress;
				var_dump("to: " . $to);
				$subject = $mail->subject;
				var_dump("subject: " . $subject);
				$message_number = $MessageListSorted["sorted"][$i];
				$structure = imap_fetchstructure($connection, $message_number);

				$mailcoded = imap_fetchbody($connection, $message_number, "");
				$mailDecoded = imap_qprint(imap_fetchbody($connection, $message_number, 1.2));
				if (empty($mailDecoded)) $mailDecoded = imap_qprint(imap_fetchbody($connection, $message_number, 1));

				$attachments = array();
				if(isset($structure->parts) && count($structure->parts)) {
					for($k = 0; $k < count($structure->parts); $k++) {
						$attachments[$k] = array(
							'is_attachment' => false,
							'filename' => '',
							'name' => '',
							'attachment' => ''
						);
						
						if($structure->parts[$k]->ifdparameters) {
							foreach($structure->parts[$k]->dparameters as $object) {
								if(strtolower($object->attribute) == 'filename') {
									$attachments[$k]['is_attachment'] = true;
									$attachments[$k]['filename'] = $object->value;
								}
							}
						}
						
						if($structure->parts[$k]->ifparameters) {
							foreach($structure->parts[$k]->parameters as $object) {
								if(strtolower($object->attribute) == 'name') {
									$attachments[$k]['is_attachment'] = true;
									$attachments[$k]['name'] = $object->value;
								}
							}
						}
						
						if($attachments[$k]['is_attachment']) {
							$attachments[$k]['attachment'] = imap_fetchbody($connection, $message_number, $k+1);
							if($structure->parts[$k]->encoding == 3) { // 3 = BASE64
								$attachments[$k]['attachment'] = $attachments[$k]['attachment'];
							}
							elseif($structure->parts[$k]->encoding == 4) { // 4 = QUOTED-PRINTABLE
								$attachments[$k]['attachment'] = quoted_printable_decode($attachments[$k]['attachment']);
							}
						}
					}
				}		
				// new code	

				$postValues = [];
				if(is_array($extraParameters)){
					$postValues = $extraParameters;
				}
				$postValues['header'] = $mailheaders;
				$postValues['mailraw'] = $mailcoded;
				$postValues['mailformatted'] = $mailDecoded;
				$postValues['attachments'] = $attachments;
				if($method == "POST"){
					PrintCurrentDateTime();
					echo "sending Message By POST\n";
					$res = postTransaction(json_encode($postValues), $WebHookurl);
					PrintCurrentDateTime();
					//var_dump($res);
					//PrintCurrentDateTime();
				}
				else{
					if($method == "GET"){
						echo "sending Message By GET\n";
					//	getTransaction($postValues, $WebHookurl);
					}
				}
				if($deleteAll){
					imap_delete ($connection ,  $MessageListSorted["sorted"][$i]);
				}					
			}
			if($deleteAll){
				imap_expunge ( $connection );
			}
		}
	}
	//PrintCurrentDateTime();
	file_put_contents($breakFile, "0");
	pop3_close($connection);
}

function postTransaction($parameters, $url){
	$request = curl_init();
	curl_setopt($request, CURLOPT_URL, $url );
	curl_setopt($request, CURLOPT_POST, 1);
	curl_setopt($request, CURLOPT_POSTFIELDS, $parameters);


   	curl_setopt($request, CURLOPT_TIMEOUT_MS, 300);
    	curl_setopt($request, CURLOPT_HEADER, 0);
    	curl_setopt($request,  CURLOPT_RETURNTRANSFER, false);
    	curl_setopt($request, CURLOPT_FORBID_REUSE, true);
    	curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 1);
    	curl_setopt($request, CURLOPT_DNS_CACHE_TIMEOUT, 10); 

    	curl_setopt($request, CURLOPT_FRESH_CONNECT, true);



	$response = curl_exec($request);

	$information = curl_getinfo($request);
	$result = array();
	$message = "";
	$error = false;

	if(  $information["content_type"] == NULL &&( $information["http_code"] == 0 )){
		$result["InternetFailture"] = true;
		$error = true;
		$message = "Internet Connection Problem Server could not be reached";
	}

	if($error == true){
		$result["Result"] = "ERROR";
		$result["Description"] = $message;  	
	}
	else{
		if (FALSE === $response){
			$result["Result"] = "ERROR";
			$result["Description"] = curl_error($request);  
		}else{
			curl_close($request);		
			$result["Result"] = "OK";
			$result["Data"] = $response;
		}
	} 
	return $result;
}

function getTransaction($parameters, $url){
		$result = array();
		$result["Result"] = "ERROR";
		$result["Description"] = ""; 
		$url_asp_final = $url . '?';
		$sp = '';
		foreach ($parameters as $key => $value) {
		    $url_asp_final .= ($sp . $key . '=' . urlencode($value));
		    $sp = '&';
		}
		$response = false;
		$result = array();
		
		$response = file_get_contents($url_asp_final);
		
		$result["Result"] = "OK";
		$result['Data']=$response;
		return $result;
}


function InstanceMonitorStartNewBreakCurrent($InstanceRestartTimeInMinutes=5){
		
    $mailServer = $_SERVER['IMAP_SERVER'];                         // imap server
    $mailPort = $_SERVER['IMAP_PORT'];                             // imap server port
    $ssl = $_SERVER['IMAP_SSL'];                                   // imap SSL enable
    $mailUsername = $_SERVER['IMAP_USER'];                         // imap username
    $mailPassword = $_SERVER['IMAP_PASSW'];                        // imap password
    $mailBox = $_SERVER['MAIL_BOX'];                               // imap folder (by default INBOX)
    $extraParameters = $_SERVER['WEB_HOOK_EXTRA_PARAMS'];          // webhook extra parameters
    $method = $_SERVER['WEB_HOOK_METHOD'];                         // webhook method (POST or GET)
    $WebHookurl = $_SERVER['WEB_HOOK_URL'];                        // WEBHOOK URL
    $DeleteMessages = $_SERVER['DELETE_AFTER_READ'];               // delete messages after process
    PrintCurrentDateTime();


	$breakFile = $_SERVER['breakFile'];
	$LockFile = $_SERVER['instanceLock'];
	if( !file_exists($LockFile) ){
		touch($LockFile);	
	}
	$fp = fopen( $LockFile , 'r+');    // permit only one instance
	if( !flock($fp, LOCK_EX | LOCK_NB)){
		echo("Instance is Running For ".$InstanceRestartTimeInMinutes." Minutes.\n"); // log instance time running
		PrintCurrentDateTime();
		die("Another Instance is Running\n");
		exit;
	}

	GetAllNewMessagesAndCallFunction($mailServer,$mailPort,$ssl,$mailUsername,$mailPassword,$mailBox,$WebHookurl,$extraParameters,$method,$DeleteMessages);

	flock($fp, LOCK_UN);
	fclose($fp);

}


?>

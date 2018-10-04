<?php

$_SERVER['ROOT_DIRECTORY'] = "/home/apretaste/MailListenerMultiThreads/";

require_once($_SERVER['ROOT_DIRECTORY'] . 'functions/config/config.php');

require_once($_SERVER['ROOT_DIRECTORY'] . 'functions/mailpop.php');


function PrintCurrentDateTime(){
$date = new DateTime();
$date = $date->format("y:m:d h:i:s");
echo $date."\n";
}



function GetAllNewMessagesAndCallFunction($mailServer,$mailPort,$ssl,$mailUsername,$mailPassword,$mailBox,$WebHookurl,$extraParameters,$method,$deleteAll=false,$breakFile){
	//PrintCurrentDateTime();
	echo "start imap login\n";
	$connection = pop3_login($mailServer,$mailPort,$mailUsername,$mailPassword,$mailBox,$ssl); 
	//PrintCurrentDateTime();
	echo "imap loguin ended\n";
	if(!$connection) die("Fail To Loguin");
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

	if( !file_exists($breakFile) ){
		touch($breakFile);
		file_put_contents($breakFile, "0");
	}
	//PrintCurrentDateTime();
	$breakFileValue = file_get_contents($breakFile);
	while($breakFileValue != "1"){
		//PrintCurrentDateTime();
		$breakFileValue = file_get_contents($breakFile);
		usleep(200000);
		//PrintCurrentDateTime();
		$status = pop3_stat($connection);
		echo("New Messages: " . $status["Unread"] . "\n");
		// $messageList = pop3_list($connection); //Not used
		imap_check($connection);
		//PrintCurrentDateTime();
		$MessageListSorted = GetNewMessages($connection);
		$messagesToBeDeleted = [];
		if( $status["Unread"] > 0){
			echo "<pre>\n";
			//PrintCurrentDateTime();
			for($i=0;$i<$status["Unread"]; $i++){
				$mail = imap_headerinfo ( $connection , $MessageListSorted["sorted"][$i] );
				$mailheaders = $mail;
				//var_dump($mail);
				$headers = imap_fetchheader($connection , $MessageListSorted["sorted"][$i]);
				preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)\r\n/m', $headers, $matches);
				//var_dump($matches);
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

				// new code
				$mailcoded = imap_fetchbody($connection, $message_number, "");
				//var_dump($mailcoded);
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
				//var_dump($mailheaders["message_id"]);
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
	//PrintCurrentDateTime();
}

function postTransaction($parameters, $url){
	//PrintCurrentDateTime();
	$request = curl_init();
	//var_dump($parameters);
	//var_dump($url);
	curl_setopt($request, CURLOPT_URL, $url );
	//curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($request, CURLOPT_POST, 1);
	curl_setopt($request, CURLOPT_POSTFIELDS, $parameters);


   	curl_setopt($request, CURLOPT_TIMEOUT_MS, 300);
	//curl_setopt($request, CURLOPT_EXPECT_100_TIMEOUT_MS, 1); 
    	curl_setopt($request, CURLOPT_HEADER, 0);
    	curl_setopt($request,  CURLOPT_RETURNTRANSFER, false);
    	curl_setopt($request, CURLOPT_FORBID_REUSE, true);
    	curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 1);
    	curl_setopt($request, CURLOPT_DNS_CACHE_TIMEOUT, 10); 

    	curl_setopt($request, CURLOPT_FRESH_CONNECT, true);



	$response = curl_exec($request);

	$information = curl_getinfo($request);
	//PrintCurrentDateTime();
	//var_dump($information);
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


function InstanceMonitorStartNewBreakCurrent($configNumber){
	
	PrintCurrentDateTime();
		$SERVER = $GLOBALS["InstancesConfigs"][$configNumber];

	if(is_array($SERVER)){
	        $mailServer = $SERVER['IMAP_SERVER'];                          // server imap para recibir los correos
        	$mailPort = $SERVER['IMAP_PORT'];                                              // puerto imap del server 
	        $ssl = $SERVER['IMAP_SSL'];                                                    // true o false utilizar SSL 
        	$mailUsername = $SERVER['IMAP_USER'];                                  // usuario para auth en el server imap
	        $mailPassword = $SERVER['IMAP_PASSW'];                                 // password para auth en el server imap
        	$mailBox = $SERVER['MAIL_BOX'];                            // Carpeta para recibir los correos (Por defecto INBOX)
        	$extraParameters = $SERVER['WEB_HOOK_EXTRA_PARAMS'];   //parametros extras para utilizar la URL del webhook
        	$method = $SERVER['WEB_HOOK_METHOD'];                                  // Metodo de la URL del WEB HOOK POST o GET
        	$WebHookurl = $SERVER['WEB_HOOK_URL'];                                 // URL del WEB HOOK que recibe los correos
        	$DeleteMessages = $SERVER['DELETE_AFTER_READ'];                // Mover los correos al trash despues de procesarlos
		$breakFile = $SERVER['breakFile'];
		$LockFile = $SERVER['instanceLock'];
	                if( !file_exists($LockFile) ){
                        touch($LockFile);
                }
                if( !file_exists($breakFile) ){
			touch($breakFile);
			file_put_contents($breakFile, "0");
                }
		$fp = fopen( $LockFile , 'r+');    // permit only one instance
                if( !flock($fp, LOCK_EX | LOCK_NB)){
                        echo("Instance is Running For ".$InstanceRestartTimeInMinutes." Minutes.\n");
                        PrintCurrentDateTime();
                        die("Another Instance is Running\n");
                        exit;
                }
                else{
                        GetAllNewMessagesAndCallFunction($mailServer,$mailPort,$ssl,$mailUsername,$mailPassword,$mailBox,$WebHookurl,$extraParameters,$method,$DeleteMessages,$breakFile);
                        flock($fp, LOCK_UN);
                        fclose($fp);
                }
	
	
	
	}
		
}


?>

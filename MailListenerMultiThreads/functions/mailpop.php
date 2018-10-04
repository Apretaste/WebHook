<?php

function pop3_login($host,$port,$user,$pass,$folder="INBOX",$ssl=false) 
{ 
    $sslString="";

    if($ssl){
        $sslString="ssl/";        
    }


    $Server = "{"."$host:$port/imap/".$sslString."novalidate-cert"."}";
    $MailBox = $Server . $folder;
    //var_dump($EndLine);
    echo "imap open starting\n";
    $connection = (imap_open($MailBox,$user,$pass)); 
    echo "imap open end\n";
    //'{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
	$tryCnt = 0;
	while(!is_resource($connection)){
        echo "imap open not resource\n";
	    $connection = imap_open($EndLine);
	    $tryCnt ++;
	    if(!is_resource($connection)){
	        $connection = imap_open($EndLine);
	        $tryCnt ++;
	    }
	    if($tryCnt > 20){
	        echo "Cannot Connect To Mail Server:<BR>";
	        die(var_dump(imap_errors()));
	    }
	}
	imap_sort($connection, SORTARRIVAL, 1);
	return $connection;
}

function pop3_folders_list($connection, $server){
	$mailboxes = imap_list($connection, $server, '*');
	return $mailboxes;
}

function pop3_move_message_to_Trash($connection,$messageID,$TrashFolder){
	return imap_mail_move($connection, $messageID, $TrashFolder, CL_EXPUNGE);
	//imap_mail_move($stream,  4 , '[Gmail]/Lixeira');
}

function pop3_stat($connection)        
{ 
    $check = imap_mailboxmsginfo($connection); 
    return ((array)$check); 
}

function pop3_list($connection,$message="") 
{ 
    if ($message) 
    { 
        $range=$message; 
    } else { 
        $MC = imap_check($connection); 
        $range = "1:".$MC->Nmsgs; 
    } 
    $response = imap_fetch_overview($connection,$range); 
    foreach ($response as $msg) $result[$msg->msgno]=(array)$msg; 
	if(isset($result))
    	return $result; 
    else
    	return false;
} 

function pop3_retr($connection,$message) 
{ 
    return(imap_fetchheader($connection,$message,FT_PREFETCHTEXT)); 
}

function pop3_delete($connection,$message) 
{ 
    return(imap_delete($connection,$message)); 
} 

function pop3_purge($connection) 
{ 
    return(imap_expunge($connection)); 
} 

function pop3_close($connection){
	return imap_close($connection, CL_EXPUNGE);
}

function mail_parse_headers($headers) 
{ 
    $headers=preg_replace('/\r\n\s+/m', '',$headers); 
    preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)?\r\n/m', $headers, $matches); 
    foreach ($matches[1] as $key =>$value) $result[$value]=$matches[2][$key]; 
	if(isset($result))
    	return $result; 
    else
    	return false;
} 

function mail_mime_to_array($imap,$mid,$parse_headers=false) 
{ 
    $mail = imap_fetchstructure($imap,$mid); 
    $mail = mail_get_parts($imap,$mid,$mail,0); 
    if ($parse_headers) $mail[0]["parsed"]=mail_parse_headers($mail[0]["data"]); 
    return($mail); 
} 

function mail_get_parts($imap,$mid,$part,$prefix) 
{    
    $attachments=array(); 
    $attachments[$prefix]=mail_decode_part($imap,$mid,$part,$prefix); 
    if (isset($part->parts)) // multipart 
    { 
        $prefix = ($prefix == "0")?"":"$prefix."; 
        foreach ($part->parts as $number=>$subpart) 
            $attachments=array_merge($attachments, mail_get_parts($imap,$mid,$subpart,$prefix.($number+1))); 
    } 
    return $attachments; 
} 

function mail_decode_part($connection,$message_number,$part,$prefix) 
{ 
    $attachment = array(); 

    $ifdparameters = false;
    	if(isset($part->ifdparametersar)){
    		$ifdparameters = $part->ifdparametersar;
    	}
    

    if($ifdparameters) { 
        foreach($part->dparameters as $object) { 
            $attachment[strtolower($object->attribute)]=$object->value; 
            if(strtolower($object->attribute) == 'filename') { 
                $attachment['is_attachment'] = true; 
                $attachment['filename'] = $object->value; 
            } 
        } 
    }

    $ifparameters = false;
	if(isset($part->ifparameters)){
		$ifparameters = $part->ifparameters;
	}
    

    if($ifparameters) { 
        foreach($part->parameters as $object) { 
            $attachment[strtolower($object->attribute)]=$object->value; 
            if(strtolower($object->attribute) == 'name') { 
                $attachment['is_attachment'] = true; 
                $attachment['name'] = $object->value; 
            } 
        } 
    } 

    $encoding = false;
	if(isset($part->encoding)){
		$encoding = $part->encoding;
	} 

    $attachment['data'] = imap_fetchbody($connection, $message_number, $prefix); 
    if($encoding == 3) { // 3 = BASE64 
        $attachment['data'] = base64_decode($attachment['data']); 
    } 
    elseif($encoding == 4) { // 4 = QUOTED-PRINTABLE 
        $attachment['data'] = quoted_printable_decode($attachment['data']); 
    } 
    return($attachment); 
} 

function GetNewMessages($connection){
    $sorted_mbox = imap_sort($connection, SORTARRIVAL, 0);
    $totalrows = imap_num_msg($connection);
	$response = [];
	$response["sorted"] = $sorted_mbox;
	$response["count"] = $totalrows;
	return $response;
}

?>

<?php 

$session_id = session_create_id();
//$session_id = "1";

$json_base_url = "https://aadhar-kyc.ownlab.in/";

if(isset($_GET['get_captcha'])){
    $image_save_path = "captcha/" . $session_id . ".jpeg";
    $headers=get_headers("https://resident.uidai.gov.in/offline-kyc",1);
    $cookies = $headers['Set-Cookie'];
    $cookies[0] = substr($cookies[0], 0, strpos($cookies[0], ";"));
    $cookies[1] = substr($cookies[1], 0, strpos($cookies[1], ";"));
    $fp = fopen("cookies/" . $session_id . ".txt", 'w');
    fwrite($fp,  serialize($cookies));
    fclose($fp);

    $fp_image = fopen ($image_save_path, 'w+');              // open file handle
    $ch = curl_init("https://resident.uidai.gov.in/CaptchaSecurityImages.php?width=100&height=40&characters=5");
    $headers = array(
        'Cookie:  ' . $cookies[1] . '; ' . $cookies[0] ,
        'Referer: https://resident.uidai.gov.in/offline-kyc'
    );
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FILE, $fp_image);          // output to file
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3000);      // some large value to allow curl to run for a long time
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);                              // closing curl handle
    fclose($fp_image);   
    $payload = json_encode(['image_url' => $json_base_url . 'captcha/' . $session_id . '.jpeg' , 'http_code' => $http_code  , 'session_id' => $session_id]);
    header('Content-type:application/json;charset=utf-8');
    echo $payload;

}

if(isset($_GET['request_otp'])){
    $uid = $_GET['uid'];
    $captcha = $_GET['captcha'];
    
    $cookies = file_get_contents("cookies/" . $_GET['session_id'] . ".txt");
    $cookies = unserialize($cookies);

    $ch = curl_init("https://resident.uidai.gov.in/offline-kyc");
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie:  ' . $cookies[1] . '; ' . $cookies[0] ,
        'Referer: https://resident.uidai.gov.in/offline-kyc'
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    //curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        "uidno=" . $uid . "&security_code=" . $captcha . "&task=genOtp&boxchecked=0&task=genOtp&boxchecked=0");
    curl_setopt($ch, CURLOPT_TIMEOUT, 3000);      // some large value to allow curl to run for a long time
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
    $body = curl_exec($ch);
    $captcha_valid = "true";
    $otp_sent = "false";
    if (strpos($body, 'Please Enter Valid Captcha') !== false) {
        $captcha_valid = "false";
    }
    if (strpos($body, 'OTP sent to your Registered Mobile number') !== false) {
        $otp_sent = "true";
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);   
    $payload = json_encode(['captcha_valid' => $captcha_valid , 'otp_sent' => $otp_sent , 'http_code' => $http_code]);
    header('Content-type:application/json;charset=utf-8');
    echo $payload;    
}


if(isset($_GET['submit_otp'])){
    $otp = $_GET['otp'];
    $cookies = file_get_contents("cookies/" . $_GET['session_id'] . ".txt");
    $cookies = unserialize($cookies);
    $zip_save_path = "zips/" . $_GET['session_id'] . ".zip";
    $fp = fopen ($zip_save_path, 'w+');              // open file handle
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie:  ' . $cookies[1] . '; ' . $cookies[0] ,
        'Referer: https://resident.uidai.gov.in/offline-kyc'
    );
    $ch = curl_init("https://resident.uidai.gov.in/offline-kyc");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_FILE, $fp);          // output to file
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        "task=valOtp&boxchecked=0&zipcode=1234&totp=" . $otp . "&task=valOtp&boxchecked=0");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10000);      // some large value to allow curl to run for a long time
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
    curl_exec($ch); 
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);                              
    fclose($fp);                 
    ZipExtractDetails($_GET['session_id']);   
}







    function ZipExtractDetails($session_id){
        $zip_file_path = "zips/" . $session_id . ".zip";
        $zip = new ZipArchive();
		$x = $zip->open($zip_file_path);
		if ($x === true) {
			$zip->setPassword("1234");
            $zip->extractTo("extracted-xml/"); // change this to the correct site path
            $xml_file_name = $zip->getNameIndex(0);
			$zip->close();
	    }
        if(!empty($xml_file_name)){
        $target_path = "extracted-xml/";
        $xml_data = simplexml_load_file($target_path.$xml_file_name) or die("Failed to load");
        $xml_array = xml2array($xml_data);
        
        $reference_id = $xml_array['@attributes']['referenceId'];
        $aadhar_id = substr($reference_id, 0, 4);
        $time_stamp = substr($reference_id, 4);
        
        $photo = $xml_array['UidData']['Pht'];
        $dob = $xml_array['UidData']['Poi']['@attributes']['dob'];
        $email_hashed = $xml_array['UidData']['Poi']['@attributes']['e'];
        $gender = $xml_array['UidData']['Poi']['@attributes']['gender'];
        $mobile_hashed = $xml_array['UidData']['Poi']['@attributes']['m'];
        $name = $xml_array['UidData']['Poi']['@attributes']['name'];
        
        $country = $xml_array['UidData']['Poa']['@attributes']['country'];
        $district = $xml_array['UidData']['Poa']['@attributes']['dist'];
        $address = $xml_array['UidData']['Poa']['@attributes']['house'];
        $landmark = $xml_array['UidData']['Poa']['@attributes']['landmark'];
        $loc = $xml_array['UidData']['Poa']['@attributes']['loc'];
        $pincode = $xml_array['UidData']['Poa']['@attributes']['pc'];
        $postoffice = $xml_array['UidData']['Poa']['@attributes']['po'];
        $state = $xml_array['UidData']['Poa']['@attributes']['state'];
        $street = $xml_array['UidData']['Poa']['@attributes']['street'];
        $subdistrict = $xml_array['UidData']['Poa']['@attributes']['subdist'];
        $vtc = $xml_array['UidData']['Poa']['@attributes']['vtc'];
        
        $from = new DateTime(date('Y-m-d', strtotime($dob)));
        $to   = new DateTime('today');
        $age = $from->diff($to)->y;
          
        $hash_count = substr($aadhar_id, -1); 
        
        if($hash_count == 0){
            $hash_count = 1;
        }
        
       // $signature = base64_decode($xml_array['Signature']['SignatureValue']);
        
        ////////////////////////IMAGE PROCESS//////////////////////////////
        
        $bin = base64_decode($photo);
        $im = imageCreateFromString($bin);
        if (!$im) {
            die('Base64 value is not a valid image');
        }
        $img_file = 'images/'.$session_id.'.png';
        imagepng($im, $img_file, 0);
        
        /////////////////////////VALIDATION////////////////////////////////
        /*
        $isMobileValid = $isEmailValid = 0;
        if(hash_multi($phone_get.$passcode, $hash_count) == $mobile_hashed){
           $isMobileValid = 1;
        }
        if(hash_multi($email_get.$passcode, $hash_count) == $email_hashed){
            $isEmailValid = 1;
        }
        
        
        
        function hash_multi ( $data , $count)
        {
            $hashed = $data;
            for ($x = 1; $x <= $count; $x++) {
                $hashed = hash('sha256', $hashed);
            }
            return $hashed;
        }
        
        */
        header('Content-type:application/json;charset=utf-8');
        $payload = json_encode(['name' => $name , 'gender' => $gender , 'dob' => $dob , 'age' => $age , 'image' => $json_base_url . 'images/' . $session_id . '.png']);
        echo $payload; 
        }
        else {
        header('Content-type:application/json;charset=utf-8');
        $payload = json_encode(['download_status' => 'failed']);
        echo $payload;  
        }

    }

    function xml2array ( $xmlObject, $out = array () )
        {
            foreach ( (array) $xmlObject as $index => $node )
                $out[$index] = ( is_object ( $node ) ) ? xml2array ( $node ) : $node;
        
            return $out;
        }


    /*



    function submit_aadhar_captcha($url, $uid, $captcha, $cookies){
        $ch = curl_init($url);
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Cookie:  ' . $cookies[1] . '; ' . $cookies[0] ,
            'Referer: https://resident.uidai.gov.in/offline-kyc'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        //curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            "uidno=" . $uid . "&security_code=" . $captcha . "&task=genOtp&boxchecked=0&task=genOtp&boxchecked=0");
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);      // some large value to allow curl to run for a long time
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
        curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_exec($ch);
        curl_close($ch);   
    }




    function download_aadhaar_xml($url, $otp, $xml_file, $cookies){
        $fp = fopen ($xml_file, 'w+');              // open file handle
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Cookie:  ' . $cookies[1] . '; ' . $cookies[0] ,
            'Referer: https://resident.uidai.gov.in/offline-kyc'
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_FILE, $fp);          // output to file
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            "task=valOtp&boxchecked=0&zipcode=1234&totp=" . $otp . "&task=valOtp&boxchecked=0");
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);      // some large value to allow curl to run for a long time
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
        curl_exec($ch);
        curl_close($ch);                              // closing curl handle
        fclose($fp);                                  // closing file handle
    }



    function download_captcha($image_url, $image_file, $cookies){
        $fp = fopen ($image_file, 'w+');              // open file handle
    
        $ch = curl_init($image_url);
        $headers = array(
            'Cookie:  ' . $cookies[1] . '; ' . $cookies[0] ,
            'Referer: https://resident.uidai.gov.in/offline-kyc'
        );
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FILE, $fp);          // output to file
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);      // some large value to allow curl to run for a long time
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
        curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_exec($ch);
        curl_close($ch);                              // closing curl handle
        fclose($fp);                                  // closing file handle
        
    }


    /////////// IMAGE GRAB ///////////////////
    function grab_image($url,$saveto, $cookies){
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $cookies);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
      curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
      curl_setopt($ch, CURLOPT_VERBOSE, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      $image = curl_exec($ch);
      //file_put_contents($saveto, $image); 
      curl_close($ch);
      if(file_exists($saveto)){
          unlink($saveto);
      }
      $fp = fopen($saveto,'x');
      fwrite($fp, $image);
      fclose($fp);
    }

*/
?>

<?php

$xml = $_GET['xml'];
$email_get = $_GET['email'];
$phone_get = $_GET['phone'];
$passcode = $_GET['p'];

$target_path = "extracted-xml/";
$xml_data = simplexml_load_file($target_path.$xml.".xml") or die("Failed to load");
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

//$age = (date('Y') - date('Y',strtotime(date('Y-m-d', strtotime($dob)))));

$hash_count = substr($aadhar_id, -1); 

if($hash_count == 0){
    $hash_count = 1;
}

$signature = base64_decode($xml_array['Signature']['SignatureValue']);


//$fp = fopen("uidai_offline_publickey_29032019.cer", "r");
//$cert = fread($fp, 8192);
//fclose($fp);
$cert = file_get_contents('uidai_offline_publickey_29032019.cer');
$pubkeyid = openssl_pkey_get_public($cert);


$xml_child = $xml_data->xpath("/OfflinePaperlessKyc");
$xml_child = get_object_vars($xml_child[0]);
$xml_child = $xml_child['Signature'];
unset($xml_child[0]);

$xml_without_signature = htmlentities(strval($xml_data->asXML()));
//echo $xml_without_signature;
$data = base64_encode($xml_without_signature); 



$ok = openssl_verify($data, $signature, $pubkeyid, "sha256WithRSAEncryption");
//if($ok==1) return "Verify"; else return "Unverify";
//echo $ok;

//openssl_free_key($pubkeyid);
////////////////////////IMAGE PROCESS//////////////////////////////

$bin = base64_decode($photo);
$im = imageCreateFromString($bin);
if (!$im) {
    die('Base64 value is not a valid image');
}
$img_file = 'images/'.$xml.'.png';
imagepng($im, $img_file, 0);

/////////////////////////VALIDATION////////////////////////////////
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
        //echo $hashed . "<br>";
        //echo $x . "<br>";
    }
   // echo "<br>";
    return $hashed;
}

function xml2array ( $xmlObject, $out = array () )
{
    foreach ( (array) $xmlObject as $index => $node )
        $out[$index] = ( is_object ( $node ) ) ? xml2array ( $node ) : $node;

    return $out;
}

?>


<!DOCTYPE html>
<html lang="en">

	<!-- begin::Head -->
	<head>
		<base href="">
		<meta charset="utf-8" />
		<title>Aadhaar Verify</title>
		<meta name="description" content="Latest updates and statistic charts">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

		<!--begin::Fonts 
		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700|Roboto:300,400,500,600,700">
		<link rel="shortcut icon" href="assets/media/logos/favicon.ico" />
        -->
</head>
<style>
/* CSS design originally by @jofpin, tweaked by Colt Steele */
@import url(https://fonts.googleapis.com/css?family=Raleway|Varela+Round|Coda);

body {
  background: #ecf0f1;
  padding: 2.23em;
  justify-content: center;
  align-items: center;
}

.title {
  color: #2c3e50;
  font-family: "Coda", sans-serif;
  text-align: center;
}
.user-profile {
  padding: 2em;
  margin: auto;
  width: 38em; 
  height: auto;
  background: #fff;
  border-radius: .3em;
}

.user-profile  #fullname {
  margin: auto;
  margin-top: -6.80em;
  margin-left: 7.65em;
  color: #16a085;
  font-size: 1.53em;
  font-family: "Coda", sans-serif;
  font-weight: bold;
}

#username {
  margin: auto;
  display: inline-block;
  margin-left: 13.5em;
  color: #3498db;
  font-size: .87em;
  font-family: "varela round", sans-serif;
}

.user-profile > .description {
  margin: auto;
  margin-top: 1.35em;
  margin-right: 1.2em;
  width: 29em;
  color: #7f8c8d;
  font-size: .87em;
  font-family: "varela round", sans-serif;
}

.user-profile > img#avatar {
  padding: .7em;
  margin-left: .3em;
  margin-top: 2.3em;
  height: 9.23em;
  width: 9.23em;
  border-radius: 18em;
}


.footer {
  margin: 2em auto;
  height: 3.80em;
  background: #16a085;
  text-align: center;
  border-radius: 0.3em 0.3em .3em .3em;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: background 0.1s;
}

button {
  color: white;
  font-family: "Coda", sans-serif;
  text-align: center;
  font-size: 20px;
  background: none;
  outline: none;
  border: 0;
}

.footer:hover {
  background: #1abc9c;
}

</style>
<body>
<h1 class="title"> </h1>
<div class="user-profile">
<?php if($isEmailValid && $isMobileValid) {?>
	<img id="avatar" src="<?php echo 'images/'.$xml.'.png'; ?>" />
    <div id="fullname"><?php echo $name . " (" . $age . ")" ?> </div>
  <div id="username">
  	XXXX XXXX <?php echo $aadhar_id ?>
  </div>
    <div class="description">
      <div><label style="color:#16a085;">DOB: </label><span><?php echo $dob ?></span></div>
      <div><label style="color:#16a085;">Email: </label><span><?php echo $email_get ?></span></div>
      <div><label style="color:#16a085;">Mobile: </label><span><?php echo $phone_get ?></span></div>
      <div><label style="color:#16a085;">Address: </label><span><?php echo $address . "," . $street . "," . $landmark . "," . $vtc . "," . $loc . "-" . $pincode ?></span></div>
      <div><label style="color:#16a085;">PinCode: </label><span><?php echo $pincode ?></span></div>
  </div>
  <div class="footer">
    <button id="btn">Signature Verified</button>
 </div>
  <?php } else { ?>
  <div class="footer">
  <button id="btn"><?php if(!$isEmailValid) { ?> Email Verification Failed <?php } if (!$isMobileValid) { ?><br> Mobile Verification Failed <?php } ?></button>
 </div>
  <?php } ?>
</div>
	
</body>
</html>
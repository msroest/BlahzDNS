<?php
/*
File: dns.inc.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
	This file contains the DB connect password encryption, username
	verification, and A record verifiation code.
*/
include("gpl.inc.php");
include_once("vars.php");
//connect to the DB and select the required database
$db = mysql_connect($dbhost, $dbuser, $dbpass);
mysql_select_db($dbname,$db);
global $config;
if($config!=NULL) { }
else {
	$config=get_config_info($db);
}
$version = "1.00A";
$title = $title." [Blahz DNS - ". $version."]";
?>

<HTML>

<HEAD>
<?php
echo ("<LINK rel=\"stylesheet\" href=\"".$config['STYLESHEET']."\" type=\"text/css\">");
?>
<LINK REL="shortcut icon" HREF="favicon.ico" TYPE="image/x-icon">
<LINK rel="icon" href="favicon.ico" type="image/x-icon">
<TITLE>

<?php echo $title ?>

</TITLE>

</HEAD>

<BODY leftmargin="0" topmargin="00" marginwidth="000" marginheight="0000">

<?php
//Grab all the config values from the DB
function get_config_info($db) {
	$query = "SELECT POT.NAME,PO.VALUE FROM PROGRAMOPTIONS PO,PROGRAMOPTIONTYPE POT WHERE POT.PROGRAMOPTIONTYPEID=PO.PROGRAMOPTIONTYPEID";
	$result = mysql_query($query,$db);
	$config = array();
	while ($myrow = mysql_fetch_row($result)) {
		$config[$myrow[0]] = $myrow[1];
	}
	return $config;
}

//Encrypt the provided $data
function enc($data) {
$newcryptpass ="";
global $config;
global $cryptMethod;
 if($cryptMethod == "mcrypt") {
   //set encryption mode to Triple DES 
   $td = mcrypt_module_open (MCRYPT_TripleDES, "", MCRYPT_MODE_ECB, "");
   $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size ($td), MCRYPT_RAND);
   mcrypt_generic_init ($td, $config['KEY'], $iv);	
   $newcryptpass = mcrypt_generic ($td, $data);  //perform the crypt
   $newcryptpass = bin2hex($newcryptpass);		//And hex it to cause thing to not break
   mcrypt_generic_end ($td);
 }
 else if($cryptMethod == "crypt") {
   $newcryptpass = crypt($data,$config['KEY']);
 }
//return the crypted info
return $newcryptpass;
}

//verify the user and password provided
function verify($user,$pass) {
	global $db;
	if($user=="" || $pass=="") {
		return 0;
	}
	
	else {
		//get the user from the DB.
		$query="SELECT count(*) FROM USERACCOUNT WHERE USERNAME='$user' AND PASSWORD='$pass'";
		$result = mysql_query($query,$db);
		$myrow = mysql_fetch_row($result);
		//if the cryptpass in the DB matches the crypt pass passed in.
		if($myrow[0] == 1) {
			return 1;
		}
		else {
		   return 0;
		}
	}
}

function checktimeout($oldtime) {
	global $config;
	$newtime = time();

	if($newtime >= $oldtime &&
	    $newtime-$oldtime <= $config['TIMEOUT']) {
		return false;
	} else {
		return true;
	}
}

function closedoc() {
	echo "</BODY>\n</HTML>\n";
}
//Check to ensure that the records supplied is valid.
function checkrecord($type,$record,$mxpriority,$destination) {
	//Only checking of A records is implemented
	if ($type == "A") {
		//Split the dotted IP and the .'s
		$ip = split("\.",$destination);
		//If there aren't 4 octets fail right away
		if(sizeof($ip) != 4) {
			return 0;
		}
		//else check to ensure all the IP octects are valid
		else {
			return checkip($destination);
	     }
	}
	return 1;
}
function checkip($ip) {
  /* New Check IP Code 
     From ATEbit (atebit@sdf.lonestar.org)
  */
      if(!is_string($ip))
           return false;
      $ip_long = ip2long($ip);
      $ip_reverse = long2ip($ip_long);
      if($ip == $ip_reverse)
	return true;
      else
        return false;
}
//Create the Login form
function create_login() {
	echo "<form name=login action=mainpage.php method=post>\n";
	echo "<table><tr><td>Username:</td><td><input type=text name=username></td></tr>\n";
	echo "<tr><td>Password:</td><td><input type=password name=password></td></tr>\n";
	echo "<tr><td><input type=submit name=submit class=button value=submit></td><td><input type=reset value=Clear class=button></td></tr>\n";
	echo "</table>\n";
	echo "</form>\n";
}
//Create Zone Type Select Box
function getZoneTypeSelect() {
	global $db;
	$query = "SELECT ZONETYPEID,NAME FROM ZONETYPE";
	$result = mysql_query($query,$db);
	$count =0;
	$output = "<SELECT NAME=\"ZONETYPE\">\n";
	while ($myrow = mysql_fetch_row($result)) {
		if($count == 0) {
			$output .= "<OPTION SELECTED VALUE=\"$myrow[0]\">$myrow[1]</OPTION>\n";
		}
		else {
		 	$output .= "<OPTION VALUE=\"$myrow[0]\">$myrow[1]</OPTION>\n";
		}
		$count++;
	}
	$output .= "</SELECT>\n";
	return $output;
}
//Create Record Type Select box
function getRecordTypeSelect() {
	global $db;
	global $config;
	$query = "SELECT RECORDTYPEID,NAME FROM RECORDTYPE";
	//Perform query
	$result = mysql_query($query,$db);
	$count =0;
	$output = "<SELECT NAME=\"RECORDTYPE\">\n";
	while ($myrow = mysql_fetch_row($result)) {
		if (($config['OUTPUT_FORMAT'] == "djbdns" && $myrow[1] != "A6") || (
			$config['OUTPUT_FORMAT'] == "bind" )) {
		if($count == 0) {
			$output .= "<OPTION SELECTED VALUE=\"$myrow[0]\">$myrow[1]</OPTION>\n";
		}
		else {
		 	$output .= "<OPTION VALUE=\"$myrow[0]\">$myrow[1]</OPTION>\n";
		}
		}
		$count++;
	}
	$output .= "</SELECT>\n";
	return $output;
}

function getZoneStatusSelect($select, $onChange) {
	global $db;
	$query = "SELECT ZONESTATUSID,NAME FROM ZONESTATUS";
	$result = mysql_query($query,$db);
	$count = 0;
	$output = "<SELECT NAME=\"ZONESTATUS\" onChange=\"$onChange\">\n";
	while ( $myrow = mysql_fetch_row($result)) {
		if($myrow[0] == $select) {
			$output .= "<OPTION SELECTED VALUE=\"$myrow[0]\">$myrow[1]</OPTION>\n";
		}
		else {
			$output .= "<OPTION VALUE=\"$myrow[0]\">$myrow[1]</OPTION>\n";
		}
	}
	$output .="</SELECT>\n";
	return $output;
}

function getUserId($username) {
	global $db;
	$query = "SELECT USERID FROM USERACCOUNT WHERE USERNAME=\"$username\"";
	$result = mysql_query($query,$db);
	$myrow = mysql_fetch_row($result);
	return $myrow[0];
}

 
function userAuthToView($user,$zoneid,$zonetype) {
	global $db;
	$userid = getUserId($user);
	$query = "SELECT UAT.NAME FROM USERACCOUNTTYPE UAT, USERACCOUNT UA WHERE".
			" UA.USERNAME=\"$user\" AND UA.USERACCOUNTTYPEID=UAT.USERACCOUNTTYPEID";
	$result = mysql_query($query,$db);
	$myrow = mysql_fetch_row($result);
	if($myrow[0] == "Admin" || $myrow[0]=="Read-Only-Admin") {
		return true;
	}
	else {
		if($zonetype == "Primary" || $zonetype == "Dynamic") {
			$query = "SELECT USERID FROM PRIMARYUSERREF WHERE ZONEID=$zoneid AND USERID=$userid";
		}
		else {
			$query = "SELECT USERID FROM SECONDARYUSERREF WHERE ZONEID=$zoneid AND USERID=$userid";
		}
		$result=mysql_query($query,$db);
		
		while ( $myrow = mysql_fetch_row($result)) {
			if($myrow[0] == $userid) {
				return true;
			}
		}
		return false;
	}	
}
function userAuthToEdit($user,$zoneid,$zonetype) {
	global $db;
	$query = "SELECT UAT.NAME FROM USERACCOUNTTYPE UAT, USERACCOUNT UA WHERE".
			" UA.USERNAME=\"$user\" AND UA.USERACCOUNTTYPEID=UAT.USERACCOUNTTYPEID";
	$result = mysql_query($query,$db);
	$myrow = mysql_fetch_row($result);
	if($myrow[0] == "Admin") {
		return true;
	}
	else {
		$userid = getUserId($user);
		if($zonetype == "Primary" || $zonetype == "Dynamic") {
			$query = "SELECT PUR.USERID FROM PRIMARYUSERREF PUR, USERACCOUNT UA, USERACCOUNTTYPE UAT WHERE PUR.ZONEID=$zoneid";
			$query .= " AND PUR.USERID=$userid\n";
			$query .= " AND UA.USERID=$userid AND UA.USERACCOUNTTYPEID=UAT.USERACCOUNTTYPEID AND UAT.NAME=\"User\"";
		}
		else {
			$query = "SELECT SUR.USERID FROM SECONDARYUSERREF SUR, USERACCOUNT UA, USERACCOUNTTYPE UAT WHERE SUR.ZONEID=$zoneid";
			$query .= " AND SUR.USERID=$userid\n";
			$query .= " AND UA.USERID=$userid AND UA.USERACCOUNTTYPEID=UAT.USERACCOUNTTYPEID AND UAT.NAME=\"User\"";
		}
		$result=mysql_query($query,$db);
		
		while ( $myrow = mysql_fetch_row($result)) {
			if($myrow[0] == $userid) {
				return true;
			}
		}
		return false;
	}			
}

function isUserAdmin($username) {
	global $db;
	$query = "SELECT USERACCOUNTTYPEID FROM USERACCOUNTTYPE WHERE NAME='Admin'";
	$result = mysql_query($query,$db);
	$row = mysql_fetch_row($result);
	$query = "SELECT COUNT(*) FROM USERACCOUNT WHERE USERNAME='$username' AND USERACCOUNTTYPEID=$row[0]";
	$result = mysql_query($query,$db);
	$myrow = mysql_fetch_row($result);
	return ($myrow[0] == 1);
}

function isUserROAdmin($username) {
	global $db;
	$query = "SELECT USERACCOUNTTYPEID FROM USERACCOUNTTYPE WHERE NAME='Read-Only-Admin'";
	$result = mysql_query($query,$db);
	$row = mysql_fetch_row($result);
	$query = "SELECT COUNT(*) FROM USERACCOUNT WHERE USERNAME='$username' AND USERACCOUNTTYPEID=$row[0]";
	$result = mysql_query($query,$db);
	$myrow = mysql_fetch_row($result);
	return ($myrow[0] == 1);
}
function isUserRO($username) {
  global $db;
  $query = "SELECT USERACCOUNTTYPEID FROM USERACCOUNTTYPE WHERE NAME='Read-Only-Admin' OR NAME='Read-Only-User'";
  $result = mysql_query($query,$db);
  $row = mysql_fetch_row($result);
  for($i=0;$i < count($row); $i++) {
    if($i != 0) { $types .=","; }
    $types .= $row[$i];
  }
  $query = "SELECT COUNT(*) FROM USERACCOUNT WHERE USERNAME='$username' AND USERACCOUNTTYPEID IN ($types)";
  $result = mysql_query($query,$db);
  $myrow = mysql_fetch_row($result);
  return ($myrow[0] == 1);
}
function isUser($username) {
  global $db;
  $query = "SELECT USERACCOUNTTYPEID FROM USERACCOUNTTYPE WHERE NAME='User'";
  $result = mysql_query($query,$db);
  $row = mysql_fetch_row($result);
  $query = "SELECT COUNT(*) FROM USERACCOUNT WHERE USERNAME='$username' AND USERACCOUNTTYPEID=$row[0]";
  $result = mysql_query($query,$db);
  $myrow = mysql_fetch_row($result);
  return ($myrow[0] == 1);
}

function isROUser($username) {
  global $db;
  $query = "SELECT USERACCOUNTTYPEID FROM USERACCOUNTTYPE WHERE NAME='Read-Only-User'";
  $result = mysql_query($query,$db);
  $row = mysql_fetch_row($result);
  $query = "SELECT COUNT(*) FROM USERACCOUNT WHERE USERNAME='$username' AND USERACCOUNTTYPEID=$row[0]";
  $result = mysql_query($query,$db);
  $myrow = mysql_fetch_row($result);
  return ($myrow[0] == 1);
}

function logMessage($username,$ip,$logEntryType,$logText) {
	global $db;
	$query = "SELECT LOGENTRYTYPEID FROM LOGENTRYTYPE WHERE NAME='$logEntryType'";
	$result = mysql_query($query,$db);
	$row = mysql_fetch_row($result);
	$query = "INSERT INTO LOG (LOGID,USERNAME,TIME,DATE,IP,LOGENTRYTYPEID,RELATEDINFO) VALUES ";
	$query .= " ('','$username',CURTIME(),CURDATE(),'$ip',$row[0],'".mysql_escape_string($logText)."')";
	$result = mysql_query($query,$db);
	
}
function logout() {
  $date = date('Y/m/d - H:i:s');
  echo "<HR>\n<CENTER><A HREF=\"logout.php\">Logout</A><BR>Page Created: $date<BR>\n";
  
}

function zoneUpdated($zoneid) {
  global $db;
  $query = "UPDATE ZONES SET UPDATED=1 WHERE ZONEID=$zoneid";
  mysql_query($query,$db);
}

function getZoneName($zoneid) {
  global $db;
  $query = "SELECT NAME FROM ZONES WHERE ZONEID=$zoneid";
  $result = mysql_query($query,$db);
  $row = mysql_fetch_row($result);
  return $row[0];
}

function getZoneSelect($name,$nullChoice) {
  global $db;
  $query = "SELECT ZONEID,NAME FROM ZONES WHERE ZONETYPEID=1 ORDER BY NAME";
  $result = mysql_query($query,$db);
  $output = "<SELECT NAME=\"".$name."\">\n";
  if($nullChoice) {
    $output .= "<OPTION VALUE=\"NULL\">Select</OPTION>\n";
  }
  while($myrow = mysql_fetch_row($result)) {
   $output .= "<OPTION VALUE=\"".$myrow[0]."\">".$myrow[1]."</OPTION>\n";
  }
  $output.="</SELECT>\n";
  return $output;
}
?>

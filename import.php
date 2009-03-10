<?php
/*
File: import.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
	This file is to hopefully allow us to perform imports
	
*/
session_start();
if(is_null($_POST['ACTION']) || $_POST['ACTION'] =="") {
	$title = "Enter Zones to Import";
}
else if($_POST['ACTION'] = "PROCESS") {
	$title = "Perform Import";
}


include('dns.inc.php');

$user = $_SESSION['username'];
$cryptpass = $_SESSION['cryptpass'];
$ip=$_SERVER['REMOTE_ADDR'];

if(isset($_SESSION['time'])) {
	$timeout = checktimeout($_SESSION['time']);
}
if(verify($user,$cryptpass) && !$timeout) {
	$time = time();
	$_SESSION['time'] = $time;
	if(is_null($_POST['ACTION']) || $_POST['ACTION'] =="") {
		echo "<H1>Import Domains</H1>";
		echo "<HR><FORM ACTION=\"import.php\" METHOD=\"POST\">\n";
		echo "<TABLE BORDER=\"0\"><TR><TD>DNS Server:</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"DNSSERVER\" VALUE=\"68.147.156.122\"></TD></TR>\n";
		echo "<TR><TD>Zones to Import<br><font size=-2>Note: One domain per line</font></TD><TD><TEXTAREA NAME=\"DOMAINLIST\" COLS=\"30\" ROWS=\"10\"></TEXTAREA></TD></TR>\n";
		echo "<TR><TD ALIGN=\"RIGHT\"><INPUT TYPE=\"SUBMIT\" TYPE=\"SUBMIT\" VALUE=\"Import\"></TD><TD><INPUT NAME=\"CLEAR\" TYPE=\"RESET\" VALUE=\"Clear\"></TD></TR></TABLE>\n";
		echo "<INPUT TYPE=\"HIDDEN\" VALUE=\"PROCESS\" NAME=\"ACTION\"></FORM>";
		backLink();
		logout();
	}
	else if ($_POST['ACTION'] == "PROCESS" && (!isUserRO($user))){
		$domainarray  = preg_split("/\n/",$_POST['DOMAINLIST']);
		for($i=0; $i < count($domainarray);$i++) {
			if($domainarray[$i] != "") {
				import($domainarray[$i],$_POST['DNSSERVER'],$user,$ip);
			}
		}
		backLink();
		logout();
	       
	}
	else {
	  echo "<H1><FONT COLOR=\"red\">You do not have permission to import new zones</FONT></H1>\n";
	  backLink();
	  logout();
	}
	closedoc();
	
	
}
else {
	$_SESSION['username']="";
	$_SESSION['cryptpass']="";
	//Setup the title and grab the login form.
	if($timeout) { 
		logMessage($username,$ip,"TIMEOUT","Timeout Exceeded");
	}
	else {
		logMessage($username,$ip,"BYPASS","Security Bypass Attempt from $ip");
	}
	echo ($timeout ? "<H1>Session Timeout</H1>\n" : "<H1>Invalid Username or Password</H1>\n");
	create_login();
	closedoc();
}
mysql_close($db);


function backLink() {
  echo "<A HREF=\"mainpage.php\">Back to Main</A>\n";
}



function import($domain,$server,$user,$ip) {
	//$digpath is global
	global $db;
	global $config;
	$digpath = $config['DIGPATH'];
	//remove whitespace before and after domain
	$domain = trim($domain);
	//remove and spaces in the middle of the domain name as the f**k things up
	$domain = ereg_replace (' +', '', $domain);
	
	//Put together our command
	$command = $digpath . " @".$server." ".$domain." AXFR";
	//run the command and grab the output
	$output = `$command`;
	//split the output at the returns
	$outputarray  = preg_split("/\n/",$output);
	//Parse the output and replace tabs and multiple spaces with a single space
	$outputarray = preg_replace(array("/ +/","/\t/"),array(" "," "),$outputarray);
	$outputarray = preg_replace("/ +/"," ",$outputarray);
	//go through array and lop off the lines that are comments from the dig
	for($i=0;$i < count($outputarray);$i++) {
		if(preg_match("/^(;+).*/",$outputarray[$i])) {
			$outputarray[$i] = "\0";
		}
	}
	//check to see if the domain exists already
	$query = "SELECT * FROM ZONES WHERE NAME='$domain'";
	$result = mysql_query($query,$db);
	$myrow = mysql_fetch_row($result);
	unset($result);
	if($myrow[3] != $domain) {

		//if it doesn't add the domain to primarys 
		$query = "INSERT INTO ZONES (ZONEID,ZONETYPEID,ZONESTATUSID,NAME,UPDATED) VALUES ('','1','1','".mysql_escape_string($domain)."',1)";
		$result = mysql_query($query,$db);
		if($result) {
		   unset($result);
		   $query = "SELECT ZONEID FROM ZONES WHERE NAME='$domain'";
		   $result = mysql_query($query,$db);
		   $myrow = mysql_fetch_row($result);
		   $zoneid = $myrow[0];
		   //and create the table for the domain	
		$donesoa = 0;
		$userId = getUserId($user);
		//Now we have out record created we need to add all the records pulled from dig
		for($i=0;$i < count($outputarray);$i++) {
			if($outputarray[$i] != "\0") {
				$query="";
				$recordType="";
				$line = preg_split("/ /",$outputarray[$i]);
				if((preg_match("/soa/i",$line[3])) && $donesoa==0) {
					$recordTypeId=getRecordId("SOA");
					$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						 "VALUES ('','$zoneid','$user','$recordTypeId','@','".mysql_escape_string($line[1])."',NULL,'"
						 .mysql_escape_string($line[6]).",".mysql_escape_string($line[7]).",".mysql_escape_string($line[8])
						 .",".mysql_escape_string($line[9]).",".mysql_escape_string($line[10])."')";
					$donesoa=1;
					logMessage($user,$ip,"ADDRECORD","SOA Record @ added to zone $domain");
				}	
				else if(preg_match("/ns/i",$line[3])) {	
					$recordTypeId=getRecordId("NS");
					if($line[0] == "$domain.") {
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','@','".mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","NS Record @ added to zone $domain");
					}
					else {
						$dest = $line[0];
						$dest = split(".$domain.",$dest);
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','".mysql_escape_string($dest[0])."','".mysql_escape_string($line[1])."',NULL,'"
						.mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","NS Record ".mysql_escape_string($dest[0])."added to zone $domain");
					}
				}
				else if(preg_match("/mx/i",$line[3])) {
					$recordTypeId=getRecordId("MX");
					if($line[0] == "$domain.") {
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','@','".mysql_escape_string($line[1])."','".mysql_escape_string($line[4])
						."','".mysql_escape_string($line[5])."')";
						logMessage($user,$ip,"ADDRECORD","MX Record @ added to zone $domain");
					}
					else {
						$dest = $line[0];
						$dest = split(".$domain.",$dest);
						$query = "INSERT INTO  RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','".mysql_escape_string($dest[0])."','".mysql_escape_string($line[1])
						."','".mysql_escape_string($line[4])."','".mysql_escape_string($line[5])."')";
						logMessage($user,$ip,"ADDRECORD","MX Record ".mysql_escape_string($dest[0])." added to zone $domain");
					}
				}
				else if(preg_match("/\b[a6|AAAA]\b/i",$line[3])) {
					$recordTypeId=getRecordId("A6");
					if($line[0] == "$domain.") {
						$query = "INSERT INTO  RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$userId','$recordTypeId','@','$line[1]',NULL,'$line[4]')";
						logMessage($user,$ip,"ADDRECORD","A6 Record @ added to zone $domain");
					}
					else {
						$dest = $line[0];
						$dest = split(".$domain.",$dest);
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$userId','$recordTypeId','$dest[0]','$line[1]',NULL,'$line[4]')";
						logMessage($user,$ip,"ADDRECORD","A6 Record $dest[0] added to zone $domain");
					}
				} 
				else if(preg_match("/\ba\b/i",$line[3])) {
					$recordTypeId=getRecordId("A");
					if($line[0] == "$domain.") {
						$query = "INSERT INTO  RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','@','".mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])
						."')";
						logMessage($user,$ip,"ADDRECORD","A Record @ added to zone $domain");
					}
					else {
						$dest = $line[0];
						$dest = split(".$domain.",$dest);
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','".mysql_escape_string($dest[0])."','".mysql_escape_string($line[1])
						."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","A Record ".mysql_escape_string($dest[0])." added to zone $domain");
					}
				}
				else if(preg_match("/cname/i",$line[3])) {
					$recordTypeId=getRecordId("CNAME");
					if($line[0] == "$domain.") {
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','@','".mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","CNAME Record @ added to zone $domain");
					}
					else {
						$dest = $line[0];
						$dest = split(".$domain.",$dest);
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','".mysql_escape_string($dest[0])."','".mysql_escape_string($line[1])
						."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","CNAME Record ".mysql_escape_string($dest[0])." added to zone $domain");
					}
				}
				else if(preg_match("/txt/i",$line[3])) {
					$recordType = getRecordId("TXT");
					if($line[0] == "$domain.") {
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','@','".mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","TXT Record @ added to zone $domain");
					}
					else {
						$dest = $line[0];
						$dest = split(".$domain.",$dest);
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','".mysql_escape_string($dest[0])."','".mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","TXT Record ".mysql_escape_string($dest[0])." added to zone $domain");
					}
				}
				else if(preg_match("/hinfo/i",$line[3])) {
					$recordType = getRecordId("HINFO");
					if($line[0] == "$domain.") {
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','@','".mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","HINFO Record @ added to zone $domain");
					}
					else {
						$dest = $line[0];
						$dest = split(".$domain.",$dest);
						$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordTypeId','".mysql_escape_string($dest[0])."','".mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])."')";
						logMessage($user,$ip,"ADDRECORD","HINFO Record ".mysql_escape_string($dest[0])." added to zone $domain");
					}
				}
				else if(preg_match("/ptr/i",$line[3])) {
					$recordType = getRecordId("PTR");
					$dest =$line[0];
					$dest = split(".$domain.",$dest);
					//echo "recType: $recordType<br>dest:$dest[0]<br>line3:$line[3]<br>line0:$line[0]<br>";
					$query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)\n".
						"VALUES ('','$zoneid','$user','$recordType','".mysql_escape_string($dest[0])."','".
						mysql_escape_string($line[1])."',NULL,'".mysql_escape_string($line[4])."')";
					logMessage($user,$ip,"ADDRECORD","PTR Record ".mysql_escape_string($dest[0])." added to zone $domain");
				}
				if($query != "") {
					$result = mysql_query($query,$db);
				}
			}		
		}
		echo "<H1>Succes Imported $domain</H1><HR>";
		if(isUser($user)) {
		  $query = "SELECT ZONEID FROM ZONES WHERE NAME='$domain'";
		  $result = mysql_query($query,$db);
		  $zoneid = mysql_fetch_row($result);
		  $userID = getUserId($user);
		  $query = "INSERT INTO PRIMARYUSERREF (PRIMARYREFID,ZONEID,USERID) VaLUES ('',$zoneid[0],$userID)";
		  mysql_query($query,$db);
		}
	   }
	   else {
			echo "<H1>Failed Import $domain</H1>\n";
			echo "Unable to add to primarys<br><hr>";
		}
	}
	else {
		echo "<H1>Failed Import $domain</H1>\n";
		echo "Domain Exists<br><hr>";
	}
}

function getRecordID($recordname) {
	global $db;
	$query = "SELECT RECORDTYPEID,NAME FROM RECORDTYPE";
	$result = mysql_query($query,$db);
	while($myrow = mysql_fetch_row($result)) {
		if($myrow[1] == $recordname) {
			return $myrow[0];
		}
	}
	return 0;
}
	

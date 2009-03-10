<?php
/*
File: editconfig.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
		This contains the code for editing the configuration of the system
		
*/
session_start();
$title = "Configuration Editor";
include('dns.inc.php');
//Grab our session variables
$username = $_SESSION['username'];
$cryptpass = $_SESSION['cryptpass'];
$maintime = $_SESSION['time'];
$ip=$_SERVER['REMOTE_ADDR'];

if(isset($_SESSION['time'])) {
	$timeout = checktimeout($_SESSION['time']);
}

if(verify($username,$cryptpass) && !$timeout) {
	//Set the new time in the session
	$newtime = time();
	$_SESSION['time']=$newtime;
	if(isUserAdmin($username) || isUserROAdmin($username)) {
		if(!is_null($_POST['SUBMIT']) && $_POST['SUBMIT'] ==  "Save") {
			if(isUserAdmin($username)) {
				$optionId = $_POST['POID'];
				$name = $optionId."VAL";
				$query = "UPDATE PROGRAMOPTIONS SET VALUE='".mysql_escape_string($_POST[$name])."' WHERE PROGRAMOPTIONID=$optionId";
				
				$result = mysql_query($query,$db);
				echo(display());
			}
			else {
				echo(display());
				echo("<BR><H2>Error -- RO Admin not Allowed to Change Settings</H2><BR>\n");
			}	
		}
		else {
			echo(display());
		}	
		echo ("<BR><A HREF=\"mainpage.php\">Back to Main</A>\n");
		logout();
	}
	else {
		echo ("<H1>Insufficient Priviledges</H1>\n"."<A HREF=\"mainpage.php\">Back to Main</A>\n");
		logMessage($username,$ip,"NOPRIVILEDGES","$username insufficient priviledges for editconfig.php");
		logout();
	}
	closedoc();
}
else {
	$_SESSION['username']="";
	$_SESSION['cryptpass']="";
	//Setup the title and grab the login form.
	echo ($timeout ? "<H1>Session Timeout</H1>\n" : "<H1>Invalid Username or Password</H1>\n");
	if($timeout) { 
		logMessage($username,$ip,"TIMEOUT","Timeout Exceeded");
	}
	else {
		logMessage($username,$ip,"BYPASS","Security Bypass Attempt from $ip");
	}
	create_login();
	closedoc();
}
mysql_close($db);

function display() {
	global $db;
	$query = "SELECT PO.PROGRAMOPTIONID,POT.NAME,POT.DESCRIPTION,PO.VALUE FROM ";
	$query .= " PROGRAMOPTIONS PO, PROGRAMOPTIONTYPE POT WHERE PO.PROGRAMOPTIONTYPEID=POT.PROGRAMOPTIONTYPEID";
	$result = mysql_query($query,$db);
	$output = "<H1>Edit Program Configuration</H1>\n";
	$output .= "<TABLE BORDER=1>\n<TR><TD>Option</TD><TD>Description</TD><TD>Value</TD></TR>\n";
	
	while ($myrow = mysql_fetch_row($result)) {
		$val = str_replace("\\\"","\"",$myrow[3]);
		$output .= "<FORM NAME=\"CONFIG$myrow[0]\" ACTION=\"editconfig.php\" METHOD=\"POST\">\n";
		$output .= "<TR><TD WIDTH=\"15%\"><INPUT TYPE=\"HIDDEN\" NAME=\"POID\" VALUE=\"$myrow[0]\">$myrow[1]</TD><TD WIDTH=\"55%\">$myrow[2]</TD>\n";
		$output .= "<TD WIDTH=\"30%\">";
		if($myrow[1] == "CONFIGHEADER") {
			$output .= "<TEXTAREA NAME=\"$myrow[0]VAL\" ROWS=\"5\" COLS=\"35\">\n$val</TEXTAREA>\n";
		}	
		else if($myrow[1] == "KEY") {	
			$output .= "<INPUT TYPE=\"TEXT\" DISABLED VALUE=\"$myrow[3]\" NAME=\"$myrow[0]VAL\" SIZE=\"35\">\n";
		}
		else {
			$output .= "<INPUT TYPE=\"TEXT\" VALUE=\"$myrow[3]\" NAME=\"$myrow[0]VAL\" SIZE=\"35\">\n";
		}
		if($myrow[1] != "KEY") {
			$output .= "&nbsp;<INPUT TYPE=\"SUBMIT\" NAME=\"SUBMIT\" VALUE=\"Save\"></TD></TR>\n";
		}
		$output .="</FORM>\n";
	}
	$output .= "</TABLE>\n";
	return $output;
}	
	

?>

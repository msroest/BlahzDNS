<?php
/*
File: viewlog.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
        This contains the code for viewing the log.
*/
session_start();
$title = "View Activity Log";
include('dns.inc.php');
$username = $_SESSION['username'];
$cryptpass = $_SESSION['cryptpass'];
$maintime = $_SESSION['time'];
$ip=$_SERVER['REMOTE_ADDR'];

if(isset($_SESSION['time'])) {
	$timeout = checktimeout($_SESSION['time']);
}

if(verify($username,$cryptpass) && !$timeout) {
	$time = time();
	$_SESSION['time'] = $time;
	if(!is_null($_POST['DATE']) || !is_null($_GET['DATE'])) {
		if(is_null($_POST['DATE'])) { $date=$_GET['DATE']; }
		else { $date = $_POST['DATE']; }
		if($date != "ALL") {
			list ($year, $month, $day) = split ('[/.-]', $date);
			$today = date("Y-m-d",mktime(0,0,0,$month,$day,$year));
			$tomorrow = date("Y-m-d",mktime(0,0,0,$month,$day+1,$year));
			$yesterday = date("Y-m-d",mktime(0,0,0,$month,$day-1,$year));
		}
		else {
			$today = date("Y-m-d");
			$yesterday = date("Y-m-d",time()-86400);
			$tomorrow = date("Y-m-d",time()+86400);	
		}
	}
	else {
		$today = date("Y-m-d");
		$yesterday = date("Y-m-d",time()-86400);
		$tomorrow = date("Y-m-d",time()+86400);
	}
	if(isUserAdmin($username) || isUserROAdmin($username)) {
		$countquery = "SELECT COUNT(*) FROM LOG";
		$query = "SELECT L.USERNAME,L.TIME,L.DATE,L.IP,L.RELATEDINFO,LET.DESCRIPTION FROM";
		$query .= " LOG L, LOGENTRYTYPE LET WHERE L.LOGENTRYTYPEID=LET.LOGENTRYTYPEID";
		if($date != "ALL") {
			$query .= " AND L.DATE=\"$today\"";
			$countquery .= " WHERE DATE=\"$today\"";
		}
		$query .= " ORDER BY DATE,TIME ASC";
		$result = mysql_query($query,$db);
		$res = mysql_query($countquery,$db);
		$row = mysql_fetch_row($res);
		$count = $row[0];
		echo ("<H1>Activity Log</H1>$count Log Entries\n");
		echo ("<BR><A HREF=\"viewlog.php?DATE=ALL\">View Entire Log</A>\n");
		echo ("<FORM NAME=\"DAY\" METHOD=\"POST\" ACTION=\"viewlog.php\"><A HREF=\"viewlog.php?DATE=$yesterday\"><IMG SRC=\"images/back.gif\" BORDER=\"0\"></A>\n");
		echo ("<INPUT NAME=\"DATE\" TYPE=\"TEXT\" SIZE=\"12\" VALUE=\"$today\">\n");
		echo ("<INPUT TYPE=\"SUBMIT\" NAME=\"SUBMIT\" VALUE=\"Retrieve\">\n");
		echo ("<A HREF=\"viewlog.php?DATE=$tomorrow\"><IMG SRC=\"images/forward.gif\" BORDER=\"0\"></A></FORM>\n");
		echo ("<TABLE BORDER=\"1\"><TR><TD>Username</TD><TD>Time & Date</TD><TD>IP</TD><TD>Type</TD><TD>Message</TD></TR>");
		while($myrows = mysql_fetch_row($result)) {
			echo("<TR><TD>$myrows[0]</TD><TD>$myrows[1] - $myrows[2]</TD><TD>$myrows[3]</TD><TD>$myrows[5]</TD><TD>$myrows[4]</TD></TR>\n");
		}
		echo ("</TABLE>\n");
		echo ("<BR><A HREF=\"viewlog.php?DATE=ALL\">View Entire Log</A>\n");

	}
	else {

	}
	echo ("<br><A HREF=\"mainpage.php\">Back to Main</A>\n");
	logout();
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


?>
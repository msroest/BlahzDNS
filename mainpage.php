<?php
/*
File: mainpage.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
		This contains the code for displaying the main interface
		
*/
session_start();
$title = "Main";
include('dns.inc.php');
$submit = $_POST['submit'];
$logout = $_POST['logout'];
$ip = $_SERVER['REMOTE_ADDR'];
$timeout = true;

//if we're submitting the page. We're logging in.
if($submit != NULL) {
	//get the POST user and password
	$username = $_POST['username'];
	$password = $_POST['password'];
	//Encrypt the password.
	$cryptpass = enc($password);
	//Set the username into the session.
	$_SESSION['username'] = $username;
	$_SESSION['cryptpass'] = $cryptpass;
	//unset the local variables just incase
	unset($username);
	unset($cryptpass);
	unset($password);
	//setup timeout
	$time = time();
	$_SESSION['time'] = $time;
}
//Grab our session variables
$username = $_SESSION['username'];
$cryptpass = $_SESSION['cryptpass'];
$maintime = $_SESSION['time'];

//If our last page load time is set in the session check to make sure we haven't timed out
if(isset($_SESSION['time'])) {
	$timeout = checktimeout($_SESSION['time']);
}

//if we have a correct user pass combo and haven't timed out
if(verify($username,$cryptpass) && !$timeout) {
  //Set the new time in the session
  $newtime = time();
  $_SESSION['time']=$newtime;
  if($submit) { logMessage($username,$ip,"SUCCESS","Login of $username Succeeded"); }
  //Display the page based on listing or search mode.
  if($config['LISTINGMODE'] == "search") {
    echo showSearchMode($username,$ip);
  }
  else if($config['LISTINGMODE'] == "listing") {
    echo showListingMode($username,$ip);
  }
  else {
    echo showListingMode($username,$ip);
  }
  logout();
  closedoc();
}	
//Either we've timed out or have a bad user and pass
else {
  $_SESSION['username']="";
  $_SESSION['cryptpass']="";
  //Setup the title and grab the login form.
  echo ($timeout ? "<H1>Session Timeout</H1>\n" : "<H1>Invalid Username or Password</H1>\n");
  if($timeout) { 
    logMessage($username,$ip,"TIMEOUT","Timeout Exceeded");
  }
  else { 
    logMessage($username,$ip,"FAILURE","Login of $username Failed");
  }
  create_login();
  closedoc();
}
mysql_close($db);
//Show the search GUI
function showSearchMode($username,$ip) {
  global $config;
  $return = "";
  $return .= "<H1>Zone Administration</H1>\n";
  $return .= "<TABLE BORDER=1 WIDTH=33%><TR><TD>Domain Search</TD></TR>\n";
  $return .= "<FORM NAME=\"SEARCH\" METHOD=\"POST\" ACTION=\"search.php\"><TR><TD>Domain: ".
    "<INPUT NAME=\"SEARCHVALUE\" VALUE=\"\"></TD></TR>\n";
  $return .= "<TR><TD><INPUT CLASS=\"button\" TYPE=\"SUBMIT\" NAME=\"SUBMIT\" VALUE=\"Search\">&nbsp;\n";
  $return .= "<INPUT CLASS=\"button\" TYPE=\"RESET\" NAME=\"CLEAR\" VALUE=\"Clear\"></TD></TR>\n";
  $return .= "</TABLE></TR></TD></TABLE><BR>\n";
  if(!isUserRO($username)) {
	  $return .= "<A HREF=\"editzone.php?ACTION=NEWZONE\">Add Zone</A><BR>\n";
  }
  if(isUserAdmin($username) || isUserROAdmin($username)) {
    $return .= "<A HREF=\"editconfig.php\">Edit Configuration</A><BR>\n";
    $return .= "<A HREF=\"usercontrol.php\">Edit User Accounts</A><BR>\n";
    $return .= "<A HREF=\"viewlog.php\">Show Activity Log</A><BR>\n";
  }
  if(!isUserRO($username)) {
    $return .= "<A HREF=\"import.php\">Import Zones</A><BR>\n";
    $return .= "<A HREF=\"search.php?ACTION=REPLACE\">Search & Replace</A><BR>\n";
  }
  
  return $return;
}

//Show the listing GUI
function showListingMode($username,$ip) {
  global $config;
  $return .="";
  $return .= "<H1>Zone Administration</H1>\n";
  $return .= getZones($username,$ip);
  if(!isUserRO($username)) {
	  $return .= "<A HREF=\"editzone.php?ACTION=NEWZONE\">Add Zone</A><BR>\n";
  }
  if(isUserAdmin($username) || isUserROAdmin($username)) {
    $return .= "<A HREF=\"editconfig.php\">Edit Configuration</A><BR>\n";
    $return .= "<A HREF=\"usercontrol.php\">Edit User Accounts</A><BR>\n";
    $return .= "<A HREF=\"viewlog.php\">Show Activity Log</A><BR>\n";
  }
  if(!isUserRO($username)) {
    $return .= "<A HREF=\"import.php\">Import Zones</A><BR>\n";
    $return .= "<A HREF=\"search.php?ACTION=REPLACE\">Search & Replace</A><BR>\n";
  }
  
  return $return;
}


function getZones($username,$ip) {
  global $config;
  global $db;
  if (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "CHANGESTATUS" && userAuthToEdit($username,$_POST['ZONEID'],$_POST['ZONETYPE'])) {
    $query = "UPDATE ZONES SET ZONESTATUSID=".$_POST['ZONESTATUS']." WHERE ZONEID=".$_POST['ZONEID'];
    $result = mysql_query($query,$db);
    logMessage($username,$ip,"CHANGESTATUS","Zone ".$_POST['ZONE']." status changed to ".$_POST['ZONESTATUS']);
  }
  else if(!is_null($_POST['ACTION']) && $_POST['ACTION'] == "CHANGESTATUS") {
    echo "<H1><FONT COLOR=\"red\">You do not have permission to edit zone ".$_POST['ZONE'].".</FONT></H1>";
    logMessage($username,$ip,"NOPRIVILEDGES","Unable to change status on zone ".$_POST['ZONE']);
  }
  if (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "DELETEZONE" && userAuthToEdit($username,$_POST['ZONEID'],$_POST['ZONETYPE'])) {
    $query = "DELETE FROM RECORDS WHERE ZONEID=".$_POST['ZONEID'];
    $result = mysql_query($query,$db);
    $query = "DELETE FROM PRIMARYUSERREF WHERE ZONEID=".$_POST['ZONEID'];
    $result = mysql_query($query,$db);
    $query = "DELETE FROM SECONDARYUSERREF WHERE ZONEID=".$_POST['ZONEID'];
    $result = mysql_query($query,$db);
    $query = "DELETE FROM PRIMARYIP WHERE ZONEID=".$_POST['ZONEID'];
    $result = mysql_query($query,$db);
    $query = "DELETE FROM ZONEOPTIONS WHERE ZONEID=".$_POST['ZONEID'];
    $result = mysql_query($query,$db);
    $query = "DELETE FROM ZONES WHERE ZONEID=".$_POST['ZONEID'];
    $result = mysql_query($query,$db);
    logMessage($username,$ip,"DELDOMAIN","Delete succeeded for zone ".$_POST['ZONE']);
    
  }
  else if (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "DELETEZONE") {
    echo "<H1><FONT COLOR=\"red\">You do not have permission to delete zone ".$_POST['ZONE'].".</FONT></H1>";
    logMessage($username,$ip,"NOPRIVILEDGES","Unable to delete zone ".$_POST['ZONE']);
  }
  $page = $_GET['PAGE'];
  if(is_null($page)) {
  	$page = $_POST['PAGE'];
	if(is_null($page)) {
		$page=0;
	}
  }
  
  $rowCount = $config['LISTINGRECORDS'];
  if($rowCount == "") {
  	$rowCount = 0;
  }
  $offset = $config['LISTINGRECORDS']*$page;
 
  
  $confirm = $config['CONFIRMDELETE'];
  $deleteScript="";
  if((strtoupper($confirm) == "YES") ||(strtoupper($confirm)=="USER" && isUser($username) )) {
    $deleteScript = "if(confirm(\"Delete zone $zone?\") { return true; } else { return false; }";
  }
 
  $query = "SELECT Z.ZONEID,Z.NAME ZNAME,ZS.NAME ZSNAME,\n";
  $query .= "ZT.NAME ZTNAME,ZS.ZONESTATUSID\n";
  $query .= " FROM ZONES Z, ZONESTATUS ZS, ZONETYPE ZT";
  if (isUser($username) || isROUser($username)) {
    $userId = getUserId($username);
    $secquery = "SELECT ZONEID FROM PRIMARYUSERREF WHERE USERID=$userId";
    $res = mysql_query($secquery,$db);
    $addquery =" AND Z.ZONEID IN (";
    $c = 0;
    while ($row = mysql_fetch_row($res)) {
      if($c != 0) { $addquery .= " , "; }
      $addquery .= $row[0];
      $c++;
    }
    $secquery = "SELECT ZONEID FROM SECONDARYUSERREF WHERE USERID=$userId";
    $res1 = mysql_query($secquery,$db);
    while ($row1 = mysql_fetch_row($res1)) {
      if($c != 0) { $addquery .= " , "; }
      $addquery .= $row1[0];
      $c++;
    }
    $addquery .=")\n";

  }
  else { $addquery = ""; }
  $query .= " WHERE \n";
  $query .= " Z.ZONESTATUSID=ZS.ZONESTATUSID\n";
  $query .= " AND Z.ZONETYPEID=ZT.ZONETYPEID\n";
  $query .= $addquery;
  $query .= " ORDER BY Z.NAME,Z.ZONETYPEID";
  if($rowCount !=0 ) {
  	$query .= " LIMIT $offset,$rowCount\n";
  }
  $result = mysql_query($query,$db);
  $numRows = mysql_num_rows($result);
  $rowsPerCol = intval(($numRows /2)+.5);
  $totRecords = "SELECT COUNT(ZONEID) FROM ZONES WHERE ZONEID > 0\n";
  $countRes = mysql_query($totRecords,$db);
  $total = mysql_fetch_row($countRes);
  $total = $total[0];
  if($rowCount != 0) {
  $totalPages = intval(($total / $rowCount));
  }
  else {
  $totalPages = 0;
  }
  $count = 0;
  $output = "Page: ";
  for($i=0; $i <=$totalPages;$i++) {
  	if($i != $page) {
		$output .= "<A HREF=mainpage.php?PAGE=$i>$i</A>";
	}
	else {
		$output .= "$i";
	}
	if($i != $totalPages) {
		$output .=",";
	}
  }

  $output .= "<TABLE WIDTH=\"100%\"><TR><TD WIDTH=\"50%\">";
  $output .= "<TABLE BORDER=1 WIDTH=100%>\n";
  $output .= "<TR><TD WIDTH=\"25%\">Domain</TD><TD WIDTH=\"25%\">Status</TD><TD WIDTH=\"25%\">Zone Type</TD><TD WIDTH=\"25%\">&nbsp;</TD></TR>\n";
  while($myrow=mysql_fetch_row($result)) {
    $deleteScript="";
    if((strtoupper($confirm) == "YES") ||(strtoupper($confirm)=="USER" && isUser($username) )) {
      $deleteScript = "var agree=confirm('Delete zone $myrow[1]?'); if(agree) { return true; } else { return false; }";
    }
	    
    if($count == $rowsPerCol) {
      $output .= "</TABLE></TD><TD>\n";
      $output .= "<TABLE BORDER=1 WIDTH=100%>\n";
      $output .= "<TR><TD WIDTH=\"25%\">Domain</TD><TD WIDTH=\"25%\">Status</TD><TD WIDTH=\"25%\">Zone Type</TD><TD WIDTH=\"25%\">&nbsp;</TD></TR>\n";
    }
    if(userAuthToView($username,$myrow[0],$myrow[3])) {
      
      $output .= "<TR WIDTH=\"25%\"><TD><A HREF=\"editzone.php?ZONEID=$myrow[0]\">$myrow[1]</A> (<A HREF=\"editzone.php?ZONEID=$myrow[0]&ACTION=EDITOPTION\">Options</A>)</TD>\n";
      $output .= "<TD WIDTH=\"25%\"><FORM NAME=\"MOD$myrow[0]\" ACTION=\"mainpage.php\" METHOD=\"POST\">".getZoneStatusSelect($myrow[4],"this.form.submit();");
      $output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"CHANGESTATUS\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$myrow[0]\">";
      $output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$myrow[1]\">\n";
      $output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONETYPE\" VALUE=\"$myrow[3]\"></FORM>\n</TD>\n";
      $output .= "<TD WIDTH=\"25%\">$myrow[3]</TD>\n";
      $output .= "<TD WIDTH=\"25%\"><FORM NAME=\"DEL$myrow[0]\" ACTION=\"mainpage.php\" METHOD=\"POST\">\n";
      $output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"DELETEZONE\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$myrow[0]\">";
      $output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$myrow[1]\">\n";
      $output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONETYPE\" VALUE=\"$myrow[3]\"><INPUT TYPE=\"SUBMIT\" NAME=\"DELETE\" VALUE=\"Delete Zone\" onClick=\"$deleteScript\"></FORM></TD></TR>\n";
    }
    $count++;
  }
  $output .= "</TABLE></TD></TR></TABLE><BR>\n";
  
  return $output;
}
?>

<?php
/*
File: search.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
		Code to search for domains.
*/
session_start();
if($_POST['ACTION'] == "REPLACE" || $_GET['ACTION'] == "REPLACE") {
  $title = "Search & Replace";
}
else {
  $title = "Domain Search";
}
include('dns.inc.php');

$user = $_SESSION['username'];
$cryptpass = $_SESSION['cryptpass'];
$ip=$_SERVER['REMOTE_ADDR'];

if(verify($user,$cryptpass) && !checktimeout($_SESSION['time'])) {
  global $config;
  $time = time();
  $_SESSION['time'] = $time;
  $action = $_GET['ACTION'];
  if(is_null($action)) {
    $action = $_POST['ACTION'];
  }
  if($action == "REPLACE") {
    if($_POST['SAVE'] == "Replace") {
	if(isUserAdmin($user) && !isUserRO($user) ) {  
	  $oldIP = $_POST['OLDIP'];
	  $newIP = $_POST['NEWIP'];
	  $query = "UPDATE RECORDS SET VALUE = '$newIP' WHERE VALUE='$oldIP'";
	  $result = mysql_query($query,$db);
	  $count = mysql_affected_rows();
	  logMessage($user,$ip,'MODRECORD',"Updated $count primary records from $oldIP to $newIP"); 
	  $output = "Updated $count primary records from $oldIP to $newIP<br>";
	  if(!is_null($_POST['INCLUDESEC'])) {
	    $query = "UPDATE PRIMARYIP SET IP='$newIP' WHERE IP='$oldIP'";
	    $result = mysql_query($query,$db);
	    $count = mysql_affected_rows();
	    logMessage($user,$ip,'UPDATESECONDARY',"Updated $count secondary records from $oldIP to $newIP");
	    $output .= "Updated $count secondary records from $oldIP to $newIP<br>";
	  }
	}
	else if(isUser($user) && !isUserRo($user)) {
	  $oldIP = $_POST['OLDIP'];
	  $newIP = $_POST['NEWIP'];
	  $userId = getUserId($user);
	  $query = "SELECT ZONEID FROM PRIMARYUSERREF WHERE USERID=$userId";
	  $res = mysql_query($query,$db);
	  $zoneIds = "";
	  $count = 0;
	  while($row = mysql_fetch_row($res)) {
	    if($count != 0) {
	      $zoneIds .= ",";
	    }
	    $zoneIds .= $row[0];
	    $count++;
	  }
	  $query = "UPDATE RECORDS SET VALUE='$newIP' WHERE VALUE='$oldIP' AND ZONEID IN ($zoneIds)";
	  $res = mysql_query($query,$db);
	  $count = mysql_affected_rows();
	  logMessage($user,$ip,'MODRECORD',"Updated $count primary records from $oldIP to $newIP");
	  $output = "Updated $count primary records from $oldIP to $newIP<br>";
	  if(!is_null($_POST['INCLUDESEC'])) {
  	    $query = "SELECT ZONEID FROM SECONDARYUSERREF WHERE USERID=$userId";
	    $res = mysql_query($query,$db);
	    $zoneIds = "";
	    $count = 0;
	    while($row = mysql_fetch_row($res)) {
	      if($count != 0) {
	        $zoneIds .= ",";
	      }
	      $zoneIds .= $row[0];
	      $count++;
	    }
	    $query = "UPDATE PRIMARYIP SET IP='$newIP' WHERE IP='$oldIP' AND ZONEID IN ($zoneIds)";
	    $res = mysql_query($query,$db);
	    $count = mysql_affected_rows();
	    logMessage($user,$ip,'UPDATESECONDARY',"Updated $count secondary records from $oldIP to $newIP");
	    $output .= "Updated $count secondary records from $oldIP to $newIP<br>";
	  }
	}
    }
    else {
      $clickJS = "if(form.elements['NEWIP'].value.lenth ==0) { alert('Please Ensure you Enter at least one Character'); return false;";
      $clickJS .= "} if(form.elements['OLDIP'].value.length ==0) { alert('Please Ensure you Eneter at leat one Character'); ";
      $clickJS .= "return false; } else { if (!this.submitted) { this.submitted = true; return true; } else return false; } }";
      $output = "<H1>Search & Replace</H1>\n";
      $output .= "<FORM ACTION=\"search.php\" METHOD=\"POST\"><TABLE><TR><TD>Old IP</TD><TD>NewIP</TD></TR>\n";
      $output .= "<TR><TD><INPUT TYPE=\"TEXT\" NAME=\"OLDIP\" VALUE=\"\"></TD><TD><INPUT TYPE=\"TEXT\" NAME=\"NEWIP\" VALUE=\"\"></TD></TR>\n";
      $output .= "<TR><TD><INPUT TYPE=\"CHECKBOX\" NAME=\"INCLUDESEC\"> Include Secondaries?</TD><TD><INPUT TYPE=\"SUBMIT\" NAME=\"SAVE\" VALUE=\"Replace\" onClick=\"$clickJS\"></TD></TR></TABLE><INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"REPLACE\"></TABLE>\n";
    }
  }
  else {
    //Setup query
    if (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "CHANGESTATUS" && userAuthToEdit($user,$_POST['ZONEID'],$_POST['ZONETYPE'])) {
      $query = "UPDATE ZONES SET ZONESTATUSID=".$_POST['ZONESTATUS']." WHERE ZONEID=".$_POST['ZONEID'];
      $result = mysql_query($query,$db);
      logMessage($user,$ip,"CHANGESTATUS","Zone ".$_POST['ZONE']." status changed to ".$_POST['ZONESTATUS']);
    }
    else if(!is_null($_POST['ACTION']) && $_POST['ACTION'] == "CHANGESTATUS") {
      echo "You do not have permission to edit zone ".$_POST['ZONE'].".<BR>";
      logMessage($user,$ip,"NOPRIVILEDGES","Unable to change status on zone ".$_POST['ZONE']);
    }
    if (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "DELETEZONE" && userAuthToEdit($user,$_POST['ZONEID'],$_POST['ZONETYPE'])) {
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
      logMessage($user,$ip,"DELDOMAIN","Delete succeeded for zone ".$_POST['ZONE']);
      
    }
    else if (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "DELETEZONE") {
      echo "You do not have permission to delete zone ".$_POST['ZONE'].".<BR>";
      logMessage($user,$ip,"NOPRIVILEDGES","Unable to delete zone ".$_POST['ZONE']);
    }
    
    $SEARCHVALUE =$_POST['SEARCHVALUE'];
    $results = false;
    $query = "SELECT Z.ZONEID,Z.NAME ZNAME,ZS.NAME ZSNAME,\n";
    $query .= "ZT.NAME ZTNAME,ZS.ZONESTATUSID\n";
    $query .= " FROM ZONES Z, ZONESTATUS ZS, ZONETYPE ZT WHERE Z.NAME LIKE '%".$SEARCHVALUE."%'\n";
    $query .= " AND Z.ZONESTATUSID=ZS.ZONESTATUSID\n";
    $query .= " AND Z.ZONETYPEID=ZT.ZONETYPEID\n";
    $query .= " ORDER BY Z.NAME";
    //Perform query
    $result = mysql_query($query,$db);
    
    //Setup output's
    $output = "";
    $output .= "<TABLE BORDER=1 WIDTH=100%>\n";
    $output .= "<TR><TD WIDTH=\"25%\">Domain</TD><TD WIDTH=\"25%\">Status</TD><TD WIDTH=\"25%\">Zone Type</TD><TD WIDTH=\"25%\">&nbsp;</TD></TR>\n";
    $confirm = $config['CONFIRMDELETE']; 
    while ($myrow = mysql_fetch_row($result)) {
      $deleteScript="";
      if((strtoupper($confirm) == "YES") ||(strtoupper($confirm)=="USER" && isUser($username) )) {
        $deleteScript = "var agree=confirm('Delete zone $myrow[1]?'); if(agree) { return true; } else { return false; }";
      }
		    
      if(userAuthToView($user,$myrow[0],$myrow[3])) {
	$results = true;
	$output .= "<TR WIDTH=\"25%\"><TD><A HREF=\"editzone.php?ZONEID=$myrow[0]\">$myrow[1]</A> (<A HREF=\"editzone.php?ZONEID=$myrow[0]&ACTION=EDITOPTION\">Options</a>)</TD>\n";
	$output .= "<TD WIDTH=\"25%\"><FORM NAME=\"MOD$myrow[0]\" ACTION=\"search.php\" METHOD=\"POST\">".getZoneStatusSelect($myrow[4],"this.form.submit();");
	$output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"CHANGESTATUS\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$myrow[0]\">";
	$output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"SEARCHVALUE\" VALUE=\"".$_POST['SEARCHVALUE']."\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$myrow[1]\">\n";
	$output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONETYPE\" VALUE=\"$myrow[3]\"></FORM>\n</TD>\n";
	$output .= "<TD WIDTH=\"25%\">$myrow[3]</TD>\n";
	$output .= "<TD WIDTH=\"25%\"><FORM NAME=\"DEL$myrow[0]\" ACTION=\"search.php\" METHOD=\"POST\">\n";
	$output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"DELETEZONE\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$myrow[0]\">";
	$output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"SEARCHVALUE\" VALUE=\"".$_POST['SEARCHVALUE']."\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$myrow[1]\">\n";
	$output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONETYPE\" VALUE=\"$myrow[3]\"><INPUT TYPE=\"SUBMIT\" NAME=\"DELETE\" VALUE=\"Delete Zone\" onClick=\"$deleteScript\"></FORM></TD></TR>\n";
      }
    }
    if(!$results) {
      $output = "<H1>No Results</H1>\n";
    } else {
      $output .= "</TABLE>\n";
    }
  }
  echo $output;
  echo "<a href=mainpage.php>Back to Main</a>\n";
  logout();
  closedoc();
}  
//Either we've timed out or have a bad user and pass
else {
  $_SESSION['username']="";
  $_SESSION['cryptpass']="";
  //Setup the title and grab the login form.
  echo (checktimeout($_SESSION['time']) ? "<H1>Session Timeout</H1>\n" : "<H1>Invalid Username or Password</H1>\n");
  create_login();
  closedoc();
}


mysql_close($db);
?>

<?php
/*
File: editzone.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
	This file contains the code for editing and creating zones.
*/
session_start();
if($_POST['ACTION'] == "NEWZONE" || $_GET['ACTION'] == "NEWZONE") {
  $title = "Create Zone";
}
else {
  $title = "Edit Zone";
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
  if(!is_null($_POST['DELETE']) && $_POST['DELETE'] == "Delete" && userAuthToEdit($user,$_POST['ZONEID'],$_POST['ZONETYPE'])) {
    $query = "DELETE FROM RECORDS WHERE RECORDID=".$_POST["RECORDID"];
    $result =  mysql_query($query,$db);
    logMessage($user,$ip,"DELRECORD","Record ".$_POST['RECORD']." deleted from zone ".$_POST['ZONE']);
    zoneUpdated($_POST['ZONEID']);
    getPage($user);
    echo "<BR>Success Record Deleted<BR>\n";
  }
  else if(((!is_null($_GET['ACTION']) && $_GET['ACTION'] == "EDITOPTION") || (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "EDITOPTION")) && !isUserRO($user)) {
    if(!is_null($_POST['UPDATE']) && $_POST['UPDATE']="Save") {
      if((!is_null($_POST['ALSONOTIFY']) && $_POST['ALSONOTIFY'] != "") || 
	 (!is_null($_POST['OUTPUTPATH']) && $_POST['OUTPUTPATH'] != "")) {
	$query = "DELETE FROM ZONEOPTIONS WHERE ZONEID=".$_POST['ZONEID']." AND ZONEOPTIONTYPEID IN (2,3)";
	mysql_query($query,$db);
	if(!is_null($_POST['ALSONOTIFY']) && $_POST['ALSONOTIFY'] != "") {
	  $allowUpdates = split(',',$_POST['ALSONOTIFY']);
	  for($i =0;$i < count($allowUpdates);$i++) {
	    $query = "INSERT INTO ZONEOPTIONS (ZONEID,ZONEOPTIONTYPEID,VALUE) VALUES "
	      ."(".$_POST['ZONEID'].",2,'".mysql_escape_string($allowUpdates[$i])."')";
	    mysql_query($query,$db);
	  }
	}
	if(!is_null($_POST['OUTPUTPATH']) && $_POST['OUTPUTPATH'] != "") {
	  $path = $_POST['OUTPUTPATH'];
	  if(substr($path,-1) != "/") {
	    $path = $path ."/";
	  }
	  $query = "INSERT INTO ZONEOPTIONS (ZONEID,ZONEOPTIONTYPEID,VALUE) VALUES "
	    ."(".$_POST['ZONEID'].",3,'".mysql_escape_string($path)."')";
	  mysql_query($query,$db);
	}
	logMessage($user,$ip,"MODZONEOPTION","Updated Zone Options for Zone ".$_POST['ZONE']);
	zoneUpdated($_POST['ZONEID']);
	getOptionPage($user);
	echo "<BR>Success Updated Zone Options.<BR>";
      }
      else {
	getOptionPage($user);
	echo "<BR>Failed Please Specify Either a Path or IP<BR>";
      }
      
    }
    else {
      getOptionPage($user);
      
    }
    backLink();
  }
  else if(((!is_null($_GET['ACTION']) && $_GET['ACTION'] == "NEWZONE") || (!is_null($_POST['ACTION']) && $_POST['ACTION'] == "NEWZONE")) && !isUserRO($user)) {
    if(!is_null($_POST['CREATE']) && $_POST['CREATE'] == "Create") {
      $domainName = $_POST['NEWZONE'];
      $query = "INSERT INTO ZONES (ZONEID,ZONETYPEID,ZONESTATUSID,NAME,UPDATED) VALUES (";
      $query .= "'',".$_POST['ZONETYPE'].",1,'".mysql_escape_string($_POST['NEWZONE'])."',1)";
      $result = null;
      $result = mysql_query($query,$db);
      if($result) {
      $zoneid = mysql_insert_id();
      $userid = getUserId($user);
      if(isUser($user)) {
	if($_POST['ZONETYPE'] == 1 || $_POST['ZONETYPE'] == 3) {
	  $query = "INSERT INTO PRIMARYUSERREF (ZONEID,USERID) VALUES (".$zoneid.",".$userid.")";
	  mysql_query($query,$db);
	}
	else {
	  $query = "INSERT INTO SECONDARYUSERREF (ZONEID,USERID) VALUES (".$zoneid.",".$userid.")";
	  mysql_query($query,$db);
	}
      }
      if($_POST['ZONETYPE'] == 2) {
	//Possibly include Support for more then one Primary IP?????
	//$primaryIP = split(',',$_POST['PRIMARYIP']);
	//for($i =0;$i < count($primaryIP);$i++) {
	$query = "INSERT INTO PRIMARYIP (ZONEID,IP) VALUES (".$zoneid.",'".mysql_escape_string($_POST['PRIMARYIP'])."')";
	mysql_query($query,$db);
	//}
      }
      if($_POST['ZONETYPE'] == 3) {
	$allowUpdates = split(',',$_POST['ALLOWUPDATES']);
	for($i =0;$i < count($allowUpdates);$i++) {
	  $query = "INSERT INTO ZONEOPTIONS (ZONEID,ZONEOPTIONTYPEID,VALUE) VALUES "
	    ."(".$zoneid.",1,'".mysql_escape_string($allowUpdates[$i])."')";
	  mysql_query($query,$db);
	}
      }
      if(!is_null($_POST['ALSONOTIFY']) && $_POST['ALSONOTIFY'] != "Additional Notified Servers") {
	$alsoNotify = split(',',$_POST['ALSONOTIFY']);
	for($i =0;$i < count($alsoNotify);$i++) {
	  $query = "INSERT INTO ZONEOPTIONS (ZONEID,ZONEOPTIONTYPEID,VALUE) VALUES "
	    ."(".$zoneid.",2,'".mysql_escape_string($alsoNotify[$i])."')";
	  mysql_query($query,$db);
	}
      }
      if($_POST['SOURCEZONE'] != 'NULL') {
	if(!is_null($_POST['REPLACE'])) {
	  $replace = true;
	}
	else {
	  $replace = false;
	}
	$sourceZoneId = $_POST['SOURCEZONE'];
	$query = "SELECT RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE FROM RECORDS WHERE ZONEID=".$sourceZoneId;
	$result = mysql_query($query,$db);
	while($myrow = mysql_fetch_row($result)) {
	  $typeId=$myrow[0];
	  $record=$myrow[1];
	  $ttl=$myrow[2];
	  $mxpri=$myrow[3];
	  $val=$myrow[4];
	  if($replace) {
	    $sourceZoneName=getZoneName($sourceZoneId);
	    $record=str_replace($sourceZoneName,$_POST['NEWZONE'],$record);
	    $val=str_replace($sourceZoneName,$_POST['NEWZONE'],$val);
	  }
	  if($mxpri == '') {
	    $mxpri="NULL";
	  }
	  $query = "INSERT INTO RECORDS (ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE) VALUES (";
	  $query .= "$zoneid,'$user',$typeId,'$record',$ttl,$mxpri,'$val')";
	  $stuff=mysql_query($query,$db);
	}
      }
      else {
      global $config;
      if(strtolower($config['AUTOADDSOA']) == 'yes') {
	$serial = date($config['SERIALSTRING']);
	$query = "INSERT INTO RECORDS (ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE) VALUES "
	  . "($zoneid,'$user',8,'@','".$config['MINIMUM']."',NULL,'$serial,".$config['REFRESH'].",".$config['RETRY'].",".$config['EXPIRE'].","
	  .$config['MINIMUM']."')";
	mysql_query($query,$db);
      }
      }
      $_POST['ZONEID'] = $zoneid;
      logMessage($user,$ip,"ADDDOMAIN","Added Domain ".mysql_escape_string($_POST['NEWZONE']));    
      getPage($user);
      echo "<BR>Success Added Zone<BR>";
      }
      else {
      $query = "SELECT ZONEID FROM ZONES WHERE NAME='".mysql_escape_string($_POST['NEWZONE'])."'";
      $res = mysql_query($query,$db);
      $row = mysql_fetch_row($res);
      $zoneid = $row[0];
      $_POST['ZONEID'] = $zoneid;
      $userid = getUserId($user);
			      
      getPage($user);
      echo "<BR>Failed Zone Exists<BR>";
      }
    }
    else {
      echo getCreatePage($user);
      backLink();
    }
    
  }
  else if(!is_null($_POST['SAVE']) && $_POST['SAVE'] == "Save" && userAuthToEdit($user,$_POST['ZONEID'],$_POST['ZONETYPE'])) {
    if(!is_null($_POST['ACTION']) && $_POST['ACTION'] =="UPDATESEC") {
      if($_POST['PRIMIP'] != "") {
	$query = "UPDATE PRIMARYIP SET IP='".mysql_escape_string($_POST['PRIMIP'])."' WHERE ZONEID=".$_POST['ZONEID'];
	$result = mysql_query($query,$db);
	logMessage($user,$ip,"UPDATESECONDARY","Primary IP for zone ".$_POST['ZONE']." updated to ".mysql_escape_string($_POST['PRIMIP']));
	
	zoneUpdated($_POST['ZONEID']);
	getPage($user);
	echo "<BR>Success Updated Primary Server IP<BR>";
      }
      else {
	getPage($user);
	echo "<BR>Failed Please Specify an IP<BR>";
      }
    } 
    else if (!is_null($_POST['ACTION']) && $_POST['ACTION'] =="UPDATEDYN") {
      if(!is_null($_POST['ALLOWUPDATES']) && $_POST['ALLOWUPDATES'] != "") {
	$query = "DELETE FROM ZONEOPTIONS WHERE ZONEID=".$_POST['ZONEID']." AND ZONEOPTIONTYPEID=1";
	mysql_query($query,$db);
	$allowUpdates = split(',',$_POST['ALLOWUPDATES']);
	for($i =0;$i < count($allowUpdates);$i++) {
	  $query = "INSERT INTO ZONEOPTIONS (ZONEID,ZONEOPTIONTYPEID,VALUE) VALUES "
	    ."(".$_POST['ZONEID'].",1,'".mysql_escape_string($allowUpdates[$i])."')";
	  mysql_query($query,$db);
	}
	zoneUpdated($_POST['ZONEID']);
	logMessage($user,$ip,"MODZONEOPTION","Updated Zone Option Allow Updates for Zone ".$_POST['ZONE']);
	getPage($user);
	echo "<BR>Success Updated Allowable Update Hosts.<BR>";
      }
      else {
	getPage($user);
	echo "<BR>Failed Please Specify an IP<BR>";
      }
    }
    else {
	$records = substr($_POST['RECORDS'],0,strlen($POST['RECORDS'])-1);
	$recordArray = split(',',$records);
	
	for( $k=0; $k < count($recordArray); $k++) {
	$record = $recordArray[$k];
	if($_POST[$record.'-RECORD'] == "") {
		$_POST[$record.'-RECORD'] = "@";
      	 }
	
	$ttl = intval($_POST[$record.'-TTL']);
	$mx = intval($_POST[$record.'-MXPRIORITY']);
	if($ttl == 0) {
	  $ttl = "NULL";
	}
	if($mx == 0) {
	  $mx = "NULL";
	}

	
	$query = "UPDATE RECORDS SET RECORD=\"".mysql_escape_string($_POST[$record.'-RECORD'])."\",TTL=$ttl,".
	  "MXPRIORITY=$mx,VALUE=\"".mysql_escape_string($_POST[$record.'-VALUE'])."\",MODUSER=\"$user\" WHERE RECORDID=".$record."\n";
	$result = mysql_query($query,$db);
	zoneUpdated($_POST['ZONEID']);
	logMessage($user,$ip,"MODRECORD","Record ".mysql_escape_string($_POST[$record.'-RECORD'])." modified to ".mysql_escape_string($_POST[$record.'-VALUE']." for zone ".$_POST['ZONE']));
	}
	getPage($user);
	
	echo "<BR>Success Changes Saved<BR>\n";
    }
  }
  else if(!is_null($_POST['NEW']) && $_POST['NEW'] == "New Record" && userAuthToEdit($user,$_POST['ZONEID'],$_POST['ZONETYPE'])) {
    if($_POST['RECORD'] == "") {
      $_POST['RECORD'] = "@";
    }
    if(is_null($_POST['RECORD']) || $_POST['RECORD'] == "" ||
       is_null($_POST['VALUE']) || $_POST['VALUE'] == ""
       ) {
      getPage($user);
    }
    else {
      $ttl = intval($_POST['TTL']);
      $mx = intval($_POST['MXPRIORITY']);
      if($ttl == 0) {
	$ttl = "NULL";
      }
      if($mx == 0) {
	$mx = "NULL";
      }
      $query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE)".
      		"VALUES ('',".$_POST['ZONEID'].",'".$user."',".
		$_POST['RECORDTYPE'].",\"".mysql_escape_string($_POST['RECORD'])."\",$ttl,$mx,\"".
		mysql_escape_string($_POST['VALUE'])."\")";

      $result = mysql_query($query,$db);
      zoneUpdated($_POST['ZONEID']);
      logMessage($user,$ip,"ADDRECORD","Record ".mysql_escape_string($_POST['RECORD'])." added to zone ".$_POST['ZONE']);
      getPage($user);
      echo "<BR>Success Record Added<BR>\n";
    }
  }
  else {
    getPage($user);
    if((!is_null($_POST['NEW']) && $_POST['NEW'] == "New Record") ||
       (!is_null($_POST['SAVE']) && $_POST['SAVE'] == "Save") ||
       (!is_null($_POST['DELETE']) && $_POST['DELETE'] == "Delete") ) {
      echo "<BR>You do not have permission to edit this zone.<br>\n";
      logMessage($user,$ip,"NOPRIVILEDGES","Unable to edit zone ".$_POST['ZONE']);
    }
  }
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

function getOptionPage($username) {
  global $db;
  $zoneid=$_GET['ZONEID'];
  if(is_null($zoneid)) {
    $zoneid = $_POST['ZONEID'];
  }
  $query = "SELECT VALUE FROM ZONEOPTIONS WHERE ZONEOPTIONTYPEID=2 AND ZONEID=".$zoneid;
  $result = mysql_query($query,$db);
  $zone = getZoneName($zoneid);
  $count =0;
  while($row = mysql_fetch_row($result)) {
    if($count == 0) {
      $string .="$row[0]";
    }
    else {
      $string .= ",$row[0]";
    }
    $count++;
  }
  $query = "SELECT VALUE FROM ZONEOPTIONS WHERE ZONEOPTIONTYPEID=3 AND ZONEID=".$zoneid;
  $result = mysql_query($query,$db);
  $count =0;
  $row = mysql_fetch_row($result);
  $path = $row[0];
  $res = mysql_query("SELECT NAME FROM ZONES WHERE ZONEID=".$zoneid,$db);
  $myrow = mysql_fetch_row($res);
  $output = "<H1>$myrow[0]</H1>\n<TABLE BORDER=\"1\">\n";
  $output .= "<TR><TD>Also Notify:</TD><TD>Output Path:</TD><TD>&nbsp;</TD></TR>";
  $output .= "<TR><TD><FORM NAME=\"DYN\" ACTION=\"editzone.php\" METHOD=\"POST\">\n".
    "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$zoneid\">\n".
    "<INPUT TYPE=\"TEXT\" NAME=\"ALSONOTIFY\" VALUE=\"$string\">\n".
    "</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"OUTPUTPATH\" VALUE=\"$path\">\n".
    "</TD><TD><INPUT TYPE=\"SUBMIT\" NAME=\"UPDATE\" VALUE=\"Save\">\n".
    "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$zone\">\n".
    "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"EDITOPTION\">\n".
    "</TD></TR></FORM></TABLE>\n";
  echo $output;
  
}
function getCreatePage($username) {
  global $db;
  $output = "<H1>Create Zone</H1>\n";
  $output .= "<FORM NAME=\"NEW\" ACTION=\"editzone.php\" METHOD=\"POST\"><TABLE BORDER=\"1\" ALLIGN=\"CENTER\" ><TR><TD ALIGN=\"RIGHT\">\n";
  $output .= "Domain Name:</TD><TD ALIGN=\"LEFT\"><INPUT TYPE=\"TEXT\" SIZE=\"50\" NAME=\"NEWZONE\" VALUE=\"\"></TD></TR>\n";
  $output .= "<TR><TD ALIGN=\"RIGHT\">Zone Type:</TD><TD ALIGN=\"LEFT\">".getZoneTypeSelect()."</TD>\n";
  $output .= "<TR><TD ALIGN=\"RIGHT\">Primary Server:</TD><TD ALIGN=\"LEFT\"><INPUT TYPE=\"TEXT\" NAME=\"PRIMARYIP\" SIZE=\"50\" VALUE=\"Only for Secondary Zones\"></TD></TR>\n";
  $output .= "<TR><TD ALIGN=\"RIGHT\">Allow Updates:</TD><TD ALIGN=\"LEFT\"><INPUT TYPE=\"TEXT\" NAME=\"ALLOWUPDATES\" SIZE=\"50\" VALUE=\"Only for Dynamic Zones\"></TD></TR>\n";
  $output .= "<TR><TD ALIGN=\"RIGHT\">Also Notify:</TD><TD ALIGN=\"LEFT\"><INPUT TYPE=\"TEXT\" NAME=\"ALSONOTIFY\" SIZE=\"50\" VALUE=\"Additional Notified Servers\"></TD></TR>\n";
  $output .= "<TR><TD ALIGN=\"RIGHT\">Source Zone:</TD><TD ALIGN=\"LEFT\">".getZoneSelect("SOURCEZONE",true)."&nbsp;Replace? <INPUT TYPE=\"CHECKBOX\" NAME=\"REPLACE\" onMouseOver=\"window.status='Replace instances of source with new zone';\" onMouseOut=\"window.status=''\"} return true;\"></TD></TR>\n";
  
  $output .= "<TR><TD ALIGN=\"RIGHT\">&nbsp;</TD><TD ALIGN=\"LEFT\"><INPUT TYPE=\"SUBMIT\" NAME=\"CREATE\" VALUE=\"Create\" onClick=\"".getCreateSubmitJS()."\"></TD></TR>\n";
  $output .= "</TABLE>\n";
  $output .= "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"NEWZONE\"></FORM>\n";
  return $output;
  
}

function getCreateSubmitJS() {
  $output = "if(form.elements['NEWZONE'].value.length != 0 ) { ";
  $output .= "if(form.elements['ZONETYPE'].value == '2' && (form.elements['PRIMARYIP'].value.length < 7 || form.elements['PRIMARYIP'].value.length > 15 || form.elements['PRIMARYIP'].value == 'Only for Secondary Zones')) { ";
  $output .= "alert('Please Provide a Primary Server IP.'); return false; } ";
  $output .= "if(form.elements['ZONETYPE'].value == '3' && (form.elements['ALLOWUPDATES'].value.length == 0 || form.elements['ALLOWUPDATES'].value == 'Only for Dynamic Zones')) { ";
  $output .= "alert('Please Provide a List of Servers Allowed to Update This Zone'); return false; } ";
  $output .= "if (!this.submitted) { this.submitted = true; return true; } ";
  $output .= "else return false; }";
  $output .= "else { alert('Please Enter a Domain Name to Add'); return false; }";
  
  return $output;
}
function getPage($user) {
  global $db;
  global $config;
  $confirm = $config['CONFIRMDELETE'];
  //Setup query
  $zoneid = $_GET['ZONEID'];
  if(is_null($zoneid)) {
    $zoneid = $_POST['ZONEID'];
  }
  $query = "SELECT Z.NAME,Z.ZONETYPEID,ZT.NAME ZTNAME FROM ZONES Z, ZONETYPE ZT WHERE ZONEID=$zoneid";
  $query .= " AND ZT.ZONETYPEID=Z.ZONETYPEID";
  $result = mysql_query($query,$db);
  $myrow = mysql_fetch_row($result);
	$zonetype = $myrow[2];
	$zone = $myrow[0];
		      
	if($zonetype == "Primary") {
	  $sortColumn = $_GET['SORTCOLUMN'];
	  if(is_null($sortColumn)) {
	  	$sortColumn=$_POST['SORTCOLUMN'];
	  }
	  $order=$_GET['ORDER'];
	  if(is_null($order)) { 
	  $order=$_POST['ORDER']; 
	  }
												      
	  //Setup Output Table
	  $output = "<H1>$myrow[0]</H1>\n<TABLE BORDER=\"1\">\n";
	  $output .= "<FORM NAME=\"EDITZONE\" ACTION=\"editzone.php\" METHOD=\"POST\">\n";
	  
	  $output .="<INPUT TYPE=\"HIDDEN\" NAME=\"SORTCOLUMN\" VALUE=\"$sortColumn\">\n";
	  $output .="<INPUT TYPE=\"HIDDEN\" NAME=\"ORDER\" VALUE=\"$order\">\n";
	  $output .= "<TR><TD>Record&nbsp;<a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=4&ORDER=ASC\"><IMG SRC=\"images/up.gif\" BORDER=\"0\"></a><a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=4&ORDER=DESC\"><IMG SRC=\"images/down.gif\" BORDER=\"0\"></a></TD>\n";
	  $output .= "<TD>TTL&nbsp;<a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=5&ORDER=ASC\"><IMG SRC=\"images/up.gif\" BORDER=\"0\"></a><a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=5&ORDER=DESC\"><IMG SRC=\"images/down.gif\" BORDER=\"0\"></a></TD>\n";
	  $output .= "<TD>Type&nbsp;<a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=3&ORDER=ASC\"><IMG SRC=\"images/up.gif\" BORDER=\"0\"></a><a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=3&ORDER=DESC\"><IMG SRC=\"images/down.gif\" BORDER=\"0\"></a></TD>\n";
	  $output .= "<TD>MX Priority&nbsp;<a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=6&ORDER=ASC\"><IMG SRC=\"images/up.gif\" BORDER=\"0\"></a><a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=6&ORDER=DESC\"><IMG SRC=\"images/down.gif\" BORDER=\"0\"></a></TD>\n";
	  $output .= "<TD>Value&nbsp;<a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=7&ORDER=ASC\"><IMG SRC=\"images/up.gif\" BORDER=\"0\"></a><a href=\"editzone.php?ZONEID=$zoneid&SORTCOLUMN=7&ORDER=DESC\"><IMG SRC=\"images/down.gif\" BORDER=\"0\"></a></TD><TD>&nbsp;</TD></TR>\n";	
	  //Perform query
	  $query = "SELECT R.RECORDID, R.ZONEID, R.RECORDTYPEID,R.RECORD,R.TTL,R.MXPRIORITY,R.VALUE,Z.NAME ZNAME,RT.NAME RTNAME\n";
	  $query .= " FROM RECORDS R,ZONES Z, RECORDTYPE RT WHERE R.ZONEID=$zoneid AND Z.ZONEID=R.ZONEID AND R.RECORDTYPEID=RT.RECORDTYPEID\n";
	  if(is_null($sortColumn) || $sortColumn =="" || $sortColumn=="3") {
		 $query .= "ORDER BY RT.SORTORDER $order ,R.RECORD,R.MXPRIORITY \n";
	  }
	  else {
	  	$query .= "ORDER BY $sortColumn $order,4,5 \n";
	  }
	  $result = mysql_query($query,$db);
	  if(userAuthToView($user,$zoneid,$zonetype)) {
	    while($myrow = mysql_fetch_row($result)) {
              $deleteScript="";
              if((strtoupper($confirm) == "YES") ||(strtoupper($confirm)=="USER" && isUser($username) )) {
                $deleteScript = "var agree=confirm('Delete Record $myrow[3]:$myrow[8]:$myrow[6]?'); if(agree) { form.elements['RECORDID'].value=$myrow[0]; return true; } else { return false; }";
              }
	      else {
	      	$deleteScript = "form.elements['RECORDID'].value=$myrow[0];";
	      }
	      $record .= $myrow[0].",";	      
	      $recordId = $myrow[0];
	      $zone=$myrow[7];
	      
	      $output .="<TR><TD><INPUT NAME=\"$recordId-RECORD\" VALUE=\"$myrow[3]\" TYPE=\"TEXT\"></TD>\n".
		"<TD><INPUT NAME=\"$recordId-TTL\" VALUE=\"$myrow[4]\" TYPE=\"TEXT\"></TD>\n".
		"<TD>$myrow[8]</TD><TD><INPUT NAME=\"$recordId-MXPRIORITY\" VALUE=\"$myrow[5]\" TYPE=\"TEXT\"></TD>\n".
		"<TD><INPUT TYPE=\"TEXT\" NAME=\"$recordId-VALUE\" VALUE=\"$myrow[6]\"></TD>\n".
		"<TD><INPUT CLASS=\"BUTTON\" TYPE=\"SUBMIT\" NAME=\"DELETE\" VALUE=\"Delete\" onClick=\"$deleteScript\"></TD></TR>\n";
	    }
	    $output .=	"<INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$zoneid\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$myrow[7]\">".
		"<INPUT TYPE=\"HIDDEN\" NAME=\"ZONETYPE\" VALUE=\"$zonetype\">\n"
		."<INPUT TYPE=\"HIDDEN\" NAME=\"RECORDS\" VALUE=\"$record\">\n"
		."<INPUT TYPE=\"HIDDEN\" NAME=\"RECORDID\" VALUE=\"\">\n";
		
	    $output .= "<TR><TD>&nbsp;</TD><TD>&nbsp;</TD><TD>&nbsp;</TD><TD>&nbsp;</TD><TD>&nbsp;</TD><TD><INPUT CLASS=\"BUTTON\" TYPE=\"SUBMIT\" NAME=\"SAVE\" VALUE=\"Save\"></TD></TR>\n"; 
 
	    $output .= "</FORM>\n";
	    $output .= "<FORM NAME=\"NEWRECORD\" ACTION=\"editzone.php\" METHOD=\"POST\">\n".
	      "<TR><TD><INPUT NAME=\"RECORD\" VALUE=\"\" TYPE=\"TEXT\"></TD>\n".
	      "<TD><INPUT NAME=\"TTL\" VALUE=\"\" TYPE=\"TEXT\"></TD>\n".
	      "<TD>".getRecordTypeSelect($myrow[2])."</TD>\n".
	      "<TD><INPUT TYPE=\"TEXT\" NAME=\"MXPRIORITY\" VALUE=\"\">\n".
	      "<TD><INPUT TYPE=\"TEXT\" NAME=\"VALUE\" VALUE=\"\"></TD>\n".
	      "<TD><INPUT CLASS=\"BUTTON\" TYPE=\"SUBMIT\" NAME=\"NEW\" VALUE=\"New Record\">\n".
	      "</TD></TR>\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONETYPE\" VALUE=\"$zonetype\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$zoneid\"><INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$zone\"></FORM>\n";
	    echo $output."</TABLE>\n";
	  }
	  else {
	    echo "<H1>$myrow[0]</H1><FONT SIZE=\"+1\">Sorry.  You are not allowed to view this zone.</FONT><BR><BR>\n";
	  }	
	}
	else if($zonetype == "Secondary") {
	  if(userAuthToView($user,$zoneid,$zonetype)) {
	    $query = "SELECT IP FROM PRIMARYIP WHERE ZONEID=$zoneid";
	    $result = mysql_query($query,$db);
	    $row = mysql_fetch_row($result);
	    $priIp = $row[0];
	    $JS = "if(form.elements['PRIMIP'].value.length < 7 || form.elements['PRIMIP'].value.length > 15) { alert('Please Provide a single Server IP'); return false; } else { if(!this.submitted) { this.submitted=true; return true; } else { return false;}}";
	    $output = "<H1>$myrow[0]</H1>\n<TABLE BORDER=\"1\">\n";
	    $output .= "<TR><TD>Primary IP</TD><TD>&nbsp;</TD></TR>";
	    $output .= "<TR><TD><FORM NAME=\"SEC\" ACTION=\"editzone.php\" METHOD=\"POST\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$zoneid\">\n".
	      "<INPUT TYPE=\"TEXT\" NAME=\"PRIMIP\" VALUE=\"$priIp\">\n".
	      "</TD><TD><INPUT TYPE=\"SUBMIT\" NAME=\"SAVE\" VALUE=\"Save\" onClick=\"$JS\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$zone\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"UPDATESEC\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONETYPE\" VALUE=\"$zonetype\">\n".
	      "</TD></TR></FORM></TABLE>\n";
	    echo $output;
	  }
	  
	}
	else if ($zonetype == "Dynamic") {
	  if(userAuthToView($user,$zoneid,$myrow[1])) {
	    $query = "SELECT VALUE FROM ZONEOPTIONS WHERE ZONEOPTIONTYPEID=1 AND ZONEID=".$zoneid;
	    $result = mysql_query($query,$db);
	    $count = 0;
	    while ($row = mysql_fetch_row($result)) {
	      if($count ==0 ) {
		$string .= $row[0];
	      }
	      else {
		$string .= ",".$row[0];
	      }
	      $count++;
	    }
	    $output = "<H1>$myrow[0]</H1>\n<TABLE BORDER=\"1\">\n";
	    $output .= "<TR><TD>Allow Updates From:</TD><TD>&nbsp;</TD></TR>";
	    $output .= "<TR><TD><FORM NAME=\"DYN\" ACTION=\"editzone.php\" METHOD=\"POST\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONEID\" VALUE=\"$zoneid\">\n".
	      "<INPUT TYPE=\"TEXT\" NAME=\"ALLOWUPDATES\" VALUE=\"$string\">\n".
	      "</TD><TD><INPUT TYPE=\"SUBMIT\" NAME=\"SAVE\" VALUE=\"Save\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ZONE\" VALUE=\"$zone\">\n".
	      "<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"UPDATEDYN\">\n".
	      "</TD></TR></FORM></TABLE>\n";
	    echo $output;
	  }
	}
	backLink();
}

function backLink() {
  echo "<A HREF=\"mainpage.php\">Back to Main</A>\n";
}
?>

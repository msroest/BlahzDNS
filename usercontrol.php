<?php
/*
File: usercontrol.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
        This contains the code creating/editing/deleting
	users.
*/
session_start();
$title = "User Control";
include('dns.inc.php');
$username = $_SESSION['username'];
$cryptpass = $_SESSION['cryptpass'];
$maintime = $_SESSION['time'];
$ip=$_SERVER['REMOTE_ADDR'];
$detail = false;
if(isset($_SESSION['time'])) {
	$timeout = checktimeout($_SESSION['time']);
}

if(verify($username,$cryptpass) && !$timeout) {
	//Set the new time in the session
	$newtime = time();
	$_SESSION['time']=$newtime;
	if(isUserAdmin($username) || isUserROAdmin($username)) {
		if(!is_null($_GET['USERID']) || !is_null($_POST['USERID'])) {
			$userId = $_GET['USERID'];
			if(is_null($userId)) { $userId = $_POST['USERID']; }
			if(!is_null($_POST['ACTION']) && $_POST['ACTION'] == "DELUSER") {
			  if(isUserROAdmin($username)) {
			    echo "<H1><FONT COLOR=\"red\">Error RO Admin can not Delete Accounts</FONT></H1>";
			  }
			  else {
			    $query = "DELETE FROM PRIMARYUSERREF WHERE USERID=$userId";
			    $result = mysql_query($query,$db);
			  
			    $query = "DELETE FROM SECONDARYUSERREF WHERE USERID=$userId";
			    $result = mysql_query($query,$db);
			  
			    $query = "DELETE FROM USERACCOUNT WHERE USERID=$userId";
			    $result = mysql_query($query,$db);
			  
			  }
			  echo(getUserList());
			}
			else {
			if(!is_null($_POST['SUBMIT']) && $_POST['SUBMIT'] == 'Save') {
				if(!is_null($_POST['ACTION']) && $_POST['ACTION'] == "NEWUSER") {
					$cryptPass = enc($_POST['PASSWORD']);
					$query = "INSERT INTO USERACCOUNT (USERID,USERNAME,PASSWORD,USERACCOUNTTYPEID,FULLNAME)";
					$query .= "VALUES ('','".mysql_escape_string($_POST['NEWUSER'])."','$cryptPass','".$_POST['ACCTTYPE']."','".mysql_escape_string($_POST['FULLNAME'])."')";
					$result = mysql_query($query,$db);
					$query = "SELECT USERID FROM USERACCOUNT WHERE USERNAME='".mysql_escape_string($_POST['NEWUSER'])."'";
					$result2 = mysql_query($query,$db);
					$myrow = mysql_fetch_row($result2);
					$userId = $myrow[0];
					if($result) {
					$primes = $_POST['PRIMARYREF'];
					$secs = $_POST['SECONDARYREF'];
					$query = "DELETE FROM PRIMARYUSERREF WHERE USERID=$userId";
					$result = mysql_query($query,$db);
					$query = "DELETE FROM SECONDARYUSERREF WHERE USERID=$userId";
					$result = mysql_query($query,$db);
					if(count($primes) != 0 && $primes[0] != "") {
					
						$query = "INSERT INTO PRIMARYUSERREF (PRIMARYREFID,ZONEID,USERID) VALUES ";
						for($i=0;$i < count($primes); $i++) {
							if($i != 0) { $query .= " , "; }
							$query .= "('',$primes[$i],$userId)";
						}
						$result = mysql_query($query,$db);
					}
					if(count($secs) != 0 && $secs[0] != "") {
						$query = "DELETE FROM SECONDARYUSERREF WHERE USERID=$userId";
						$result = mysql_query($query,$db);
						$query = "INSERT INTO SECONDARYUSERREF (SECONDARYREFID,ZONEID,USERID) VALUES ";
						for($i=0;$i < count($secs); $i++) {
							if($i != 0) { $query .= " , "; }
							$query .= "('',$secs[$i],$userId)";
						}
						$result = mysql_query($query,$db);
					}
					echo (getUserDetail($myrow[0]));
					echo ("<H2>Success Created ".$_POST['NEWUSER']."</H2>\n");
					logMessage($username,$ip,"ADDUSER","Add user ".$_POST['NEWUSER']);
					}
					else {
					echo (getUserDetail($myrow[0]));
					echo ("<H2>Failed User ".$_POST['NEWUSER']." Exists</H2>\n");
					}
										
				}
				else {
				if(is_null($_POST['NEWUSER']) || $_POST['NEWUSER'] == "" || is_null($_POST['NEWPASS']) || $_POST['NEWPASS'] == "") {
					echo(getUserDetail($userId));
					echo("<H2>Error -- You must specify both a Username and a Password</H2>\n");
				}
				else {
					$fullname = $_POST['FULLNAME'];
					$newuser = $_POST['NEWUSER'];
					$newpass = $_POST['NEWPASS'];
					$typeId = $_POST['ACCTTYPE'];
				
					$query = "UPDATE USERACCOUNT SET USERNAME='".mysql_escape_string($newuser)."', FULLNAME='".mysql_escape_string($fullname)."', USERACCOUNTTYPEID=$typeId";
					if($newpass != "******") {
						$newcrypt = enc($newpass);
						$query .= ", PASSWORD='$newcrypt'";
						if($newuser == $username) {
							$_SESSION['cryptpass'] = $newcrypt;
						}
					}
					$query .= " WHERE USERID=$userId";
					$result = mysql_query($query,$db);
					
					$primes = $_POST['PRIMARYREF'];
					$secs = $_POST['SECONDARYREF'];
					$query = "DELETE FROM PRIMARYUSERREF WHERE USERID=$userId";
					$result = mysql_query($query,$db);
					$query = "DELETE FROM SECONDARYUSERREF WHERE USERID=$userId";
					$result = mysql_query($query,$db);
					if(count($primes) != 0 && $primes[0] != "") {
					  $query = "INSERT INTO PRIMARYUSERREF (PRIMARYREFID,ZONEID,USERID) VALUES ";
					  for($i=0;$i < count($primes); $i++) {
					    if($i != 0) { $query .= " , "; }
					    $query .= "('',$primes[$i],$userId)";
					  }
					  $result = mysql_query($query,$db);
					}
					if(count($secs) != 0 && $secs[0] != "") {
					  $query = "DELETE FROM SECONDARYUSERREF WHERE USERID=$userId";
					  $result = mysql_query($query,$db);
					  $query = "INSERT INTO SECONDARYUSERREF (SECONDARYREFID,ZONEID,USERID) VALUES ";
					  for($i=0;$i < count($secs); $i++) {
					    if($i != 0) { $query .= " , "; }
					    $query .= "('',$secs[$i],$userId)";
					  }
					  $result = mysql_query($query,$db);
					}
					echo(getUserDetail($userId));
					echo("<H2>Success Updated $newuser</H2>\n");
					logMessage($username,$ip,"MODUSER","Modified user $newuser");
				}
				}
			}
			else if($userId=="NEW") {
				$typeSelect = accountTypeSelect("");
				$primarySelect = primarySelectForUser($userId);
				$secondarySelect = secondarySelectForUser($userId);
				echo "<H1>New User</H1>\n";
				echo "<FORM ACTION=\"usercontrol.php\" METHOD=\"POST\" NAME=\"CREATEUSER\">\n";
				echo "<TABLE BORDER=\"1\"><TR><TD>Username: </TD><TD><INPUT NAME=\"NEWUSER\" VALUE=\"\"></TD></TR>\n";
				echo "<TR><TD>Full Name: </TD><TD><INPUT TYPE=\"TEXT\" NAME=\"FULLNAME\" VALUE=\"\"></TD></TR>\n";
				echo "<TR><TD>Password: </TD><TD><INPUT TYPE=\"PASSWORD\" NAME=\"PASSWORD\" VALUE=\"\"></TD></TR>\n";
				echo "<TR><TD>Account Type: </TD><TD>$typeSelect</TD></TR>\n";
				echo "<TR><TD ALIGN=\"TOP\">Primary Access: </TD><TD>$primarySelect</TD></TR>\n";
				echo "<TR><TD ALIGN=\"TOP\">Secondary Access: </TD><TD>$secondarySelect</TD></TR>\n";
				echo "<TR><TD ALIGN=\"RIGHT\"><INPUT TYPE=\"SUBMIT\" NAME=\"SUBMIT\" VALUE=\"Save\"></TD>\n";
				echo "<TD><INPUT TYPE=\"RESET\" NAME=\"CLEAR\" VALUE=\"Clear\"></TD></TR>\n";
				echo "</TABLE><INPUT TYPE=\"HIDDEN\" NAME=\"USERID\" VALUE=\"$userId\"><INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"NEWUSER\"></FORM>\n";
				
			}
			else {
				echo(getUserDetail($userId));
			}
			}
		}
		else {
			echo (getUserList());
		}
	}
	else {
	}
	echo(($detail ? "<BR><A HREF=\"usercontrol.php\">Back to User Config</A>\n" : "<BR><A HREF=\"mainpage.php\">Back to Main</A>\n"));
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

function getUserDetail($userId) {
	global $db;
	global $detail;
	$detail = true;
	$query = "SELECT UA.USERNAME,UA.USERACCOUNTTYPEID,UA.FULLNAME FROM USERACCOUNT UA WHERE UA.USERID=$userId";
	$result = mysql_query($query,$db);
	$row = mysql_fetch_row($result);
	$primarySelect = primarySelectForUser($userId);
	$secondarySelect = secondarySelectForUser($userId);
	$typeSelect = accountTypeSelect($row[1]);
	$output = "<H1>Edit User $row[0]</H1>\n";
	$output .= "<FORM ACTION=\"usercontrol.php\" METHOD=\"POST\" NAME=\"UPDATEUSER\">\n";
	$output .= "<TABLE BORDER=\"1\"><TR><TD>Username: </TD><TD><INPUT TYPE=\"TEXT\" NAME=\"NEWUSER\" VALUE=\"$row[0]\"></TR>\n";
	$output .= "<TR><TD>Full Name: </TD><TD><INPUT TYPE=\"TEXT\" NAME=\"FULLNAME\" VALUE=\"$row[2]\"></TR>\n";
	$output .= "<TR><TD>Password: </TD><TD><INPUT TYPE=\"PASSWORD\" NAME=\"NEWPASS\" VALUE=\"******\"></TD></TR>\n";
	$output .= "<TR><TD>Account Type: </TD><TD>$typeSelect</TD></TR>\n";
	$output .= "<TR><TD ALIGN=\"TOP\">Primary Access: </TD><TD>$primarySelect</TD></TR>\n";
	$output .= "<TR><TD ALIGN=\"TOP\">Seconday Access: </TD><TD>$secondarySelect</TD></TR>\n";
	$output .= "<TR><TD ALIGN=\"RIGHT\"><INPUT TYPE=\"SUBMIT\" NAME=\"SUBMIT\" VALUE=\"Save\"></TD>\n";
	$output .= "<TD><INPUT TYPE=\"RESET\" NAME=\"CLEAR\" VALUE=\"Clear\"></TD></TR>\n";
	$output .= "</TABLE><INPUT TYPE=\"HIDDEN\" NAME=\"USERID\" VALUE=\"$userId\"></FORM>\n";
	return $output;

}

function accountTypeSelect($typeId) {
	global $db;
	$query = "SELECT USERACCOUNTTYPEID,DESCRIPTION FROM USERACCOUNTTYPE";
	$result = mysql_query($query,$db);
	$output = "<SELECT NAME=\"ACCTTYPE\">\n";
	while($myrow = mysql_fetch_row($result)) {
		if($typeId == $myrow[0]) {
			$output .= "<OPTION VALUE=\"$myrow[0]\" SELECTED>$myrow[1]";
		}
		else {
			$output .= "<OPTION VALUE=\"$myrow[0]\">$myrow[1]";
		}
	}
	$output .= "</SELECT>\n";
	return $output;
}
function primarySelectForUser($userId) {
	global $db;
	$current = array();
	if($userId != "NEW") {
		$query = "SELECT ZONEID FROM PRIMARYUSERREF WHERE USERID=$userId";
		$result = mysql_query($query,$db);
		while($myrow = mysql_fetch_row($result)) {
			$current[$myrow[0]] = TRUE;
		}
	}
	$query = "SELECT ZONETYPEID FROM ZONETYPE WHERE NAME = 'Primary' OR NAME='Dynamic'";
	$result = mysql_query($query,$db);
	$query = "SELECT ZONEID,NAME FROM ZONES WHERE";
	$count = 0;
	while($myrow = mysql_fetch_row($result)) {
		if($count != 0) {
			$query .= " OR ";
		}
		$query .= " ZONETYPEID = $myrow[0]";
		$count++;
	}
	$result = mysql_query($query,$db);
	
	$output = "";
	$output .= "<SELECT MULTIPLE SIZE=\"5\" NAME=\"PRIMARYREF[]\">\n";
	$count = 0;
	while($myrow = mysql_fetch_row($result)) {
		if(!is_null($current[$myrow[0]])) {
			$output .= "<OPTION VALUE=\"$myrow[0]\" SELECTED>$myrow[1]\n";
		}
		else {
			$output.="<OPTION VALUE=\"$myrow[0]\">$myrow[1]\n";
		}
		$count++;
	}
	$output .= "</SELECT>\n";
	if ($count == 0) { $output= ""; }
	return $output;
		
}

function secondarySelectForUser($userId) {
	global $db;
	$current = array();
	if($userId != "NEW") {
		$query = "SELECT ZONEID FROM SECONDARYUSERREF WHERE USERID=$userId";
		$result = mysql_query($query,$db);
		while($myrow = mysql_fetch_row($result)) {
			$current[$myrow[0]] = TRUE;
		}
	}
	$query = "SELECT ZONETYPEID FROM ZONETYPE WHERE NAME = 'Secondary'";
	$result = mysql_query($query,$db);
	$query = "SELECT ZONEID,NAME FROM ZONES WHERE";
	$count = 0;
	while($myrow = mysql_fetch_row($result)) {
		if($count != 0) {
			$query .= " OR ";
		}
		$query .= " ZONETYPEID = $myrow[0]";
		$count++;
	}
	$result = mysql_query($query,$db);

	$output = "";
	$count =0;
	$output .= "<SELECT MULTIPLE SIZE=\"5\" NAME=\"SECONDARYREF[]\">\n";
	while($myrow = mysql_fetch_row($result)) {
		if(!is_null($current[$myrow[0]])) {
			$output .= "<OPTION VALUE=\"$myrow[0]\" SELECTED>$myrow[1]\n";
		}
		else {
			$output.="<OPTION VALUE=\"$myrow[0]\">$myrow[1]\n";
		}
		$count++;
	}
	$output .= "</SELECT>\n";
	if($count==0) { $output = ""; }
	return $output;
}
function getUserList() {
	global $db;
	$query = "SELECT UA.USERID,UA.USERNAME,UAT.DESCRIPTION FROM USERACCOUNT UA, USERACCOUNTTYPE UAT";
	$query .= " WHERE UA.USERACCOUNTTYPEID=UAT.USERACCOUNTTYPEID";
	$result = mysql_query($query,$db);
	$output = "<H1>User Config</H1>\n";
	$output .= "<TABLE BORDER=1><TR><TD>Username</TD><TD COLSPAN=\"3\">Account Type</TD></TR>";

	while($myrow = mysql_fetch_row($result)) {
		$output .= "<TR><TD><A HREF=\"usercontrol.php?USERID=$myrow[0]\">$myrow[1]</a></TD><TD>$myrow[2]</TD><TD>".
			"<FORM NAME=\"DELUSER\" ACTION=\"usercontrol.php\" METHOD=\"POST\"><INPUT TYPE=\"HIDDEN\" NAME=\"USERID\" VALUE=\"$myrow[0]\">\n".
			"<INPUT TYPE=\"HIDDEN\" NAME=\"ACTION\" VALUE=\"DELUSER\"><INPUT TYPE=\"SUBMIT\" NAME=\"SUBMIT\" VALUE=\"Delete\"></FORM></TD></TR>\n";
	}
	$output .= "<TR><TD COLSPAN=\"3\"><A HREF=\"usercontrol.php?USERID=NEW\">New</A></TD></TR>\n";
	$output .= "</TABLE>\n";
	return $output;
}

?>


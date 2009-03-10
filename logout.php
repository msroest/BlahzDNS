<?php
/*
File: logout.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
       This file contains the Code that removes the session variables and clears everything
*/

session_start();
$title = "Main";
include('dns.inc.php');
$user = $_SESSION['username'];

$_SESSION['username']=null;
$_SESSION['cryptpass']=null;
$_SESSION['time']=null;

logMessage($username,$ip,"LOGOUT","Logout User $user");
echo "<H1>User $user Logged Out</H1>\n";
create_login();
closedoc();

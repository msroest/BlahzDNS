<?php
/*
File: login.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
        This contains the code for logging in and showing the
        main interface
*/
session_start();
//Set the version number
$title = "Login";
include("dns.inc.php");
create_login();
closedoc();
mysql_close($db);
?>
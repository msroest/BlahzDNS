<?php
/*
File: install.php
Author: Mike Roest <msroest@user.sourceforge.net>
Homepage: http://blahzdns.sourceforge.net/
Comments:
        This file contains the installation procedure.
*/

include_once("vars.php");
//connect to the DB and select the required database
$db = mysql_connect($dbhost, $dbuser, $dbpass);
mysql_select_db($dbname,$db);

$query = "SELECT PROGRAMOPTIONID FROM PROGRAMOPTIONS";
$res = mysql_query($query,$db);
$count = mysql_num_rows($res);
if($count ==0) {
  $query = "SELECT PROGRAMOPTIONTYPEID FROM PROGRAMOPTIONTYPE";
  $res = mysql_query($query,$db);
  while($myrow=mysql_fetch_row($res)) {
    $query = "INSERT INTO PROGRAMOPTIONS (PROGRAMOPTIONID,PROGRAMOPTIONTYPEID,VALUE) VALUES ('',$myrow[0],'')";
    mysql_query($query,$db);
  }
  $DEFAULTS = "UPDATE PROGRAMOPTIONS SET VALUE='300' WHERE PROGRAMOPTIONTYPEID=18";
  mysql_query($DEFAULTS,$db);
  $DEFAULTS = "UPDATE PROGRAMOPTIONS SET VALUE='styles/style2.css' WHERE PROGRAMOPTIONTYPEID=19";
  mysql_query($DEFAULTS,$db);
  $DEFAULTS = "UPDATE PROGRAMOPTIONS SET VALUE='search' WHERE PROGRAMOPTIONTYPEID=16";
  mysql_query($DEFAULTS,$db);
  $DEFAULTS = "UPDATE PROGRAMOPTIONS SET VALUE='Ymd01' WHERE PROGRAMOPTIONTYPEID=11";
  mysql_query($DEFAULTS,$db);
  $DEFAULTS = "UPDATE PROGRAMOPTIONS SET VALUE='bind' WHERE PROGRAMOPTIONTYPEID=17";
  mysql_query($DEFAULTS,$db);
  $DEFAULTS = "UPDATE PROGRAMOPTIONS SET VALUE='YES' WHERE PROGRAMOPTIONTYPEID=20";
  mysql_query($DEFAULTS,$db);
  $DEFAULTS = "UPDATE PROGRAMOPTIONS SET VALUE='50' WHERE PROGRAMOPTIONTYPEID=21";
  mysql_query($DEFAULTS,$db);
  

}

//Initial Install
if(!is_null($_POST['STEP'])) {
  if($_POST['STEP'] == "1") {
    $query = "UPDATE PROGRAMOPTIONS SET VALUE='".mysql_escape_string($_POST['SALT'])."' WHERE PROGRAMOPTIONTYPEID=2";
    mysql_query($query,$db);
    $js = "if(form.elements['USERNAME'].value.length == 0 || form.elements['PASSWORD'].value.length ==0) { alert('Please Enter a Username and Password'); return false;";
    $js .= "} else { if (!this.submitted) { this.submitted = true; return true; } else { return false; } }";
    echo "<HTML><HEAD><TITLE>Step 2</TITLE></HEAD><BODY><H1>Step 2: Create Initial User</H1>\n";
    echo "<FORM METHOD=\"POST\" ACTION=\"install.php\"><TABLE><TR><TD>Username: </TD><TD><INPUT TYPE=\"TEXT\" NAME=\"USERNAME\"></TD></TR>\n";
    echo "<TR><TD>Password: </TD><TD><INPUT TYPE=\"PASSWORD\" NAME=\"PASSWORD\"></TD></TR>\n";
    echo "<TR><TD><INPUT TYPE=\"SUBMIT\" NAME=\"SAVE\" VALUE=\"Save\" onClick=\"$js\"></TD><TD><INPUT TYPE=\"RESET\" NAME=\"CLEAR\" VALUE=\"Clear\"></TD></TR>\n";
    echo "</TABLE><INPUT TYPE=\"HIDDEN\" NAME=\"STEP\" VALUE=\"2\"></FORM>\n";
    echo "</BODY></HTML>\n";
  }
  else if ($_POST['STEP'] == "2") {
    $cryptPass = enc($_POST['PASSWORD']);
    $query = "INSERT INTO USERACCOUNT (USERID,USERNAME,PASSWORD,USERACCOUNTTYPEID,FULLNAME) VALUES "
      ."('','".mysql_escape_string($_POST['USERNAME'])."','".$cryptPass."',1,'Initial Account')";
    mysql_query($query,$db);
    
    echo "<HTML><HEAD><TITLE>Installation Complete</TITLE></HEAD><BODY><H1>Installation Complete</H1>\n";
    echo "Please Click the link below to access your installation of BlahzDNS.<br>\n";
    echo "Remember to delete the install.php file and to set the remainder of the system options through the Edit Configuration Screen<br>\n";
    echo "<A HREF=\"login.php\">Proceed to Login</A><br></BODY></HTML>\n";

  }
  
}
else {
  $js = "if(form.elements['SALT'].value.length < 20 || form.elements['SALT'].value.length > 40) { alert('Please Ensure your Salt is between 20 and 40 characters'); return false;";
  $js .= "} else { if (!this.submitted) { this.submitted = true; return true; } else { return false; } }";
  //In step one.  Insert all program Option Type's into PROGRAMOPTIONS
  echo "<HTML><HEAD><TITLE>Step 1</TITLE></HEAD><BODY><H1>Step 1: Set Encryption Salt</H1>\n";
  echo "<FORM METHOD=\"POST\" ACTION=\"install.php\"><TABLE><TR><TD>Salt Value:</TD><TD><INPUT TYPE=\"TEXT\" NAME=\"SALT\"></TD></TR>\n";
  echo "<TR><TD><INPUT TYPE=\"SUBMIT\" NAME=\"SAVE\" VALUE=\"Save\" onClick=\"$js\"></TD><TD><INPUT TYPE=\"RESET\" NAME=\"CLEAR\" VALUE=\"Clear\"></TD></TR>\n";
  echo "</TABLE><INPUT TYPE=\"HIDDEN\" NAME=\"STEP\" VALUE=\"1\"></FORM>\n";
  echo "</BODY></HTML>\n";
}
  

function enc($data) {
  $newcryptpass ="";
  global $db;
  global $cryptMethod;
  $query = "SELECT VALUE FROM PROGRAMOPTIONS WHERE PROGRAMOPTIONTYPEID=2";
  $res = mysql_query($query,$db);
  $row = mysql_fetch_row($res);
  if($cryptMethod == "mcrypt") {
    //set encryption mode to Triple DES
    $td = mcrypt_module_open (MCRYPT_TripleDES, "", MCRYPT_MODE_ECB, "");
    $iv = mcrypt_create_iv (mcrypt_enc_get_iv_size ($td), MCRYPT_RAND);
    mcrypt_generic_init ($td, $row[0], $iv);
    $newcryptpass = mcrypt_generic ($td, $data);  //perform the crypt
    $newcryptpass = bin2hex($newcryptpass);              //And hex it to cause thing to not break
    mcrypt_generic_end ($td);
  }
  else if($cryptMethod == "crypt") {
    $newcryptpass = crypt($data,$row[0]);
  }
  //return the crypted info
  return $newcryptpass;
}


?>

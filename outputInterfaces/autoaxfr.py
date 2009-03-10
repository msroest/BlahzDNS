#!/bin/env python
# File: autoaxfr.py
# Author: Mike Roest <msroest@users.sourceforge.net>
# Purpose:
#       This file will connect to the BlahzDNS DataBase and output
#       autoaxfr entries to AXFR zone data from Bind/axfrdns servers.
#
#

import MySQLdb
import string
import getopt
import sys
import time

#Config Values
username = ''
password = ''
dbHost = 'localhost'
dbName = 'blahzdns'


def main():
	global dbHost
	global dbName
	global username
	global password
				
	try:
          opts, args = getopt.getopt(sys.argv[1:],"u:p:H:D:dho:a",["username=","password=","dbhost=","dbname=","debug","help","output-path","override","about"])
        except getopt.GetoptError:
          print "Illegal Option"
          usage()
	  sys.exit()
	#set defaults.
	filePath = '/service/autoaxfr/root/slaves'
	debug = 0
	override =0
	for o,a in opts:
	  if o in ("-h","--help"):
	    usage()
	    sys.exit()
	  if o in ("-a","--about"):
	    about()
	    sys.exit()
	  if o in ("-o","--output-path"):
	    filePath=a
	    if(filePath[len(filePath)-1] != "/"):
	    	filePath = filePath+"/"
	  if o in ("-d","--debug"):
	    debug=1
	  if o == "--override":
	    override=1
	  if o in ("-u","--username"):
            username = a
          if o in ("-p","--password"):
            password = a
          if o in ("-H","--dbhost"):
            dbHost = a
          if o in ("-D","--dbname"):
            dbName = a
	if debug:
	  print "Finished Parsing Command Like Parameters -- Results:"
	  print "Debug: "+debug.__str__()
	  print "Override: "+override.__str__()
	  print "Output Path: "+filePath
	  print "Username: "+username
	  print "Password: "+password
	  print "dbHost: "+dbHost
	  print "dbName: "+dbName
	  
	if(username == "" or password=="") and override==0:
	  print "Must specify a username & password (or --override commandline option)\n"
	  sys.exit(1)
	try:
	  db = MySQLdb.connect(host=dbHost,db=dbName,user=username,passwd=password)
	except:
	  print "Unable to Connect to Database\nPlease check username,password,DB name and DB host\n"
	  sys.exit(1)
	#clean up any existing slave files
	command = "rm -rf "+filePath+"/*";
	os.system(command);
	

	#get secondaries that need to be written out.
	c = db.cursor()
	c.execute("""SELECT Z.ZONEID,Z.NAME,P.IP FROM ZONES Z, PRIMARYIP P WHERE Z.ZONETYPEID=2 AND 
				Z.ZONESTATUSID=1 AND Z.ZONEID=P.ZONEID""")
	result = c.fetchall()
	c.close()
	for id,name,ip in result:
	 output=open(filePath+name,"w")
	 output.write(ip+"\n")
	 if debug:
	   print "Wrote file for "+name

	return
def usage():
	print "Usage: autoaxfr.py [OPTIONS]"
        print "-oPATH,--output-path=PATH: \tWhere PATH is the autoaxfr slaves directory"
	print "-uUSER,--username=USER: \tSet mySQL username"
	print "-pPASS,--password=PASS: \tSet mySQL password"
	print "-HHOST,--dbhost=HOST: \t\tSet mySQL Server hostname (defaults to localhost)"
	print "-DDB,--dbname=DB: \t\tSet Blahz DNS DB name (defaults to blahzdns)"
	print "--override: \t\t\tAllows blank username/password"
	print "-d,--debug : \t\t\tPrint Increased Debugging to STDOUT"
	print "-h,--help : \t\t\tThis Screen"
	print "-a,--about : \t\t\tAbout autoaxfr.py"
	return
def about():
	print "This script will output the root/slaves files for use"
	print "with the autoaxfr (http://www.lickey.com/autoaxfr/) perl"
	print "script."

if __name__ == "__main__":
    main()

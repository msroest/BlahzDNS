#!/bin/env python
# File: upgrade.py
# Author: Mike Roest <msroest@users.sourceforge.net>
# Purpose:
#       This file will connect to the BlahzDNS 0.5 DataBase and output
#	into a BlahzDNS 1.0 database

import MySQLdb
import string
import getopt
import sys
import time

def main():
	try:
	  opts,args = getopt.getopt(sys.argv[1:],'u:U:p:P:s:S:d:D:h',["userOld=","userNew=","passOld=","passNew=","dbhostOld=","dbhostNew=","dbnameOld=","dbnameNew=","help"])
	except getopt.GetoptError:
	  print "Illegal Option"
	  usage()
	  sys.exit()
	#setup options
	userOld =""
	userNew =""
	passOld =""
	passNew =""
	dbhostOld = ""
	dbhostNew = ""
	dbnameOld = ""
	dbnameNew = ""
	try:
 	 for o,a in opts:
	  if o in ("-h","--help"):
	    usage()
	    sys.exit()
	  if o in ("-u","--userOld"):
	    userOld = a
	  if o in ("-U","--userNew"):
	    userNew = a
	  if o in ("-p","--passOld"):
	    passOld = a
	  if o in ("-P","--passNew"):
	    passNew = a
	  if o in ("-s","--dbhostOld"):
	    dbhostOld = a
	  if o in ("-S","--dbhostNew"):
	    dbhostNew = a
	  if o in ("-d","--dbnameOld"):
	    dbnameOld = a
	  if o in ("-D","--dbnameNew"):
	    dbnameNew = a
	except:
	  print "Error Parsing Options\n"
	  usage()
	  sys.exit()
	  
	if userOld=="" or userNew=="" or passOld=="" or passNew=="" or dbhostOld=="" or dbhostNew=="" or dbnameOld=="" or dbnameNew=="":
	  print "ERROR -- Please include all the required command line parameters"
	  print userOld
	  print userNew
	  print passOld
	  print passNew
	  print dbhostOld
	  print dbhostNew
	  print dbnameOld
	  print dbnameNew
	  usage()
	  sys.exit()
	#Open DB connections.
	#Open connection to Old Blahz DNS DB
	try:
	  dbOld = MySQLdb.connect(host=dbhostOld,db=dbnameOld,user=userOld,passwd=passOld)
	except:
	  print "Unable to connect to BlahzDNS 0.5 DB"
	  print "Please check userOld,passOld,dbhostOld,dbnameOld values"
	  sys.exit(1)
	
	#Open connection to New Blahz DNS DB
	try:
	  dbNew = MySQLdb.connect(host=dbhostNew,db=dbnameNew,user=userNew,passwd=passNew)
	except:
	  print "Unable to connect to BlahzDNS 1.X DB"
	  print "Please check userNew,passNew,dbhostNew,dbnameNew values"
	  sys.exit(1)
	
	#We now have our connections.  First step grab the starting zoneId from the DB.
	query = "SELECT MAX(ZONEID)+1 FROM ZONES";
	c=dbNew.cursor()
	c.execute(query)
	result = c.fetchall()
	c.close()
	zoneId = result[0][0];
	if zoneId == None:
	  zoneId = 1
	#now create a mapping of record types to recordtype id
	query = "SELECT RECORDTYPEID,NAME FROM RECORDTYPE"
	c=dbNew.cursor()
	c.execute(query)
	result = c.fetchall()
	c.close()
	recordMap={}
	for id,name in result:
	  recordMap[name.upper()]= id
	#now for each primary zone in the old DB.  Setup the new zone record and insert the records
	query = "SELECT domain,table_name,active from primarys"
	c = dbOld.cursor()
	c.execute(query)
	result = c.fetchall()
	c.close()
	for domain,table,status in result:
	  query = "INSERT INTO ZONES (ZONEID,ZONETYPEID,ZONESTATUSID,NAME,UPDATED) VALUES"
	  query = query +" ("+zoneId.__str__()+",1,"
	  if status == "active":
	    query = query+"1,"
	  else:
	    query = query+"2,"
	  query = query+"'"+domain+"',1)"
	  
	  c = dbNew.cursor()
	  c.execute(query)
	  c.close()

	  #grab records from old DB
	  query = "SELECT record,type,mxpriority,value FROM "+table
	  c = dbOld.cursor()
	  c.execute(query)
	  result = c.fetchall()
	  c.close()
	  
	  for record,type,mxpriority,value in result:
	    if mxpriority == None:
	      mxpriority ="NULL"
	    query = "INSERT INTO RECORDS (RECORDID,ZONEID,MODUSER,RECORDTYPEID,RECORD,TTL,MXPRIORITY,VALUE) VALUES"
	    query = query + " ('',"+zoneId.__str__()+",'upgrade',"+recordMap.get(type.upper()).__str__()+",'"+record+"',NULL,"
	    query = query + mxpriority.__str__()+",'"+value+"')"
	    c = dbNew.cursor()
	    c.execute(query)
	    c.close()
	  zoneId = zoneId+1
	
	#Get info for Secondary's 
	query = "SELECT domain,primaryip,active FROM secondarys"
	c = dbOld.cursor()
	c.execute(query)
	result = c.fetchall()
	c.close()
	for domain,primaryip,status in result:
	  query = "INSERT INTO ZONES (ZONEID,ZONETYPEID,ZONESTATUSID,NAME,UPDATED) VALUES"
	  query = query + "("+zoneId.__str__()+",2,"
	  if status == "active":
	    query = query+"1,"
	  else:
	    query = query+"2,"
	  query = query + "'"+domain+"',1)"
	  c = dbNew.cursor()
	  c.execute(query)
	  c.close

	  query = "INSERT INTO PRIMARYIP (ZONEID,IP) VALUES ("+zoneId.__str__()+",'"
	  query = query +""+primaryip+"')"
	  c = dbNew.cursor()
	  c.execute(query)
	  c.close()
	  zoneId = zoneId+1
	
	#Get Info For Users
	query = "SELECT MAX(USERID)+1 FROM USERACCOUNT"
	c = dbNew.cursor()
	c.execute(query)
	result=c.fetchall()
	c.close
	userId = result[0][0];
        if userId == None:
          userId = 1
		  
	query = "SELECT user,pass,type,primary_access,secondary_access FROM users"
	c = dbOld.cursor()
	c.execute(query)
	result=c.fetchall()
	c.close

	for user,cryptPass,type,primary,secondary in result:
	  if type == "admin":
	    typeId=1
	  elif type =="user":
	    typeId=2
	    
	  query = "INSERT INTO USERACCOUNT (USERID,USERNAME,PASSWORD,USERACCOUNTTYPEID) VALUES"
	  query = query + "("+userId.__str__()+",'"+user+"','"+cryptPass+"',"+typeId+")"
	  #now setup the references to all users to edit there zones.
	  prim = split(primary,",")
	  for zoneName in prim:
	    query = "INSERT INTO PRIMARYUSERREF (ZONEID,USERID) SELECT ZONEID,"+userId+" FROM ZONES WHERE NAME='"+zoneName+"'"
	    c = dbNew.cursor()
	    c.execute(query)
	    c.close()
	  sec = split(secondary,",")
	  for zoneName in sec:
	    query = "INSERT INTO SECONDARYUSERREF (ZONEID,USERID) SELECT ZONEID,"+userId+" FROM ZONES WHERE NAME='"+zoneName+"'"
	    c = dbNew.cursor()
	    c.execute(query)
	    c.close()
	  userID = userId + 1

    
	return


def usage():
	print "Usage: upgrade.py [OPTIONS]"
	print "-uUSER,--userOld=USER: \t\tWhere USER is the mySQL user for the BlahzDNS .5 DB"
	print "-UUSER,--userNew=USER: \t\tWhere USER is the mySQL user for the BlahzDNS 1.X DB"
	print "-pPASS,--passOld=PASS: \t\tWhere PASS is the mySQL password for the BlahzDNS .5 DB"
	print "-PPASS,--passNew=PASS: \t\tWhere PASS is the mySQL password for the BlahzDNS 1.X DB"
	print "-sSERVER,--dbhostOld=SERVER: \tWhere SERVER is the mySQL server for the BlahzDNS .5 DB"
	print "-SSERVER,--dbhostNew=SERVER: \tWhere SERVER is the mySQL server for the BlahzDNS 1.X DB"
	print "-dDB,--dbnameOld=DB: \t\tWhere DB is the mySQL database name for BlahzDNS .5 DB"
	print "-DDB,--dbnameNew=DB: \t\tWhere DB is the mySQL database name from BlahzDNS 1.x DB"
	print "-h,--help: \t\t\tThis Screen"

	return

if __name__ == "__main__":
	main()


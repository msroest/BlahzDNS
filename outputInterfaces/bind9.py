#!/bin/env python
# File: bind9.py
# Author: Mike Roest <msroest@users.sourceforge.net>
# Purpose: 
#	This file will connect to the BlahzDNS DataBase and output 
#	A named.conf file (or a file that can be included in a named.conf)
#	and the required zone files
#
#
#Import required modules.
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

#Main Program
def main():
	global dbHost
	global dbName
	global username
	global password
	#First get comand line arguments
	try:
	  opts, args = getopt.getopt(sys.argv[1:],"u:p:H:D:dho:ra",["username=","password=","dbhost=","dbname=","debug","help","output-file=","override","read-only","about"])
	except getopt.GetoptError:
	  print "Illegal Option"
	  usage()
	  sys.exit()
	#set defaults
	outputFile = ""
	debug=0
	override=0
	readOnly=0
	for o,a in opts:
	  if o in ("-h","--help"):
	    usage()
	    sys.exit()
	  if o in ("-a","--about"):
	    about()
	    sys.exit()
	  if o in ("-o","--output-file"):
	    outputFile=a
	  if o in ("-d","--debug"):
	    debug=1
	  if o in ("-u","--username"):
	    username = a
	  if o in ("-p","--password"):
	    password = a
	  if o in ("-H","--dbhost"):
	    dbHost = a
	  if o in ("-D","--dbname"):
	    dbName = a
	  if o =="--override":
	    override = 1
	  if o in ("-r","--read-only"):
	    readOnly=1
	    
	if debug:
	  print "Finished Parsing Command Line Parameters -- Results:"
	  print "Debug: "+debug.__str__()
	  print "Output File: "+outputFile
	  print "Username: "+username
	  print "Password: "+password
	  print "dbHost: "+dbHost
	  print "dbName: "+dbName
	
	if (username=="" or password =="") and override==0:
	  print "Must specify a username & password (or --override commandline option)\n";
	  sys.exit(1)
	try:
          db = MySQLdb.connect(host=dbHost,db=dbName,user=username,passwd=password)
	except :
	  print "Unable to Connect to DataBase\nPlease check username,password,DB name and DB host\n"
	  sys.exit(1)
	config = getConfig(username,password,dbName,dbHost)
	configDir = config.get("CONFDIRABS")
	if outputFile == "":
	  outputFile = configDir+"/named.conf"
	
	try:
	  output = open(outputFile,"w")
	except:
	  print "Unable to open output File\nPlease ensure script has permission to right to file\n"
	  sys.exit(1)
	mname = config.get("ORIGINSERVER")
	if (mname[len(mname)-1] != '.'):
	  mname = mname+"."
	admin = config.get("DNSADMIN")
	if(admin[len(admin)-1] != '.'):
	  admin = admin+"."
	header = config.get("CONFIGHEADER")
	globalttl = config.get("REFRESH")
	relZPath = config.get("ZONEDIR")
	absZPath = config.get("ZONEDIRABS")
	if(relZPath != "" and relZPath[len(relZPath)-1] != "/"):
	  relZPath = relZPath + "/"
	if(absZPath != "" and absZPath[len(absZPath)-1] != "/"):
	  absZPath = absZPath + "/"
	
	output.write(header+"\n\n")
	c=db.cursor()
	c.execute("SELECT ZONEID,NAME,UPDATED FROM ZONES WHERE ZONETYPEID=1 AND ZONESTATUSID=1")
	result = c.fetchall()
	c.close()
	for zoneId,zoneName,updated in result:
	  if debug:
	    print "Beginning Work for "+zoneName
	    print "\nGetting Zone Options & Records\n"
	  
	  c=db.cursor()
	  try:
  	    c.execute("""SELECT ZOT.NAME,ZO.ZONEOPTIONTYPEID,ZO.VALUE FROM ZONEOPTIONS ZO, ZONEOPTIONTYPE ZOT WHERE ZO.ZONEID="""+zoneId.__str__()+
	  		""" AND ZO.ZONEOPTIONTYPEID=ZOT.ZONEOPTIONTYPEID ORDER BY ZONEOPTIONTYPEID""")
            result2 = c.fetchall()
	  except:
	    result2 = ""
	  
	  zoHash={}
	  for ztypeName,ztypeId,zValue in result2:
	    if ztypeId.__str__() not in zoHash:
	      zoHash[ztypeId.__str__()]=zValue
	    else:
	      zoHash[ztypeId.__str__()]= zoHash[ztypeId.__str__()]+","+zValue
	  #ok we now have any program options
	  #First write out named.conf file entry
	  output.write("zone \""+zoneName.__str__()+"\" IN {\n")
	  output.write("\ttype master;\n")
	  val = zoHash.get("2")
	  if val != None:
	    output.write("\talso-notify {"+string.replace(val,",","; ")+";};\n")
	  
	  val = zoHash.get("3")
	  if val != None:
	    if(val[len(val)-1] != "/"):
	      val = val + "/"
	  else :
	    val=""
          output.write("\tfile \""+relZPath.__str__()+val.__str__()+zoneName.__str__()+"\";\n")
	  output.write("};\n\n")

  	  c=db.cursor()
	  c.execute("""SELECT RT.NAME,R.RECORD,R.TTL,
	  		R.MXPRIORITY,R.VALUE,R.RECORDID FROM
			RECORDTYPE RT, RECORDS R WHERE R.ZONEID="""+zoneId.__str__()+
				    """ AND R.RECORDTYPEID=RT.RECORDTYPEID
				    	ORDER BY RT.SORTORDER,R.RECORD,R.MXPRIORITY""")
	  result3 = c.fetchall()
 	  c.close
	  if debug:
	    print "Records for "+zoneName+result.__str__()
	  #Now time to open our zone file
          zoneFile=absZPath+val+zoneName
          try:
            zoneOut = file(zoneFile,"w",0)
          except:
            print "Failed to open "+zoneFile+"\nPlease ensure script has access to write in "+absZPath+val+"\n"
            sys.exit(1)
          zoneOut.write("$ORIGIN .\n")
          zoneOut.write("$TTL "+globalttl.__str__()+";\n")
														
	  for type,record,ttl,mxpriority,value,recordid in result3:
	    #Handle SOA Records
	    if type == 'SOA':
	      soaParts = string.split(value,",")
	      if(record != "@"):
	        fqdn = record+zoneName+"."
	      else:
	        fqdn = zoneName+"."
	      newSOA=soaParts[0]
	      if updated == 1 and readOnly == 0:
	        revision = soaParts[0][-2:]
		datePart = soaParts[0][:-2]
		newdatePart = time.strftime("%Y%m%d")
		if newdatePart==datePart:
		  revision=int(revision)+1
		  if revision < 10:
		    newSOA=datePart+"0"+revision.__str__()
		  else:
		    newSOA=datePart+revision.__str__()
		else:
		  newSOA=newdatePart+"01"
   	      #output.write("Z"+fqdn+":"+mname+":"+admin+":"+newSOA+":"+soaParts[1]+":"+soaParts[2]+":"+soaParts[3]+":"+soaParts[4]+":"+ttl.__str__()+"\n")
              outputLine = fqdn+"\t\t\tIN SOA\t"+admin+" "+mname+" (\n\t\t\t\t\t"+newSOA+" ;\n\t\t\t\t\t"+soaParts[1]
	      outputLine = outputLine+" ;\n\t\t\t\t\t"+soaParts[2]+" ;\n\t\t\t\t\t"+soaParts[3]+" ;\n\t\t\t\t\t"+soaParts[4]+" ;\n\t\t\t\t\t)\n"
	      outputLine = outputLine +"\n$ORIGIN "+zoneName+".\n"
	      if readOnly == 0:
	        query = "UPDATE RECORDS SET VALUE='"+newSOA+","+soaParts[1]+","+soaParts[2]+","+soaParts[3]+","+soaParts[4]+"' WHERE RECORDID="+recordid.__str__()
	        c=db.cursor()
	        c.execute(query)
	        c.close()

	    #Handle PTR Records
	    if type == 'PTR':
 	      if(ttl == None):
		ttl=globalttl
	      else:
		ttl = ttl.__str__()
	      if(value[len(value)-1] != "."):
		value = value+"."+zoneName+"."
		#output.write("^"+record+":"+value+ttl+"\n")
	      outputLine = record+"\t\t"+ttl+"\tIN PTR\t"+value+"\n"				  

	    #Handle A Records
	    if type == 'A':
	      if(ttl == None):
	        ttl = globalttl
	      else:
	        ttl = ttl.__str__()
	      #output.write("+"+record+":"+value+":"+ttl.__str__()+"\n")
	      outputLine = record+"\t\t"+ttl+"\tIN A\t"+value+"\n"
	    #Handle A6 Records
	    if type == 'A6':
	      if(ttl == None):
	        ttl = globalttl
	      else:
	        ttl = ttl.__str__()
	      outputLine = record+"\t\t"+ttl+"\t IN A6\t"+value+"\n"
	      
	    #Handle MX Records
	    if type == 'MX':
	      if(ttl == None):
	        ttl=globalttl
	      else:
	        ttl =ttl.__str__()
	      #output.write("@"+record+"::"+value+":"+mxpriority.__str__()+ttl+"\n")
	      outputLine = record+"\t\t"+ttl+"\tIN MX "+mxpriority.__str__()+"\t"+value+"\n"
	    #Handle NS Records
	    if type == 'NS':
	      if(ttl == None):
	        ttl=globalttl
	      else:
	        ttl=ttl.__str__()
	      #output.write("&"+record+"::"+value+ttl+"\n")
	      outputLine=record+"\t\t"+ttl+"\tIN NS\t"+value+"\n"
	    #Handle TXT Records
	    if type == 'TXT':
	      if(ttl==None):
	        ttl=globalttl
	      else:
	        ttl = ttl.__str__()
	      outputLine=record+"\t\t"+ttl+"\tIN TXT\t\""+value+"\"\n"
	      
	      #output.write("'"+record+":"+string.replace(value,":","\\072")+"\n")

	    #Hande HINFO Records
	    if type == 'HINFO':
	      if(ttl==None):
	        ttl=globalttl
	      else:
	        ttl=ttl.__str__()
	      outputLine=record+"\t\t"+ttl+"\tIN HINFO\t"+value+"\n"
	    #Handle CNAME Records
	    if type == 'CNAME':
	      if(ttl == None):
	        ttl=globalttl
	      else:
	        ttl = ttl.__str__()
	      #output.write("C"+record+":"+value+ttl+"\n")
	      outputLine=record+"\t\t"+ttl+"\tIN CNAME\t"+value+"\n"
	    zoneOut.write(outputLine)
	if readOnly == 0:
  	  query = "UPDATE ZONES SET UPDATED=0 WHERE ZONETYPEID=1 AND ZONESTATUSID=1"
	  c=db.cursor()
	  c.execute(query)
	  c.close()

	#Now grab secondary Zones to work on.
	c=db.cursor()
	c.execute("SELECT ZONEID,NAME,UPDATED FROM ZONES WHERE ZONETYPEID=2 AND ZONESTATUSID=1")
	result = c.fetchall()
	c.close()
	for zoneId,zoneName,updated in result:
	  #process secondaries
	  c=db.cursor()
          try:
            c.execute("""SELECT ZOT.NAME,ZO.ZONEOPTIONTYPEID,ZO.VALUE FROM ZONEOPTIONS ZO, ZONEOPTIONTYPE ZOT WHERE ZO.ZONEID="""+zoneId.__str__()+
                      """ AND ZO.ZONEOPTIONTYPEID=ZOT.ZONEOPTIONTYPEID ORDER BY ZONEOPTIONTYPEID""")
	    result2 = c.fetchall()
	  except:
	    result2 = ""
          zoHash={}
          for ztypeName,ztypeId,zValue in result2:
            if ztypeId.__str__() not in zoHash:
              zoHash[ztypeId.__str__()]=zValue
            else:
              zoHash[ztypeId.__str__()]= zoHash[ztypeId.__str__()]+","+zValue
	  
	  output.write("ZONE \""+zoneName+"\" IN {\n")
	  output.write("\ttype slave;\n")
	  val = zoHash.get("2")
          if val != None:
            output.write("\talso-notify {"+string.replace(val,",","; ")+";};\n")
            val = zoHash.get("3")
          if val != None:
            if(val[len(val)-1] != "/"):
              val = val + "/"
          else :
              val=""
          output.write("\tfile \""+relZPath.__str__()+val.__str__()+zoneName.__str__()+"\";\n")
	  c=db.cursor()
	  c.execute("""SELECT IP FROM PRIMARYIP WHERE ZONEID="""+zoneId.__str__())
	  result2=c.fetchall()
	  output.write("\tmasters { "+result2[0][0].__str__()+"; };\n")
          output.write("};\n\n")
				 
	zoneOut.close()
	output.close()
	return



def usage():
	print "Usage: bind9.py [OPTIONS]"
	print "-oFILE,--output-file=FILE: \tWhere FILE is the desired bind 9 conf file (defaults to named.conf)"
	print "-uUSER,--username=USER: \tSet mySQL username"
	print "-pPASS,--password=PASS: \tSet mySQL password"
	print "-HHOST,--dbhost=HOST: \t\tSet mySQL Server hostname (defaults to localhost)"
	print "-DDB,--dbname=DB: \t\tSet Blahz DNS DB name (defaults to blahzdns)"
	print "--override: \t\t\tAllows blank username/password"
	print "--read-only: \t\t\tAllows a readonly client. To not update the Host"
	print "-d,--debug : \t\t\tPrint Increased Debugging to STDOUT"
	print "-h,--help : \t\t\tThis Screen"
	print "-a,--about : \t\t\tAbout bind9.py"
	return

def about():
	print "This python script will output a bind9 named.conf format file"
	print "along with any requiste primary zone files."
	print "Dynamic Zone support has not been completely hashed out yet."
	print "So dynamic zones will currently not be written out."
	return
	
def getConfig(username,password,dbName,dbHost):
	configHash= {}
	db = MySQLdb.connect(host=dbHost,db=dbName,user=username,passwd=password)
        c=db.cursor()
	c.execute("SELECT POT.NAME,PO.VALUE FROM PROGRAMOPTIONTYPE POT,PROGRAMOPTIONS PO WHERE POT.PROGRAMOPTIONTYPEID=PO.PROGRAMOPTIONTYPEID")
	result = c.fetchall()
	for name,value in result:
	  configHash[name] = value
	  configHash[name] = string.replace(configHash[name],"\\\"","\"")
	return configHash
	  
if __name__ == "__main__":
    main()

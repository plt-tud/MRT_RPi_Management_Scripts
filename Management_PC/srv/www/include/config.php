<?php
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) die();

// Quick-Switch to define if
// 0: Testing/Tagging environment is to be used
// 1: Production environment is to be used
define("OPERATING_ENVIRONMENT", 0);

// Data persistency method
// 0: FileDB - Flat file with serializes object
// 1: SqliteDB - PDO based sqlite-database
// 2: MySQL - PDO based mysql server on local host
if (OPERATING_ENVIRONMENT == 0)
  define("PERSISTMETHOD", 1); // Use persistent SQLite DB (slow, even on ramfs...)
else
  define("PERSISTMETHOD", 2); // Use in-memory sqlite db; no info on "old" clients, but pretty fast

// Expiration of devices without heartbeat after n seconds
// (0 to disable)
define("EXPIREDEVICEAFTER", "3000");

// Allow users to manually expire single devices/all devices
if (OPERATING_ENVIRONMENT == 0)
  define("ALLOWMANUALEXPIRE", True); // Allow expiration in testing
else
  define("ALLOWMANUALEXPIRE", False);
  
// Handling of CPU serial numbers by the registerdev.php script
if (OPERATING_ENVIRONMENT == 0) {
  define("SHOWSERIALINLISTING", True);
  define("AUTOGETSERIAL", True);
  define("AUTOLOCKWHENSERIALAQUIRED", True);
}
else {
  define("SHOWSERIALINLISTING", False);
  define("AUTOGETSERIAL", False);
  define("AUTOLOCKWHENSERIALAQUIRED", False);
}

// Paths and fonts
// If FileDB or SQLite is selected for data persistency,
// use this file.
define("DBFILE", "data/devices.db");
define("SDCARDSTATDIRECTORY", "data/sdcards/");
define("WEBROOT","");
define("IMAGEDIRECTORY","data.disk/images/");
define("LABELFONTFILEBOLD","DINBd.ttf");
define("LABELFONTFILELIGHT","UniversLight.ttf");
//define("LABELFONTFILELIGHT","Univers.ttf");

//PDO  and sqlite config Config
if (OPERATING_ENVIRONMENT == 0) {
  define("DBTYPE", "sqlite");
  define("DBPATH", "data/devices.sqlite3");
  define("DBUSER", "pltmrt");
  define("DBPASS", "pltmrt");
}
else {
  define("DBTYPE", "mysql");
  define("DBPATH", "host=localhost;dbname=raspberrymrt");
  define("DBUSER", "pltmrt");
  define("DBPASS", "pltmrt");
}

?>

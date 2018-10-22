<?php
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) die();

require_once("include/config.php");


function checkIdFormat($id) {
  if (strlen($id) != 17)
    return False;
  
  if (preg_match("/[0-9A-FX]{8}-[0-9A-FY]{8}/", $id, $match) == 1)
    return True;
  
  return False;
}

function compareId($idx, $idy) {
  /* Return -1 on formatting error
   * Return 0 on mismatch 
   * Return 1 on perfect match
   * Return 2 on partial match
   */
  
  if (!(checkIdFormat($idx) && (checkIdFormat($idy))))
    return -1;
  
  $retval = 1;
  for($i = 0; $i < 17; $i++) {
    if (strcmp($idx[$i],$idy[$i]) == 0) {
      continue;
    }
    else if ($i < 8) {
      if ( strcmp($idx[$i],"X") == 0 || strcmp($idy[$i],"X") == 0)
        $retval = 2;
      else {
        return 0;
      }
    }
    else {
      if ( strcmp($idx[$i],"Y") == 0 || strcmp($idy[$i],"Y") == 0) {
        // Need at least one partial match, not a complete wildcard
        if ($retval == 2) {
          return 0;
        }
        $retval = 2;
      }
      else {
        return 0;
      }
    }
  }
  return $retval;
}

function rpicmd_process(deviceManager $dmgr, $id, $cmd) {  
  if (!checkIdFormat($id))
    return;
  
  if (strcmp($cmd, "ping") && strcmp($cmd, "echo") && strcmp($cmd, "lock"))
    return;
  
  $rpi = $dmgr->getDeviceBySerial($id);
  if ($rpi == False)
    return;
  
  if (!strcmp($cmd, "ping"))
    $rpi->setCmdPing();
  else if (!strcmp($cmd, "echo"))
    $rpi->setCmdEcho();
  else if (!strcmp($cmd, "lock"))
    $rpi->setCmdLock();
}

Class deviceManager{

  static private $instance = null;
  public $devices;
  public $dbfile;
  public $db;
  
  public function getManager() {
    if (null === self::$instance) {
    if (PERSISTMETHOD == 0)
      self::$instance = new deviceManager_filedb;
    else if (PERSISTMETHOD == 1)
      self::$instance = new deviceManager_sqlite;
    else if (PERSISTMETHOD == 2)
      self::$instance = new deviceManager_mysql;
    else
      die("Persistence managment type " . PERSISTMETHOD . " specified in confiq file in unknown.");   
      }
      return self::$instance;
  }
  
  public function addDevice(raspberry $rpi) {
    $this->devices[] = $rpi;
    return True;
  }
  
  public function destroyDevice() {
  }
  
  public function resetLastSeen() {
    foreach($this->devices as $rpi) {
      $rpi->lastseen = -1;
    }
  }
  
  public function getDeviceBySerial($id) {
    if (!checkIdFormat($id))
      return;
    foreach($this->devices as $rpi) {
      if (compareId($rpi->id, $id) == 1)
        return $rpi;
    } 
    return False;
  }
  
  public function getAliveDevices() {
    $retdev = array();
    if (EXPIREDEVICEAFTER <= 0)
      return $this->devices;
    foreach($this->devices as $rpi) {
      if (EXPIREDEVICEAFTER > 0 && ($rpi->getLastseenDelta() < EXPIREDEVICEAFTER) && strlen($rpi->ip)>0) {
        $retdev[] = $rpi;
      }
    }
    return $retdev;
  }

  public function getDevices() {
    return $this->devices;
  }
}

//------------------------------------------------------------
//----------------Class deviceManager_mysql-------------------
//------------------------------------------------------------

Class deviceManager_mysql extends deviceManager {
  public function __construct() {
    $this->devices = array();
    $this->db = new PDO(DBTYPE . ":" . DBPATH, DBUSER, DBPASS);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      $statement = $this->db->prepare("SELECT * FROM tabledevices");
      $statement->execute();
      $this->readDBFile();
    }
    catch(PDOException $e) {
      $this->db->exec("CREATE TABLE IF NOT EXISTS tabledevices ( deviceid VARCHAR(17) PRIMARY KEY, lastip VARCHAR(100), cpusn VARCHAR(100), lastseen INTEGER, doCmd INTEGER)  ENGINE=MEMORY");
    }
    $this->db = NULL;
  }

  private function readDBFile() {

    $statement = $this->db->prepare("SELECT * FROM tabledevices");
    $statement->execute();
    $pis = $statement->fetchAll();
    
    foreach($pis as $row) {
      $newpi = new raspberry("");
      $newpi->id = $row['deviceid'];
      $newpi->ip = $row['lastip'];
      $newpi->cpuserial = $row['cpusn'];
      $newpi->lastseen  = intval($row['lastseen']);
      $newpi->doCmd     = intval($row['doCmd']);
      $this->devices[] = $newpi;
    }
  }

  
  private function writeDBFile() {
    $this->db = new PDO(DBTYPE . ":" . DBPATH, DBUSER, DBPASS);
    foreach($this->devices as $rpi) {
      # Only write back updated (alive) devices or expired ones
      if (($rpi->getLastseenDelta() < EXPIREDEVICEAFTER) || $rpi->lastseen <= 0) {
        $cmd = "SELECT COUNT(*) FROM tabledevices WHERE deviceid = :id";
        $statement = $this->db->prepare($cmd);
        $statement->bindParam(':id', $rpi->id);
        $statement->execute();
        if($statement->fetchColumn() > 0){
          $cmd = "UPDATE tabledevices SET lastip = :ip, cpusn = :cpuserial, lastseen = :lastseen, doCmd = :doCmd WHERE deviceid = :id";
        }
        else {
          $cmd = "INSERT INTO tabledevices (deviceid,lastip,cpusn,lastseen,doCmd) VALUES ( :id, :ip, :cpuserial, :lastseen, :doCmd)";
        }
        $statement = $this->db->prepare($cmd);
        $statement->bindParam(':id', $rpi->id);
        $statement->bindParam(':ip', $rpi->ip);
        $statement->bindParam(':cpuserial', $rpi->cpuserial);
        $statement->bindParam(':lastseen', $rpi->lastseen);
        $statement->bindParam(':doCmd', $rpi->doCmd);
        $statement->execute();
      }
    }
    $db = NULL;
  }

  public function __destruct() {
    $this->writeDBFile();
  }

}


//------------------------------------------------------------
//----------------Class deviceManager_sqlite------------------
//------------------------------------------------------------

Class deviceManager_sqlite extends deviceManager {
  public function __construct() {
    $this->devices = array();
    $this->db = new PDO(DBTYPE . ":" . DBPATH,DBUSER,DBPASS);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
      $statement = $this->db->prepare("SELECT * FROM tabledevices");
      $statement->execute();

      $this->readDBFile();
    }
    catch(PDOException $e) {
      $this->db->exec("CREATE TABLE IF NOT EXISTS tabledevices ( deviceid TEXT PRIMARY KEY, lastip TEXT, cpusn TEXT, lastseen INTEGER, doCmd INTEGER)");
    }
    $this->db = NULL;
  }

  private function readDBFile() {

    $statement = $this->db->prepare("SELECT * FROM tabledevices");
    $statement->execute();
    $pis = $statement->fetchAll();
    
    foreach($pis as $row) {
      $newpi = new raspberry("");

      $newpi->id = $row['deviceid'];
      $newpi->ip = $row['lastip'];
      $newpi->cpuserial = $row['cpusn'];
      $newpi->lastseen  = intval($row['lastseen']);
      $newpi->doCmd     = intval($row['doCmd']);

      $this->devices[] = $newpi;
    }
  }

  
  private function writeDBFile() {
    $db = new PDO(DBTYPE . ":" . DBPATH, DBUSER, DBPASS);
    foreach($this->devices as $rpi) {
      # Only write back updated (alive) devices or expired ones
      if (($rpi->getLastseenDelta() < EXPIREDEVICEAFTER) || $rpi->lastseen <= 0) {
        $cmd = "SELECT COUNT(*) FROM tabledevices WHERE deviceid = :id";
        $statement = $db->prepare($cmd);
        $statement->bindParam(':id', $rpi->id);
        $statement->execute();
        
        if($statement->fetchColumn() > 0){
          $cmd = "UPDATE tabledevices SET lastip = :ip, cpusn = :cpuserial, lastseen = :lastseen, doCmd = :doCmd WHERE deviceid = :id";
        }
        else {
          $cmd = "INSERT INTO tabledevices (deviceid,lastip,cpusn,lastseen,doCmd) VALUES ( :id, :ip, :cpuserial, :lastseen, :doCmd)";
        }
        $statement = $db->prepare($cmd);
        $statement->bindParam(':id', $rpi->id);
        $statement->bindParam(':ip', $rpi->ip);
        $statement->bindParam(':cpuserial', $rpi->cpuserial);
        $statement->bindParam(':lastseen', $rpi->lastseen);
        $statement->bindParam(':doCmd', $rpi->doCmd);
        $statement->execute();
      }
    }
    $db = NULL;
  }

  public function __destruct() {
    $this->writeDBFile();
  }

}

//------------------------------------------------------------
//----------------Class deviceManager_filedb------------------
//------------------------------------------------------------

Class deviceManager_filedb extends deviceManager {
  public function __construct() {
    $this->devices = array();
    if (!($this->dbfile = fopen(DBFILE, "r+")))
      die("Database File cannot be opened");
    $this->readDBFile();
    fclose($this->dbfile);
  }

  private function readDBFile() {
    while ( $dbline = fgets($this->dbfile)) {
      $this->devices[] = unserialize($dbline);
    }
  }
  
  private function writeDBFile() {
    foreach($this->devices as $rpi) {
      if (EXPIREDEVICEAFTER > 0 && ($rpi->getLastseenDelta() < EXPIREDEVICEAFTER)) {
        fwrite($this->dbfile, serialize($rpi)."\n");
      }
    }
  }
  
  public function __destruct() {
    if (!($this->dbfile = fopen(DBFILE, "w+")))
      die("Database File cannot be opened");
    $this->writeDBFile();
    fclose($this->dbfile);
  }
}

//----------------------------------------------------------------------
//----------------------------------------------------------------------
//----------------------------------------------------------------------

Class raspberry {
  public  $id;
  public  $cpuserial;
  public  $ip;
  public  $lastseen = -1;
  public  $doCmd = 0;
  private $rpiCmdSet = array( 0 => "OK <br>", 1 => "PING <br>", 2 => "ECHO <br>", 3 => "LOCK <br>" );

  public function __construct($cpuserial) {
    $this->rpiCmdSet = array( 0 => "OK <br>", 1 => "PING <br>", 2 => "ECHO <br>", 3 => "LOCK <br>" );
    $this->doCmd = 0;
    $this->cpuserial = $cpuserial;
    $this->lastseen = 0;
    $this->ip = "";
  }
  
  public function getCmd() {    
    if ($this->doCmd != 0) {
      foreach($this->rpiCmdSet as $cmdn => $cmds) {
        if ($this->doCmd == $cmdn)
          $retval = $cmds;
      }
      $this->doCmd = 0;
      return $retval;
    }
    return "OK";
  } // ends doCmd
  
  public function setIP($ip) {
    $this->ip = $ip;
  }
  
  public function passwordFromSerial() {
    if (strlen($this->cpuserial) < 6)
      return '*******';
    return substr($this->cpuserial, strlen($this->cpuserial) - 6, strlen($this->cpuserial));
  }

  public function updateLastseen() {
    date_default_timezone_set('UTC');
    $date = new DateTime();  
    $this->lastseen  = $date->getTimestamp();
  }
  
  public function getLastseenDelta() {
    date_default_timezone_set('UTC');
    $date = new DateTime();  
    if ($this->lastseen <= 0)
      return "Never";
    return $date->getTimestamp() - $this->lastseen;
  }
  
  public function setCmdPing() {
    if ($this->doCmd != 0) return False;
    $this->doCmd = 1;
    return True;
  }
  
  public function setCmdEcho() {
    if ($this->doCmd != 0) return False;
    $this->doCmd = 2;
    return True;
  }
  
  public function setCmdLock() {
    if ($this->doCmd != 0) return False;
    $this->doCmd = 3;
    return True;
  }
  
  public function printListEntry() {
  
  }
}

?>

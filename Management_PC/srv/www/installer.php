<?php
require_once("include/config.php");
require_once("include/classes.php");
require_once("include/htmlPrinter.php");

function main() {
  session_start();
  if (!isset($_SESSION['view']))
    $_SESSION['view']="DeviceListing";
  if (!in_array($_SESSION['view'], array('DeviceListing','Scan','CloneSD')))
    $_SESSION['view']="DeviceListing";
  
  $dmgr = deviceManager::getManager();
  
  if (isset($_GET['expire'])) {
    if (trim($_GET['expire']) == "all")
      $dmgr->resetLastSeen();
    else {
      if (trim($_GET['expire']) == "device" && isset($_GET['deviceId']) && checkIdFormat(trim($_GET['deviceId']))) {
	$dev = $dmgr->getDeviceBySerial(trim($_GET['deviceId']));
	if ($dev != False)
	  $dev->lastseen = -1;
      }
    }
  }
  
  printHTML_header("This is a title");
  printMenuBar();
  printInstallProcesses();
  printTrailer();
  printHTML_tail();
  
  if(isset($_GET["deviceId"]) || isset($_GET["rpicmd"])) {
    $id  = trim($_GET["deviceId"]);
    $cmd = trim($_GET["rpicmd"]);
  
    if(isset($_GET["deviceId"]) && isset($_GET["rpicmd"])) 
      rpicmd_process($dmgr, $id, $cmd);
  }
}

main();
?>

<?php
require_once("include/config.php");
require_once("include/classes.php");
require_once("include/htmlPrinter.php");

$id = $_GET["deviceId"];

if (checkIdFormat($id) == False) {
  echo "ERROR";
  exit;
}

date_default_timezone_set('UTC');
$date = new DateTime();

$dmgr = deviceManager::getManager();
$newdev  = new raspberry("");
$newdev->id = $id;

$ip="";
if (strlen($_SERVER['REMOTE_ADDR']) == 0)
  $ip = trim($_SERVER['HTTP_CLIENT_IP']);
else
  $ip = trim($_SERVER['REMOTE_ADDR']);

if (isset($_GET["serial"]))
  $newdev->cpuserial = trim($_GET["serial"]);


$newdev->setIP($ip);
foreach ($dmgr->getDevices() as $dev) {
  if (compareId($id, $dev->id) == 1) {
    $dev->setIP($ip);
    $dev->updateLastseen();
    if (isset($_GET["serial"])) {
      $dev->cpuserial = trim($_GET["serial"]);
      if (AUTOLOCKWHENSERIALAQUIRED)
        $dev->setCmdLock();
    }
    echo $dev->getCmd();
    exit;
  }
}
$newdev->lastseen = $date->getTimestamp();
if(AUTOGETSERIAL) {
  $newdev->setCmdEcho();
}
$dmgr->addDevice($newdev);


if (AUTOLOCKWHENSERIALAQUIRED && OPERATING_ENVIRONMENT==1) {
    echo "LOCK new\n";
}
echo "OK new\n";
echo "<br>";

?>

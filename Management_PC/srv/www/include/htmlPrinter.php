<?php
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) die();

function printHTML_header($title="") {
  echo "<!doctype html>";
  echo "<html class=\"no-js\" lang=\"en\">";
  echo "<head>";
  echo "<meta charset=\"utf-8\" />";
  echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />";
  
  # return to the last page, but don't pass parameters a second time
  $uri_parts = explode('?', $_SERVER['REQUEST_URI']);
  echo "<meta http-equiv=\"refresh\" content=\"30;url=\"http://".$_SERVER['HTTP_HOST']."".$uri_parts[0]."\" >";
  echo "<title>MRT Raspberry Pi</title>";
  echo "<link rel=\"stylesheet\" href=\"css/foundation.css\" />";
  
  echo "<script src=\"js/vendor/jquery.js\"></script>";
  echo "<script src=\"js/foundation/foundation.js\"></script>";
  echo "<script src=\"js/foundation/foundation.dropdown.js\"></script>";
  
  echo "<script src=\"js/vendor/jquery.liveFilter.js\"></script>";
  echo "<script>";
  echo "$(function(){";
  echo "  var liveFilter = $('#tbl_devices').liveFilter('.livefilter-input', 'tr.tbl_device_row', {";
  echo "   filterChildSelector: 'td.tbl_device_id'";
  echo "  });";
  echo "});";
  echo "</script>";
  
  echo "</head>";
  echo "<body>";
}

function printMenuBar() {
  echo "<div>";
  echo "<nav class=\"top-bar\" data-topbar role=\"navigation\">";
  
  echo "<ul class=\"title-area\">";
  echo "<li class=\"name\"><h1><a>PLT MRT WebIf</a></h1></li>";
  echo "</ul>";
  
  echo "<section class=\"top-bar-section\">";
  echo "<ul class=\"left\">";
  echo "<li class=\"has-dropdown\">";
  echo "<a href=\"#\">Interface</a>";
  echo "<ul class=\"dropdown\">";
  echo "<li class=\"active\"><a href=\"index.php\">Active Device Listing</a></li>";
  echo "<li><a href=\"installer.php\">SDCard Cloning</a></li>";
  echo "<li><a href=\"#\">Scan & Go </a></li>";
  if(SHOWSERIALINLISTING) {
    echo "<li><a href=\"getlabel.php?batch=all\">Generate Label Batch</a></li>";
  }
  if(ALLOWMANUALEXPIRE) {
    echo "<li><a href=\"index.php?expire=all\">Expire all devices</a></li>";  
  }
  echo "</ul>";
  echo "</li>";
  echo "</ul>";
  
  echo "</section>";
  echo "</nav>";
  echo "</div>";
}

function printProgessBar($percentage, $msg="") {
  echo "<div class=\"progress [small-# large-#] [radius round]\">";
  echo "  <span class=\"meter\" style=\"width: " . $percentage . "%\"><center>$msg</center></span>";
  echo "</div>";
}

function printInstallProcesses() {
 echo "<hr/>";
  echo "<div class=\"row\">";
  
  echo "<div class=\"large-2 medium-2 columns\">";
  echo "<p>Device Name</p>";
  echo "</div>";
  echo "<div class=\"large-4 medium-4 columns\">";
  echo "<p>Installation Progress</p>";
  echo "</div>";
  echo "<div class=\"large-4 medium-4 columns\">";
  echo "<p>Phase</p>";
  echo "</div>";
  echo "</div>";
  echo "<hr/>";
  
  $runningprocs = scandir(SDCARDSTATDIRECTORY);
  if ($runningprocs == FALSE) 
    return;
    
  foreach ($runningprocs as $proc ) {
    // Only open files
    if (! is_file(SDCARDSTATDIRECTORY . $proc))
      continue;
    // Do not open _dev_name.log files in this context
    if (preg_match("/\.log$/", $proc))
        continue;
    $statsfile = fopen(SDCARDSTATDIRECTORY . $proc, "r");
    $status = explode(";", fgets($statsfile));
    fclose($statsfile);
    
    // If this device has a _dev_name.log file with extra logging info, display it as tooltip
    if (is_file(SDCARDSTATDIRECTORY . $proc . ".log")) {
      $extStatusFile = fopen(SDCARDSTATDIRECTORY . $proc . ".log", "r");
      $extStatus = "Logfile contents ";
      // We can only show a one-liner in the tooltip; concatenate all lines
      while ( $line = fgets($extStatusFile)) {
          $extStatus = $extStatus . "; " . $line;
      }
      fclose($extStatusFile);
    }
    else {
      $extStatus = "No extra status info for this process found.";
    }
    
    echo "<div class=\"row\">";
    echo "<div class=\"large-2 medium-2 columns\">";
    echo $status[1];
    echo "</div>";
    echo "<div class=\"large-4 medium-4 columns\">";
    $percentage = ceil(100*($status[0]/6));
    printProgessBar($percentage, $percentage . "%" );
    echo "</div>";
    echo "<div class=\"large-4 medium-4 columns\">";
    $status[2] = trim(preg_replace('/\r\n|\r|\n/', '', $status[2]));
    $status[2] = trim(preg_replace('/\'|\"|\`/', '', $status[2]));
    echo "<span data-tooltip aria-haspopup=\"true\" class=\"has-tip\" title=\"" . $extStatus . "\">" . $status[2] . "</span>";
    echo "</div>";
    echo "</div>"; 
  }
}

function printDeviceList(deviceManager $manager) {
  echo "<input width=\"200\" class=\"livefilter-input\" type=\"text\" placeholder=\"Search DeviceId\">";
  
  echo "<table id=\"tbl_devices\">";
  echo "<thead>";
  echo "<tr>";
  echo "<td width=\"200\">Device ID</td>";
  echo "<td>Last known IP</td>";
  if(SHOWSERIALINLISTING == 1) {
    echo "<td width=\"150\">CPU SerialNo</td>";
  }
  echo "<td width=\"150\">Last Seen</td>";
  echo "<td width=\"150\">Actions</td>";
  echo "</tr>";
  echo "</thead>";
  
  echo "<tbody>";
  foreach($manager->getAliveDevices() as $dev) {
    deviceToHTMLDiv($dev);
  }
  echo "</tbody>";
  echo "</table>";
}

function printTrailer() {
  echo "<div class=\"row\">";
  echo "</div>";
}

function deviceToHTMLDiv(raspberry $dev) {
  
  echo "<tr class=\"tbl_device_row\">";
  
  echo "<td class=\"tbl_device_id\"><code>$dev->id</code></td>";
  if (strlen($dev->ip)>0)
    echo "<td>$dev->ip</td>";
  
  if(SHOWSERIALINLISTING) {
    echo "<td>$dev->cpuserial</td>";
  }
  
  echo "<td>" . $dev->getLastseenDelta() . "</td>";
  echo "<td width=400>";
  
  echo "<a class=\"button tiny radius\" href=\"index.php?deviceId=" . $dev->id .  "&rpicmd=ping\" >Blink</a>";
  if(SHOWSERIALINLISTING) {
    echo "<a class=\"button tiny radius\" href=\"index.php?deviceId=" . $dev->id .  "&rpicmd=echo\">Serial</a>";
    echo "<a class=\"button tiny radius\" href=\"getlabel.php?deviceId=" . $dev->id .  "&serial=" . $dev->cpuserial . "\">Label</a>";
  }
  echo "<a class=\"button tiny radius\" href=\"index.php?deviceId=" . $dev->id .  "&rpicmd=lock\" >Lockdown</a>";
  if(ALLOWMANUALEXPIRE) {
    echo "<a class=\"button tiny radius\" href=\"index.php?expire=device&deviceId=" . $dev->id .  "\" >Expire</a>";
  }

  echo "</td>";
  echo "</tr>";
}

function printHTML_tail() {
  echo "<script>";
  echo "$(document).foundation();";
  echo "$(document).foundation('dropdown', 'reflow');";
  echo "</script>";
  echo "</body>";
  echo "</html>";
}

?>

<?php
require_once("include/config.php");
require_once("include/classes.php");
require_once("include/htmlPrinter.php");
require_once("include/phpqrcode/qrlib.php");

function imageStringWidth($data, $fontsize, $font) {
  $x = imagettfbbox( $fontsize , 0,  WEBROOT . IMAGEDIRECTORY . $font , $data);
  return array(abs(($x[4]-$x[0])), abs($x[5]-$x[0]));
}

function placeString($img, $x, $y, $string, $fontsize, $color, $font) {
  $fontdim = array(imagefontwidth($fontsize), imagefontheight($fontsize));
  
  //int $col , string $fontfile , string $text )
  imagettftext($img, $fontsize, 0, $x, $y, $color, WEBROOT . IMAGEDIRECTORY . $font, $string); 
}

function buildLabel(raspberry $rpi) {
  $QRTEXTGAP = 20;
  $PARAGRAPHINDENT = 8;
  $HEADSKIP  = 14;
  $QRPXSIZE  = 7;
  $globalFontsize = 30;
  $framewidth = 3;
  $imgpath = WEBROOT . IMAGEDIRECTORY;
  
  // Create a QR-Code if it does not exist
  //if (!file_exists($imgpath . $rpi->id . '-qr.png'))
    QRcode::png($rpi->id, $imgpath . $rpi->id . '-qr.png', 'H', $QRPXSIZE, 2 );
  $qrsize = getimagesize($imgpath . $rpi->id . '-qr.png' );
  
  // Load the QR-Code and use it to guestimate the necessary label size
  $qr  = imagecreatefrompng($imgpath . $rpi->id . '-qr.png');
  $tustringdim = imageStringWidth("Device ID: AABBCCDDEE-AABBCCDDEE", $globalFontsize, LABELFONTFILEBOLD);
  
  // Create Label with white BG
  $img = imagecreate($qrsize[0] + $tustringdim[0] + $QRTEXTGAP, $qrsize[1]);
  imagecolorallocate($img, 255, 255, 255); // NOTE: First call is always bg-color
  
  // Copy QR Code onto new label
  imagecopy($img, $qr, 0, 0, 0, 0, $qrsize[0], $qrsize[1]);
  imagedestroy($qr);
  
  // Place the strings on the image
  placeString($img, $qrsize[0], $tustringdim[1]*1 + $HEADSKIP + $PARAGRAPHINDENT*0,   "Technische Universität Dresden", $globalFontsize, imagecolorallocate($img, 0, 0, 0), LABELFONTFILEBOLD);
  placeString($img, $qrsize[0], $tustringdim[1]*2 + $HEADSKIP + $PARAGRAPHINDENT*1,   "Lehrstuhl für Prozessleittechnik", $globalFontsize, imagecolorallocate($img, 0, 0, 0), LABELFONTFILEBOLD);
  placeString($img, $qrsize[0], $tustringdim[1]*3 + 2*$HEADSKIP + $PARAGRAPHINDENT*2, "Device ID: " . $rpi->id, $globalFontsize, imagecolorallocate($img, 0, 0, 0), LABELFONTFILELIGHT);
  placeString($img, $qrsize[0], $tustringdim[1]*4 + 3*$HEADSKIP + $PARAGRAPHINDENT*3, "Password: " . $rpi->passwordFromSerial(), $globalFontsize, imagecolorallocate($img, 0, 0, 0), LABELFONTFILELIGHT);
  
  
  if ($framewidth > 0) {
    for ($i=1; $i<=$framewidth; $i++) {
      imagerectangle($img, $i, $i, imagesx($img)-($i),imagesy($img)-($i), imagecolorallocate($img, 0, 0, 0)  );
    }
  }
  
  header('Content-Type: image/png');
  
  return $img;
  //imagepng($img, $imgpath . "label-wserial-" . $rpi->id. ".png", 5);
}

function getLabelBatch(deviceManager $dmgr) {
  // Pixel per centimeter @ resolution
  $DPCM   = 300/2.54;
  // Paper dimensions in cm
  $BORDER = 0.5;
  $PAPERWIDTH  = 21;
  $PAPERHEIGHT = 29.7;  
  $img = imagecreate(($PAPERWIDTH-2*$BORDER)*$DPCM, ($PAPERHEIGHT-2*$BORDER)*$DPCM);
  
  header('Content-Type: image/png');
  $lblcount=0;
  $xpos=0;
  $ypos=0;
  $rotate=270;
  foreach($dmgr->getAliveDevices() as $rpi) {
    $lbl = buildLabel($rpi);
    $lblsz = array(imagesx($lbl), imagesy($lbl));
    
    if ($rotate != 0) {
      $lbl   = imagerotate($lbl, 270, 0);
      $lblsz = array(imagesx($lbl), imagesy($lbl));
    }
    
    imagecopy($img, $lbl, $xpos, $ypos, 0, 0, $lblsz[0], $lblsz[1]);
    
    
    imagedestroy($lbl);
    
    $lblcount=$lblcount+1;
    $xpos=$xpos + $lblsz[0];
    if ($xpos + $lblsz[0] > ($PAPERWIDTH-2*$BORDER)*$DPCM) {
      $xpos = 0;
      $ypos = $ypos + $lblsz[1];
      if ($ypos + $lblsz[1] > ($PAPERHEIGHT-2*$BORDER)*$DPCM) {
	if ($rotate != 0)
	  $rotate=0;
	else {
	  break;
	}
      }
    }
  }
  imagepng($img);
  imagedestroy($img);
}

$devid = "ABCDABCD-ABCDABCD";
$serial = "123456";

if (isset($_GET['batch'])) {
  $dmgr = deviceManager::getManager();
  getLabelBatch($dmgr);
}
else {
  if (isset($_GET['deviceId'])) 
    if (checkIdFormat(trim($_GET['deviceId'])))
      $devid = trim($_GET['deviceId']);

  if (isset($_GET['serial'])) 
      $serial = trim($_GET['serial']);

  $mypi = new raspberry($serial);
  $mypi->id = $devid ;
  $img = buildLabel($mypi);

  imagepng($img);
  imagedestroy($img);
}

?>

#!/bin/bash

WEB_STATUS_DIR="/srv/www/data/sdcards"
RASPBIAN_DOWNLOAD_URI="https://downloads.raspberrypi.org/raspbian_lite_latest"

function usage() {
  echo "Usage: "
  echo ""
  echo "  install_raspbian <path_to_raspbian_image> <path_to_device> <path_to_pltupdate>"
  echo "or"
  echo "  install_raspbian <path_to_device> <path_to_pltupdate>"
  echo ""
  echo "The former will use that explicit image for installation."
  echo ""
  echo "The later form will attempt to use the first file conforming to '*raspbian*.img', preferring any images with 'lite' in their name. If none are found, the script will attempt to download the latest raspbian and use that."
  echo ""
}

function assertOK() {
  DEV="$2"
  log_actionAppend "$DEV" "$1"
  if [ ! "$1" = "OK" ]; then
    if [ -b "$DEV" ]; then
      waitForDeviceRemoval "$DEV"
      log_remove "$DEV"
    fi
    exit 255
  fi
}


function unmountIfMounted() {
  DEV="$1"
  
  if [ -z "$DEV" ]; then
    return 1
  fi
  
  if [ ! -b "$DEV" ]; then
    return 1
  fi
  
  log_action 0 "$DEV" "Unmounting $DEVICE partitions if mounted..."
  ESCDEV=`echo $DEV | awk '{gsub("/","\\\\/"); print}'`
  RETVAL="OK"
  for PART in `mount | awk '/^'"$ESCDEV"'/ {print $1}'`; do
    # Some distros mount a partition in multiple points, which would lead
    # to a 'not mounted' error. So check if the partition is still mounted
    ESCPART=`echo $PART | awk '{gsub("/","\\\\/"); print}'`
    PARTCHECK=`mount | awk '/^'"$ESCPART"'/ {print $1}'`
    if [ -n "$PARTCHECK" ]; then
      umount $PART || RETVAL="FAIL"
      sleep 1
    fi
  done
  assertOK "$RETVAL" "$DEV"
  
  return 0
}

function waitForDeviceRemoval() {
  DEV="$1"
  
  if [ -z "$DEV" ]; then
    return 1
  fi
  
  if [ ! -b "$DEV" ]; then
    return 1
  fi

  while [ -b "$DEV" ]; do
    sleep 5
  done
  
  return 0
}

function log_action() {
  PHASE="$1"
  DEV="$2"
  MSG="$3"
  
  echo -n "$MSG"
  
  if [ -d "$WEB_STATUS_DIR" ]; then
    WEBLOGFILE=`echo $DEV | awk '{gsub("/","_"); print}'`
    echo -n "$PHASE;$DEV;$(date +%H:%M) $MSG" > "$WEB_STATUS_DIR/$WEBLOGFILE"
  fi
  return
}

function log_actionAppend() {
  DEV="$1"
  MSG="$2"
  
  echo "$MSG"
  
  if [ -d "$WEB_STATUS_DIR" ]; then
    WEBLOGFILE=`echo $DEV | awk '{gsub("/","_"); print}'`
    echo "$MSG" >> "$WEB_STATUS_DIR/$WEBLOGFILE"
  fi
  return
}

function log_remove() {
  DEV="$1"
  if [ -d "$WEB_STATUS_DIR" ]; then
    WEBLOGFILE=`echo $DEV | awk '{gsub("/","_"); print}'`
    if [ -f "$WEB_STATUS_DIR/$WEBLOGFILE" ]; then
      rm -f "$WEB_STATUS_DIR/$WEBLOGFILE"
    fi
  fi
  return
}

function cleanup() {
  DEV="$1"
  echo "Signal received."
  log_remove "$DEV"
  if [ -f "$LOGFILE" ] ; then
    rm "$LOGFILE"
  fi
  exit 0
}

function getRaspbianImage() {
    SCRIPTDIR=`dirname $0`
    
    RASPBIANIMG=`ls *.img 2>&1 | grep -i raspbian | head -n 1`
    RASPBIANLITEIMG=`ls *.img 2>&1 | grep -i raspbian | grep -i lite | head -n 1`
    
    # Prefer any found (lite) image over download
    if [ -n "${RASPBIANLITEIMG}" ]; then
        echo "$RASPBIANLITEIMG"
        return
    elif [ -n "${RASPBIANIMG}" ]; then
        echo "$RASPBIANIMG"
        return
    fi
    
    # No image found in current directory?
    if [ ! -f ./raspbian_lite_latest.zip ]; then
        echo "Failed to detect Raspbian Image, attempting to download..." 1>&2
        DOWNLOADOK=0
        wget "$RASPBIAN_DOWNLOAD_URI" -O raspbian_lite_latest.zip > /dev/null 2>&1 && DOWLOADOK=1
        
        if [ ! $DOWNLOADOK -eq 1 ]; then
            echo "Download failed. No image available for installation. Terminating." 1>&2
            exit 1
        fi
    fi
    
    unzip ./raspbian_lite_latest.zip
    # Always remove the file - in case download was interrupted and zip is partial, this will retrigger download
    rm ./raspbian_lite_latest.zip
    
    RASPBIANIMG=`ls *.img 2>&1 | grep -i raspbian | head -n 1`
    RASPBIANLITEIMG=`ls *.img 2>&1 | grep -i raspbian | grep -i lite | head -n 1`
    # Prefer any found (lite) image over download
    if [ -n "${RASPBIANLITEIMG}" ]; then
        echo "$RASPBIANLITEIMG"
        return
    elif [ -n "${RASPBIANIMG}" ]; then
        echo "$RASPBIANIMG"
        return
    fi
    
    echo "Failed to detect image after unzipping download (or unzipping failed...). Terminating." 1>&2
    exit 1
}

##==================================================================================
## Execution starts here
##

if   [ "$#" -eq 3 ]; then
    # All data has been supplied
    IMGFILE=$1;
    DEVICE=$2;
    UPDATE=$3;
elif [ "$#" -eq 2 ]; then
    # Autodetect or download latest raspbian image
    DEVICE=$1;
    UPDATE=$2;
    IMGFILE=`getRaspbianImage`
    echo "Autodetected image: Using $IMGFILE to setup $DEVICE using patch $UPDATE"
else
  usage
  exit 1
fi

# Some safeguards
if [ ! `id -u` -eq 0 ]; then
  echo "You must be root to execute this script."
  exit 1
fi

if [ ! -f "$IMGFILE" ]; then
  echo "File $IMGFILE does not exist";
  exit 1
fi

if [ ! -f "$UPDATE" ]; then
  echo "Update archive $UPDATE does not exist";
  exit 1
fi

DEVICENAME=`basename $DEVICE`
if [[ "$DEVICENAME" =~ ^mmc ]]; then
  DEVICENAME=`echo "$DEVICENAME" | sed -r 's/p[0-9]+$//g'`
else
  DEVICENAME=`echo "$DEVICENAME" | sed -r 's/[0-9]+$//g'`
fi

if [ ! -b "$DEVICE" ] ; then
  echo "Device $DEVICE is not a valid block device";
  exit 1
elif [[ "$DEVICE" =~ ("$DEVICENAME"+p[0-9]+|sd.+[0-9]+)$ ]] ; then
  echo "Device $DEVICE appears to be a partition, not a device."
  exit 1
elif [[ "$DEVICE" =~ (sda|hd.)[0-9]*$ ]] ; then
  echo "Device $DEVICE appears to be a primary disk/harddisk.";
  exit 1
fi

LOGFILE=`echo $DEVICE | awk '{gsub("/","_"); print}'`
LOGFILE="$WEB_STATUS_DIR/$LOGFILE.log"
if [ -d "$WEB_STATUS_DIR" ]; then
    echo "$(date +%H:%M) Started" > "$LOGFILE"
fi
  
# 1: Install raspbian image
# ---------------------------------------------------------------------------------
if [[ ! "$IMGFILE" =~ [0-9-]+raspbian[a-z-]*.img$ ]]; then
  echo "Image file $IMGFILE is not named like a raspbian image... could be invalid."
  exit 1
fi

log_action 1 "$DEVICE" "Unmounting partition if mounted..."
unmountIfMounted "$DEVICE"

RETVAL="OK"
log_action 1 "$DEVICE" "Copying image..."
if [ -f "$LOGFILE" ]; then
  dd if="$IMGFILE" of="$DEVICE" bs=4M conv=noerror > "$LOGFILE" 2>&1 || RETVAL="FAIL"
else
  dd if="$IMGFILE" of="$DEVICE" bs=4M conv=noerror > /dev/null 2>&1 || RETVAL="FAIL"
fi
assertOK "$RETVAL" "$DEVICE"

sync
sleep 2


# 2: Repartitioning
# ---------------------------------------------------------------------------------
unmountIfMounted $DEVICE

ROOTPARTSTART=`echo -e "p\nq\n" | fdisk $DEVICE | awk '/^\/dev\/'$DEVICENAME'p?2/ {print $2}'`
ROOTPARTNAME=`echo -e "p\nq\n" | fdisk $DEVICE | awk '/^\/dev\/'$DEVICENAME'p?2/ {print $1}'`
BOOTPARTNAME=`echo -e "p\nq\n" | fdisk $DEVICE | awk '/^\/dev\/'$DEVICENAME'p?1/ {print $1}'`

log_action 2 "$DEVICE" "Repartitioning... (root $ROOTPARTNAME; starts at cyl. $ROOTPARTSTART)..."
if [ -f "$LOGFILE" ]; then
  echo -e "d\n2\nn\np\n2\n$ROOTPARTSTART\n\nw\n" | fdisk "$DEVICE" >> "$LOGFILE" 2>&1
else
  echo -e "d\n2\nn\np\n2\n$ROOTPARTSTART\n\nw\n" | fdisk "$DEVICE" > /dev/null 2>&1
fi

if [ $? -gt 1 ]; then
  RETVAL="FAIL"
fi
assertOK "$RETVAL" "$DEVICE"

# 3: Integrity check
# ---------------------------------------------------------------------------------
unmountIfMounted "$DEVICE"
log_action 3 "$DEVICE" "Checking integrity..."
if [ -f "$LOGFILE" ]; then
  e2fsck -p -f "$ROOTPARTNAME" > "$LOGFILE" 2>&1
else
  e2fsck -p -f "$ROOTPARTNAME" > /dev/null 2>&1
fi
if [ ! $? -eq 0 ]; then
  RETVAL="FAIL"
fi
assertOK "$RETVAL" "$DEVICE"

# 4: Resize
# ---------------------------------------------------------------------------------
log_action 4 "$DEVICE"  "Resizing disk..."
if [ -f "$LOGFILE" ]; then
  resize2fs "$ROOTPARTNAME" > "$LOGFILE" 2>&1 || RETVAL="FAIL" 
else
  resize2fs "$ROOTPARTNAME" > /dev/null 2>&1 || RETVAL="FAIL" 
fi
assertOK "$RETVAL" "$DEVICE"

# 5: Remount and Patch
# ---------------------------------------------------------------------------------
log_action 5 "$DEVICE" "Remounting installation..."
TMPDIR=`mktemp -d`
if [ -f "$LOGFILE" ]; then
  mount "$ROOTPARTNAME" "$TMPDIR" > "$LOGFILE" 2>&1 ||  RETVAL="FAIL" 
else
  mount "$ROOTPARTNAME" "$TMPDIR" > /dev/null 2>&1  ||  RETVAL="FAIL" 
fi
assertOK $RETVAL  $DEVICE
log_action 5 "$DEVICE" "Extracting Patch..."

# Copy and extract patch
cp "$UPDATE" "$TMPDIR" ||  RETVAL="FAIL" 
UPDATENAME=`basename $UPDATE`
if [ -f "$LOGFILE" ]; then
  tar -C "$TMPDIR" -xzf "$TMPDIR/$UPDATENAME" > "$LOGFILE" 2>&1 ||  RETVAL="FAIL" 
else
  tar -C "$TMPDIR" -xzf "$TMPDIR/$UPDATENAME"  > /dev/null 2>&1 ||  RETVAL="FAIL" 
fi
rm "$TMPDIR/$UPDATENAME" ||  RETVAL="FAIL"

# Disable raspi-config at boot (excerpt from raspi-config)
if [ -e "$TMPDIR/etc/profile.d/raspi-config.sh" ]; then
  rm -f "$TMPDIR/etc/profile.d/raspi-config.sh"
  sed -i "$TMPDIR/etc/inittab" \
      -e 's/^#\(.*\)#\s*RPICFG_TO_ENABLE\s*/\1/' \
      -e i'/#\s*RPICFG_TO_DISABLE/d'
fi

sync
sleep 2
umount $TMPDIR

mount "$BOOTPARTNAME" "$TMPDIR"
touch "$TMPDIR/ssh" # Alternative to enable SSH since Jessy Lite
sync
sleep 2
umount "$TMPDIR"

rm -rf $TMPDIR
assertOK "$RETVAL" "$DEVICE"

# 6: Remove device & Done
# ---------------------------------------------------------------------------------
log_action 6 "$DEVICE" "Waiting for device removal..."
waitForDeviceRemoval $DEVICE
echo "Bye"
log_remove "$DEVICE"

if [ -f "$LOGFILE" ]; then
  rm "$LOGFILE"
fi

exit 0

#!/bin/bash

#REPOFOLDER="/home/$USER/svn/RaspberryMRT"
REPOFOLDER=`pwd`

WWWUSER="www-data"
WWWGROUP="www-data"

if [ ! `id -u` -eq 0 ]; then 
	echo ERROR: Must be run as root to setup RPi Root Filesystem permissions...; 
	exit -1
fi

chown root.root $REPOFOLDER/Management_PC -R
chown $USER.$GROUP $REPOFOLDER/Management_PC/www -R

find $REPOFOLDER -name "*.sh" -exec chmod +x '{}' \;
find $REPOFOLDER -name check_dev -exec chmod +x '{}' \;
find $REPOFOLDER -name "*.php" -exec chmod +x '{}' \;

exit 0

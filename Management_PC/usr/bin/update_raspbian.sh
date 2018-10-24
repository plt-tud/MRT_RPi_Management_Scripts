#!/bin/bash

CURRENT_IMAGE=2018-10-09-raspbian-stretch-lite.img
RASPBIAN_DOWNLOAD_URI="https://downloads.raspberrypi.org/raspbian_lite_latest"

cd /home/makerspace/Downloads/
if [ ! -e $CURRENT_IMAGE ]; then
	echo "Die aktuelle Version von Raspbian wird heruntergeladen"
	
	wget "$RASPBIAN_DOWNLOAD_URI" -O raspbian_lite_latest.zip
	unzip /home/makerspace/Downloads/raspbian_lite_latest.zip
	# Always remove the file - in case download was interrupted and zip is partial, this will retrigger download
	rm /home/makerspace/Downloads/raspbian_lite_latest.zip
else
echo "Die aktuellste Raspbian-Version ist bereit vorhanden"
fi

exit 1;

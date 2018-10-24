#!/bin/bash

chmod +x /home/makerspace/Downloads/MRT_RPi_Management_Scripts/Management_PC/usr/bin/update_raspbian.sh
chmod +x /home/makerspace/Downloads/MRT_RPi_Management_Scripts/Management_PC/usr/bin/check_dev

/home/makerspace/Downloads/MRT_RPi_Management_Scripts/Management_PC/usr/bin/update_raspbian.sh
/home/makerspace/Downloads/MRT_RPi_Management_Scripts/Management_PC/usr/bin/check_dev $(find /home/makerspace/Downloads/*-raspbian-*-lite.img) /home/makerspace/Downloads/MRT_RPi_FirmwarePatch/PLT_RPi-Update.tgz

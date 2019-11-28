# raspi-iot
A generic IOT platform for Raspberry Pi

This project aims to achieve:
* A service layer on top of a Linux OS
* Centralised, managed communications with a server
* Communications over a variety of means (wifi, ethernet, LoraWAN, Sigfox, semaphore, interpretive dance, etc...)
* Allowing control of multiple devices (input & output)
* Time-share for devices that are contended (e.g. wifi for comms and phone counting).
* Decent security
* Ease of use

It is a standardisation/consolidation of projects I have been developing for 5+ years in a variety of IoT contexts.

# Install from scratch

You'll need:
* Windows PC (Mac/Linux instructions to follow...)
* Micro SD card writer
* PuTTY - https://www.chiark.greenend.org.uk/~sgtatham/putty/latest.html (you'll probably want the 64-bit MSI Installer).
* Rufus - https://rufus.ie/ - at time of writing, 3.8 is the newest.
* For now the Pi will be networked...

(NB Advanced users may have their own preferences instead of PuTTY and Rufus - adapt instructions as you see fit!)

* Download the latest Raspbian Buster Lite from https://www.raspberrypi.org/downloads/raspbian/ (if you don't know anything about torrents, use the ZIP).
* Extract this ZIP into a folder on your computer (be warned: this file will expand to 2Gb). It contains a Raspbian IMG file.
* Insert a Micro SD (4GB minimum) into your computer.
* Open Rufus (Administrative permissions are required to write to the card).
* Ensure the Device listed is the Micro SD card you just inserted.
* Click SELECT on the second row, and select the expanded Raspbian IMG file.
* Ensure you have selected the correct SD card and click START at the bottom. You will be warned about overwriting the card; this is another chance to check you've selected the right one! Writing the card could take anything up to 15 minutes.
* When complete, a new drive should be visible in Windows Explorer ("Boot").
* Copy the empty file `ssh` into the boot folder. This ensures we can connect to the Pi when needed.
* Eject the SD Card using your OS (in Windows it's an icon on the task bar) and place it in the Pi.
* Power the Pi up.
* After a few minutes, from your computer, ssh to raspberrypi (this is the bit where instructions diverge!)
 

`./install.bash` should do everything we need once the package has been deployed

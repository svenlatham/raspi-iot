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

It is a standardisation/consolidation of projects I have been developing for 5+ years in a variety of IoT contextx.

# Install from scratch

You'll need:
* Windows PC (Mac/Linux instructions to follow...)
* Micro SD card writer
* PuTTY - https://www.chiark.greenend.org.uk/~sgtatham/putty/latest.html (you'll probably want the 64-bit MSI Installer).
* Rufus - https://rufus.ie/ - at time of writing, 3.8 is the newest.

(NB Advanced users may have their own preferences instead of PuTTY and Rufus - adapt instructions as you see fit!)

* Download the latest Raspbian Buster Lite from https://www.raspberrypi.org/downloads/raspbian/ (if you don't know anything about torrents, use the ZIP).
* Extract this ZIP into a folder on your computer (be warned: this file will expand to 2Gb)
* Insert a Micro SD (4GB minimum) into your computer.
* Open Rufus (Administrative permissions are required to write to the card) and select the newly expanded IMG file.
* Ensure you have selected the correct SD card and start. This will take anything up to 15 minutes.
* When complete, a new drive should be visible in Windows Explorer ("Boot").

... to be continued...
 
 
 

`./install.bash` should do everything we need once the package has been deployed

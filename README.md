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

# Prerequisities

The software is based on latest (Buster) version of Raspbian at https://www.raspberrypi.org/downloads/raspbian/
Lite is recommended to save on bloat; we'll install what we need to separately:
Other versions might work, but there are no guarantees.

sudo apt-get -y install php-cli network-manager tcpdump
sudo apt -y purge openresolv dhcpcd5

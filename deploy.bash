#!/bin/bash

# Copy this entire system to /home/pi/raspi-iot/

# Prerequisites:
sudo apt-get -y install php-cli network-manager tcpdump
sudo apt -y purge openresolv dhcpcd5

# Installation of service:
sudo ln -s /home/pi/raspi-iot/daemon/raspi-iot.service /etc/systemd/system/raspi-iot.service

# Now run it
sudo systemctl enable raspi-iot.service
sudo systemctl start raspi-iot.service

# reboot - needed because of networking changes
sudo reboot

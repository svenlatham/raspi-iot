#!/usr/bin/bash

mkdir /home/pi/raspi-iot/
# Copy everything there...
sudo cp /home/pi/raspi-iot/daemon/raspi-iot.service /etc/systemd/system/raspi-iot.service

# Create a permanent config file


# Now run it
sudo systemctl enable raspi-iot.service
sudo systemctl start raspi-iot.service

# Note - the service will atuomatically remount the device as read-only

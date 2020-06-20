#!/bin/bash

rsync -avz --exclude .git . pi@192.168.178.45:~/raspi-iot
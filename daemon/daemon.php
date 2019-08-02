<?php

# Placeholder!

class IotDaemon {
    public function log($msg) {
        printf("* %s\n", $msg);
    }

    public function start() {
        // Beep bop
        while(true) {
            sleep(1);

        }
    }

    public function readConfig() {
        // Read config from the local system

    }

    public function writeConfig() {
        // Sync our configuration


    }

    public function stop() {
        // Write config state locally
        writeConfig();
        exit();
    }
}

$singleton = new IotDaemon();
$singleton->start();
<?php

# Placeholder!

class IotDaemon {
    public const CONFIGFILE = "../../state/raspi-iot.json";
    public const CONFIGDEFAULT = "daemon/raspi-iot-default.json";

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
        if (file_exists(self::CONFIGFILE)) {
            $data = file_get_contents(self::CONFIGFILE);
        } else {
            $data = self::CONFIGDEFAULT;
            $this->writeConfig();
        }
        $this->config = json_decode($data, true);
    }

    public function writeConfig() {
        // Sync our configuration
        $data = json_encode($this->config);
        $fp = fopen(self::CONFIGFILE, 'w');
        fputs($fp, $data);
        fclose($fp);
    }

    public function stop() {
        // Write config state locally
        writeConfig();
        exit();
    }
}

$singleton = new IotDaemon();
$singleton->start();
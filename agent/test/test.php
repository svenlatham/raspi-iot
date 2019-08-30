#!/usr/bin/php
<?php
chdir(__DIR__.'/../../');
require_once('common/agent-common.php');

class TestService extends GenericService {
    var $counter = 0;



    function start() {
        $data = file_get_contents("php://stdin");
        $this->log($data);
        $test = json_decode($data);
        if ($test === null) {
            $this->log("Nothing I can use from STDIN");
            exit();
        }
        $psk = md5(mt_rand(0,999999).getSystemUptime()); // Not too cryptic for now. Will expand later.
        $cmd = sprintf("sudo nmcli dev wifi hotspot ifname wlan1 con-name devtest ssid %s password \"%s\"", getDeviceId(), $psk);
        exec($cmd);
        $this->log("TestService: Automatic process start");
        $this->startAutomatic();
    }

    function tick() {
        // By using tick, all looping and signal handling is done automatically:
        $this->sleep(1);
    }

    function stop() {
        // Typically triggered by sigs
        $this->log("TestService: Process is stopping");
        $cmd = sprintf("sudo nmcli connection delete devtest");
        exec($cmd);

    }

    function getConfigDefault() {
        return array("countLimit" => 8);
    }
}


upstand("TestService");
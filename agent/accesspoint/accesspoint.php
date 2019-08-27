#!/usr/bin/php
<?php

chdir(__DIR__);
chdir('../');

require_once('../common/daemon.php');

class AccessPointService extends GenericService {
    var $counter = 0;
    var $pipes;
    var $tcpproc;
    var $startTime;

    var $macs = array(); // Number of unique macs found


    function start() {
        $device = 'wlan0';
        $this->log("Automatic process start");
        $this->pipes = array();
        $descriptor = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "r"));
        $cmd = sprintf("/usr/bin/sudo /usr/sbin/tcpdump -l -I -i wlan0 -e -s 256 type mgt subtype probe-req");

        $this->tcpproc = proc_open($cmd, $descriptor, $this->pipes);
        stream_set_blocking($this->pipes[1], false);
        $this->startTime = microtime(true);
        $this->startAutomatic();
    }

    function parseLine($line) {
        // Returns useful data from this line
        // Split by space because we just need the first part:
        $data = explode(' ', $line);
        if (count($data) < 14) { return null; }
        $out = array();
        $out['sig'] = $data[6];
        $out['mac'] = $data[12];
        return $out;
    }

    function tick() {
        // By using tick, all looping and signal handling is done automatically:
        $data = stream_get_contents($this->pipes[1]);
        $lines = explode("\n", $data);
        foreach($lines as $line) {
            $res = $this->parseLine($line);
            if (!$res) { continue; }
            $k = $res['mac'];
            if (!array_key_exists($k, $this->macs)) {
                $this->macs[$k] = array('firstping' => microtime(true), 'count' => 1);
            } else {
                // Only count if firstping was >[LIMIT] seconds
                if ($this->macs[$k]['firstping'] < microtime(true) - 1) {
                    $this->macs[$k]['count'] ++;
                } else {
                    // Ignore
                }
            }
        }
        $this->sleep(1);
        $status = proc_get_status($this->tcpproc);
        if (!$status['running']) {
            $this->log("Sub-process has ended");
            $this->stop();
        }
    }

    function stop() {
        // Typically triggered by sigs
        $this->log("Closing down safely");
        $end = microtime(true);
        $diff = $end - $this->startTime;
        if ($diff != 0) {
            $counter = count(array_keys($this->macs));
            // We need this as a per-hour count (note, lower precision can cause its own issues)
            $cph = round(3600 * ($counter / $diff));
            // Feed this back on stdout
            $out = json_encode(array('cph' => $cph));
            echo json_encode($out);
        }
        exit();
    }

    function getConfigDefault() {
        return array("countLimit" => 8);
    }
}


upstand("AccessPointService");
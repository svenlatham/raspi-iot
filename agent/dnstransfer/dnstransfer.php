#!/usr/bin/php
<?php
chdir(__DIR__.'/../../');
require_once('common/agent-common.php');

class DnsService extends GenericService {
    var $queue;
    function start() {
        // We'll be wanting messages to send. These come in on stdin:
        $data = file_get_contents("php://stdin");
        $this->log(sprintf("We've got %s", $data));
        $this->queue = json_decode($data);
        if (!$this->queue) {
            $this->log("Nothing I can use from STDIN");
            exit();
        }
        sleep(5); // Quick breather
        // Where we're going, we don't need tick()...
        $descriptors = array(1 => array('pipe', 'w'));
        $this->log("Rescanning device");
        exec("sudo /usr/bin/nmcli device wifi rescan ifname wlan1");
        $this->log("assessing landscape");
        $proc = proc_open("sudo /usr/bin/nmcli device wifi list ifname wlan1", $descriptors, $pipes);
        $data = stream_get_contents($pipes[1]);
        printf($data);
        proc_close($proc);
        $ssids = $this->findOpenWireless($data);
        if (count($ssids) == 0) {
            $this->log("Nothing to connect to");
            exit();
        }
        // Try connecting to each in turn:
        foreach($ssids as $ssid) {
            if (!preg_match("/^[A-Za-z0-9\-\_\.]+$/", $ssid)) {
                $this->log(sprintf("Invalid SSID %s", $ssid));
                continue;
            }
            $this->log(sprintf("Attempting to connect to SSID %s", $ssid));
            $descriptors = array(1 => array('pipe', 'w'));
            $cmd = sprintf("sudo /usr/bin/nmcli device wifi connect %s ifname wlan1", $ssid);
            // Avoid system/exec as we might want more enhanced debugging
            exec($cmd);

            foreach($this->queue as $item) {
                $channel = $item->channel;
                $payload = $item->payload;
                $deviceid = getDeviceId();
                $timestamp = date('Ymd');
                $signature = 'xxxxx';
                //$item has channel, payload
                // Let's try poking the DNS bear:
                for($i=0;$i<=5;$i++) {
                    $hostname = sprintf("c%d-%s-%s-%s-%s.connectivity.latham-it.net", $channel, $deviceid, $payload, $timestamp, $signature);
                    $this->log($hostname);
                    $resolver = @dns_get_record($hostname);
                    if ($resolver != false) {
                        var_dump($resolver);
                    }
                    sleep(2);
                }
            }

            // Get rid of the connection profile (automatically created):
            $descriptors = array(1 => array('pipe', 'w'));
            $this->log("Remove existing connection");
            $cmd = sprintf("sudo /usr/bin/nmcli connection delete %s", $ssid);
            // Avoid system/exec as we might want more enhanced debugging
            $proc = proc_open($cmd, $descriptors, $pipes);
            $data = stream_get_contents($pipes[1]); // Wait for this to unblock
            proc_close($proc);

        }





        exit();



    }

    function findOpenWireless($input) {
        // Takes the NMCLI input and finds open wifi SSIDs
        $breaker = 100;
        while (strpos($input, '  ') !== false) {
            $input = str_replace('  ', ' ', $input);
            $breaker--; if ($breaker==0) { throw new Exception("Got stuck in endless loop"); }
        }
        $lines = explode("\n", $input);
        var_dump($lines);

        $ssids = array();
        foreach($lines as $line) {
            $row = explode(' ', $line);
            if (count($row) < 9) { continue; }
            $this->log(sprintf("Found SSID %s", $row[1]));
            if ($row[2] != 'Infra') { continue; }
            if ($row[8] != '--') { continue; }
            $ssids[] = $row[1];
        }
        return $ssids;
    }

    function tick() {
      
    }

    function stop() {
        // Typically triggered by sigs
        $this->log("Closing down safely");
        $end = microtime(true);
        $diff = $end - $this->startTime;
        $out = null;
        if ($diff != 0) {
            $counter = count(array_keys($this->macs));
            // We need this as a per-hour count (note, lower precision can cause its own issues)
            $cph = round(3600 * ($counter / $diff));
            // Feed this back on stdout
            $out = json_encode(array('cph' => $cph));
        }
        echo json_encode($out);
        exit();
    }

    function getConfigDefault() {
        return array("countLimit" => 8);
    }
}


upstand("DnsService");
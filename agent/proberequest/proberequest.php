#!/usr/bin/php
<?php
chdir(__DIR__ . '/../../');
require_once('common/agent-common.php');

class ProbeRequestService extends GenericService
{
    var $counter = 0;
    var $pipes;
    var $tcpproc;
    var $startTime;

    var $macs = array(); // Number of unique macs found
    var $totalTime = 0;

    var $results = array(); // This is where we'll accumulate results before returning the median

    var $maxruntime = 30; // Seconds ... We can't let scanning exceed this, as momentarily duff data can't be allowed to take over the hour's results

    function start()
    {
        $device = 'wlan1';
        $this->log("Automatic process start");
        $this->startProcess();
        $this->startAutomatic();
    }

    function startProcess()
    {
        $this->log("Starting tcpdump process");
        $this->macs = array(); // Reset macs list
        $this->pipes = array();
        $descriptor = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("file", "php://stderr", "a"));
        $cmd = sprintf("/usr/bin/sudo /usr/sbin/tcpdump -l -I -i wlan1 -e -s 256 type mgt subtype probe-req");
        $this->tcpproc = proc_open($cmd, $descriptor, $this->pipes);
        stream_set_blocking($this->pipes[1], false);
        $this->startTime = microtime(true);
    }

    function parseLine($line)
    {
        // Returns useful data from this line
        // Split by space because we just need the first part:
        $mac = false;
        $this->log($line);
        if (preg_match("/ (SA\:)?(([a-f0-9][a-f0-9]\:){5}[a-f0-9][a-f0-9]) /", $line, $matches)) {
            $mac = strtolower($matches[2]);
            // Next line MUST be for debugging only. Do not provide in production:
        }
        return $mac;
    }

    function tick()
    {
        // By using tick, all looping and signal handling is done automatically:
        $data = stream_get_contents($this->pipes[1]);
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $mac = $this->parseLine($line);
            if (!$mac) {
                continue;
            }
            

            if (!array_key_exists($mac, $this->macs)) {
                $this->macs[$mac] = array('firstping' => microtime(true), 'count' => 1);
                $this->log(sprintf("Collected MAC %s (first time)", $mac));
            } else {
                // Only count if firstping was >[LIMIT] seconds
                if ($this->macs[$mac]['firstping'] < microtime(true) - 1) {
                    $this->macs[$mac]['count']++;
                    $this->log(sprintf("Collected MAC %s again (%d time)", $mac, $this->macs[$mac]['count']));
                } else {
                    $this->log(sprintf("Collected MAC %s again (ignoring, since threshold is too low)", $mac));
                }
            }
        }
        $this->sleep(1);
        if (microtime(true) > $this->startTime + $this->maxruntime) {
            $this->log("Forcing closure of listening process");
            proc_close($this->tcpproc);
            sleep(5);
        }
        $status = @proc_get_status($this->tcpproc);
        if (!$status || !$status['running']) {
            $this->log("Sub-process has ended");
            $this->collectData();
            $this->startProcess();
        }
    }

    function collectData()
    {
        // Run me after every period of scanning:
        $end = microtime(true);
        $diff = $end - $this->startTime;
        $this->log(sprintf("Counted %d mac(s) in %d second(s)", count(array_keys($this->macs)), $diff));
        $this->results[] = array('macs' => count(array_keys($this->macs)), 'duration' => $diff);
    }

    function stop()
    {
        // Typically triggered by sigs
        $this->log("Closing down safely");
        $this->collectData();

        // Remove anything that does not meet our sampling criteria:
        $this->log(sprintf("Counted %d sets of results (unfiltered)", count($this->results)));
        $this->results = array_filter($this->results, function ($x) {
            return $x['duration'] > 30;
        });
        $this->log(sprintf("Counted %d sets of results (unfiltered)", count($this->results)));
        if (count($this->results) > 0) {
            $cph = array_map(function ($x) {
                return round(3600 * ($x['macs'] / $x['duration']));
            }, $this->results);
            // Now we have something we can get the median from
            sort($cph);
            $this->log(sprintf("Full set is %s", implode(' ', $cph)));
            $i = floor(count($cph) / 2); // Using floor cheats a bit, but we'll come back to this.
            $this->log(sprintf("Returning %d", $cph[$i]));
            // Feed this back on stdout
            $out = json_encode(array('channel' => 1, 'payload' => $cph[$i], 'expiry' => getSystemUptime() + 3600));
            echo $out;
        }
        sleep(2);
        exec("sudo ifconfig wlan1 down");
        exec("sudo iwconfig wlan1 mode managed");
        exec("sudo ifconfig wlan1 up");
        exit();
    }

    function getConfigDefault()
    {
        return array("countLimit" => 8);
    }
}


upstand("ProbeRequestService");

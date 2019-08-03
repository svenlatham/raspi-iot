<?php

# Placeholder!

class IotJob
{
    var $class;
    var $duration; // in minutes

    static function parse($row)
    {
        $job = new IotJob();
        $job->class = $row['class'];
        $job->duration = $row['duration'];
        return $job;
    }
}

class IotProcess
{
    var $job;
    var $start;
    var $expiry;
    var $log;
    var $pipe;

    public function start()
    {
        // Start process in a new fork
        if (preg_match("/^([a-zA-Z0-9\_]+)Service$/", $this->job->class, $matches)) {
            $prefix = strtolower($matches[1]);
            $file = sprintf("agent/%s/%s.php", $prefix, $prefix);
            $this->pipe = popen($file, 'w');
        } else {
            throw new Exception("Class not available");
        }
    }

    public function stop()
    {
        pclose($this->pipe);
    }
}

class IotDaemon
{
    public const CONFIGFILE = "../state/raspi-iot.json";
    public const CONFIGDEFAULT = "daemon/raspi-iot-default.json";
    public $config = null;
    public $pipes = array();

    public function __construct()
    {
        $this->readConfig();
    }

    public function log($msg)
    {
        printf("* %s\n", $msg);
    }

    public function getJob($pipe)
    {
        $row = array_shift($this->config['jobs']);
        array_push($this->config['jobs'], $row);
        $job = IotJob::parse($row);
        return $job;
    }

    public function start()
    {
        // Beep bop
        $this->log("Starting daemon");
        $pipe = 'wlan1';
        $pipes[$pipe] = null;
        while (true) {
            sleep(1);
            $this->log('tick');
            if ($pipes[$pipe] === null) {
                // Start a new process
                $this->log("Starting new process");
                $job = $this->getJob($pipe);
                var_dump($job);
                if (!$job) {
                    throw new Exception("Could not retrieve a job");
                }
                $proc = new IotProcess();
                $proc->job = $job;
                $proc->start = time();
                $proc->expiry = time() + 60 * $job->duration;
                $proc->log = '';
                $pipes[$pipe] = $proc;
                $proc->start();
            } else {
                $proc = $pipes[$pipe];
                if ($proc->expiry < time()) {
                    $this->log("Goodnight");
                    $proc->stop();
                    $pipes[$pipe] = null;
                }
            }
        }
    }

    public function getConfigDefault()
    {
        $out = array();
        $out['jobs'] = array();
        $out['jobs'][] = new IotJob("TestService", 1);
        return $out;
    }

    public function readConfig()
    {
        // Read config from the local system
        if (file_exists(self::CONFIGFILE)) {
            $data = file_get_contents(self::CONFIGFILE);
            $this->config = json_decode($data, true);
        } else {
            $this->config = $this->getConfigDefault();
            $this->writeConfig();
        }
    }

    public function writeConfig()
    {
        // Sync our configuration
        $data = json_encode($this->config);
        $fp = fopen(self::CONFIGFILE, 'w');
        fputs($fp, $data);
        fclose($fp);
    }

    public function stop()
    {
        // Write config state locally
        writeConfig();
        exit();
    }
}

$singleton = new IotDaemon();
$singleton->start();

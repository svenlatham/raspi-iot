<?php

# Placeholder!

class IotTask
{
    var $class;
    var $duration; // in minutes

    static function parse($row)
    {
        if (is_object($row) && get_class($row) == 'IotTask') { return $row; }
        $task = new IotTask();
        $task->class = $row['class'];
        $task->duration = $row['duration'];
        return $task;
    }

    static function create($class, $duration) {
        $out = new IotTask();
        $out->class = $class;
        $out->duration = $duration;
        return $out;
    }
}

class IotWorkUnit
{
    var $task;
    var $start;
    var $expiry;
    var $log;
    var $process;

    public function start()
    {
        // Start process in a new fork
        if (preg_match("/^([a-zA-Z0-9\_]+)Service$/", $this->job->class, $matches)) {
            $prefix = strtolower($matches[1]);
            $file = sprintf("/bin/sh ./run.sh");
            $cwd = sprintf("agent/%s/", $prefix);
            $pipes = array(); // We're not using this yet
            $this->process = proc_open($file, array(), $pipes, $cwd);
        } else {
            throw new Exception("Class not available");
        }
    }

    public function isRunning() {
        $stats = proc_get_status($this->process);
        return !!$stats['running'];
        /*array(8) {
            ["command"]=>
            string(19) "agent/test/test.php"
            ["pid"]=>
            int(21469)
            ["running"]=>
            bool(true)
            ["signaled"]=>
            bool(false)
            ["stopped"]=>
            bool(false)
            ["exitcode"]=>
            int(-1)
            ["termsig"]=>
            int(0)
            ["stopsig"]=>
            int(0)
        }*/
    }

    public function stop()
    {
        proc_close($this->process);
    }
}

class IotDaemon
{
    public const CONFIGFILE = "../state/raspi-iot.json";
    public const CONFIGDEFAULT = "daemon/raspi-iot-default.json";
    public $config = null;
    public $workers = array();

    public function __construct()
    {
        $this->readConfig();
    }

    public function log($msg)
    {
        printf("[%s] %s\n", date('c'), $msg);
    }

    public function getTask($pipe)
    {
        if ($this->config === null) { throw new Exception("Config has not been defined"); }
        if (count($this->config['tasks']) == 0) {
            return null;
        }
        $row = array_shift($this->config['tasks']);
        array_push($this->config['tasks'], $row);
        $task = IotTask::parse($row);
        return $task;
    }

    public function start()
    {
        // Beep bop
        $this->log("Starting daemon");

        // We need to start a series of workers, one for each channel

        // Temporary: eventually we'll negotiate each channel separately.
        $channel = 'wlan1';

        $this->workers[$channel] = null;
        while (true) {
            sleep(1);
            foreach(array_keys($this->workers) as $channel) {
                if ($this->workers[$channel] === null) {
                    // Start a new process
                    $this->log(sprintf("%s: Starting new process", $channel));
                    $task = $this->getTask($channel);
                    if (!$task) {
                        throw new Exception("Could not retrieve a job");
                    }
                    $proc = new IotWorkUnit();
                    $proc->job = $task;
                    $proc->start = time();
                    $proc->expiry = time() + 10 * $task->duration;
                    $proc->log = '';
                    $this->workers[$channel] = $proc;
                    $proc->start();
                } else {
                    $proc = $this->workers[$channel];

                    if ($proc->isRunning()) {
                        if ($proc->expiry < time()) {
                            $this->log(sprintf("%s: Current unit of work %s has expired and will be forcibly stopped", $channel, $proc->job->class));
                            $proc->stop();
                            $this->workers[$channel] = null;
                        } else {
                            $this->log(sprintf("%s: Process %s is still running", $channel, $proc->job->class));
                        }
                    } else {
                        $this->log(sprintf("%s: Process %s has been removed", $channel, $proc->job->class));
                        $proc = null;
                        $this->workers[$channel] = null;
                    }
                }
            }
        }
    }

    public function getConfigDefault()
    {
        $out = array();
        $out['tasks'] = array();
        $out['tasks'][] = IotTask::create("TestService", 1);
        $out['tasks'][] = IotTask::create("AccessPointService", 5);
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

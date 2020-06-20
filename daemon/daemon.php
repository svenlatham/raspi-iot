<?php
chdir(__DIR__.'/../');
require_once 'common/common.php';

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
    var $pipes;
    var $response = null;
    var $pid;

    public function start($data=null)
    {
        // Start process in a new fork
        if (preg_match("/^([a-zA-Z0-9\_]+)Service$/", $this->job->class, $matches)) {
            $prefix = strtolower($matches[1]);
            $file = sprintf("exec /usr/bin/php %s.php", $prefix);
            $cwd = sprintf("agent/%s/", $prefix);
            $descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe','w'), 2 => array('file','php://stderr','a'));
            
            $this->process = proc_open($file, $descriptors, $this->pipes, $cwd);
            fwrite($this->pipes[0], json_encode($data));
            fclose($this->pipes[0]);

            stream_set_blocking($this->pipes[1], false);
            $stats = proc_get_status($this->process);
            $this->pid = $stats['pid'];
        } else {
            throw new Exception("Class not available");
        }
    }

    public function isRunning() {
        $stats = proc_get_status($this->process);
        return !!$stats['running'];
    }

    public function log($msg)
    {
        fwrite(STDERR, sprintf("[%s] %s %s\n", getSystemUptime(), posix_getpid(), $msg));
    }

    public function stop()
    {
        $this->log(sprintf("Stopping child process %s with SIGINT", $this->pid));
        // Read whatever we got from STDOUT (which may or may not be useable)
        posix_kill($this->pid, SIGINT); // Be nice about it
        for ($i=0; $i<=5; $i++) {
            if (!$this->isRunning()) { break; }
            sleep(1);
        }
        $this->log("Getting response from ended process");
        $this->response = stream_get_contents($this->pipes[1]);
        fclose($this->pipes[1]);
        $this->log("Closing process");
        proc_close($this->process);
        $this->log("Process closed");
    }
}

class IotDaemon
{
    public const CONFIGFILE = "../state/raspi-iot.json";
    public $config = null;
    public $workers = array();
    public $queue = array();

    public function __construct()
    {
        $this->readConfig();
    }

    public function log($msg)
    {
        fwrite(STDERR, sprintf("[%s] %s daemon - %s\n", getSystemUptime(), posix_getpid(), $msg));
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
        $this->log("Starting daemon. 30 second sleep first...");
        sleep(30);

        // We need to start a series of workers, one for each channel

        // Temporary: eventually we'll negotiate each channel separately.
        $channel = 'wlan1';

        $this->workers[$channel] = null;
        while (true) {
            sleep(1);
            // Delete anything in the queue that has expired
            $now = getSystemUptime();
            $this->queue = array_filter($this->queue, function($item) use ($now) {     
                return !!$item && $item->expiry > $now;
            });


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
                    $proc->start = getSystemUptime();
                    $proc->expiry = getSystemUptime() + 60 * $task->duration;
                    $proc->log = '';
                    $this->workers[$channel] = $proc;
                    $proc->start($this->queue);
                } else {
                    $proc = $this->workers[$channel];
                    if ($proc->isRunning()) {
                        if ($proc->expiry < getSystemUptime()) {
                            $this->log(sprintf("%s: Current unit of work %s has expired and will be forcibly stopped", $channel, $proc->job->class));
                            $proc->stop();
                            $this->log(sprintf("Got reply %s", $proc->response));
                            $data = json_decode($proc->response);
                            if ($data) { $this->queue[] = $data; }
                            $this->workers[$channel] = null;
                        } else {
                            //$this->log(sprintf("%s: Process %s is still running; expiry %d", $channel, $proc->job->class, $proc->expiry));
                        }
                    } else {
                        if ($proc->expiry < getSystemUptime()) {
                            $this->log(sprintf("%s: Process %s has been removed", $channel, $proc->job->class));
                            $this->log(sprintf("Got reply %s", $proc->response));
                            $data = json_decode($proc->response);
                            if ($data) { $this->queue[] = $data; }
                            $proc = null;
                            $this->workers[$channel] = null;
                        } else {
                            $this->log(sprintf("%s: Process %s has stopped; cleanup scheduled for %d", $channel, $proc->job->class, $proc->expiry));
                        }
                    }
                }
            }
        }
    }

    public function getConfigDefault()
    {
        $out = array();
        $out['tasks'] = array();
        // Needs to be <1 hour just in case the clock is drifting wildly (does happen!)
        //$out['tasks'][] = IotTask::create("TestService", 2);
        $out['tasks'][] = IotTask::create("ProbeRequestService", 2);
        $out['tasks'][] = IotTask::create("DnsTransferService", 1);
        $out['tasks'][] = IotTask::create("AccessPointService", 2);
        return $out;
    }

    public function readConfig()
    {
        // Read config from the local system
        /*if (file_exists(self::CONFIGFILE)) {
            $data = file_get_contents(self::CONFIGFILE);
            $this->config = json_decode($data, true);
        } else {*/
            $this->config = $this->getConfigDefault();
            $this->writeConfig();
        //}
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
        $this->writeConfig();
        exit();
    }
}

$singleton = new IotDaemon();
$singleton->start();

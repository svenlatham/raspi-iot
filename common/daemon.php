<?php

# Common daemon library (PHP only; eventually will be .NET Core compatible as well)

# Bear in mind these will be run as external executables, and will obey Linux SIGHUP and all that jazz

abstract class GenericService
{
    var $status = null;
    var $runAutomatic = true;
    var $config = null;
    var $channel = null;

    function __construct() {
        $this->config = $this->readConfig();
        $this->channel = argv(1);
    }

    public abstract function start();
    public abstract function stop();

    public function setSignal($signo) {
        $this->log(sprintf("Signal received: %s", $signo));
        $this->status = $signo;
    }

    protected function log($msg) {
        printf("* %s\n", $msg);
    }

    protected function tick() { }

    protected function startAutomatic() {
        // Internal loop facility
        while (true) {
            if (!$this->runAutomatic) {
                $this->stop();
                return;
            }
            $this->tick();
            if ($this->status === null) { continue; }
            switch ($this->status) {
                case SIGTERM:
                case SIGINT:
                case SIGHUP:
                    $this->stopAutomatic();
                    break;
            }
            // Nowt else supported yet...
        }
    }

    protected function sleep($seconds) {
        // Can't use standard sleep as that will capture signals directly...
        $expiry = microtime(true) + $seconds;
        while (microtime(true) < $expiry) {
            // Donowt
        }
    }

    protected function stopAutomatic() {
        // Call me when the process stops 'naturally' (i.e. completed its task)
        // Prevents the automatic script from continuing; 
        $this->runAutomatic = false;
    }
    
    public function getConfigFile() {
        $file = sprintf("../../state/config-%s.json", get_class($this));
        return $file;
    }

    protected function getConfigDefault() {
        return array();
    }

    public function readConfig() {
        // Read config from the local system
        $configFile = $this->getConfigFile();
        $this->config = $this->getConfigDefault();
        if (file_exists($configFile)) {
            $data = file_get_contents($configFile);
            $json = json_decode($data, true);
            $this->config = array_merge($this->config, $json);
        } else {
            $this->writeConfig();
        }
    }

    public function writeConfig() {
        // Sync our configuration
        $data = json_encode($this->config);
        @mkdir("../../state/", 0777, true);
        $fp = fopen($this->getConfigFile(), 'w');
        fputs($fp, $data);
        fclose($fp);
    }
}

$service = null;
function upstand($servicename) {
    global $service;
    // Instantiate the given service:
    printf("Upstand complete\n");
    $service = new $servicename();
    $service->start();
    printf("Upstand finished\n");
    // When we get here the service has stopped
    exit();
}

if (!function_exists("pcntl_async_signals")) { echo "PHP 7.1+ needed"; exit(); }
pcntl_async_signals(true);
printf("Standing up\n");


function signal_handler($signo)
{
    global $service;
    $service->setSignal($signo);
}
pcntl_signal(SIGINT,  "signal_handler");
pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGHUP,  "signal_handler");
printf("Registered signal handlers\n");
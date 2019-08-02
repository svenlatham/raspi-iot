<?php
require_once('../common/daemon.php');

class TestService extends GenericService {
    var $counter = 0;



    function start() {
        $this->log("Automatic process start");
        $this->startAutomatic();
    }

    function tick() {
        // By using tick, all looping and signal handling is done automatically:
        printf("It's %s\n", date('c'));
        $this->sleep(1);
        $this->counter++;
        if ($this->counter > 5) { $this->stopAutomatic(); }
    }

    function stop() {
        // Typically triggered by sigs
        $this->log("Process is stopping");

    }
}


upstand("TestService");
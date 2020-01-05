<?php

require(__DIR__.'/vendor/autoload.php');

set_time_limit(0);
ini_set('display_errors', 1);

$daemon = new phpSocketDaemon\socketDaemon();
$server = $daemon->create_server('httpServer', 'httpServerClient', 0, 8000);
$daemon->process();
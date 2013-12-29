<?php

define('BASE_DIR', dirname(__FILE__));
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']));

include './../../lib/filestor-server.php';
FilestorServer::handleRequest($_GET, $_POST, $_FILES);
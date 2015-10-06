<?php
define("ROOTADDR", getcwd()."\\");
define("LIBADDR", ROOTADDR."lib\\");
define("LOGADDR", ROOTADDR."log\\");
define("CONTENTADDR", ROOTADDR."content\\");

require_once LIBADDR."watcher.php";

$__watcher = new watcher("http://plentix.com.au/", LOGADDR, CONTENTADDR, "farshidalavi@live.com");
$__watcher->execute();
?>
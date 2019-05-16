<?php
ini_set("display_errors", 1);
ini_set("track_errors", 1);
ini_set("html_errors", 1);
error_reporting(E_ALL);

include("../../vendor/autoload.php");
$helper = new \grader\control\Helper();

$input = array();
$in = array_merge($_POST, $_GET);
foreach ($in as $k => $v) {
    $input[str_replace("_", " ", $k)] = $v;
}

$helper->run($in);



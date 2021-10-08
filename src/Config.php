<?php

namespace grader;

class Config {

    public static $DATABASE = [
        "host" => "",
        "port" => "5432",
        "database" => "",
        "user" => "",
        "password" => ""
    ];

    public static $TEMPLATE_DIR = "/path/to/src/control/templates";
    public static $DATA_DIR = "/path/to/data";
}

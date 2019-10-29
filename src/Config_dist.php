<?php

namespace grader;

class Config {

    public static $DATABASE = [
        "host" => "",
        "port" => "5432",
        "database" => "", // Replace with your database
        "user" => "",     // Replace with your db username
        "password" => ""  // Replace with your db password
    ];

    public static $TEMPLATE_DIR = "/path/to/src/control/templates";
    public static $DATA_DIR = "/path/to/data";
    public static $TEMP_DIR = "/path/to/tmp";
}

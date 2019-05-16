<?php

namespace grader;

class Config {

    public static $DATABASE = [
        "host" => "localhost",
        "port" => "5432",
        "database" => "pdf_grader",
        "user" => "pdf_grader",
        "password" => "GRADERpdf"
    ];

    public static $TEMPLATE_DIR = "/scratch/grading_app/src/control/templates";
    public static $DATA_DIR = "/scratch/grading_app/data";
}

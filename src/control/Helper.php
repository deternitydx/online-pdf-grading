<?php
namespace grader\control;

use \grader\Config as Config;

class Helper {

    public $dData;
    public $db;
    public $dataset;

    public function __construct() {
        $this->dData = array();
        $this->db = new \grader\control\DatabaseConnector();
        if (!isset($_SERVER["uid"])) {
            die(); // update soon
        }
        $this->user = $this->readUser($_SERVER["uid"]);
        if ($this->user == null)
            die($this->showError("Not Authorized"));
    }
    
    public function run(&$input = null) {
        $command = "list-courses";
        if ($input != null || isset($input["command"])) {
            $command = $input["command"];
        }

        switch($command) {
            case 'new-homework':
                $this->newHomework($input['course_id']);
                break;
            case 'create-homework':
                $this->createHomework($input);
                $this->showCourse($input);
                break;
            case 'assign-graders-post':
                $this->updateGraders($input);
            case 'assign-graders':
                $this->assignGraders($input);
                break;
            case 'save-grade':
                $this->saveGrades($input);
                unset($input["userid"]);
            case 'grade':
                $this->showGradePage($input);
                break;
            case 'show-pdf':
                $this->showPDF($input["userid"], $input["homework_id"]);
                break;
            case 'course':
                $this->showCourse($input);
                break;
            case 'list-courses':
            default:
                $this->display("courses", null);
                break;
        }


    }

    public function showPDF($userid, $hid) {
        $homework = $this->loadHomework($hid);

        $projDir = \grader\Config::$DATA_DIR."/".$homework["directory"];
        if ($handle = opendir($projDir)) {
            $fullpath = "";
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && is_dir($userdir = $projDir ."/".$entry)) {
                    preg_match("/(.*)\(([a-z]+[0-9][a-z]+)\)/", $entry, $matches);
                    $dirid = $matches[2];
                    if ($dirid == $userid) {
                        $fullpath = $userdir;
                        break;
                    }
                }
            }
            closedir($handle); 
            
            $filename = null;
            $submitDir = "$fullpath/Submission attachment(s)/";
            if ($h2 = @opendir($submitDir)) {
                while (false !== ($fn = readdir($h2))) {
                    if ($fn != "." && $fn != ".." && stripos($fn, '.pdf') !== false) {
                        $filename = "$submitDir$fn";
                    }
                }
            }

            if ($filename != null) {
                header("Content-type:application/pdf");
                header("Content-Disposition:inline;filename='$userid.pdf'");
                readfile($filename); 
            } else {
                echo "<h1>NO PDF</h1>";//$this->showError("Problem viewing file");
            }
        } else {
            echo "<h1>NO PDF</h1>";//$this->showError("Problem viewing file");
        }

    }

    private function getValue($val) {
        if (empty($val) || $val == "" || $val == null)
            return null;
        return $val;
    }

    public function saveGrades($data) {
        //TODO sanity checks

        foreach ($data["problem"] as $i => $gid) {
            $res = $this->db->query("update grade set grade = $1, comment = $2, graded = 't' where id = $3 and grader_id = (select id from grader where userid = $4 and course_id = $5 limit 1);", 
                array(
                    $this->getValue($data["points"][$i]), 
                    $this->getValue($data["comments"][$i]), 
                    $gid, 
                    $this->user["userid"], 
                    $data["course_id"]
                )
            );
        }
    }

    private function getRandom($c, $h) {
        $res = $this->db->query("select g.userid from grade g, grader r where g.homework_id = $1 and g.grader_id = r.id and r.userid = $2 and not g.graded group by g.userid limit 1;", 
            array($h["id"], $this->user["userid"]));
        $tograde = $this->db->fetchAll($res);
        if($tograde !== false && count($tograde) == 1)
            return $tograde[0]["userid"];
        return null;
    }


    public function showGradePage($data) {
        $course = $this->loadCourse($data["course_id"]);
        $homework = $this->loadHomework($data["homework_id"]);
        $uid = null;
        if (isset($data["userid"]))
            $uid = $data["userid"];
        else
            $uid = $this->getRandom($course,$homework);

        if ($uid == null) {
            $this->showError("Nothing left to grade!");
            die();
        }
            


        $res = $this->db->query("select g.* from grade g, grader r where g.homework_id = $1 and g.grader_id = r.id and r.userid = $2 and g.userid = $3 order by g.problem_id asc", array($homework["id"], $this->user["userid"], $uid));
        $tograde = $this->db->fetchAll($res);
        foreach ($tograde as &$tg) {
            foreach ($homework["problems"] as $p) {
                if ($p["id"] == $tg["problem_id"]) {
                    $tg["p"] = $p;
                    break;
                }
            }
        }

        $this->display("grade", ["course" => $course, "homework" => $homework, "userid" => $uid, "tograde" => $tograde]);
    }

    public function loadCourse($cid) {
        $res = $this->db->query("select * from course c where id = $1", array($cid));
        $data = $this->db->fetchAll($res);

        $course = null;
        if (count($data) == 1) {
            $course = $data[0];
            $res = $this->db->query("select * from grader where course_id = $1", array($cid));
            $data = $this->db->fetchAll($res);
            $course["graders"] = $data;
            $res = $this->db->query("select id from homework where course_id = $1", array($cid));
            $data = $this->db->fetchAll($res);
            $course["homeworks"] = [];
            if ($data !== false)
                foreach ($data as $hw) {
                    array_push($course["homeworks"], $this->loadHomework($hw["id"]));
                }
        }
        return $course;
    }   

    public function loadHomework($hid) {
        $res = $this->db->query("select * from homework c where id = $1", array($hid));
        $data = $this->db->fetchAll($res);

        $homework = null;
        if (count($data) == 1) {
            $homework = $data[0];
            $res = $this->db->query("select * from problem where homework_id = $1", array($hid));
            $data = $this->db->fetchAll($res);
            $homework["problems"] = $data;
            $res = $this->db->query("select distinct userid from grade where homework_id = $1 and grader_id is null order by userid asc", array($hid));
            $data = $this->db->fetchAll($res);
            $homework["students"] = $data;
        }
        return $homework;
    }

    public function newHomework($cid) {
        //TODO sanity check
        $course = $this->loadCourse($cid);
        $this->display("upload", ["course" => $course]);
    }

    public function showCourse($data) {
        //TODO sanity check
        $course = $this->loadCourse($data["course_id"]);
        $this->display("course", ["course" => $course]);
    }

    public function assignGraders($data) {
        $course = $this->loadCourse($data["course_id"]);
        $homework = $this->loadHomework($data["homework_id"]);

        $this->display("assign", ["course" => $course, "homework" => $homework]);
    }

    public function updateGraders($data) {
        //TODO Sanity checks

        $cid = $data["course_id"];
        $hid = $data["homework_id"];
        $gid = $data["grader"];
        foreach ($data["students"] as $student) {
            foreach ($data["problems"] as $problem) {
                $res = $this->db->query("update grade set grader_id = $1 where homework_id = $2 and userid = $3 and problem_id = $4;", array($gid, $hid, $student, $problem));
            }
        }
                
    }

    public function createHomework($data) {
        //$hw = new \grader\data\Homework($data);
        //TODO Sanity checks
        
        
        
        $course = $this->loadCourse($data["course_id"]);
        $res = $this->db->query("insert into homework (course_id, name, due_date) values ($1, $2, $3) returning id;", array($course["id"], $data["name"], $data["duedate"]));
        $tmp = $this->db->fetchAll($res);
        if (count($tmp) != 1) {
            $this->showError("Database Error");
        }
        $id = $tmp[0]["id"];
        
        mkdir(\grader\Config::$DATA_DIR."/$id");

        $fn = $this->handleFileUpload();

        $zip = new \ZipArchive();
        if ($zip->open($fn) === TRUE) {
            $zip->extractTo(\grader\Config::$DATA_DIR."/$id");
            $zip->close();
        } else {
            $this->showError("ZIP Error");
        }

        $tmp = scandir(\grader\Config::$DATA_DIR."/$id", 1);
        $realdir = "";
        foreach ($tmp as $d) {
            if ($d != "." && $d != ".." && $d != "__MACOSX") {
                $realdir = $d;
                break;
            }
        }
        $res = $this->db->query("update homework set directory = $1 where id = $2 returning *;", array("$id/$realdir", $id));

        $problems = explode("\n", $data["setup"]);
        foreach ($problems as $problem) {
            $parts = explode(" ", $problem);
            $n = $parts[0];
            $p = $parts[1];
            $extra = 'f';
            if (isset($parts[2]))
                $extra = 't';
            $res = $this->db->query("insert into problem (homework_id, name, points, extra_credit) values ($1, $2, $3, $4);", array($id, $n, $p, $extra));
        }

        $res = $this->db->query("select id from problem where homework_id = $1 order by id;", array($id));
        $problems = $this->db->fetchAll($res);

        $students = [];
        $fullpath = \grader\Config::$DATA_DIR."/$id/$realdir";
        if ($handle = opendir($fullpath)) {
            // Read original grades file
            $fp = fopen($fullpath.'/grades.csv', 'r');
            $i = 0;
            while (($grData = fgetcsv($fp, 1000, ",")) !== FALSE) {
                if ($i++ > 2)
                    array_push($students, $grData[1]);
            }
            fclose($fp);
        }
        foreach ($students as $student) {
            foreach ($problems as $problem) {
                $this->db->query("insert into grade (homework_id, problem_id, userid) values ($1, $2, $3);", array($id, $problem["id"], $student));
            }
        }
    }

    public function handleFileUpload() {
        if (
            !isset($_FILES['zipfile']['error']) ||
            is_array($_FILES['zipfile']['error'])
        ) {
            throw new \RuntimeException('Invalid parameters.');
        }

        // Check $_FILES['zipfile']['error'] value.
        switch ($_FILES['zipfile']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new \RuntimeException('No file sent.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \RuntimeException('Exceeded filesize limit.');
            default:
                throw new \RuntimeException('Unknown errors.');
        }

        // You should also check filesize here.
        if ($_FILES['zipfile']['size'] > 100000000) {
            throw new \RuntimeException('Exceeded filesize limit.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        if (false === $ext = array_search(
            $finfo->file($_FILES['zipfile']['tmp_name']),
            array(
                'zip' => 'application/zip'
            ),
            true
        )) {
            throw new \RuntimeException('Invalid file format.');
        }

        return $_FILES['zipfile']['tmp_name'];
    }

    public function readUser($uid) {
        $res = $this->db->query("select g.*, c.name as course_name, c.description as course_description from grader g, course c where g.course_id = c.id and userid = $1", array($uid));
        $data = $this->db->fetchAll($res);

        $user = [];
        $courses = [];
        foreach ($data as $line) {
            $user["userid"] = $line["userid"];
            $user["name"] = $line["name"];
            $courses[$line["course_id"]] = [
                "id" => $line["course_id"],
                "name" => $line["course_name"],
                "description" => $line["course_description"],
                "instructor" => $line["instructor"]
            ];
        }
        if (!empty($user)) {
            $user["courses"] = $courses;
        } else {
            $user = null;
        }

        return $user;
    }

    public function display($template, $data) {
        $loader = new \Twig_Loader_Filesystem(\grader\Config::$TEMPLATE_DIR);
        $twig = new \Twig_Environment($loader, array(
            ));

        echo $twig->render($template . ".html", array("data" => $data, "user" => $this->user));
    
    }

    public function showError($error) {
        $this->display("error", ["error" => $error]);
        die();
    }
}

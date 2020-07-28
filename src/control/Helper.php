<?php
namespace grader\control;

use \grader\Config as Config;

// helper function for CSV
// From: https://gist.github.com/johanmeiring/2894568
if (!function_exists('str_putcsv')) {
    function str_putcsv($input, $delimiter = ',', $enclosure = '"') {
        $fp = fopen('php://temp', 'r+b');
        fputcsv($fp, $input, $delimiter, $enclosure);
        rewind($fp);
        $data = rtrim(stream_get_contents($fp), "\n");
        fclose($fp);
        return $data;
    }
}

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
            case 'new-course':
                $this->newCourse($input);
                break;
            case 'save-course':
                $courseid = $this->saveCourse($input);
                $this->showCourse($courseid);
                break;
            case 'new-homework':
                $this->newHomework($input['course_id']);
                break;
            case 'create-homework':
                $this->createHomework($input);
                $this->showCourse($input['course_id']);
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
                $this->showCourse($input['course_id']);
                break;
            case 'download-grades':
                $this->downloadGrades($input["homework_id"], $input['course_id']);
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
                // Read PDF
                header("Content-type:application/pdf");
                header("Content-Disposition:inline;filename='$userid.pdf'");
                readfile($filename); 
            } else {
                // Try to find a ZIP file
                $filename = null;
                if ($h2 = @opendir($submitDir)) {
                    while (false !== ($fn = readdir($h2))) {
                        if ($fn != "." && $fn != ".." && stripos($fn, '.zip') !== false) {
                            $filename = "$submitDir$fn";
                        }
                    }
                }
                if ($filename != null) {
                
                    //HERE
                    $zip = new \ZipArchive();
                    if ($zip->open($filename) === true) {
                        $internal = null;
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            if (stripos($zip->getNameIndex($i), '.pdf') !== false && stripos($zip->getNameIndex($i), '__MACOSX') === false) {
                                $internal = $i;
                            }
                        }
                        if ($internal != null) {
                            header("Content-type:application/pdf");
                            header("Content-Disposition:inline;filename='$userid.pdf'");
                            echo $zip->getFromIndex($internal); 

                        } else {
                            echo "<h1>NO PDF and NO PDF IN ZIP</h1>";//$this->showError("Problem viewing file");
                        }
                        $zip->close();
                    } else {
                        echo "<h1>NO PDF and CORRUPT ZIP</h1>";//$this->showError("Problem viewing file");
                    }
                } else {
                    echo "<h1>NO PDF and NO ZIP</h1>";//$this->showError("Problem viewing file");
                }
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
            $res = $this->db->query("update grade set grade = $1, comment = $2, graded = 't', graded_time = now() where id = $3 and grader_id = (select id from grader where userid = $4 and course_id = $5 limit 1);", 
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
        //$res = $this->db->query("select g.userid from grade g, grader r where g.homework_id = $1 and g.grader_id = r.id and r.userid = $2 and not g.graded group by g.userid limit 1;", 
        $res = $this->db->query("select g.userid from grade g, grader r where g.homework_id = $1 and g.grader_id = r.id and r.userid = $2 and not g.graded order by g.userid limit 1;", 
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
            $this->showHappy("Nothing left to grade!");
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
            $tmp = [];
            foreach ($data as $d) {
                $tmp[$d["id"]] = $d;
                $tmp[$d["id"]]["status"] = [
                    "graded" => 0,
                    "count" => 0,
                    "percentgraded" => 0
                ];
            }
            $homework["problems"] = $tmp;

            $res = $this->db->query("select distinct userid from grade where homework_id = $1 and grader_id is null order by userid asc", array($hid));
            $data = $this->db->fetchAll($res);
            $homework["students"] = $data;
            
            // Get an overall count ungraded
            $res = $this->db->query("select count(*) as ungraded from grade where homework_id = $1 and not graded", array($hid));
            $data = $this->db->fetchAll($res);
            $homework["ungraded"] = $data[0]["ungraded"];
            $res = $this->db->query("select count(*) as all from grade where homework_id = $1", array($hid));
            $data = $this->db->fetchAll($res);
            $homework["count"] = $data[0]["all"];
            $homework["percentgraded"] = round(100* ($homework["count"] - $homework["ungraded"]) / (double) $homework["count"], 2);

            // get recents for this user: TODO: update based on userid not id
            $res = $this->db->query("select distinct userid, graded_time from grade where homework_id = $1 and grader_id = $2 and graded order by graded_time desc", array($hid, $this->user["id"]));
            //$res = $this->db->query("select userid from (select userid, graded_time from grade where homework_id = $1 and grader_id = $2 and graded order by graded_time desc) a group by userid", array($hid, $this->user["id"]));
            $data = $this->db->fetchAll($res);
            $tmp = [];
            $prev = "";
            if (!empty($data)) {
                foreach ($data as $d) {
                    if ($d["userid"] != $prev) {
                        array_push($tmp, $d);
                        $prev = $d["userid"];
                    }
                }
            }

            $homework["recents"] = $tmp;

            // grader stats
            $res = $this->db->query("select graded, count(*)  from grade where homework_id = $1 and grader_id = $2 group by graded", array($hid, $this->user["id"]));
            $data = $this->db->fetchAll($res);

            $homework["graderstatus"] = [
                "graded" => 0,
                "total" => 0,
                "ungraded" => 0
            ];
            if (isset($data[0])) {
                foreach ($data as $d) {
                    if ($d["graded"] == 't')
                        $homework["graderstatus"]["graded"] = $d['count'];
                    if ($d["graded"] == 'f')
                        $homework["graderstatus"]["ungraded"] = $d['count'];
                }
                $homework["graderstatus"]["total"] = $homework["graderstatus"]["graded"] + $homework["graderstatus"]["ungraded"];
            }


            $res = $this->db->query("select problem_id, count(*) as graded from grade where homework_id = $1 and graded group by problem_id", array($hid));
            $data = $this->db->fetchAll($res);
            if (isset($data[0])) {
                foreach ($data as $d) {
                    $homework["problems"][$d["problem_id"]]["status"]["graded"] = $d["graded"];
                }
            }
            $res = $this->db->query("select problem_id, count(*) as all from grade where homework_id = $1 group by problem_id", array($hid));
            $data = $this->db->fetchAll($res);
            foreach ($data as $d) {
                $homework["problems"][$d["problem_id"]]["status"]["count"] = $d["all"];
                $homework["problems"][$d["problem_id"]]["status"]["percentgraded"] = round(100 * ($homework["problems"][$d["problem_id"]]["status"]["graded"] / (double) $homework["problems"][$d["problem_id"]]["status"]["count"]),2);
            }

            // grader stats
            $res = $this->db->query("select a.name, b.graded, b.count from grader a, (select grader_id, graded, count(*)  from grade where homework_id = $1 group by grader_id, graded order by grader_id, graded asc) b where a.id = b.grader_id", array($hid));
            $data = $this->db->fetchAll($res);

            $tmp = [];
            if (isset($data[0])) {
                foreach ($data as $d) {
                    if (!isset($tmp[$d["name"]]))
                        $tmp[$d["name"]] = [];
                    if ($d["graded"] == 't')
                        $tmp[$d["name"]]["graded"] = $d['count'];
                    if ($d["graded"] == 'f')
                        $tmp[$d["name"]]["ungraded"] = $d['count'];
                }
                foreach ($tmp as $k => $v) {
                    $g = 0;
                    $ung = 0;
                    if (isset($tmp[$k]["graded"]))
                        $g = $tmp[$k]["graded"];
                    if (isset($tmp[$k]["ungraded"]))
                        $ung = $tmp[$k]["ungraded"];
                    $tmp[$k]["total"] = $g + $ung;
                }
            }
            $homework["graders"] = $tmp;

            // Load all assigned comments for each problem
            foreach ($homework["problems"] as $pid => $problem) {
                $res = $this->db->query("select distinct comment from grade where homework_id = $1 and problem_id = $2 order by comment asc;", array($hid, $pid));
                $data = $this->db->fetchAll($res);
                $homework["problems"][$pid]["comments"] = [];
                foreach ($data as $d) {
                    if (!empty($d["comment"]))
                        array_push($homework["problems"][$pid]["comments"], $d["comment"]);
                }

            }
        }
        return $homework;
    }

    public function newHomework($cid) {
        //TODO sanity check
        $course = $this->loadCourse($cid);
        $this->display("upload", ["course" => $course]);
    }
    
    public function newCourse($cid) {
        //TODO sanity check (has permissions)
        $this->display("newcourse", null);
    }


    public function showCourse($id) {
        //TODO sanity check
        $course = $this->loadCourse($id);
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
        $num = $data["numstudents"];
        if (!empty($data["students"]) && !is_numeric($num)) {
            foreach ($data["students"] as $student) {
                foreach ($data["problems"] as $problem) {
                    $res = $this->db->query("update grade set grader_id = $1 where homework_id = $2 and userid = $3 and problem_id = $4;", array($gid, $hid, $student, $problem));
                }
            }
        } else if (is_numeric($num)) {
            if (count($data["problems"]) == 1) {
                // If only assigning one, then grab at random
                foreach ($data["problems"] as $problem) {
                    $res = $this->db->query("update grade set grader_id = $1 where id in (select id from grade where homework_id = $2 and problem_id = $3 and grader_id is null order by random() limit $num);", array($gid, $hid, $problem));
                }
            } else {
                // Otherwise, be smart and try to grab all problems from the same students
                //$i = 0;
                //while ($i < $num) {
                //    $res = $this->db->query("select id from grade where homework_id = $2 and grader_id is null order by random();", array($hid));

                //}
                // Currently just assigns all to one grader
                $res = $this->db->query("select userid from (select distinct userid from grade where homework_id = $1 and grader_id is null) a order by random() limit $num;", array($hid));
                $userids = $this->db->fetchAll($res);
                foreach ($userids as $userid) {
                    $res = $this->db->query("update grade set grader_id = $1 where homework_id = $2 and userid = $3 and grader_id is null;", array($gid, $hid, $userid["userid"]));
                }
            }
        }
                
    }

    public function saveCourse($data) {
        //$hw = new \grader\data\Homework($data);
        //TODO Sanity checks
        
        
        
        $res = $this->db->query("insert into course (name, description) values ($1, $2) returning id;", array($data["name"], $data["description"]));
        $tmp = $this->db->fetchAll($res);
        if (count($tmp) != 1) {
            $this->showError("Database Error");
        }
        $id = $tmp[0]["id"];

        $tatmp = explode(",", $data["tas"]);
        $intmp = explode(",", $data["instructors"]);

        $tas = [];
        $instructors = [];
        foreach ($tatmp as $ta) {
            $tmp = trim($ta);
            if ($tmp != "") {
                list ($uid, $name) = explode(" ", $tmp, 2);
                array_push($tas, ["userid" => $uid, "name" => $name]);
            }
        }
        foreach ($intmp as $in) {
            $tmp = trim($in);
            if ($tmp != "") {
                list ($uid, $name) = explode(" ", $tmp, 2);
                array_push($instructors, ["userid" => $uid, "name" => $name]);
            }
        }

        foreach ($tas as $ta) {
            $res = $this->db->query("insert into grader (userid, name, course_id, instructor) values ($1, $2, $3, false) returning *;", array($ta["userid"], $ta["name"], $id));
        }
        foreach ($instructors as $instructor) {
            $res = $this->db->query("insert into grader (userid, name, course_id, instructor) values ($1, $2, $3, true) returning *;", array($instructor["userid"], $instructor["name"], $id));
        }

        return $id;

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
                    array_push($students, ["id" => $grData[1], "name" => $grData[2] . ", " . $grData[3], "date" => $grData[5]]);
            }
            fclose($fp);
        }
        foreach ($students as $student) {
            $this->db->query("insert into submissions (homework_id, userid, name, submission_date) values ($1, $2, $3, $4);", array($id, $student["id"], $student["name"], $student["date"]));
            foreach ($problems as $problem) {
                $this->db->query("insert into grade (homework_id, problem_id, userid, name) values ($1, $2, $3, $4);", array($id, $problem["id"], $student["id"], $student["name"]));
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
        if ($_FILES['zipfile']['size'] > 1524000000) {
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
        $res = $this->db->query("select g.*, g.id as db_id, c.name as course_name, c.description as course_description from grader g, course c where g.course_id = c.id and userid = $1", array($uid));
        $data = $this->db->fetchAll($res);

        $user = [];
        $courses = [];
        foreach ($data as $line) {
            $user["id"] = $line["db_id"];
            $user["userid"] = $line["userid"];
            $user["name"] = $line["name"];
            $courses[$line["course_id"]] = [
                "id" => $line["course_id"],
                "name" => $line["course_name"],
                "description" => $line["course_description"],
                "instructor" => $line["instructor"] == 't' ? true : false
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

    public function showHappy($error) {
        $this->display("happy", ["notice" => $error]);
        die();
    }

    private function loadResults($hid, $cid) {
        $res = $this->db->query("select * from course c where c.id = $1", array($cid));
        $course = $this->db->fetchAll($res);
        if (count($course) != 1) {
            $this->showError("Database Error");
        }
        $course = $course[0];

        $res = $this->db->query("select * from homework h where h.id = $1 and h.course_id = $2", array($hid, $cid));
        $homework = $this->db->fetchAll($res);
        if (count($homework) != 1) {
            $this->showError("Database Error");
        }
        $homework = $homework[0];

        $res = $this->db->query("select * from problem p where p.homework_id = $1 order by p.id", array($hid));
        $problems = $this->db->fetchAll($res);
        if (count($problems) <= 0) {
            $this->showError("Database Error");
        }
        $ps = [];
        $max = 0;        
        foreach ($problems as $p) {
            $ps[$p["id"]] = $p;
            $max += $p["points"];
        }
        $homework["max"] = $max;

        $res = $this->db->query("select * from grade g where g.homework_id = $1 order by g.userid, g.problem_id", array($hid));
        $grades = $this->db->fetchAll($res);
        if (count($grades) <= 0) {
            $this->showError("Database Error");
        }

        $byuser = [];
        foreach ($grades as $grade) {
            $id = $grade["userid"];
            if (!isset($byuser[$grade["userid"]]))
                $byuser[$id] = [
                    "uva_id" => $id,
                    "name" => $grade["name"],
                    "problems" => []
                ];
            $byuser[$id]["problems"][$grade["problem_id"]] = [
                "grade" => $grade["grade"],
                "comment" => $grade["comment"]
            ];
        }
        
        $res = $this->db->query("select * from submissions where homework_id = $1 order by userid", array($hid));
        $subs = $this->db->fetchAll($res);
        if (count($subs) <= 0) {
            $this->showError("Database Error");
        }
        foreach ($subs as $sub) {
            if (isset($byuser[$sub["userid"]])) {
                $byuser[$sub["userid"]]["date"] = $sub["submission_date"];
            }
        }

        $results = [
            "info" => array_merge($homework, ["problems" => $ps]),
            "homeworks" => $byuser
        ];

        return $results;
    }
    
    /**
     * Download Grades
     *
     * Packages up the grades and creates a ZIP file compatible with 
     * UVA Collab for upload.  This creates the grades file, then turns
     * the submissions into PDFs for the students to view their submissions.
     *
     * @param int $onlyid optional currently unused
     * @return Contents of the created zipfile
     */
    public function downloadGrades($hid, $cid) {
        $results = $this->loadResults($hid, $cid);
        $info = $results["info"];
        //$dir = Config::$TEMP_DIR . "/".$results["info"]["title"];
        $zdir = $results["info"]["name"];
        $zip = new \ZipArchive();
        $zipname = Config::$TEMP_DIR . "/". $info["id"] .".zip";
        $zip->open($zipname, \ZipArchive::CREATE);
        $zip->addEmptyDir($zdir);

        $gradefile = [];
        array_push($gradefile, [$info["name"], "Points"]); 
        array_push($gradefile, []); 
        array_push($gradefile, 
            ["Display ID","ID","Last Name","First Name","grade","Submission date","Late submission"]
        );

        foreach ($results["homeworks"] as $hw) {
            // only download student grades

            $uzdir = "$zdir/{$hw["name"]}({$hw["uva_id"]})";
            $zip->addEmptyDir($zdir);


            $response = "";
            $comments = "";
            $score = 0;
            foreach ($info["problems"] as $q) {
                $comments .= "{$q["name"]}: ";
                if (isset($hw["problems"][$q["id"]])) {
                    if (isset($hw["problems"][$q["id"]]["grade"])) {
                        $comments .= $hw["problems"][$q["id"]]["grade"] . "/" . $q["points"];
                        $score += $hw["problems"][$q["id"]]["grade"];
                    } else {
                        $comments .= "0/".$q["points"];
                    }
                    if (isset($hw["problems"][$q["id"]]["comment"])) {
                        $comments .= " -- " . $hw["problems"][$q["id"]]["comment"];
                    }
                    $comments .= "\n";
                }
            }

            $score = round($score, 2);
            

            $comments .= "\n-------------------\n";

            // handle late policy
            $phi = (1 + sqrt(5)) / 2.0;
            $dueTime = strtotime($info["due_date"]);
            $timestamp = strtotime($hw["date"]);
            $diff = $timestamp - $dueTime;

            if ($diff > 0) {
                $diffD = ((double) $diff) / (60*60*24);
                $newGrade = round($score * exp((-1/(2*$phi))*$diffD), 2);
                $penalty = $score - $newGrade;
                // if the penalty outweighs the score, then set to 0
                if ($newGrade < 0) {
                    $newGrade = 0;
                    $penalty = $score;
                }

                $comments .= "Original Score: $score\nLate Penalty: $penalty\n\n";
                $score = $newGrade;
            }
            

            $comments .= "Final Score: $score\n";

            array_push($gradefile, [
                $hw["uva_id"],
                $hw["uva_id"],
                "", // last name
                "", // first name
                round($score, 2), // grade
                "", // submission date
                "" // late submission
            ]);


            $zip->addFromString("$uzdir/comments.txt", $comments);
        }

        // write the grades.csv file
        //$fp = fopen("$dir/grades.csv", 'w');
        //foreach ($gradefile as $fields) {
        //    fputcsv($fp, $fields);
        //}
        //fclose($fp);
        $gradefilecsv = "";
        foreach ($gradefile as $fields) {
            $gradefilecsv .= str_putcsv($fields) . "\n";
        }
        $zip->addFromString("$zdir/grades.csv", $gradefilecsv); 
        
        
        // close zip for downloading
        $zip->close();

        // show ZIP file
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=bulk_download.zip');
        header('Content-Length: ' . filesize($zipname));
        $zipfile = file_get_contents($zipname); 
        
        // remove the zip from the local filesystem
        unlink($zipname);

        // remove the temporary PDFs from the filesystem
        

        // return the contents of the zipfile
        echo $zipfile;
    }
}

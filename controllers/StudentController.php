<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';

class StudentController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    // GET /api/students
    public function index() {
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = max(1, intval($_GET['limit'] ?? 15));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE s.studentid ILIKE :search
                      OR s.firstname ILIKE :search
                      OR s.lastname  ILIKE :search";
            $params[':search'] = "%$search%";
        }

        // Total count
        $countSql = "SELECT COUNT(*) FROM public.tblstudent s $where";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Main query — join lookup tables
        $sql = "
            SELECT
                s.studentid,
                s.firstname,
                s.lastname,
                g.gender,
                s.age,
                co.country,
                ci.city,
                COUNT(e.courseid)           AS total_enrollments,
                ROUND(AVG(e.finalscore), 1) AS avg_final_score,
                COUNT(CASE WHEN cs.completionstatus = 'Completed' THEN 1 END) AS completed_courses
            FROM public.tblstudent s
            LEFT JOIN public.tblgender          g  ON s.genderid  = g.genderid
            LEFT JOIN public.tblcountry         co ON s.countryid = co.countryid
            LEFT JOIN public.tblcity            ci ON s.cityid    = ci.cityid
            LEFT JOIN public.tblenrollments     e  ON s.studentid = e.studentid
            LEFT JOIN public.tblcompletionstatus cs ON e.completionstatusid = cs.completionstatusid
            $where
            GROUP BY s.studentid, s.firstname, s.lastname, g.gender, s.age, co.country, ci.city
            ORDER BY s.studentid
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();

        Response::paginated($data, $total, $page, $limit);
    }

    // GET /api/students/:id
    public function show($id) {
        // Student info
        $sql = "
            SELECT
                s.studentid,
                s.firstname,
                s.lastname,
                g.gender,
                s.age,
                co.country,
                ci.city
            FROM public.tblstudent s
            LEFT JOIN public.tblgender  g  ON s.genderid  = g.genderid
            LEFT JOIN public.tblcountry co ON s.countryid = co.countryid
            LEFT JOIN public.tblcity    ci ON s.cityid    = ci.cityid
            WHERE s.studentid = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $student = $stmt->fetch();

        if (!$student) return Response::error('Student not found', 404);

        // Enrollments with engagement data
        $enrollSql = "
            SELECT
                e.courseid,
                c.coursename                    AS course_name,
                cc.coursecategory               AS course_category,
                i.instructorname                AS instructor_name,
                lm.learningmode,
                e.totaltimespenthours,
                e.assignmentssubmitted,
                e.assignmentspending,
                e.quizaverage,
                e.midtermscore,
                e.finalscore,
                og.overallgrade                 AS overall_grade,
                cs.completionstatus             AS completion_status,
                eng.logincount,
                eng.forumposts,
                eng.forumreplies,
                eng.certificatesearned,
                dv.devicetype,
                eng.internetspeedmbps,
                eng.lastlogindate
            FROM public.tblenrollments e
            LEFT JOIN public.tblcourse           c   ON e.courseid         = c.courseid
            LEFT JOIN public.tblcoursecategory   cc  ON c.coursecategoryid = cc.coursecategoryid
            LEFT JOIN public.tblinstructor       i   ON c.instructorid     = i.instructorid
            LEFT JOIN public.tbllearningmode     lm  ON e.learningmodeid   = lm.learningmodeid
            LEFT JOIN public.tbloverallgrade     og  ON e.gradeid          = og.overallgradeid
            LEFT JOIN public.tblcompletionstatus cs  ON e.completionstatusid = cs.completionstatusid
            LEFT JOIN public.tblengagements      eng ON e.studentid = eng.studentid AND e.courseid = eng.courseid
            LEFT JOIN public.tbldevicetype       dv  ON eng.devicetypeid   = dv.devicetypeid
            WHERE e.studentid = :id
        ";
        $enrollStmt = $this->db->prepare($enrollSql);
        $enrollStmt->execute([':id' => $id]);
        $student['enrollments'] = $enrollStmt->fetchAll();

        Response::success($student);
    }

    // POST /api/students
    public function store() {
        $data = json_decode(file_get_contents('php://input'), true);

        // Resolve lookup IDs
        $genderid  = $this->lookupOrCreate('tblgender',  'genderid',  'gender',  $data['gender']  ?? null, 'G', 2);
        $countryid = $this->lookupOrCreate('tblcountry', 'countryid', 'country', $data['country'] ?? null, 'CO', 2);
        $cityid    = $this->lookupOrCreate('tblcity',    'cityid',    'city',    $data['city']    ?? null, 'CI', 2);

        $sql = "
            INSERT INTO public.tblstudent (studentid, firstname, lastname, genderid, age, countryid, cityid)
            VALUES (:studentid, :firstname, :lastname, :genderid, :age, :countryid, :cityid)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':studentid' => $data['studentid'],
            ':firstname' => $data['firstname'],
            ':lastname'  => $data['lastname'],
            ':genderid'  => $genderid,
            ':age'       => $data['age'] ?? null,
            ':countryid' => $countryid,
            ':cityid'    => $cityid,
        ]);

        Response::success(['studentid' => $data['studentid']], 'Student created successfully', 201);
    }

    // PUT /api/students/:id
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $genderid  = $this->lookupOrCreate('tblgender',  'genderid',  'gender',  $data['gender']  ?? null, 'G', 2);
        $countryid = $this->lookupOrCreate('tblcountry', 'countryid', 'country', $data['country'] ?? null, 'CO', 2);
        $cityid    = $this->lookupOrCreate('tblcity',    'cityid',    'city',    $data['city']    ?? null, 'CI', 2);

        $sql = "
            UPDATE public.tblstudent
            SET firstname  = :firstname,
                lastname   = :lastname,
                genderid   = :genderid,
                age        = :age,
                countryid  = :countryid,
                cityid     = :cityid
            WHERE studentid = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':firstname' => $data['firstname'],
            ':lastname'  => $data['lastname'],
            ':genderid'  => $genderid,
            ':age'       => $data['age'] ?? null,
            ':countryid' => $countryid,
            ':cityid'    => $cityid,
            ':id'        => $id,
        ]);

        Response::success(null, 'Student updated successfully');
    }

    // DELETE /api/students/:id
    public function destroy($id) {
        // Delete engagements and enrollments first
        $this->db->prepare("DELETE FROM public.tblengagements  WHERE studentid = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM public.tblenrollments  WHERE studentid = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM public.tblstudent      WHERE studentid = :id")->execute([':id' => $id]);
        Response::success(null, 'Student deleted successfully');
    }

    // Helper: find existing lookup value or create new row
    private function lookupOrCreate($table, $idCol, $valCol, $value, $prefix, $padLen) {
        if (!$value) return null;
        $stmt = $this->db->prepare("SELECT $idCol FROM public.$table WHERE LOWER($valCol) = LOWER(:v)");
        $stmt->execute([':v' => $value]);
        $row = $stmt->fetch();
        if ($row) return $row[$idCol];

        // Create new entry
        $countStmt = $this->db->query("SELECT COUNT(*) FROM public.$table");
        $next = (int)$countStmt->fetchColumn() + 1;
        $newId = $prefix . lpad_php($next, $padLen);
        $ins = $this->db->prepare("INSERT INTO public.$table ($idCol, $valCol) VALUES (:id, :v)");
        $ins->execute([':id' => $newId, ':v' => $value]);
        return $newId;
    }
}

function lpad_php($n, $len) {
    return str_pad((string)$n, $len, '0', STR_PAD_LEFT);
}
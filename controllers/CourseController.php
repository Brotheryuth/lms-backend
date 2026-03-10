<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';

class CourseController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    // GET /api/courses
    public function index() {
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = max(1, intval($_GET['limit'] ?? 15));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $where  = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE c.courseid ILIKE :search OR c.coursename ILIKE :search";
            $params[':search'] = "%$search%";
        }

        $countSql = "SELECT COUNT(*) FROM public.tblcourse c $where";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                c.courseid,
                c.coursename                AS course_name,
                cc.coursecategory           AS course_category,
                d.departmentname            AS department,
                i.instructorname            AS instructor_name,
                COUNT(e.studentid)          AS total_enrollments,
                ROUND(AVG(e.finalscore),1)  AS avg_final_score,
                COUNT(CASE WHEN cs.completionstatus = 'Completed'   THEN 1 END) AS completed_count,
                COUNT(CASE WHEN cs.completionstatus = 'Dropped'     THEN 1 END) AS dropped_count,
                COUNT(CASE WHEN cs.completionstatus = 'In Progress' THEN 1 END) AS in_progress_count
            FROM public.tblcourse c
            LEFT JOIN public.tblcoursecategory   cc ON c.coursecategoryid = cc.coursecategoryid
            LEFT JOIN public.tbldepartment        d ON c.departmentid     = d.departmentid
            LEFT JOIN public.tblinstructor        i ON c.instructorid     = i.instructorid
            LEFT JOIN public.tblenrollments       e ON c.courseid         = e.courseid
            LEFT JOIN public.tblcompletionstatus cs ON e.completionstatusid = cs.completionstatusid
            $where
            GROUP BY c.courseid, c.coursename, cc.coursecategory, d.departmentname, i.instructorname
            ORDER BY c.courseid
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }

    // GET /api/courses/:id
    public function show($id) {
        $sql = "
            SELECT
                c.courseid,
                c.coursename                AS course_name,
                cc.coursecategory           AS course_category,
                d.departmentname            AS department,
                i.instructorname            AS instructor_name,
                COUNT(e.studentid)          AS total_enrollments,
                ROUND(AVG(e.finalscore),1)  AS avg_final_score,
                ROUND(AVG(e.midtermscore),1)AS avg_midterm_score,
                ROUND(AVG(e.quizaverage),1) AS avg_quiz,
                COUNT(CASE WHEN cs.completionstatus = 'Completed'   THEN 1 END) AS completed_count,
                COUNT(CASE WHEN cs.completionstatus = 'Dropped'     THEN 1 END) AS dropped_count,
                COUNT(CASE WHEN cs.completionstatus = 'In Progress' THEN 1 END) AS in_progress_count
            FROM public.tblcourse c
            LEFT JOIN public.tblcoursecategory   cc ON c.coursecategoryid = cc.coursecategoryid
            LEFT JOIN public.tbldepartment        d ON c.departmentid     = d.departmentid
            LEFT JOIN public.tblinstructor        i ON c.instructorid     = i.instructorid
            LEFT JOIN public.tblenrollments       e ON c.courseid         = e.courseid
            LEFT JOIN public.tblcompletionstatus cs ON e.completionstatusid = cs.completionstatusid
            WHERE c.courseid = :id
            GROUP BY c.courseid, c.coursename, cc.coursecategory, d.departmentname, i.instructorname
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $course = $stmt->fetch();

        if (!$course) return Response::error('Course not found', 404);
        Response::success($course);
    }

    // POST /api/courses
    public function store() {
        $data = json_decode(file_get_contents('php://input'), true);

        $categoryid = $this->lookupId('tblcoursecategory', 'coursecategoryid', 'coursecategory', $data['course_category'] ?? null);
        $deptid     = $this->lookupId('tbldepartment',     'departmentid',     'departmentname', $data['department']      ?? null);

        $sql = "INSERT INTO public.tblcourse (courseid, coursename, coursecategoryid, departmentid, instructorid)
                VALUES (:courseid, :coursename, :categoryid, :deptid, :instructorid)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':courseid'     => $data['courseid'],
            ':coursename'   => $data['course_name'],
            ':categoryid'   => $categoryid,
            ':deptid'       => $deptid,
            ':instructorid' => $data['instructor_id'] ?? null,
        ]);

        Response::success(['courseid' => $data['courseid']], 'Course created successfully', 201);
    }

    // PUT /api/courses/:id
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $categoryid = $this->lookupId('tblcoursecategory', 'coursecategoryid', 'coursecategory', $data['course_category'] ?? null);
        $deptid     = $this->lookupId('tbldepartment',     'departmentid',     'departmentname', $data['department']      ?? null);

        $sql = "UPDATE public.tblcourse
                SET coursename = :coursename, coursecategoryid = :categoryid,
                    departmentid = :deptid, instructorid = :instructorid
                WHERE courseid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':coursename'   => $data['course_name'],
            ':categoryid'   => $categoryid,
            ':deptid'       => $deptid,
            ':instructorid' => $data['instructor_id'] ?? null,
            ':id'           => $id,
        ]);

        Response::success(null, 'Course updated successfully');
    }

    // DELETE /api/courses/:id
    public function destroy($id) {
        $this->db->prepare("DELETE FROM public.tblengagements WHERE courseid = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM public.tblenrollments WHERE courseid = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM public.tblcourse      WHERE courseid = :id")->execute([':id' => $id]);
        Response::success(null, 'Course deleted successfully');
    }

    private function lookupId($table, $idCol, $valCol, $value) {
        if (!$value) return null;
        $stmt = $this->db->prepare("SELECT $idCol FROM public.$table WHERE LOWER($valCol) = LOWER(:v)");
        $stmt->execute([':v' => $value]);
        $row = $stmt->fetch();
        return $row ? $row[$idCol] : null;
    }
}
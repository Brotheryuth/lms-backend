<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';

class EnrollmentController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    // GET /api/enrollments
    public function index() {
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = max(1, intval($_GET['limit'] ?? 15));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = "(s.firstname ILIKE :search OR s.lastname ILIKE :search OR c.coursename ILIKE :search OR e.studentid ILIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($status !== '') {
            $conditions[] = "cs.completionstatus = :status";
            $params[':status'] = $status;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "
            SELECT COUNT(*)
            FROM public.tblenrollments e
            LEFT JOIN public.tblstudent          s  ON e.studentid        = s.studentid
            LEFT JOIN public.tblcourse           c  ON e.courseid         = c.courseid
            LEFT JOIN public.tblcompletionstatus cs ON e.completionstatusid = cs.completionstatusid
            $where
        ";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                e.studentid,
                e.courseid,
                s.firstname,
                s.lastname,
                c.coursename                AS course_name,
                cc.coursecategory           AS course_category,
                lm.learningmode,
                e.totaltimespenthours,
                e.assignmentssubmitted,
                e.assignmentspending,
                e.quizaverage,
                e.midtermscore,
                e.finalscore,
                og.overallgrade             AS overall_grade,
                cs.completionstatus         AS completion_status,
                e.enrollmentdate,
                eng.logincount,
                eng.lastlogindate,
                eng.certificatesearned,
                eng.forumposts,
                eng.forumreplies,
                dv.devicetype,
                eng.internetspeedmbps,
                CONCAT(e.studentid, '-', e.courseid) AS id
            FROM public.tblenrollments e
            LEFT JOIN public.tblstudent          s   ON e.studentid          = s.studentid
            LEFT JOIN public.tblcourse           c   ON e.courseid           = c.courseid
            LEFT JOIN public.tblcoursecategory   cc  ON c.coursecategoryid   = cc.coursecategoryid
            LEFT JOIN public.tbllearningmode     lm  ON e.learningmodeid     = lm.learningmodeid
            LEFT JOIN public.tbloverallgrade     og  ON e.gradeid            = og.overallgradeid
            LEFT JOIN public.tblcompletionstatus cs  ON e.completionstatusid = cs.completionstatusid
            LEFT JOIN public.tblengagements      eng ON e.studentid = eng.studentid AND e.courseid = eng.courseid
            LEFT JOIN public.tbldevicetype       dv  ON eng.devicetypeid     = dv.devicetypeid
            $where
            ORDER BY e.studentid, e.courseid
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }

    // GET /api/enrollments/:id  (id = studentid-courseid)
    public function show($id) {
        [$studentid, $courseid] = explode('-', $id, 2);

        $sql = "
            SELECT
                e.studentid, e.courseid,
                s.firstname, s.lastname,
                c.coursename AS course_name,
                lm.learningmode,
                e.totaltimespenthours,
                e.assignmentssubmitted,
                e.assignmentspending,
                e.quizaverage,
                e.midtermscore,
                e.finalscore,
                og.overallgrade             AS overall_grade,
                cs.completionstatus         AS completion_status,
                e.enrollmentdate,
                eng.logincount, eng.forumposts, eng.forumreplies,
                eng.certificatesearned, eng.internetspeedmbps, eng.lastlogindate,
                dv.devicetype,
                CONCAT(e.studentid, '-', e.courseid) AS id
            FROM public.tblenrollments e
            LEFT JOIN public.tblstudent          s   ON e.studentid        = s.studentid
            LEFT JOIN public.tblcourse           c   ON e.courseid         = c.courseid
            LEFT JOIN public.tbllearningmode     lm  ON e.learningmodeid   = lm.learningmodeid
            LEFT JOIN public.tbloverallgrade     og  ON e.gradeid          = og.overallgradeid
            LEFT JOIN public.tblcompletionstatus cs  ON e.completionstatusid = cs.completionstatusid
            LEFT JOIN public.tblengagements      eng ON e.studentid = eng.studentid AND e.courseid = eng.courseid
            LEFT JOIN public.tbldevicetype       dv  ON eng.devicetypeid   = dv.devicetypeid
            WHERE e.studentid = :sid AND e.courseid = :cid
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':sid' => $studentid, ':cid' => $courseid]);
        $row = $stmt->fetch();
        if (!$row) return Response::error('Enrollment not found', 404);
        Response::success($row);
    }

    // POST /api/enrollments
    public function store() {
        $data = json_decode(file_get_contents('php://input'), true);

        $learningmodeid    = $this->lookupId('tbllearningmode',     'learningmodeid',    'learningmode',    $data['learning_mode']      ?? null);
        $gradeid           = $this->lookupId('tbloverallgrade',     'overallgradeid',    'overallgrade',    $data['overall_grade']      ?? null);
        $completionstatusid= $this->lookupId('tblcompletionstatus', 'completionstatusid','completionstatus',$data['completion_status']   ?? 'In Progress');

        $sql = "
            INSERT INTO public.tblenrollments
                (studentid, courseid, enrollmentdate, learningmodeid, totaltimespenthours,
                 assignmentssubmitted, assignmentspending, quizaverage, midtermscore,
                 finalscore, gradeid, completionstatusid)
            VALUES
                (:studentid, :courseid, :enrollmentdate, :learningmodeid, :timespent,
                 :submitted, :pending, :quiz, :midterm, :final, :gradeid, :statusid)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':studentid'      => $data['student_id'],
            ':courseid'       => $data['course_id'],
            ':enrollmentdate' => $data['enrollment_date'] ?? date('Y-m-d'),
            ':learningmodeid' => $learningmodeid,
            ':timespent'      => $data['total_time_spent_hours'] ?? null,
            ':submitted'      => $data['assignments_submitted'] ?? null,
            ':pending'        => $data['assignments_pending'] ?? null,
            ':quiz'           => $data['quiz_average'] ?? null,
            ':midterm'        => $data['midterm_score'] ?? null,
            ':final'          => $data['final_score'] ?? null,
            ':gradeid'        => $gradeid,
            ':statusid'       => $completionstatusid,
        ]);

        Response::success(null, 'Enrollment created successfully', 201);
    }

    // PUT /api/enrollments/:id
    public function update($id) {
        [$studentid, $courseid] = explode('-', $id, 2);
        $data = json_decode(file_get_contents('php://input'), true);

        $learningmodeid     = $this->lookupId('tbllearningmode',     'learningmodeid',    'learningmode',    $data['learning_mode']    ?? null);
        $gradeid            = $this->lookupId('tbloverallgrade',     'overallgradeid',    'overallgrade',    $data['overall_grade']    ?? null);
        $completionstatusid = $this->lookupId('tblcompletionstatus', 'completionstatusid','completionstatus',$data['completion_status'] ?? null);

        $sql = "
            UPDATE public.tblenrollments
            SET learningmodeid     = :learningmodeid,
                totaltimespenthours= :timespent,
                assignmentssubmitted=:submitted,
                assignmentspending = :pending,
                quizaverage        = :quiz,
                midtermscore       = :midterm,
                finalscore         = :final,
                gradeid            = :gradeid,
                completionstatusid = :statusid
            WHERE studentid = :sid AND courseid = :cid
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':learningmodeid' => $learningmodeid,
            ':timespent'      => $data['total_time_spent_hours'] ?? null,
            ':submitted'      => $data['assignments_submitted'] ?? null,
            ':pending'        => $data['assignments_pending'] ?? null,
            ':quiz'           => $data['quiz_average'] ?? null,
            ':midterm'        => $data['midterm_score'] ?? null,
            ':final'          => $data['final_score'] ?? null,
            ':gradeid'        => $gradeid,
            ':statusid'       => $completionstatusid,
            ':sid'            => $studentid,
            ':cid'            => $courseid,
        ]);

        // Update engagement if provided
        if (isset($data['login_count'])) {
            $engSql = "
                UPDATE public.tblengagements
                SET logincount = :login, forumposts = :posts, forumreplies = :replies,
                    certificatesearned = :certs, internetspeedmbps = :speed
                WHERE studentid = :sid AND courseid = :cid
            ";
            $this->db->prepare($engSql)->execute([
                ':login'   => $data['login_count'] ?? null,
                ':posts'   => $data['forum_posts'] ?? null,
                ':replies' => $data['forum_replies'] ?? null,
                ':certs'   => $data['certificates_earned'] ?? null,
                ':speed'   => $data['internet_speed_mbps'] ?? null,
                ':sid'     => $studentid,
                ':cid'     => $courseid,
            ]);
        }

        Response::success(null, 'Enrollment updated successfully');
    }

    // DELETE /api/enrollments/:id
    public function destroy($id) {
        [$studentid, $courseid] = explode('-', $id, 2);
        $this->db->prepare("DELETE FROM public.tblengagements WHERE studentid=:s AND courseid=:c")->execute([':s'=>$studentid,':c'=>$courseid]);
        $this->db->prepare("DELETE FROM public.tblenrollments WHERE studentid=:s AND courseid=:c")->execute([':s'=>$studentid,':c'=>$courseid]);
        Response::success(null, 'Enrollment deleted successfully');
    }

    private function lookupId($table, $idCol, $valCol, $value) {
        if (!$value) return null;
        $stmt = $this->db->prepare("SELECT $idCol FROM public.$table WHERE LOWER($valCol) = LOWER(:v)");
        $stmt->execute([':v' => $value]);
        $row = $stmt->fetch();
        return $row ? $row[$idCol] : null;
    }
}
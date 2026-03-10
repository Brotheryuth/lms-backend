<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';

class AnalyticsController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    // GET /api/analytics/overview
    public function overview() {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM public.tblstudent)     AS total_students,
                (SELECT COUNT(*) FROM public.tblcourse)      AS total_courses,
                (SELECT COUNT(*) FROM public.tblinstructor)  AS total_instructors,
                (SELECT COUNT(*) FROM public.tblenrollments) AS total_enrollments,
                ROUND(
                    100.0 * COUNT(CASE WHEN cs.completionstatus = 'Completed' THEN 1 END)
                    / NULLIF(COUNT(*), 0), 1
                ) AS completion_rate,
                ROUND(
                    100.0 * COUNT(CASE WHEN cs.completionstatus = 'Dropped' THEN 1 END)
                    / NULLIF(COUNT(*), 0), 1
                ) AS drop_rate,
                ROUND(AVG(e.finalscore),  1) AS avg_final_score,
                ROUND(AVG(e.midtermscore),1) AS avg_midterm_score,
                ROUND(AVG(e.quizaverage), 1) AS avg_quiz_score,
                ROUND(AVG(eng.totaltimespenthours), 1) AS avg_time_spent_hours,
                SUM(eng2.certificatesearned) AS total_certificates
            FROM public.tblenrollments e
            LEFT JOIN public.tblcompletionstatus cs  ON e.completionstatusid = cs.completionstatusid
            LEFT JOIN (
                SELECT studentid, courseid, totaltimespenthours FROM public.tblenrollments
            ) eng ON e.studentid = eng.studentid AND e.courseid = eng.courseid
            LEFT JOIN public.tblengagements eng2 ON e.studentid = eng2.studentid AND e.courseid = eng2.courseid
        ";
        $stmt = $this->db->query($sql);
        Response::success($stmt->fetch());
    }

    // GET /api/analytics/completion-rate
    public function completionRate() {
        $byCategory = $this->db->query("
            SELECT
                cc.coursecategory AS course_category,
                ROUND(100.0 * COUNT(CASE WHEN cs.completionstatus = 'Completed' THEN 1 END) / NULLIF(COUNT(*),0), 1) AS completion_rate,
                ROUND(100.0 * COUNT(CASE WHEN cs.completionstatus = 'Dropped'   THEN 1 END) / NULLIF(COUNT(*),0), 1) AS drop_rate
            FROM public.tblenrollments e
            LEFT JOIN public.tblcourse           c  ON e.courseid         = c.courseid
            LEFT JOIN public.tblcoursecategory   cc ON c.coursecategoryid = cc.coursecategoryid
            LEFT JOIN public.tblcompletionstatus cs ON e.completionstatusid = cs.completionstatusid
            GROUP BY cc.coursecategory ORDER BY cc.coursecategory
        ")->fetchAll();

        $byMode = $this->db->query("
            SELECT
                lm.learningmode,
                ROUND(100.0 * COUNT(CASE WHEN cs.completionstatus = 'Completed' THEN 1 END) / NULLIF(COUNT(*),0), 1) AS completion_rate
            FROM public.tblenrollments e
            LEFT JOIN public.tbllearningmode     lm ON e.learningmodeid   = lm.learningmodeid
            LEFT JOIN public.tblcompletionstatus cs ON e.completionstatusid = cs.completionstatusid
            GROUP BY lm.learningmode
        ")->fetchAll();

        Response::success(['by_category' => $byCategory, 'by_mode' => $byMode]);
    }

    // GET /api/analytics/drop-rate
    public function dropRate() {
        $byMonth = $this->db->query("
            SELECT
                TO_CHAR(e.enrollmentdate, 'YYYY-MM') AS month,
                ROUND(100.0 * COUNT(CASE WHEN cs.completionstatus = 'Dropped' THEN 1 END) / NULLIF(COUNT(*),0), 1) AS drop_rate
            FROM public.tblenrollments e
            LEFT JOIN public.tblcompletionstatus cs ON e.completionstatusid = cs.completionstatusid
            GROUP BY month ORDER BY month
        ")->fetchAll();

        Response::success(['by_month' => $byMonth]);
    }

    // GET /api/analytics/scores
    public function scores() {
        $byCourse = $this->db->query("
            SELECT
                c.coursename AS course_name,
                ROUND(AVG(e.midtermscore), 1) AS avg_midterm,
                ROUND(AVG(e.finalscore),   1) AS avg_final,
                ROUND(AVG(e.quizaverage),  1) AS avg_quiz,
                COUNT(*) AS total_enrollments
            FROM public.tblenrollments e
            LEFT JOIN public.tblcourse c ON e.courseid = c.courseid
            GROUP BY c.coursename
            ORDER BY total_enrollments DESC
            LIMIT 10
        ")->fetchAll();

        Response::success(['by_course' => $byCourse]);
    }

    // GET /api/analytics/grades
    public function grades() {
        $distribution = $this->db->query("
            SELECT
                og.overallgrade AS overall_grade,
                COUNT(*) AS total
            FROM public.tblenrollments e
            LEFT JOIN public.tbloverallgrade og ON e.gradeid = og.overallgradeid
            GROUP BY og.overallgrade ORDER BY og.overallgrade
        ")->fetchAll();

        Response::success(['distribution' => $distribution]);
    }

    // GET /api/analytics/engagement
    public function engagement() {
        $byMode = $this->db->query("
            SELECT
                lm.learningmode,
                ROUND(AVG(e.totaltimespenthours), 1) AS avg_time_spent,
                ROUND(AVG(eng.logincount), 1)         AS avg_login_count,
                ROUND(AVG(e.assignmentssubmitted), 1) AS avg_assignments
            FROM public.tblenrollments e
            LEFT JOIN public.tbllearningmode lm  ON e.learningmodeid = lm.learningmodeid
            LEFT JOIN public.tblengagements  eng ON e.studentid = eng.studentid AND e.courseid = eng.courseid
            GROUP BY lm.learningmode
        ")->fetchAll();

        Response::success(['by_mode' => $byMode]);
    }

    // GET /api/analytics/at-risk
    public function atRisk() {
        $data = $this->db->query("
            SELECT
                s.studentid,
                s.firstname,
                s.lastname,
                c.coursename        AS course_name,
                e.quizaverage       AS quiz_average,
                e.midtermscore      AS midterm_score,
                e.assignmentspending AS assignments_pending,
                eng.logincount      AS login_count,
                eng.lastlogindate   AS last_login_date,
                cs.completionstatus AS completion_status
            FROM public.tblenrollments e
            LEFT JOIN public.tblstudent          s   ON e.studentid        = s.studentid
            LEFT JOIN public.tblcourse           c   ON e.courseid         = c.courseid
            LEFT JOIN public.tblcompletionstatus cs  ON e.completionstatusid = cs.completionstatusid
            LEFT JOIN public.tblengagements      eng ON e.studentid = eng.studentid AND e.courseid = eng.courseid
            WHERE cs.completionstatus = 'In Progress'
              AND (e.quizaverage < 60 OR e.assignmentspending > 3 OR eng.logincount < 10)
            ORDER BY e.quizaverage ASC
            LIMIT 50
        ")->fetchAll();

        Response::success($data);
    }

    // GET /api/analytics/devices
    public function devices() {
        $byDevice = $this->db->query("
            SELECT
                dv.devicetype,
                COUNT(DISTINCT eng.studentid) AS total_students,
                ROUND(AVG(e.finalscore), 1)   AS avg_final_score
            FROM public.tblengagements eng
            LEFT JOIN public.tbldevicetype   dv ON eng.devicetypeid = dv.devicetypeid
            LEFT JOIN public.tblenrollments  e  ON eng.studentid = e.studentid AND eng.courseid = e.courseid
            GROUP BY dv.devicetype ORDER BY total_students DESC
        ")->fetchAll();

        $bySpeed = $this->db->query("
            SELECT
                CASE
                    WHEN eng.internetspeedmbps < 10  THEN 'Slow (<10 Mbps)'
                    WHEN eng.internetspeedmbps < 50  THEN 'Medium (10-50 Mbps)'
                    ELSE 'Fast (>50 Mbps)'
                END AS speed_category,
                COUNT(DISTINCT eng.studentid) AS total_students,
                ROUND(AVG(e.finalscore), 1)   AS avg_final_score
            FROM public.tblengagements eng
            LEFT JOIN public.tblenrollments e ON eng.studentid = e.studentid AND eng.courseid = e.courseid
            GROUP BY speed_category ORDER BY total_students DESC
        ")->fetchAll();

        Response::success(['by_device' => $byDevice, 'by_speed' => $bySpeed]);
    }
}
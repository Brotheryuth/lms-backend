<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';

class InstructorController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    // GET /api/instructors
    public function index() {
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = max(1, intval($_GET['limit'] ?? 200));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        $where  = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE i.instructorid ILIKE :search OR i.instructorname ILIKE :search";
            $params[':search'] = "%$search%";
        }

        $countSql  = "SELECT COUNT(*) FROM public.tblinstructor i $where";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                i.instructorid,
                i.instructorname            AS instructor_name,
                COUNT(DISTINCT c.courseid)  AS total_courses,
                COUNT(e.studentid)          AS total_enrollments,
                ROUND(AVG(e.finalscore),1)  AS avg_student_score
            FROM public.tblinstructor i
            LEFT JOIN public.tblcourse      c ON i.instructorid = c.instructorid
            LEFT JOIN public.tblenrollments e ON c.courseid     = e.courseid
            $where
            GROUP BY i.instructorid, i.instructorname
            ORDER BY i.instructorid
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::paginated($stmt->fetchAll(), $total, $page, $limit);
    }

    // GET /api/instructors/:id
    public function show($id) {
        $sql = "
            SELECT
                i.instructorid,
                i.instructorname            AS instructor_name,
                COUNT(DISTINCT c.courseid)  AS total_courses,
                COUNT(e.studentid)          AS total_enrollments,
                ROUND(AVG(e.finalscore),1)  AS avg_student_score
            FROM public.tblinstructor i
            LEFT JOIN public.tblcourse      c ON i.instructorid = c.instructorid
            LEFT JOIN public.tblenrollments e ON c.courseid     = e.courseid
            WHERE i.instructorid = :id
            GROUP BY i.instructorid, i.instructorname
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $instructor = $stmt->fetch();

        if (!$instructor) return Response::error('Instructor not found', 404);
        Response::success($instructor);
    }

    // POST /api/instructors
    public function store() {
        $data = json_decode(file_get_contents('php://input'), true);
        $sql  = "INSERT INTO public.tblinstructor (instructorid, instructorname) VALUES (:id, :name)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $data['instructor_id'], ':name' => $data['instructor_name']]);
        Response::success(['instructorid' => $data['instructor_id']], 'Instructor created', 201);
    }

    // PUT /api/instructors/:id
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $sql  = "UPDATE public.tblinstructor SET instructorname = :name WHERE instructorid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $data['instructor_name'], ':id' => $id]);
        Response::success(null, 'Instructor updated successfully');
    }

    // DELETE /api/instructors/:id
    public function destroy($id) {
        // Unlink from courses first
        $this->db->prepare("UPDATE public.tblcourse SET instructorid = NULL WHERE instructorid = :id")->execute([':id' => $id]);
        $this->db->prepare("DELETE FROM public.tblinstructor WHERE instructorid = :id")->execute([':id' => $id]);
        Response::success(null, 'Instructor deleted successfully');
    }
}
<?php
// Database Helper Functions for SQL Server
require_once 'config.php';

// User-related functions
function getUserByUsername($username) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in getUserByUsername: " . $e->getMessage());
        return false;
    }
}

function getUserById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in getUserById: " . $e->getMessage());
        return false;
    }
}

function getAllUsers() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, role, faculty_id, created_at, is_super_admin FROM users ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getAllUsers: " . $e->getMessage());
        return [];
    }
}

function getAllFaculties() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name FROM faculty ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getAllFaculties: " . $e->getMessage());
        return [];
    }
}

function getFacultyById($id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name FROM faculty WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in getFacultyById: " . $e->getMessage());
        return false;
    }
}

function createFaculty($name) {
    try {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'message' => 'Numele facultății este obligatoriu'];
        }
        $pdo = getDBConnection();
        // Duplicate check (case-insensitive)
        $dup = $pdo->prepare("SELECT 1 FROM faculty WHERE LOWER(name) = LOWER(?)");
        $dup->execute([$name]);
        if ($dup->fetch()) {
            return ['success' => false, 'message' => 'Există deja o facultate cu acest nume'];
        }
        $stmt = $pdo->prepare("INSERT INTO faculty (id, name) VALUES (NEWID(), ?)");
        $ok = $stmt->execute([$name]);
        if ($ok) {
            return ['success' => true, 'message' => 'Facultatea a fost creată cu succes'];
        }
        return ['success' => false, 'message' => 'Nu s-a putut crea facultatea'];
    } catch (PDOException $e) {
        error_log("Database error in createFaculty: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

function updateFaculty($id, $name) {
    try {
        $name = trim($name);
        if (empty($id) || $name === '') {
            return ['success' => false, 'message' => 'ID și nume sunt obligatorii'];
        }
        $pdo = getDBConnection();
        // Duplicate check (exclude self, case-insensitive)
        $dup = $pdo->prepare("SELECT 1 FROM faculty WHERE LOWER(name) = LOWER(?) AND id <> ?");
        $dup->execute([$name, $id]);
        if ($dup->fetch()) {
            return ['success' => false, 'message' => 'Există deja o facultate cu acest nume'];
        }
        $stmt = $pdo->prepare("UPDATE faculty SET name = ? WHERE id = ?");
        $ok = $stmt->execute([$name, $id]);
        if ($ok) {
            // Consider successful even if rowCount is 0 (name unchanged)
            return ['success' => true, 'message' => 'Facultatea a fost actualizată'];
        }
        return ['success' => false, 'message' => 'Actualizarea nu a reușit'];
    } catch (PDOException $e) {
        error_log("Database error in updateFaculty: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

function deleteFaculty($id) {
    try {
        if (empty($id)) {
            return ['success' => false, 'message' => 'ID este obligatoriu'];
        }
        
        $pdo = getDBConnection();
        
        // First, check if any students are assigned to this faculty
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM users WHERE faculty_id = ? AND role = 'student'");
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch();
        
        if ($result['student_count'] > 0) {
            return [
                'success' => false, 
                'message' => "Nu se poate șterge facultatea. Există {$result['student_count']} studenți înscriși la această facultate. Vă rugăm să-i reasignați mai întâi."
            ];
        }
        
        // If no students are assigned, proceed with deletion
        $stmt = $pdo->prepare("DELETE FROM faculty WHERE id = ?");
        $ok = $stmt->execute([$id]);
        if ($ok && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Facultatea a fost ștearsă'];
        }
        return ['success' => false, 'message' => 'Facultatea nu a fost găsită sau nu a putut fi ștearsă'];
    } catch (PDOException $e) {
        // Likely foreign key constraint if users reference this faculty
        if (stripos($e->getMessage(), 'FOREIGN KEY') !== false || stripos($e->getMessage(), 'constraint') !== false) {
            return ['success' => false, 'message' => 'Nu se poate șterge: există utilizatori asociați acestei facultăți'];
        }
        error_log("Database error in deleteFaculty: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

function getStudentsByFaculty($facultyId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE faculty_id = ? AND role = 'student' ORDER BY username");
        $stmt->execute([$facultyId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getStudentsByFaculty: " . $e->getMessage());
        return [];
    }
}

// ==================== MATERII FUNCTIONS ====================

function getAllMaterii() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                m.id, 
                m.name, 
                m.[year], 
                m.[semester], 
                m.credits,
                m.created_at,
                m.updated_at,
                STRING_AGG(f.name, ', ') as faculty_names
            FROM subjects m
            LEFT JOIN subjects_faculties mf ON m.id = mf.materie_id
            LEFT JOIN faculty f ON mf.faculty_id = f.id
            GROUP BY m.id, m.name, m.[year], m.[semester], m.credits, m.created_at, m.updated_at
            ORDER BY m.[year] ASC, m.[semester] ASC, m.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getAllMaterii: " . $e->getMessage());
        return [];
    }
}

function getMaterieById($materieId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$materieId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in getMaterieById: " . $e->getMessage());
        return null;
    }
}

function getMaterieFaculties($materieId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT f.id, f.name 
            FROM faculty f 
            JOIN subjects_faculties mf ON f.id = mf.faculty_id 
            WHERE mf.materie_id = ?
        ");
        $stmt->execute([$materieId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getMaterieFaculties: " . $e->getMessage());
        return [];
    }
}

/**
 * Returns materii assigned to the given faculty ID.
 */
function getMateriiByFacultyId($facultyId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT m.id, m.name, m.[year], m.[semester], m.credits, m.created_at, m.updated_at
            FROM subjects m
            JOIN subjects_faculties mf ON m.id = mf.materie_id
            WHERE mf.faculty_id = ?
            ORDER BY m.[year] ASC, m.[semester] ASC, m.name ASC
        ");
        $stmt->execute([$facultyId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getMateriiByFacultyId: " . $e->getMessage());
        return [];
    }
}

function createMaterie($materieData, $facultyIds) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Generate UUID for materie
        $materieId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $now = date('Y-m-d H:i:s');
        
        // Insert materie
        $stmt = $pdo->prepare("
            INSERT INTO subjects (id, name, [year], [semester], credits, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $materieId,
            $materieData['name'],
            $materieData['year'],
            $materieData['semester'],
            $materieData['credits'] ?? 6,
            $now,
            $now
        ]);
        
        // Insert faculty associations
        $facultyStmt = $pdo->prepare("INSERT INTO subjects_faculties (materie_id, faculty_id, assigned_at) VALUES (?, ?, ?)");
        foreach ($facultyIds as $facultyId) {
            if (!empty($facultyId)) {
                $facultyStmt->execute([$materieId, $facultyId, $now]);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Materia a fost creată cu succes', 'materie_id' => $materieId];
    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("Database error in createMaterie: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

function updateMaterie($materieId, $materieData, $facultyIds) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        $now = date('Y-m-d H:i:s');
        
        // Update materie data
        $stmt = $pdo->prepare("
            UPDATE subjects 
            SET name = ?, [year] = ?, [semester] = ?, credits = ?, updated_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $materieData['name'],
            $materieData['year'],
            $materieData['semester'],
            $materieData['credits'] ?? 6,
            $now,
            $materieId
        ]);
        
        // Delete existing faculty associations
        $deleteStmt = $pdo->prepare("DELETE FROM subjects_faculties WHERE materie_id = ?");
        $deleteStmt->execute([$materieId]);
        
        // Insert new faculty associations
        $facultyStmt = $pdo->prepare("INSERT INTO subjects_faculties (materie_id, faculty_id, assigned_at) VALUES (?, ?, ?)");
        foreach ($facultyIds as $facultyId) {
            if (!empty($facultyId)) {
                $facultyStmt->execute([$materieId, $facultyId, $now]);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Materia a fost actualizată cu succes'];
    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("Database error in updateMaterie: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

function deleteMaterie($materieId) {
    try {
        $pdo = getDBConnection();
        
        // Check if materie has courses
        $courseCheckStmt = $pdo->prepare("SELECT COUNT(*) as course_count FROM courses_subjects WHERE materie_id = ?");
        $courseCheckStmt->execute([$materieId]);
        $courseCount = $courseCheckStmt->fetch()['course_count'];
        
        if ($courseCount > 0) {
            return ['success' => false, 'message' => 'Nu puteți șterge o materie care are cursuri asociate.'];
        }
        
        // Delete materie (CASCADE will handle subjects_faculties)
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $success = $stmt->execute([$materieId]);
        
        if ($success && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Materia a fost ștearsă cu succes'];
        } else {
            return ['success' => false, 'message' => 'Materia nu a fost găsită sau nu a putut fi ștearsă'];
        }
    } catch (PDOException $e) {
        error_log("Database error in deleteMaterie: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

// ==================== COURSES FUNCTIONS ====================

function getAllCourses() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.title, 
                c.type, 
                c.file_name, 
                c.file_path, 
                c.file_size, 
                c.mime_type,
                c.downloads,
                c.uploaded_by,
                c.created_at,
                c.updated_at,
                u.username as uploaded_by_name,
                (
                    SELECT STRING_AGG(s.name, ', ')
                    FROM (
                        SELECT DISTINCT m2.name
                        FROM courses_subjects cm2
                        JOIN subjects m2 ON cm2.materie_id = m2.id
                        WHERE cm2.course_id = c.id
                    ) s
                ) as materii_names,
                (
                    SELECT STRING_AGG(s2.item, '; ')
                    FROM (
                        SELECT DISTINCT CONCAT(m3.name, ' (', f3.name, ')') AS item
                        FROM courses_subjects cm3
                        JOIN subjects m3 ON cm3.materie_id = m3.id
                        LEFT JOIN subjects_faculties mf3 ON m3.id = mf3.materie_id
                        LEFT JOIN faculty f3 ON mf3.faculty_id = f3.id
                        WHERE cm3.course_id = c.id
                    ) s2
                ) as detailed_assignments
            FROM courses c
            LEFT JOIN users u ON c.uploaded_by = u.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getAllCourses: " . $e->getMessage());
        return [];
    }
}

function getCourseById($courseId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$courseId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in getCourseById: " . $e->getMessage());
        return null;
    }
}

function getCourseMaterii($courseId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT m.id, m.name, m.year, m.semester
            FROM subjects m 
            JOIN courses_subjects cm ON m.id = cm.materie_id 
            WHERE cm.course_id = ?
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getCourseMaterii: " . $e->getMessage());
        return [];
    }
}

// Legacy function for backward compatibility during transition
function getCourseFaculties($courseId) {
    try {
        $pdo = getDBConnection();
        // Get faculties through materii assignments
        $stmt = $pdo->prepare("
            SELECT DISTINCT f.id, f.name 
            FROM faculty f 
            JOIN subjects_faculties mf ON f.id = mf.faculty_id
            JOIN courses_subjects cm ON mf.materie_id = cm.materie_id
            WHERE cm.course_id = ?
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getCourseFaculties: " . $e->getMessage());
        return [];
    }
}

/**
 * Returns courses associated with a specific materie.
 */
function getCoursesByMaterie($materieId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                c.id,
                c.title,
                c.type,
                c.file_name,
                c.file_path,
                c.file_size,
                c.mime_type,
                c.downloads,
                c.uploaded_by,
                c.created_at,
                c.updated_at
            FROM courses c
            JOIN courses_subjects cm ON c.id = cm.course_id
            WHERE cm.materie_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$materieId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getCoursesByMaterie: " . $e->getMessage());
        return [];
    }
}

/**
 * Checks whether a user can access a given course based on role and faculty-materii assignments.
 */
function userCanAccessCourse($userId, $courseId) {
    try {
        $user = getUserById($userId);
        if (!$user) { return false; }
        if ($user['role'] === 'admin') { return true; }
        $facultyId = $user['faculty_id'] ?? null;
        if (empty($facultyId)) { return false; }
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT TOP 1 1
            FROM courses_subjects cm
            JOIN subjects_faculties mf ON cm.materie_id = mf.materie_id
            WHERE cm.course_id = ? AND mf.faculty_id = ?
        ");
        $stmt->execute([$courseId, $facultyId]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Database error in userCanAccessCourse: " . $e->getMessage());
        return false;
    }
}

function createCourse($courseData, $materiiIds) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Generate UUID for course
        $courseId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $now = date('Y-m-d H:i:s');
        
        // Insert course
        $stmt = $pdo->prepare("
            INSERT INTO courses (id, title, type, file_name, file_path, file_size, mime_type, uploaded_by, created_at, updated_at, downloads) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $courseId,
            $courseData['title'],
            $courseData['type'],
            $courseData['file_name'],
            $courseData['file_path'],
            $courseData['file_size'],
            $courseData['mime_type'],
            $courseData['uploaded_by'],
            $now,
            $now,
            0
        ]);
        
        // Insert materii associations
        $materiiStmt = $pdo->prepare("INSERT INTO courses_subjects (course_id, materie_id, assigned_at) VALUES (?, ?, ?)");
        foreach ($materiiIds as $materieId) {
            if (!empty($materieId)) {
                $materiiStmt->execute([$courseId, $materieId, $now]);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Cursul a fost creat cu succes', 'course_id' => $courseId];
    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("Database error in createCourse: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

/**
 * Increments the downloads counter for a course by 1.
 */
function incrementCourseDownloads($courseId) {
    try {
        $pdo = getDBConnection();
        // Ensure null-safe increment and keep updated_at in sync
        $stmt = $pdo->prepare("UPDATE courses SET downloads = ISNULL(downloads, 0) + 1, updated_at = GETDATE() WHERE id = ?");
        $stmt->execute([$courseId]);
    } catch (PDOException $e) {
        // Do not interrupt the file response flow; just log the error
        error_log("Database error in incrementCourseDownloads: " . $e->getMessage());
    }
}

function updateCourse($courseId, $courseData, $materiiIds) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        $now = date('Y-m-d H:i:s');
        
        // Update course data
        $stmt = $pdo->prepare("
            UPDATE courses 
            SET title = ?, type = ?, updated_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $courseData['title'],
            $courseData['type'],
            $now,
            $courseId
        ]);
        
        // Delete existing materii associations
        $deleteStmt = $pdo->prepare("DELETE FROM courses_subjects WHERE course_id = ?");
        $deleteStmt->execute([$courseId]);
        
        // Insert new materii associations
        $materiiStmt = $pdo->prepare("INSERT INTO courses_subjects (course_id, materie_id, assigned_at) VALUES (?, ?, ?)");
        foreach ($materiiIds as $materieId) {
            if (!empty($materieId)) {
                $materiiStmt->execute([$courseId, $materieId, $now]);
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Cursul a fost actualizat cu succes'];
    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("Database error in updateCourse: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

function deleteCourse($courseId) {
    try {
        $pdo = getDBConnection();
        
        // Get file path before deletion for cleanup
        $course = getCourseById($courseId);
        if (!$course) {
            return ['success' => false, 'message' => 'Cursul nu a fost găsit'];
        }
        
        // Delete course (CASCADE will handle course_faculties)
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $success = $stmt->execute([$courseId]);
        
        if ($success && $stmt->rowCount() > 0) {
            // Delete physical file
            if (!empty($course['file_path']) && file_exists($course['file_path'])) {
                unlink($course['file_path']);
            }
            return ['success' => true, 'message' => 'Cursul a fost șters cu succes'];
        } else {
            return ['success' => false, 'message' => 'Cursul nu a fost găsit sau nu a putut fi șters'];
        }
    } catch (PDOException $e) {
        error_log("Database error in deleteCourse: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

// ==================== SEMESTER FUNCTIONS ====================

function semestersHasIsActiveColumn(): bool {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT 1 FROM sys.columns WHERE name = 'is_active' AND object_id = OBJECT_ID('dbo.semesters')");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Column check failed (is_active): " . $e->getMessage());
        return false;
    }
}

function getAllSemesters() {
    try {
        $pdo = getDBConnection();
        $has = semestersHasIsActiveColumn();
        $sql = $has
            ? "SELECT id, academic_year, semester_number, start_date, end_date, is_active, created_at, updated_at FROM semesters ORDER BY academic_year DESC, semester_number ASC"
            : "SELECT id, academic_year, semester_number, start_date, end_date, created_at, updated_at FROM semesters ORDER BY academic_year DESC, semester_number ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!$has) {
            // Add computed is_active for compatibility
            $today = date('Y-m-d');
            foreach ($rows as &$r) {
                $r['is_active'] = ($today >= $r['start_date'] && $today <= $r['end_date']) ? 1 : 0;
            }
        }
        return $rows;
    } catch (PDOException $e) {
        error_log("Database error in getAllSemesters: " . $e->getMessage());
        return [];
    }
}

function getActiveSemester() {
    try {
        $pdo = getDBConnection();
        if (semestersHasIsActiveColumn()) {
            $stmt = $pdo->prepare("SELECT TOP 1 * FROM semesters WHERE is_active = 1 ORDER BY start_date DESC");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row) { return $row; }
        }
        // Fallback: compute by date
        $stmt = $pdo->prepare("SELECT TOP 1 * FROM semesters WHERE GETDATE() BETWEEN start_date AND end_date ORDER BY start_date DESC");
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in getActiveSemester: " . $e->getMessage());
        return null;
    }
}

function createSemester($semesterData) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Generate UUID for semester
        $semesterId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $now = date('Y-m-d H:i:s');
        
        // Check for duplicate semester
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM semesters WHERE academic_year = ? AND semester_number = ?");
        $checkStmt->execute([$semesterData['academic_year'], $semesterData['semester_number']]);
        if ($checkStmt->fetch()['count'] > 0) {
            return ['success' => false, 'message' => 'Semestrul există deja pentru acest an academic.'];
        }
        
        // Determine if this semester should be active based on current date
        $currentDate = date('Y-m-d');
        $isActive = ($currentDate >= $semesterData['start_date'] && $currentDate <= $semesterData['end_date']) ? 1 : 0;
        
        if (semestersHasIsActiveColumn()) {
            // If this semester should be active, deactivate others
            if ($isActive) {
                $deactivateStmt = $pdo->prepare("UPDATE semesters SET is_active = 0");
                $deactivateStmt->execute();
            }
            // Insert with is_active
            $stmt = $pdo->prepare("INSERT INTO semesters (id, academic_year, semester_number, start_date, end_date, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $semesterId,
                $semesterData['academic_year'],
                $semesterData['semester_number'],
                $semesterData['start_date'],
                $semesterData['end_date'],
                $isActive,
                $now,
                $now
            ]);
        } else {
            // Insert without is_active column
            $stmt = $pdo->prepare("INSERT INTO semesters (id, academic_year, semester_number, start_date, end_date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $semesterId,
                $semesterData['academic_year'],
                $semesterData['semester_number'],
                $semesterData['start_date'],
                $semesterData['end_date'],
                $now,
                $now
            ]);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Semestrul a fost creat cu succes', 'semester_id' => $semesterId];
    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("Database error in createSemester: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

function updateSemester($semesterId, $semesterData) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        $now = date('Y-m-d H:i:s');

        // Determine if this semester should be active based on current date
        $currentDate = date('Y-m-d');
        $isActive = ($currentDate >= $semesterData['start_date'] && $currentDate <= $semesterData['end_date']) ? 1 : 0;
        if ($isActive && semestersHasIsActiveColumn()) {
            // Deactivate all others only when column exists
            $deactivateStmt = $pdo->prepare("UPDATE semesters SET is_active = 0 WHERE id != ?");
            $deactivateStmt->execute([$semesterId]);
        }
        
        // Update semester data
        if (semestersHasIsActiveColumn()) {
            $stmt = $pdo->prepare("UPDATE semesters SET academic_year = ?, semester_number = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([
                $semesterData['academic_year'],
                $semesterData['semester_number'],
                $semesterData['start_date'],
                $semesterData['end_date'],
                $isActive,
                $now,
                $semesterId
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE semesters SET academic_year = ?, semester_number = ?, start_date = ?, end_date = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([
                $semesterData['academic_year'],
                $semesterData['semester_number'],
                $semesterData['start_date'],
                $semesterData['end_date'],
                $now,
                $semesterId
            ]);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Semestrul a fost actualizat cu succes'];
    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("Database error in updateSemester: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

/**
 * Ensures the correct semester is marked active based on current date.
 */
function syncActiveSemesterByDate(): void {
    try {
        $pdo = getDBConnection();
        $today = date('Y-m-d');
        $pdo->beginTransaction();
        // If column doesn't exist, nothing to sync in DB
        if (!semestersHasIsActiveColumn()) {
            $pdo->commit();
            return;
        }
        // Find current semester by date
        $stmt = $pdo->prepare("SELECT TOP 1 id FROM semesters WHERE ? BETWEEN start_date AND end_date ORDER BY start_date DESC");
        $stmt->execute([$today]);
        $row = $stmt->fetch();
        // Deactivate all
        $pdo->prepare("UPDATE semesters SET is_active = 0")->execute();
        if ($row && isset($row['id'])) {
            // Activate the one that matches
            $pdo->prepare("UPDATE semesters SET is_active = 1 WHERE id = ?")->execute([$row['id']]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        // Best-effort; log and continue
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in syncActiveSemesterByDate: " . $e->getMessage());
    }
}

function deleteSemester($semesterId) {
    try {
        $pdo = getDBConnection();
        
        // Delete semester
        $stmt = $pdo->prepare("DELETE FROM semesters WHERE id = ?");
        $success = $stmt->execute([$semesterId]);
        
        if ($success && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Semestrul a fost șters cu succes'];
        } else {
            return ['success' => false, 'message' => 'Semestrul nu a fost găsit sau nu a putut fi șters'];
        }
    } catch (PDOException $e) {
        error_log("Database error in deleteSemester: " . $e->getMessage());
        return ['success' => false, 'message' => 'Eroare la baza de date'];
    }
}

// ==================== USER FUNCTIONS ====================

function createUser($userData) {
    try {
        $pdo = getDBConnection();
        
        // Validate required fields for simplified table
        $required = ['username', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Check if username already exists
        if (getUserByUsername($userData['username'])) {
            throw new InvalidArgumentException("Username already exists");
        }
        
        // Enforce faculty_id based on role
        if ($userData['role'] === 'student') {
            if (empty($userData['faculty_id'])) {
                throw new InvalidArgumentException("Faculty is required for student role");
            }
        } else if ($userData['role'] === 'admin') {
            // Admin must not have a faculty_id
            $userData['faculty_id'] = null;
        }

        // Hash password
        $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (id, username, password_hash, role, faculty_id, created_at)
            VALUES (NEWID(), ?, ?, ?, ?, GETDATE())
        ");
        
        $success = $stmt->execute([
            $userData['username'],
            $passwordHash,
            $userData['role'],
            $userData['faculty_id'] ?? null
        ]);
        
        if ($success) {
            return ['success' => true, 'message' => 'User created successfully'];
        } else {
            throw new Exception("Failed to create user");
        }
        
    } catch (InvalidArgumentException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    } catch (PDOException $e) {
        error_log("Database error in createUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    } catch (Exception $e) {
        error_log("Error in createUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while creating user'];
    }
}

function getUserByEmail($email) {
    // Since email field doesn't exist in simplified table, return false
    return false;
}

function updateUser($userId, $userData) {
    try {
        $pdo = getDBConnection();
        $currentUser = getUserById($userId);
        if (!$currentUser) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Determine the effective role after update (either provided or current)
        $effectiveRole = isset($userData['role']) && $userData['role'] !== null
            ? $userData['role']
            : $currentUser['role'];

        // Enforce faculty_id invariants based on effective role
        if ($effectiveRole === 'admin') {
            // Admins must have faculty_id = NULL
            $userData['faculty_id'] = null;
        } elseif ($effectiveRole === 'student') {
            // Students must have a faculty_id provided (non-empty)
            if (!array_key_exists('faculty_id', $userData)) {
                // If not explicitly provided in this update, keep current; if still null/empty, block
                if (empty($currentUser['faculty_id'])) {
                    return ['success' => false, 'message' => 'Faculty is required for student role'];
                }
            } else {
                if (empty($userData['faculty_id'])) {
                    return ['success' => false, 'message' => 'Faculty is required for student role'];
                }
            }
        }
        
        // Build dynamic update query for simplified table
        $allowedFields = ['username', 'role', 'faculty_id'];
        $updateFields = [];
        $params = [];
        
        foreach ($userData as $field => $value) {
            if ($field === 'password' && !empty($value)) {
                // Handle password update with hashing
                $updateFields[] = "password_hash = ?";
                $params[] = password_hash($value, PASSWORD_DEFAULT);
            } elseif ($field === 'faculty_id' && array_key_exists('faculty_id', $userData)) {
                // Allow explicit NULL for faculty_id
                $updateFields[] = "faculty_id = ?";
                $params[] = $value; // can be null
            } elseif (in_array($field, $allowedFields) && $value !== null) {
                $updateFields[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updateFields)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }
        
        // Add userId to params
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            return ['success' => true, 'message' => 'User updated successfully'];
        } else {
            throw new Exception("Failed to update user");
        }
        
    } catch (PDOException $e) {
        error_log("Database error in updateUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    } catch (Exception $e) {
        error_log("Error in updateUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while updating user'];
    }
}

function deleteUser($userId) {
    try {
        $pdo = getDBConnection();
        
        // Hard delete while protecting super admin accounts
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_super_admin = 0");
        $success = $stmt->execute([$userId]);
        
        if ($success && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'User deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'User not found or deletion not permitted'];
        }
        
    } catch (PDOException $e) {
        error_log("Database error in deleteUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

function validateUserLogin($username, $password) {
    try {
        $user = getUserByUsername($username);
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            updateLastLogin($user['id']);
            return $user;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error in validateUserLogin: " . $e->getMessage());
        return false;
    }
}

function updateLastLogin($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET last_login = GETDATE() WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Database error in updateLastLogin: " . $e->getMessage());
    }
}

// Test database connection
function testDatabaseConnection() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
} 
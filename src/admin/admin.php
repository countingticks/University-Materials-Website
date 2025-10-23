<?php
// Config is already loaded by the router
// requireLogin() and requireAdmin() are already called by the router

// Handle GET requests for course data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_course_faculties':
            $courseId = $_GET['course_id'] ?? '';
            if (empty($courseId)) {
                echo json_encode(['success' => false, 'message' => 'Course ID required']);
                exit();
            }
            
            $faculties = getCourseFaculties($courseId);
            echo json_encode(['success' => true, 'faculties' => $faculties]);
            exit();
        case 'get_course_materii':
            $courseId = $_GET['course_id'] ?? '';
            if (empty($courseId)) {
                echo json_encode(['success' => false, 'message' => 'Course ID required']);
                exit();
            }
            $materiiForCourse = getCourseMaterii($courseId);
            echo json_encode(['success' => true, 'materii' => $materiiForCourse]);
            exit();

        case 'get_all_courses':
            // Load all courses grouped by type and expose required fields for client rendering
            $allCourses = getAllCourses();
            $cursuri = [];
            $laboratoare = [];
            foreach ($allCourses as $c) {
                $item = [
                    'id' => $c['id'],
                    'title' => $c['title'],
                    'type' => $c['type'],
                    'materii_names' => $c['materii_names'] ?? '',
                    'file_name' => $c['file_name'] ?? '',
                    'file_size' => (int)($c['file_size'] ?? 0),
                    'created_at' => $c['created_at'] ?? ''
                ];
                if (isset($c['type']) && $c['type'] === 'laborator') {
                    $laboratoare[] = $item;
                } else {
                    $cursuri[] = $item;
                }
            }
            echo json_encode(['success' => true, 'cursuri' => $cursuri, 'laboratoare' => $laboratoare]);
            exit();

        case 'get_all_users':
            $faculties = getAllFaculties();
            $facById = [];
            foreach ($faculties as $f) { $facById[$f['id']] = $f['name']; }
            $users = getAllUsers();
            $out = array_map(function($u) use ($facById) {
                return [
                    'id' => $u['id'],
                    'username' => $u['username'],
                    'role' => $u['role'],
                    'faculty_id' => $u['faculty_id'] ?? null,
                    'faculty_name' => isset($u['faculty_id']) && isset($facById[$u['faculty_id']]) ? $facById[$u['faculty_id']] : '-',
                    'is_super_admin' => !empty($u['is_super_admin']) ? 1 : 0,
                    'created_at' => $u['created_at'] ?? ''
                ];
            }, $users);
            echo json_encode(['success' => true, 'users' => $out]);
            exit();

        case 'get_all_faculties':
            $faculties = getAllFaculties();
            echo json_encode(['success' => true, 'faculties' => $faculties]);
            exit();

        case 'get_all_materii':
            $materii = getAllMaterii();
            // Ensure faculty_names string exists for each materie
            $materii = array_map(function($m) {
                $m['faculty_names'] = $m['faculty_names'] ?? '';
                return $m;
            }, $materii);
            echo json_encode(['success' => true, 'materii' => $materii]);
            exit();

        case 'get_materie_faculties':
            $materieId = $_GET['materie_id'] ?? '';
            if (empty($materieId)) {
                echo json_encode(['success' => false, 'message' => 'Materie ID required']);
                exit();
            }
            // Build faculties assigned to this materie
            $allFaculties = getAllFaculties();
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT faculty_id FROM subjects_faculties WHERE materie_id = ?");
            $stmt->execute([$materieId]);
            $facIds = array_map(function($r){ return $r['faculty_id']; }, $stmt->fetchAll());
            $facultiesForMaterie = array_values(array_filter($allFaculties, function($f) use ($facIds) { return in_array($f['id'], $facIds); }));
            echo json_encode(['success' => true, 'faculties' => $facultiesForMaterie]);
            exit();
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit();
    }
}

// Handle AJAX requests for user management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_user':
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';
            
            // Validate password confirmation
            if ($password !== $passwordConfirm) {
                echo json_encode(['success' => false, 'message' => 'Parolele nu se potrivesc.']);
                exit();
            }
            
            $userData = [
                'username' => $_POST['username'] ?? '',
                'password' => $password,
                'role' => $_POST['role'] ?? 'student',
                // Accept faculty_id for students; admins will be coerced to NULL server-side
                'faculty_id' => isset($_POST['faculty_id']) && $_POST['faculty_id'] !== '' ? $_POST['faculty_id'] : null
            ];
            
            $result = createUser($userData);
            echo json_encode($result);
            exit();
            
        case 'edit_user':
            $userId = $_POST['user_id'] ?? '';
            $currentUserId = $_SESSION['user_id'] ?? '';
            $userToEdit = getUserById($userId);
            $currentUser = $currentUserId ? getUserById($currentUserId) : null;
            
            // Prepare user data for update
            $userData = [];
            
            // Handle username changes - allow changing username with admin protection rules
            if (isset($_POST['username']) && !empty($_POST['username'])) {
                $newUsername = $_POST['username'];
                $targetIsAdmin = $userToEdit && isset($userToEdit['role']) && $userToEdit['role'] === 'admin';
                $targetIsProtected = $userToEdit && !empty($userToEdit['is_super_admin']);
                $isSelfEdit = ($userId === $currentUserId);
                $editorIsPrivileged = $currentUser && !empty($currentUser['is_super_admin']);

                if ($targetIsAdmin && !$isSelfEdit) {
                    // Editing another admin's username requires privileged editor and target must be unprotected
                    if (!$editorIsPrivileged || $targetIsProtected) {
                        echo json_encode(['success' => false, 'message' => 'Nu puteți modifica numele acestui cont de administrator.']);
                        exit();
                    }
                }

                $userData['username'] = $newUsername;
            }
            
            // Handle role changes - restrict changing roles for admin accounts
            if (isset($_POST['role'])) {
                $newRole = $_POST['role'];
                $targetIsAdmin = $userToEdit && isset($userToEdit['role']) && $userToEdit['role'] === 'admin';
                $targetIsProtected = $userToEdit && !empty($userToEdit['is_super_admin']);
                $isSelfEdit = ($userId === $currentUserId);
                $editorIsPrivileged = $currentUser && !empty($currentUser['is_super_admin']);

                // Always block changing your own role for safety
                if ($isSelfEdit) {
                    echo json_encode(['success' => false, 'message' => 'Nu vă puteți modifica propriul rol.']);
                    exit();
                }

                if ($targetIsAdmin) {
                    // Changing another admin's role requires privileged editor and target must be unprotected
                    if (!$editorIsPrivileged || $targetIsProtected) {
                        echo json_encode(['success' => false, 'message' => 'Rolul acestui cont de administrator nu poate fi modificat.']);
                        exit();
                    }
                }

                $userData['role'] = $newRole;
                // If switching to admin, ensure faculty_id will be cleared
                if ($newRole === 'admin') {
                    $userData['faculty_id'] = null;
                }
            }
            


            // Handle faculty changes explicitly when provided
            if (array_key_exists('faculty_id', $_POST)) {
                $facultyId = $_POST['faculty_id'] !== '' ? $_POST['faculty_id'] : null;
                $userData['faculty_id'] = $facultyId;
            }
            
            // Handle password changes
            if (!empty($_POST['password'])) {
                $password = $_POST['password'];
                $passwordConfirm = $_POST['password_confirm'] ?? '';
                
                // Security check: Prevent password changes for super admin accounts by others (but allow self-changes)
                if ($userToEdit && $userToEdit['role'] === 'admin' && !empty($userToEdit['is_super_admin']) && $userId !== $currentUserId) {
                    echo json_encode(['success' => false, 'message' => 'Parola acestui cont protejat nu poate fi modificată de alți utilizatori.']);
                    exit();
                }
                
                // Validate password confirmation
                if ($password !== $passwordConfirm) {
                    echo json_encode(['success' => false, 'message' => 'Parolele nu se potrivesc.']);
                    exit();
                }
                
                $userData['password'] = $password;
            }
            
            if ($userId && !empty($userData)) {
                $result = updateUser($userId, $userData);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
            }
            exit();
            
        case 'delete_user':
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                // Security checks: Prevent deleting yourself and protect the special 'admin' account
                $currentUserId = $_SESSION['user_id'] ?? '';
                $userToDelete = getUserById($userId);
                
                // Block self-deletion regardless of role
                if ($userId === $currentUserId) {
                    echo json_encode(['success' => false, 'message' => 'Nu vă puteți șterge propriul cont.']);
                    exit();
                }

                // Block deletion of super admin accounts
                if ($userToDelete && !empty($userToDelete['is_super_admin'])) {
                    echo json_encode(['success' => false, 'message' => 'Acest cont este protejat și nu poate fi șters.']);
                    exit();
                }
                
                $result = deleteUser($userId);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
            }
            exit();

        case 'add_faculty':
            $name = trim($_POST['name'] ?? '');
            $result = createFaculty($name);
            echo json_encode($result);
            exit();

        case 'edit_faculty':
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID facultate lipsă']);
                exit();
            }
            $result = updateFaculty($id, $name);
            echo json_encode($result);
            exit();

        case 'delete_faculty':
            $id = $_POST['id'] ?? '';
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID facultate lipsă']);
                exit();
            }
            $result = deleteFaculty($id);
            echo json_encode($result);
            exit();
            
        case 'add_course':
            // Validate title
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Titlul cursului este obligatoriu.']);
                exit();
            }
            
            // Handle file upload
            if (!isset($_FILES['course_file']) || $_FILES['course_file']['error'] !== UPLOAD_ERR_OK) {
                // Provide helpful messages for common errors
                $err = $_FILES['course_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                $uploadMax = ini_get('upload_max_filesize');
                $postMax = ini_get('post_max_size');
                $messages = [
                    UPLOAD_ERR_INI_SIZE => "Fișierul depășește limita serverului (upload_max_filesize: {$uploadMax}).",
                    UPLOAD_ERR_FORM_SIZE => "Fișierul depășește limita formularului (post_max_size: {$postMax}).",
                    UPLOAD_ERR_PARTIAL => 'Fișierul a fost încărcat doar parțial. Încercați din nou.',
                    UPLOAD_ERR_NO_FILE => 'Nu a fost selectat niciun fișier.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Lipsește directorul temporar pe server.',
                    UPLOAD_ERR_CANT_WRITE => 'Fișierul nu a putut fi salvat pe disc.',
                    UPLOAD_ERR_EXTENSION => 'Încărcarea a fost oprită de o extensie PHP.'
                ];
                $message = $messages[$err] ?? 'Eroare la încărcarea fișierului.';
                echo json_encode(['success' => false, 'message' => $message]);
                exit();
            }
            
            $file = $_FILES['course_file'];
            
            // Validate file type
            if ($file['type'] !== 'application/pdf') {
                echo json_encode(['success' => false, 'message' => 'Doar fișierele PDF sunt permise.']);
                exit();
            }
            
            // Validate file size
            if ($file['size'] > MAX_FILE_SIZE) {
                $maxSizeMB = MAX_FILE_SIZE / 1024 / 1024;
                echo json_encode(['success' => false, 'message' => "Fișierul este prea mare. Mărimea maximă este {$maxSizeMB}MB."]);
                exit();
            }
            
            // Create unique filename
            $fileName = pathinfo($file['name'], PATHINFO_FILENAME);
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $uniqueFileName = $fileName . '_' . uniqid() . '.' . $extension;
            $uploadPath = 'uploads/courses/' . $uniqueFileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                echo json_encode(['success' => false, 'message' => 'Eroare la salvarea fișierului.']);
                exit();
            }
            
            // Prepare course data
            $courseData = [
                'title' => $_POST['title'] ?? '',
                'type' => $_POST['type'] ?? 'curs teoretic',
                'file_name' => $file['name'],
                'file_path' => $uploadPath,
                'file_size' => $file['size'],
                'mime_type' => $file['type'],
                'uploaded_by' => $_SESSION['user_id'] ?? ''
            ];
            
            // Get materii IDs
            $materiiIds = isset($_POST['materii_ids']) ? $_POST['materii_ids'] : [];
            if (!is_array($materiiIds)) {
                $materiiIds = [$materiiIds];
            }
            
            $result = createCourse($courseData, $materiiIds);
            echo json_encode($result);
            exit();
            
        case 'edit_course':
            $courseId = $_POST['course_id'] ?? '';
            
            // Validate title
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Titlul cursului este obligatoriu.']);
                exit();
            }
            
            $courseData = [
                'title' => $title,
                'type' => $_POST['type'] ?? 'curs teoretic'
            ];
            
            // Get materii IDs
            $materiiIds = isset($_POST['materii_ids']) ? $_POST['materii_ids'] : [];
            if (!is_array($materiiIds)) {
                $materiiIds = [$materiiIds];
            }
            
            $result = updateCourse($courseId, $courseData, $materiiIds);
            echo json_encode($result);
            exit();
            
        case 'delete_course':
            $courseId = $_POST['course_id'] ?? '';
            $result = deleteCourse($courseId);
            echo json_encode($result);
            exit();
            
        // Materii management
        case 'add_materie':
            $materieData = [
                'name' => $_POST['name'] ?? '',
                'year' => intval($_POST['year'] ?? 1),
                'semester' => intval($_POST['semester'] ?? 1),
                'credits' => intval($_POST['credits'] ?? 6)
            ];
            
            $facultyIds = isset($_POST['faculty_ids']) ? $_POST['faculty_ids'] : [];
            if (!is_array($facultyIds)) {
                $facultyIds = [$facultyIds];
            }
            
            $result = createMaterie($materieData, $facultyIds);
            echo json_encode($result);
            exit();
            
        case 'edit_materie':
            $materieId = $_POST['materie_id'] ?? '';
            $materieData = [
                'name' => $_POST['name'] ?? '',
                'year' => intval($_POST['year'] ?? 1),
                'semester' => intval($_POST['semester'] ?? 1),
                'credits' => intval($_POST['credits'] ?? 6)
            ];
            
            $facultyIds = isset($_POST['faculty_ids']) ? $_POST['faculty_ids'] : [];
            if (!is_array($facultyIds)) {
                $facultyIds = [$facultyIds];
            }
            
            $result = updateMaterie($materieId, $materieData, $facultyIds);
            echo json_encode($result);
            exit();
            
        case 'delete_materie':
            $materieId = $_POST['materie_id'] ?? '';
            $result = deleteMaterie($materieId);
            echo json_encode($result);
            exit();
            
        // Semester management
        case 'add_semester':
            $semesterData = [
                'academic_year' => $_POST['academic_year'] ?? '',
                'semester_number' => intval($_POST['semester_number'] ?? 1),
                'start_date' => $_POST['start_date'] ?? '',
                'end_date' => $_POST['end_date'] ?? ''
            ];
            
            $result = createSemester($semesterData);
            echo json_encode($result);
            exit();
            
        case 'edit_semester':
            $semesterId = $_POST['semester_id'] ?? '';
            $semesterData = [
                'academic_year' => $_POST['academic_year'] ?? '',
                'semester_number' => intval($_POST['semester_number'] ?? 1),
                'start_date' => $_POST['start_date'] ?? '',
                'end_date' => $_POST['end_date'] ?? ''
            ];
            
            $result = updateSemester($semesterId, $semesterData);
            echo json_encode($result);
            exit();
            
        case 'delete_semester':
            $semesterId = $_POST['semester_id'] ?? '';
            $result = deleteSemester($semesterId);
            echo json_encode($result);
            exit();
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
}

// Load data for the admin panel
$faculties = getAllFaculties();
$courses = getAllCourses();
$materii = getAllMaterii();
$semesters = getAllSemesters();

// Pagination settings
$PAGE_SIZE = 8;

// Users pagination
$totalUsers = count($users = getAllUsers());
$usersPages = max(1, (int)ceil($totalUsers / $PAGE_SIZE));
$usersPage = max(1, min($usersPages, (int)($_GET['users_page'] ?? 1)));
$usersPaged = array_slice($users, ($usersPage - 1) * $PAGE_SIZE, $PAGE_SIZE);

// Faculties pagination
$totalFaculties = count($faculties);
$facultiesPages = max(1, (int)ceil($totalFaculties / $PAGE_SIZE));
$facultiesPage = max(1, min($facultiesPages, (int)($_GET['faculties_page'] ?? 1)));
$facultiesPaged = array_slice($faculties, ($facultiesPage - 1) * $PAGE_SIZE, $PAGE_SIZE);

// Materii pagination
$totalMaterii = count($materii);
$materiiPages = max(1, (int)ceil($totalMaterii / $PAGE_SIZE));
$materiiPage = max(1, min($materiiPages, (int)($_GET['materii_page'] ?? 1)));
$materiiPaged = array_slice($materii, ($materiiPage - 1) * $PAGE_SIZE, $PAGE_SIZE);

// Semesters pagination
$totalSemesters = count($semesters);
$semestersPages = max(1, (int)ceil($totalSemesters / $PAGE_SIZE));
$semestersPage = max(1, min($semestersPages, (int)($_GET['semesters_page'] ?? 1)));
$semestersPaged = array_slice($semesters, ($semestersPage - 1) * $PAGE_SIZE, $PAGE_SIZE);
$activeSemester = getActiveSemester();

// Courses pagination per subtab (server-side)
$coursesCursuri = array_values(array_filter($courses, function($course) {
    return isset($course['type']) && $course['type'] === 'curs teoretic';
}));
$totalCoursesCursuri = count($coursesCursuri);
$coursesCursuriPages = max(1, (int)ceil($totalCoursesCursuri / $PAGE_SIZE));
$coursesCursuriPage = max(1, min($coursesCursuriPages, (int)($_GET['cursuri_page'] ?? 1)));
$coursesCursuriPaged = array_slice($coursesCursuri, ($coursesCursuriPage - 1) * $PAGE_SIZE, $PAGE_SIZE);

$coursesLaboratoare = array_values(array_filter($courses, function($course) {
    return isset($course['type']) && $course['type'] === 'laborator';
}));
$totalCoursesLaboratoare = count($coursesLaboratoare);
$coursesLaboratoarePages = max(1, (int)ceil($totalCoursesLaboratoare / $PAGE_SIZE));
$coursesLaboratoarePage = max(1, min($coursesLaboratoarePages, (int)($_GET['laboratoare_page'] ?? 1)));
$coursesLaboratoarePaged = array_slice($coursesLaboratoare, ($coursesLaboratoarePage - 1) * $PAGE_SIZE, $PAGE_SIZE);

// Get real user data from database (already paginated variables created above)

// Calculate statistics using real data
$totalLectures = count($courses); // Real count of uploaded courses
$totalStudents = count($users); // Real data from database

// Calculate storage used from real course file sizes (in bytes)
$storageUsedBytes = 0;
foreach ($courses as $course) {
    $storageUsedBytes += $course['file_size'];
}
$storageUsed = $storageUsedBytes / 1024 / 1024; // Convert to MB

// For now, set downloads to 0 (can be implemented later with tracking)
// Sum downloads across all courses (null-safe)
$totalDownloads = 0;
foreach ($courses as $course) {
    $totalDownloads += isset($course['downloads']) ? (int)$course['downloads'] : 0;
}

// Recent activity mock data
$recentActivity = [
    ['action' => 'Curs nou adăugat', 'time' => '2 ore în urmă'],
    ['action' => 'Student descărcat materiale', 'time' => '4 ore în urmă'],
    ['action' => 'Laborator actualizat', 'time' => '1 zi în urmă'],
    ['action' => 'Backup realizat cu succes', 'time' => '2 zile în urmă']
];
?>

<?php include __DIR__ . '/../components/header.php'; ?>

<script>
// Add current user ID to body for JavaScript access
document.body.dataset.currentUserId = "<?php echo $_SESSION['user_id'] ?? ''; ?>";
document.body.dataset.currentUserIsDeleteProtected = "<?php
    $currentUserId = $_SESSION['user_id'] ?? '';
    $currentUser = $currentUserId ? getUserById($currentUserId) : null;
    echo ($currentUser && !empty($currentUser['is_super_admin'])) ? '1' : '0';
?>";
// Add max file size for JavaScript validation
window.MAX_FILE_SIZE = <?php echo MAX_FILE_SIZE; ?>;
window.MAX_FILE_SIZE_MB = <?php echo MAX_FILE_SIZE / 1024 / 1024; ?>;
// Expose server page size for client-side pagination to mirror per-page rows
window.PAGE_SIZE = <?php echo (int)$PAGE_SIZE; ?>;
</script>

<div class="admin-page">
    <div class="container">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="container">
                <h1>Panou Administrativ</h1>
                <p class="admin-subtitle">Gestionarea cursurilor și materialelor</p>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card lectures">
                <div class="stat-icon"></div>
                <div class="stat-value"><?php echo $totalLectures; ?></div>
                <div class="stat-label">Materiale Încărcate</div>
            </div>
            
            <div class="stat-card students">
                <div class="stat-icon"></div>
                <div class="stat-value"><?php echo $totalStudents; ?></div>
                <div class="stat-label">Conturi Înregistrate</div>
            </div>
            
            <div class="stat-card downloads">
                <div class="stat-icon"></div>
                <div class="stat-value"><?php echo $totalDownloads; ?></div>
                <div class="stat-label">Descărcări Totale</div>
            </div>
            
            <div class="stat-card storage">
                <div class="stat-icon"></div>
                <div class="stat-value"><?php echo number_format($storageUsed, 1); ?> MB</div>
                <div class="stat-label">Stocare Utilizată</div>
            </div>
        </div>

        <!-- Admin Tabs -->
        <div class="admin-tabs">
            <div class="tab-buttons">
                <button class="tab-btn" onclick="switchTab('users')">
                    <span>👥</span>
                    Utilizatori
                </button>
                <button class="tab-btn" onclick="switchTab('faculties')">
                    <span>🏫</span>
                    Facultăți
                </button>
                <button class="tab-btn" onclick="switchTab('materii')">
                    <span>📖</span>
                    Materii
                </button>
                <button class="tab-btn" onclick="switchTab('courses')">
                    <span>📚</span>
                    Cursuri
                </button>
                <button class="tab-btn" onclick="switchTab('general')">
                    <span>⚙️</span>
                    General
                </button>
            </div>
        </div>

        <!-- Main Admin Content -->
        <div class="admin-content">
            <!-- User Management Tab -->
            <div id="users-tab" class="tab-content">
                    <div class="users-management">
                    <div class="section-header">
                        <h2>Utilizatori</h2>
                        <div class="table-filters">
                            <select id="usersRoleFilter" onchange="filterUsers()">
                                <option value="">Toate rolurile</option>
                                <option value="admin">Administratori</option>
                                <option value="student">Studenți</option>
                            </select>
                            <select id="usersFacultyFilter" onchange="filterUsers()">
                                <option value="">Toate facultățile</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo htmlspecialchars($faculty['id']); ?>"><?php echo htmlspecialchars($faculty['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="usersSearch" placeholder="Căutare nume..." onkeyup="filterUsers()">
                            <button class="btn-secondary btn-reset" onclick="clearUsersFilters()" title="Resetează filtrele">
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                    Resetează
                                </span>
                            </button>
                        </div>
                        <button class="btn-add-user" onclick="openAddUserModal()">Adaugă Utilizator Nou</button>
                    </div>
                    
                    <div class="users-table-container">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Nume</th>
                                    <th>Rol</th>
                                    <th>Facultate</th>
                                    <th>Data Înregistrării</th>
                                    <th>Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersPaged as $user): ?>
                                    <tr>
                                        <td class="user-name-cell" data-label="Nume">
                                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                                        </td>
                                         <td data-label="Rol">
                                            <span class="user-role <?php echo $user['role']; ?>">
                                                <?php 
                                                $roleLabels = ['admin' => 'Administrator', 'student' => 'Student'];
                                                echo $roleLabels[$user['role']] ?? $user['role']; 
                                                ?>
                                            </span>
                                        </td>
                                         <td data-label="Facultate"><?php 
                                            if ($user['role'] === 'admin') {
                                                echo '-';
                                            } else {
                                                // Find faculty name by ID
                                                $facultyName = '-';
                                                foreach ($faculties as $f) {
                                                    if ($f['id'] === $user['faculty_id']) {
                                                        $facultyName = htmlspecialchars($f['name']);
                                                        break;
                                                    }
                                                }
                                                echo $facultyName;
                                            }
                                        ?></td>
                                         <td data-label="Data Înregistrării"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                         <td data-label="Acțiuni">
                                            <div class="user-actions">
                                                <button class="btn-action btn-edit" onclick="editUser('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['faculty_id'] ?? ''); ?>', '<?php echo !empty($user['is_super_admin']) ? '1' : '0'; ?>')">
                                                    Editează
                                                </button>
                                                <?php 
                                                $currentUserId = $_SESSION['user_id'] ?? '';
                                                $isSelf = $user['id'] === $currentUserId;
                                                $isSuperAdmin = !empty($user['is_super_admin']);
                                                if (!$isSelf && !$isSuperAdmin): ?>
                                                <button class="btn-action btn-delete" onclick="deleteUser('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    Șterge
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($usersPages > 1): ?>
                        <div class="table-pager" style="display:flex;gap:8px;align-items:center;justify-content:center;padding:10px;border-top:1px solid #eee;flex-wrap:wrap;">
                            <?php $prev = max(1, $usersPage - 1); $next = min($usersPages, $usersPage + 1); ?>
                            <a class="btn-secondary" href="?users_page=<?php echo $prev; ?>#users-tab" style="font-size:0.85rem;<?php echo $usersPage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                            <?php
                                $__total = $usersPages;
                                $__current = (int)$usersPage;
                                $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                sort($__pages);
                                $__last = 0;
                                foreach ($__pages as $__p) {
                                    if ($__last && $__p > $__last + 1) {
                                        echo '<span class="pager-ellipsis" style="color:#6b7280;">…</span>';
                                    }
                                    $isCurrent = ($__p === $__current);
                                    echo '<a class="btn-secondary" ' . ($isCurrent ? 'aria-current="page"' : '') . ' href="?users_page=' . $__p . '#users-tab" style="font-size:0.85rem;">' . $__p . '</a>';
                                    $__last = $__p;
                                }
                            ?>
                            <a class="btn-secondary" href="?users_page=<?php echo $next; ?>#users-tab" style="font-size:0.85rem;<?php echo $usersPage==$usersPages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Course Management Tab -->
            <div id="courses-tab" class="tab-content">
                <div class="lecture-management">
                                    <div class="section-header">
                    <div class="course-subtabs">
                        <button class="course-subtab-btn active" onclick="switchCourseSubtab('cursuri')" data-tab="cursuri">
                            Cursuri
                        </button>
                        <button class="course-subtab-btn" onclick="switchCourseSubtab('laboratoare')" data-tab="laboratoare">
                            Laboratoare
                        </button>
                    </div>
                    <div class="course-filters table-filters">
                        <?php 
                        // Build unique materii options with merged faculty lists
                        $materieOptionsMap = [];
                        foreach ($materii as $m) {
                            $name = $m['name'];
                            $facNames = isset($m['faculty_names']) ? $m['faculty_names'] : '';
                            if (!isset($materieOptionsMap[$name])) {
                                $materieOptionsMap[$name] = $facNames;
                            } else {
                                $existing = $materieOptionsMap[$name];
                                $combined = array_filter(array_unique(array_merge(
                                    array_map('trim', explode(',', (string)$existing)),
                                    array_map('trim', explode(',', (string)$facNames))
                                )));
                                $materieOptionsMap[$name] = implode(', ', $combined);
                            }
                        }
                        ?>
                        <select id="courseScopeFilter" onchange="filterCourses()">
                            <option value="">Toate materiile</option>
                            <?php foreach ($materieOptionsMap as $matName => $facNames): ?>
                                <option value="materie::<?php echo htmlspecialchars($matName); ?>" data-faculties="<?php echo htmlspecialchars($facNames); ?>">
                                    <?php echo htmlspecialchars($matName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="courseSearch" placeholder="Căutare după titlu..." onkeyup="filterCourses()" size="16">
                        <button class="btn-secondary btn-reset" onclick="clearFilters()" title="Resetează filtrele">
                            <span style="display:inline-flex;align-items:center;gap:6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                Resetează
                            </span>
                        </button>
                    </div>
                    <button class="btn-add-lecture" onclick="openAddCourseModal()">
                        Adăugare Curs Nou
                    </button>
            </div>
                
                    <!-- Cursuri Subtab Content -->
                    <div class="course-subtab-content active" id="cursuri-courses-content">
                        <div class="lecture-table-container">
                            <table class="lecture-table">
                                <thead>
                                    <tr>
                                        <th>Titlu Curs</th>
                                        <th>Tip</th>
                                        <th>Materii</th>
                                        <th>Fișier</th>
                                        <th>Data Încărcării</th>
                                        <th>Acțiuni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coursesCursuriPaged as $course): ?>
                                        <tr>
                                         <td class="lecture-title-cell" data-label="Titlu Curs">
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Tip">
                                                <?php echo htmlspecialchars($course['type']); ?>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Materii">
                                                <?php echo htmlspecialchars($course['materii_names'] ?: '-'); ?>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Fișier">
                                                <span title="<?php echo htmlspecialchars($course['file_name']); ?>">
                                                    <?php echo htmlspecialchars($course['file_name']); ?>
                                                </span>
                                                <br>
                                                <small><?php echo number_format($course['file_size'] / 1024 / 1024, 2); ?> MB</small>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Data Încărcării">
                                                <?php echo date('d.m.Y H:i', strtotime($course['created_at'])); ?>
                                            </td>
                                             <td data-label="Acțiuni">
                                                <div class="lecture-actions">
                                                    <button class="btn-action btn-view" onclick="viewCourse('<?php echo $course['id']; ?>')">
                                                        Vezi
                                                    </button>
                                                    <button class="btn-action btn-download" onclick="downloadCourse('<?php echo $course['id']; ?>')">
                                                        Descarcă
                                                    </button>
                                                    <button class="btn-action btn-edit" onclick="editCourse('<?php echo $course['id']; ?>', '<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>', '<?php echo $course['type']; ?>')">
                                                        Editează
                                                    </button>
                                                    <button class="btn-action btn-delete" onclick="deleteCourse('<?php echo $course['id']; ?>', '<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>')">
                                                        Șterge
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($coursesCursuriPages > 1): ?>
                            <div class="table-pager" style="display:flex;gap:8px;align-items:center;justify-content:center;padding:10px;border-top:1px solid #eee;flex-wrap:wrap;">
                                <?php $prev = max(1, $coursesCursuriPage - 1); $next = min($coursesCursuriPages, $coursesCursuriPage + 1); ?>
                                <a class="btn-secondary" href="?cursuri_page=<?php echo $prev; ?>#courses-tab" style="font-size:0.85rem;<?php echo $coursesCursuriPage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                                <?php
                                    $__total = $coursesCursuriPages;
                                    $__current = (int)$coursesCursuriPage;
                                    $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                    $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                    sort($__pages);
                                    $__last = 0;
                                    foreach ($__pages as $__p) {
                                        if ($__last && $__p > $__last + 1) {
                                            echo '<span class="pager-ellipsis" style="color:#6b7280;">…</span>';
                                        }
                                        $isCurrent = ($__p === $__current);
                                        echo '<a class="btn-secondary" ' . ($isCurrent ? 'aria-current="page"' : '') . ' href="?cursuri_page=' . $__p . '#courses-tab" style="font-size:0.85rem;">' . $__p . '</a>';
                                        $__last = $__p;
                                    }
                                ?>
                                <a class="btn-secondary" href="?cursuri_page=<?php echo $next; ?>#courses-tab" style="font-size:0.85rem;<?php echo $coursesCursuriPage==$coursesCursuriPages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Laboratoare Subtab Content -->
                    <div class="course-subtab-content" id="laboratoare-courses-content">
                        <div class="lecture-table-container">
                            <table class="lecture-table">
                                <thead>
                                    <tr>
                                        <th>Titlu Laborator</th>
                                        <th>Tip</th>
                                        <th>Materii</th>
                                        <th>Fișier</th>
                                        <th>Data Încărcării</th>
                                        <th>Acțiuni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coursesLaboratoarePaged as $course): ?>
                                        <tr>
                                         <td class="lecture-title-cell" data-label="Titlu Laborator">
                                                <?php echo htmlspecialchars($course['title']); ?>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Tip">
                                                <?php echo htmlspecialchars($course['type']); ?>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Materii">
                                                <?php echo htmlspecialchars($course['materii_names'] ?: '-'); ?>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Fișier">
                                                <span title="<?php echo htmlspecialchars($course['file_name']); ?>">
                                                    <?php echo htmlspecialchars($course['file_name']); ?>
                                                </span>
                                                <br>
                                                <small><?php echo number_format($course['file_size'] / 1024 / 1024, 2); ?> MB</small>
                                            </td>
                                             <td class="lecture-meta-cell" data-label="Data Încărcării">
                                                <?php echo date('d.m.Y H:i', strtotime($course['created_at'])); ?>
                                            </td>
                                             <td data-label="Acțiuni">
                                                <div class="lecture-actions">
                                                    <button class="btn-action btn-view" onclick="viewCourse('<?php echo $course['id']; ?>')">
                                                        Vezi
                                                    </button>
                                                    <button class="btn-action btn-download" onclick="downloadCourse('<?php echo $course['id']; ?>')">
                                                        Descarcă
                                                    </button>
                                                    <button class="btn-action btn-edit" onclick="editCourse('<?php echo $course['id']; ?>', '<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>', '<?php echo $course['type']; ?>')">
                                                        Editează
                                                    </button>
                                                    <button class="btn-action btn-delete" onclick="deleteCourse('<?php echo $course['id']; ?>', '<?php echo htmlspecialchars($course['title'], ENT_QUOTES); ?>')">
                                                        Șterge
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($coursesLaboratoarePages > 1): ?>
                            <div class="table-pager" style="display:flex;gap:8px;align-items:center;justify-content:center;padding:10px;border-top:1px solid #eee;flex-wrap:wrap;">
                                <?php $prev = max(1, $coursesLaboratoarePage - 1); $next = min($coursesLaboratoarePages, $coursesLaboratoarePage + 1); ?>
                                <a class="btn-secondary" href="?laboratoare_page=<?php echo $prev; ?>#courses-tab" style="font-size:0.85rem;<?php echo $coursesLaboratoarePage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                                <?php
                                    $__total = $coursesLaboratoarePages;
                                    $__current = (int)$coursesLaboratoarePage;
                                    $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                    $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                    sort($__pages);
                                    $__last = 0;
                                    foreach ($__pages as $__p) {
                                        if ($__last && $__p > $__last + 1) {
                                            echo '<span class="pager-ellipsis" style="color:#6b7280;">…</span>';
                                        }
                                        $isCurrent = ($__p === $__current);
                                        echo '<a class="btn-secondary" ' . ($isCurrent ? 'aria-current="page"' : '') . ' href="?laboratoare_page=' . $__p . '#courses-tab" style="font-size:0.85rem;">' . $__p . '</a>';
                                        $__last = $__p;
                                    }
                                ?>
                                <a class="btn-secondary" href="?laboratoare_page=<?php echo $next; ?>#courses-tab" style="font-size:0.85rem;<?php echo $coursesLaboratoarePage==$coursesLaboratoarePages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Faculties Management Tab -->
            <div id="faculties-tab" class="tab-content">
                    <div class="users-management">
                    <div class="section-header">
                        <h2>Facultăți</h2>
                        
                        <button class="btn-add-user" onclick="openAddFacultyModal()">
                            Adaugă Facultate
                        </button>
                    </div>
                    <div class="users-table-container">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Nume Facultate</th>
                                    <th>Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facultiesPaged as $f): ?>
                                    <tr>
                                        <td class="user-name-cell" data-label="Nume Facultate">
                                            <span><?php echo htmlspecialchars($f['name']); ?></span>
                                        </td>
                                        <td data-label="Acțiuni">
                                            <div class="user-actions">
                                                <button class="btn-action btn-edit" onclick="editFaculty('<?php echo $f['id']; ?>', '<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>')">Editează</button>
                                                <button class="btn-action btn-delete" onclick="deleteFaculty('<?php echo $f['id']; ?>', '<?php echo htmlspecialchars($f['name'], ENT_QUOTES); ?>')">Șterge</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($facultiesPages > 1): ?>
                        <div class="table-pager" style="display:flex;gap:8px;align-items:center;justify-content:center;padding:10px;border-top:1px solid #eee;flex-wrap:wrap;">
                            <?php $prev = max(1, $facultiesPage - 1); $next = min($facultiesPages, $facultiesPage + 1); ?>
                            <a class="btn-secondary" href="?faculties_page=<?php echo $prev; ?>#faculties-tab" style="font-size:0.85rem;<?php echo $facultiesPage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                            <?php
                                $__total = $facultiesPages;
                                $__current = (int)$facultiesPage;
                                $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                sort($__pages);
                                $__last = 0;
                                foreach ($__pages as $__p) {
                                    if ($__last && $__p > $__last + 1) {
                                        echo '<span class="pager-ellipsis" style="color:#6b7280;">…</span>';
                                    }
                                    $isCurrent = ($__p === $__current);
                                    echo '<a class="btn-secondary" ' . ($isCurrent ? 'aria-current="page"' : '') . ' href="?faculties_page=' . $__p . '#faculties-tab" style="font-size:0.85rem;">' . $__p . '</a>';
                                    $__last = $__p;
                                }
                            ?>
                            <a class="btn-secondary" href="?faculties_page=<?php echo $next; ?>#faculties-tab" style="font-size:0.85rem;<?php echo $facultiesPage==$facultiesPages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Materii Management Tab -->
            <div id="materii-tab" class="tab-content">
                <div class="users-management">
                    <div class="section-header">
                        <h2>Materii</h2>
                        <div class="materii-filters table-filters">
                            <select id="materiiFacultyFilter" onchange="filterMaterii()">
                                <option value="">Toate facultățile</option>
                                <?php foreach ($faculties as $faculty): ?>
                                    <option value="<?php echo htmlspecialchars($faculty['name']); ?>">
                                        <?php echo htmlspecialchars($faculty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="materiiYearFilter" onchange="filterMaterii()">
                                <option value="">Toți anii</option>
                                <option value="1">Anul 1</option>
                                <option value="2">Anul 2</option>
                                <option value="3">Anul 3</option>
                                <option value="4">Anul 4</option>
                            </select>
                            <select id="materiiSemFilter" onchange="filterMaterii()">
                                <option value="">Toate semestrele</option>
                                <option value="1">Semestrul 1</option>
                                <option value="2">Semestrul 2</option>
                            </select>
                            <input type="text" id="materiiSearch" placeholder="Căutare după nume..." onkeyup="filterMaterii()" size="16">
                            <button class="btn-secondary btn-reset" onclick="clearMateriiFilters()" title="Resetează filtrele">
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                    Resetează
                                </span>
                            </button>
                        </div>
                        <button class="btn-add-user" onclick="openAddMaterieModal()">
                            Adaugă Materie Nouă
                        </button>
                    </div>
                    <div class="users-table-container">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Nume Materie</th>
                                    <th>An</th>
                                    <th>Semestru</th>
                                    <th>Credite</th>
                                    <th>Facultăți</th>
                                    <th>Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materiiPaged as $materie): ?>
                                    <tr>
                                        <td class="user-name-cell" data-label="Nume Materie">
                                            <span><?php echo htmlspecialchars($materie['name']); ?></span>
                                            
                                        </td>
                                        <td data-label="An">Anul <?php echo $materie['year']; ?></td>
                                        <td data-label="Semestru">Sem. <?php echo $materie['semester']; ?></td>
                                        <td data-label="Credite"><?php echo $materie['credits']; ?></td>
                                        <td data-label="Facultăți"><?php echo htmlspecialchars($materie['faculty_names'] ?: '-'); ?></td>
                                        <td data-label="Acțiuni">
                                            <div class="user-actions">
                                                <button class="btn-action btn-edit" onclick="editMaterie('<?php echo $materie['id']; ?>', '<?php echo htmlspecialchars($materie['name'], ENT_QUOTES); ?>', <?php echo $materie['year']; ?>, <?php echo $materie['semester']; ?>, <?php echo $materie['credits']; ?>)">Editează</button>
                                                <button class="btn-action btn-delete" onclick="deleteMaterie('<?php echo $materie['id']; ?>', '<?php echo htmlspecialchars($materie['name'], ENT_QUOTES); ?>')">Șterge</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($materiiPages > 1): ?>
                        <div class="table-pager" style="display:flex;gap:8px;align-items:center;justify-content:center;padding:10px;border-top:1px solid #eee;flex-wrap:wrap;">
                            <?php $prev = max(1, $materiiPage - 1); $next = min($materiiPages, $materiiPage + 1); ?>
                            <a class="btn-secondary" href="?materii_page=<?php echo $prev; ?>#materii-tab" style="font-size:0.85rem;<?php echo $materiiPage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                            <?php
                                $__total = $materiiPages;
                                $__current = (int)$materiiPage;
                                $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                sort($__pages);
                                $__last = 0;
                                foreach ($__pages as $__p) {
                                    if ($__last && $__p > $__last + 1) {
                                        echo '<span class=\"pager-ellipsis\" style=\"color:#6b7280;\">…</span>';
                                    }
                                    $isCurrent = ($__p === $__current);
                                    echo '<a class=\"btn-secondary\" ' . ($isCurrent ? 'aria-current=\"page\"' : '') . ' href=\"?materii_page=' . $__p . '#materii-tab\" style=\"font-size:0.85rem;\">' . $__p . '</a>';
                                    $__last = $__p;
                                }
                            ?>
                            <a class="btn-secondary" href="?materii_page=<?php echo $next; ?>#materii-tab" style="font-size:0.85rem;<?php echo $materiiPage==$materiiPages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- General Settings Tab -->
            <div id="general-tab" class="tab-content">
                <div class="users-management">
                    <div class="section-header">
                        <h2>Setări Generale</h2>
                        <button class="btn-add-user" onclick="openAddSemesterModal()">
                            Adaugă Semestru Nou
                        </button>
                    </div>
                    
                    <!-- Active Semester Display -->
                    <?php 
                    // Ensure UI shows the real active semester based on date
                    if (function_exists('syncActiveSemesterByDate')) { syncActiveSemesterByDate(); }
                    $activeSemester = getActiveSemester();
                    if ($activeSemester): ?>
                        <div class="active-semester-info">
                            <h3 class="active-semester-title">Semestru Activ</h3>
                            <p class="active-semester-text">
                                <strong><?php echo htmlspecialchars($activeSemester['academic_year']); ?> - Semestrul <?php echo $activeSemester['semester_number']; ?></strong><br>
                                <?php echo date('d.m.Y', strtotime($activeSemester['start_date'])); ?> - <?php echo date('d.m.Y', strtotime($activeSemester['end_date'])); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="active-semester-info" style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
                            <h3 style="margin: 0 0 8px 0; color: #92400e;">Atenție</h3>
                            <p style="margin: 0; color: #92400e;">Nu există un semestru activ definit.</p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Semesters Table -->
                    <div class="users-table-container">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>An Academic</th>
                                    <th>Semestru</th>
                                    <th>Data Început</th>
                                    <th>Data Sfârșit</th>
                                    <th>Status</th>
                                    <th>Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($semestersPaged as $semester): ?>
                                    <tr>
                                        <td data-label="An Academic"><?php echo htmlspecialchars($semester['academic_year']); ?></td>
                                        <td data-label="Semestru">Semestrul <?php echo $semester['semester_number']; ?></td>
                                        <td data-label="Data Început"><?php echo date('d.m.Y', strtotime($semester['start_date'])); ?></td>
                                        <td data-label="Data Sfârșit"><?php echo date('d.m.Y', strtotime($semester['end_date'])); ?></td>
                                        <td data-label="Status">
                                            <?php
                                            $today = date('Y-m-d');
                                            $isCurrent = ($today >= $semester['start_date'] && $today <= $semester['end_date']);
                                            if ($isCurrent) {
                                                echo '<span style="color: #059669; font-weight: bold;">Activ</span>';
                                            } else {
                                                echo '<span style="color: #6b7280;">Inactiv</span>';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Acțiuni">
                                            <div class="user-actions">
                                                <button class="btn-action btn-edit" onclick="editSemester('<?php echo $semester['id']; ?>', '<?php echo htmlspecialchars($semester['academic_year'], ENT_QUOTES); ?>', <?php echo $semester['semester_number']; ?>, '<?php echo $semester['start_date']; ?>', '<?php echo $semester['end_date']; ?>')">Editează</button>
                                                <button class="btn-action btn-delete" onclick="deleteSemester('<?php echo $semester['id']; ?>', '<?php echo htmlspecialchars($semester['academic_year'], ENT_QUOTES); ?> - Sem. <?php echo $semester['semester_number']; ?>')">Șterge</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($semestersPages > 1): ?>
                        <div class="table-pager" style="display:flex;gap:8px;align-items:center;justify-content:center;padding:10px;border-top:1px solid #eee;flex-wrap:wrap;">
                            <?php $prev = max(1, $semestersPage - 1); $next = min($semestersPages, $semestersPage + 1); ?>
                            <a class="btn-secondary" href="?semesters_page=<?php echo $prev; ?>#general-tab" style="font-size:0.85rem;<?php echo $semestersPage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                            <?php
                                $__total = $semestersPages;
                                $__current = (int)$semestersPage;
                                $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                sort($__pages);
                                $__last = 0;
                                foreach ($__pages as $__p) {
                                    if ($__last && $__p > $__last + 1) {
                                        echo '<span class="pager-ellipsis" style="color:#6b7280;">…</span>';
                                    }
                                    $isCurrent = ($__p === $__current);
                                    echo '<a class="btn-secondary" ' . ($isCurrent ? 'aria-current="page"' : '') . ' href="?semesters_page=' . $__p . '#general-tab" style="font-size:0.85rem;">' . $__p . '</a>';
                                    $__last = $__p;
                                }
                            ?>
                            <a class="btn-secondary" href="?semesters_page=<?php echo $next; ?>#general-tab" style="font-size:0.85rem;<?php echo $semestersPage==$semestersPages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Adaugă Utilizator Nou</h3>
            <button type="button" class="modal-close" onclick="closeAddUserModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addUserForm">
                <div class="form-group">
                    <label for="username">Nume de utilizator*</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Parolă*</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="password-toggle">
                            <span class="show-text"></span>
                            <span class="hide-text" style="display: none;"></span>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmă parola*</label>
                    <div class="password-input-container">
                        <input type="password" id="password_confirm" name="password_confirm" required>
                        <button type="button" class="password-toggle">
                            <span class="show-text"></span>
                            <span class="hide-text" style="display: none;"></span>
                        </button>
                    </div>
                    <small id="add-password-match-message" style="display: none; margin-top: 4px;"></small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Rol*</label>
                        <select id="role" name="role" required>
                            <option value="student">Student</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group" id="add_faculty_group">
                        <label for="faculty_id">Facultate*</label>
                        <select id="faculty_id" name="faculty_id">
                            <option value="">Selectează facultatea</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f['id']); ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeAddUserModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitAddUserForm()">Adaugă Utilizator</button>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editează Utilizator</h3>
            <button type="button" class="modal-close" onclick="closeEditUserModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editUserForm">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="edit_username">Nume de utilizator</label>
                    <input type="text" id="edit_username" name="username" disabled style="background-color: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label for="edit_password">Parolă nouă</label>
                    <div class="password-input-container">
                        <input type="password" id="edit_password" name="password" placeholder="Lăsați gol pentru a păstra parola actuală">
                        <button type="button" class="password-toggle">
                            <span class="show-text"></span>
                            <span class="hide-text" style="display: none;"></span>
                        </button>
                    </div>

                </div>
                
                <div class="form-group">
                    <label for="edit_password_confirm">Confirmă parola nouă</label>
                    <div class="password-input-container">
                        <input type="password" id="edit_password_confirm" name="password_confirm" placeholder="Confirmați parola nouă">
                        <button type="button" class="password-toggle">
                            <span class="show-text"></span>
                            <span class="hide-text" style="display: none;"></span>
                        </button>
                    </div>
                    <small id="password-match-message" style="display: none; margin-top: 4px;"></small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_role">Rol*</label>
                        <select id="edit_role" name="role" required>
                            <option value="student">Student</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group" id="edit_faculty_group">
                        <label for="edit_faculty_id">Facultate*</label>
                        <select id="edit_faculty_id" name="faculty_id">
                            <option value="">Selectează facultatea</option>
                            <?php foreach ($faculties as $f): ?>
                                <option value="<?php echo htmlspecialchars($f['id']); ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeEditUserModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitEditUserForm()">Salvează Modificările</button>
        </div>
    </div>
</div>

<!-- Add Faculty Modal -->
<div id="addFacultyModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Adaugă Facultate</h3>
            <button type="button" class="modal-close" onclick="closeAddFacultyModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addFacultyForm" onsubmit="return false;">
                <div class="form-group">
                    <label for="faculty_name">Nume*</label>
                    <input type="text" id="faculty_name" name="name" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeAddFacultyModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitAddFacultyForm()">Adaugă Facultate</button>
        </div>
    </div>
    </div>

<!-- Edit Faculty Modal -->
<div id="editFacultyModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editează Facultate</h3>
            <button type="button" class="modal-close" onclick="closeEditFacultyModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editFacultyForm" onsubmit="return false;">
                <input type="hidden" id="edit_faculty_id_hidden" name="id">
                <div class="form-group">
                    <label for="edit_faculty_name">Nume*</label>
                    <input type="text" id="edit_faculty_name" name="name" required>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeEditFacultyModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitEditFacultyForm()">Salvează Modificările</button>
        </div>
    </div>
    </div>

<!-- Add Course Modal -->
<div id="addCourseModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Adaugă Curs Nou</h3>
            <button type="button" class="modal-close" onclick="closeAddCourseModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addCourseForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="course_title">Titlu*</label>
                    <input type="text" id="course_title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="course_type">Tip*</label>
                    <select id="course_type" name="type" required>
                        <option value="curs teoretic">Curs teoretic</option>
                        <option value="laborator">Laborator</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="course_materii">Materii*</label>
                    <select id="course_materii" name="materii_ids[]" multiple required size="5" style="width: 100%; padding: 8px;">
                        <?php foreach ($materii as $materie): ?>
                            <option value="<?php echo $materie['id']; ?>">
                                <?php echo htmlspecialchars($materie['name']); ?> (Anul <?php echo $materie['year']; ?>, Sem. <?php echo $materie['semester']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Țineți Ctrl și faceți clic pentru a selecta multiple materii</small>
                </div>
                
                <div class="form-group">
                    <label for="course_file">Fișier PDF*</label>
                    <input type="file" id="course_file" name="course_file" accept=".pdf" required>
                    <small>Mărimea maximă: <?php echo MAX_FILE_SIZE / 1024 / 1024; ?>MB. Doar fișiere PDF sunt permise.</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeAddCourseModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitAddCourseForm()">Adaugă Curs</button>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editCourseModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editează Curs</h3>
            <button type="button" class="modal-close" onclick="closeEditCourseModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editCourseForm">
                <input type="hidden" id="edit_course_id" name="course_id">
                
                <div class="form-group">
                    <label for="edit_course_title">Titlu*</label>
                    <input type="text" id="edit_course_title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_course_type">Tip*</label>
                    <select id="edit_course_type" name="type" required>
                        <option value="curs teoretic">Curs teoretic</option>
                        <option value="laborator">Laborator</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_course_materii">Materii*</label>
                    <select id="edit_course_materii" name="materii_ids[]" multiple required size="5" style="width: 100%; padding: 8px;">
                        <?php foreach ($materii as $materie): ?>
                            <option value="<?php echo $materie['id']; ?>">
                                <?php echo htmlspecialchars($materie['name']); ?> (Anul <?php echo $materie['year']; ?>, Sem. <?php echo $materie['semester']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Țineți Ctrl și faceți clic pentru a selecta multiple materii</small>
                </div>
                
                <div class="form-group">
                    <small><strong>Notă:</strong> Pentru a schimba fișierul, va trebui să ștergeți cursul și să-l adăugați din nou.</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeEditCourseModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitEditCourseForm()">Salvează Modificările</button>
        </div>
    </div>
</div>

<!-- PDF Viewer Modal -->
<div id="pdfModal" class="pdf-modal-overlay">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <h3 class="pdf-modal-title" id="pdfModalTitle">Vizualizare Document</h3>
            <div class="pdf-modal-controls">
                <button class="pdf-modal-close" onclick="closePDFModal()">
                    ✕
                </button>
            </div>
        </div>
        <div class="pdf-modal-body">
            <div class="pdf-loading" id="pdfLoading">
                <div class="pdf-loading-spinner"></div>
                <p>Se încarcă documentul...</p>
            </div>
            <iframe class="pdf-viewer" id="pdfViewer" style="display: none;"></iframe>
            <div class="pdf-error" id="pdfError" style="display: none;">
                <h3>Eroare la încărcare</h3>
                <p>Nu s-a putut încărca documentul PDF. Vă rugăm să încercați din nou sau să folosiți butonul de descărcare.</p>
                <button class="pdf-modal-btn download" onclick="downloadCurrentPDF()">
                    📥 Descarcă PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Materie Modal -->
<div id="addMaterieModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Adaugă Materie Nouă</h3>
            <button type="button" class="modal-close" onclick="closeAddMaterieModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addMaterieForm">
                <div class="form-group">
                    <label for="materie_name">Nume Materie*</label>
                    <input type="text" id="materie_name" name="name" required>
                </div>
                

                
                <div class="form-group">
                    <label for="materie_year">An*</label>
                    <select id="materie_year" name="year" required>
                        <option value="1">Anul I</option>
                        <option value="2">Anul II</option>
                        <option value="3">Anul III</option>
                        <option value="4">Anul IV</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="materie_semester">Semestru*</label>
                    <select id="materie_semester" name="semester" required>
                        <option value="1">Semestrul I</option>
                        <option value="2">Semestrul II</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="materie_credits">Credite*</label>
                    <input type="number" id="materie_credits" name="credits" min="1" max="30" value="6" required>
                </div>
                
                <div class="form-group">
                    <label for="materie_faculties">Facultăți*</label>
                    <select id="materie_faculties" name="faculty_ids[]" multiple required size="5" style="width: 100%; padding: 8px;">
                        <?php foreach ($faculties as $faculty): ?>
                            <option value="<?php echo $faculty['id']; ?>">
                                <?php echo htmlspecialchars($faculty['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Țineți Ctrl și faceți clic pentru a selecta multiple facultăți</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeAddMaterieModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitAddMaterieForm()">Adaugă Materie</button>
        </div>
    </div>
</div>

<!-- Edit Materie Modal -->
<div id="editMaterieModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editează Materie</h3>
            <button type="button" class="modal-close" onclick="closeEditMaterieModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editMaterieForm">
                <input type="hidden" id="edit_materie_id" name="materie_id">
                
                <div class="form-group">
                    <label for="edit_materie_name">Nume Materie*</label>
                    <input type="text" id="edit_materie_name" name="name" required>
                </div>
                

                
                <div class="form-group">
                    <label for="edit_materie_year">An*</label>
                    <select id="edit_materie_year" name="year" required>
                        <option value="1">Anul I</option>
                        <option value="2">Anul II</option>
                        <option value="3">Anul III</option>
                        <option value="4">Anul IV</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_materie_semester">Semestru*</label>
                    <select id="edit_materie_semester" name="semester" required>
                        <option value="1">Semestrul I</option>
                        <option value="2">Semestrul II</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_materie_credits">Credite*</label>
                    <input type="number" id="edit_materie_credits" name="credits" min="1" max="30" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_materie_faculties">Facultăți*</label>
                    <select id="edit_materie_faculties" name="faculty_ids[]" multiple required size="5" style="width: 100%; padding: 8px;">
                        <?php foreach ($faculties as $faculty): ?>
                            <option value="<?php echo $faculty['id']; ?>">
                                <?php echo htmlspecialchars($faculty['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Țineți Ctrl și faceți clic pentru a selecta multiple facultăți</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeEditMaterieModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitEditMaterieForm()">Salvează Modificările</button>
        </div>
    </div>
</div>

<!-- Add Semester Modal -->
<div id="addSemesterModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Adaugă Semestru Nou</h3>
            <button type="button" class="modal-close" onclick="closeAddSemesterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addSemesterForm">
                <div class="form-group">
                    <label for="semester_academic_year">An Academic*</label>
                    <input type="text" id="semester_academic_year" name="academic_year" placeholder="ex: 2024-2025" required>
                    <small>Format: YYYY-YYYY</small>
                </div>
                
                <div class="form-group">
                    <label for="semester_number">Semestru*</label>
                    <select id="semester_number" name="semester_number" required>
                        <option value="1">Semestrul I</option>
                        <option value="2">Semestrul II</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="semester_start_date">Data Început*</label>
                    <input type="date" id="semester_start_date" name="start_date" required>
                </div>
                
                <div class="form-group">
                    <label for="semester_end_date">Data Sfârșit*</label>
                    <input type="date" id="semester_end_date" name="end_date" required>
                </div>
                

            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeAddSemesterModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitAddSemesterForm()">Adaugă Semestru</button>
        </div>
    </div>
</div>

<!-- Edit Semester Modal -->
<div id="editSemesterModal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editează Semestru</h3>
            <button type="button" class="modal-close" onclick="closeEditSemesterModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editSemesterForm">
                <input type="hidden" id="edit_semester_id" name="semester_id">
                
                <div class="form-group">
                    <label for="edit_semester_academic_year">An Academic*</label>
                    <input type="text" id="edit_semester_academic_year" name="academic_year" placeholder="ex: 2024-2025" required>
                    <small>Format: YYYY-YYYY</small>
                </div>
                
                <div class="form-group">
                    <label for="edit_semester_number">Semestru*</label>
                    <select id="edit_semester_number" name="semester_number" required>
                        <option value="1">Semestrul I</option>
                        <option value="2">Semestrul II</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_semester_start_date">Data Început*</label>
                    <input type="date" id="edit_semester_start_date" name="start_date" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_semester_end_date">Data Sfârșit*</label>
                    <input type="date" id="edit_semester_end_date" name="end_date" required>
                </div>
                
                
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeEditSemesterModal()">Anulare</button>
            <button type="button" class="btn-primary" onclick="submitEditSemesterForm()">Salvează Modificările</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?> 
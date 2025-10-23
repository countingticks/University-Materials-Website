<?php
// Config is already loaded by the router
// requireLogin() is already called by the router

require_once __DIR__ . '/../config/database.php';

// Determine active semester (1 or 2)
$activeSemester = getActiveSemester();
$activeSemesterNumber = (int)($activeSemester['semester_number'] ?? 0);

// Determine accessible materii for current user
$currentUserId = $_SESSION['user_id'] ?? null;
$isAdminUser = isAdmin();
$availableMaterii = [];
if ($isAdminUser) {
    // Admins see all materii
    $availableMaterii = getAllMaterii();
} else {
    // Students see materii for their faculty
    $currentUser = $currentUserId ? getUserById($currentUserId) : null;
    $facultyId = $currentUser['faculty_id'] ?? null;
    if ($facultyId) {
        $availableMaterii = getMateriiByFacultyId($facultyId);
        // Filter by active semester when available; ignore academic year
        if ($activeSemesterNumber === 1 || $activeSemesterNumber === 2) {
            $availableMaterii = array_values(array_filter($availableMaterii, function($m) use ($activeSemesterNumber) {
                return (int)($m['semester'] ?? 0) === $activeSemesterNumber;
            }));
        }
    }
}

// Selected materie: from query or default to first available
$selectedMaterieId = $_GET['materie_id'] ?? '';
if (empty($selectedMaterieId) && !empty($availableMaterii)) {
    $selectedMaterieId = $availableMaterii[0]['id'];
}
// Ensure selected materie is among visible options; if not, fallback to first
if (!empty($selectedMaterieId) && !empty($availableMaterii)) {
    $ids = array_column($availableMaterii, 'id');
    if (!in_array($selectedMaterieId, $ids, true)) {
        $selectedMaterieId = $availableMaterii[0]['id'] ?? '';
    }
}

// Load courses for selected materie
$courses = [];
if (!empty($selectedMaterieId)) {
    $courses = getCoursesByMaterie($selectedMaterieId);
}

// Pagination settings (show 4 items per page on home)
$PAGE_SIZE = 5;

// Split courses by type and paginate per subtab
$cursuriTeoretice = array_values(array_filter($courses, function($course) {
    return isset($course['type']) && $course['type'] === 'curs teoretic';
}));
$laboratoare = array_values(array_filter($courses, function($course) {
    return isset($course['type']) && $course['type'] === 'laborator';
}));

$totalCursuri = count($cursuriTeoretice);
$totalLaboratoare = count($laboratoare);

$cursuriPages = max(1, (int)ceil($totalCursuri / $PAGE_SIZE));
$laboratoarePages = max(1, (int)ceil($totalLaboratoare / $PAGE_SIZE));

$cursuriPage = max(1, min($cursuriPages, (int)($_GET['cursuri_page'] ?? 1)));
$laboratoarePage = max(1, min($laboratoarePages, (int)($_GET['laboratoare_page'] ?? 1)));

$cursuriTeoreticePaged = array_slice($cursuriTeoretice, ($cursuriPage - 1) * $PAGE_SIZE, $PAGE_SIZE);
$laboratoarePaged = array_slice($laboratoare, ($laboratoarePage - 1) * $PAGE_SIZE, $PAGE_SIZE);

// Aggregate stats
$totalLectures = count($courses);
$totalDownloads = 0;
foreach ($courses as $c) { $totalDownloads += (int)($c['downloads'] ?? 0); }
$currentSemester = ""; // Could derive from semesters if needed
$courseCredits = ""; // Could be per materie
?>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="home-page">
    <div class="container">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="container">
                <h1>Grafică</h1>                
                <div class="course-meta">
                    <div class="course-meta-item university">
                        Universitatea Tehnică din Cluj-Napoca
                    </div>
                    <!-- <div class="course-meta-item semester">
                        <?php echo $currentSemester; ?>
                    </div>
                    <div class="course-meta-item credits">
                        <?php echo $courseCredits; ?>
                    </div> -->
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Main Content - Lectures -->
            <div class="lectures-section">
                <div class="section-header">
                    <div class="subtabs">
                        <button class="subtab-btn active" onclick="switchSubtab('cursuri')" data-tab="cursuri">
                            Cursuri
                        </button>
                        <button class="subtab-btn" onclick="switchSubtab('laboratoare')" data-tab="laboratoare">
                            Laboratoare
                        </button>
                    </div>
                    <div class="course-filters">
                        <?php if ($isAdminUser): ?>
                            <!-- <label for="materieFilter" style="font-weight: 600; color: var(--text-primary);">Materie:</label> -->
                            <select id="materieFilter" onchange="onMaterieChange(this.value)">
                                <?php foreach ($availableMaterii as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['id']); ?>" <?php echo $selectedMaterieId === $m['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <!-- <label for="materieFilter" style="font-weight: 600; color: var(--text-primary);">Materie:</label> -->
                            <select id="materieFilter" onchange="onMaterieChange(this.value)" <?php echo empty($availableMaterii) ? 'disabled' : ''; ?>>
                                <?php if (!empty($availableMaterii)): ?>
                                    <?php foreach ($availableMaterii as $m): ?>
                                        <option value="<?php echo htmlspecialchars($m['id']); ?>" <?php echo $selectedMaterieId === $m['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($m['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option>Nicio materie disponibilă</option>
                                <?php endif; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Cursuri Tab Content -->
                <div class="subtab-content active" id="cursuri-content">
                    <div class="lectures-list">
                        <?php
                        // Using server-side paginated data
                        ?>
                        <?php if (!empty($cursuriTeoreticePaged)): ?>
                            <?php foreach ($cursuriTeoreticePaged as $course): ?>
                                <div class="lecture-item">
                                    <div class="lecture-icon">
                                        📖
                                    </div>
                                    
                                    <div class="lecture-content">
                                        <div class="lecture-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                    </div>
                                    
                                    <div class="lecture-actions">
                                        <button class="btn-view" onclick="viewCourse('<?php echo $course['id']; ?>')">
                                            Vizualizare
                                        </button>
                                        <button class="btn-download" onclick="downloadCourse('<?php echo $course['id']; ?>')">
                                            Descărcare
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>Nu sunt cursuri teoretice disponibile</h3>
                                <p>Cursurile teoretice vor fi publicate în curând.</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($cursuriPages > 1): ?>
                        <div class="lectures-pager-wrapper">
                        <div class="table-pager">
                            <?php $prev = max(1, $cursuriPage - 1); $next = min($cursuriPages, $cursuriPage + 1); ?>
                            <a class="btn-secondary" href="?materie_id=<?php echo urlencode($selectedMaterieId); ?>&cursuri_page=<?php echo $prev; ?>#cursuri-content" style="font-size:0.85rem;<?php echo $cursuriPage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                            <?php
                                $__total = $cursuriPages;
                                $__current = (int)$cursuriPage;
                                $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                sort($__pages);
                                $__last = 0;
                                foreach ($__pages as $__p) {
                                    if ($__last && $__p > $__last + 1) {
                                        echo '<span class="pager-ellipsis" style="color:#6b7280;">…</span>';
                                    }
                                    $isCurrent = ($__p === $__current);
                                    echo '<a class="btn-secondary" ' . ($isCurrent ? 'aria-current="page"' : '') . ' href="?materie_id=' . urlencode($selectedMaterieId) . '&cursuri_page=' . $__p . '#cursuri-content" style="font-size:0.85rem;">' . $__p . '</a>';
                                    $__last = $__p;
                                }
                            ?>
                            <a class="btn-secondary" href="?materie_id=<?php echo urlencode($selectedMaterieId); ?>&cursuri_page=<?php echo $next; ?>#cursuri-content" style="font-size:0.85rem;<?php echo $cursuriPage==$cursuriPages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                        </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Laboratoare Tab Content -->
                <div class="subtab-content" id="laboratoare-content">
                    <div class="lectures-list">
                        <?php
                        // Using server-side paginated data
                        ?>
                        <?php if (!empty($laboratoarePaged)): ?>
                            <?php foreach ($laboratoarePaged as $course): ?>
                                <div class="lecture-item">
                                    <div class="lecture-icon">
                                        🧪
                                    </div>
                                    
                                    <div class="lecture-content">
                                        <div class="lecture-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                    </div>
                                    
                                    <div class="lecture-actions">
                                        <button class="btn-view" onclick="viewCourse('<?php echo $course['id']; ?>')">
                                            Vizualizare
                                        </button>
                                        <button class="btn-download" onclick="downloadCourse('<?php echo $course['id']; ?>')">
                                            Descărcare
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>Nu sunt laboratoare disponibile</h3>
                                <p>Laboratoarele vor fi publicate în curând.</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($laboratoarePages > 1): ?>
                        <div class="lectures-pager-wrapper">
                        <div class="table-pager">
                            <?php $prev = max(1, $laboratoarePage - 1); $next = min($laboratoarePages, $laboratoarePage + 1); ?>
                            <a class="btn-secondary" href="?materie_id=<?php echo urlencode($selectedMaterieId); ?>&laboratoare_page=<?php echo $prev; ?>#laboratoare-content" style="font-size:0.85rem;<?php echo $laboratoarePage==1?'pointer-events:none;opacity:.5;':''; ?>">‹ Anterior</a>
                            <?php
                                $__total = $laboratoarePages;
                                $__current = (int)$laboratoarePage;
                                $__pages = [1, max(1, $__current - 1), $__current, min($__total, $__current + 1), $__total];
                                $__pages = array_values(array_unique(array_filter($__pages, function($p) use ($__total) { return $p >= 1 && $p <= $__total; })));
                                sort($__pages);
                                $__last = 0;
                                foreach ($__pages as $__p) {
                                    if ($__last && $__p > $__last + 1) {
                                        echo '<span class="pager-ellipsis" style="color:#6b7280;">…</span>';
                                    }
                                    $isCurrent = ($__p === $__current);
                                    echo '<a class="btn-secondary" ' . ($isCurrent ? 'aria-current="page"' : '') . ' href="?materie_id=' . urlencode($selectedMaterieId) . '&laboratoare_page=' . $__p . '#laboratoare-content" style="font-size:0.85rem;">' . $__p . '</a>';
                                    $__last = $__p;
                                }
                            ?>
                            <a class="btn-secondary" href="?materie_id=<?php echo urlencode($selectedMaterieId); ?>&laboratoare_page=<?php echo $next; ?>#laboratoare-content" style="font-size:0.85rem;<?php echo $laboratoarePage==$laboratoarePages?'pointer-events:none;opacity:.5;':''; ?>">Următor ›</a>
                        </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="course-info">
                <!-- Instructor Info -->
                <div class="info-card instructor-card">
                    <div class="info-card-header">
                        <h3>Instructor</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="instructor-info">
                            <div class="instructor-avatar">👨‍🏫</div>
                            <div class="instructor-name">Ș.L. Dr. Ing. Bălcău Monica-Carmen</div>
                            <div class="instructor-role">Profesor Titular</div>
                            <a href="mailto:monica.balcau@auto.utcluj.ro" class="instructor-contact">
                                monica.balcau@auto.utcluj.ro
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Course Statistics -->
                <div class="info-card stats-card">
                    <div class="info-card-header">
                        <h3>Statistici Materie</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="stat-item">
                            <span class="stat-label">Cursuri publicate</span>
                            <span class="stat-value"><?php echo $totalLectures; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Descărcări totale</span>
                            <span class="stat-value"><?php echo $totalDownloads; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Semestru curent</span>
                            <span class="stat-value">Sem. <?php echo ($activeSemesterNumber === 1 || $activeSemesterNumber === 2) ? $activeSemesterNumber : '-'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Credite</span>
                            <span class="stat-value">6</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PDF Viewer Modal (home) -->
<div id="homePdfModal" class="pdf-modal-overlay">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <h3 class="pdf-modal-title">Vizualizare Document</h3>
            <div class="pdf-modal-controls">
                <button class="pdf-modal-close" onclick="closeHomePDFModal()">✕</button>
            </div>
        </div>
        <div class="pdf-modal-body">
            <div class="pdf-loading" id="homePdfLoading">
                <div class="pdf-loading-spinner"></div>
                <p>Se încarcă documentul...</p>
            </div>
            <iframe class="pdf-viewer" id="homePdfViewer" style="display: none;"></iframe>
            <div class="pdf-error" id="homePdfError" style="display: none;">
                <h3>Eroare la încărcare</h3>
                <p>Nu s-a putut încărca documentul PDF. Vă rugăm să încercați din nou sau să folosiți butonul de descărcare.</p>
                <button class="pdf-modal-btn download" onclick="downloadCurrentHomePDF()">📥 Descarcă PDF</button>
            </div>
        </div>
    </div>
    </div>

<script>
function onMaterieChange(materieId) {
    const url = new URL(window.location.href);
    url.searchParams.set('materie_id', materieId);
    window.location.href = url.toString();
}

// Reuse admin-style actions for view and download
function downloadCourse(courseId) {
    window.open(`/src/home/download.php?id=${courseId}`, '_blank');
}

// Desktop: open modal; Mobile: open new tab
let currentHomePDFCourseId = null;
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || (window.innerWidth <= 768);
}

function viewCourse(courseId) {
    if (isMobileDevice()) {
        window.open(`/src/home/view.php?id=${courseId}`, '_blank');
        return;
    }
    currentHomePDFCourseId = courseId;
    const modal = document.getElementById('homePdfModal');
    const loading = document.getElementById('homePdfLoading');
    const viewer = document.getElementById('homePdfViewer');
    const error = document.getElementById('homePdfError');

    modal.classList.add('active');
    loading.style.display = 'flex';
    viewer.style.display = 'none';
    error.style.display = 'none';

    viewer.src = `/src/home/view.php?id=${courseId}`;
    viewer.onload = function() {
        loading.style.display = 'none';
        viewer.style.display = 'block';
    };
    viewer.onerror = function() {
        loading.style.display = 'none';
        error.style.display = 'block';
    };
    setTimeout(() => {
        if (loading.style.display === 'flex') {
            loading.style.display = 'none';
            error.style.display = 'block';
        }
    }, 5000);
    document.body.style.overflow = 'hidden';
}

function closeHomePDFModal() {
    const modal = document.getElementById('homePdfModal');
    const viewer = document.getElementById('homePdfViewer');
    modal.classList.remove('active');
    viewer.src = '';
    currentHomePDFCourseId = null;
    document.body.style.overflow = '';
}

function downloadCurrentHomePDF() {
    if (currentHomePDFCourseId) {
        downloadCourse(currentHomePDFCourseId);
    }
}

document.addEventListener('click', function(e) {
    const modal = document.getElementById('homePdfModal');
    if (e.target === modal) {
        closeHomePDFModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('homePdfModal');
        if (modal && modal.classList.contains('active')) {
            closeHomePDFModal();
        }
    }
});

function switchSubtab(tabName) {
    // Remove active class from all subtab buttons
    const allSubtabBtns = document.querySelectorAll('.subtab-btn');
    allSubtabBtns.forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active class to clicked button
    const activeBtn = document.querySelector(`.subtab-btn[data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
    
    // Hide all subtab content
    const allSubtabContent = document.querySelectorAll('.subtab-content');
    allSubtabContent.forEach(content => {
        content.classList.remove('active');
    });
    
    // Show selected subtab content
    const activeContent = document.getElementById(`${tabName}-content`);
    if (activeContent) {
        activeContent.classList.add('active');
    }

    // Persist selection & update hash for reload/pager links
    try { localStorage.setItem('homeActiveSubtab', tabName); } catch (e) {}
    if (window.history && window.history.replaceState) {
        const newHash = `#${tabName}-content`;
        if (location.hash !== newHash) {
            history.replaceState(null, '', newHash);
        }
    }
}

// Add some interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Restore subtab based on hash or saved preference
    const hash = (window.location.hash || '').toLowerCase();
    if (hash.includes('laboratoare-content')) {
        switchSubtab('laboratoare');
    } else if (hash.includes('cursuri-content')) {
        switchSubtab('cursuri');
    } else {
        try {
            const saved = localStorage.getItem('homeActiveSubtab');
            if (saved === 'laboratoare' || saved === 'cursuri') {
                switchSubtab(saved);
            }
        } catch (e) {}
    }
    // Add hover effects and animations
    const lectureItems = document.querySelectorAll('.lecture-item');
    
    lectureItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?> 
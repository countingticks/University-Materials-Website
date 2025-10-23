<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Require login (any role)
requireLogin();

// Get course ID
$courseId = $_GET['id'] ?? '';
if (empty($courseId)) {
    http_response_code(400);
    die('Invalid course ID');
}

// Check access
$userId = $_SESSION['user_id'] ?? '';
if (!$userId || !userCanAccessCourse($userId, $courseId)) {
    http_response_code(403);
    die('Access denied');
}

// Get course
$course = getCourseById($courseId);
if (!$course) {
    http_response_code(404);
    die('Course not found');
}

// File path is relative to project root
$filePath = __DIR__ . '/../../' . $course['file_path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Increment downloads (best effort)
if (function_exists('incrementCourseDownloads')) {
    incrementCourseDownloads($courseId);
}

// Prepare headers
$fileName = $course['file_name'];
$fileSize = filesize($filePath);
$mimeType = $course['mime_type'];

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

if (ob_get_level()) { ob_end_clean(); }
readfile($filePath);
exit();
?>



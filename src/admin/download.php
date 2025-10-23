<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Security check - require login and admin role
requireLogin();
requireAdmin();

// Get course ID from URL parameter
$courseId = $_GET['id'] ?? '';

if (empty($courseId)) {
    http_response_code(400);
    die('Invalid course ID');
}

// Get course from database
$course = getCourseById($courseId);

if (!$course) {
    http_response_code(404);
    die('Course not found');
}

// Increment downloads counter (best effort)
if (function_exists('incrementCourseDownloads')) {
    incrementCourseDownloads($courseId);
}

// Fix file path - from src/admin/ we need to go up two levels to reach uploads/
$filePath = '../../' . $course['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Get file info
$fileName = $course['file_name'];
$fileSize = filesize($filePath);
$mimeType = $course['mime_type'];

// Set headers for file download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($filePath);
exit();
?>

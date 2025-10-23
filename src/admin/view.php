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

// Optionally track views separately from downloads; for now, we won't increment downloads on view
// If desired later, a separate 'views' column could be incremented here.

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

// Set headers for file viewing (inline display)
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=3600');

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Output file
readfile($filePath);
exit();
?>

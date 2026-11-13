<?php
/**
 * Secure file download for assignments and submissions.
 * Direct access to uploads/assignments and uploads/submissions is blocked
 * by .htaccess, so every download goes through this permission check.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

$type = $_GET['type'] ?? '';
$id   = get_int('id');
$file = null;
$allowed = false;

if ($type === 'assignment') {
    $st = $pdo->prepare('SELECT a.file_path, a.teacher_id, s.batch_id FROM assignments a JOIN subjects s ON s.id = a.subject_id WHERE a.id = ?');
    $st->execute([$id]);
    if ($r = $st->fetch()) {
        $file = $r['file_path'];
        $allowed = role() === 'admin'
            || (role() === 'teacher' && (int)$r['teacher_id'] === my_teacher_id())
            || (role() === 'student' && (int)$r['batch_id'] === (int)$pdo->query('SELECT batch_id FROM students WHERE id = ' . my_student_id())->fetchColumn());
    }
} elseif ($type === 'submission') {
    $st = $pdo->prepare('SELECT sb.file_path, sb.student_id, a.teacher_id FROM submissions sb JOIN assignments a ON a.id = sb.assignment_id WHERE sb.id = ?');
    $st->execute([$id]);
    if ($r = $st->fetch()) {
        $file = $r['file_path'];
        $allowed = role() === 'admin'
            || (role() === 'teacher' && (int)$r['teacher_id'] === my_teacher_id())
            || (role() === 'student' && (int)$r['student_id'] === my_student_id());
    }
}

$path = $file ? UPLOAD_PATH . '/' . $file : '';
if (!$allowed || !$file || !is_file($path)) {
    http_response_code(404);
    exit('File not found or you do not have permission to download it.');
}

$mime = [
    'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'doc' => 'application/msword', 'zip' => 'application/zip',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);

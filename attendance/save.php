<?php
/**
 * AJAX endpoint: save attendance for one batch and date.
 * Request (JSON): { batch_id, date, records: { student_id: "Present|Absent|Late" } }
 * Response (JSON): { ok: bool, message: string }
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

function json_out(bool $ok, string $message): void
{
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if (!is_logged_in() || !in_array(role(), ['teacher', 'admin'], true)) {
    http_response_code(403);
    json_out(false, 'Not allowed.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !hash_equals(csrf_token(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(400);
    json_out(false, 'Invalid request. Refresh the page and try again.');
}

$in       = json_decode(file_get_contents('php://input'), true) ?: [];
$batch_id = (int)($in['batch_id'] ?? 0);
$date     = (string)($in['date'] ?? '');
$records  = is_array($in['records'] ?? null) ? $in['records'] : [];

if (!$batch_id || !can_manage_batch($batch_id)) {
    json_out(false, 'You cannot mark attendance for this batch.');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today()) {
    json_out(false, 'Invalid date.');
}
if (!$records) {
    json_out(false, 'No students to save.');
}

// Only accept students who really belong to this batch
$st = $pdo->prepare('SELECT id FROM students WHERE batch_id = ?');
$st->execute([$batch_id]);
$valid = array_flip($st->fetchAll(PDO::FETCH_COLUMN));

$find   = $pdo->prepare('SELECT id FROM attendance WHERE student_id = ? AND att_date = ?');
$insert = $pdo->prepare('INSERT INTO attendance (student_id, batch_id, att_date, status, marked_by) VALUES (?,?,?,?,?)');
$update = $pdo->prepare('UPDATE attendance SET status = ?, batch_id = ?, marked_by = ? WHERE id = ?');

$count = ['Present' => 0, 'Absent' => 0, 'Late' => 0];
$pdo->beginTransaction();
try {
    foreach ($records as $sid => $status) {
        $sid = (int)$sid;
        if (!isset($valid[$sid]) || !isset($count[$status])) continue;

        $find->execute([$sid, $date]);
        if ($aid = $find->fetchColumn()) {
            $update->execute([$status, $batch_id, user_id(), $aid]);
        } else {
            $insert->execute([$sid, $batch_id, $date, $status, user_id()]);
        }
        $count[$status]++;
    }
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    error_log($ex->getMessage());
    json_out(false, 'Could not save attendance. Please try again.');
}

json_out(true, sprintf('Saved at %s — Present: %d, Late: %d, Absent: %d',
    date('h:i A'), $count['Present'], $count['Late'], $count['Absent']));

<?php
require_once '../config/db.php';
require_once '../includes/session.php';
requireLogin();

$uid = currentUserId();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?")->execute([$id, $uid]);
}
header('Location: list.php');
exit;

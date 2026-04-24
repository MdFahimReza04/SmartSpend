<?php
require_once '../config/db.php';
require_once '../includes/session.php';
requireLogin();

$uid = currentUserId();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    $check = $pdo->prepare("SELECT id FROM categories WHERE id=? AND user_id=? AND is_default=0");
    $check->execute([$id, $uid]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM categories WHERE id=? AND user_id=?")->execute([$id, $uid]);
    }
}
header('Location: manage.php');
exit;

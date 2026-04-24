<?php
require_once 'includes/session.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: auth/login.php');
}
exit;

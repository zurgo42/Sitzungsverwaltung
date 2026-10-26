<?php
require_once '../../phpqrcode/qrlib.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'todo';

// Determine target ICS file based on type
if ($type === 'poll') {
    $url = 'https://' . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/poll_ics.php?id=' . $id;
} else {
    // Default: todo
    $url = 'https://' . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/todo_ics.php?id=' . $id;
}

QRcode::png($url, false, QR_ECLEVEL_L, 8, 2);
?>
<?php
require_once '../../phpqrcode/qrlib.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$url = 'https://geschäftsordnung.com/Sitzungsverwaltung/todo_ics.php?id=' . $id;
QRcode::png($url, false, QR_ECLEVEL_L, 8, 2);
?>
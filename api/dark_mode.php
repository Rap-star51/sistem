<?php
session_start(); require_once '../includes/config.php';
if(!isset($_SESSION['user'])) exit;
$v=(int)($_GET['v']??0);
getDB()->prepare("UPDATE users SET dark_mode=? WHERE id=?")->execute([$v,(int)$_SESSION['user']['id']]);
$_SESSION['user']['dark_mode']=$v;
header('Content-Type: application/json'); echo '{"ok":true}';

<?php
session_start(); require_once '../includes/config.php';
if(!isset($_SESSION['user'])) { http_response_code(401); exit; }
$db=getDB(); $uid=(int)$_SESSION['user']['id']; $a=$_GET['action']??'';
if($a==='read_all') $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
elseif($a==='read'&&isset($_GET['id'])) $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['id'],$uid]);
header('Content-Type: application/json'); echo '{"ok":true}';

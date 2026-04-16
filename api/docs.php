<?php
session_start(); require_once '../includes/config.php'; requireLogin();
$bid=(int)($_GET['bid']??0);
$docs=getDB()->prepare("SELECT * FROM booking_documents WHERE booking_id=? ORDER BY created_at");
$docs->execute([$bid]); 
header('Content-Type: application/json');
echo json_encode(['docs'=>$docs->fetchAll()]);

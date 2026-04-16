<?php 
session_start(); 
session_unset();
session_destroy(); 
header('Location: /tps-v2-updated/index.php'); 
exit;
<?php
require_once 'config/conexao.php';
session_destroy();
header("Location: login.php");
exit;
?>
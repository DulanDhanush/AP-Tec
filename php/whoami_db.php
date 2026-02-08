<?php
require_once __DIR__ . "/db.php";
echo $pdo->query("SELECT DATABASE()")->fetchColumn();
<?php
require_once dirname(__DIR__) . '/config/app.php';
header('Location: ' . BASE_URL . (isset($_SESSION['user']) ? '/modul9/dashboard.php' : '/modul4/katalog.php'));
exit;

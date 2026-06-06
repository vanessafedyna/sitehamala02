<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');

header('Location: index.php');
exit;

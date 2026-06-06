<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

auth_start();
logout_admin_user();
redirect('admin/login.php');

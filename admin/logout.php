<?php
require_once __DIR__ . '/../config.php';

auth_logout();

flash('success', 'You have been signed out.');
redirect('admin/login.php');

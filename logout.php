<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('You have been signed out.', 'success');
redirect('index.php');

<?php
declare(strict_types=1);
// Redirect to the public front controller so relative CSS/JS, form URLs and routes resolve correctly.
header('Location: public/index.php?c=auth&a=login', true, 302);
header('Cache-Control: no-store');
exit;

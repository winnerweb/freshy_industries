<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';

function appDb(): PDO
{
    return db();
}


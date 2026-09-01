<?php
declare(strict_types=1);

function hasRole(string $role): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole(string $role): void
{
    if (!hasRole($role)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

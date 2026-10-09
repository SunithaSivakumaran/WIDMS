<?php
declare(strict_types=1);

/** Shared normalization and bound SQL for the Admin user directory. */
function widmsUserFilters(array $input): array
{
    $scalar = static fn(string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    $search = mb_substr($scalar('search'), 0, 120);
    $status = $scalar('status');
    $role = $scalar('role');
    if (!in_array($status, ['active', 'inactive'], true)) { $status = ''; }
    if (!in_array($role, ['admin', 'subject-officer', 'store-keeper', 'social-service-officer'], true)) { $role = ''; }
    $conditions = [];
    $parameters = [];
    if ($search !== '') {
        $matches = [];
        foreach (['u.full_name', 'u.username', 'u.email', 'u.salary_number', 'u.phone', 'u.division', 'd.name', 'ds.name'] as $index => $column) {
            $key = 'search_' . $index;
            // LOCATE treats percent and underscore literally, unlike LIKE.
            $matches[] = "LOCATE(LOWER(:$key), LOWER(COALESCE($column, ''))) > 0";
            $parameters[$key] = $search;
        }
        $conditions[] = '(' . implode(' OR ', $matches) . ')';
    }
    foreach (['status' => $status, 'role' => $role] as $key => $value) {
        if ($value !== '') {
            $conditions[] = "u.$key = :$key";
            $parameters[$key] = $value;
        }
    }
    return ['search' => $search, 'status' => $status, 'role' => $role,
        'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '', 'parameters' => $parameters];
}

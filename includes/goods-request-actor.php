<?php
declare(strict_types=1);

/** @return array{username: string, role: string} */
function widmsGoodsRequestLastActor(array $request): array
{
    if (!empty($request['allocated_to_sso_at'])) {
        $prefix = !empty($request['allocator_username']) ? 'allocator' : 'requester';
    } elseif (($request['status'] ?? '') === 'dispatched' && !empty($request['dispatcher_username'])) {
        $prefix = 'dispatcher';
    } elseif (!empty($request['approver_username'])) {
        $prefix = 'approver';
    } else {
        $prefix = 'requester';
    }

    $role = widmsGoodsRequestRoleLabel((string) ($request[$prefix . '_role'] ?? ''));

    $username = trim((string) ($request[$prefix . '_username'] ?? ''));

    return [
        'username' => $username !== '' ? $username : 'User not recorded',
        'role' => $role,
    ];
}

function widmsGoodsRequestRoleLabel(string $role): string
{
    return match ($role) {
        'subject-officer' => 'Subject Officer',
        'store-keeper' => 'Store Keeper',
        'admin' => 'Administrator',
        'social-service-officer' => 'Social Service Officer',
        default => 'Role not recorded',
    };
}

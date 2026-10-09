<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/goods-request-actor.php';

function expectActor(array $request, string $username, string $role): void
{
    $actor = widmsGoodsRequestLastActor($request);
    if ($actor !== ['username' => $username, 'role' => $role]) {
        throw new RuntimeException('Unexpected actor: ' . json_encode($actor));
    }
}

$base = [
    'status' => 'pending-admin-approval',
    'allocated_to_sso_at' => null,
    'requester_username' => 'subject.user',
    'requester_role' => 'subject-officer',
    'approver_username' => null,
    'approver_role' => null,
    'dispatcher_username' => null,
    'dispatcher_role' => null,
];

expectActor($base, 'subject.user', 'Subject Officer');
expectActor(array_replace($base, [
    'status' => 'approved-awaiting-dispatch',
    'approver_username' => 'admin.user',
    'approver_role' => 'admin',
]), 'admin.user', 'Administrator');
expectActor(array_replace($base, [
    'status' => 'dispatched',
    'approver_username' => 'admin.user',
    'approver_role' => 'admin',
    'dispatcher_username' => 'keeper.user',
    'dispatcher_role' => 'store-keeper',
]), 'keeper.user', 'Store Keeper');
expectActor(array_replace($base, [
    'status' => 'dispatched',
    'allocated_to_sso_at' => '2026-09-30 12:00:00',
    'dispatcher_username' => 'keeper.user',
    'dispatcher_role' => 'store-keeper',
]), 'subject.user', 'Subject Officer');

echo "Goods-request actor tests passed.\n";

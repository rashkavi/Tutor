<?php
declare(strict_types=1);

use Enlixo\Core\AuthContext;
use Enlixo\Core\HttpException;
use Enlixo\Entitlements\EntitlementService;
use Enlixo\Entitlements\FeatureLockedException;
use Enlixo\Roles\PermissionService;
use Enlixo\Roles\Role;
use Enlixo\Roles\RoleService;

function roleFixture(string $plan = 'grow'): array
{
    $repo = new FakeRoleRepo();
    $ent = new EntitlementService(new FakeEntitlementRepo([1 => $plan]));
    $perm = new PermissionService($repo, $ent);
    $audit = new FakeAudit();
    return [$repo, $perm, new RoleService($repo, $perm, $ent, $audit), $audit];
}

test('owner gets every permission the package allows', function () {
    [, $perm] = roleFixture('grow');
    $owner = new AuthContext(1, 'tutor', 1);
    T::true($perm->can($owner, 'payments.approve'));
    T::true($perm->can($owner, 'settings.roles'), 'custom roles on GROW');
    T::eq(false, $perm->can($owner, 'recordings.limits'), 'needs PRO controlled access');
});

test('staff only get their role permissions', function () {
    [$repo, $perm] = roleFixture('pro');
    $repo->roles[5] = new Role(5, 1, 'Cashier', null, false, ['payments.view', 'payments.approve']);
    $repo->staff[42] = 5;
    $cashier = new AuthContext(42, 'staff', 1);
    T::true($perm->can($cashier, 'payments.approve'));
    T::eq(false, $perm->can($cashier, 'students.manage'));
    T::throws(HttpException::class, fn () => $perm->require($cashier, 'students.manage'));
});

test('role permission blocked by package raises feature_locked', function () {
    [$repo, $perm] = roleFixture('start');
    $repo->roles[5] = new Role(5, 1, 'Assistant', null, false, ['recordings.limits']);
    $repo->staff[42] = 5;
    T::throws(FeatureLockedException::class, fn () => $perm->require(new AuthContext(42, 'staff', 1), 'recordings.limits'));
});

test('START cannot manage roles (upsell)', function () {
    [, , $svc] = roleFixture('start');
    T::throws(FeatureLockedException::class, fn () => $svc->list(new AuthContext(1, 'tutor', 1)));
});

test('create role validates and audits', function () {
    [$repo, , $svc, $audit] = roleFixture('grow');
    $owner = new AuthContext(1, 'tutor', 1);
    $e = T::throws(HttpException::class, fn () => $svc->create($owner, ['name' => '', 'permissions' => ['nope']]));
    T::true(isset($e?->details['fields']['name'], $e?->details['fields']['permissions']), 'both fields flagged');
    T::throws(HttpException::class, fn () => $svc->create($owner, ['name' => 'owner', 'permissions' => ['students.view']]), 'reserved name');

    $role = $svc->create($owner, ['name' => 'Class Assistant', 'permissions' => ['students.view', 'attendance.mark', 'students.view']]);
    T::eq(['students.view', 'attendance.mark'], $role->permissions, 'deduplicated');
    T::eq('role.created', $audit->entries[0]['action']);
    T::throws(HttpException::class, fn () => $svc->create($owner, ['name' => 'class assistant', 'permissions' => ['students.view']]), 'duplicate name');
});

test('list starts with built-in Owner role', function () {
    [, , $svc] = roleFixture('grow');
    $roles = $svc->list(new AuthContext(1, 'tutor', 1));
    T::eq('Owner', $roles[0]->name);
    T::true($roles[0]->isSystem);
});

test('delete role in use needs a target role', function () {
    [$repo, , $svc] = roleFixture('grow');
    $owner = new AuthContext(1, 'tutor', 1);
    $a = $svc->create($owner, ['name' => 'A', 'permissions' => ['students.view']]);
    $b = $svc->create($owner, ['name' => 'B', 'permissions' => ['students.view']]);
    $repo->staff[42] = $a->id;
    T::throws(HttpException::class, fn () => $svc->delete($owner, $a->id, null));
    $svc->delete($owner, $a->id, $b->id);
    T::eq($b->id, $repo->staff[42], 'staff moved');
});

test('other tutors cannot touch my roles', function () {
    [$repo, , $svc] = roleFixture('grow');
    $repo->roles[5] = new Role(5, 2, 'Theirs', null, false, ['students.view']);
    T::throws(HttpException::class, fn () => $svc->update(new AuthContext(1, 'tutor', 1), 5, ['name' => 'Mine', 'permissions' => ['students.view']]));
});

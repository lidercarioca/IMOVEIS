<?php

require_once __DIR__ . '/../../../app/legacy/UserModel.php';

use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    protected function setUp(): void
    {
        \App\Auth\AuthContext::reset();
    }

    public function testResetPasswordByUserIdDelegatesToRepositoryReturnsTrue()
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('updatePasswordData')
            ->with(123, 'hashedpw')
            ->willReturn(true);

        $service = new \App\Application\User\UserService($repo);
        $this->assertTrue($service->resetPasswordByUserId(123, 'hashedpw'));
    }

    public function testResetPasswordByUserIdDelegatesToRepositoryReturnsFalse()
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('updatePasswordData')
            ->with(999, 'otherhash')
            ->willReturn(false);

        $service = new \App\Application\User\UserService($repo);
        $this->assertFalse($service->resetPasswordByUserId(999, 'otherhash'));
    }

    public function testRequestPasswordResetCreatesTokenForExistingUser()
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn(['id' => 42, 'email' => 'user@example.com', 'tenant_id' => 7]);
        $repo->expects($this->once())
            ->method('findLatestPasswordResetForUser')
            ->with(42)
            ->willReturn(null);
        $repo->expects($this->once())
            ->method('createPasswordResetToken')
            ->with(42, $this->isType('string'), $this->isType('string'))
            ->willReturn(true);

        $service = new \App\Application\User\UserService($repo);
        $result = $service->requestPasswordReset('user@example.com');

        $this->assertIsArray($result);
        $this->assertSame(42, $result['user_id']);
        $this->assertSame('user@example.com', $result['email']);
        $this->assertNotEmpty($result['token']);
        $this->assertNotEmpty($result['expires_at']);
    }

    public function testPasswordResetStatusRejectsInvalidUsedAndExpiredTokens()
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->method('findPasswordResetByToken')
            ->willReturnMap([
                ['missing', null],
                ['used-token', ['id' => 7, 'user_id' => 42, 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'used' => 1]],
                ['expired-token', ['id' => 8, 'user_id' => 43, 'expires_at' => date('Y-m-d H:i:s', time() - 60), 'used' => 0]],
                ['valid-token', ['id' => 9, 'user_id' => 44, 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'used' => 0]],
            ]);

        $service = new \App\Application\User\UserService($repo);

        $this->assertSame('invalid', $service->getPasswordResetStatus('missing'));
        $this->assertSame('used', $service->getPasswordResetStatus('used-token'));
        $this->assertSame('expired', $service->getPasswordResetStatus('expired-token'));
        $this->assertSame('valid', $service->getPasswordResetStatus('valid-token'));
    }

    public function testCompletePasswordResetFailsWhenTokenIsAlreadyUsedOrClaimed()
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('beginTransaction');
        $repo->expects($this->once())
            ->method('claimPasswordResetToken')
            ->with('abc123')
            ->willReturn(null);
        $repo->expects($this->once())
            ->method('rollback');

        $service = new \App\Application\User\UserService($repo);
        $this->assertFalse($service->completePasswordReset('abc123', 'NewPassword123'));
    }

    public function testCompletePasswordResetUpdatesPasswordAndMarksTokenUsed()
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())->method('beginTransaction');
        $repo->expects($this->once())
            ->method('claimPasswordResetToken')
            ->with('abc123')
            ->willReturn([
                'id' => 18,
                'user_id' => 42,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'used' => 0,
            ]);
        $repo->expects($this->once())
            ->method('updatePasswordData')
            ->with(42, $this->isType('string'))
            ->willReturn(true);
        $repo->expects($this->once())
            ->method('markPasswordResetUsed')
            ->with(18)
            ->willReturn(true);
        $repo->expects($this->once())
            ->method('commit');

        $service = new \App\Application\User\UserService($repo);
        $this->assertTrue($service->completePasswordReset('abc123', 'NewPassword123'));
    }

    public function testUpdateUserAllowsAdminToEditAnotherUserUsingAuthContext()
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['name'] = 'Updated Name';

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['name' => 'Updated Name'])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $result = $service->updateUser(42, 9, ['name' => 'Updated Name']);

        $this->assertSame('Updated Name', $result['name']);
    }

    public function testUpdateUserAcceptsNullBrokerDefaultCommissionPercentage()
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['broker_default_commission_percentage'] = null;

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['broker_default_commission_percentage' => null])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['broker_default_commission_percentage' => null]);
    }

    public function testUpdateUserAcceptsZeroBrokerDefaultCommissionPercentage()
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['broker_default_commission_percentage'] = 0;

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['broker_default_commission_percentage' => 0])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['broker_default_commission_percentage' => 0]);
    }

    public function testUpdateUserAcceptsFortyBrokerDefaultCommissionPercentage()
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['broker_default_commission_percentage'] = 40;

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['broker_default_commission_percentage' => 40])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['broker_default_commission_percentage' => 40]);
    }

    public function testUpdateUserAcceptsHundredBrokerDefaultCommissionPercentage()
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['broker_default_commission_percentage'] = 100;

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['broker_default_commission_percentage' => 100])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['broker_default_commission_percentage' => 100]);
    }

    public function testSuperadminCanChangeUserRole(): void
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['role'] = 'admin';

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['role' => 'admin'])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(1, 'superadmin');

        $service = new \App\Application\User\UserService($repo);
        $result = $service->updateUser(42, 9, ['role' => 'admin']);

        $this->assertSame('admin', $result['role']);
    }

    public function testTenantAdminCanPromoteUserToAdmin(): void
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['role'] = 'admin';

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['role' => 'admin'])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $result = $service->updateUser(42, 9, ['role' => 'admin']);

        $this->assertSame('admin', $result['role']);
    }

    public function testTenantAdminCanDemoteAdminToUser(): void
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'admin-user',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'phone' => null,
            'role' => 'admin',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['role'] = 'user';

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['role' => 'user'])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $result = $service->updateUser(42, 9, ['role' => 'user']);

        $this->assertSame('user', $result['role']);
    }

    public function testTenantAdminCannotGrantSuperadminRole(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Apenas superadmin pode alterar a role');

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['role' => 'superadmin']);
    }

    public function testTenantAdminCannotEditUserFromAnotherTenant(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Usuário não encontrado');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->with(42, 9)
            ->willReturn(null);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['role' => 'admin']);
    }

    public function testRegularUserCannotChangeRole(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Apenas superadmin pode alterar a role');

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(42, 'user');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['role' => 'admin']);
    }

    public function testUpdateUserRejectsNegativeBrokerDefaultCommissionPercentage()
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['broker_default_commission_percentage' => -1]);
    }

    public function testUpdateUserRejectsBrokerDefaultCommissionPercentageAboveHundred()
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['broker_default_commission_percentage' => 101]);
    }

    public function testUpdateUserRejectsNonNumericBrokerDefaultCommissionPercentage()
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['broker_default_commission_percentage' => 'abc']);
    }

    public function testUpdateUserRejectsTenantIdOverride()
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['tenant_id' => 999]);
    }

    public function testUpdateUserRejectsUsernameOverride()
    {
        $this->expectException(\InvalidArgumentException::class);

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['username' => 'new-user']);
    }

    public function testUpdateUserRejectsIsSuperadminOverride()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Campos tenant_id, username e is_superadmin não podem ser alterados');

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->with(42, 9)
            ->willReturn($user);
        $repo->expects($this->never())
            ->method('update');

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service = new \App\Application\User\UserService($repo);
        $service->updateUser(42, 9, ['is_superadmin' => 1]);
    }

    public function testListUsersOnlyReturnsCurrentTenantRecords(): void
    {
        $user = [
            'id' => 41,
            'tenant_id' => 9,
            'is_superadmin' => 0,
            'username' => 'alpha-user',
            'role' => 'user',
            'name' => 'Alpha',
            'email' => 'alpha@example.com',
            'phone' => null,
            'broker_default_commission_percentage' => null,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'active' => 1,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('countUsers')
            ->with(9, [])
            ->willReturn(1);
        $repo->expects($this->once())
            ->method('listUsers')
            ->with(9, [], 10, 0)
            ->willReturn([$user]);

        $service = new \App\Application\User\UserService($repo);
        $result = $service->listUsers(9, [], 1, 10);

        $this->assertSame(1, $result['pagination']['total']);
        $this->assertCount(1, $result['data']);
        $this->assertSame(41, $result['data'][0]['id']);
        $this->assertArrayNotHasKey('password', $result['data'][0]);
    }

    public function testGetUserByIdRejectsUserOutsideCurrentTenant(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Usuário não encontrado');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->with(42, 9)
            ->willReturn(null);

        $service = new \App\Application\User\UserService($repo);
        $service->getUserById(42, 9);
    }

    public function testRegularUserCanUpdateOnlyTheirOwnRecord(): void
    {
        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'is_superadmin' => 0,
            'username' => 'self-user',
            'role' => 'user',
            'name' => 'Original',
            'email' => 'self@example.com',
            'phone' => null,
            'broker_default_commission_percentage' => null,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'active' => 1,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['name'] = 'Updated by self';

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['name' => 'Updated by self'])
            ->willReturn(true);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(42, 'user');

        $service = new \App\Application\User\UserService($repo);
        $result = $service->updateUser(42, 9, ['name' => 'Updated by self']);

        $this->assertSame('Updated by self', $result['name']);
    }

    public function testCreateUserRejectsDuplicateUsername(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Username já está em uso');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findByUsername')
            ->with('duplicate-user')
            ->willReturn(['id' => 7]);

        $service = new \App\Application\User\UserService($repo);
        $service->createUser(9, [
            'username' => 'duplicate-user',
            'name' => 'Duplicate User',
            'email' => 'dup@example.com',
            'password' => 'StrongPass!123',
        ]);
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email já está em uso');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findByUsername')
            ->with('unique-user')
            ->willReturn(null);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with('dup@example.com')
            ->willReturn(['id' => 8]);

        $service = new \App\Application\User\UserService($repo);
        $service->createUser(9, [
            'username' => 'unique-user',
            'name' => 'Unique User',
            'email' => 'dup@example.com',
            'password' => 'StrongPass!123',
        ]);
    }

    public function testCreateUserAllowsAuthorizedSystemCreationForAdminRole(): void
    {
        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findByUsername')
            ->with('system-admin')
            ->willReturn(null);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with('system-admin@example.com')
            ->willReturn(null);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payload) {
                $this->assertSame(9, $payload['tenant_id']);
                $this->assertSame('system-admin', $payload['username']);
                $this->assertSame('admin', $payload['role']);
                $this->assertSame(0, (int)$payload['is_superadmin']);
                return true;
            }))
            ->willReturn(77);
        $repo->expects($this->once())
            ->method('findById')
            ->with(77, 9)
            ->willReturn([
                'id' => 77,
                'tenant_id' => 9,
                'is_superadmin' => 1,
                'username' => 'system-admin',
                'role' => 'admin',
                'name' => 'System Admin',
                'email' => 'system-admin@example.com',
                'phone' => null,
                'broker_default_commission_percentage' => null,
                'created_at' => '2024-01-01 00:00:00',
                'last_login' => null,
                'active' => 1,
                'force_password_change' => 0,
                'password_changed_at' => null,
            ]);

        $service = new \App\Application\User\UserService($repo);
        $result = $service->createUser(9, [
            'username' => 'system-admin',
            'name' => 'System Admin',
            'email' => 'system-admin@example.com',
            'password' => 'StrongPass!123',
            'role' => 'admin',
        ], true);

        $this->assertSame('admin', $result['role']);
        $this->assertSame(77, $result['id']);
    }

    public function testRegularUserCannotCreateUsers(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permissão negada para criar usuário');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->method('findByUsername')->willReturn(null);
        $repo->method('findByEmail')->willReturn(null);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(12, 'user');

        $service = new \App\Application\User\UserService($repo);
        $service->createUser(9, [
            'username' => 'new-user',
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'StrongPass!123',
            'role' => 'user',
        ]);
    }

    public function testDeleteUserProtectsPrimaryAdministratorAccount(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Não é permitido excluir o usuário administrador principal');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $service = new \App\Application\User\UserService($repo);

        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $service->deleteUser(1, 9);
    }

    public function testDeleteUserRejectsUserOutsideTenant(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Usuário não encontrado');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->with(42, 9)
            ->willReturn(null);

        $service = new \App\Application\User\UserService($repo);
        $service->deleteUser(42, 9);
    }

    public function testCreateUserLogsSingleAuditEventWithTrustedActorAndTenant(): void
    {
        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(9, 'admin');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findByUsername')
            ->with('new-user')
            ->willReturn(null);
        $repo->expects($this->once())
            ->method('findByEmail')
            ->with('new-user@example.com')
            ->willReturn(null);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payload) {
                $this->assertSame(9, $payload['tenant_id']);
                $this->assertSame('new-user', $payload['username']);
                $this->assertSame('user', $payload['role']);
                return true;
            }))
            ->willReturn(77);
        $repo->expects($this->once())
            ->method('findById')
            ->with(77, 9)
            ->willReturn([
                'id' => 77,
                'tenant_id' => 9,
                'username' => 'new-user',
                'name' => 'New User',
                'email' => 'new-user@example.com',
                'role' => 'user',
                'phone' => null,
                'broker_default_commission_percentage' => null,
                'created_at' => '2024-01-01 00:00:00',
                'last_login' => null,
                'active' => 1,
                'is_superadmin' => 0,
                'force_password_change' => 0,
                'password_changed_at' => null,
            ]);

        $auditService = $this->createMock(\App\Application\User\UserAuditService::class);
        $auditService->expects($this->once())
            ->method('logUserChange')
            ->with(
                77,
                [],
                $this->callback(fn(array $payload) => isset($payload['id']) && $payload['id'] === 77),
                'create',
                9,
                9,
                'Usuário criado',
                $this->anything(),
                $this->anything(),
                'success'
            )
            ->willReturn(true);

        $service = new \App\Application\User\UserService($repo, $auditService);
        $result = $service->createUser(9, [
            'username' => 'new-user',
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'StrongPass!123',
            'role' => 'user',
        ]);

        $this->assertSame(77, $result['id']);
        $this->assertSame('new-user', $result['username']);
    }

    public function testUpdateUserDoesNotEmitSuccessEventForUnauthorizedActor(): void
    {
        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(12, 'user');

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->once())
            ->method('findById')
            ->with(42, 9)
            ->willReturn([
                'id' => 42,
                'tenant_id' => 9,
                'username' => 'other-user',
                'name' => 'Other User',
                'email' => 'other@example.com',
                'role' => 'user',
                'active' => 1,
                'created_at' => '2024-01-01 00:00:00',
                'last_login' => null,
                'is_superadmin' => 0,
                'force_password_change' => 0,
                'password_changed_at' => null,
            ]);

        $auditService = $this->createMock(\App\Application\User\UserAuditService::class);
        $auditService->expects($this->never())
            ->method('logUserChange');

        $service = new \App\Application\User\UserService($repo, $auditService);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permissão negada para atualizar este usuário');

        $service->updateUser(42, 9, ['name' => 'Updated Name']);
    }

    public function testUpdateUserIgnoresAuditFailureAndPreservesMainOperation(): void
    {
        $context = \App\Auth\AuthContext::getInstance();
        $context->authenticate(7, 'admin');

        $user = [
            'id' => 42,
            'tenant_id' => 9,
            'username' => 'second-user',
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'phone' => null,
            'role' => 'user',
            'active' => 1,
            'created_at' => '2024-01-01 00:00:00',
            'last_login' => null,
            'is_superadmin' => 0,
            'force_password_change' => 0,
            'password_changed_at' => null,
        ];

        $updated = $user;
        $updated['name'] = 'Updated Name';

        $repo = $this->createMock(\App\Infrastructure\User\UserRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($user, $updated);
        $repo->expects($this->once())
            ->method('update')
            ->with(42, 9, ['name' => 'Updated Name'])
            ->willReturn(true);

        $auditService = $this->createMock(\App\Application\User\UserAuditService::class);
        $auditService->expects($this->once())
            ->method('logUserChange')
            ->with(
                42,
                ['name' => 'Original Name'],
                ['name' => 'Updated Name'],
                'update',
                7,
                9,
                'Usuário atualizado',
                $this->anything(),
                $this->anything(),
                'success'
            )
            ->willReturn(false);

        $service = new \App\Application\User\UserService($repo, $auditService);
        $result = $service->updateUser(42, 9, ['name' => 'Updated Name']);

        $this->assertSame('Updated Name', $result['name']);
    }

    public function testLegacyUserModelLogUserChangeUsesTenantContextUser(): void
    {
        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $originalUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        try {
            $pdo = new \PDO('sqlite::memory:');
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                tenant_id INTEGER,
                action_type TEXT,
                resource_type TEXT,
                resource_id INTEGER,
                description TEXT,
                old_values TEXT,
                new_values TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');

            $GLOBALS['pdo'] = $pdo;
            $_SESSION = ['user_id' => 9, 'tenant_id' => 7, 'role' => 'admin'];
            \App\Auth\AuthContext::reset();
            \App\Auth\AuthContext::getInstance()->authenticate(9, 'admin');

            $model = new \App\Models\UserModel($pdo);
            $result = $model->logUserChange(42, ['name' => 'Old Name'], ['name' => 'New Name'], 'update');

            $this->assertTrue($result);

            $row = $pdo->query('SELECT user_id, tenant_id, action_type, resource_id, ip_address, user_agent FROM audit_logs')->fetch(\PDO::FETCH_ASSOC);
            $this->assertNotFalse($row);
            $this->assertSame(9, (int) $row['user_id']);
            $this->assertSame(7, (int) $row['tenant_id']);
            $this->assertSame('update', $row['action_type']);
            $this->assertSame('42', (string) $row['resource_id']);
            $this->assertSame('127.0.0.1', $row['ip_address']);
            $this->assertSame('PHPUnit', $row['user_agent']);
        } finally {
            $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
            if ($originalUserAgent === null) {
                unset($_SERVER['HTTP_USER_AGENT']);
            } else {
                $_SERVER['HTTP_USER_AGENT'] = $originalUserAgent;
            }
        }
    }

    public function testLegacyTenantContextAuthorizationRejectsUnauthenticatedAndUnauthorizedUsers(): void
    {
        $_SESSION = [];
        \App\Auth\AuthContext::reset();
        $this->assertFalse(\TenantContext::canManageUser(42));

        $_SESSION = ['user_id' => 8, 'tenant_id' => 7, 'role' => 'user'];
        \App\Auth\AuthContext::getInstance()->authenticate(8, 'user');
        $this->assertFalse(\TenantContext::canManageUser(42));

        $_SESSION = ['user_id' => 9, 'tenant_id' => 7, 'role' => 'admin'];
        \App\Auth\AuthContext::getInstance()->authenticate(9, 'admin');
        $this->assertTrue(\TenantContext::canManageUser(42));
        $this->assertTrue(\TenantContext::canManageUser(9));
    }
}

<?php

namespace Tests\Unit;

use App\Repository\AgendamentoRepository;
use App\Service\CheckLoginService;
use PHPUnit\Framework\TestCase;

class CheckLoginMigrationContractTest extends TestCase
{
    public function testCheckLoginServiceAndRepositoryExist(): void
    {
        $this->assertTrue(class_exists(CheckLoginService::class));
        $this->assertTrue(class_exists(AgendamentoRepository::class));
        $this->assertTrue(method_exists(CheckLoginService::class, 'getLoginStatus'));
    }

    public function testCheckLoginEndpointUsesServiceAndNoInlineSql(): void
    {
        $endpoint = file_get_contents(__DIR__ . '/../../api/v1/check_login.php');

        $this->assertStringContainsString('new \\App\\Service\\CheckLoginService', $endpoint);
        $this->assertStringNotContainsString('SELECT COUNT(*) as total FROM agendamentos', $endpoint);
    }
}

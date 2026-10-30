<?php

namespace Tests\Unit;

use App\Application\Lead\LeadService;
use App\Infrastructure\Lead\LeadRepository;
use PHPUnit\Framework\TestCase;
use PDO;

class LeadServiceTenantSafetyTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE properties (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, title TEXT, assigned_user_id INTEGER NULL, status TEXT DEFAULT "active");');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, role TEXT, name TEXT, email TEXT, phone TEXT, created_at TEXT);');
        $this->pdo->exec('CREATE TABLE leads (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, name TEXT, email TEXT, phone TEXT, property_id INTEGER NULL, message TEXT, status TEXT, source TEXT, created_at TEXT, updated_at TEXT, assigned_user_id INTEGER NULL, utm_tracking_id INTEGER NULL);');
        $this->pdo->exec('CREATE TABLE portal_integrations (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, portal_slug TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, endpoint TEXT NULL, credentials TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(tenant_id, portal_slug));');
    }

    public function testResolveTenantForLeadSubmissionDoesNotDefaultToSystemTenant(): void
    {
        $service = new LeadService(new LeadRepository($this->pdo));

        $context = $service->resolveTenantForLeadSubmission(null, null);

        $this->assertNull($context['tenant_id']);
        $this->assertNull($context['property_id']);
    }

    public function testSubmitLeadFromContactRequiresAnExplicitTenant(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tenant');

        $service = new LeadService(new LeadRepository($this->pdo));
        $service->submitLeadFromContact([
            'name' => 'Teste',
            'email' => 'teste@example.com',
            'phone' => '11999999999',
            'message' => 'Mensagem',
            'property_id' => null,
            'status' => 'new',
            'source' => 'site',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }
}

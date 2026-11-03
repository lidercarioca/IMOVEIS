<?php

namespace Tests\Unit;

use App\Application\Integration\FacebookLeadService;
use App\Infrastructure\Lead\LeadRepository;
use PHPUnit\Framework\TestCase;

class FacebookLeadWebhookSecurityTest extends TestCase
{
    private \PDO $pdo;
    private FacebookLeadService $service;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT NOT NULL DEFAULT "", slug TEXT NOT NULL DEFAULT "", company_subdomain TEXT NOT NULL DEFAULT "", status TEXT NOT NULL DEFAULT "active");');
        $this->pdo->exec('CREATE TABLE portal_integrations (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, portal_slug TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0, endpoint TEXT DEFAULT NULL, credentials TEXT DEFAULT NULL);');
        $this->pdo->exec('CREATE TABLE leads (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL, message TEXT NOT NULL, status TEXT NOT NULL DEFAULT "new", source TEXT NOT NULL DEFAULT "facebook_leads", created_at TEXT NOT NULL, updated_at TEXT NOT NULL, property_id INTEGER NULL, assigned_user_id INTEGER NULL, utm_tracking_id INTEGER NULL);');

        $this->pdo->exec("INSERT INTO tenants (id, status) VALUES (10, 'active');");
        $this->pdo->exec("INSERT INTO tenants (id, status) VALUES (20, 'active');");
        $this->pdo->exec("INSERT INTO tenants (id, status) VALUES (30, 'inactive');");

        $repo = new LeadRepository($this->pdo);
        $this->service = new class($repo, $this->pdo) extends FacebookLeadService {
            public function fetchLeadDataFromGraph(
                string $leadgenId,
                string $accessToken,
                ?string $appSecret,
                string $graphVersion
            ): array {
                return [
                    'field_data' => [
                        ['name' => 'full_name', 'values' => ['Alice Test']],
                        ['name' => 'email', 'values' => ['alice@example.com']],
                        ['name' => 'phone', 'values' => ['(11) 99999-0000']],
                        ['name' => 'message', 'values' => ['Mensagem do webhook']],
                    ],
                    'created_time' => '2024-01-01T00:00:00+0000',
                ];
            }
        };
    }

    private function seedFacebookIntegration(int $tenantId, string $appSecret, bool $enabled = true): void
    {
        $credentials = json_encode([
            'app_secret' => $appSecret,
            'webhook_verify_token' => 'verify-token-' . $tenantId,
            'access_token' => 'access-token-' . $tenantId,
            'graph_api_version' => 'v16.0',
        ], JSON_UNESCAPED_UNICODE);

        $this->pdo->prepare('INSERT INTO portal_integrations (tenant_id, portal_slug, enabled, credentials) VALUES (?, ?, ?, ?)')
            ->execute([$tenantId, 'facebook_leads', $enabled ? 1 : 0, $credentials]);
    }

    public function testPublicPersistenceContractIsPrivateAndNoAuthContextRemains(): void
    {
        $privateMethod = new \ReflectionMethod(FacebookLeadService::class, 'processValidatedFacebookLead');
        $this->assertTrue($privateMethod->isPrivate());
        $this->assertFalse((new \ReflectionClass(FacebookLeadService::class))->hasMethod('processFacebookLeadEntry'));
        $this->assertFalse(class_exists('App\\Application\\Integration\\FacebookWebhookAuthContext'));
    }

    public function testValidSignaturePassesAuthentication(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');
        $payload = '{"entry":[{"changes":[{"value":{"leadgen_id":"lead-123"}}]}]}';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'tenant-secret');

        $result = $this->service->processAuthenticatedWebhookLead(10, $payload, $signature, 'lead-123');

        $this->assertArrayHasKey('lead_id', $result);
        $this->assertSame('alice@example.com', $result['email']);
    }

    public function testSignaturePreservesOriginalAppSecretValue(): void
    {
        $secret = '  tenant-secret  ';
        $this->seedFacebookIntegration(10, $secret);
        $payload = '{"entry":[{"changes":[{"value":{"leadgen_id":"lead-123"}}]}]}';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $result = $this->service->processAuthenticatedWebhookLead(10, $payload, $signature, 'lead-123');

        $this->assertArrayHasKey('lead_id', $result);
        $this->assertSame('alice@example.com', $result['email']);
    }

    public function testMissingSignatureHeaderIsRejected(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('X-Hub-Signature-256');

        $this->service->processAuthenticatedWebhookLead(10, '{"entry":[]}', '', 'lead-123');
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');
        $payload = '{"entry":[{"changes":[]}]}';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('assinatura');

        $this->service->processAuthenticatedWebhookLead(10, $payload, 'sha256=invalid', 'lead-123');
    }

    public function testMissingAppSecretIsRejected(): void
    {
        $this->seedFacebookIntegration(10, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app_secret');

        $this->service->processAuthenticatedWebhookLead(10, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testEmptyStringAppSecretIsRejected(): void
    {
        $this->seedFacebookIntegration(10, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app_secret');

        $this->service->processAuthenticatedWebhookLead(10, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testWhitespaceOnlyAppSecretIsRejected(): void
    {
        $this->seedFacebookIntegration(10, '   ');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app_secret');

        $this->service->processAuthenticatedWebhookLead(10, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testNullAppSecretIsRejected(): void
    {
        $this->pdo->prepare('INSERT INTO portal_integrations (tenant_id, portal_slug, enabled, credentials) VALUES (?, ?, ?, ?)')
            ->execute([10, 'facebook_leads', 1, json_encode(['app_secret' => null, 'webhook_verify_token' => 'ok'])]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app_secret');

        $this->service->processAuthenticatedWebhookLead(10, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testMissingTenantIdOnPostIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant');

        $this->service->processAuthenticatedWebhookLead(0, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testTenantDoesNotExistIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant');

        $this->service->processAuthenticatedWebhookLead(999, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testInactiveTenantIsRejected(): void
    {
        $this->seedFacebookIntegration(30, 'secret-30');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ativo');

        $this->service->processAuthenticatedWebhookLead(30, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testIntegrationDisabledIsRejected(): void
    {
        $this->seedFacebookIntegration(20, 'tenant-secret-20', false);

        try {
            $this->service->processAuthenticatedWebhookLead(20, '{"entry":[]}', 'sha256=whatever', 'lead-123');
            $this->fail('Integração desabilitada não deveria seguir para persistência.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('habilitada', strtolower($e->getMessage()));
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testSignatureFromAnotherTenantIsRejected(): void
    {
        $this->seedFacebookIntegration(10, 'secret-A');
        $this->seedFacebookIntegration(20, 'secret-B');
        $payload = '{"entry":[{"changes":[{"value":{"leadgen_id":"lead-123"}]}]}';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'secret-B');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('assinatura');

        $this->service->processAuthenticatedWebhookLead(10, $payload, $signature, 'lead-123');
    }

    public function testVerifyTokenValidAndInvalidCases(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');

        $this->assertTrue($this->service->validateVerifyToken('verify-token-10', 'verify-token-10'));
        $this->assertFalse($this->service->validateVerifyToken('wrong-token', 'verify-token-10'));
        $this->assertFalse($this->service->validateVerifyToken('anything', ''));
    }

    public function testGetVerificationRejectsMissingOrInvalidVerifyToken(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');

        $this->assertTrue($this->service->validateVerifyToken('verify-token-10', 'verify-token-10'));
        $this->assertFalse($this->service->validateVerifyToken('verify-token-10', ''));
        $this->assertFalse($this->service->validateVerifyToken('wrong-token', 'verify-token-10'));
    }

    public function testGetRejectsInactiveTenantAndDisabledIntegration(): void
    {
        $this->seedFacebookIntegration(30, 'secret-30');

        try {
            $this->service->authorizeWebhookTenant(30);
            $this->fail('Tenant inativo deveria ter sido rejeitado.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('inativo', strtolower($e->getMessage()));
        }

        $this->seedFacebookIntegration(20, 'tenant-secret-20', false);

        try {
            $this->service->authorizeWebhookTenant(20);
            $this->fail('Integração desabilitada deveria ter sido rejeitada.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('desabilitada', strtolower($e->getMessage()));
        }
    }

    public function testFailedAuthenticationDoesNotPersistLead(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');
        $payload = '{"entry":[{"changes":[{"value":{"leadgen_id":"lead-123"}]}]}';

        try {
            $this->service->processAuthenticatedWebhookLead(10, $payload, 'sha256=invalid', 'lead-123');
            $this->fail('A autenticação inválida não deveria passar.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('assinatura', strtolower($e->getMessage()));
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testProcessRequiresAuthorizedTenantContext(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant');

        $this->service->processAuthenticatedWebhookLead(999, '{"entry":[]}', 'sha256=whatever', 'lead-123');
    }

    public function testProcessUsesAuthenticatedTenant(): void
    {
        $this->seedFacebookIntegration(10, 'tenant-secret');
        $payload = '{"entry":[{"changes":[{"value":{"leadgen_id":"lead-123"}]}]}';
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'tenant-secret');


        $result = $this->service->processAuthenticatedWebhookLead(10, $payload, $signature, 'lead-123');

        $this->assertArrayHasKey('lead_id', $result);

        $row = $this->pdo->query('SELECT tenant_id, source FROM leads WHERE id = ' . (int) $result['lead_id'])->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(10, (int) $row['tenant_id']);
        $this->assertSame('facebook_leads', $row['source']);
    }
}

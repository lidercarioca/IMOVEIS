<?php

namespace Tests\Unit;

use App\Models\BillingHistoryModel;
use PDO;
use PHPUnit\Framework\TestCase;

class BillingHistoryModelEdgeCasesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE pagamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id INTEGER NOT NULL,
                valor REAL NOT NULL,
                status TEXT,
                data_pagamento TEXT,
                plano TEXT,
                stripe_reference TEXT,
                stripe_reference_type TEXT,
                stripe_event_id TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT NULL
            )'
        );
        // Simula a constraint existente em produção
        $this->pdo->exec('CREATE UNIQUE INDEX uq_pagamentos_tenant_stripe_reference ON pagamentos(tenant_id, stripe_reference)');
    }

    private function createModel(): BillingHistoryModel
    {
        return new BillingHistoryModel($this->pdo);
    }

    public function test_stripe_event_id_empty_string_is_normalized_and_allows_multiple_payments(): void
    {
        $model = $this->createModel();

        $first = $model->recordPayment(1, [
            'valor' => 10.0,
            'plano' => 'basico',
            'stripe_event_id' => '',
        ]);

        $second = $model->recordPayment(1, [
            'valor' => 12.0,
            'plano' => 'basico',
            'stripe_event_id' => '',
        ]);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('id', $second);
        $this->assertNotEquals($first['id'], $second['id']);
    }

    public function test_stripe_event_id_with_null_reference_is_deduplicated_by_event_id(): void
    {
        $model = $this->createModel();

        $first = $model->recordPayment(1, [
            'valor' => 20.0,
            'plano' => 'profissional',
            'stripe_event_id' => 'evt_edge_1',
        ]);

        $second = $model->recordPayment(1, [
            'valor' => 20.0,
            'plano' => 'profissional',
            'stripe_event_id' => 'evt_edge_1',
        ]);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals($first['id'], $second['id']);
        $this->assertArrayHasKey('existing', $second);
        $this->assertTrue($second['existing']);
    }

    public function test_same_stripe_event_id_diff_tenants_are_isolated(): void
    {
        $model = $this->createModel();

        $a = $model->recordPayment(1, [
            'valor' => 30.0,
            'plano' => 'premium',
            'stripe_event_id' => 'evt_shared',
        ]);

        $b = $model->recordPayment(2, [
            'valor' => 30.0,
            'plano' => 'premium',
            'stripe_event_id' => 'evt_shared',
        ]);

        $this->assertTrue($a['success']);
        $this->assertTrue($b['success']);
        $this->assertNotEquals($a['id'], $b['id']);
    }

    public function test_same_stripe_reference_diff_tenants_are_allowed(): void
    {
        $model = $this->createModel();

        $a = $model->recordPayment(1, [
            'valor' => 40.0,
            'plano' => 'basico',
            'stripe_reference' => 'ref_shared',
            'stripe_reference_type' => 'invoice',
        ]);

        $b = $model->recordPayment(2, [
            'valor' => 40.0,
            'plano' => 'basico',
            'stripe_reference' => 'ref_shared',
            'stripe_reference_type' => 'invoice',
        ]);

        $this->assertTrue($a['success']);
        $this->assertTrue($b['success']);
        $this->assertNotEquals($a['id'], $b['id']);
    }

    public function test_stripe_reference_empty_string_is_normalized_and_allows_multiple(): void
    {
        $model = $this->createModel();

        $a = $model->recordPayment(1, [
            'valor' => 50.0,
            'plano' => 'basico',
            'stripe_reference' => '',
        ]);

        $b = $model->recordPayment(1, [
            'valor' => 60.0,
            'plano' => 'basico',
            'stripe_reference' => '',
        ]);

        $this->assertTrue($a['success']);
        $this->assertTrue($b['success']);
        $this->assertNotEquals($a['id'], $b['id']);
    }

    public function test_manual_payments_without_stripe_fields_allowed(): void
    {
        $model = $this->createModel();

        $a = $model->recordPayment(1, [
            'valor' => 70.0,
            'plano' => 'basico',
        ]);

        $b = $model->recordPayment(1, [
            'valor' => 80.0,
            'plano' => 'basico',
        ]);

        $this->assertTrue($a['success']);
        $this->assertTrue($b['success']);
        $this->assertNotEquals($a['id'], $b['id']);
    }

    public function test_event_id_blocks_insert_even_if_reference_added_later(): void
    {
        $model = $this->createModel();

        $first = $model->recordPayment(1, [
            'valor' => 90.0,
            'plano' => 'premium',
            'stripe_event_id' => 'evt_combo'
        ]);

        $second = $model->recordPayment(1, [
            'valor' => 90.0,
            'plano' => 'premium',
            'stripe_event_id' => 'evt_combo',
            'stripe_reference' => 'ref_after'
        ]);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals($first['id'], $second['id']);
    }
}

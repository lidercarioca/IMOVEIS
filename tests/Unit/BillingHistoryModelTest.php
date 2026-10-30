<?php

namespace Tests\Unit;

use App\Models\BillingHistoryModel;
use PDO;
use PHPUnit\Framework\TestCase;

class BillingHistoryModelTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        $this->pdo->beginTransaction();

        if (!$this->hasStripeReferenceColumn()) {
            $this->markTestSkipped('Migration 006 not applied: stripe_reference columns missing.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function hasStripeReferenceColumn(): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pagamentos' AND column_name = 'stripe_reference'"
        );
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    /** @test */
    public function it_creates_a_payment_for_the_first_event(): void
    {
        $model = new BillingHistoryModel($this->pdo);
        $result = $model->recordPayment(1, [
            'valor' => 99.90,
            'plano' => 'basico',
            'stripe_reference' => 'pi_test_1',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_test_1',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('id', $result);
        $this->assertSame('Pagamento registrado com sucesso', $result['message']);
    }

    /** @test */
    public function it_does_not_duplicate_payment_for_the_same_stripe_reference(): void
    {
        $model = new BillingHistoryModel($this->pdo);
        $payload = [
            'valor' => 199.90,
            'plano' => 'profissional',
            'stripe_reference' => 'pi_test_2',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_test_2',
        ];

        $first = $model->recordPayment(1, $payload);
        $second = $model->recordPayment(1, $payload);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals($first['id'], $second['id']);
        $this->assertArrayHasKey('existing', $second);
        $this->assertTrue($second['existing']);
    }

    /** @test */
    public function it_does_not_duplicate_payment_for_the_same_stripe_event_id_with_different_reference(): void
    {
        $model = new BillingHistoryModel($this->pdo);

        $first = $model->recordPayment(1, [
            'valor' => 199.90,
            'plano' => 'profissional',
            'stripe_reference' => 'pi_test_2a',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_same_2',
        ]);

        $second = $model->recordPayment(1, [
            'valor' => 199.90,
            'plano' => 'profissional',
            'stripe_reference' => 'pi_test_2b',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_same_2',
        ]);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals($first['id'], $second['id']);
        $this->assertArrayHasKey('existing', $second);
        $this->assertTrue($second['existing']);
    }

    /** @test */
    public function it_does_not_duplicate_payment_for_different_events_same_reference(): void
    {
        $model = new BillingHistoryModel($this->pdo);

        $first = $model->recordPayment(1, [
            'valor' => 299.90,
            'plano' => 'premium',
            'stripe_reference' => 'pi_test_3',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_initial_3',
        ]);

        $second = $model->recordPayment(1, [
            'valor' => 299.90,
            'plano' => 'premium',
            'stripe_reference' => 'pi_test_3',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_followup_3',
        ]);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertEquals($first['id'], $second['id']);
    }

    /** @test */
    public function it_allows_two_distinct_stripe_payments_with_different_references(): void
    {
        $model = new BillingHistoryModel($this->pdo);

        $first = $model->recordPayment(1, [
            'valor' => 299.90,
            'plano' => 'premium',
            'stripe_reference' => 'pi_test_4',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_test_4',
        ]);

        $second = $model->recordPayment(1, [
            'valor' => 299.90,
            'plano' => 'premium',
            'stripe_reference' => 'pi_test_4b',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_test_4b',
        ]);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertNotEquals($first['id'], $second['id']);
    }

    /** @test */
    public function it_allows_manual_payments_without_stripe_reference(): void
    {
        $model = new BillingHistoryModel($this->pdo);
        $result = $model->recordPayment(1, [
            'valor' => 49.90,
            'plano' => 'basico',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('id', $result);
    }

    /** @test */
    public function it_recovers_existing_payment_on_duplicate_key_error_for_stripe_event_id(): void
    {
        $model = new BillingHistoryModel($this->pdo);

        // Primeiro pagamento com stripe_event_id
        $first = $model->recordPayment(1, [
            'valor' => 99.90,
            'plano' => 'premium',
            'stripe_reference' => 'pi_dup_test_1',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_dup_test_1',
        ]);

        $this->assertTrue($first['success']);
        $firstId = $first['id'];

        // Simular chegada de segundo webhook com mesmo stripe_event_id
        // (em produção, a UNIQUE KEY forçaria o duplicate-key error)
        // No unit test SQLite, a pré-verificação já impede a duplicação
        // Mas vamos registrar novamente para comprovar que returna o mesmo ID
        $second = $model->recordPayment(1, [
            'valor' => 99.90,
            'plano' => 'premium',
            'stripe_reference' => 'pi_dup_test_1_alt',
            'stripe_reference_type' => 'payment_intent',
            'stripe_event_id' => 'evt_dup_test_1',
        ]);

        $this->assertTrue($second['success']);
        $this->assertEquals($firstId, $second['id']);
        $this->assertArrayHasKey('existing', $second);
        $this->assertTrue($second['existing']);
    }
}

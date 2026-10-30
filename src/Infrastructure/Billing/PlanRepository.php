<?php

namespace App\Infrastructure\Billing;

use PDO;

class PlanRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, array{code:string,name:string,stripe_price_id:?string}>
     */
    public function getPlansByCode(): array
    {
        $stmt = $this->pdo->prepare('SELECT code, name, stripe_price_id FROM plans');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $plansByCode = [];
        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $plansByCode[$code] = [
                'code' => $code,
                'name' => (string) ($row['name'] ?? ''),
                'stripe_price_id' => $row['stripe_price_id'] ?? null,
            ];
        }

        return $plansByCode;
    }

    public function updatePriceIdByCode(string $code, string $priceId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE plans SET stripe_price_id = ? WHERE code = ?');
        $stmt->execute([$priceId, $code]);

        return $stmt->rowCount() > 0;
    }
}

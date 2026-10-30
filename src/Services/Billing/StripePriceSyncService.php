<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\PlanRepository;
use PDO;
use RuntimeException;

class StripePriceSyncService
{
    private PlanRepository $planRepository;
    private PDO $pdo;

    public function __construct(PDO $pdo, ?PlanRepository $planRepository = null)
    {
        $this->pdo = $pdo;
        $this->planRepository = $planRepository ?? new PlanRepository($pdo);
    }

    public function syncStripePriceIds(string $secretKey): array
    {
        $plansByCode = $this->planRepository->getPlansByCode();
        if ($plansByCode === []) {
            return [
                'success' => true,
                'updated_count' => 0,
                'updated' => [],
                'skipped' => [],
                'unmatched' => [],
                'message' => 'Nenhum plano encontrado para sincronização.',
            ];
        }

        $prices = $this->fetchStripePrices($secretKey);
        if ($prices === null) {
            throw new RuntimeException('Erro ao buscar preços no Stripe.');
        }

        $updated = [];
        $skipped = [];
        $unmatched = [];

        foreach ($prices as $priceInfo) {
            $matchedPlanCode = $this->matchStripePriceToPlanCode($priceInfo, $plansByCode);
            if (!$matchedPlanCode) {
                $unmatched[] = $priceInfo;
                continue;
            }

            $plan = $plansByCode[$matchedPlanCode];
            if (!empty($plan['stripe_price_id'])) {
                $skipped[] = [
                    'plan_code' => $matchedPlanCode,
                    'existing_price_id' => $plan['stripe_price_id'],
                    'stripe_price_id' => $priceInfo['price_id'],
                ];
                continue;
            }

            try {
                if ($this->planRepository->updatePriceIdByCode($matchedPlanCode, $priceInfo['price_id'])) {
                    $updated[] = [
                        'plan_code' => $matchedPlanCode,
                        'stripe_price_id' => $priceInfo['price_id'],
                    ];
                }
            } catch (\Throwable $e) {
                $skipped[] = [
                    'plan_code' => $matchedPlanCode,
                    'reason' => 'Falha ao atualizar: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'updated_count' => count($updated),
            'updated' => $updated,
            'skipped' => $skipped,
            'unmatched' => $unmatched,
            'message' => 'Sincronização concluída.',
        ];
    }

    public function fetchStripePrices(string $secretKey): ?array
    {
        $endpoint = 'https://api.stripe.com/v1/prices';
        $params = [
            'limit' => 100,
            'active' => 'true',
            'expand[]' => 'data.product',
        ];

        $prices = [];
        $startingAfter = null;

        while (true) {
            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $ch = curl_init($endpoint . '?' . http_build_query($params));
            curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ':');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                return null;
            }

            $result = json_decode($response, true);
            if (empty($result['data']) || !is_array($result['data'])) {
                return null;
            }

            foreach ($result['data'] as $price) {
                if (empty($price['id']) || ($price['type'] ?? '') !== 'recurring') {
                    continue;
                }

                $interval = strtolower((string) ($price['recurring']['interval'] ?? ''));
                if ($interval !== 'month') {
                    continue;
                }

                $productName = '';
                $productMetadata = [];
                if (!empty($price['product']) && is_array($price['product'])) {
                    $productName = (string) ($price['product']['name'] ?? '');
                    $productMetadata = $price['product']['metadata'] ?? [];
                }

                $prices[] = [
                    'price_id' => (string) $price['id'],
                    'nickname' => (string) ($price['nickname'] ?? ''),
                    'product_name' => $productName,
                    'metadata' => $price['metadata'] ?? [],
                    'product_metadata' => $productMetadata,
                ];
            }

            if (empty($result['has_more'])) {
                break;
            }

            $lastPrice = end($result['data']);
            $startingAfter = $lastPrice['id'] ?? null;
            if (!$startingAfter) {
                break;
            }
        }

        return $prices;
    }

    public function matchStripePriceToPlanCode(array $priceInfo, array $plansByCode): ?string
    {
        $metadataPlanCode = trim(strtolower((string) ($priceInfo['metadata']['plan_code'] ?? '')));
        if ($metadataPlanCode !== '' && isset($plansByCode[$metadataPlanCode])) {
            return $metadataPlanCode;
        }

        $productMetadataPlanCode = trim(strtolower((string) ($priceInfo['product_metadata']['plan_code'] ?? '')));
        if ($productMetadataPlanCode !== '' && isset($plansByCode[$productMetadataPlanCode])) {
            return $productMetadataPlanCode;
        }

        $normalizedNickname = self::normalizeString((string) ($priceInfo['nickname'] ?? ''));
        $normalizedProductName = self::normalizeString((string) ($priceInfo['product_name'] ?? ''));

        foreach ($plansByCode as $code => $plan) {
            $normalizedCode = self::normalizeString((string) $code);
            $normalizedPlanName = self::normalizeString((string) ($plan['name'] ?? ''));

            if ($normalizedCode !== '' && (
                stripos($normalizedNickname, $normalizedCode) !== false ||
                stripos($normalizedProductName, $normalizedCode) !== false
            )) {
                return $code;
            }

            if ($normalizedPlanName !== '' && (
                stripos($normalizedProductName, $normalizedPlanName) !== false ||
                stripos($normalizedNickname, $normalizedPlanName) !== false
            )) {
                return $code;
            }
        }

        return null;
    }

    private static function normalizeString(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '', $value);

        return $value ?? '';
    }
}

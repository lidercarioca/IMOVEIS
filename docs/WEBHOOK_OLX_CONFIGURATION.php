<?php

/**
 * WEBHOOK OLX - CONFIGURAÇÃO DE SEGURANÇA
 * 
 * Etapas para configurar webhook OLX seguro após patch.
 */

// ============================================================
// PASSO 1: Adicionar integração OLX em portal_integrations
// ============================================================

// SQL para inserir/atualizar integração OLX

$olxIntegrationSQL = <<<SQL
INSERT INTO portal_integrations (tenant_id, portal_slug, enabled, credentials, created_at, updated_at)
VALUES (
    1,                                                          -- tenant_id
    'olx',                                                      -- portal_slug
    1,                                                          -- enabled
    '{"webhook_secret":"YOUR_OLX_WEBHOOK_SECRET_HERE"}',      -- credentials (gerar secret forte)
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    enabled = 1,
    credentials = '{"webhook_secret":"YOUR_OLX_WEBHOOK_SECRET_HERE"}',
    updated_at = NOW();
SQL;

// ============================================================
// PASSO 2: Gerar Secret Webhook Forte
// ============================================================

// PHP CLI:
// php -r "echo bin2hex(random_bytes(32));"

// Resultado: algo como
// a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6

// ============================================================
// PASSO 3: Configurar Webhook na Dashboard OLX
// ============================================================

// Webhook URL: https://seu-dominio.com/api/v1/public/olxLeadReceiver.php

// Webhook Secret: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6

// Headers que OLX deve enviar:
// - X-Webhook-Signature: (gerado automaticamente pela OLX)
// - X-Webhook-Timestamp: (Unix timestamp em segundos)

// ============================================================
// PASSO 4: Teste Manual do Webhook
// ============================================================

$testPayload = json_encode([
    'property_id' => 25,
    'name' => 'Test Lead',
    'email' => 'test@example.com',
    'phone' => '11999999999',
    'message' => 'Test message from OLX'
]);

$secret = 'YOUR_OLX_WEBHOOK_SECRET_HERE';
$timestamp = time();
$signature = hash_hmac('sha256', $timestamp . '.' . $testPayload, $secret);

// Usar curl para testar
$curlCommand = <<<CURL
curl -X POST https://seu-dominio.com/api/v1/public/olxLeadReceiver.php \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: $signature" \
  -H "X-Webhook-Timestamp: $timestamp" \
  -d '$testPayload'
CURL;

echo "Comando CURL para testar:\n$curlCommand\n";

// Respostas esperadas:
// 201 Created: Lead gravado com sucesso
// 401 Unauthorized: Assinatura inválida ou secret não configurado
// 403 Forbidden: Property não pertence ao tenant
// 404 Not Found: Property não existe
// 400 Bad Request: Payload inválido

// ============================================================
// PASSO 5: Verificar Logs de Webhook
// ============================================================

// Consultar webhook_events para verificar se foi processado:
$checkSQL = <<<SQL
SELECT id, tenant_id, event_type, entity_id, data, created_at 
FROM webhook_events 
WHERE entity_type = 'lead' AND event_type = 'lead_received'
ORDER BY created_at DESC 
LIMIT 10;
SQL;

// ============================================================
// PASSO 6: Configurar por Tenant (Multi-tenant)
// ============================================================

// Se tiver múltiplos tenants, cada um pode ter seu próprio secret:

$multiTenantSQL = <<<SQL
INSERT INTO portal_integrations (tenant_id, portal_slug, enabled, credentials, created_at, updated_at)
VALUES 
    (1, 'olx', 1, '{"webhook_secret":"SECRET_TENANT_1"}', NOW(), NOW()),
    (2, 'olx', 1, '{"webhook_secret":"SECRET_TENANT_2"}', NOW(), NOW()),
    (3, 'olx', 1, '{"webhook_secret":"SECRET_TENANT_3"}', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    enabled = 1,
    updated_at = NOW();
SQL;

// ============================================================
// NOTAS DE SEGURANÇA
// ============================================================

/*

1. NUNCA compartilhar webhook_secret
   - Armazenado em banco de dados
   - Acessível apenas por administradores
   - Regenerar se comprometido

2. Timestamp validation
   - Aceita ±300 segundos de desvio
   - Previne replay attacks antigos

3. Replay protection
   - webhook_events rastreia eventos já processados
   - Hash SHA256 do payload
   - Idempotente: repetições retornam sucesso silencioso

4. Multi-tenant isolation
   - Cada webhook autenticado se vincula ao seu tenant
   - Property_id validado contra tenant (WHERE tenant_id = ?)
   - Nenhuma cross-tenant gravação possível

5. Monitoramento
   - Ativar logs em webhook_events
   - Alertar em 401/403/404
   - Investigar mudanças em timestamps ou assinaturas

*/

<?php
/**
 * Property Helpers - Funções reutilizáveis para processamento de propriedades
 *
 * Responsável por:
 * - Sanitização de valores (XSS prevention)
 * - Formatação de imagens
 * - Transformação de dados brutos do BD para formato final
 * - Agrupamento de propriedades com múltiplas imagens
 */

/**
 * Sanitiza valor para evitar XSS (aplicar htmlspecialchars)
 *
 * @param mixed $value Valor a sanitizar
 * @return mixed Valor sanitizado ou original se não é string
 */
function sanitizePropertyValue($value) {
    if (is_string($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $value;
}

/**
 * Formata caminho de imagem da propriedade
 *
 * @param int $propertyId ID da propriedade
 * @param string|null $imageUrl Nome/caminho da imagem no banco
 * @return string|null Caminho relativo completo ou null se vazio
 */
function buildImagePath($propertyId, $imageUrl) {
    if (!$imageUrl) {
        return null;
    }
    return "assets/imagens/{$propertyId}/" . $imageUrl;
}

/**
 * Transforma linha bruta do BD em objeto de propriedade formatado
 *
 * Responsabilidades:
 * - Decodificar JSON (features)
 * - Normalizar status
 * - Type casting correto
 * - Sanitização de strings
 * - Inicializar imagens
 *
 * @param array $row Linha do resultado do banco (SELECT p.*, pi.image_url)
 * @return array Propriedade com dados sanitizados e formatados
 */
function formatPropertyData($row) {
    $propertyId = (int)$row['id'];
    
    // Decodificar features (JSON)
    $features = json_decode($row['features'] ?? '[]', true);
    if (!is_array($features)) {
        $features = [];
    }
    
    // Inicializar imagens
    $images = [];
    $imagePath = buildImagePath($propertyId, $row['image_url'] ?? null);
    if ($imagePath) {
        $images[] = $imagePath;
    }
    
    // Normalizar status
    $status = '';
    if (isset($row['status'])) {
        $status = ($row['status'] === 'active' || $row['status'] === 'activ') 
            ? 'Ativo' 
            : sanitizePropertyValue($row['status']);
    }
    
    return [
        'id'              => $propertyId,
        'title'           => sanitizePropertyValue($row['title']),
        'description'     => sanitizePropertyValue($row['description']),
        'location'        => sanitizePropertyValue($row['location']),
        'neighborhood'    => sanitizePropertyValue($row['neighborhood'] ?? ''),
        'city'            => sanitizePropertyValue($row['city'] ?? ''),
        'state'           => sanitizePropertyValue($row['state'] ?? ''),
        'zip'             => sanitizePropertyValue($row['zip'] ?? ''),
        'price'           => (float)$row['price'],
        'type'            => sanitizePropertyValue($row['type']),
        'transactionType' => sanitizePropertyValue($row['transactionType']),
        'images'          => $images,
        'features'        => array_map('sanitizePropertyValue', $features),
        'bedrooms'        => (int)$row['bedrooms'],
        'bathrooms'       => (int)$row['bathrooms'],
        'garage'          => sanitizePropertyValue($row['garage'] ?? ''),
        'area'            => (float)$row['area'],
        'yearBuilt'       => (int)$row['yearBuilt'],
        'status'          => $status,
        'condominium'     => isset($row['condominium']) ? (float)$row['condominium'] : null,
        'iptu'            => isset($row['iptu']) ? (float)$row['iptu'] : null,
        'suites'          => isset($row['suites']) ? intval($row['suites']) : null,
        'assigned_user_id' => isset($row['assigned_user_id']) ? (int)$row['assigned_user_id'] : null,
    ];
}

/**
 * Agrupa propriedades e suas imagens em uma lista única
 *
 * Necessário porque LEFT JOIN gera múltiplas linhas por propriedade quando tem múltiplas imagens.
 * Esta função:
 * - Verifica se propriedade já existe no mapa
 * - Se sim: apenas adiciona a nova imagem
 * - Se não: cria entrada formatada completa
 *
 * @param array &$propertyMap Mapa de ID propriedade → dados formatados (passado por referência)
 * @param array $row Linha do resultado do banco
 * @return bool true se foi primeira vez (nova propriedade), false se foi adição de imagem
 */
function groupPropertiesWithImages(&$propertyMap, $row) {
    $propertyId = $row['id'];
    
    // Se propriedade já foi processada, apenas adiciona nova imagem
    if (isset($propertyMap[$propertyId])) {
        $imagePath = buildImagePath($propertyId, $row['image_url'] ?? null);
        if ($imagePath) {
            $propertyMap[$propertyId]['images'][] = $imagePath;
        }
        return false; // Já foi processada
    }
    
    // Primeira vez: criar entrada formatada
    $propertyMap[$propertyId] = formatPropertyData($row);
    return true; // Nova propriedade
}

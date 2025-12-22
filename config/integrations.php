<?php
/**
 * Configurações para integrações com portais (ex.: ZAP, OLX)
 * Adicione credenciais, endpoints e mapeamentos de campos aqui.
 */
return [
    // Base URL do site para gerar URLs absolutas de imagens. Se vazio, tentaremos inferir via SERVER vars.
        // Base URL usada para gerar URLs absolutas de imagens e links de detalhes.
        // Temporariamente definimos localhost para validação local; em produção deve apontar para https://seu-dominio.com
        'base_url' => getenv('SITE_URL') ?: 'http://localhost',
    // Opções globais para export
    'defaults' => [
        // Se true, exclui imóveis sem imagens
        'require_images' => false,
        // Campos mínimos obrigatórios no dado local antes de exportar
        'required_fields' => ['id', 'title', 'price', 'url'],
        // Se true, o price será enviado em centavos (inteiro)
        'price_in_cents' => false,
    ],

    // Exemplo de configuração para ZAP
    'zap' => [
        'enabled' => false,
        'name' => 'ZAP',
        'endpoint' => 'https://api.zap.com.br/receive', // placeholder
        'credentials' => [
            'api_key' => '',
            'username' => '',
            'password' => '',
        ],
        // Mapear campos internos => campos do portal
        'field_map' => [
            'id' => 'id',
            'title' => 'title',
            'description' => 'description',
            'price' => 'price',
            'city' => 'city',
            'state' => 'state',
            'neighborhood' => 'neighborhood',
            'bedrooms' => 'bedrooms',
            'bathrooms' => 'bathrooms',
            'area' => 'area',
            'images' => 'images',
            // URL público da página do anúncio (Portal pode esperar link para o detalhe)
            'url' => 'url',
        ],
        'options' => [
            'max_images' => 10,
        ],
    ],

    // Exemplo para OLX
    'olx' => [
        'enabled' => false,
        'name' => 'OLX',
        'endpoint' => 'https://api.olx.com/receive',
        'credentials' => [
            'token' => '',
        ],
        // Exemplo de mapeamento mínimo (ajuste conforme especificação do portal)
        'field_map' => [
            'id' => 'id',
            'title' => 'title',
            'description' => 'description',
            'price' => 'price',
            'city' => 'city',
            'state' => 'state',
            'neighborhood' => 'neighborhood',
            'bedrooms' => 'bedrooms',
            'bathrooms' => 'bathrooms',
            'area' => 'area',
            'images' => 'images',
            'url' => 'url',
            // campos adicionais podem ser mapeados aqui
            // 'zipcode' => 'zip',
            // 'latitude' => 'lat',
            // 'longitude' => 'lng',
        ],
        'options' => [
            'max_images' => 10,
        ],
    ],
];

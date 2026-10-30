<?php
/**
 * API Routes - api/v1/routes.php
 * Centraliza o mapeamento de endpoints da API
 */

return [
    'GET' => [
        'properties' => '../../getProperties.php',
        'properties/:id' => '../../getPropertyById.php',
        'leads' => '../../getLeads.php',
        'notifications' => '../../getNotifications.php',
        'messages' => '../../getMessages.php',
        'agendamentos' => '../../getAgendamentos.php',
        'financeiro' => '../../getFinanceiro.php',
        'reports' => '../../getReports.php',
        'banners' => '../../getBanners.php',
        'settings' => '../../getCompanySettings.php',
    ],
    'POST' => [
        'properties' => '../../addProperty.php',
        'properties/update' => '../../updateProperty.php',
        'properties/delete' => '../../deleteProperty.php',
        'properties/image' => '../../uploadImagem.php',
        'leads' => '../../addLead.php',
        'leads/update' => '../../updateLead.php',
        'leads/delete' => '../../deleteLead.php',
        'agendamentos' => '../../addAgendamento.php',
        'agendamentos/update' => '../../updateAgendamento.php',
        'agendamentos/delete' => '../../deleteAgendamento.php',
        'financeiro' => '../../addFinanceiro.php',
        'financeiro/update' => '../../updateFinanceiro.php',
        'financeiro/delete' => '../../deleteFinanceiro.php',
        'notifications/mark-read' => '../../markNotificationRead.php',
        'messages/mark-read' => '../../markMessageRead.php',
    ],
    'DELETE' => [
        'properties/:id' => '../../deleteProperty.php',
        'leads/:id' => '../../deleteLead.php',
        'agendamentos/:id' => '../../deleteAgendamento.php',
        'financeiro/:id' => '../../deleteFinanceiro.php',
    ]
];

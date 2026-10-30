<?php
/**
 * API v1 - Índice de Estrutura RESTful
 * 
 * Estrutura reorganizada por recurso/entidade
 * 
 * MAPEAMENTO DE ENDPOINTS:
 */

return [
    'properties' => [
        'GET' => [
            'list' => 'v1/properties/getProperties.php',
            'getById/:id' => 'v1/properties/getPropertyById.php',
            'simple' => 'v1/properties/getPropertiesSimple.php',
            'categories' => 'v1/properties/getPropertiesCategories.php',
            'images/:id' => 'v1/properties/getPropertyImages.php',
        ],
        'POST' => [
            'create' => 'v1/properties/addProperty.php',
            'update/:id' => 'v1/properties/updateProperty.php',
            'upload-image' => 'v1/properties/uploadImagem.php',
        ],
        'DELETE' => [
            'delete/:id' => 'v1/properties/deleteProperty.php',
            'delete-image/:id' => 'v1/properties/deletePropertyImage.php',
        ]
    ],
    
    'leads' => [
        'GET' => [
            'list' => 'v1/leads/getLeads.php',
        ],
        'POST' => [
            'create' => 'v1/leads/addLead.php',
            'update/:id' => 'v1/leads/updateLead.php',
            'assign' => 'v1/leads/assignLeadToUser.php',
        ],
        'DELETE' => [
            'delete/:id' => 'v1/leads/deleteLead.php',
        ]
    ],
    
    'agendamentos' => [
        'GET' => [
            'list' => 'v1/agendamentos/getAgendamentos.php',
            'proximos' => 'v1/agendamentos/getAgendamentosProximos.php',
        ],
        'POST' => [
            'create' => 'v1/agendamentos/addAgendamento.php',
            'update/:id' => 'v1/agendamentos/updateAgendamento.php',
        ],
        'DELETE' => [
            'delete/:id' => 'v1/agendamentos/deleteAgendamento.php',
        ]
    ],
    
    'banners' => [
        'GET' => [
            'list' => 'v1/banners/getBanners.php',
        ],
        'POST' => [
            'create' => 'v1/banners/addBanner.php',
            'update/:id' => 'v1/banners/updateBanner.php',
            'upload-image' => 'v1/banners/uploadBannerImage.php',
        ],
        'DELETE' => [
            'delete/:id' => 'v1/banners/deleteBanner.php',
        ]
    ],
    
    'financeiro' => [
        'GET' => [
            'list' => 'v1/financeiro/getFinanceiro.php',
        ],
        'POST' => [
            'create' => 'v1/financeiro/addFinanceiro.php',
            'update/:id' => 'v1/financeiro/updateFinanceiro.php',
        ],
        'DELETE' => [
            'delete/:id' => 'v1/financeiro/deleteFinanceiro.php',
        ]
    ],
    
    'notifications' => [
        'GET' => [
            'list' => 'v1/notifications/getNotifications.php',
            'backup' => 'v1/notifications/getBackupNotifications.php',
        ],
        'POST' => [
            'mark-read' => 'v1/notifications/markNotificationRead.php',
        ],
        'DELETE' => [
            'delete/:id' => 'v1/notifications/deleteNotification.php',
        ]
    ],
    
    'messages' => [
        'GET' => [
            'list' => 'v1/messages/getMessages.php',
        ],
        'POST' => [
            'mark-read' => 'v1/messages/markMessageRead.php',
        ]
    ],
    
    'settings' => [
        'GET' => [
            'company' => 'v1/settings/getCompanySettings.php',
        ],
        'POST' => [
            'company' => 'v1/settings/saveCompanySettings.php',
        ]
    ],
    
    'reports' => [
        'GET' => [
            'dashboard' => 'v1/reports/getDashboardStats.php',
            'list' => 'v1/reports/getReports.php',
            'pending-sales' => 'v1/reports/getPendingSales.php',
            'visit-stats' => 'v1/reports/getVisitStats.php',
        ]
    ],
    
    'utils' => [
        'GET' => [
            'export-properties' => 'v1/utils/exportProperties.php',
            'contact-info' => 'v1/utils/getContactInfo.php',
            'formulario-imovel' => 'v1/utils/getFormularioImovel.php',
            'hero-images' => 'v1/utils/getHeroImages.php',
        ],
        'POST' => [
            'upload-logo' => 'v1/utils/uploadLogo.php',
        ]
    ],
    
    'auth' => [
        'GET' => [
            'user-role' => 'v1/auth/checkUserRole.php',
            'unread-emails' => 'v1/auth/getUnreadEmails.php',
            'unread-gmail' => 'v1/auth/getUnreadGmailEmails.php',
            'backups' => 'v1/auth/listBackups.php',
        ],
        'POST' => [
            'check-users-table' => 'v1/auth/checkUsersTable.php',
            'delete-user' => 'v1/auth/deleteUser.php',
            'password-reset' => 'v1/auth/requestPasswordReset.php',
            'email-notification' => 'v1/auth/createEmailNotification.php',
            'gmail-notification' => 'v1/auth/createGmailNotification.php',
            'export-portal' => 'v1/auth/runPortalExport.php',
        ],
        'DELETE' => [
            'backup' => 'v1/auth/deleteBackup.php',
        ]
    ],
    
    'tests' => [
        'GET' => [
            'test-banners' => 'v1/tests/test_banners_db.php',
            'test-commission' => 'v1/tests/test_commission_complete.php',
            'test-commission-field' => 'v1/tests/test_commission_field.php',
            'test-financial' => 'v1/tests/test_financial_report.php',
            'test-financial-details' => 'v1/tests/test_financial_report_details.php',
        ]
    ],
];

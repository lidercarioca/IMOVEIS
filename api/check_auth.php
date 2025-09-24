<?php
require_once '../auth.php';

function checkApiAuth() {
    checkAuth();
    
    // APIs públicas que não precisam de verificação
    $publicApis = [
        'getProperties.php',
        'getPropertyById.php',
        'addLead.php',
        'getHeroImages.php',
        'getBanners.php',
        'getCompanySettings.php'
    ];
    
    $currentFile = basename($_SERVER['PHP_SELF']);
    
    // Se não for uma API pública, verifica se é admin para certas operações
    if (!in_array($currentFile, $publicApis)) {
        $restrictedApisForUsers = [
            'addBanner.php',
            'deleteBanner.php',
            'uploadBannerImage.php',
            'uploadLogo.php',
            'saveCompanySettings.php',
            'users.php'
        ];
        
        if (in_array($currentFile, $restrictedApisForUsers) && !isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
    }
}
?>

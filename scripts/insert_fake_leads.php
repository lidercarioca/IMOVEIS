<?php
/**
 * Script para inserir 50 leads fake no sistema
 * Executable: php scripts/insert_fake_leads.php
 */

require_once __DIR__ . '/../config/database.php';

// Dados aleatórios para gerar leads fake
$firstNames = ['Maria', 'João', 'Ana', 'Carlos', 'Paula', 'Pedro', 'Fernanda', 'Marcos', 'Juliana', 'Roberto'];
$lastNames = ['Silva', 'Santos', 'Oliveira', 'Ferreira', 'Costa', 'Sousa', 'Almeida', 'Gomes', 'Martins', 'Pereira'];
$domains = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'empresa.com.br', 'email.com', 'mail.com'];

$messages = [
    'Tenho interesse neste imóvel. Gostaria de agendar uma visita para o próximo final de semana.',
    'Procuro uma propriedade neste bairro. Este imóvel está disponível para visita?',
    'Gostei muito da descrição do imóvel. Poderia enviar mais fotos e informações?',
    'Quais são as condições de financiamento? Gostaria de conhecer as opções.',
    'Tenho interesse em alugar este imóvel. Qual é o valor do aluguel e as condições?',
    'Este imóvel atende aos meus requisitos. Quando posso agendar uma visita?',
    'Gostaria de mais detalhes sobre a localização e infraestrutura do bairro.',
    'Tenho interesse em compra imediata. Qual é o melhor preço oferecido?',
    'Poderia enviar o contrato e documentação para análise?',
    'Estou interessado em agendar uma visita este mês. Qual horário melhor?'
];

$statuses = ['new', 'contacted', 'negotiating', 'closed'];
$sources = ['site', 'olx', 'imobiliario', 'indicacao', 'telefone', 'rede_social'];

$areaStates = [
    'São Paulo', 'Rio de Janeiro', 'Minas Gerais', 'Bahia', 'Rio Grande do Sul',
    'Paraná', 'Ceará', 'Pernambuco', 'Santa Catarina', 'Brasília'
];

echo "Iniciando inserção de 50 leads fake...\n";

try {
    $inserted = 0;
    
    for ($i = 1; $i <= 50; $i++) {
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $name = "$firstName $lastName";
        
        // Email único baseado no nome + número
        $email = strtolower(str_replace(' ', '.', $name)) . '+' . $i . '@' . $domains[array_rand($domains)];
        
        // Telefone fake mas realista
        $areaCode = sprintf("%02d", rand(11, 99));
        $phone = "($areaCode) 9" . rand(6000, 9999) . "-" . rand(1000, 9999);
        
        $message = $messages[array_rand($messages)];
        $status = $statuses[array_rand($statuses)];
        $source = $sources[array_rand($sources)];
        
        // Data aleatória nos últimos 30 dias
        $daysAgo = rand(1, 30);
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days") + rand(0, 86400));
        $updatedAt = $createdAt;
        
        // Insere o lead
        $stmt = $pdo->prepare('
            INSERT INTO leads (name, email, phone, property_id, message, status, created_at, updated_at, source, notes)
            VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, NULL)
        ');
        
        $stmt->execute([$name, $email, $phone, $message, $status, $createdAt, $updatedAt, $source]);
        $inserted++;
        
        echo "✓ Lead $i inserido: $name ($email)\n";
    }
    
    echo "\n✅ Sucesso! $inserted leads inseridos com sucesso!\n";
    
    // Mostra estatísticas
    $total = $pdo->query('SELECT COUNT(*) as count FROM leads')->fetch();
    echo "\n📊 Total de leads no sistema: " . $total['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao inserir leads: " . $e->getMessage() . "\n";
    exit(1);
}

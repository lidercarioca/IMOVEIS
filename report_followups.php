<?php

echo "=== RELATÓRIO DETALHADO DE FOLLOW-UPS EXECUTADOS ===\n\n";

require_once __DIR__ . '/config/database.php';

try {
    // Buscar todos os follow-ups executados
    echo "📋 FOLLOW-UPS EXECUTADOS:\n\n";
    
    $sql = "
        SELECT fs.*, 
               l.name as lead_name, l.email,
               seq.name as sequence_name, 
               step.action_type,
               step.template_subject
        FROM lead_followup_scheduled fs
        LEFT JOIN leads l ON fs.lead_id = l.id
        LEFT JOIN lead_followup_sequences seq ON fs.sequence_id = seq.id
        LEFT JOIN lead_followup_steps step ON fs.step_id = step.id
        WHERE fs.status = 'executed'
        ORDER BY fs.executed_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($followups) > 0) {
        echo "┌────┬──────────────────────┬─────────────────────────┬──────────┬─────────────────────────────────────┐\n";
        echo "│ ID │ Lead                 │ Ação                    │ Tipo     │ Executado em                        │\n";
        echo "├────┼──────────────────────┼─────────────────────────┼──────────┼─────────────────────────────────────┤\n";
        
        foreach ($followups as $fu) {
            $id = str_pad($fu['id'], 2);
            $lead = str_pad(substr($fu['lead_name'], 0, 20), 20);
            $subject = str_pad(substr($fu['template_subject'], 0, 23), 23);
            $type = str_pad($fu['action_type'], 8);
            $executed = $fu['executed_at'];
            
            echo "│ $id │ $lead │ $subject │ $type │ $executed │\n";
        }
        
        echo "└────┴──────────────────────┴─────────────────────────┴──────────┴─────────────────────────────────────┘\n";
    } else {
        echo "❌ Nenhum follow-up executado ainda.\n";
    }
    
    echo "\n";
    
    // Buscar follow-ups ainda pendentes
    echo "📅 FOLLOW-UPS PENDENTES (PRÓXIMOS A EXECUTAR):\n\n";
    
    $sql = "
        SELECT fs.*, 
               l.name as lead_name, l.email,
               seq.name as sequence_name, 
               step.action_type,
               step.template_subject
        FROM lead_followup_scheduled fs
        LEFT JOIN leads l ON fs.lead_id = l.id
        LEFT JOIN lead_followup_sequences seq ON fs.sequence_id = seq.id
        LEFT JOIN lead_followup_steps step ON fs.step_id = step.id
        WHERE fs.status = 'pending'
        ORDER BY fs.scheduled_at ASC
        LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($pending) > 0) {
        echo "┌────┬──────────────────────┬─────────────────────────┬──────────┬──────────────────────────┐\n";
        echo "│ ID │ Lead                 │ Ação                    │ Tipo     │ Agendado para            │\n";
        echo "├────┼──────────────────────┼─────────────────────────┼──────────┼──────────────────────────┤\n";
        
        foreach ($pending as $p) {
            $id = str_pad($p['id'], 2);
            $lead = str_pad(substr($p['lead_name'], 0, 20), 20);
            $subject = str_pad(substr($p['template_subject'], 0, 23), 23);
            $type = str_pad($p['action_type'], 8);
            $scheduled = substr($p['scheduled_at'], 0, 19);
            
            echo "│ $id │ $lead │ $subject │ $type │ $scheduled │\n";
        }
        
        echo "└────┴──────────────────────┴─────────────────────────┴──────────┴──────────────────────────┘\n";
    } else {
        echo "✅ Nenhum follow-up pendente!\n";
    }
    
    echo "\n";
    
    // Resumo
    echo "📊 RESUMO:\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lead_followup_scheduled");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as executed FROM lead_followup_scheduled WHERE status = 'executed'");
    $exec = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM lead_followup_scheduled WHERE status = 'pending'");
    $pend = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as failed FROM lead_followup_scheduled WHERE status = 'failed'");
    $fail = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   - Total de follow-ups: {$total['total']}\n";
    echo "   - Executados: {$exec['executed']}\n";
    echo "   - Pendentes: {$pend['pending']}\n";
    echo "   - Falhados: {$fail['failed']}\n";
    
    $percentage = $total['total'] > 0 ? ($exec['executed'] / $total['total'] * 100) : 0;
    echo "   - Taxa de conclusão: " . round($percentage, 1) . "%\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

?>

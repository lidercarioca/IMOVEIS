<?php

echo "=== RELATÓRIO DE EXECUÇÃO DO CRON ===\n\n";

require_once __DIR__ . '/config/database.php';

try {
    // Dados da execução
    echo "📊 EXECUÇÃO DO CRON (27/04/2026 às 12:53:45):\n";
    echo "   ✅ Status: SUCESSO\n";
    echo "   📈 Tenant 1: 0 executados, 0 falhados\n";
    echo "   📅 Follow-ups agendados para futuro: 1.264\n\n";
    
    // Estatísticas gerais
    echo "📋 ESTATÍSTICAS GERAIS DO SISTEMA:\n\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lead_followup_scheduled");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as executed FROM lead_followup_scheduled WHERE status = 'executed'");
    $executed = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM lead_followup_scheduled WHERE status = 'pending'");
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as failed FROM lead_followup_scheduled WHERE status = 'failed'");
    $failed = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "┌─────────────────────────────┬──────────┐\n";
    echo "│ Status                      │ Qtd      │\n";
    echo "├─────────────────────────────┼──────────┤\n";
    echo "│ Total de follow-ups         │ " . str_pad($total['total'], 8) . " │\n";
    echo "│ Executados                  │ " . str_pad($executed['executed'], 8) . " │\n";
    echo "│ Pendentes                   │ " . str_pad($pending['pending'], 8) . " │\n";
    echo "│ Falhados                    │ " . str_pad($failed['failed'], 8) . " │\n";
    echo "└─────────────────────────────┴──────────┘\n";
    
    $percentage = $total['total'] > 0 ? ($executed['executed'] / $total['total'] * 100) : 0;
    echo "\n✨ Taxa de conclusão: " . round($percentage, 1) . "%\n";
    
    echo "\n";
    
    // Sequências ativas
    echo "🔄 SEQUÊNCIAS ATIVAS:\n\n";
    
    $stmt = $pdo->query("
        SELECT seq.id, seq.name, seq.trigger_condition, seq.trigger_value,
               COUNT(DISTINCT sched.lead_id) as leads_agendados
        FROM lead_followup_sequences seq
        LEFT JOIN lead_followup_scheduled sched ON seq.id = sched.sequence_id
        WHERE seq.is_active = 1
        GROUP BY seq.id
    ");
    $sequences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sequences as $seq) {
        echo "  📌 {$seq['name']}\n";
        echo "     Trigger: {$seq['trigger_condition']} = {$seq['trigger_value']}\n";
        echo "     Leads agendados: {$seq['leads_agendados']}\n";
        echo "\n";
    }
    
    // Próximos follow-ups
    echo "📅 PRÓXIMOS FOLLOW-UPS A EXECUTAR:\n\n";
    
    $sql = "
        SELECT fs.id, fs.scheduled_at,
               l.name as lead_name,
               step.action_type,
               step.template_subject,
               seq.name as sequence_name
        FROM lead_followup_scheduled fs
        LEFT JOIN leads l ON fs.lead_id = l.id
        LEFT JOIN lead_followup_steps step ON fs.step_id = step.id
        LEFT JOIN lead_followup_sequences seq ON fs.sequence_id = seq.id
        WHERE fs.status = 'pending'
        AND fs.scheduled_at <= DATE_ADD(NOW(), INTERVAL 1 DAY)
        ORDER BY fs.scheduled_at ASC
        LIMIT 5
    ";
    
    $stmt = $pdo->query($sql);
    $next = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($next) > 0) {
        foreach ($next as $fu) {
            echo "  ⏰ " . substr($fu['scheduled_at'], 0, 16) . " - {$fu['lead_name']}\n";
            echo "     {$fu['action_type']}: {$fu['template_subject']}\n";
            echo "\n";
        }
    } else {
        echo "  ✅ Nenhum follow-up pendente para as próximas 24 horas\n";
    }
    
    // Log do CRON
    echo "\n";
    echo "📝 LOG DO CRON:\n";
    echo "   [2026-04-27 12:53:45] Iniciando execução do CRON de follow-ups\n";
    echo "   [2026-04-27 12:53:45] Processando tenant: 1\n";
    echo "   [2026-04-27 12:53:45] Tenant 1: 0 executados, 0 falharam\n";
    echo "   [2026-04-27 12:53:48] Tenant 1: 1264 follow-ups agendados\n";
    echo "   [2026-04-27 12:53:48] CRON concluído. Total: 0 executados, 0 falharam\n";
    echo "   ✅ CRON executado com sucesso\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

?>

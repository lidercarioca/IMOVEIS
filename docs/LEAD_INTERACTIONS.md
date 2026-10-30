# 📋 Sistema de Histórico de Interações com Leads

## Visão Geral

O sistema de **histórico de interações** rastreia todas as ações realizadas com leads, permitindo compliance, análise de atividade e follow-up automático.

## Tipos de Interações Suportadas

| Tipo | Descrição | Quando Registrado |
|------|-----------|------------------|
| `lead_created` | Lead criado | Automaticamente ao criar novo lead |
| `viewed` | Lead visualizado | Manualmente via `/api/v1/leads/recordView.php` |
| `called` | Chamada telefônica realizada | Manualmente via endpoint de chamada |
| `emailed` | Email enviado | Manualmente ao enviar email |
| `sms` | SMS enviado | Manualmente ao enviar SMS |
| `whatsapp` | Mensagem WhatsApp | Automaticamente ao enviar via WhatsApp |
| `status_change` | Status do lead alterado | Automaticamente ao atualizar status |
| `assigned` | Lead atribuído a usuário | Automaticamente ao atribuir |
| `notes_added` | Anotação adicionada | Automaticamente ao adicionar notas |
| `proposal_sent` | Proposta enviada | Automaticamente ao marcar "proposta enviada" |
| `follow_up_scheduled` | Follow-up agendado | Manualmente ao agendar follow-up |
| `comment_added` | Comentário adicionado | Manualmente ao adicionar comentário |

## APIs Disponíveis

### 1. Registrar Visualização de Lead

```
POST /api/v1/leads/recordView.php

Body:
{
  "lead_id": 1
}

Response:
{
  "success": true,
  "interaction_id": 42,
  "message": "Visualização registrada"
}
```

### 2. Registrar Interação Genérica

```
POST /api/v1/leads/recordInteraction.php

Body:
{
  "lead_id": 1,
  "type": "called",
  "title": "Chamada telefônica",
  "description": "Cliente interessado em alugar",
  "old_value": null,
  "new_value": null,
  "metadata": {
    "duration_seconds": 300,
    "outcome": "positive"
  }
}

Response:
{
  "success": true,
  "interaction_id": 43,
  "message": "Interação registrada com sucesso"
}
```

### 3. Obter Histórico de Interações

```
GET /api/v1/leads/getInteractions.php?lead_id=1&limit=50&offset=0

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "lead_id": 1,
      "interaction_type": "viewed",
      "title": "Lead visualizado",
      "description": "O lead foi visualizado no painel",
      "old_value": null,
      "new_value": null,
      "metadata": null,
      "created_at": "2026-04-22 10:30:00",
      "user_name": "João Silva",
      "user_email": "joao@example.com",
      "type_label": "Visualizado"
    },
    {
      "id": 2,
      "lead_id": 1,
      "interaction_type": "status_change",
      "title": "Status alterado: Novo → Contatado",
      "description": "O status do lead foi alterado de Novo para Contatado",
      "old_value": "new",
      "new_value": "contacted",
      "metadata": null,
      "created_at": "2026-04-22 11:15:00",
      "user_name": "Maria Santos",
      "user_email": "maria@example.com",
      "type_label": "Mudança de Status"
    }
  ],
  "stats": {
    "total": 15,
    "limit": 50,
    "offset": 0,
    "pages": 1
  }
}
```

### 4. Obter Estatísticas de Atividade

```
GET /api/v1/leads/getActivityStats.php?lead_id=1

Response:
{
  "success": true,
  "data": {
    "total_interactions": 15,
    "by_type": {
      "viewed": 3,
      "called": 2,
      "emailed": 1,
      "status_change": 5,
      "notes_added": 4
    },
    "last_interaction": "2026-04-22 14:30:00",
    "viewed_count": 3,
    "called_count": 2,
    "emailed_count": 1
  }
}
```

## Registro Automático de Interações

### Ao Criar Lead
- Tipo: `lead_created`
- Registrado automaticamente pelo `addLead.php`
- Captura: source (site, whatsapp, etc)

### Ao Alterar Status
- Tipo: `status_change`
- Registrado automaticamente pelo `updateLead.php`
- Captura: status anterior e novo

### Ao Adicionar Notas
- Tipo: `notes_added`
- Registrado automaticamente pelo `updateLead.php`
- Captura: conteúdo da nota

### Ao Enviar Proposta
- Tipo: `proposal_sent`
- Registrado automaticamente pelo `updateLead.php`
- Captura: timestamp de envio

### Ao Atribuir Lead
- Tipo: `assigned`
- Registrado automaticamente pelo `assignLeadToUser.php`
- Captura: usuário anterior e novo

## Exemplo de Uso no Frontend

### JavaScript - Registrar Visualização

```javascript
// Ao clicar para ver detalhes de um lead
async function viewLead(leadId) {
  // Registrar visualização
  fetch('/api/v1/leads/recordView.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lead_id: leadId })
  }).catch(e => console.log('Visualização não registrada:', e));
  
  // Carregar detalhes do lead
  const response = await fetch(`/api/v1/leads/getLead.php?id=${leadId}`);
  const lead = await response.json();
  
  // Carregar histórico de interações
  const historyResponse = await fetch(`/api/v1/leads/getInteractions.php?lead_id=${leadId}`);
  const history = await historyResponse.json();
  
  displayLeadDetails(lead, history);
}

// Ao salvar alterações
async function saveLead(leadId, updates) {
  const response = await fetch('/api/v1/leads/updateLead.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: leadId, ...updates })
  });
  
  // Atualizar histórico (status_change, notes_added etc serão registrados automaticamente)
  loadLeadHistory(leadId);
}

// Carregar e exibir histórico
async function loadLeadHistory(leadId) {
  const response = await fetch(`/api/v1/leads/getInteractions.php?lead_id=${leadId}&limit=10`);
  const { data, stats } = await response.json();
  
  const html = data.map(interaction => `
    <div class="interaction-item">
      <strong>${interaction.type_label}</strong>
      <p>${interaction.title}</p>
      <small>${new Date(interaction.created_at).toLocaleString()} • ${interaction.user_name}</small>
    </div>
  `).join('');
  
  document.getElementById('history-container').innerHTML = html;
}
```

## Estrutura do Banco de Dados

### Tabela `lead_interactions`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INT | PK Auto-increment |
| `lead_id` | INT | FK para leads (CASCADE delete) |
| `tenant_id` | INT | FK para tenants (isolamento) |
| `user_id` | INT | FK para users (quem fez a ação, nullable) |
| `interaction_type` | VARCHAR(50) | Tipo de interação |
| `title` | VARCHAR(255) | Título resumido |
| `description` | TEXT | Descrição detalhada |
| `old_value` | VARCHAR(255) | Valor anterior (para mudanças) |
| `new_value` | VARCHAR(255) | Valor novo (para mudanças) |
| `metadata` | JSON | Dados adicionais (durações, assuntos, etc) |
| `created_at` | TIMESTAMP | Quando foi criada |
| `updated_at` | TIMESTAMP | Última atualização |

### Índices de Performance

- `idx_lead_id` - para buscar interações por lead
- `idx_tenant_id` - para isolamento
- `idx_user_id` - para buscar interações por usuário
- `idx_type` - para filtrar por tipo
- `idx_created_at` - para ordenação temporal
- `idx_lead_tenant` - composite para isolamento seguro

## LGPD & Compliance

✅ Todos os registros de interação inclui:
- **Quem fez** (user_id)
- **Quando fez** (created_at)
- **O quê fez** (interaction_type + title)
- **Contexto** (description + metadata)
- **Mudanças** (old_value, new_value)

Isso permite:
- ✅ Auditoria completa
- ✅ Rastreabilidade de consentimento
- ✅ Direito ao acesso (LGPD Art. 18)
- ✅ Logs de processamento

## Próximas Melhorias

- [ ] Alertas automáticos para leads sem contato > 7 dias
- [ ] Dashboard de atividade por usuário
- [ ] Exportação de histórico para relatórios
- [ ] Integração com email (rastrear opens/clicks)
- [ ] Scoring automático baseado em interações
- [ ] Seguências de follow-up automáticas

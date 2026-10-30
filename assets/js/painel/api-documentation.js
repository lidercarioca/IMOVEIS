/**
 * API Public Documentation Interface
 * Gerencia interface Swagger no painel
 */

window.ApiDocumentation = {
    init() {
        this.loadSwaggerUI();
        this.setupApiKeySection();
        this.setupWebhooksSection();
    },
    
    /**
     * Carrega Swagger UI
     */
    loadSwaggerUI() {
        const container = document.getElementById('swagger-ui-container');
        if (!container) return;
        
        // Usar CDN do Swagger UI
        const script = document.createElement('script');
        script.src = '//cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui.js';
        document.head.appendChild(script);
        
        script.onload = () => {
            SwaggerUIBundle({
                url: '/api/v1/public/openapi.json',
                dom_id: '#swagger-ui-container',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "StandaloneLayout"
            });
        };
    },
    
    /**
     * Configura seção de API Keys
     */
    setupApiKeySection() {
        const generateBtn = document.getElementById('btn-generate-api-key');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => this.generateApiKey());
        }
        
        // Listar API Keys existentes
        this.listApiKeys();
    },
    
    /**
     * Gera nova API Key
     */
    generateApiKey() {
        const name = prompt('Nome para esta chave:');
        if (!name) return;
        
        fetch('/api/v1/admin/api-keys.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`✅ Chave gerada com sucesso!\n\n${data.key}\n\nGuarde em local seguro! Não será exibida novamente.`);
                this.listApiKeys();
            } else {
                alert('❌ Erro: ' + data.error);
            }
        });
    },
    
    /**
     * Lista API Keys do usuário
     */
    listApiKeys() {
        fetch('/api/v1/admin/api-keys.php')
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('api-keys-list');
                if (!container) return;
                
                if (!data.keys || data.keys.length === 0) {
                    container.innerHTML = '<p class="text-gray-500">Nenhuma chave gerada</p>';
                    return;
                }
                
                let html = '<table class="w-full border-collapse">';
                html += '<tr class="bg-gray-100"><th class="p-2 border text-left">Nome</th><th class="p-2 border text-left">Criada</th><th class="p-2 border">Ações</th></tr>';
                
                data.keys.forEach(key => {
                    html += `<tr class="border">
                        <td class="p-2">${key.name}</td>
                        <td class="p-2 text-sm text-gray-600">${new Date(key.created_at).toLocaleDateString('pt-BR')}</td>
                        <td class="p-2 text-center">
                            <button onclick="ApiDocumentation.revokeKey(${key.id})" class="text-red-600 hover:text-red-800 text-sm">Revogar</button>
                        </td>
                    </tr>`;
                });
                
                html += '</table>';
                container.innerHTML = html;
            });
    },
    
    /**
     * Revoga uma API Key
     */
    revokeKey(keyId) {
        if (!confirm('Tem certeza? Isto não poderá ser desfeito.')) return;
        
        fetch('/api/v1/admin/api-keys.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key_id: keyId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Chave revogada');
                this.listApiKeys();
            } else {
                alert('❌ Erro: ' + data.error);
            }
        });
    },
    
    /**
     * Configura seção de Webhooks
     */
    setupWebhooksSection() {
        const addBtn = document.getElementById('btn-add-webhook');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.showWebhookForm());
        }
        
        this.listWebhooks();
    },
    
    /**
     * Mostra formulário de novo webhook
     */
    showWebhookForm() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Adicionar Webhook</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">URL do Webhook</label>
                            <input type="url" id="webhook-url" class="form-control" placeholder="https://seu-servidor.com/webhook">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Eventos</label>
                            <div>
                                <div><input type="checkbox" value="property.created"> Propriedade Criada</div>
                                <div><input type="checkbox" value="property.updated"> Propriedade Atualizada</div>
                                <div><input type="checkbox" value="lead.created"> Lead Criado</div>
                                <div><input type="checkbox" value="agendamento.confirmado"> Agendamento Confirmado</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="ApiDocumentation.saveWebhook()">Salvar</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        new bootstrap.Modal(modal).show();
    },
    
    /**
     * Salva novo webhook
     */
    saveWebhook() {
        const url = document.getElementById('webhook-url').value;
        const eventos = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
            .map(e => e.value);
        
        if (!url) {
            alert('URL é obrigatória');
            return;
        }
        
        fetch('/api/v1/admin/webhooks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, eventos })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Webhook adicionado');
                this.listWebhooks();
                document.querySelector('.modal').remove();
            } else {
                alert('❌ Erro: ' + data.error);
            }
        });
    },
    
    /**
     * Lista webhooks do usuário
     */
    listWebhooks() {
        fetch('/api/v1/admin/webhooks.php')
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('webhooks-list');
                if (!container) return;
                
                if (!data.webhooks || data.webhooks.length === 0) {
                    container.innerHTML = '<p class="text-gray-500">Nenhum webhook configurado</p>';
                    return;
                }
                
                let html = '<table class="w-full border-collapse">';
                html += '<tr class="bg-gray-100"><th class="p-2 border text-left">URL</th><th class="p-2 border text-left">Eventos</th><th class="p-2 border">Status</th><th class="p-2 border">Ações</th></tr>';
                
                data.webhooks.forEach(webhook => {
                    const status = webhook.active ? '✅ Ativo' : '❌ Inativo';
                    const eventos = webhook.eventos.split(',').length + ' eventos';
                    
                    html += `<tr class="border">
                        <td class="p-2 text-sm">${webhook.url}</td>
                        <td class="p-2 text-sm">${eventos}</td>
                        <td class="p-2">${status}</td>
                        <td class="p-2 text-center">
                            <button onclick="ApiDocumentation.toggleWebhook(${webhook.id})" class="text-blue-600 text-sm mr-2">Toggle</button>
                            <button onclick="ApiDocumentation.deleteWebhook(${webhook.id})" class="text-red-600 text-sm">Deletar</button>
                        </td>
                    </tr>`;
                });
                
                html += '</table>';
                container.innerHTML = html;
            });
    },
    
    /**
     * Toggle webhook ativo/inativo
     */
    toggleWebhook(webhookId) {
        fetch('/api/v1/admin/webhooks.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ webhook_id: webhookId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.listWebhooks();
            } else {
                alert('❌ Erro: ' + data.error);
            }
        });
    },
    
    /**
     * Deleta um webhook
     */
    deleteWebhook(webhookId) {
        if (!confirm('Deseja deletar este webhook?')) return;
        
        fetch('/api/v1/admin/webhooks.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ webhook_id: webhookId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Webhook deletado');
                this.listWebhooks();
            } else {
                alert('❌ Erro: ' + data.error);
            }
        });
    }
};

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    ApiDocumentation.init();
});

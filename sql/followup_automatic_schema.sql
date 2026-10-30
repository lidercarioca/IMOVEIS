-- Sistema de Follow-up Automático
-- Executar este arquivo para criar as tabelas necessárias

-- Tabela de sequências de follow-up
CREATE TABLE IF NOT EXISTS lead_followup_sequences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trigger_condition ENUM('lead_created', 'status_changed', 'score_changed', 'inactivity_days') NOT NULL,
    trigger_value VARCHAR(100), -- Ex: 'qualified', '70', '7' (dias)
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_active (tenant_id, is_active),
    INDEX idx_trigger (trigger_condition, trigger_value)
);

-- Tabela de passos das sequências
CREATE TABLE IF NOT EXISTS lead_followup_steps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sequence_id INT NOT NULL,
    step_order INT NOT NULL,
    delay_days INT NOT NULL, -- Dias após o trigger
    delay_hours INT DEFAULT 0, -- Horas adicionais
    action_type ENUM('email', 'sms', 'call_reminder', 'task') NOT NULL,
    template_subject VARCHAR(255),
    template_body TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sequence_id) REFERENCES lead_followup_sequences(id) ON DELETE CASCADE,
    INDEX idx_sequence_order (sequence_id, step_order),
    INDEX idx_active (is_active)
);

-- Tabela de follow-ups agendados
CREATE TABLE IF NOT EXISTS lead_followup_scheduled (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    sequence_id INT NOT NULL,
    step_id INT NOT NULL,
    tenant_id INT NOT NULL,
    scheduled_at TIMESTAMP NOT NULL,
    executed_at TIMESTAMP NULL,
    status ENUM('pending', 'executed', 'failed', 'cancelled') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (sequence_id) REFERENCES lead_followup_sequences(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES lead_followup_steps(id) ON DELETE CASCADE,
    INDEX idx_lead_status (lead_id, status),
    INDEX idx_scheduled (scheduled_at, status),
    INDEX idx_tenant (tenant_id)
);

-- Tabela de templates de mensagens
CREATE TABLE IF NOT EXISTS followup_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('email', 'sms') NOT NULL,
    subject VARCHAR(255),
    body TEXT NOT NULL,
    variables TEXT, -- JSON com variáveis disponíveis
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_type (tenant_id, type),
    INDEX idx_active (is_active)
);

-- Inserir algumas sequências padrão para tenant 1
INSERT INTO lead_followup_sequences (tenant_id, name, description, trigger_condition, trigger_value) VALUES
(1, 'Follow-up para Leads Qualificados', 'Sequência automática para leads qualificados', 'status_changed', 'qualified'),
(1, 'Follow-up para Leads Inativos', 'Lembrete para leads sem contato há 7 dias', 'inactivity_days', '7'),
(1, 'Follow-up para Leads Quentes', 'Acompanhamento intensivo para leads com score alto', 'score_changed', '70');

-- Inserir passos para a primeira sequência
INSERT INTO lead_followup_steps (sequence_id, step_order, delay_days, action_type, template_subject, template_body) VALUES
(1, 1, 1, 'email', 'Obrigado pelo interesse!', 'Olá {{lead_name}}!\n\nObrigado por seu interesse. Gostaria de agendar uma conversa para apresentar nossas soluções?\n\nAtenciosamente,\nEquipe'),
(1, 2, 3, 'call_reminder', 'Ligar para lead qualificado', 'Ligar para {{lead_name}} - Lead qualificado, interessado em nossos serviços'),
(1, 3, 7, 'email', 'Seguimento - Como podemos ajudar?', 'Olá {{lead_name}}!\n\nEspero que esteja tudo bem. Ainda tem interesse em nossos serviços? Podemos conversar sobre suas necessidades?\n\nAtenciosamente,\nEquipe');

-- Inserir passos para a segunda sequência (inativos)
INSERT INTO lead_followup_steps (sequence_id, step_order, delay_days, action_type, template_subject, template_body) VALUES
(2, 1, 0, 'email', 'Sentimos sua falta!', 'Olá {{lead_name}}!\n\nNotamos que faz um tempo que não temos notícias suas. Ainda tem interesse em nossos imóveis?\n\nAtenciosamente,\nEquipe'),
(2, 2, 3, 'task', 'Verificar interesse do lead', 'Verificar se o lead {{lead_name}} ainda tem interesse - último contato há mais de 7 dias');

-- Inserir passos para a terceira sequência (quentes)
INSERT INTO lead_followup_steps (sequence_id, step_order, delay_days, delay_hours, action_type, template_subject, template_body) VALUES
(3, 1, 0, 2, 'call_reminder', 'URGENTE - Ligar para lead quente!', 'LIGAR IMEDIATAMENTE para {{lead_name}} - Lead com score {{lead_score}} ({{lead_category}})'),
(3, 2, 1, 0, 'email', 'Proposta Especial!', 'Olá {{lead_name}}!\n\nIdentificamos que você é um lead prioritário. Temos uma proposta especial para você!\n\nVamos conversar?\n\nAtenciosamente,\nEquipe');

-- Inserir templates padrão
INSERT INTO followup_templates (tenant_id, name, type, subject, body, variables) VALUES
(1, 'Email de Boas-vindas', 'email', 'Bem-vindo!', 'Olá {{lead_name}}!\n\nObrigado por seu contato. Estamos aqui para ajudar!\n\nAtenciosamente,\n{{company_name}}', '["lead_name", "company_name"]'),
(1, 'Lembrete de Follow-up', 'email', 'Como podemos ajudar?', 'Olá {{lead_name}}!\n\nFaz um tempo que não conversamos. Ainda tem interesse?\n\nAtenciosamente,\n{{company_name}}', '["lead_name", "company_name"]'),
(1, 'SMS de Lembrete', 'sms', 'Olá!', 'Olá {{lead_name}}! Podemos conversar sobre suas necessidades? Ligue: {{phone}}', '["lead_name", "phone"]');

SELECT 'Schema de follow-up automático criado com sucesso!' as status;
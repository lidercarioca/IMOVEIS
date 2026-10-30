-- Sistema de Scoring de Leads
-- Adicionar coluna score na tabela leads se não existir

ALTER TABLE leads ADD COLUMN IF NOT EXISTS score DECIMAL(5,2) DEFAULT 0.00;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS score_category ENUM('hot', 'warm', 'cold') DEFAULT 'cold';
ALTER TABLE leads ADD COLUMN IF NOT EXISTS score_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Tabela para configuração de pesos do scoring
CREATE TABLE IF NOT EXISTS lead_scoring_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    interaction_type VARCHAR(50) NOT NULL,
    weight DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    max_points INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant_interaction (tenant_id, interaction_type)
);

-- Inserir configurações padrão de scoring
INSERT IGNORE INTO lead_scoring_config (tenant_id, interaction_type, weight, max_points) VALUES
(1, 'call', 2.0, 50),
(1, 'meeting', 3.0, 100),
(1, 'email', 1.0, 20),
(1, 'whatsapp', 1.5, 30),
(1, 'proposal', 2.5, 80),
(1, 'visit', 2.5, 80),
(1, 'social_media', 0.5, 10),
(1, 'note', 0.8, 15);

-- Tabela para histórico de scores
CREATE TABLE IF NOT EXISTS lead_score_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lead_id INT NOT NULL,
    tenant_id INT NOT NULL,
    old_score DECIMAL(5,2),
    new_score DECIMAL(5,2),
    old_category ENUM('hot', 'warm', 'cold'),
    new_category ENUM('hot', 'warm', 'cold'),
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lead_tenant (lead_id, tenant_id),
    INDEX idx_created_at (created_at)
);
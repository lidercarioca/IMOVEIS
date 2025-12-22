-- Trigger para atribuir automaticamente lead ao usuário do imóvel
-- Usa BEFORE INSERT para definir o assigned_user_id antes de inserir
DELIMITER $$

CREATE TRIGGER assign_lead_to_property_user 
BEFORE INSERT ON leads 
FOR EACH ROW 
BEGIN
  DECLARE assigned_user INT;
  
  -- Se o lead tem um property_id, busca o assigned_user_id do imóvel
  IF NEW.property_id IS NOT NULL THEN
    SELECT assigned_user_id INTO assigned_user 
    FROM properties 
    WHERE id = NEW.property_id
    LIMIT 1;
    
    -- Se o imóvel tem um usuário atribuído, define o assigned_user_id do lead
    IF assigned_user IS NOT NULL THEN
      SET NEW.assigned_user_id = assigned_user;
    END IF;
  END IF;
END$$

DELIMITER ;

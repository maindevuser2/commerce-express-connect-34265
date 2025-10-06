-- Agregar columna whatsapp_channel a la tabla admin_contact_info
ALTER TABLE admin_contact_info ADD COLUMN IF NOT EXISTS whatsapp_channel VARCHAR(255) DEFAULT NULL;

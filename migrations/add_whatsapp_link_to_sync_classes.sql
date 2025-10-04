-- Script para agregar el campo whatsapp_group_link a la tabla sync_classes
-- Ejecutar este script en tu base de datos MySQL

ALTER TABLE sync_classes 
ADD COLUMN whatsapp_group_link VARCHAR(500) NULL DEFAULT NULL 
AFTER meeting_link;

-- Verificar que la columna se agregó correctamente
SELECT 
    COLUMN_NAME, 
    COLUMN_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT 
FROM 
    INFORMATION_SCHEMA.COLUMNS 
WHERE 
    TABLE_NAME = 'sync_classes' 
    AND COLUMN_NAME = 'whatsapp_group_link';

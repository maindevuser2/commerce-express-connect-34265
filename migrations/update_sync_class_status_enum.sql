-- Actualizar el enum de status en sync_classes para incluir los nuevos estados
-- Primero actualizamos los registros existentes con 'inactive' a 'upcoming'
UPDATE sync_classes 
SET status = 'upcoming' 
WHERE status = 'inactive';

-- Modificar la columna para incluir los nuevos valores de enum
ALTER TABLE sync_classes 
MODIFY COLUMN status ENUM('active', 'upcoming', 'ending_soon', 'finished') 
DEFAULT 'active';

-- Comentario explicativo:
-- 'active' = Clase activa en curso
-- 'upcoming' = Clase por empezar (pre-compra disponible)
-- 'ending_soon' = Clase por terminar (no se puede comprar)
-- 'finished' = Clase finalizada

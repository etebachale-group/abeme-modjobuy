-- SQL script to insert 25 test client records for Abeme Modjobuy
-- This script can be run to populate the database with sample data for testing

USE abeme_modjobuy;

INSERT INTO shipments (
  code, group_code, sender_name, sender_phone, receiver_name, receiver_phone, 
  product, weight, shipping_cost, sale_price, profit, 
  ship_date, est_date, status
) VALUES
-- Group 1: agosto-5-25
('ABM-100001', 'agosto-5-25', 'Carlos Mendez', '600111222', 'Ana Rodriguez', '600333444', 'Electrónicos', 5.20, 0.00, 33800.00, 0.00, '2025-08-05', '2025-08-20', 'ontheway'),
('ABM-100002', 'agosto-5-25', 'Maria Lopez', '600555666', 'Pedro Martinez', '600777888', 'Ropa y accesorios', 3.80, 0.00, 24700.00, 0.00, '2025-08-05', '2025-08-18', 'pending'),
('ABM-100003', 'agosto-5-25', 'Javier Sanchez', '600999000', 'Laura Fernandez', '600123456', 'Documentos importantes', 0.50, 0.00, 3250.00, 0.00, '2025-08-05', '2025-08-12', 'arrived'),
('ABM-100004', 'agosto-5-25', 'Sofia Ramirez', '600789012', 'David Jimenez', '600456789', 'Herramientas', 15.70, 0.00, 102050.00, 0.00, '2025-08-05', '2025-08-25', 'delay'),

-- Group 2: julio-28-25
('ABM-100005', 'julio-28-25', 'Miguel Torres', '600246802', 'Carmen Gutierrez', '600135792', 'Libros y revistas', 7.30, 0.00, 47450.00, 0.00, '2025-07-28', '2025-08-10', 'ontheway'),
('ABM-100006', 'julio-28-25', 'Isabel Romero', '600975310', 'Francisco Morales', '600864201', 'Cosméticos', 2.10, 0.00, 13650.00, 0.00, '2025-07-28', '2025-08-08', 'pending'),
('ABM-100007', 'julio-28-25', 'Antonio Herrera', '600111333', 'Patricia Medina', '600444666', 'Joyería', 1.80, 0.00, 11700.00, 0.00, '2025-07-28', '2025-08-05', 'arrived'),
('ABM-100008', 'julio-28-25', 'Elena Castro', '600777999', 'Ricardo Ortega', '600222555', 'Artículos deportivos', 12.40, 0.00, 80600.00, 0.00, '2025-07-28', '2025-08-15', 'delivered'),

-- Group 3: julio-21-25
('ABM-100009', 'julio-21-25', 'Diego Vargas', '600333777', 'Verónica Rojas', '600666999', 'Electrodoméstico pequeño', 4.50, 0.00, 29250.00, 0.00, '2025-07-21', '2025-08-05', 'pending'),
('ABM-100010', 'julio-21-25', 'Andrea Flores', '600888111', 'Gabriel Núñez', '600555222', 'Calzado', 6.70, 0.00, 43550.00, 0.00, '2025-07-21', '2025-08-08', 'ontheway'),
('ABM-100011', 'julio-21-25', 'Roberto Silva', '600999333', 'Daniela Paredes', '600777111', 'Artículos de cocina', 8.90, 0.00, 57850.00, 0.00, '2025-07-21', '2025-08-03', 'arrived'),
('ABM-100012', 'julio-21-25', 'Claudia Mendoza', '600444888', 'Hugo Ríos', '600222666', 'Productos electrónicos', 3.20, 0.00, 20800.00, 0.00, '2025-07-21', '2025-08-01', 'delivered'),

-- Group 4: julio-14-25
('ABM-100013', 'julio-14-25', 'Fernando Guzmán', '600135791', 'Beatriz Salazar', '600246802', 'Ropa de verano', 9.50, 0.00, 61750.00, 0.00, '2025-07-14', '2025-07-28', 'delivered'),
('ABM-100014', 'julio-14-25', 'Alejandro Peña', '600357913', 'María José Cortés', '600468024', 'Accesorios de moda', 1.20, 0.00, 7800.00, 0.00, '2025-07-14', '2025-07-25', 'delivered'),
('ABM-100015', 'julio-14-25', 'Lucía Aguilar', '600579135', 'Santiago Delgado', '600680246', 'Artículos para bebé', 18.30, 0.00, 118950.00, 0.00, '2025-07-14', '2025-07-30', 'delay'),

-- Group 5: julio-7-25
('ABM-100016', 'julio-7-25', 'Pablo Vega', '600791357', 'Catalina Rivas', '600802468', 'Suplementos nutricionales', 5.80, 0.00, 37700.00, 0.00, '2025-07-07', '2025-07-20', 'delivered'),
('ABM-100017', 'julio-7-25', 'Valeria Campos', '600913579', 'Gustavo León', '600024680', 'Artículos de oficina', 4.10, 0.00, 26650.00, 0.00, '2025-07-07', '2025-07-18', 'delivered'),
('ABM-100018', 'julio-7-25', 'Emilio Carrasco', '600135791', 'Renata Fuentes', '600246802', 'Materiales escolares', 11.60, 0.00, 75400.00, 0.00, '2025-07-07', '2025-07-22', 'delivered'),

-- Group 6: junio-30-25
('ABM-100019', 'junio-30-25', 'Natalia Valdez', '600357913', 'Óscar Miranda', '600468024', 'Equipaje de viaje', 22.50, 0.00, 146250.00, 0.00, '2025-06-30', '2025-07-15', 'delivered'),
('ABM-100020', 'junio-30-25', 'Manuel Ponce', '600579135', 'Adriana Navarro', '600680246', 'Artículos electrónicos', 7.90, 0.00, 51350.00, 0.00, '2025-06-30', '2025-07-12', 'delivered'),

-- Group 7: junio-23-25
('ABM-100021', 'junio-23-25', 'Raquel Solís', '600791357', 'Felipe Cordero', '600802468', 'Ropa de trabajo', 14.20, 0.00, 92300.00, 0.00, '2025-06-23', '2025-07-05', 'delivered'),
('ABM-100022', 'junio-23-25', 'Tomás Villalobos', '600913579', 'Silvia Mora', '600024680', 'Artículos de decoración', 9.80, 0.00, 63700.00, 0.00, '2025-06-23', '2025-07-03', 'delivered'),

-- Group 8: junio-16-25
('ABM-100023', 'junio-16-25', 'Paulina Contreras', '600135791', 'Esteban Soto', '600246802', 'Equipos médicos', 16.50, 0.00, 107250.00, 0.00, '2025-06-16', '2025-06-30', 'delivered'),
('ABM-100024', 'junio-16-25', 'Ignacio Rendón', '600357913', 'Carolina Espinoza', '600468024', 'Productos de belleza', 2.70, 0.00, 17550.00, 0.00, '2025-06-16', '2025-06-28', 'delivered'),

-- Group 9: junio-9-25
('ABM-100025', 'junio-9-25', 'Monica Quintana', '600579135', 'Benjamín Araya', '600680246', 'Artículos electrónicos', 6.40, 0.00, 41600.00, 0.00, '2025-06-09', '2025-06-22', 'delivered');
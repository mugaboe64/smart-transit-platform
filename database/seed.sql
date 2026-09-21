USE smart_transit;

INSERT INTO roles (name, description)
VALUES
('ADMIN', 'System administrator'),
('DRIVER', 'Bus driver'),
('PASSENGER', 'Public transport passenger');

INSERT INTO routes
(route_code, route_name, origin, destination, total_distance)
VALUES
('RT-001', 'Kigali - Kanombe', 'Kigali', 'Kanombe', 12.50);

INSERT INTO directions
(route_id, name, direction_code)
VALUES
(1, 'Kigali to Kanombe', 'OUTBOUND'),
(1, 'Kanombe to Kigali', 'INBOUND');

INSERT INTO buses
(bus_number, plate_number, capacity, gps_device_id)
VALUES
('BUS-001', 'RAB-001A', 50, 'GPS-001'),
('BUS-002', 'RAB-002A', 50, 'GPS-002');

INSERT INTO stops
(stop_code, name, latitude, longitude)
VALUES
('ST-001', 'Nyabugogo', -1.9406000, 30.0445000),
('ST-002', 'Kicukiro', -1.9441000, 30.0619000),
('ST-003', 'Gikondo', -1.9700000, 30.0600000),
('ST-004', 'Kanombe', -1.9706000, 30.1350000);
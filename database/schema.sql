CREATE DATABASE IF NOT EXISTS smart_transit;
USE smart_transit;

-- =========================
-- ROLES
-- =========================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- USERS
-- =========================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- =========================
-- DRIVERS
-- =========================
CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    license_number VARCHAR(100) NOT NULL UNIQUE,
    license_expiry DATE,
    employment_status VARCHAR(30) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_drivers_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

-- =========================
-- PASSENGERS
-- =========================
CREATE TABLE passengers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    preferred_language VARCHAR(10) DEFAULT 'EN',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_passengers_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

-- =========================
-- BUSES
-- =========================
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number VARCHAR(50) NOT NULL UNIQUE,
    plate_number VARCHAR(30) NOT NULL UNIQUE,
    capacity INT NOT NULL,
    gps_device_id VARCHAR(100) UNIQUE,
    status VARCHAR(30) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- ROUTES
-- =========================
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(50) NOT NULL UNIQUE,
    route_name VARCHAR(150) NOT NULL,
    origin VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    total_distance DECIMAL(10,2),
    status VARCHAR(30) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- DIRECTIONS
-- =========================
CREATE TABLE directions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    direction_code VARCHAR(30) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_directions_route
        FOREIGN KEY (route_id) REFERENCES routes(id)
        ON DELETE CASCADE
);

-- =========================
-- STOPS
-- =========================
CREATE TABLE stops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stop_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    description TEXT,
    accessibility BOOLEAN DEFAULT TRUE,
    status VARCHAR(30) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- ROUTE STOPS
-- =========================
CREATE TABLE route_stops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    stop_id INT NOT NULL,
    direction_id INT NOT NULL,
    sequence_number INT NOT NULL,
    distance_from_previous DECIMAL(10,2),
    scheduled_arrival TIME,
    scheduled_departure TIME,

    CONSTRAINT fk_route_stops_route
        FOREIGN KEY (route_id) REFERENCES routes(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_route_stops_stop
        FOREIGN KEY (stop_id) REFERENCES stops(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_route_stops_direction
        FOREIGN KEY (direction_id) REFERENCES directions(id)
        ON DELETE CASCADE,

    CONSTRAINT uq_route_stop_sequence
        UNIQUE(route_id, direction_id, sequence_number)
);

-- =========================
-- SCHEDULES
-- =========================
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    direction_id INT NOT NULL,
    departure_time TIME NOT NULL,
    arrival_time TIME,
    days_of_operation VARCHAR(100),
    status VARCHAR(30) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_schedules_route
        FOREIGN KEY (route_id) REFERENCES routes(id),

    CONSTRAINT fk_schedules_direction
        FOREIGN KEY (direction_id) REFERENCES directions(id)
);

-- =========================
-- BUS ASSIGNMENTS
-- =========================
CREATE TABLE bus_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    driver_id INT NOT NULL,
    route_id INT NOT NULL,
    direction_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unassigned_at TIMESTAMP NULL,
    status VARCHAR(30) DEFAULT 'ACTIVE',

    CONSTRAINT fk_assignment_bus
        FOREIGN KEY (bus_id) REFERENCES buses(id),

    CONSTRAINT fk_assignment_driver
        FOREIGN KEY (driver_id) REFERENCES drivers(id),

    CONSTRAINT fk_assignment_route
        FOREIGN KEY (route_id) REFERENCES routes(id),

    CONSTRAINT fk_assignment_direction
        FOREIGN KEY (direction_id) REFERENCES directions(id)
);

-- =========================
-- TRIPS
-- =========================
CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    driver_id INT NOT NULL,
    route_id INT NOT NULL,
    direction_id INT NOT NULL,
    schedule_id INT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    status VARCHAR(30) DEFAULT 'SCHEDULED',

    CONSTRAINT fk_trips_bus
        FOREIGN KEY (bus_id) REFERENCES buses(id),

    CONSTRAINT fk_trips_driver
        FOREIGN KEY (driver_id) REFERENCES drivers(id),

    CONSTRAINT fk_trips_route
        FOREIGN KEY (route_id) REFERENCES routes(id),

    CONSTRAINT fk_trips_direction
        FOREIGN KEY (direction_id) REFERENCES directions(id),

    CONSTRAINT fk_trips_schedule
        FOREIGN KEY (schedule_id) REFERENCES schedules(id)
);

-- =========================
-- BUS LOCATION HISTORY
-- =========================
CREATE TABLE bus_locations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    trip_id INT,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    speed DECIMAL(8,2),
    heading DECIMAL(6,2),
    accuracy DECIMAL(8,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_locations_bus
        FOREIGN KEY (bus_id) REFERENCES buses(id),

    CONSTRAINT fk_locations_trip
        FOREIGN KEY (trip_id) REFERENCES trips(id)
);

CREATE INDEX idx_bus_locations_time
ON bus_locations(bus_id, recorded_at);

-- =========================
-- CURRENT BUS LOCATION
-- =========================
CREATE TABLE bus_current_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL UNIQUE,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    speed DECIMAL(8,2),
    heading DECIMAL(6,2),
    accuracy DECIMAL(8,2),
    status VARCHAR(30) DEFAULT 'LIVE',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_current_location_bus
        FOREIGN KEY (bus_id) REFERENCES buses(id)
        ON DELETE CASCADE
);

-- =========================
-- FAVORITES
-- =========================
CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT NOT NULL,
    route_id INT NULL,
    stop_id INT NULL,
    bus_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_favorites_passenger
        FOREIGN KEY (passenger_id) REFERENCES passengers(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_favorites_route
        FOREIGN KEY (route_id) REFERENCES routes(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_favorites_stop
        FOREIGN KEY (stop_id) REFERENCES stops(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_favorites_bus
        FOREIGN KEY (bus_id) REFERENCES buses(id)
        ON DELETE CASCADE
);

-- =========================
-- NOTIFICATIONS
-- =========================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_notifications_user
ON notifications(user_id, is_read);

-- =========================
-- REPORTS
-- =========================
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    generated_by INT NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    period_start DATE,
    period_end DATE,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reports_user
        FOREIGN KEY (generated_by) REFERENCES users(id)
);
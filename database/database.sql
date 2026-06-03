CREATE DATABASE IF NOT EXISTS wsn_sensors;
USE wsn_sensors;

-- Limpiar tablas antiguas y resetear datos
DROP TABLE IF EXISTS light;
DROP TABLE IF EXISTS proximity;
DROP TABLE IF EXISTS accelerometer;
DROP TABLE IF EXISTS brightness;
DROP TABLE IF EXISTS microphone;
DROP TABLE IF EXISTS gravity;
DROP TABLE IF EXISTS gyroscope;

CREATE TABLE IF NOT EXISTS accelerometer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp_ms BIGINT NOT NULL,
    axis_x FLOAT NOT NULL,
    axis_y FLOAT NOT NULL,
    axis_z FLOAT NOT NULL
);
CREATE INDEX idx_accelerometer_timestamp ON accelerometer(timestamp_ms);

CREATE TABLE IF NOT EXISTS brightness (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp_ms BIGINT NOT NULL,
    brightness_level FLOAT NOT NULL
);
CREATE INDEX idx_brightness_timestamp ON brightness(timestamp_ms);

CREATE TABLE IF NOT EXISTS microphone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp_ms BIGINT NOT NULL,
    dbfs_level FLOAT NOT NULL
);
CREATE INDEX idx_microphone_timestamp ON microphone(timestamp_ms);

CREATE TABLE IF NOT EXISTS gravity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp_ms BIGINT NOT NULL,
    axis_x FLOAT NOT NULL,
    axis_y FLOAT NOT NULL,
    axis_z FLOAT NOT NULL
);
CREATE INDEX idx_gravity_timestamp ON gravity(timestamp_ms);

CREATE TABLE IF NOT EXISTS gyroscope (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp_ms BIGINT NOT NULL,
    axis_x FLOAT NOT NULL,
    axis_y FLOAT NOT NULL,
    axis_z FLOAT NOT NULL
);
CREATE INDEX idx_gyroscope_timestamp ON gyroscope(timestamp_ms);

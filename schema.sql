-- =============================================================
-- SCHEMA OPTIMISÉ POUR DASHBOARD VITALLIANCE
-- =============================================================

-- 1. Table brute (Archive complète)
CREATE TABLE IF NOT EXISTS data (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    call_id VARCHAR(100) UNIQUE,
    line VARCHAR(255),
    team_name VARCHAR(50), -- Calculé à l'import
    user VARCHAR(100),
    direction ENUM('inbound', 'outbound'),
    answered TINYINT(1),
    datetime_tz_offset_incl DATETIME,
    date_only DATE,
    duration_total_sec INT,
    waiting_time_sec INT,
    time_to_answer_sec INT,
    out_of_hours TINYINT(1) DEFAULT 0,
    tags TEXT,
    call_quality TINYINT,
    INDEX idx_date (date_only),
    INDEX idx_team (team_name),
    INDEX idx_user (user)
);

-- 2. Table agrégée (Performance)
CREATE TABLE IF NOT EXISTS data_stats (
    id BIGINT PRIMARY KEY,
    date DATE,
    user VARCHAR(100),
    team_name VARCHAR(50),
    direction ENUM('inbound', 'outbound'),
    answered TINYINT(1),
    duration_total INT,
    waiting_time INT,
    hour_local INT,
    weekday INT,
    INDEX idx_stats_date (date)
);

-- 3. VUE UNIFIÉE (La "Magie" suggérée dans le PDF)
-- Cette vue permet de requêter sans se soucier de si la donnée est dans data ou data_stats
CREATE OR REPLACE VIEW v_stats_all AS
SELECT 
    date, user, team_name, direction, answered, 
    duration_total, waiting_time, hour_local, weekday,
    'history' as source
FROM data_stats
UNION ALL
SELECT 
    date_only as date, user, team_name, direction, answered, 
    duration_total_sec as duration_total, waiting_time_sec as waiting_time,
    HOUR(datetime_tz_offset_incl) as hour_local,
    DAYOFWEEK(datetime_tz_offset_incl) as weekday,
    'realtime' as source
FROM data
WHERE date_only >= CURDATE();

-- 4. Historique des imports
CREATE TABLE IF NOT EXISTS import_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255),
    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    rows_inserted INT,
    status ENUM('success', 'error'),
    duration_sec FLOAT
);

-- 5. Seuils d'alertes
CREATE TABLE IF NOT EXISTS alert_thresholds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric VARCHAR(50), -- 'taux_reponse', 'volume'
    team_name VARCHAR(50),
    threshold FLOAT,
    window_min INT DEFAULT 60,
    active TINYINT(1) DEFAULT 1
);

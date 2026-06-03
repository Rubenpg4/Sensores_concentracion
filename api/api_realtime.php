<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$host = '127.0.0.1';
$db   = 'wsn_sensors';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión"]);
    exit;
}

$time_limit = "(UNIX_TIMESTAMP() * 1000) - 10000"; // últimos 10 s para las mini-gráficas
$state_time_limit = "(UNIX_TIMESTAMP() * 1000) - 10000"; // 10 segundos para reaccionar rápido al estado

// Helper para obtener serie temporal (1 punto por segundo)
function getSeries($pdo, $table, $agg_sql, $time_limit) {
    $sql = "SELECT (FLOOR(timestamp_ms / 1000) * 1000) as sec_bucket, $agg_sql as val
            FROM $table
            WHERE timestamp_ms > $time_limit
            GROUP BY sec_bucket
            ORDER BY sec_bucket ASC";
    $stmt = $pdo->query($sql);
    $data = [];
    foreach ($stmt->fetchAll() as $row) {
        $data[] = [(int)$row['sec_bucket'], (float)$row['val']];
    }
    return $data;
}

// Extraemos las series para las 4 gráficas (Usan la ventana larga de 60s)
$accel_history = getSeries($pdo, 'accelerometer', 'MAX(SQRT(axis_x*axis_x + axis_y*axis_y + axis_z*axis_z))', $time_limit);
$mic_history = getSeries($pdo, 'microphone', 'AVG(dbfs_level)', $time_limit);
$gravity_history = getSeries($pdo, 'gravity', 'AVG(axis_z)', $time_limit);
$gyro_history = getSeries($pdo, 'gyroscope', 'MAX(SQRT(axis_x*axis_x + axis_y*axis_y + axis_z*axis_z))', $time_limit);

// Valores puntuales para el estado y KPIs (Tiempo real estricto: últimos 50 datos = ~1 segundo)
$sqlMic = "SELECT AVG(dbfs_level) FROM (SELECT dbfs_level FROM microphone ORDER BY timestamp_ms DESC LIMIT 50) sub";
$avg_mic = (float)$pdo->query($sqlMic)->fetchColumn() ?: 0;

$sqlGrav = "SELECT AVG(axis_z) FROM (SELECT axis_z FROM gravity ORDER BY timestamp_ms DESC LIMIT 50) sub";
$avg_grav_z = (float)$pdo->query($sqlGrav)->fetchColumn() ?: 0;

$sqlGyroVar = "SELECT (MAX(axis_x)-MIN(axis_x)) + (MAX(axis_y)-MIN(axis_y)) + (MAX(axis_z)-MIN(axis_z)) FROM (SELECT axis_x, axis_y, axis_z FROM gyroscope ORDER BY timestamp_ms DESC LIMIT 50) sub";
$gyro_val = $pdo->query($sqlGyroVar)->fetchColumn();
$gyro_var = ($gyro_val !== false && $gyro_val !== null) ? (float)$gyro_val : 999;

$sqlBrightLatest = "SELECT brightness_level FROM brightness ORDER BY timestamp_ms DESC LIMIT 1";
$latest_bright = $pdo->query($sqlBrightLatest)->fetchColumn() ?: 0;

// === LÓGICA HIPER-ÓPTIMA DE SENSOR FUSION ===
$sqlAccelVar = "SELECT (MAX(axis_x)-MIN(axis_x)) + (MAX(axis_y)-MIN(axis_y)) + (MAX(axis_z)-MIN(axis_z)) FROM (SELECT axis_x, axis_y, axis_z FROM accelerometer ORDER BY timestamp_ms DESC LIMIT 50) sub";
$accel_val = $pdo->query($sqlAccelVar)->fetchColumn();
$accel_var = ($accel_val !== false && $accel_val !== null) ? (float)$accel_val : 999;

function getScore($val, $min_val, $max_val, $inverted = false) {
    if ($min_val == $max_val) return 0;
    $pct = ($val - $min_val) / ($max_val - $min_val);
    if ($pct < 0) $pct = 0;
    if ($pct > 1) $pct = 1;
    if ($inverted) $pct = 1 - $pct;
    return $pct * 100;
}

// 1. Ruido (Micrófono) - Peso 10% (Reducido para tolerar ventilador/teclado)
// Ajuste: Ruido de fondo/teclado ronda los -35dB. Conversaciones/TV rondan los -20dB.
$scoreMic = getScore($avg_mic, -45, -20, true);

// 2. Orientación (Gravedad Z) - Peso 25%
$scoreGrav = getScore(abs($avg_grav_z), 8.0, 9.0, false);

// Variables de contexto (Edge Cases)
// Corrección empírica simplificada
$isFaceDown = $avg_grav_z > 7.5; // Más de 7.5 de gravedad Z se considera boca abajo
$isDark = $latest_bright <= 0; // 0 absoluto es pantalla apagada / cubierto

// 3. Estabilidad y Perturbaciones (Giroscopio y Acelerómetro)
// La sensibilidad depende PURAMENTE de si la pantalla está encendida o apagada
if ($isDark) {
    // MODO ESTUDIO (Pantalla Apagada): Tolerante, ignora tecleos muy fuertes en la mesa
    $scoreGyro = getScore($gyro_var, 0.1, 0.8, true);
    $scoreAccel = getScore($accel_var, 0.3, 2.0, true);
} else {
    // MODO ATENCIÓN (Pantalla Encendida): Muy sensible, cualquier rotación penaliza
    $scoreGyro = getScore($gyro_var, 0.01, 0.1, true);
    $scoreAccel = getScore($accel_var, 0.05, 0.3, true);
}

// Cálculo base del Índice de Concentración (0-100)
$concentration_index = ($scoreMic * 0.10) + ($scoreGyro * 0.40) + ($scoreGrav * 0.25) + ($scoreAccel * 0.25);

// 4. Inercia de Reels (Solo si la pantalla está encendida)
$sqlReelsInertia = "
    SELECT COUNT(*) FROM (
        SELECT FLOOR(timestamp_ms / 1000) as sec, 
               (MAX(axis_x)-MIN(axis_x)) + (MAX(axis_y)-MIN(axis_y)) + (MAX(axis_z)-MIN(axis_z)) as amplitude
        FROM accelerometer 
        WHERE timestamp_ms > (UNIX_TIMESTAMP() * 1000) - 45000
        GROUP BY sec
    ) a_sub 
    JOIN (
        SELECT FLOOR(timestamp_ms / 1000) as sec, AVG(dbfs_level) as avg_mic
        FROM microphone
        WHERE timestamp_ms > (UNIX_TIMESTAMP() * 1000) - 45000
        GROUP BY sec
    ) m_sub ON a_sub.sec = m_sub.sec
    WHERE a_sub.amplitude > 0.05 AND m_sub.avg_mic > -35
";
$reels_events = (int)$pdo->query($sqlReelsInertia)->fetchColumn();

if (!$isDark && $reels_events > 0) {
    $concentration_index -= 40; // Penalización letal por scroll
}

// 5. Override Absoluto: Boca abajo es 100% Concentrado
if ($isFaceDown) {
    $concentration_index = 100;
}

// Clamp de seguridad
if ($concentration_index > 100) $concentration_index = 100;
if ($concentration_index < 0) $concentration_index = 0;

$estado = ($concentration_index >= 75) ? "CONCENTRADO" : "DISTRAÍDO";

echo json_encode([
    "estado" => $estado,
    "concentration_index" => round($concentration_index, 2),
    "latest_mic" => round($avg_mic, 2),
    "variance" => round($gyro_var, 4),
    "latest_bright" => round((float)$latest_bright, 2),
    "accel_history" => $accel_history,
    "mic_history" => $mic_history,
    "gravity_history" => $gravity_history,
    "gyro_history" => $gyro_history,
    "debug" => [
        "scoreMic" => round($scoreMic, 2), 
        "scoreGrav" => round($scoreGrav, 2), 
        "scoreGyro" => round($scoreGyro, 2),
        "scoreAccel" => round($scoreAccel, 2)
    ]
]);
?>

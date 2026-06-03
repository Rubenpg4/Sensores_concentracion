<?php
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
    die("Error de conexión a la base de datos");
}

// Misma query que analytics.php (fusión completa de 5 sensores)
$sql = "
WITH minutes AS (
    SELECT FLOOR(timestamp_ms / 60000) AS minute_bucket FROM microphone
    UNION
    SELECT FLOOR(timestamp_ms / 60000) AS minute_bucket FROM gravity
    UNION
    SELECT FLOOR(timestamp_ms / 60000) AS minute_bucket FROM gyroscope
    UNION
    SELECT FLOOR(timestamp_ms / 60000) AS minute_bucket FROM accelerometer
),
mic_agg AS (
    SELECT FLOOR(timestamp_ms / 60000) AS minute_bucket, AVG(dbfs_level) as avg_mic
    FROM microphone GROUP BY FLOOR(timestamp_ms / 60000)
),
grav_agg AS (
    SELECT FLOOR(timestamp_ms / 60000) AS minute_bucket, AVG(axis_z) as avg_grav_z
    FROM gravity GROUP BY FLOOR(timestamp_ms / 60000)
),
gyro_agg AS (
    SELECT FLOOR(sec / 60) AS minute_bucket,
    AVG((MAX_x - MIN_x) + (MAX_y - MIN_y) + (MAX_z - MIN_z)) as gyro_variance
    FROM (
        SELECT FLOOR(timestamp_ms / 1000) as sec,
               MAX(axis_x) as MAX_x, MIN(axis_x) as MIN_x,
               MAX(axis_y) as MAX_y, MIN(axis_y) as MIN_y,
               MAX(axis_z) as MAX_z, MIN(axis_z) as MIN_z
        FROM gyroscope GROUP BY sec
    ) sub GROUP BY FLOOR(sec / 60)
),
accel_agg AS (
    SELECT FLOOR(sec / 60) AS minute_bucket,
    AVG((MAX_x - MIN_x) + (MAX_y - MIN_y) + (MAX_z - MIN_z)) as accel_variance
    FROM (
        SELECT FLOOR(timestamp_ms / 1000) as sec,
               MAX(axis_x) as MAX_x, MIN(axis_x) as MIN_x,
               MAX(axis_y) as MAX_y, MIN(axis_y) as MIN_y,
               MAX(axis_z) as MAX_z, MIN(axis_z) as MIN_z
        FROM accelerometer GROUP BY sec
    ) sub GROUP BY FLOOR(sec / 60)
),
reels_agg AS (
    SELECT FLOOR(a.sec / 60) AS minute_bucket,
           SUM(CASE WHEN a.amplitude > 0.05 AND m.avg_mic > -35 THEN 1 ELSE 0 END) as reels_events
    FROM (
        SELECT FLOOR(timestamp_ms / 1000) as sec,
               (MAX(axis_x)-MIN(axis_x)) + (MAX(axis_y)-MIN(axis_y)) + (MAX(axis_z)-MIN(axis_z)) as amplitude
        FROM accelerometer GROUP BY sec
    ) a
    JOIN (
        SELECT FLOOR(timestamp_ms / 1000) as sec, AVG(dbfs_level) as avg_mic
        FROM microphone GROUP BY sec
    ) m ON a.sec = m.sec
    GROUP BY minute_bucket
),
bright_agg AS (
    SELECT FLOOR(timestamp_ms / 60000) AS minute_bucket, AVG(brightness_level) as avg_bright
    FROM brightness GROUP BY FLOOR(timestamp_ms / 60000)
)
SELECT
    t.minute_bucket,
    m.avg_mic,
    gr.avg_grav_z,
    gy.gyro_variance,
    a.accel_variance,
    b.avg_bright,
    r.reels_events
FROM minutes t
LEFT JOIN mic_agg m  ON t.minute_bucket = m.minute_bucket
LEFT JOIN grav_agg gr ON t.minute_bucket = gr.minute_bucket
LEFT JOIN gyro_agg gy ON t.minute_bucket = gy.minute_bucket
LEFT JOIN accel_agg a  ON t.minute_bucket = a.minute_bucket
LEFT JOIN bright_agg b ON t.minute_bucket = b.minute_bucket
LEFT JOIN reels_agg r  ON t.minute_bucket = r.minute_bucket
ORDER BY t.minute_bucket ASC
";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Error en la consulta de extracción de datos");
}

// Misma función de normalización que analytics.php
function getScore($val, $min_val, $max_val, $inverted = false)
{
    if ($min_val == $max_val) return 0;
    $pct = ($val - $min_val) / ($max_val - $min_val);
    if ($pct < 0) $pct = 0;
    if ($pct > 1) $pct = 1;
    if ($inverted) $pct = 1 - $pct;
    return $pct * 100;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="dataset_sensores.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'Fecha_Hora',
    'Ruido_dBFS',
    'Brillo_Medio',
    'Gravedad_Z',
    'Varianza_Giroscopio',
    'Varianza_Acelerometro',
    'Eventos_Reels',
    'Indice_Concentracion',
    'Estado_Concentracion'
]);

foreach ($rows as $row) {
    $mic       = $row['avg_mic']       !== null ? (float)$row['avg_mic']       : 0;
    $grav_z    = $row['avg_grav_z']    !== null ? (float)$row['avg_grav_z']    : 9.8;
    $gyro_var  = $row['gyro_variance'] !== null ? (float)$row['gyro_variance'] : 0;
    $accel_var = $row['accel_variance'] !== null ? (float)$row['accel_variance'] : 0;
    $bright    = $row['avg_bright']    !== null ? (float)$row['avg_bright']    : 0;
    $reels     = $row['reels_events']  !== null ? (int)$row['reels_events']    : 0;

    $isFaceDown = $grav_z > 7.5;
    $isDark     = $bright <= 0;

    $scoreMic  = getScore($mic, -45, -20, true);
    $scoreGrav = getScore(abs($grav_z), 8.0, 9.0, false);

    if ($isDark) {
        $scoreGyro  = getScore($gyro_var,  0.1,  0.8, true);
        $scoreAccel = getScore($accel_var, 0.3,  2.0, true);
    } else {
        $scoreGyro  = getScore($gyro_var,  0.01, 0.1, true);
        $scoreAccel = getScore($accel_var, 0.05, 0.3, true);
    }

    // Si falta un sensor no penalizamos (mismo comportamiento que analytics.php)
    if ($row['avg_grav_z']    === null) $scoreGrav  = 100;
    if ($row['gyro_variance'] === null) $scoreGyro  = 100;
    if ($row['accel_variance'] === null) $scoreAccel = 100;

    $index = ($scoreMic * 0.10) + ($scoreGyro * 0.40) + ($scoreGrav * 0.25) + ($scoreAccel * 0.25);

    if (!$isDark && $reels > 0) $index -= 40;
    if ($isFaceDown)             $index  = 100;

    $index = max(0, min(100, $index));

    $timestamp_ms = (int)$row['minute_bucket'] * 60000;
    $fecha_hora   = date('Y-m-d H:i:s', (int)floor($timestamp_ms / 1000));

    fputcsv($output, [
        $fecha_hora,
        $row['avg_mic']       !== null ? round($mic,       2) : '',
        $row['avg_bright']    !== null ? round($bright,    4) : '',
        $row['avg_grav_z']    !== null ? round($grav_z,    4) : '',
        $row['gyro_variance'] !== null ? round($gyro_var,  4) : '',
        $row['accel_variance'] !== null ? round($accel_var, 4) : '',
        $reels,
        round($index, 2),
        $index >= 75 ? 'Concentrado' : 'Distraido'
    ]);
}

fclose($output);
exit;

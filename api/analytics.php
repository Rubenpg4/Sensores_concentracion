<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Configuración de la base de datos
$host = '127.0.0.1';
$db = 'wsn_sensors';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión a la base de datos"]);
    exit;
}

/**
 * LÓGICA MATEMÁTICA DE FUSIÓN ESTRICTA (Sensor Fusion)
 */
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
LEFT JOIN mic_agg m ON t.minute_bucket = m.minute_bucket
LEFT JOIN grav_agg gr ON t.minute_bucket = gr.minute_bucket
LEFT JOIN gyro_agg gy ON t.minute_bucket = gy.minute_bucket
LEFT JOIN accel_agg a ON t.minute_bucket = a.minute_bucket
LEFT JOIN bright_agg b ON t.minute_bucket = b.minute_bucket
LEFT JOIN reels_agg r ON t.minute_bucket = r.minute_bucket
ORDER BY t.minute_bucket ASC
";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
} catch (\PDOException $e) {
    // Si la tabla no está creada, simplemente no hay datos
    $rows = [];
}

function getScore($val, $min_val, $max_val, $inverted = false)
{
    if ($min_val == $max_val)
        return 0;
    $pct = ($val - $min_val) / ($max_val - $min_val);
    if ($pct < 0)
        $pct = 0;
    if ($pct > 1)
        $pct = 1;
    if ($inverted)
        $pct = 1 - $pct;
    return $pct * 100;
}

$dailyConcentration = [];
$hourlyConcentration = [];

foreach ($rows as $row) {
    $mic = $row['avg_mic'] !== null ? (float) $row['avg_mic'] : 0;
    $grav_z = $row['avg_grav_z'] !== null ? (float) $row['avg_grav_z'] : 9.8;
    $gyro_var = $row['gyro_variance'] !== null ? (float) $row['gyro_variance'] : 0;
    $accel_var = $row['accel_variance'] !== null ? (float) $row['accel_variance'] : 0;
    $bright = $row['avg_bright'] !== null ? (float) $row['avg_bright'] : 0;
    $reels_events = $row['reels_events'] !== null ? (int) $row['reels_events'] : 0;

    // Corrección empírica: Boca abajo es > 5
    $isFaceDown = $grav_z > 7.5;
    $isDark = $bright <= 0;

    $scoreMic = getScore($mic, -45, -20, true);
    $scoreGrav = getScore(abs($grav_z), 8.0, 9.0, false);

    // Sensibilidad pura basada en pantalla
    if ($isDark) {
        // MODO ESTUDIO: Muy tolerante
        $scoreGyro = getScore($gyro_var, 0.1, 0.8, true);
        $scoreAccel = getScore($accel_var, 0.3, 2.0, true);
    } else {
        $scoreGyro = getScore($gyro_var, 0.01, 0.1, true);
        $scoreAccel = getScore($accel_var, 0.05, 0.3, true);
    }

    // Si faltan datos en la tabla (ej. por pruebas antiguas), los omitimos y no penalizamos
    if ($row['avg_grav_z'] === null)
        $scoreGrav = 100;
    if ($row['gyro_variance'] === null)
        $scoreGyro = 100;
    if ($row['accel_variance'] === null)
        $scoreAccel = 100;

    $concentration_index = ($scoreMic * 0.10) + ($scoreGyro * 0.40) + ($scoreGrav * 0.25) + ($scoreAccel * 0.25);

    // Inercia de Reels si la pantalla está encendida
    if (!$isDark && $reels_events > 0) {
        $concentration_index -= 40;
    }

    // Override Absoluto
    if ($isFaceDown) {
        $concentration_index = 100;
    }

    if ($concentration_index > 100)
        $concentration_index = 100;
    if ($concentration_index < 0)
        $concentration_index = 0;

    $minute_bucket = (int) $row['minute_bucket'];
    $timestamp_ms = $minute_bucket * 60000;

    // Agrupar por hora en lugar de por día
    $hour_bucket_ms = floor($timestamp_ms / 3600000) * 3600000;

    if (!isset($hourlyConcentration[$hour_bucket_ms])) {
        $hourlyConcentration[$hour_bucket_ms] = ['concentrado' => 0, 'distraido' => 0];
    }

    if ($concentration_index >= 75) {
        $hourlyConcentration[$hour_bucket_ms]['concentrado'] += 1;
    } else {
        $hourlyConcentration[$hour_bucket_ms]['distraido'] += 1;
    }
}

$flotConcentrado = [];
$flotDistraido = [];

foreach ($hourlyConcentration as $hour_ms => $counts) {
    // Sumamos media hora para centrar la barra visualmente en Flot
    $center_ms = $hour_ms + 1800000;
    $flotConcentrado[] = [$center_ms, $counts['concentrado']];
    $flotDistraido[] = [$center_ms, $counts['distraido']];
}

// Ordenar por tiempo
usort($flotConcentrado, function ($a, $b) {
    return $a[0] <=> $b[0];
});
usort($flotDistraido, function ($a, $b) {
    return $a[0] <=> $b[0];
});

// Estructura de series esperada por FlotCharts para barras apiladas
$chartData = [
    [
        "label" => "Concentrado",
        "data" => $flotConcentrado,
        "color" => "#10b981" // Esmeralda
    ],
    [
        "label" => "Distraído",
        "data" => $flotDistraido,
        "color" => "#ef4444" // Rojo
    ]
];

header('Content-Type: application/json');
echo json_encode($chartData);

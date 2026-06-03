<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    echo json_encode(["error" => "Error de conexión a la base de datos"]);
    exit;
}

$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["error" => "Payload JSON inválido"]);
    exit;
}

// Sensor Logger envía los datos masivos dentro de un array llamado 'payload'
if (!isset($data['payload']) || !is_array($data['payload'])) {
    file_put_contents(__DIR__ . '/debug_payload.log', $rawPayload . "\n\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(["error" => "Formato incorrecto. Se esperaba el objeto 'payload' de Sensor Logger."]);
    exit;
}

// Preparar los statements fuera del bucle para máxima eficiencia
$stmtAcc = $pdo->prepare("INSERT INTO accelerometer (timestamp_ms, axis_x, axis_y, axis_z) VALUES (?, ?, ?, ?)");
$stmtBrightness = $pdo->prepare("INSERT INTO brightness (timestamp_ms, brightness_level) VALUES (?, ?)");
$stmtMic = $pdo->prepare("INSERT INTO microphone (timestamp_ms, dbfs_level) VALUES (?, ?)");
$stmtGravity = $pdo->prepare("INSERT INTO gravity (timestamp_ms, axis_x, axis_y, axis_z) VALUES (?, ?, ?, ?)");
$stmtGyro = $pdo->prepare("INSERT INTO gyroscope (timestamp_ms, axis_x, axis_y, axis_z) VALUES (?, ?, ?, ?)");

// Usar transacciones para insertar cientos de filas de golpe
$pdo->beginTransaction();

try {
    foreach ($data['payload'] as $reading) {
        $name = $reading['name'] ?? '';
        
        // Sensor Logger manda tiempo en nanosegundos (ej: 1690000000000000000)
        // Lo convertimos a milisegundos dividiendo por 1,000,000
        $timestamp_ms = isset($reading['time']) ? floor($reading['time'] / 1000000) : round(microtime(true) * 1000);
        $values = $reading['values'] ?? [];

        if ($name === 'accelerometer') {
            $stmtAcc->execute([
                $timestamp_ms,
                $values['x'] ?? 0,
                $values['y'] ?? 0,
                $values['z'] ?? 0
            ]);
        } elseif ($name === 'brightness') {
            $stmtBrightness->execute([
                $timestamp_ms,
                $values['brightness'] ?? 0
            ]);
        } elseif ($name === 'microphone') {
            // El micrófono de Sensor Logger exporta dBFS (Decibelios Full Scale, valores negativos, ej: -40.5)
            $stmtMic->execute([
                $timestamp_ms,
                $values['dBFS'] ?? 0
            ]);
        } elseif ($name === 'gravity') {
            $stmtGravity->execute([
                $timestamp_ms,
                $values['x'] ?? 0,
                $values['y'] ?? 0,
                $values['z'] ?? 0
            ]);
        } elseif ($name === 'gyroscope') {
            $stmtGyro->execute([
                $timestamp_ms,
                $values['x'] ?? 0,
                $values['y'] ?? 0,
                $values['z'] ?? 0
            ]);
        }
    }
    
    $pdo->commit();
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Lote de datos sincronizado correctamente"]);

} catch (\Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Fallo al insertar en la base de datos: " . $e->getMessage()]);
}
?>

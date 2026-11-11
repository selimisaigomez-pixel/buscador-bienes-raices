<?php
// buscador.php - Conexión a MariaDB y búsqueda de bienes raíces.
// La base "bienes_raices" se crea importando bienes_raices.sql en phpMyAdmin.
header('Content-Type: application/json; charset=utf-8');

// ---- Conexión (valores por defecto de XAMPP) ----
$host = 'localhost';
$base = 'bienes_raices';
$user = 'root';
$pass = '';

// Convierte una fila de la tabla al mismo formato que data-1.json
function aFormatoJson($f) {
    return [
        'Id'            => (int) $f['id'],
        'Direccion'     => $f['direccion'],
        'Ciudad'        => $f['ciudad'],
        'Telefono'      => $f['telefono'],
        'Codigo_Postal' => $f['codigo_postal'],
        'Tipo'          => $f['tipo'],
        'Precio'        => '$' . number_format($f['precio']),
    ];
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$base;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $accion = $_REQUEST['accion'] ?? 'buscar';

    // Requerimiento 2: ciudades y tipos únicos para los menús
    if ($accion === 'filtros') {
        echo json_encode([
            'ciudades' => $pdo->query('SELECT DISTINCT ciudad FROM bienes ORDER BY ciudad')->fetchAll(PDO::FETCH_COLUMN),
            'tipos'    => $pdo->query('SELECT DISTINCT tipo FROM bienes ORDER BY tipo')->fetchAll(PDO::FETCH_COLUMN),
        ]);
        exit;
    }

    // Requerimiento 1: mostrar todos
    if ($accion === 'todos') {
        $filas = $pdo->query('SELECT * FROM bienes ORDER BY id')->fetchAll();
        echo json_encode(array_map('aFormatoJson', $filas));
        exit;
    }

    // Requerimientos 3-6: precio siempre; ciudad y tipo solo si se eligieron
    $ciudad = trim($_POST['ciudad'] ?? '');
    $tipo   = trim($_POST['tipo'] ?? '');
    $precio = $_POST['precio'] ?? '';   // el slider envía "min;max"

    $min = 0;
    $max = PHP_INT_MAX;
    $partes = explode(';', $precio);
    if (count($partes) === 2) {
        $min = (int) $partes[0];
        $max = (int) $partes[1];
    }

    $sql = 'SELECT * FROM bienes WHERE precio BETWEEN :min AND :max';
    $params = [':min' => $min, ':max' => $max];
    if ($ciudad !== '') { $sql .= ' AND ciudad = :ciudad'; $params[':ciudad'] = $ciudad; }
    if ($tipo !== '')   { $sql .= ' AND tipo = :tipo';     $params[':tipo']   = $tipo; }
    $sql .= ' ORDER BY id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(array_map('aFormatoJson', $stmt->fetchAll()));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}

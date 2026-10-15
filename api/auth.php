<?php
require 'db.php';
$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $d = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("SELECT id, nombre, email, rol, password FROM usuarios WHERE email = ?");
    $stmt->execute([$d['email']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($d['password'], $u['password'])) {
        unset($u['password']); // nunca guardar el hash en la sesión
        $_SESSION['usuario'] = $u;
        echo json_encode(['ok' => true, 'usuario' => $u]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
    }
}

if ($action === 'register') {
    $d = json_decode(file_get_contents('php://input'), true);
    $hash = password_hash($d['password'], PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password) VALUES (?,?,?)");
        $stmt->execute([$d['nombre'], $d['email'], $hash]);
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'El email ya está registrado']);
    }
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok' => true]);
}

if ($action === 'me') {
    echo json_encode(['usuario' => $_SESSION['usuario'] ?? null]);
}
?>
<?php
require 'db.php';
$action = $_GET['action'] ?? 'listar';

// Endpoint público para la portada (sin login)
if ($action === 'publicos') {
    $stmt = $pdo->query("SELECT id, titulo, descripcion, imagen,
        (SELECT COUNT(*) FROM clases cl WHERE cl.curso_id = c.id) AS total_clases
        FROM cursos c ORDER BY c.id");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

$u = auth();

if ($action === 'listar') {
    // Todos los cursos con progreso del usuario
    $stmt = $pdo->prepare("
        SELECT c.*,
          (SELECT nombre FROM usuarios pr WHERE pr.id = c.profesor_id) AS profesor,
          (SELECT COUNT(*) FROM clases cl WHERE cl.curso_id = c.id) AS total_clases,
          (SELECT COUNT(*) FROM clases cl JOIN progreso p ON p.clase_id = cl.id
             WHERE cl.curso_id = c.id AND p.usuario_id = ? AND p.completada = 1) AS completadas,
          EXISTS(SELECT 1 FROM inscripciones i WHERE i.curso_id = c.id AND i.usuario_id = ?) AS inscrito
        FROM cursos c ORDER BY c.id");
    $stmt->execute([$u['id'], $u['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === 'inscribirme') {
    $curso_id = (int)$_GET['curso_id'];
    $pdo->prepare("INSERT IGNORE INTO inscripciones (usuario_id, curso_id) VALUES (?,?)")
        ->execute([$u['id'], $curso_id]);
    echo json_encode(['ok' => true]);
}

if ($action === 'clases') {
    $curso_id = (int)$_GET['curso_id'];
    $stmt = $pdo->prepare("
        SELECT cl.*, p.completada
        FROM clases cl
        LEFT JOIN progreso p ON p.clase_id = cl.id AND p.usuario_id = ?
        WHERE cl.curso_id = ? ORDER BY cl.orden");
    $stmt->execute([$u['id'], $curso_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === 'completar') {
    $clase_id = (int)$_GET['clase_id'];
    $pdo->prepare("INSERT INTO progreso (usuario_id, clase_id, completada, completada_en)
        VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE completada=1, completada_en=NOW()")
        ->execute([$u['id'], $clase_id]);
    echo json_encode(['ok' => true]);
}

if ($action === 'certificados') {
    // Cursos 100% completados
    $stmt = $pdo->prepare("
        SELECT c.id, c.titulo,
          MAX(p.completada_en) AS fecha,
          (SELECT COUNT(*) FROM clases cl WHERE cl.curso_id = c.id) AS total,
          (SELECT COUNT(*) FROM clases cl JOIN progreso pr ON pr.clase_id = cl.id
             WHERE cl.curso_id = c.id AND pr.usuario_id = ? AND pr.completada = 1) AS done
        FROM cursos c
        JOIN clases cl ON cl.curso_id = c.id
        JOIN progreso p ON p.clase_id = cl.id AND p.usuario_id = ? AND p.completada = 1
        GROUP BY c.id HAVING total = done");
    $stmt->execute([$u['id'], $u['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
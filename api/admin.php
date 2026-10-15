<?php
require 'db.php';
$u = auth();
$es_admin = $u['rol'] === 'admin';
$es_prof  = $u['rol'] === 'profesor';
if (!$es_admin && !$es_prof) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

$action = $_GET['action'] ?? '';
$d = json_decode(file_get_contents('php://input'), true);

// El profesor solo puede tocar clases de SUS cursos
function puede_curso($pdo, $uid, $curso_id, $es_admin) {
    if ($es_admin) return true;
    $s = $pdo->prepare("SELECT 1 FROM cursos WHERE id = ? AND profesor_id = ?");
    $s->execute([$curso_id, $uid]);
    return (bool)$s->fetchColumn();
}

switch ($action) {

    // ===== GESTIÓN DE USUARIOS (solo admin) =====
    case 'listar_usuarios':
        if (!$es_admin) { http_response_code(403); echo json_encode(['error'=>'Solo admin']); exit; }
        $stmt = $pdo->prepare("
            SELECT u.id, u.nombre, u.email, u.rol, u.creado_en,
              (SELECT COUNT(*) FROM inscripciones i WHERE i.usuario_id = u.id) AS cursos_inscritos,
              (SELECT COUNT(*) FROM progreso p WHERE p.usuario_id = u.id AND p.completada = 1) AS clases_completadas
            FROM usuarios u ORDER BY FIELD(u.rol,'admin','profesor','estudiante'), u.nombre");
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'crear_usuario':   // admin crea estudiantes o profesores
        if (!$es_admin) { http_response_code(403); echo json_encode(['error'=>'Solo admin']); exit; }
        $hash = password_hash($d['password'], PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?,?,?,?)")
                ->execute([$d['nombre'], $d['email'], $hash, $d['rol']]);
            echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error'=>'El email ya está registrado']);
        }
        break;

    case 'listar_profesores':   // para el select de asignación
        $stmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'profesor' ORDER BY nombre");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'asignar_profesor':    // admin asigna un profesor a un curso
        if (!$es_admin) { http_response_code(403); echo json_encode(['error'=>'Solo admin']); exit; }
        $pdo->prepare("UPDATE cursos SET profesor_id = ? WHERE id = ?")
            ->execute([(int)$d['profesor_id'], (int)$d['curso_id']]);
        echo json_encode(['ok'=>true]);
        break;

    // ===== CURSOS =====
    case 'crear_curso':
        if ($es_admin) {
            $prof = !empty($d['profesor_id']) ? (int)$d['profesor_id'] : null;
            $pdo->prepare("INSERT INTO cursos (titulo, descripcion, imagen, profesor_id) VALUES (?,?,?,?)")
                ->execute([$d['titulo'], $d['descripcion'], $d['imagen'], $prof]);
        } else {
            // el profesor crea su propio curso
            $pdo->prepare("INSERT INTO cursos (titulo, descripcion, imagen, profesor_id) VALUES (?,?,?,?)")
                ->execute([$d['titulo'], $d['descripcion'], $d['imagen'], $u['id']]);
        }
        echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId()]);
        break;

    case 'eliminar_curso':
        if (!$es_admin) { http_response_code(403); echo json_encode(['error'=>'Solo admin']); exit; }
        $pdo->prepare("DELETE FROM cursos WHERE id=?")->execute([(int)$_GET['id']]);
        echo json_encode(['ok'=>true]);
        break;

    // ===== CLASES (admin: todas | profesor: solo las suyas) =====
    case 'clases_curso':
        $curso_id = (int)$_GET['curso_id'];
        if (!puede_curso($pdo, $u['id'], $curso_id, $es_admin)) { http_response_code(403); echo json_encode(['error'=>'No es tu curso']); exit; }
        $stmt = $pdo->prepare("SELECT * FROM clases WHERE curso_id = ? ORDER BY orden");
        $stmt->execute([$curso_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'crear_clase':
        if (!puede_curso($pdo, $u['id'], (int)$d['curso_id'], $es_admin)) { http_response_code(403); echo json_encode(['error'=>'No es tu curso']); exit; }
        $pdo->prepare("INSERT INTO clases (curso_id, titulo, descripcion, video_url, pdf_url, orden, duracion)
            VALUES (?,?,?,?,?,?,?)")
            ->execute([(int)$d['curso_id'], $d['titulo'], $d['descripcion'], $d['video_url'], $d['pdf_url'], (int)$d['orden'], $d['duracion']]);
        echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId()]);
        break;

    case 'editar_clase':
        $c = $pdo->prepare("SELECT curso_id FROM clases WHERE id=?");
        $c->execute([(int)$d['id']]);
        $curso_id = $c->fetchColumn();
        if (!$curso_id || !puede_curso($pdo, $u['id'], $curso_id, $es_admin)) { http_response_code(403); echo json_encode(['error'=>'No es tu curso']); exit; }
        $pdo->prepare("UPDATE clases SET titulo=?, descripcion=?, video_url=?, pdf_url=?, orden=?, duracion=? WHERE id=?")
            ->execute([$d['titulo'], $d['descripcion'], $d['video_url'], $d['pdf_url'], (int)$d['orden'], $d['duracion'], (int)$d['id']]);
        echo json_encode(['ok'=>true]);
        break;

    case 'eliminar_clase':
        $c = $pdo->prepare("SELECT curso_id FROM clases WHERE id=?");
        $c->execute([(int)$_GET['id']]);
        $curso_id = $c->fetchColumn();
        if (!$curso_id || !puede_curso($pdo, $u['id'], $curso_id, $es_admin)) { http_response_code(403); echo json_encode(['error'=>'No es tu curso']); exit; }
        $pdo->prepare("DELETE FROM clases WHERE id=?")->execute([(int)$_GET['id']]);
        echo json_encode(['ok'=>true]);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
}
?>
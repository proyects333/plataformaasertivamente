<?php
// Certificado de finalización - Asertiva Mente
require 'db.php';
$u = auth();
$curso_id = (int)($_GET['curso_id'] ?? 0);

// Verificar que el estudiante completó el 100% del curso
$stmt = $pdo->prepare("
    SELECT c.titulo, MAX(p.completada_en) AS fecha,
      (SELECT COUNT(*) FROM clases cl WHERE cl.curso_id = c.id) AS total,
      (SELECT COUNT(*) FROM clases cl JOIN progreso pr ON pr.clase_id = cl.id
         WHERE cl.curso_id = c.id AND pr.usuario_id = ? AND pr.completada = 1) AS done
    FROM cursos c
    JOIN clases cl ON cl.curso_id = c.id
    JOIN progreso p ON p.clase_id = cl.id AND p.usuario_id = ? AND p.completada = 1
    WHERE c.id = ?
    GROUP BY c.id");
$stmt->execute([$u['id'], $u['id'], $curso_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || $row['total'] != $row['done']) {
    http_response_code(403);
    exit('<h3 style="font-family:sans-serif;text-align:center;margin-top:80px">⚠️ Aún no has completado este curso al 100%.</h3>');
}

$nombre  = htmlspecialchars($u['nombre']);
$curso   = htmlspecialchars($row['titulo']);
$fecha   = date('d \d\e F \d\e\l Y', strtotime($row['fecha']));
$folio   = 'AM-' . date('Y') . '-' . str_pad($u['id'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($curso_id, 3, '0', STR_PAD_LEFT);
$verif   = strtoupper(substr(hash('sha256', $folio . $nombre), 0, 10));
$logo    = '../assets/img/logo.jpg';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Certificado - <?= $curso ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-family:Georgia, 'Times New Roman', serif; }
  body { background:#E8EDF3; display:flex; justify-content:center; padding:30px; }
  .cert { width:950px; background:#FDFDFB; padding:14px; box-shadow:0 10px 40px rgba(0,0,0,.25); }
  .inner { border:6px double #1E3A5F; padding:50px 60px; text-align:center; position:relative; }
  .inner::before { content:''; position:absolute; inset:10px; border:1px solid #7BC47F; pointer-events:none; }
  .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:26px; }
  .top img { width:95px; height:95px; border-radius:50%; object-fit:cover; }
  .brand b { font-size:22px; color:#1E3A5F; display:block; }
  .brand b span { color:#2E7D5B; }
  .brand small { font-size:9px; letter-spacing:4px; color:#7A8A99; }
  h1 { font-size:44px; letter-spacing:6px; color:#1E3A5F; margin:14px 0 6px; }
  .sub { font-size:13px; letter-spacing:3px; color:#7A8A99; margin-bottom:34px; }
  .otorga { font-size:16px; color:#4A5A6A; font-style:italic; }
  .nombre { font-size:38px; color:#2E7D5B; margin:20px 0; border-bottom:2px solid #7BC47F;
    display:inline-block; padding:0 40px 8px; }
  .texto { font-size:15px; color:#4A5A6A; line-height:1.9; max-width:640px; margin:0 auto; }
  .curso { font-size:24px; color:#1E3A5F; font-weight:bold; margin:22px 0 6px; }
  .duracion { font-size:13px; color:#7A8A99; margin-bottom:38px; }
  .bottom { display:flex; justify-content:space-between; align-items:flex-end; margin-top:30px; }
  .firma { text-align:center; }
  .firma .linea { width:220px; border-top:1.5px solid #1E3A5F; margin-bottom:6px; }
  .firma b { font-size:13px; color:#1E3A5F; }
  .firma small { display:block; font-size:11px; color:#7A8A99; }
  .sello { font-size:52px; }
  .folio { margin-top:22px; font-size:11px; color:#7A8A99; letter-spacing:1px; }
  .folio b { color:#1E3A5F; }
  .btn-print { position:fixed; bottom:24px; right:24px; background:#3D77BC; color:#fff;
    border:none; padding:13px 28px; border-radius:10px; font-size:15px; cursor:pointer;
    font-family:Arial, sans-serif; box-shadow:0 4px 14px rgba(0,0,0,.3); }
  @media print { body { background:#fff; padding:0; } .cert { box-shadow:none; width:100%; }
    .btn-print { display:none; } }
</style>
</head>
<body>
<div class="cert"><div class="inner">
  <div class="top">
    <img src="<?= $logo ?>" alt="logo">
    <div class="brand"><b>Asertiva <span>Mente</span></b><small>CENTRO PSICOLÓGICO</small></div>
    <div style="width:95px"></div>
  </div>

  <h1>CERTIFICADO</h1>
  <p class="sub">DE FINALIZACIÓN Y APROBACIÓN</p>

  <p class="otorga">El presente certificado se otorga a:</p>
  <div class="nombre"><?= $nombre ?></div>

  <p class="texto">Por haber completado satisfactoriamente todas las clases del diplomado
  virtual, demostrando compromiso y dedicación en su proceso de aprendizaje.</p>

  <div class="curso">“<?= $curso ?>”</div>
  <p class="duracion">Emitido el <?= $fecha ?></p>

  <div class="bottom">
    <div class="firma">
      <div class="linea"></div>
      <b>Dirección Académica</b>
      <small>Asertiva Mente · Centro Psicológico</small>
    </div>
    <div class="sello">🏆</div>
    <div class="firma">
      <div class="linea"></div>
      <b><?= $nombre ?></b>
      <small>Estudiante</small>
    </div>
  </div>

  <p class="folio">FOLIO: <b><?= $folio ?></b> &nbsp;·&nbsp; CÓDIGO DE VERIFICACIÓN: <b><?= $verif ?></b></p>
</div></div>

<button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
</body>
</html>

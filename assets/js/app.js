const API = 'api/';
let USUARIO = null, CURSOS = [], CURSO_ACTUAL = null, CLASE_ACTUAL = null;

/* ---------- helpers ---------- */
const $ = s => document.querySelector(s);
const api = async (file, params = {}, body = null) => {
  let response;
  try { response = await fetch(API + file + '?' + new URLSearchParams(params), {
    method: body ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
    ...(body ? {headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)} : {})
  }); } catch { throw new Error('No hay conexión con el servidor. Abre el aula desde localhost con Apache activo.'); }
  let data;
  try { data = await response.json(); } catch { throw new Error('El servidor no devolvió una respuesta válida. Revisa que la carpeta api esté junto a aula.html.'); }
  if (!response.ok) throw new Error(data.error || 'El servidor no pudo completar la operación.');
  return data;
};
const youtubeId = url => {
  const m = url?.match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([\w-]{11})/);
  return m ? m[1] : null;
};
const fmtFecha = f => new Date(f).toLocaleDateString('es-ES', {day:'numeric', month:'short', year:'numeric'});

/* ---------- sesión / landing ---------- */
async function init() {
  try { const r = await api('auth.php', {action:'me'}); if(r.usuario){USUARIO=r.usuario;showApp();} }
  catch { $('#login-msg').textContent='No pudimos conectar con el aula. Inténtalo de nuevo en unos minutos.'; }
}
function mostrarLogin(){ $('#login').classList.add('show'); }
function cerrarLogin(){ location.href='index.html'; }
let accesoEnCurso = false;
async function login() {
  if (accesoEnCurso) return;
  const email = $('#l-email').value.trim(), password = $('#l-pass').value;
  if (!email || !$('#l-email').validity.valid || !password) { $('#login-msg').textContent='Ingresa tu correo y contraseña.'; return; }
  accesoEnCurso = true;
  $('#login-msg').textContent='Comprobando tus datos…';
  try {
    const r = await api('auth.php', {action:'login'}, {email,password});
    if (!r.usuario) throw new Error(r.error || 'El servidor no confirmó el acceso.');
    USUARIO = r.usuario;
    showApp();
  } catch (error) {
    $('#login').hidden = false;
    $('#login').style.display = 'flex';
    $('#login').classList.add('show');
    $('#app').style.display = 'none';
    $('#login-msg').textContent=error.message || 'No se pudo ingresar. Inténtalo otra vez.';
  } finally { accesoEnCurso = false; }
}
async function register() {
  const nombre=$('#r-nombre').value.trim(), email=$('#r-email').value.trim(), password=$('#r-pass').value;
  if (!nombre || !email || !$('#r-email').validity.valid || !password) { $('#login-msg').textContent='Completa tu nombre, correo válido y contraseña.'; return; }
  $('#login-msg').textContent='Creando tu cuenta…';
  try {
    const r=await api('auth.php',{action:'register'},{nombre,email,password});
    if (!r.ok) throw new Error(r.error || 'No se pudo crear tu cuenta.');
    $('#l-email').value=email;
    $('#l-pass').value=password;
    $('#reg-fields').style.display='none';
    $('#login-msg').textContent='Cuenta creada. Ingresando al aula…';
    await login();
  } catch(error) { $('#login-msg').textContent=error.message || 'No se pudo crear la cuenta.'; }
}
async function logout() { await api('auth.php', {action:'logout'}); location.reload(); }

function showApp() {
  
  $('#login').classList.remove('show');
  $('#login').hidden = true;
  $('#login').style.display = 'none';
  $('#app').hidden = false;
  $('#app').style.display = 'flex';
  document.body.style.overflow='';
  $('#u-nombre').textContent = USUARIO.nombre.split(' ')[0] + ' ' + (USUARIO.nombre.split(' ')[1]||'');
  $('#u-rol').textContent = ({admin:'Administrador',profesor:'Profesor',estudiante:'Estudiante'})[USUARIO.rol];
  $('#u-avatar').textContent = USUARIO.nombre[0].toUpperCase();
  if (USUARIO.rol !== 'estudiante') {
    $('#menu-admin').style.display = 'block';
    if (USUARIO.rol === 'profesor') {
      $('#admin-titulo').textContent = 'Panel del profesor 👨‍🏫';
      document.querySelectorAll('.admin-only').forEach(e => e.style.display = 'none');
    }
  }
  $('#profile-name').textContent = USUARIO.nombre || '—';
  $('#profile-email').textContent = USUARIO.email || 'No disponible';
  $('#profile-role').textContent = $('#u-rol').textContent;
  $('#profile-avatar').textContent = USUARIO.nombre?.[0]?.toUpperCase() || 'A';
  go('dashboard');
}

/* ---------- navegación ---------- */
function go(vista) {
  document.querySelectorAll('.vista').forEach(v => v.style.display = 'none');
  document.querySelectorAll('.menu [data-v]').forEach(a => a.classList.remove('active'));
  $('#vista-' + vista).style.display = 'block';
  const link = document.querySelector(`.menu [data-v="${vista}"]`);
  if (link) link.classList.add('active');
  $('.topbar').style.display = ['dashboard','cursos'].includes(vista) ? 'flex' : 'none';
  if (vista === 'dashboard') loadDashboard();
  if (vista === 'cursos') loadCursos();
  if (vista === 'certificados') loadCertificados();
  if (vista === 'admin') loadAdmin();
}

/* ---------- dashboard / cursos ---------- */
async function loadCursos() {
  try {
    const data = await api('cursos.php');
    if (!Array.isArray(data)) throw new Error('No se pudo consultar la lista de cursos.');
    CURSOS = data;
    renderCursos($('#grid-cursos'));
    const g2 = $('#grid-cursos2'); if (g2) renderCursos(g2);
  } catch(error) {
    for (const selector of ['#grid-cursos','#grid-cursos2']) {
      const grid=$(selector); if (grid) { grid.replaceChildren(); const message=document.createElement('p'); message.className='empty'; message.textContent=error.message; grid.append(message); }
    }
  }
}
function loadDashboard() {
  loadCursos();
  $('#hero-nombre').textContent = USUARIO.nombre.split(' ')[0];
}
function renderCursos(container) {
  const query=($('#aula-search').value||'').toLocaleLowerCase('es');
  container.innerHTML = CURSOS.filter(c => { const filter=$('#progress-filter').value; const done=Number(c.total_clases)>0 && Number(c.completadas)>=Number(c.total_clases); return (container.id!=='grid-cursos' || Number(c.inscrito)===1) && (c.titulo||'').toLocaleLowerCase('es').includes(query) && (filter==='all' || (filter==='done' ? done : !done)); }).map(c => {
    const pct = c.total_clases ? Math.round(c.completadas / c.total_clases * 100) : 0;
    return `<div class="curso-card" role="button" tabindex="0" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openCurso(${c.id})}" onclick="openCurso(${c.id})">
      <img src="${c.imagen || 'assets/img/formacion.jpg'}" alt="">
      <div class="body">
        <h3>${c.titulo}</h3><p>${c.descripcion || ''}</p>
        <div class="progress"><div style="width:${pct}%"></div></div>
        <div class="meta"><span>${c.completadas} de ${c.total_clases} clases</span><span>${pct}%</span></div>
      </div></div>`;
  }).join('') || `<div class="empty account-empty"><span class="empty-symbol">▤</span><h3>No hay cursos para mostrar</h3><p>Explora los cursos disponibles o cambia los filtros.</p><button class="btn btn-inline" onclick="document.querySelector('#progress-filter').value='all';document.querySelector('#aula-search').value='';go('cursos')">Explorar cursos</button></div>`;
}

/* ---------- reproductor ---------- */
async function openCurso(id) {
  CURSO_ACTUAL = CURSOS.find(c => c.id == id);
  if (!CURSO_ACTUAL.inscrito) await api('cursos.php', {action:'inscribirme', curso_id:id});
  CLASE_ACTUAL = await api('cursos.php', {action:'clases', curso_id:id});
  go('player');
  $('#curso-titulo').textContent = CURSO_ACTUAL.titulo;
  renderListaClases();
  if (CLASE_ACTUAL.length) selectClase(0);
}
function renderListaClases() {
  const done = CLASE_ACTUAL.filter(c => c.completada == 1).length;
  $('#lista-clases').innerHTML = `<h3>📚 Contenido del curso</h3>
    <p style="font-size:12px;color:var(--gris);margin-bottom:10px">${done} de ${CLASE_ACTUAL.length} clases (${CLASE_ACTUAL.length?Math.round(done/CLASE_ACTUAL.length*100):0}%)</p>` +
    CLASE_ACTUAL.map((c, i) => `
      <div class="clase-item ${c.completada==1?'done':''}" id="ci-${i}" onclick="selectClase(${i})">
        <div class="num">${c.completada==1?'✓':i+1}</div>
        <div class="info"><b>${c.titulo}</b><small>🕐 ${c.duracion||''}</small></div>
      </div>`).join('');
}
function selectClase(i) {
  CLASE_IDX = i;
  document.querySelectorAll('.clase-item').forEach(e => e.classList.remove('active'));
  $('#ci-'+i)?.classList.add('active');
  const c = CLASE_ACTUAL[i];
  const yt = youtubeId(c.video_url);
  $('#p-video').innerHTML = yt
    ? `<iframe src="https://www.youtube.com/embed/${yt}" allowfullscreen></iframe>`
    : `<div style="padding:60px;text-align:center;color:var(--gris)">Video no disponible aún 🎬</div>`;
  $('#p-titulo').textContent = c.titulo;
  $('#p-desc').textContent = c.descripcion || '';
  $('#p-pdf').style.display = c.pdf_url ? 'inline-flex' : 'none';
  if (c.pdf_url) $('#p-pdf').href = c.pdf_url;
  $('#btn-completar').style.display = c.completada == 1 ? 'none' : 'inline-block';
}
async function completarClase() {
  const c = CLASE_ACTUAL[CLASE_IDX];
  await api('cursos.php', {action:'completar', clase_id:c.id});
  c.completada = 1;
  renderListaClases();
  $('#ci-'+CLASE_IDX)?.classList.add('active');
  $('#btn-completar').style.display = 'none';
}

/* ---------- certificados ---------- */
async function loadCertificados() {
  const certs = await api('cursos.php', {action:'certificados'});
  $('#grid-cert').innerHTML = certs.length ? certs.map(c => `
    <div class="cert-card">
      <div class="medal">🏆</div>
      <h3>${c.titulo}</h3>
      <p>Completado el ${fmtFecha(c.fecha)}</p>
      <button class="btn" onclick="window.open('api/certificado.php?curso_id=${c.id}', '_blank')">⬇ Descargar certificado</button>
    </div>`).join('')
    : `<div class="empty">Aún no tienes cursos completados. ¡Sigue aprendiendo! 💪</div>`;
}

/* ---------- admin / profesor ---------- */
async function loadAdmin() {
  await loadCursos();
  const opts = CURSOS.map(c => `<option value="${c.id}">${c.titulo}</option>`).join('');
  $('#f-curso').innerHTML = opts;
  $('#pc-curso').innerHTML = opts;
  $('#a-curso').innerHTML = opts;
  if (USUARIO.rol === 'admin') {
    const profs = await api('admin.php', {action:'listar_profesores'});
    $('#a-prof').innerHTML = profs.length
      ? profs.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('')
      : '<option value="">(crea un profesor primero)</option>';
    cargarUsuarios();
  }
  cargarClasesAdmin();
}
async function cargarUsuarios() {
  const us = await api('admin.php', {action:'listar_usuarios'});
  const tag = {admin:'adm', profesor:'prof', estudiante:'est'};
  const txt = {admin:'Admin', profesor:'Profesor', estudiante:'Estudiante'};
  $('#tabla-usuarios').innerHTML = us.map(u => `<tr>
    <td><b>${u.nombre}</b></td><td>${u.email}</td>
    <td><span class="tag ${tag[u.rol]||'est'}">${txt[u.rol]||u.rol}</span></td>
    <td>${u.cursos_inscritos}</td><td>${u.clases_completadas}</td>
    <td>${fmtFecha(u.creado_en)}</td></tr>`).join('');
}
async function crearUsuario() {
  const r = await api('admin.php', {action:'crear_usuario'}, {
    nombre: $('#nu-nombre').value, email: $('#nu-email').value,
    password: $('#nu-pass').value, rol: $('#nu-rol').value});
  alert(r.ok ? '✅ Usuario creado' : ('❌ ' + (r.error || 'Error')));
  if (r.ok) { $('#nu-nombre').value = $('#nu-email').value = $('#nu-pass').value = ''; cargarUsuarios(); loadAdmin(); }
}
async function asignarProfesor() {
  if (!$('#a-prof').value) return alert('Primero crea un usuario con rol Profesor');
  await api('admin.php', {action:'asignar_profesor'}, {
    curso_id: $('#a-curso').value, profesor_id: $('#a-prof').value});
  alert('✅ Profesor asignado al curso');
}
async function crearCurso() {
  const r = await api('admin.php', {action:'crear_curso'}, {
    titulo: $('#nc-titulo').value, descripcion: $('#nc-desc').value, imagen: $('#nc-img').value});
  alert(r.ok ? '✅ Curso creado' : ('❌ ' + (r.error || 'Error')));
  if (r.ok) { $('#nc-titulo').value = $('#nc-desc').value = $('#nc-img').value = ''; loadAdmin(); }
}
async function cargarClasesAdmin() {
  const curso_id = $('#pc-curso').value;
  if (!curso_id) return;
  const clases = await api('admin.php', {action:'clases_curso', curso_id});
  $('#tabla-clases').innerHTML = clases.map(c => `<tr>
    <td>${c.orden}</td><td><b>${c.titulo}</b></td>
    <td>${c.video_url ? '▶️ YouTube' : '—'}</td>
    <td>${c.pdf_url ? '📄 Drive' : '—'}</td>
    <td>${c.duracion || ''}</td>
    <td>
      <button class="mini-btn edit" onclick='llenarEdicion(${JSON.stringify(c)})'>✏️ Editar</button>
      <button class="mini-btn del" onclick="eliminarClase(${c.id})">🗑 Eliminar</button>
    </td></tr>`).join('') || '<tr><td colspan="6" style="color:var(--gris)">Este curso aún no tiene clases</td></tr>';
}
async function guardarClase() {
  const datos = {
    curso_id: $('#f-curso').value, titulo: $('#nl-titulo').value, descripcion: $('#nl-desc').value,
    video_url: $('#nl-video').value, pdf_url: $('#nl-pdf').value,
    orden: $('#nl-orden').value || 1, duracion: $('#nl-dur').value};
  const id = $('#nl-id').value;
  const r = id
    ? await api('admin.php', {action:'editar_clase'}, {...datos, id})
    : await api('admin.php', {action:'crear_clase'}, datos);
  alert(r.ok ? (id ? '✅ Clase actualizada' : '✅ Clase agregada') : ('❌ ' + (r.error || 'Error')));
  if (r.ok) { cancelarEdicion(); cargarClasesAdmin(); }
}
function llenarEdicion(c) {
  $('#nl-id').value = c.id;
  $('#f-curso').value = c.curso_id;
  $('#nl-titulo').value = c.titulo;
  $('#nl-desc').value = c.descripcion || '';
  $('#nl-video').value = c.video_url || '';
  $('#nl-pdf').value = c.pdf_url || '';
  $('#nl-orden').value = c.orden;
  $('#nl-dur').value = c.duracion || '';
  $('#clase-form-titulo').textContent = '✏️ Editando: ' + c.titulo;
  $('#btn-guardar-clase').textContent = 'Guardar cambios';
  $('#btn-cancelar-edit').style.display = 'inline-block';
  window.scrollTo({top: 0, behavior: 'smooth'});
}
function cancelarEdicion() {
  $('#nl-id').value = '';
  ['nl-titulo','nl-desc','nl-video','nl-pdf','nl-orden','nl-dur'].forEach(i => $('#'+i).value = '');
  $('#clase-form-titulo').textContent = '➕ Agregar clase (pega la URL de YouTube y el link de Drive)';
  $('#btn-guardar-clase').textContent = 'Agregar clase';
  $('#btn-cancelar-edit').style.display = 'none';
}
async function eliminarClase(id) {
  if (!confirm('¿Eliminar esta clase?')) return;
  const r = await api('admin.php', {action:'eliminar_clase', id});
  alert(r.ok ? '🗑 Clase eliminada' : ('❌ ' + (r.error || 'Error')));
  cargarClasesAdmin();
}

$('#aula-search')?.addEventListener('input',()=>{renderCursos($('#grid-cursos'));renderCursos($('#grid-cursos2'));});
$('#l-pass').addEventListener('keydown',e=>{if(e.key==='Enter')login();});
$('#progress-filter').addEventListener('change',()=>{renderCursos($('#grid-cursos'));renderCursos($('#grid-cursos2'));});
/* arranque */
init();

<?php
/* ===== Fișiere încărcate: stocate în afara directorului public, descărcate doar prin PHP cu verificarea drepturilor ===== */
const UP_MAX = 20 * 1024 * 1024; // 20 MB / fișier
const UP_TYPES = ['pdf' => ['application/pdf'], 'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream', 'application/CDFV2'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream', 'application/CDFV2'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'heic' => ['image/heic', 'image/heif', 'application/octet-stream'], 'mp4' => ['video/mp4'], 'mov' => ['video/quicktime']];
const UP_ENT = ['firme', 'puncte', 'angajati', 'cursuri', 'lectii', 'echipamente', 'audituri', 'neconformitati', 'documente', 'tichete_msg', 'mesaje'];
function up_dir(): string { $d = getenv('UPLOAD_DIR') ?: __DIR__ . '/uploads'; if (!is_dir($d)) { @mkdir($d, 0750, true); @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n"); } return $d; }
function migrate_uploads(): void {
    $id = match (drv()) { 'pgsql' => 'SERIAL PRIMARY KEY', 'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY', default => 'INTEGER PRIMARY KEY AUTOINCREMENT' };
    db()->exec("CREATE TABLE IF NOT EXISTS fisiere (id $id, entitate VARCHAR(30), entitate_id INT, firma_id INT NULL, nume_original VARCHAR(255), nume_stocat VARCHAR(80), mime VARCHAR(120), marime INT, autor_k VARCHAR(20), created_at VARCHAR(20))");
}
function up_accept(): string { return '.' . implode(',.', array_keys(UP_TYPES)) . ',image/*,video/mp4'; }
function up_hint(): string { return 'PDF, Word, Excel, imagini (JPG/PNG/HEIC), video MP4/MOV · max. 20 MB/fișier · se pot alege mai multe'; }
function up_input(string $name = 'fisiere'): string {
    return '<div class="upbox"><label class="btn ghost sm filebtn">📎 Alege fișiere<input type="file" name="' . $name . '[]" multiple accept="' . up_accept() . '" hidden></label><label class="btn ghost sm filebtn">📷 Fotografiază<input type="file" name="' . $name . '[]" accept="image/*" capture="environment" hidden></label><span class="upnames muted small"></span><small class="muted">' . up_hint() . '</small></div>';
}
/** salvează fișierele trimise în $_FILES[$field]; întoarce [nr salvate, erori] */
function up_save(string $ent, int $entId, ?int $firma, string $field = 'fisiere'): array {
    if (!in_array($ent, UP_ENT, true) || empty($_FILES[$field]['name'])) return [0, []];
    $F = $_FILES[$field]; $n = 0; $err = []; $fi = new finfo(FILEINFO_MIME_TYPE);
    foreach ((array)$F['name'] as $i => $orig) {
        $e = (int)((array)$F['error'])[$i]; if ($e === UPLOAD_ERR_NO_FILE) continue;
        $orig = mb_substr(basename((string)$orig), 0, 200); $tmp = ((array)$F['tmp_name'])[$i]; $size = (int)((array)$F['size'])[$i];
        if ($e === UPLOAD_ERR_INI_SIZE || $e === UPLOAD_ERR_FORM_SIZE || $size > UP_MAX) { $err[] = "$orig: depășește 20 MB."; continue; }
        if ($e !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) { $err[] = "$orig: încărcare eșuată."; continue; }
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION)); $mime = $fi->file($tmp) ?: '';
        if (!isset(UP_TYPES[$ext]) || !in_array($mime, UP_TYPES[$ext], true)) { $err[] = "$orig: tip de fișier nepermis."; continue; }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($tmp, up_dir() . '/' . $stored)) { $err[] = "$orig: nu a putut fi salvat."; continue; }
        @chmod(up_dir() . '/' . $stored, 0640);
        insert('fisiere', ['entitate' => $ent, 'entitate_id' => $entId, 'firma_id' => $firma, 'nume_original' => $orig, 'nume_stocat' => $stored, 'mime' => $mime, 'marime' => $size, 'autor_k' => user() ? me_k() : null, 'created_at' => date('Y-m-d H:i')]); $n++;
    }
    return [$n, $err];
}
function up_flash(array $r): void { if ($r[0]) flash($r[0] . ' fișier(e) încărcat(e).'); foreach ($r[1] as $e) flash($e, 'err'); }
function up_files(string $ent, int $id): array { return rows("SELECT * FROM fisiere WHERE entitate=? AND entitate_id=? ORDER BY id", [$ent, $id]); }
function up_delete_for(string $ent, int $id): void { foreach (up_files($ent, $id) as $f) { @unlink(up_dir() . '/' . $f['nume_stocat']); q("DELETE FROM fisiere WHERE id=?", [$f['id']]); } }
function up_can(array $f): bool {
    if (!user()) return false; if (is_staff()) return true; $sf = scope_firma(); $ent = $f['entitate']; $id = (int)$f['entitate_id'];
    if (in_array($ent, ['cursuri', 'lectii'])) return true;
    if ($ent === 'documente') { $d = row("SELECT * FROM documente WHERE id=?", [$id]); return $d && doc_access($d); }
    if ($ent === 'mesaje') { $m = row("SELECT from_k, to_k FROM mesaje WHERE id=?", [$id]); return $m && in_array(me_k(), [$m['from_k'], $m['to_k']], true); }
    if ($ent === 'tichete_msg') { $t = row("SELECT t.autor_k FROM tichete_msg m JOIN tichete t ON t.id=m.tichet_id WHERE m.id=?", [$id]); return $t && $t['autor_k'] === me_k(); }
    if (role() === 'client') return (int)$f['firma_id'] === $sf;
    if (role() === 'angajat') return $ent === 'angajati' && $id === (int)user()['angajat_id'];
    return false;
}
function up_url(array $f): string { return url('fisier', ['id' => $f['id']]); }
function up_list(array $files, bool $del = false, string $back = ''): string {
    $files = array_values(array_filter($files, 'up_can')); if (!$files) return '';
    $h = '<ul class="files">';
    foreach ($files as $f) { $img = str_starts_with((string)$f['mime'], 'image/') && $f['mime'] !== 'image/heic' && $f['mime'] !== 'image/heif';
        $h .= '<li>' . ($img ? '<a href="' . up_url($f) . '" target="_blank"><img src="' . up_url($f) . '" alt="" loading="lazy"></a>' : (str_starts_with((string)$f['mime'], 'video/') ? '<video controls preload="metadata" src="' . up_url($f) . '"></video>' : '')) . '<a href="' . up_url($f) . '" target="_blank">📄 ' . h($f['nume_original']) . '</a> <small class="muted">' . number_format($f['marime'] / 1048576, 1, ',', '') . ' MB</small>'
            . ($del && (is_staff() || $f['autor_k'] === me_k() || role() === 'client') ? ' ' . post_btn(url('fisier_sterge', ['id' => $f['id'], 'back' => $back]), '✕', 'sm danger', 'Ștergeți fișierul ' . $f['nume_original'] . '?') : '') . '</li>'; }
    return $h . '</ul>';
}
function r_fisier(): void {
    $f = row("SELECT * FROM fisiere WHERE id=?", [(int)($_GET['id'] ?? 0)]);
    if (!$f || !up_can($f)) { http_response_code(404); exit('Fișier inexistent sau fără drept de acces.'); }
    $p = up_dir() . '/' . basename($f['nume_stocat']); if (!is_file($p)) { http_response_code(404); exit('Fișier lipsă.'); }
    session_write_close(); $size = filesize($p); $start = 0; $end = $size - 1;
    $inline = preg_match('#^(image/(jpeg|png|webp)|application/pdf|video/)#', $f['mime']);
    header('Content-Type: ' . $f['mime']); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=3600'); header('Accept-Ranges: bytes');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($f['nume_original']));
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') { $start = (int)$m[1]; if ($m[2] !== '') $end = min((int)$m[2], $end); } else { $start = max(0, $size - (int)$m[2]); }
        if ($start > $end) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
        http_response_code(206); header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));
    $fh = fopen($p, 'rb'); fseek($fh, $start); $left = $end - $start + 1;
    while ($left > 0 && !feof($fh)) { $chunk = fread($fh, min(1048576, $left)); echo $chunk; $left -= strlen($chunk); flush(); }
    exit;
}
function r_fisier_sterge(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    $f = row("SELECT * FROM fisiere WHERE id=?", [(int)($_GET['id'] ?? 0)]);
    if ($f && up_can($f) && (is_staff() || $f['autor_k'] === me_k() || role() === 'client')) { @unlink(up_dir() . '/' . basename($f['nume_stocat'])); q("DELETE FROM fisiere WHERE id=?", [$f['id']]); flash('Fișier șters.'); }
    $b = (string)($_GET['back'] ?? ''); redirect(str_starts_with($b, '?r=') ? $b : url('dashboard'));
}
function r_doc_upload(): void {
    require_role('superadmin', 'inspector', 'client'); $d = row("SELECT * FROM documente WHERE id=?", [(int)$_GET['id']]);
    if (!$d || !doc_access($d)) redirect(url('documente'));
    up_flash(up_save('documente', (int)$d['id'], (int)$d['firma_id'])); redirect(url('doc', ['id' => $d['id']]));
}

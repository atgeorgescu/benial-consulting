<?php
/* ===== ANAF (completare automată după CUI) + Asistent AI Grok (xAI) ===== */
function migrate_ai(): void {
    $id = match (drv()) { 'pgsql' => 'SERIAL PRIMARY KEY', 'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY', default => 'INTEGER PRIMARY KEY AUTOINCREMENT' };
    db()->exec("CREATE TABLE IF NOT EXISTS ai_usage (id $id, user_k VARCHAR(20), zi VARCHAR(10), tokens INT, created_at VARCHAR(20))");
}
function json_out(array $a, int $code = 200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function http_post_json(string $url, array $body, array $headers, int $timeout): array {
    if (!function_exists('curl_init')) {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers)), 'content' => json_encode($body, JSON_UNESCAPED_UNICODE), 'timeout' => $timeout, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx); $code = 0; foreach ($http_response_header ?? [] as $hl) if (preg_match('#^HTTP/\S+ (\d+)#', $hl, $m)) $code = (int)$m[1];
        return [$code, $res === false ? null : json_decode((string)$res, true), $res === false ? 'conexiune eșuată' : ''];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers)]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    return [$code, $res === false ? null : json_decode((string)$res, true), $err];
}
/* ---- ANAF ---- */
function r_anaf(): void {
    require_role('superadmin', 'inspector', 'client');
    $cui = preg_replace('/\D/', '', (string)($_GET['cui'] ?? ''));
    if (strlen($cui) < 2 || strlen($cui) > 10) json_out(['ok' => false, 'error' => 'CUI invalid (2–10 cifre, fără RO).'], 400);
    if (isset($_SESSION['anaf'][$cui])) json_out($_SESSION['anaf'][$cui]);
    [$code, $d, $err] = http_post_json('https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva', [['cui' => (int)$cui, 'data' => date('Y-m-d')]], [], 12);
    if (is_array($d) && empty($d['found']) && !empty($d['notFound'])) json_out(['ok' => false, 'error' => 'CUI-ul nu a fost găsit în baza ANAF.'], 404);
    if ($code !== 200 || !is_array($d)) json_out(['ok' => false, 'error' => 'Serviciul ANAF nu răspunde momentan (' . ($code ?: 'fără conexiune') . '). Încercați din nou sau completați manual.'], 502);
    $f = $d['found'][0] ?? null;
    if (!$f) json_out(['ok' => false, 'error' => 'CUI-ul nu a fost găsit în baza ANAF.'], 404);
    $g = $f['date_generale'] ?? []; $s = $f['adresa_sediu_social'] ?? [];
    $loc = trim((string)($s['sdenumire_Localitate'] ?? '')); $jud = trim((string)($s['sdenumire_Judet'] ?? ''));
    $out = ['ok' => true, 'denumire' => $g['denumire'] ?? '', 'cui' => (($f['inregistrare_scop_Tva']['scpTVA'] ?? false) ? 'RO' : '') . $cui, 'reg_com' => $g['nrRegCom'] ?? '', 'caen' => $g['cod_CAEN'] ?? '', 'adresa' => $g['adresa'] ?? '', 'telefon' => $g['telefon'] ?? '', 'localitate' => $loc, 'judet' => $jud,
        'tva' => ($f['inregistrare_scop_Tva']['scpTVA'] ?? false) ? 'Da' : 'Nu', 'iban' => $g['iban'] ?? '', 'stare' => $g['stare_inregistrare'] ?? '', 'inactiv' => (bool)($f['stare_inactiv']['statusInactivi'] ?? false)];
    $_SESSION['anaf'][$cui] = $out; json_out($out);
}
/* ---- Asistent AI ---- */
function ai_limit(): int { $r = role(); return (int)setare('ai_limit_' . ($r === 'angajat' ? 'angajat' : ($r === 'superadmin' ? 'admin' : 'staff')), $r === 'angajat' ? '15' : ($r === 'superadmin' ? '100' : '40')); }
function ai_used_today(): int { return (int)val("SELECT COUNT(*) FROM ai_usage WHERE user_k=? AND zi=?", [me_k(), date('Y-m-d')]); }
function r_ai(): void {
    if (!user()) json_out(['ok' => false, 'error' => 'Autentificare necesară.'], 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Metodă invalidă.'], 405);
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    if (($in['csrf'] ?? '') !== csrf()) json_out(['ok' => false, 'error' => 'Sesiune expirată. Reîncărcați pagina.'], 400);
    if (setare('ai_enabled', '1') !== '1') json_out(['ok' => false, 'error' => 'Troti este dezactivat de administrator.'], 403);
    $key = getenv('XAI_API_KEY'); if (!$key) json_out(['ok' => false, 'error' => 'Troti nu este configurat.'], 503);
    $lim = ai_limit(); $used = ai_used_today(); if ($used >= $lim) json_out(['ok' => false, 'error' => "Ați atins limita zilnică de $lim întrebări. Reveniți mâine.", 'used' => $used, 'limit' => $lim], 429);
    $hist = array_slice(array_values(array_filter((array)($in['messages'] ?? []), fn($m) => is_array($m) && in_array($m['role'] ?? '', ['user', 'assistant'], true))), -10);
    $msgs = []; foreach ($hist as $m) $msgs[] = ['role' => $m['role'], 'content' => mb_substr((string)$m['content'], 0, 4000)];
    if (!$msgs || end($msgs)['role'] !== 'user') json_out(['ok' => false, 'error' => 'Scrieți o întrebare.'], 400);
    $form = array_slice((array)($in['form'] ?? []), 0, 60); $fdesc = '';
    foreach ($form as $f) if (is_array($f) && preg_match('/^[a-z_0-9\[\]]+$/', (string)($f['name'] ?? ''))) $fdesc .= '- ' . $f['name'] . ' („' . mb_substr((string)($f['label'] ?? ''), 0, 80) . '”' . (!empty($f['options']) ? ', opțiuni: ' . mb_substr(implode(' | ', array_slice((array)$f['options'], 0, 25)), 0, 600) : '') . '): valoare curentă „' . mb_substr((string)($f['value'] ?? ''), 0, 200) . "”\n";
    $sys = "Te numești Troti și ești asistentul AI al platformei BENIAL CONSULTING; când te prezinți sau ești întrebat cine ești, spui că ești Troti, fără să numești alte modele sau companii (securitate și sănătate în muncă – SSM, PSI și situații de urgență, România). Răspunzi în limba română, concis și practic. Te bazezi pe Legea 319/2006, HG 1425/2006, Legea 307/2006, OMAI 163/2007, OMAI 712/2005 și alte acte normative românești; când nu ești sigur, spui asta și recomanzi verificarea cu un specialist/inspector SSM. Nu inventa numere de acte normative sau articole. Nu ceri și nu repeți date personale (CNP etc.) inutil."
        . ($fdesc ? "\n\nUtilizatorul este pe pagina „" . mb_substr((string)($in['page'] ?? ''), 0, 120) . "” cu un formular. Câmpurile formularului:\n$fdesc\nDacă utilizatorul îți cere să completezi sau să sugerezi valori, răspunde scurt și adaugă la final, pe o linie separată, exact formatul: <<<FILL {\"nume_camp\": \"valoare\", ...}>>> folosind doar numele de câmpuri de mai sus (pentru câmpurile de tip listă folosește exact una dintre opțiuni; datele în format AAAA-LL-ZZ). Nu completa CNP, parole sau date pe care nu le cunoști." : '');
    [$code, $d, $err] = http_post_json('https://api.x.ai/v1/chat/completions', ['model' => getenv('XAI_MODEL') ?: 'grok-4.20-0309-non-reasoning', 'messages' => array_merge([['role' => 'system', 'content' => $sys]], $msgs), 'max_tokens' => 900, 'temperature' => 0.3], ['Authorization: Bearer ' . $key], 90);
    if ($code !== 200 || !isset($d['choices'][0]['message']['content'])) { error_log('BENIAL AI: HTTP ' . $code . ' ' . ($d['error']['message'] ?? $d['error'] ?? $err ?? '')); json_out(['ok' => false, 'error' => 'Troti nu este disponibil momentan. Încercați din nou.'], 502); }
    $txt = (string)$d['choices'][0]['message']['content']; $fill = null;
    if (preg_match('/<<<FILL\s*(\{.*?\})\s*>>>/s', $txt, $m)) { $fill = json_decode($m[1], true); $txt = trim(str_replace($m[0], '', $txt)); $names = array_column($form, 'name');
        if (is_array($fill)) $fill = array_filter($fill, fn($v, $k) => in_array($k, $names, true) && is_scalar($v) && !in_array($k, ['cnp', 'password', 'username'], true), ARRAY_FILTER_USE_BOTH); }
    insert('ai_usage', ['user_k' => me_k(), 'zi' => date('Y-m-d'), 'tokens' => (int)($d['usage']['total_tokens'] ?? 0), 'created_at' => date('Y-m-d H:i')]);
    json_out(['ok' => true, 'reply' => $txt, 'fill' => $fill ?: null, 'used' => $used + 1, 'limit' => $lim]);
}
function ai_widget(): string {
    if (!user() || setare('ai_enabled', '1') !== '1' || !getenv('XAI_API_KEY')) return '';
    return '<button type="button" class="ai-fab noprint" id="ai-fab" title="Troti – asistent AI">🤖 Troti</button><div class="ai-panel noprint" id="ai-panel" hidden data-csrf="' . csrf() . '" data-url="' . url('ai') . '"><div class="ai-head"><b>Troti · asistent AI BENIAL</b> <small class="muted" id="ai-quota"></small><button type="button" class="btn sm ghost" id="ai-close">✕</button></div><div class="ai-log" id="ai-log"><div class="ai-m bot">Bună! Sunt Troti. Vă pot ajuta cu întrebări despre SSM/PSI sau cu completarea formularului de pe această pagină (ex. „completează riscurile pentru un depozit”). Răspunsurile sunt orientative – verificați-le.</div></div><form id="ai-form"><textarea id="ai-in" rows="2" placeholder="Întrebați ceva…" required></textarea><button class="btn sm">Trimite</button></form></div>';
}
function r_ai_setari(bool $post): void {
    require_role('superadmin');
    if ($post) { set_setare('ai_enabled', !empty($_POST['ai_enabled']) ? '1' : '0'); foreach (['admin', 'staff', 'angajat'] as $k) set_setare('ai_limit_' . $k, (string)max(0, min(1000, (int)($_POST['ai_limit_' . $k] ?? 0)))); flash('Setări AI salvate.'); redirect(url('ai_setari')); }
    $u = rows("SELECT user_k, COUNT(*) AS n, SUM(tokens) AS t FROM ai_usage WHERE zi>=? GROUP BY user_k ORDER BY n DESC", [date('Y-m-01')]); $ut = '';
    foreach ($u as $x) $ut .= '<tr><td>' . h(k_name($x['user_k'])) . '</td><td>' . (int)$x['n'] . '</td><td>' . (int)$x['t'] . '</td></tr>';
    page('Troti (asistent AI) – setări', '<form method="post" class="card form">' . csrf_field() . '<p>Stare cheie API: ' . (getenv('XAI_API_KEY') ? '<span class="b b-green">configurată pe server</span>' : '<span class="b b-red">lipsă</span>') . ' · model ' . h(getenv('XAI_MODEL') ?: 'grok-4.20-0309-non-reasoning') . '</p><label class="chk"><input type="checkbox" name="ai_enabled" value="1"' . (setare('ai_enabled', '1') === '1' ? ' checked' : '') . '> Troti (asistent AI) activ</label><div class="grid"><label>Limită zilnică super-admin<input type="number" name="ai_limit_admin" value="' . h(setare('ai_limit_admin', '100')) . '"></label><label>Limită zilnică inspector / client<input type="number" name="ai_limit_staff" value="' . h(setare('ai_limit_staff', '40')) . '"></label><label>Limită zilnică angajat<input type="number" name="ai_limit_angajat" value="' . h(setare('ai_limit_angajat', '15')) . '"></label></div><div class="savebar"><button class="btn">Salvează</button></div></form><div class="card p0"><h2 class="pad">Utilizare luna curentă</h2><table class="tbl"><thead><tr><th>Utilizator</th><th>Întrebări</th><th>Tokeni</th></tr></thead><tbody>' . ($ut ?: '<tr><td colspan="3" class="empty">Nicio utilizare.</td></tr>') . '</tbody></table></div>');
}

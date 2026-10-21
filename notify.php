<?php
/* ===== Notificări e-mail BENIAL (SMTP prin PHPMailer) ===== */
require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';

const NOTIF_TYPES = ['semnare' => 'Documente de semnat (angajat / CLM)', 'digest' => 'Rezumat zilnic: instruiri scadente (7 zile) și depășite', 'echipamente' => 'Verificări echipamente / stingătoare în ≤30 zile (în rezumatul zilnic)', 'test' => 'Instruire / test alocat angajatului', 'mesaj' => 'Mesaj nou primit', 'tichet' => 'Tichet suport nou / răspuns'];

function migrate_notify(): void {
    $id = match (drv()) { 'pgsql' => 'SERIAL PRIMARY KEY', 'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY', default => 'INTEGER PRIMARY KEY AUTOINCREMENT' };
    db()->exec("CREATE TABLE IF NOT EXISTS setari (id $id, cheie VARCHAR(60), valoare VARCHAR(255))");
    db()->exec("CREATE TABLE IF NOT EXISTS mail_log (id $id, cheie VARCHAR(190), tip VARCHAR(20), catre VARCHAR(190), subiect VARCHAR(255), status VARCHAR(10), eroare VARCHAR(255), created_at VARCHAR(20))");
    foreach (['users' => ['notif_email' => 'VARCHAR(190)', 'email_optout' => 'INT DEFAULT 0'], 'angajati' => ['email_optout' => 'INT DEFAULT 0']] as $t => $cs) foreach ($cs as $c => $def) if (!has_col($t, $c)) db()->exec("ALTER TABLE $t ADD COLUMN $c $def");
}
function setare(string $k, ?string $def = null): ?string { $v = val("SELECT valoare FROM setari WHERE cheie=?", [$k]); return $v === null ? $def : (string)$v; }
function set_setare(string $k, string $v): void { if (val("SELECT COUNT(*) FROM setari WHERE cheie=?", [$k])) q("UPDATE setari SET valoare=? WHERE cheie=?", [$v, $k]); else insert('setari', ['cheie' => $k, 'valoare' => $v]); }
function notif_on(string $tip): bool { return setare('mail_enabled', '1') === '1' && setare('notif_' . $tip, '1') === '1'; }
function app_url(): string { return rtrim(setare('app_url', getenv('APP_URL') ?: (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . '/' : '')), '/') . '/'; }
function unsub_token(string $k): string { return substr(hash_hmac('sha256', $k, (string)(getenv('APP_SECRET') ?: 'benial')), 0, 24); }

/** destinatar pentru o cheie u:ID / a:ID; null dacă nu are e-mail sau s-a dezabonat */
function recipient(string $k): ?array {
    [$t, $i] = explode(':', $k) + ['', 0];
    if ($t === 'a') { $a = row("SELECT nume, prenume, email, email_optout, activ FROM angajati WHERE id=?", [(int)$i]); if (!$a || !$a['activ'] || $a['email_optout'] || !filter_var((string)$a['email'], FILTER_VALIDATE_EMAIL)) return null; return ['k' => $k, 'email' => $a['email'], 'name' => $a['prenume'] . ' ' . $a['nume']]; }
    $u = row("SELECT name, email, notif_email, email_optout FROM users WHERE id=?", [(int)$i]); if (!$u || $u['email_optout']) return null;
    $e = filter_var((string)$u['notif_email'], FILTER_VALIDATE_EMAIL) ? $u['notif_email'] : (filter_var((string)$u['email'], FILTER_VALIDATE_EMAIL) ? $u['email'] : null);
    return $e ? ['k' => $k, 'email' => $e, 'name' => $u['name']] : null;
}
function mail_html(string $title, string $intro, string $body, string $btnUrl, string $btnTxt, ?string $k): string {
    $un = $k ? app_url() . '?r=dezabonare&k=' . rawurlencode($k) . '&t=' . unsub_token($k) : '';
    return '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . h($title) . '</title></head><body style="margin:0;background:#f6f7f9;font-family:Segoe UI,Roboto,Arial,sans-serif;color:#0f172a">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f7f9;padding:24px 0"><tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0">
<tr><td style="background:#0f172a;padding:16px 22px;color:#fff"><span style="display:inline-block;background:#c8102e;color:#fff;font-weight:800;width:32px;height:32px;line-height:32px;text-align:center;border-radius:8px;margin-right:8px">B</span><b style="font-size:17px">BENIAL</b> <span style="font-size:17px">CONSULTING</span><div style="font-size:12px;color:#cbd5e1;margin-top:2px">SSM · PSI · Situații de urgență</div></td></tr>
<tr><td style="padding:22px"><h1 style="font-size:20px;margin:0 0 10px">' . h($title) . '</h1><p style="margin:0 0 14px;line-height:1.5">' . $intro . '</p>' . $body . ($btnUrl ? '<p style="margin:20px 0"><a href="' . h($btnUrl) . '" style="background:#c8102e;color:#fff;text-decoration:none;padding:11px 18px;border-radius:8px;font-weight:600;display:inline-block">' . h($btnTxt) . '</a></p>' : '') . '</td></tr>
<tr><td style="padding:14px 22px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;line-height:1.5">Mesaj automat trimis de platforma BENIAL CONSULTING (' . h(parse_url(app_url(), PHP_URL_HOST) ?: '') . '). Nu răspundeți la acest e-mail; folosiți secțiunea Mesaje din platformă.' . ($un ? '<br><a href="' . h($un) . '" style="color:#64748b">Dezabonare de la notificările pe e-mail</a>' : '') . '</td></tr></table></td></tr></table></body></html>';
}
function send_mail(string $toEmail, string $toName, string $subject, string $html, string $tip, string $key, ?string $rk = null): bool {
    if ($key !== '' && val("SELECT COUNT(*) FROM mail_log WHERE cheie=? AND status='trimis'", [$key])) return false;
    $log = fn($st, $er = '') => insert('mail_log', ['cheie' => $key, 'tip' => $tip, 'catre' => $toEmail, 'subiect' => mb_substr($subject, 0, 250), 'status' => $st, 'eroare' => mb_substr($er, 0, 250), 'created_at' => date('Y-m-d H:i')]);
    $host = getenv('SMTP_HOST'); if (!$host) { $log('omis', 'SMTP neconfigurat'); return false; }
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP(); $m->Host = $host; $m->Port = (int)(getenv('SMTP_PORT') ?: 465); $m->SMTPAuth = true; $m->Username = getenv('SMTP_USER'); $m->Password = getenv('SMTP_PASS');
        $m->SMTPSecure = $m->Port === 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $m->Timeout = 20;
        if (getenv('SMTP_SECURE') === 'none') { $m->SMTPSecure = ''; $m->SMTPAutoTLS = false; $m->SMTPAuth = false; }
        $m->CharSet = 'UTF-8'; $m->setFrom(getenv('SMTP_USER'), 'BENIAL CONSULTING – notificări'); $m->addAddress($toEmail, $toName);
        $m->Subject = $subject; $m->isHTML(true); $m->Body = $html;
        $m->AltBody = trim(html_entity_decode(strip_tags(preg_replace(['/<(br|\/p|\/tr|\/h1|\/li)[^>]*>/i', '/<a [^>]*href="([^"]+)"[^>]*>([^<]*)<\/a>/i'], ["\n", '$2 ($1)'], $html)), ENT_QUOTES, 'UTF-8'));
        $m->AltBody = preg_replace("/\n\s*\n+/", "\n\n", $m->AltBody);
        if ($rk) { $m->addCustomHeader('List-Unsubscribe', '<' . app_url() . '?r=dezabonare&k=' . rawurlencode($rk) . '&t=' . unsub_token($rk) . '>'); $m->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'); }
        $m->send(); $log('trimis'); return true;
    } catch (Throwable $e) { $log('eroare', $m->ErrorInfo ?: $e->getMessage()); return false; }
}
function notify(string $k, string $tip, string $subject, string $title, string $intro, string $body, string $route, string $btn, string $key): bool {
    if (!notif_on($tip) || !($r = recipient($k))) return false;
    return send_mail($r['email'], $r['name'], $subject, mail_html($title, $intro, $body, app_url() . $route, $btn, $k), $tip, $key, $k);
}
function li(array $items): string { return $items ? '<ul style="padding-left:18px;margin:0 0 10px;line-height:1.6">' . implode('', array_map(fn($x) => '<li>' . $x . '</li>', $items)) . '</ul>' : ''; }

/* ---- declanșatoare ---- */
function notify_sign_pending(array $angIds): void {
    foreach (array_unique(array_filter(array_map('intval', $angIds))) as $a) {
        $D = rows("SELECT id, titlu, angajat_id, clm_id, semn_ang, semn_clm, sablon_cod FROM documente WHERE sablon_cod IS NOT NULL AND ((angajat_id=? AND semn_ang IS NULL) OR (clm_id=? AND semn_clm IS NULL)) ORDER BY id", [$a, $a]);
        $D = array_filter($D, fn($d) => (int)$d['clm_id'] === $a || doc_needs_ang($d)); if (!$D) continue;
        $max = max(array_column($D, 'id')); $titles = array_slice(array_map(fn($d) => h($d['titlu']), $D), 0, 10);
        notify('a:' . $a, 'semnare', 'Aveți ' . count($D) . ' documente SSM/SU de semnat', 'Documente de semnat', 'Bună ziua! În portalul BENIAL vă așteaptă <b>' . count($D) . '</b> documente care necesită semnătura dumneavoastră:', li($titles) . (count($D) > 10 ? '<p>… și altele.</p>' : ''), '?r=semneaza', 'Deschide documentele', 'semn:' . $a . ':' . $max);
    }
}
function notify_test(int $insId): void {
    $i = row("SELECT i.id, i.angajat_id, c.titlu, c.durata_min FROM instruiri i JOIN cursuri c ON c.id=i.curs_id WHERE i.id=?", [$insId]); if (!$i) return;
    notify('a:' . $i['angajat_id'], 'test', 'Instruire nouă alocată: ' . $i['titlu'], 'Aveți o instruire nouă', 'V-a fost alocată instruirea <b>' . h($i['titlu']) . '</b> (aprox. ' . (int)$i['durata_min'] . ' minute). Parcurgeți lecțiile, susțineți testul și semnați fișa în portal.', '', '?r=take&id=' . $i['id'], 'Începe instruirea', 'test:' . $i['id']);
}
function notify_message(int $mid): void {
    $m = row("SELECT * FROM mesaje WHERE id=?", [$mid]); if (!$m) return;
    notify($m['to_k'], 'mesaj', 'Mesaj nou de la ' . k_name($m['from_k']) . ': ' . $m['subiect'], 'Ați primit un mesaj nou', '<b>' . h(k_name($m['from_k'])) . '</b> v-a trimis mesajul „' . h($m['subiect']) . '”. Din motive de confidențialitate, conținutul se citește doar în platformă.', '', '?r=mesaje&id=' . $mid, 'Citește mesajul', 'msg:' . $mid);
}
function notify_ticket(int $tid, int $msgId, bool $new): void {
    $t = row("SELECT * FROM tichete WHERE id=?", [$tid]); $m = row("SELECT * FROM tichete_msg WHERE id=?", [$msgId]); if (!$t || !$m) return;
    $targets = $m['autor_k'] === $t['autor_k'] ? array_map(fn($u) => 'u:' . $u['id'], rows("SELECT id FROM users WHERE role='superadmin'")) : [$t['autor_k']];
    foreach ($targets as $k) if ($k !== $m['autor_k']) notify($k, 'tichet', ($new ? 'Tichet nou' : 'Răspuns la tichet') . ' #' . $tid . ': ' . $t['subiect'], $new ? 'Tichet de suport nou' : 'Răspuns nou la tichet', '<b>' . h($m['autor']) . '</b> ' . ($new ? 'a deschis tichetul' : 'a răspuns la tichetul') . ' #' . $tid . ' „' . h($t['subiect']) . '” (prioritate ' . h($t['prioritate']) . ').', '', '?r=tichete&id=' . $tid, 'Deschide tichetul', 'tic:' . $msgId . ':' . $k);
}

/* ---- rezumat zilnic (rulat din cron) ---- */
function daily_digest(bool $dry = false): array {
    $today = date('Y-m-d'); $sent = []; $T = termene();
    $due = fn($t) => $t['zile'] !== null && $t['zile'] <= 7 && $t['tip'] !== 'Instruire nefinalizată';
    $eq = fn(?int $f) => rows("SELECT e.*, f.denumire AS firma, p.nume AS punct FROM echipamente e JOIN firme f ON f.id=e.firma_id LEFT JOIN puncte p ON p.id=e.punct_id WHERE e.urmatoarea IS NOT NULL AND e.urmatoarea<=?" . ($f ? ' AND e.firma_id=' . $f : '') . " ORDER BY e.urmatoarea", [date('Y-m-d', strtotime('+30 days'))]);
    $fmt = fn($t) => ($t['zile'] < 0 ? '<b style="color:#c8102e">depășit cu ' . (-$t['zile']) . ' zile</b>' : 'în ' . $t['zile'] . ' zile') . ' – ' . h($t['tip']) . ': ' . h($t['angajat']) . ' (' . h($t['firma']) . ') – ' . h($t['ce']) . ', ' . fmt_date($t['data']);
    $fmtE = fn($e) => (days_until($e['urmatoarea']) < 0 ? '<b style="color:#c8102e">depășit</b>' : 'în ' . days_until($e['urmatoarea']) . ' zile') . ' – ' . h($e['denumire']) . ($e['serie'] ? ' (' . h($e['serie']) . ')' : '') . ', ' . h($e['firma'] . ($e['punct'] ? ' / ' . $e['punct'] : '')) . ' – ' . fmt_date($e['urmatoarea']);
    foreach (rows("SELECT id, role, firma_id FROM users WHERE role IN ('superadmin','inspector','client')") as $u) {
        $f = $u['role'] === 'client' ? (int)$u['firma_id'] : null;
        $tr = array_filter($T, fn($t) => $due($t) && (!$f || (int)$t['firma_id'] === $f));
        $E = notif_on('echipamente') ? $eq($f) : [];
        if (!notif_on('digest')) $tr = [];
        if (!$tr && !$E) continue;
        $body = ($tr ? '<h3 style="font-size:15px;margin:14px 0 6px">Instruiri și avize scadente / depășite (' . count($tr) . ')</h3>' . li(array_map($fmt, array_slice($tr, 0, 60))) : '') . ($E ? '<h3 style="font-size:15px;margin:14px 0 6px">Verificări echipamente și mijloace de stingere în ≤30 zile (' . count($E) . ')</h3>' . li(array_map($fmtE, array_slice($E, 0, 60))) : '');
        $k = 'u:' . $u['id']; $r = recipient($k); if (!$r) continue;
        if ($dry) { $sent[] = $r['email']; continue; }
        if (send_mail($r['email'], $r['name'], 'Rezumat SSM/SU ' . date('d.m.Y') . ': ' . count($tr) . ' termene, ' . count($E) . ' verificări', mail_html('Rezumatul zilei – ' . date('d.m.Y'), 'Bună dimineața! Iată termenele care necesită atenție (scadente în următoarele 7 zile sau depășite):', $body, app_url() . '?r=dashboard', 'Deschide Centrul de termene', $k), 'digest', "digest:$k:$today", $k)) $sent[] = $r['email'];
    }
    if (notif_on('digest')) foreach (rows("SELECT id FROM angajati WHERE activ=1") as $a) {
        $tr = array_filter($T, fn($t) => $due($t) && (int)$t['aid'] === (int)$a['id']); $open = (int)val("SELECT COUNT(*) FROM instruiri WHERE angajat_id=? AND status IN ('alocat','picat','promovat')", [$a['id']]);
        if (!$tr && !$open) continue; $k = 'a:' . $a['id']; $r = recipient($k); if (!$r) continue;
        if ($dry) { $sent[] = $r['email']; continue; }
        $body = li(array_map(fn($t) => ($t['zile'] < 0 ? '<b style="color:#c8102e">depășit</b>' : 'în ' . $t['zile'] . ' zile') . ' – ' . h($t['tip']) . ': ' . h($t['ce']) . ' (' . fmt_date($t['data']) . ')', $tr)) . ($open ? '<p>Aveți <b>' . $open . '</b> instruiri nefinalizate în portal.</p>' : '');
        if (send_mail($r['email'], $r['name'], 'Instruirile tale SSM/SU – reamintire ' . date('d.m.Y'), mail_html('Reamintire instruiri', 'Bună ziua! Aveți instruiri sau avize care necesită atenție:', $body, app_url() . '?r=portal', 'Deschide portalul', $k), 'digest', "digest:$k:$today", $k)) $sent[] = $r['email'];
    }
    set_setare('digest_last', date('Y-m-d H:i'));
    return $sent;
}

/* ---- pagini ---- */
function r_setari(bool $post): void {
    require_role('superadmin');
    if ($post) {
        if (!empty($_POST['test'])) { $to = trim($_POST['test_to'] ?? '');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) flash('Adresă de test invalidă.', 'err');
            else { $ok = send_mail($to, '', 'Test notificări BENIAL ' . date('d.m.Y H:i'), mail_html('E-mail de test', 'Acesta este un mesaj de test trimis din pagina <b>Setări notificări</b>. Dacă îl citiți, trimiterea funcționează corect.', li(['Server SMTP: ' . h((string)getenv('SMTP_HOST')), 'Expeditor: ' . h((string)getenv('SMTP_USER'))]), app_url(), 'Deschide platforma', null), 'test', 'test:' . microtime(true));
                flash($ok ? "E-mail de test trimis către $to." : 'Trimiterea a eșuat – vedeți jurnalul de mai jos.', $ok ? 'ok' : 'err'); }
        } elseif (!empty($_POST['digest_now'])) { $s = daily_digest(); flash('Rezumat trimis către ' . count($s) . ' destinatari (cei care l-au primit deja azi sunt omiși).'); }
        else { set_setare('mail_enabled', !empty($_POST['mail_enabled']) ? '1' : '0'); foreach (NOTIF_TYPES as $k => $_) set_setare('notif_' . $k, !empty($_POST['notif_' . $k]) ? '1' : '0');
            if (($u = trim($_POST['app_url'] ?? '')) !== '' && filter_var($u, FILTER_VALIDATE_URL)) set_setare('app_url', $u); flash('Setările au fost salvate.'); }
        redirect(url('setari'));
    }
    $ck = fn($n, $on) => '<input type="checkbox" name="' . $n . '" value="1"' . ($on ? ' checked' : '') . '>';
    $types = ''; foreach (NOTIF_TYPES as $k => $l) $types .= '<label class="chk">' . $ck('notif_' . $k, setare('notif_' . $k, '1') === '1') . ' ' . h($l) . '</label>';
    $L = rows("SELECT * FROM mail_log ORDER BY id DESC LIMIT 50"); $lt = '';
    foreach ($L as $l) $lt .= '<tr><td data-l="Data">' . h($l['created_at']) . '</td><td data-l="Tip">' . h($l['tip']) . '</td><td data-l="Către">' . h($l['catre']) . '</td><td data-l="Subiect">' . h($l['subiect']) . '</td><td data-l="Stare">' . ($l['status'] === 'trimis' ? '<span class="b b-green">trimis</span>' : '<span class="b b-red">' . h($l['status']) . '</span> <small>' . h($l['eroare']) . '</small>') . '</td></tr>';
    $smtp = getenv('SMTP_HOST') ? '<span class="b b-green">configurat</span> ' . h(getenv('SMTP_USER')) . ' prin ' . h(getenv('SMTP_HOST')) . ':' . h(getenv('SMTP_PORT') ?: '465') : '<span class="b b-red">neconfigurat</span> (variabilele SMTP_* lipsesc)';
    page('Setări notificări e-mail', '<form method="post" class="card form">' . csrf_field() . '<h2>Trimitere e-mail</h2><p>Server: ' . $smtp . '</p><label class="chk">' . $ck('mail_enabled', setare('mail_enabled', '1') === '1') . ' <b>Notificările pe e-mail sunt active</b></label><h3>Tipuri de notificări</h3><div class="checks">' . $types . '</div>
    <div class="grid"><label>Adresa platformei (în linkurile din e-mail)<input name="app_url" value="' . h(app_url()) . '"></label></div><p class="muted small">Rezumatul zilnic se trimite automat la ora 07:00 (cron). Ultima rulare: ' . h(setare('digest_last', '—')) . '. Fiecare utilizator/angajat poate renunța la e-mailuri din „Preferințe” sau din linkul de dezabonare.</p><div class="savebar"><button class="btn">Salvează</button></div></form>
    <form method="post" class="card form">' . csrf_field() . '<h2>Test</h2><div class="tools"><input type="email" name="test_to" placeholder="adresa@exemplu.ro" required><button class="btn ghost" name="test" value="1">Trimite e-mail de test</button></div></form>
    <form method="post" class="tools">' . csrf_field() . '<button class="btn ghost" name="digest_now" value="1" onclick="return confirm(\'Trimiteți acum rezumatul zilnic tuturor destinatarilor?\')">Trimite acum rezumatul zilnic</button></form>
    <div class="card p0"><h2 class="pad">Jurnal trimiteri (ultimele 50)</h2><table class="tbl"><thead><tr><th>Data</th><th>Tip</th><th>Către</th><th>Subiect</th><th>Stare</th></tr></thead><tbody>' . ($lt ?: '<tr><td colspan="5" class="empty">Nicio trimitere.</td></tr>') . '</tbody></table></div>');
}
function r_preferinte(bool $post): void {
    if (!user()) redirect(url('login')); $ang = role() === 'angajat'; $id = $ang ? (int)user()['angajat_id'] : (int)user()['id'];
    $r = $ang ? row("SELECT email, email_optout FROM angajati WHERE id=?", [$id]) : row("SELECT notif_email, email_optout FROM users WHERE id=?", [$id]);
    if ($post) { $e = trim($_POST['email'] ?? '');
        if ($e !== '' && !filter_var($e, FILTER_VALIDATE_EMAIL)) { flash('Adresă de e-mail invalidă.', 'err'); redirect(url('preferinte')); }
        $ang ? update('angajati', $id, ['email' => $e ?: null, 'email_optout' => empty($_POST['primeste']) ? 1 : 0]) : update('users', $id, ['notif_email' => $e ?: null, 'email_optout' => empty($_POST['primeste']) ? 1 : 0]);
        flash('Preferințele au fost salvate.'); redirect(url('preferinte')); }
    page('Preferințe notificări', '<form method="post" class="card form">' . csrf_field() . '<div class="grid"><label>Adresa de e-mail pentru notificări<input type="email" name="email" value="' . h($ang ? $r['email'] : $r['notif_email']) . '"></label></div><label class="chk"><input type="checkbox" name="primeste" value="1"' . (empty($r['email_optout']) ? ' checked' : '') . '> Doresc să primesc notificări pe e-mail (documente de semnat, instruiri, mesaje, rezumat zilnic)</label><div class="savebar"><button class="btn">Salvează</button></div></form>');
}
function r_dezabonare(): void {
    $k = (string)($_GET['k'] ?? ''); $ok = preg_match('/^[ua]:\d+$/', $k) && hash_equals(unsub_token($k), (string)($_GET['t'] ?? ''));
    if ($ok) { [$t, $i] = explode(':', $k); update($t === 'a' ? 'angajati' : 'users', (int)$i, ['email_optout' => 1]); }
    page('Dezabonare', '<div class="card">' . ($ok ? 'Nu veți mai primi notificări pe e-mail de la platforma BENIAL. Le puteți reactiva oricând din „Preferințe”, după autentificare.' : 'Link de dezabonare invalid.') . '</div>');
}

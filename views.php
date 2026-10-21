<?php
/* ---------- autentificare ---------- */
function r_login(bool $post): void {
    $err = '';
    if ($post) {
        if (($_POST['mode'] ?? '') === 'angajat') {
            $cod = strtoupper(trim($_POST['cod'] ?? '')); $un = trim($_POST['username'] ?? '');
            $a = $cod ? row("SELECT * FROM angajati WHERE cod_acces=? AND activ=1", [$cod]) : null;
            if (!$a && $un !== '') { $x = row("SELECT * FROM angajati WHERE username=? AND activ=1", [$un]); if ($x && $x['password_hash'] && password_verify($_POST['upass'] ?? '', $x['password_hash'])) $a = $x; }
            if ($a) { session_regenerate_id(true); $_SESSION['user'] = ['id' => 0, 'role' => 'angajat', 'name' => $a['prenume'] . ' ' . $a['nume'], 'angajat_id' => (int)$a['id'], 'firma_id' => (int)$a['firma_id']]; redirect(url('portal')); }
            $err = 'Cod de acces sau date de autentificare invalide.';
        } else {
            $u = row("SELECT * FROM users WHERE email=?", [trim($_POST['email'] ?? '')]);
            if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) { session_regenerate_id(true); $_SESSION['user'] = ['id' => (int)$u['id'], 'role' => $u['role'], 'name' => $u['name'], 'firma_id' => $u['firma_id']]; redirect(url('dashboard')); }
            $err = 'Utilizator sau parolă greșită.';
        }
    }
    $e = $err ? '<div class="flash err">' . h($err) . '</div>' : '';
    page('Autentificare', $e . '<div class="login-grid">
    <form method="post" class="card"><h2>Personal SSM / client</h2><input type="hidden" name="mode" value="staff">
      <label>Utilizator<input name="email" required autocomplete="username"></label>
      <label>Parolă<input name="password" type="password" required autocomplete="current-password"></label>
      <button class="btn">Intră în cont</button></form>
    <form method="post" class="card"><h2>Portal angajat</h2><p class="muted">Introduceți codul de acces primit de la angajator (pe telefon sau calculator).</p><input type="hidden" name="mode" value="angajat">
      <label>Cod de acces<input name="cod" autocapitalize="characters" placeholder="ex. AB12CD34"></label><p class="muted small">sau cu utilizator și parolă:</p><label>Utilizator<input name="username" autocomplete="username"></label><label>Parolă<input name="upass" type="password" autocomplete="current-password"></label>
      <button class="btn">Intră în portal</button></form></div>
    <div class="card install-card" id="pwa-install" hidden><h2>📲 Aplicația BENIAL</h2><p class="muted">Instalați platforma pe telefon sau calculator pentru acces rapid, ca o aplicație.</p><button type="button" class="btn" id="pwa-btn">Instalează aplicația</button><div id="pwa-ios" class="muted" hidden><p><b>Pe iPhone/iPad (Safari):</b></p><ol><li>Apăsați butonul <b>Partajare</b> <span aria-hidden="true">⬆️</span> din bara de jos</li><li>Alegeți <b>Adaugă pe ecranul principal</b> (Add to Home Screen)</li><li>Apăsați <b>Adaugă</b></li></ol></div><p id="pwa-help" class="muted" hidden>Dacă butonul nu răspunde, deschideți meniul browserului (⋮) și alegeți <b>Instalează aplicația</b> / <b>Adaugă pe ecranul de pornire</b>.</p></div>');
}

/* ---------- liste generice ---------- */
function can(array $E, string $what): bool { return in_array(role(), $E[$what]); }
function display(array $E, string $col, array $row, array &$cache): string {
    $f = $E['fields'][$col] ?? null; $v = $row[$col] ?? '';
    if ($col === 'role') return h(['superadmin' => 'Super-admin', 'inspector' => 'Inspector SSM', 'client' => 'Administrator client'][$v] ?? $v);
    if (!$f) return h($v);
    return match ($f[1]) {
        'select' => h(($cache[$col] ??= ($f[3])())[$v] ?? '—'),
        'cnp' => '<span class="mono">' . mask_cnp($v) . '</span>',
        'date' => in_array($col, ['medical_expira', 'urmatoarea', 'termen']) ? fmt_date($v) . ' ' . badge_days(days_until($v)) : fmt_date($v),
        'multi' => h(implode(', ', array_map(fn($x) => ($cache[$col] ??= ($f[3])())[$x] ?? $x, array_filter(explode(',', (string)$v))))),
        'check' => $v ? 'Da' : 'Nu',
        default => h(mb_strimwidth((string)$v, 0, 70, '…')),
    };
}
function scoped_where(array $E, array &$args): string {
    $w = [];
    if ($E['scope'] && ($sf = scope_firma())) { $w[] = "{$E['scope']}=?"; $args[] = $sf; }
    if (!empty($E['parent']) && !empty($_GET[$E['parent']])) { $w[] = "{$E['parent']}=?"; $args[] = (int)$_GET[$E['parent']]; }
    if ($E['scope'] === 'firma_id' && !empty($_GET['firma'])) { $w[] = "firma_id=?"; $args[] = (int)$_GET['firma']; }
    if ($E['key'] === 'angajati' && !empty($_GET['punct'])) { $w[] = "(punct_id=? OR puncte_extra LIKE ?)"; $args[] = (int)$_GET['punct']; $args[] = '%' . (int)$_GET['punct'] . '%'; }
    if ($E['key'] === 'angajati' && ($fn = trim($_GET['functie'] ?? '')) !== '') { $w[] = "functie=?"; $args[] = $fn; }
    if ($E['key'] === 'echipamente' && !empty($_GET['cat'])) { $w[] = "categorie=?"; $args[] = $_GET['cat']; }
    if ($s = trim($_GET['q'] ?? '')) { $first = array_keys(array_filter($E['fields'], fn($f) => in_array($f[1], ['text', 'textarea', 'email'])))[0] ?? null;
        $txt = array_slice(array_keys(array_filter($E['fields'], fn($f) => in_array($f[1], ['text', 'email']))), 0, 4);
        if ($txt) { $w[] = '(' . implode(' OR ', array_map(fn($c) => "LOWER($c) LIKE ?", $txt)) . ')'; foreach ($txt as $_) $args[] = '%' . mb_strtolower($s) . '%'; } }
    return $w ? ' WHERE ' . implode(' AND ', $w) : '';
}
function r_list(array $E): void {
    require_role(...$E['read']);
    $args = []; $rows = rows("SELECT * FROM {$E['key']}" . scoped_where($E, $args) . " ORDER BY {$E['order']}", $args);
    $cache = []; $th = ''; foreach ($E['list'] as $c) $th .= '<th>' . h($c === 'role' ? 'Rol' : $E['fields'][$c][0]) . '</th>';
    $tb = '';
    foreach ($rows as $r) {
        $td = ''; foreach ($E['list'] as $c) $td .= '<td data-l="' . h($c === 'role' ? 'Rol' : $E['fields'][$c][0]) . '">' . display($E, $c, $r, $cache) . '</td>';
        $act = '';
        if ($E['key'] === 'angajati') $act .= '<a class="btn sm ghost" href="' . url('angajat', ['id' => $r['id']]) . '">Fișă</a>';
        if ($E['key'] === 'cursuri') $act .= '<a class="btn sm ghost" href="' . url('curs', ['id' => $r['id']]) . '">Lecții & test</a>';
        if ($E['key'] === 'program') $act .= post_btn(url('alloc', ['id' => $r['id']]), 'Alocă angajaților', 'sm ghost');
        if (can($E, 'write')) {
            $act .= '<a class="btn sm" href="' . url('form', ['e' => $E['key'], 'id' => $r['id']]) . '">Editează</a>';
            $act .= post_btn(url('delete', ['e' => $E['key'], 'id' => $r['id']]), 'Șterge', 'sm danger', 'Sigur ștergeți acest ' . $E['one'] . '? Acțiunea nu poate fi anulată.');
        }
        $tb .= "<tr>$td<td class=\"act\">$act</td></tr>";
    }
    if (!$rows) $tb = '<tr><td colspan="99" class="empty">Nu există înregistrări.</td></tr>';
    $extra = array_filter([$E['parent'] ?? null => $_GET[$E['parent'] ?? ''] ?? null]);
    $tools = '<form class="tools" method="get"><input type="hidden" name="r" value="list"><input type="hidden" name="e" value="' . $E['key'] . '">'
        . '<input name="q" placeholder="Caută…" value="' . h($_GET['q'] ?? '') . '">'
        . ($E['scope'] === 'firma_id' && !scope_firma() ? select_html('firma', opts("SELECT id, denumire AS l FROM firme", false), $_GET['firma'] ?? '', 'Toate firmele') : '')
        . ($E['key'] === 'angajati' ? select_html('punct', opts("SELECT id, nume AS l FROM puncte" . (scope_firma() ? ' WHERE firma_id=' . scope_firma() : ''), false), $_GET['punct'] ?? '', 'Toate punctele') . select_html('functie', array_combine($fx = array_column(rows("SELECT DISTINCT functie FROM angajati WHERE functie IS NOT NULL" . (scope_firma() ? ' AND firma_id=' . scope_firma() : '') . " ORDER BY functie"), 'functie'), $fx), $_GET['functie'] ?? '', 'Toate funcțiile') : '')
        . ($E['key'] === 'echipamente' ? select_html('cat', ['echipament' => 'Echipamente de muncă', 'stingere' => 'Mijloace de stingere'], $_GET['cat'] ?? '', 'Toate categoriile') : '')
        . '<button class="btn ghost">Filtrează</button>'
        . (can($E, 'write') ? '<a class="btn" href="' . url('form', ['e' => $E['key']] + $extra) . '">+ Adaugă ' . h($E['one']) . '</a>' : '')
        . ($E['key'] === 'angajati' ? '<a class="btn ghost" href="' . url('import') . '">Import Excel/CSV</a>' : '') . '</form>';
    page($E['title'], $tools . '<div class="card p0"><table class="tbl"><thead><tr>' . $th . '<th></th></tr></thead><tbody>' . $tb . '</tbody></table></div><p class="muted">' . count($rows) . ' înregistrări</p>');
}
function post_btn(string $action, string $label, string $cls = '', string $confirm = ''): string {
    return '<form method="post" action="' . h($action) . '" class="inline"' . ($confirm ? ' data-confirm="' . h($confirm) . '"' : '') . '>' . csrf_field() . '<button class="btn ' . $cls . '">' . h($label) . '</button></form>';
}
function select_html(string $name, array $opts, $cur, string $empty = '— alegeți —', bool $req = false): string {
    $o = '<option value="">' . h($empty) . '</option>';
    foreach ($opts as $k => $l) $o .= '<option value="' . h($k) . '"' . ((string)$k === (string)$cur ? ' selected' : '') . '>' . h($l) . '</option>';
    return '<select name="' . h($name) . '"' . ($req ? ' required' : '') . '>' . $o . '</select>';
}

/* ---------- formular generic (adăugare = editare, aceleași câmpuri) ---------- */
function load_scoped(array $E, int $id): array {
    $r = row("SELECT * FROM {$E['key']} WHERE id=?", [$id]);
    if (!$r || ($E['scope'] && ($sf = scope_firma()) && (int)$r[$E['scope']] !== $sf)) { http_response_code(404); page('Negăsit', '<div class="card">Înregistrarea nu există sau nu aveți acces.</div>'); exit; }
    return $r;
}
function r_form(array $E, array $data = null, array $errors = []): void {
    require_role(...$E['write']);
    $id = (int)($_GET['id'] ?? 0);
    if ($data === null) { $data = $id ? load_scoped($E, $id) : array_map(fn($f) => '', $E['fields']);
        if (!$id) { if (!empty($E['parent'])) $data[$E['parent']] = $_GET[$E['parent']] ?? ''; if (isset($E['fields']['activ'])) $data['activ'] = 1; if ($sf = scope_firma()) $data['firma_id'] = $sf; } }
    $h = '';
    foreach ($E['fields'] as $k => $f) {
        [$label, $type] = $f; $req = !empty($f[2]); $hint = (is_string($f[3] ?? null)) ? $f[3] : '';
        if ($k === 'firma_id' && scope_firma()) { $h .= '<input type="hidden" name="firma_id" value="' . scope_firma() . '">'; continue; }
        $v = $data[$k] ?? ''; $er = $errors[$k] ?? '';
        $in = match ($type) {
            'select' => select_html($k, ($f[3])(), $v, '— alegeți —', $req),
            'multi' => '<span class="multi">' . implode('', array_map(fn($ok, $ol) => '<label class="chk"><input type="checkbox" name="' . $k . '[]" value="' . h($ok) . '"' . (in_array((string)$ok, is_array($v) ? $v : explode(',', (string)$v)) ? ' checked' : '') . '> ' . h($ol) . '</label>', array_keys(($f[3])()), ($f[3])())) . '</span>',
            'textarea' => '<textarea name="' . $k . '" rows="5"' . ($req ? ' required' : '') . '>' . h($v) . '</textarea>',
            'check' => '<input type="hidden" name="' . $k . '" value="0"><input type="checkbox" name="' . $k . '" value="1"' . ($v ? ' checked' : '') . '>',
            'password' => '<input type="password" name="' . $k . '" autocomplete="new-password">',
            'cnp' => '<input name="' . $k . '" value="' . h($v) . '" inputmode="numeric" maxlength="13" pattern="\d{13}"' . ($req ? ' required' : '') . '>',
            default => '<input type="' . ($type === 'url' ? 'url' : $type) . '" name="' . $k . '" value="' . h($v) . '"' . ($req ? ' required' : '') . ($type === 'number' ? ' min="0"' : '') . '>',
        };
        $h .= '<label class="' . ($type === 'textarea' ? 'full ' : '') . ($er ? 'has-err' : '') . '">' . h($label) . ($req ? ' <i class="req">*</i>' : '') . $in . ($hint ? '<small>' . h($hint) . '</small>' : '') . ($er ? '<em class="err">' . h($er) . '</em>' : '') . '</label>';
    }
    $back = url('list', ['e' => $E['key']] + (!empty($E['parent']) && !empty($data[$E['parent']]) ? [$E['parent'] => $data[$E['parent']]] : []));
    if (!empty($E['parent'])) $back = url('curs', ['id' => $data['curs_id']]);
    $summary = $errors ? '<div class="flash err">Formularul are ' . count($errors) . ' erori. Corectați câmpurile marcate cu roșu.</div>' : '';
    $up = in_array($E['key'], UP_ENT, true) ? '<div class="full"><h3>Fișiere atașate</h3>' . ($id ? up_list(up_files($E['key'], $id), true, url('form', ['e' => $E['key'], 'id' => $id])) : '') . up_input() . '</div>' : '';
    page(($id ? 'Editează ' : 'Adaugă ') . $E['one'], $summary . '<form method="post" action="' . url('save', ['e' => $E['key'], 'id' => $id]) . '" class="card form" novalidate enctype="multipart/form-data">' . csrf_field() . '<div class="grid">' . $h . '</div>' . $up . '
      <div class="savebar"><a class="btn ghost" href="' . $back . '">Renunță</a><button class="btn">Salvează</button></div></form>');
}
function r_save(array $E): void {
    require_role(...$E['write']);
    $id = (int)($_GET['id'] ?? 0); $old = $id ? load_scoped($E, $id) : [];
    $d = []; $err = [];
    foreach ($E['fields'] as $k => $f) {
        $t = $f[1]; $v = $t === 'multi' ? implode(',', array_intersect(array_map('strval', (array)($_POST[$k] ?? [])), array_map('strval', array_keys(($f[3])())))) : trim((string)($_POST[$k] ?? ''));
        if ($k === 'firma_id' && scope_firma()) $v = (string)scope_firma();
        if ($t === 'password') { if ($v !== '') { if (strlen($v) < 8) $err[$k] = 'Parola trebuie să aibă minim 8 caractere.'; else $d['password_hash'] = password_hash($v, PASSWORD_DEFAULT); } elseif (!$id && $E['key'] === 'users') $err[$k] = 'Parola este obligatorie la un utilizator nou.'; continue; }
        if (!empty($f[2]) && $v === '') { $err[$k] = 'Câmp obligatoriu.'; }
        elseif ($v !== '') {
            if ($t === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $err[$k] = 'Adresă de e-mail invalidă.';
            if ($t === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) $err[$k] = 'Dată invalidă.';
            if ($t === 'number' && !ctype_digit($v)) $err[$k] = 'Introduceți un număr întreg.';
            if ($t === 'cnp' && !valid_cnp($v)) $err[$k] = 'CNP invalid (13 cifre, cifra de control nu corespunde).';
            if ($t === 'select' && !array_key_exists($v, ($f[3])())) $err[$k] = 'Valoare invalidă.';
        }
        $d[$k] = $v === '' ? null : $v;
        if ($t === 'check') $d[$k] = $v ? 1 : 0;
    }
    if ($E['key'] === 'angajati' && empty($err['cnp']) && val("SELECT COUNT(*) FROM angajati WHERE cnp=? AND firma_id=? AND id<>?", [$d['cnp'], $d['firma_id'], $id])) $err['cnp'] = 'Există deja un angajat cu acest CNP în firmă.';
    if ($E['key'] === 'users') { if (val("SELECT COUNT(*) FROM users WHERE email=? AND id<>?", [$d['email'], $id])) $err['email'] = 'Utilizatorul există deja.'; if ($d['role'] === 'client' && !$d['firma_id']) $err['firma_id'] = 'Alegeți firma administratorului client.'; }
    if ($E['key'] === 'angajati' && !empty($d['username']) && val("SELECT COUNT(*) FROM angajati WHERE username=? AND id<>?", [$d['username'], $id])) $err['username'] = 'Utilizatorul există deja.';
    if ($E['key'] === 'angajati' && !empty($d['password_hash']) && empty($d['username']) && empty($old['username'])) $err['username'] = 'Completați utilizatorul pentru parola de portal.';
    if ($E['key'] === 'echipamente' && empty($err) && !$d['urmatoarea'] && $d['data_verificare']) $d['urmatoarea'] = add_months($d['data_verificare'], (int)$d['periodicitate_luni']);
    if ($E['key'] === 'grupe' && empty($err['periodicitate_luni']) && ((int)$d['periodicitate_luni'] < 1 || (int)$d['periodicitate_luni'] > 12)) $err['periodicitate_luni'] = 'Între 1 și 12 luni.';
    if ($err) { $_GET['id'] = $id; r_form($E, $_POST, $err); return; }
    if ($E['key'] === 'users') { unset($d['password']); if ($d['role'] !== 'client') $d['firma_id'] = null; }
    if ($id) update($E['key'], $id, $d);
    else { if ($E['key'] === 'angajati') $d['cod_acces'] = gen_code(); if ($E['key'] === 'users') $d['created_at'] = date('Y-m-d H:i'); $id = insert($E['key'], $d); }
    if ($E['key'] === 'angajati' && $d['grupa_id']) foreach (rows("SELECT id FROM program WHERE grupa_id=?", [$d['grupa_id']]) as $p) allocate_program((int)$p['id']);
    if (in_array($E['key'], UP_ENT, true)) up_flash(up_save($E['key'], (int)$id, isset($d['firma_id']) ? (int)$d['firma_id'] : (isset($old['firma_id']) ? (int)$old['firma_id'] : null)));
    flash(ucfirst($E['one']) . ' salvat(ă) cu succes.');
    if ($E['key'] === 'angajati') redirect(url('angajat', ['id' => $id]));
    if (!empty($E['parent'])) redirect(url('curs', ['id' => $d['curs_id']]));
    redirect(url('list', ['e' => $E['key']]));
}
function r_delete(array $E): void {
    require_role(...$E['write']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Ștergerea se face doar prin buton, cu confirmare.'); }
    $id = (int)$_GET['id']; $r = load_scoped($E, $id);
    $block = match ($E['key']) {
        'firme' => val("SELECT COUNT(*) FROM angajati WHERE firma_id=?", [$id]) ? 'Firma are angajați. Ștergeți sau mutați întâi angajații.' : '',
        'grupe' => val("SELECT COUNT(*) FROM angajati WHERE grupa_id=?", [$id]) ? 'Grupa are angajați alocați.' : '',
        'cursuri' => val("SELECT COUNT(*) FROM instruiri WHERE curs_id=? AND status='semnat'", [$id]) ? 'Cursul are fișe semnate – nu poate fi șters.' : '',
        'users' => $id === (int)user()['id'] ? 'Nu vă puteți șterge propriul cont.' : '',
        default => '',
    };
    if ($block) { flash($block, 'err'); }
    else {
        if ($E['key'] === 'angajati') q("DELETE FROM instruiri WHERE angajat_id=? AND status<>'semnat'", [$id]);
        q("DELETE FROM {$E['key']} WHERE id=?", [$id]); up_delete_for($E['key'], $id); if ($E['key'] === 'cursuri') foreach (rows("SELECT id FROM lectii WHERE curs_id=?", [$id]) as $l) up_delete_for('lectii', (int)$l['id']); flash(ucfirst($E['one']) . ' șters(ă).');
    }
    if (!empty($E['parent'])) redirect(url('curs', ['id' => $r['curs_id']]));
    redirect(url('list', ['e' => $E['key']]));
}

/* ---------- fișa angajatului ---------- */
function status_badge(string $s): string { return ['alocat' => '<span class="b b-yellow">alocată</span>', 'picat' => '<span class="b b-red">test nepromovat</span>', 'promovat' => '<span class="b b-orange">de semnat</span>', 'semnat' => '<span class="b b-green">semnată</span>'][$s] ?? h($s); }
function r_angajat(): void {
    require_role('superadmin', 'inspector', 'client');
    $E = ent('angajati'); $a = load_scoped($E, (int)$_GET['id']);
    $f = row("SELECT * FROM firme WHERE id=?", [$a['firma_id']]);
    $g = $a['grupa_id'] ? row("SELECT * FROM grupe WHERE id=?", [$a['grupa_id']]) : null;
    $p = $a['punct_id'] ? row("SELECT * FROM puncte WHERE id=?", [$a['punct_id']]) : null;
    $ins = rows("SELECT i.*, c.titlu FROM instruiri i JOIN cursuri c ON c.id=i.curs_id WHERE angajat_id=? ORDER BY i.id DESC", [$a['id']]);
    $t = '';
    foreach ($ins as $i) $t .= '<tr><td data-l="Curs">' . h($i['titlu']) . '</td><td data-l="Stare">' . status_badge($i['status']) . '</td><td data-l="Scor">' . ($i['scor'] !== null ? $i['scor'] . '%' : '—') . '</td><td data-l="Semnat">' . ($i['data_semnare'] ? fmt_date($i['data_semnare']) : '—') . '</td><td data-l="Următoarea">' . ($i['urmatoarea'] ? fmt_date($i['urmatoarea']) . ' ' . badge_days(days_until($i['urmatoarea'])) : '—') . '</td><td class="act">' . ($i['status'] === 'semnat' ? '<a class="btn sm ghost" href="' . url('fisa', ['id' => $i['id']]) . '">Fișa de instructaj</a>' : '') . '</td></tr>';
    if (!$ins) $t = '<tr><td colspan="9" class="empty">Nicio instruire alocată. Alocați o grupă care are program de instruire.</td></tr>';
    $dl = fn($k, $v) => '<div><dt>' . h($k) . '</dt><dd>' . $v . '</dd></div>';
    page($a['nume'] . ' ' . $a['prenume'], '<div class="tools"><a class="btn" href="' . url('form', ['e' => 'angajati', 'id' => $a['id']]) . '">Editează</a><a class="btn ghost" href="' . url('list', ['e' => 'angajati']) . '">← Înapoi la listă</a></div>
    <div class="cols"><div class="card"><h2>Date angajat</h2><dl class="dl">' . $dl('Firmă', h($f['denumire'])) . $dl('CNP', '<span class="mono">' . mask_cnp($a['cnp']) . '</span>') . $dl('Funcție / COR', h($a['functie'] . ' / ' . $a['cor'])) . $dl('Punct de lucru', h($p['nume'] ?? '—')) . $dl('Grupă', h($g['nume'] ?? '—')) . $dl('Data angajării', fmt_date($a['data_angajarii'])) . $dl('Telefon', h($a['telefon'])) . $dl('Medicina muncii', fmt_date($a['medical_expira']) . ' ' . badge_days(days_until($a['medical_expira']))) . '</dl></div>
    <div class="card"><h2>Acces portal angajat</h2><p>Angajatul intră pe <b>pagina de autentificare → Portal angajat</b> cu codul:</p><p class="code">' . h($a['cod_acces']) . '</p><p class="muted">Codul se comunică personal angajatului. Generați unul nou dacă a fost compromis.</p>' . post_btn(url('regen', ['id' => $a['id']]), 'Generează cod nou', 'ghost', 'Codul vechi nu va mai funcționa. Continuați?') . '</div></div>
    ' . (is_staff() ? '<div class="card"><h2>Rezultat test offline</h2><form method="post" action="' . url('offline', ['id' => $a['id']]) . '" class="tools">' . csrf_field() . select_html('ins', array_column(array_filter($ins, fn($i) => $i['status'] !== 'semnat'), 'titlu', 'id'), '', '— instruire —', true) . '<input type="number" name="scor" min="0" max="100" placeholder="Scor %" required><button class="btn ghost">Înregistrează</button></form></div>' : '') . '
    ' . (($fl = up_list(up_files('angajati', (int)$a['id']), true, url('angajat', ['id' => $a['id']]))) ? '<div class="card"><h2>Fișiere atașate</h2>' . $fl . '</div>' : '') . '
    <div class="card p0"><h2 class="pad">Documente <a class="btn sm" href="' . url('documente', ['q' => $a['nume']]) . '">Toate</a></h2><table class="tbl"><tbody>' . (implode('', array_map(fn($d) => '<tr><td>' . h($d['titlu']) . '</td><td>' . fmt_date($d['data_doc']) . '</td><td>' . doc_status_label($d) . '</td><td class="act"><a class="btn sm ghost" href="' . url('doc', ['id' => $d['id']]) . '">Deschide</a></td></tr>', rows("SELECT * FROM documente WHERE angajat_id=? AND sablon_cod IS NOT NULL ORDER BY id DESC", [$a['id']]))) ?: '<tr><td class="empty">Niciun document generat.</td></tr>') . '</tbody></table></div>
    <div class="card p0"><h2 class="pad">Instruiri</h2><table class="tbl"><thead><tr><th>Curs</th><th>Stare</th><th>Scor</th><th>Semnat</th><th>Următoarea instruire</th><th></th></tr></thead><tbody>' . $t . '</tbody></table></div>');
}
function r_regen(): void { require_role('superadmin', 'inspector', 'client'); $a = load_scoped(ent('angajati'), (int)$_GET['id']); update('angajati', (int)$a['id'], ['cod_acces' => gen_code()]); flash('Cod de acces nou generat.'); redirect(url('angajat', ['id' => $a['id']])); }
function r_alloc(): void { require_role('superadmin', 'inspector'); $n = allocate_program((int)$_GET['id']); flash("Au fost alocate $n instruiri noi angajaților din grupă."); redirect(url('list', ['e' => 'program'])); }

/* ---------- curs: lecții + întrebări ---------- */
function r_curs(): void {
    require_role('superadmin', 'inspector');
    $c = load_scoped(ent('cursuri'), (int)$_GET['id']);
    $L = rows("SELECT * FROM lectii WHERE curs_id=? ORDER BY ordine", [$c['id']]); $Q = rows("SELECT * FROM intrebari WHERE curs_id=? ORDER BY id", [$c['id']]);
    $lh = ''; foreach ($L as $l) $lh .= '<tr><td>' . $l['ordine'] . '</td><td>' . h($l['titlu']) . ($l['video_url'] ? ' <span class="b b-gray">video</span>' : '') . (($nf = count(up_files('lectii', (int)$l['id']))) ? ' <span class="b b-gray">' . $nf . ' fișiere</span>' : '') . '</td><td class="act"><a class="btn sm" href="' . url('form', ['e' => 'lectii', 'id' => $l['id']]) . '">Editează</a>' . post_btn(url('delete', ['e' => 'lectii', 'id' => $l['id']]), 'Șterge', 'sm danger', 'Ștergeți lecția?') . '</td></tr>';
    $qh = ''; foreach ($Q as $i => $x) $qh .= '<tr><td>' . ($i + 1) . '</td><td>' . h($x['text']) . '<br><small class="muted">Corect: ' . h(implode('; ', array_map(fn($o) => strtoupper($o) . ') ' . ($x['opt_' . $o] ?? ''), array_filter(explode(',', (string)$x['corect']))))) . ((int)($x['puncte'] ?? 0) > 1 ? ' · ' . (int)$x['puncte'] . ' puncte' : '') . '</small></td><td class="act"><a class="btn sm" href="' . url('form', ['e' => 'intrebari', 'id' => $x['id']]) . '">Editează</a>' . post_btn(url('delete', ['e' => 'intrebari', 'id' => $x['id']]), 'Șterge', 'sm danger', 'Ștergeți întrebarea?') . '</td></tr>';
    page($c['titlu'], '<div class="tools"><a class="btn" href="' . url('form', ['e' => 'cursuri', 'id' => $c['id']]) . '">Editează cursul</a><a class="btn ghost" href="' . url('list', ['e' => 'cursuri']) . '">← Cursuri</a></div>
    <div class="card">' . up_list(up_files('cursuri', (int)$c['id'])) . '<p>' . h($c['tip']) . ' · ' . (int)$c['durata_min'] . ' minute · prag promovare ' . (int)$c['prag_promovare'] . '%</p><p class="muted">' . h($c['descriere']) . '</p></div>
    <div class="cols"><div class="card p0"><h2 class="pad">Lecții <a class="btn sm" href="' . url('form', ['e' => 'lectii', 'curs_id' => $c['id']]) . '">+ Lecție</a></h2><table class="tbl"><tbody>' . ($lh ?: '<tr><td class="empty">Nicio lecție.</td></tr>') . '</tbody></table></div>
    <div class="card p0"><h2 class="pad">Test (' . count($Q) . ' întrebări) <a class="btn sm" href="' . url('form', ['e' => 'intrebari', 'curs_id' => $c['id']]) . '">+ Întrebare</a></h2><table class="tbl"><tbody>' . ($qh ?: '<tr><td class="empty">Nicio întrebare.</td></tr>') . '</tbody></table></div></div>');
}

/* ---------- centru de termene ---------- */
function termene(?int $firma = null): array {
    $sf = scope_firma() ?? $firma; $w = $sf ? ' AND a.firma_id=' . (int)$sf : '';
    $out = [];
    foreach (rows("SELECT a.*, f.denumire FROM angajati a JOIN firme f ON f.id=a.firma_id WHERE a.activ=1$w") as $a) {
        $nm = $a['nume'] . ' ' . $a['prenume'];
        $out[] = ['tip' => 'Medicina muncii', 'firma' => $a['denumire'], 'firma_id' => $a['firma_id'], 'angajat' => $nm, 'aid' => $a['id'], 'ce' => 'Aviz de aptitudine', 'data' => $a['medical_expira'], 'zile' => days_until($a['medical_expira'])];
    }
    foreach (rows("SELECT i.*, c.titlu, a.nume, a.prenume, a.firma_id, f.denumire FROM instruiri i JOIN cursuri c ON c.id=i.curs_id JOIN angajati a ON a.id=i.angajat_id JOIN firme f ON f.id=a.firma_id WHERE a.activ=1$w") as $i) {
        $nm = $i['nume'] . ' ' . $i['prenume'];
        if ($i['status'] === 'semnat') {
            $newer = val("SELECT COUNT(*) FROM instruiri WHERE angajat_id=? AND curs_id=? AND id>?", [$i['angajat_id'], $i['curs_id'], $i['id']]);
            if (!$newer) $out[] = ['tip' => 'Instruire periodică', 'firma' => $i['denumire'], 'firma_id' => $i['firma_id'], 'angajat' => $nm, 'aid' => $i['angajat_id'], 'ce' => $i['titlu'], 'data' => $i['urmatoarea'], 'zile' => days_until($i['urmatoarea'])];
        } else {
            $out[] = ['tip' => 'Instruire nefinalizată', 'firma' => $i['denumire'], 'firma_id' => $i['firma_id'], 'angajat' => $nm, 'aid' => $i['angajat_id'], 'ce' => $i['titlu'] . ' – ' . strip_tags(status_badge($i['status'])), 'data' => null, 'zile' => null];
        }
    }
    usort($out, fn($a, $b) => ($a['zile'] ?? -1) <=> ($b['zile'] ?? -1));
    return $out;
}
function r_dashboard(): void {
    require_role('superadmin', 'inspector', 'client');
    $T = termene(); $flt = $_GET['f'] ?? 'urgent';
    $cnt = ['expirat' => 0, '7' => 0, '15' => 0, '30' => 0, 'nef' => 0];
    foreach ($T as $t) { $z = $t['zile'];
        if ($z === null) $cnt['nef']++; elseif ($z < 0) $cnt['expirat']++; elseif ($z <= 7) $cnt['7']++; elseif ($z <= 15) $cnt['15']++; elseif ($z <= 30) $cnt['30']++; }
    $match = fn($t) => match ($flt) { 'expirat' => $t['zile'] !== null && $t['zile'] < 0, '7' => $t['zile'] !== null && $t['zile'] >= 0 && $t['zile'] <= 7, '15' => $t['zile'] !== null && $t['zile'] > 7 && $t['zile'] <= 15, '30' => $t['zile'] !== null && $t['zile'] > 15 && $t['zile'] <= 30, 'nef' => $t['zile'] === null, 'toate' => true, default => $t['zile'] === null || $t['zile'] <= 30 };
    $card = fn($k, $l, $c) => '<a class="stat ' . $c . ($flt === $k ? ' on' : '') . '" href="' . url('dashboard', ['f' => $k]) . '"><b>' . $cnt[$k] . '</b><span>' . $l . '</span></a>';
    $rowsh = '';
    foreach (array_filter($T, $match) as $t) $rowsh .= '<tr><td data-l="Termen">' . ($t['zile'] === null ? status_badge('alocat') : badge_days($t['zile'])) . '</td><td data-l="Tip">' . h($t['tip']) . '</td><td data-l="Angajat"><a href="' . url('angajat', ['id' => $t['aid']]) . '">' . h($t['angajat']) . '</a></td><td data-l="Firmă">' . h($t['firma']) . '</td><td data-l="Detalii">' . h($t['ce']) . '</td><td data-l="Data">' . fmt_date($t['data']) . '</td></tr>';
    if (!$rowsh) $rowsh = '<tr><td colspan="9" class="empty">Nimic în această categorie. 👍</td></tr>';
    // semafor pe firmă
    $sem = [];
    foreach ($T as $t) { $s = &$sem[$t['firma']]; $s ??= 'green'; if ($t['zile'] !== null && $t['zile'] < 0) $s = 'red'; elseif ($s !== 'red' && ($t['zile'] === null || $t['zile'] <= 30)) $s = 'yellow'; unset($s); }
    $semh = ''; foreach ($sem as $f => $c) $semh .= '<div class="sem"><i class="dot ' . $c . '"></i>' . h($f) . '</div>';
    $sfw = scope_firma() ? ' AND a.firma_id=' . scope_firma() : ''; $grp = []; $types = ['Introductiv-generală', 'La locul de muncă', 'Periodică', 'Suplimentară', 'Alte'];
    foreach (rows("SELECT c.tip, c.domeniu, i.status, i.urmatoarea FROM instruiri i JOIN cursuri c ON c.id=i.curs_id JOIN angajati a ON a.id=i.angajat_id WHERE a.activ=1$sfw") as $r) {
        $due = $r['status'] !== 'semnat' || ($r['urmatoarea'] && $r['urmatoarea'] <= today()); if (!$due) continue;
        $d = ($r['domeniu'] === 'SU' || $r['tip'] === 'SU / PSI') ? 'PSI / SU' : 'SSM'; $t = in_array($r['tip'], $types) ? $r['tip'] : 'Alte'; $grp[$d][$t] = ($grp[$d][$t] ?? 0) + 1; }
    $gt = ''; foreach (['SSM', 'PSI / SU'] as $d) { $gt .= '<tr><th>' . $d . '</th>'; foreach ($types as $t) $gt .= '<td class="c">' . (($n = $grp[$d][$t] ?? 0) ? '<b>' . $n . '</b>' : '<span class="muted">0</span>') . '</td>'; $gt .= '</tr>'; }
    $ndoc = (int)val("SELECT COUNT(*) FROM documente d WHERE d.sablon_cod IS NOT NULL AND d.status<>'finalizat'" . (scope_firma() ? ' AND d.firma_id=' . scope_firma() : ''));
    $neq = (int)val("SELECT COUNT(*) FROM echipamente e WHERE e.urmatoarea IS NOT NULL AND e.urmatoarea<=?" . (scope_firma() ? ' AND e.firma_id=' . scope_firma() : ''), [date('Y-m-d', strtotime('+30 days'))]);
    $today = '<div class="card"><h2>De efectuat azi <span class="help" title="Instruiri alocate și nefinalizate sau instruiri periodice ajunse la termen, grupate pe tipul instruirii. Detaliile sunt în tabelul de mai jos.">?</span></h2><table class="tbl mini"><thead><tr><th></th>' . implode('', array_map(fn($t) => '<th>' . $t . '</th>', $types)) . '</tr></thead><tbody>' . $gt . '</tbody></table><div class="tools"><a class="btn sm ghost" href="' . url('documente', ['st' => 'de_semnat']) . '">📄 ' . $ndoc . ' documente nefinalizate</a><a class="btn sm ghost" href="' . url('rapoarte', ['tip' => 'echipamente']) . '">🧯 ' . $neq . ' verificări echipamente în ≤30 zile</a></div></div>';
    page('Centru de termene', $today . '<div class="stats">' . $card('expirat', 'Expirate', 'red') . $card('7', 'Expiră în ≤ 7 zile', 'red') . $card('15', 'Expiră în 8–15 zile', 'orange') . $card('30', 'Expiră în 16–30 zile', 'yellow') . $card('nef', 'Instruiri nefinalizate', 'gray') . '</div>
    <div class="card"><h2>Semafor conformitate pe firme</h2>' . ($semh ?: '<p class="muted">Nicio firmă cu angajați.</p>') . '</div>
    <div class="tools"><a class="btn ghost sm" href="' . url('dashboard', ['f' => 'urgent']) . '">Toate urgențele (≤30 zile)</a><a class="btn ghost sm" href="' . url('dashboard', ['f' => 'toate']) . '">Toate termenele</a><a class="btn ghost sm" href="' . url('rapoarte', ['tip' => 'termene']) . '">Raport termene</a></div>
    <div class="card p0"><table class="tbl"><thead><tr><th>Termen</th><th>Tip</th><th>Angajat</th><th>Firmă</th><th>Detalii</th><th>Data</th></tr></thead><tbody>' . $rowsh . '</tbody></table></div>');
}

/* ---------- import Excel / CSV ---------- */
function read_sheet(string $path, string $name): array {
    if (preg_match('/\.xlsx$/i', $name)) {
        $z = new ZipArchive(); if ($z->open($path) !== true) throw new RuntimeException('Fișier Excel invalid.');
        $ss = []; if (($x = $z->getFromName('xl/sharedStrings.xml')) !== false) { $sx = simplexml_load_string($x); foreach ($sx->si as $si) { $t = ''; if (isset($si->t)) $t = (string)$si->t; else foreach ($si->r as $r) $t .= (string)$r->t; $ss[] = $t; } }
        $sh = simplexml_load_string($z->getFromName('xl/worksheets/sheet1.xml')); $out = [];
        foreach ($sh->sheetData->row as $r) { $line = [];
            foreach ($r->c as $c) { preg_match('/^([A-Z]+)/', (string)$c['r'], $m); $col = 0; foreach (str_split($m[1]) as $ch) $col = $col * 26 + ord($ch) - 64;
                $v = (string)$c->v; $t = (string)$c['t']; if ($t === 's') $v = $ss[(int)$v] ?? ''; elseif ($t === 'inlineStr') $v = (string)$c->is->t; $line[$col - 1] = $v; }
            if ($line) { $mx = max(array_keys($line)); $o = []; for ($i = 0; $i <= $mx; $i++) $o[] = $line[$i] ?? ''; $out[] = $o; } }
        return $out;
    }
    $txt = file_get_contents($path); $txt = preg_replace('/^\xEF\xBB\xBF/', '', $txt);
    $first = strtok($txt, "\n"); $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    $out = []; $fh = fopen('php://memory', 'r+'); fwrite($fh, $txt); rewind($fh);
    while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) if (array_filter($r, fn($x) => trim((string)$x) !== '')) $out[] = $r;
    return $out;
}
function norm_date(string $v): ?string {
    $v = trim($v); if ($v === '') return null;
    if (is_numeric($v) && $v > 20000) return date('Y-m-d', (int)(($v - 25569) * 86400));
    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    return 'X';
}
function r_import_template(): void {
    require_role('superadmin', 'inspector', 'client');
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="model_import_angajati.csv"');
    echo "\xEF\xBB\xBFnume;prenume;cnp;functie;cor;data_angajarii;telefon;email;medical_expira\nExemplu;Ion;1900101290017;Muncitor;931301;01.03.2024;0700000000;;31.12.2026\n"; exit;
}
function r_import(bool $post): void {
    require_role('superadmin', 'inspector', 'client');
    $res = '';
    if ($post && !empty($_FILES['fisier']['tmp_name'])) {
        $firma = scope_firma() ?? (int)($_POST['firma_id'] ?? 0); $grupa = (int)($_POST['grupa_id'] ?? 0) ?: null; $punct = (int)($_POST['punct_id'] ?? 0) ?: null;
        if (!$firma || !row("SELECT id FROM firme WHERE id=?", [$firma])) { flash('Alegeți firma.', 'err'); redirect(url('import')); }
        try { $data = read_sheet($_FILES['fisier']['tmp_name'], $_FILES['fisier']['name']); } catch (Throwable $e) { flash('Nu am putut citi fișierul: ' . $e->getMessage(), 'err'); redirect(url('import')); }
        $hdr = array_map(fn($x) => strtolower(trim(str_replace([' ', 'ă', 'â', 'î', 'ș', 'ț'], ['_', 'a', 'a', 'i', 's', 't'], mb_strtolower((string)$x)))), array_shift($data) ?? []);
        $alias = ['data_angajare' => 'data_angajarii', 'medicina_muncii' => 'medical_expira', 'aviz_medical' => 'medical_expira', 'functia' => 'functie', 'cod_cor' => 'cor'];
        $hdr = array_map(fn($x) => $alias[$x] ?? $x, $hdr);
        if (!in_array('nume', $hdr) || !in_array('cnp', $hdr)) { flash('Antetul trebuie să conțină cel puțin coloanele „nume” și „cnp”. Descărcați modelul.', 'err'); redirect(url('import')); }
        $ok = 0; $errs = [];
        foreach ($data as $n => $r) {
            $x = []; foreach ($hdr as $i => $k) $x[$k] = trim((string)($r[$i] ?? ''));
            $line = $n + 2; $cnp = preg_replace('/\D/', '', $x['cnp'] ?? '');
            if (($x['nume'] ?? '') === '' || ($x['prenume'] ?? '') === '') { $errs[] = "Rândul $line: lipsește numele sau prenumele."; continue; }
            if (!valid_cnp($cnp)) { $errs[] = "Rândul $line ({$x['nume']}): CNP invalid."; continue; }
            if (val("SELECT COUNT(*) FROM angajati WHERE cnp=? AND firma_id=?", [$cnp, $firma])) { $errs[] = "Rândul $line ({$x['nume']}): CNP deja existent – omis."; continue; }
            $da = norm_date($x['data_angajarii'] ?? ''); $me = norm_date($x['medical_expira'] ?? '');
            if ($da === 'X' || $me === 'X') { $errs[] = "Rândul $line ({$x['nume']}): dată invalidă (folosiți ZZ.LL.AAAA)."; continue; }
            insert('angajati', ['firma_id' => $firma, 'punct_id' => $punct, 'grupa_id' => $grupa, 'nume' => $x['nume'], 'prenume' => $x['prenume'], 'cnp' => $cnp, 'functie' => $x['functie'] ?? '', 'cor' => $x['cor'] ?? '', 'data_angajarii' => $da, 'telefon' => $x['telefon'] ?? '', 'email' => $x['email'] ?? '', 'medical_expira' => $me, 'cod_acces' => gen_code(), 'activ' => 1]);
            $ok++;
        }
        if ($grupa) foreach (rows("SELECT id FROM program WHERE grupa_id=?", [$grupa]) as $p) allocate_program((int)$p['id']);
        $res = '<div class="card"><h2>Rezultat import</h2><p><span class="b b-green">' . $ok . ' angajați importați</span> ' . ($errs ? '<span class="b b-red">' . count($errs) . ' rânduri cu probleme</span>' : '') . '</p>' . ($errs ? '<ul class="errs"><li>' . implode('</li><li>', array_map('h', $errs)) . '</li></ul>' : '') . '<a class="btn" href="' . url('list', ['e' => 'angajati']) . '">Vezi angajații</a></div>';
    }
    $E = ent('angajati');
    $firm = scope_firma() ? '' : '<label>Firmă <i class="req">*</i>' . select_html('firma_id', ($E['fields']['firma_id'][3])(), '', '— alegeți —', true) . '</label>';
    page('Import angajați din Excel / CSV', $res . '<form method="post" enctype="multipart/form-data" class="card form">' . csrf_field() . '<p>Acceptăm fișiere <b>.xlsx</b> (prima foaie) sau <b>.csv</b> (separator „;” sau „,”). Prima linie = antet. Coloane: <code>nume, prenume, cnp, functie, cor, data_angajarii, telefon, email, medical_expira</code>. <a href="' . url('import_template') . '">Descarcă modelul</a></p><div class="grid">' . $firm .
        '<label>Grupă de instruire (opțional)' . select_html('grupa_id', ($E['fields']['grupa_id'][3])(), '', '— fără —') . '</label><label>Punct de lucru (opțional)' . select_html('punct_id', ($E['fields']['punct_id'][3])(), '', '— fără —') . '</label><label>Fișier <i class="req">*</i><input type="file" name="fisier" accept=".xlsx,.csv" required></label></div><div class="savebar"><button class="btn">Importă</button></div></form>');
}

/* ---------- documente + fișa individuală ---------- */
function r_fisa(): void {
    $i = row("SELECT * FROM instruiri WHERE id=?", [(int)$_GET['id']]);
    if (!$i) { page('Negăsit', '<div class="card">Fișa nu există.</div>'); return; }
    $a = row("SELECT * FROM angajati WHERE id=?", [$i['angajat_id']]);
    $okAccess = role() === 'angajat' ? (int)user()['angajat_id'] === (int)$a['id'] : (is_staff() || scope_firma() === (int)$a['firma_id']);
    if (!$okAccess || $i['status'] !== 'semnat') { http_response_code(403); page('Acces interzis', '<div class="card">Fișa nu este disponibilă.</div>'); return; }
    $f = row("SELECT * FROM firme WHERE id=?", [$a['firma_id']]); $c = row("SELECT * FROM cursuri WHERE id=?", [$i['curs_id']]);
    $p = $i['program_id'] ? row("SELECT * FROM program WHERE id=?", [$i['program_id']]) : null;
    $pl = $a['punct_id'] ? row("SELECT * FROM puncte WHERE id=?", [$a['punct_id']]) : null;
    $cnp = role() === 'angajat' ? mask_cnp($a['cnp']) : h($a['cnp']);
    $sig = str_starts_with((string)$i['semnatura'], 'data:image/png;base64,') ? '<img class="sig" src="' . h($i['semnatura']) . '" alt="semnătură">' : '';
    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fișa individuală de instructaj – ' . h($a['nume'] . ' ' . $a['prenume']) . '</title><link rel="stylesheet" href="style.css"></head><body class="doc">
    <div class="noprint docbar"><button class="btn" onclick="window.print()">Descarcă PDF / Tipărește</button> <a class="btn ghost" href="javascript:history.back()">← Înapoi</a></div>
    <div class="sheet"><div class="dochead"><div><b>' . h($f['denumire']) . '</b><br>CUI ' . h($f['cui']) . ' · ' . h($f['reg_com']) . '<br>' . h($f['adresa']) . '</div><div class="right"><span class="mark">B</span> întocmit prin<br><b>BENIAL CONSULTING</b></div></div>
    <h2 class="center">FIȘĂ INDIVIDUALĂ DE INSTRUCTAJ<br><small>privind securitatea și sănătatea în muncă</small></h2>
    <table class="doctbl"><tr><th>Nume și prenume</th><td>' . h($a['nume'] . ' ' . $a['prenume']) . '</td><th>CNP</th><td>' . $cnp . '</td></tr>
    <tr><th>Funcția / COR</th><td>' . h($a['functie']) . ' / ' . h($a['cor']) . '</td><th>Data angajării</th><td>' . fmt_date($a['data_angajarii']) . '</td></tr>
    <tr><th>Loc de muncă</th><td colspan="3">' . h(($pl['nume'] ?? '') . ' ' . ($pl['adresa'] ?? '')) . '</td></tr></table>
    <h3>Instruire efectuată</h3><table class="doctbl"><tr><th>Tip instruire</th><th>Tematica</th><th>Durată</th><th>Data</th><th>Rezultat test</th><th>Semnătura lucrătorului</th><th>Instructor</th></tr>
    <tr><td>' . h($c['tip']) . '</td><td>' . h($c['titlu']) . '</td><td>' . (int)$c['durata_min'] . ' min</td><td>' . h(date('d.m.Y H:i', strtotime($i['data_semnare']))) . '</td><td>' . (int)$i['scor'] . '% – admis</td><td>' . $sig . '</td><td>' . h($p['instructor'] ?? '—') . '<div class="sigline">semnătura</div></td></tr></table>
    <p>Următoarea instruire periodică: <b>' . fmt_date($i['urmatoarea']) . '</b></p>
    <p class="small">Subsemnatul declar că am primit instruirea și mi-am însușit cunoștințele prezentate. Document generat electronic la ' . h(date('d.m.Y H:i', strtotime($i['data_semnare']))) . ', ID instruire #' . (int)$i['id'] . '. Semnătura a fost desenată de lucrător în portalul angajatului.</p></div></body></html>';
}

/* ---------- rapoarte ---------- */
function r_rapoarte(): void {
    require_role('superadmin', 'inspector', 'client');
    $tip = $_GET['tip'] ?? 'stadiu'; $sf = scope_firma() ?? ((int)($_GET['firma'] ?? 0) ?: null); $pct = (int)($_GET['punct'] ?? 0) ?: null;
    $w = ($sf || $pct) ? ' WHERE ' . implode(' AND ', array_filter([$sf ? 'a.firma_id=' . $sf : '', $pct ? 'a.punct_id=' . $pct : ''])) : '';
    if ($x = report_ext($tip, $sf, $pct)) { [$hdr, $data] = $x; }
    elseif ($tip === 'angajati') { $hdr = ['Firmă', 'Nume', 'Prenume', 'CNP', 'Funcție', 'Grupă', 'Data angajării', 'Medicina muncii până la'];
        $data = array_map(fn($r) => [$r['denumire'], $r['nume'], $r['prenume'], mask_cnp($r['cnp']), $r['functie'], $r['grupa'], fmt_date($r['data_angajarii']), fmt_date($r['medical_expira'])], rows("SELECT a.*, f.denumire, g.nume AS grupa FROM angajati a JOIN firme f ON f.id=a.firma_id LEFT JOIN grupe g ON g.id=a.grupa_id$w ORDER BY f.denumire, a.nume"));
    } elseif ($tip === 'termene') { $hdr = ['Zile rămase', 'Tip', 'Angajat', 'Firmă', 'Detalii', 'Data'];
        $data = array_map(fn($t) => [$t['zile'] === null ? 'nefinalizat' : $t['zile'], $t['tip'], $t['angajat'], $t['firma'], $t['ce'], fmt_date($t['data'])], termene($sf));
    } elseif ($tip === 'situatie') { $hdr = ['Firmă', 'Angajat', 'Curs', 'Tip', 'Stare', 'Scor', 'Data testării', 'Data semnării'];
        $data = array_map(fn($r) => [$r['denumire'], $r['nume'] . ' ' . $r['prenume'], $r['titlu'], $r['tip'], strip_tags(status_badge($r['status'])), $r['scor'] !== null ? $r['scor'] . '%' : '', $r['data_test'] ? date('d.m.Y', strtotime($r['data_test'])) : '', fmt_date($r['data_semnare'])], rows("SELECT i.*, a.nume, a.prenume, f.denumire, c.titlu, c.tip FROM instruiri i JOIN angajati a ON a.id=i.angajat_id JOIN firme f ON f.id=a.firma_id JOIN cursuri c ON c.id=i.curs_id$w ORDER BY i.data_test DESC"));
    } else { $tip = 'stadiu'; $hdr = ['Firmă', 'Angajat', 'Curs', 'Stare', 'Scor', 'Data semnării', 'Următoarea'];
        $data = array_map(fn($r) => [$r['denumire'], $r['nume'] . ' ' . $r['prenume'], $r['titlu'], strip_tags(status_badge($r['status'])), $r['scor'] !== null ? $r['scor'] . '%' : '', fmt_date($r['data_semnare']), fmt_date($r['urmatoarea'])], rows("SELECT i.*, a.nume, a.prenume, f.denumire, c.titlu FROM instruiri i JOIN angajati a ON a.id=i.angajat_id JOIN firme f ON f.id=a.firma_id JOIN cursuri c ON c.id=i.curs_id$w ORDER BY f.denumire, a.nume"));
    }
    if (!empty($_GET['csv'])) { header('Content-Type: text/csv; charset=utf-8'); header("Content-Disposition: attachment; filename=\"raport_$tip.csv\""); echo "\xEF\xBB\xBF"; $o = fopen('php://output', 'w'); fputcsv($o, $hdr, ';', '"', ''); foreach ($data as $d) fputcsv($o, $d, ';', '"', ''); exit; }
    $names = ['stadiu' => 'Stadiu instruire', 'situatie' => 'Situație instruiri / testare', 'plan_lunar' => 'Planificare lunară', 'plan_anual' => 'Planificare anuală', 'grafic' => 'Grafic instruire', 'istoric' => 'Istoric instruiri', 'angajati' => 'Listă angajați', 'meserii' => 'Meserii autorizate', 'echipe' => 'Echipe intervenție / evacuare', 'medicina' => 'Medicina muncii', 'echipamente' => 'Echipamente de muncă', 'stingere' => 'Mijloace de stingere', 'audit' => 'Audit SSM / PSI', 'neconf' => 'Neconformități audit', 'termene' => 'Termene'];
    if (!isset($names[$tip])) $tip = 'stadiu';
    $tabs = ''; foreach ($names as $k => $l) $tabs .= '<a class="btn sm ' . ($k === $tip ? '' : 'ghost') . '" href="' . url('rapoarte', ['tip' => $k] + ($sf && !scope_firma() ? ['firma' => $sf] : []) + ($pct ? ['punct' => $pct] : [])) . '">' . $l . '</a>';
    $th = implode('', array_map(fn($x) => '<th>' . h($x) . '</th>', $hdr)); $tb = '';
    foreach ($data as $d) { $tb .= '<tr>'; foreach ($d as $j => $x) $tb .= '<td data-l="' . h($hdr[$j]) . '">' . h($x) . '</td>'; $tb .= '</tr>'; }
    if (!$data) $tb = '<tr><td colspan="99" class="empty">Nu există date.</td></tr>';
    $flt = '<form class="tools noprint" method="get"><input type="hidden" name="r" value="rapoarte"><input type="hidden" name="tip" value="' . $tip . '">' . (scope_firma() ? '' : select_html('firma', opts("SELECT id, denumire AS l FROM firme", false), $sf ?? '', 'Toate firmele')) . select_html('punct', opts("SELECT id, nume AS l FROM puncte" . ($sf ? ' WHERE firma_id=' . $sf : ''), false), $pct ?? '', 'Toate punctele de lucru') . ($tip === 'plan_lunar' ? select_html('luna', luni_ro(), $_GET['luna'] ?? date('n'), 'Luna') : '') . '<button class="btn ghost">Filtrează</button></form>';
    page('Rapoarte', '<div class="tools noprint">' . $tabs . '</div>' . $flt . '<div class="tools noprint"><a class="btn ghost" href="' . url('rapoarte', ['tip' => $tip, 'csv' => 1] + ($sf ? ['firma' => $sf] : []) + ($pct ? ['punct' => $pct] : []) + (isset($_GET['luna']) ? ['luna' => (int)$_GET['luna']] : [])) . '">Export Excel (CSV)</a><button class="btn ghost" onclick="window.print()">Tipărește / PDF</button></div><div class="card p0"><h2 class="pad">' . $names[$tip] . ' – ' . date('d.m.Y') . '</h2><table class="tbl"><thead><tr>' . $th . '</tr></thead><tbody>' . $tb . '</tbody></table></div>');
}

/* ---------- portal angajat ---------- */
function my_ins(int $id): array {
    require_role('angajat');
    $i = row("SELECT i.*, c.titlu, c.durata_min, c.prag_promovare, c.descriere, c.tip FROM instruiri i JOIN cursuri c ON c.id=i.curs_id WHERE i.id=? AND i.angajat_id=?", [$id, user()['angajat_id']]);
    if (!$i) { page('Negăsit', '<div class="card">Instruirea nu există.</div>'); exit; }
    return $i;
}
function r_portal(): void {
    require_role('angajat');
    $a = row("SELECT * FROM angajati WHERE id=?", [user()['angajat_id']]);
    $I = rows("SELECT i.*, c.titlu, c.durata_min FROM instruiri i JOIN cursuri c ON c.id=i.curs_id WHERE angajat_id=? ORDER BY i.id DESC", [$a['id']]);
    $h = '';
    foreach ($I as $i) {
        $btn = match ($i['status']) { 'alocat' => '<a class="btn" href="' . url('take', ['id' => $i['id']]) . '">Începe instruirea</a>', 'picat' => '<a class="btn" href="' . url('take', ['id' => $i['id']]) . '">Reia testul</a>', 'promovat' => '<a class="btn" href="' . url('take', ['id' => $i['id']]) . '">Semnează fișa</a>', 'semnat' => '<a class="btn ghost" href="' . url('fisa', ['id' => $i['id']]) . '">Vezi fișa</a>', default => '' };
        $h .= '<div class="card task"><div><h3>' . h($i['titlu']) . '</h3><p class="muted">' . (int)$i['durata_min'] . ' minute · ' . status_badge($i['status']) . ($i['urmatoarea'] ? ' · următoarea: ' . fmt_date($i['urmatoarea']) : '') . '</p></div>' . $btn . '</div>';
    }
    if (!$h) $h = '<div class="card">Nu aveți instruiri alocate.</div>';
    $T = docs_to_sign(); $ds = '';
    if ($T) { $ds = '<div class="card"><h2>Documente de semnat (' . count($T) . ') <a class="btn" href="' . url('semneaza') . '">Semnează toate</a></h2><table class="tbl"><tbody>';
        foreach ($T as $d) { $an = $d['angajat_id'] && (int)$d['angajat_id'] !== (int)$a['id'] ? row("SELECT nume, prenume FROM angajati WHERE id=?", [$d['angajat_id']]) : null; $ds .= '<tr><td>' . h($d['titlu']) . ($an ? ' – ' . h($an['nume'] . ' ' . $an['prenume']) : '') . '</td><td><span class="b b-gray">ca ' . $d['ca'] . '</span></td><td class="act"><a class="btn sm ghost" href="' . url('doc', ['id' => $d['id']]) . '">Citește</a><a class="btn sm" href="' . url('semneaza', ['ids' => [$d['id']]]) . '">Semnează</a></td></tr>'; }
        $ds .= '</tbody></table></div>'; }
    $mine = rows("SELECT * FROM documente WHERE angajat_id=? AND sablon_cod IS NOT NULL ORDER BY id DESC", [$a['id']]);
    $dm = $mine ? '<h2>Documentele mele</h2><div class="card p0"><table class="tbl"><tbody>' . implode('', array_map(fn($d) => '<tr><td>' . h($d['titlu']) . '</td><td>' . fmt_date($d['data_doc']) . '</td><td>' . doc_status_label($d) . '</td><td class="act"><a class="btn sm ghost" href="' . url('doc', ['id' => $d['id']]) . '">Deschide / PDF</a></td></tr>', $mine)) . '</tbody></table></div>' : '';
    page('Bună, ' . $a['prenume'] . '!', '<div class="card"><dl class="dl"><div><dt>Aviz medicina muncii</dt><dd>' . fmt_date($a['medical_expira']) . ' ' . badge_days(days_until($a['medical_expira'])) . '</dd></div><div><dt>Funcție</dt><dd>' . h($a['functie']) . '</dd></div></dl></div>' . $ds . '<h2>Instruirile mele</h2>' . $h . $dm);
}
function r_take(): void {
    $i = my_ins((int)$_GET['id']);
    if ($i['status'] === 'semnat') redirect(url('fisa', ['id' => $i['id']]));
    if ($i['status'] === 'promovat') {
        page('Semnează fișa de instructaj', '<div class="card"><p>Ați promovat testul cu <b>' . (int)$i['scor'] . '%</b>. Semnați cu degetul sau mouse-ul în chenar pentru a confirma că ați primit instruirea „' . h($i['titlu']) . '”.</p>
        <canvas id="pad" class="pad-canvas"></canvas><div class="tools"><button type="button" class="btn ghost" id="clear">Șterge semnătura</button></div>
        <form method="post" action="' . url('sign', ['id' => $i['id']]) . '" id="signform">' . csrf_field() . '<input type="hidden" name="sig" id="sig"><label class="chk"><input type="checkbox" required name="decl" value="1"> Declar că am parcurs instruirea și mi-am însușit cunoștințele.</label><div class="savebar"><button class="btn">Semnează și generează fișa</button></div></form></div>');
        return;
    }
    $L = rows("SELECT * FROM lectii WHERE curs_id=? ORDER BY ordine", [$i['curs_id']]); $Q = rows("SELECT * FROM intrebari WHERE curs_id=? ORDER BY id", [$i['curs_id']]);
    $lh = '';
    foreach ($L as $n => $l) {
        $v = ''; if ($l['video_url']) $v = preg_match('/\.mp4($|\?)/', $l['video_url']) ? '<video controls src="' . h($l['video_url']) . '"></video>' : '<div class="video"><iframe src="' . h($l['video_url']) . '" allowfullscreen loading="lazy"></iframe></div>';
        $lh .= '<details class="card lesson"' . ($n === 0 ? ' open' : '') . '><summary>Lecția ' . (int)$l['ordine'] . ': ' . h($l['titlu']) . '</summary><div class="txt">' . nl2br(h($l['continut'])) . '</div>' . $v . up_list(up_files('lectii', (int)$l['id'])) . '</details>';
    }
    $c = row("SELECT * FROM cursuri WHERE id=?", [$i['curs_id']]);
    if (($c['mod_test'] ?? '') === 'offline') { page($i['titlu'], $lh . '<div class="card"><p>Testul pentru această instruire se susține <b>pe hârtie</b>. După ce inspectorul înregistrează rezultatul, veți putea semna fișa aici.</p></div>'); return; }
    if ((int)$c['nr_intrebari'] > 0 && count($Q) > (int)$c['nr_intrebari']) { shuffle($Q); $Q = array_slice($Q, 0, (int)$c['nr_intrebari']); }
    $_SESSION['test_' . $i['id']] = array_column($Q, 'id');
    $qh = '';
    foreach ($Q as $n => $q) { $multi = str_contains((string)$q['corect'], ','); $qh .= '<fieldset class="q"><legend>' . ($n + 1) . '. ' . h($q['text']) . ($multi ? ' <small class="muted">(mai multe răspunsuri corecte)</small>' : '') . '</legend>';
        foreach (['a', 'b', 'c', 'd'] as $o) if (trim((string)($q['opt_' . $o] ?? '')) !== '') $qh .= '<label class="opt"><input type="' . ($multi ? 'checkbox' : 'radio') . '" name="q' . $q['id'] . '[]" value="' . $o . '"' . ($multi ? '' : ' required') . '> ' . h($q['opt_' . $o]) . '</label>'; $qh .= '</fieldset>'; }
    $timer = (int)$c['timp_test_min'] > 0 ? '<div class="timer" data-min="' . (int)$c['timp_test_min'] . '">⏱ <span></span></div>' : '';
    $warn = $i['status'] === 'picat' ? '<div class="flash err">Data trecută ați obținut ' . (int)$i['scor'] . '%. Recitiți lecțiile și reluați testul (prag: ' . (int)$i['prag_promovare'] . '%).</div>' : '';
    $cf = up_list(up_files('cursuri', (int)$i['curs_id'])); if ($cf) $lh = '<div class="card"><h3>Materiale curs</h3>' . $cf . '</div>' . $lh;
    page($i['titlu'], $warn . '<p class="muted">Parcurgeți lecțiile, apoi răspundeți la test. Prag de promovare: ' . (int)$i['prag_promovare'] . '%.</p>' . $lh . '<h2>Test</h2>' . $timer . '<form method="post" action="' . url('submit_test', ['id' => $i['id']]) . '" class="card" id="testform">' . csrf_field() . $qh . '<div class="savebar"><button class="btn">Trimite răspunsurile</button></div></form>');
}
function r_submit_test(): void {
    $i = my_ins((int)$_GET['id']);
    if (!in_array($i['status'], ['alocat', 'picat'])) redirect(url('take', ['id' => $i['id']]));
    $ids = $_SESSION['test_' . $i['id']] ?? null;
    $Q = array_values(array_filter(rows("SELECT * FROM intrebari WHERE curs_id=? ORDER BY id", [$i['curs_id']]), fn($q) => !$ids || in_array($q['id'], $ids)));
    $ok = 0; $pts = 0; $max = 0; $det = [];
    foreach ($Q as $q) { $corr = array_filter(explode(',', (string)$q['corect'])); sort($corr); $ans = array_map('strval', (array)($_POST['q' . $q['id']] ?? [])); sort($ans);
        $w = max(1, (int)($q['puncte'] ?? 1)); $max += $w; $good = $ans === $corr; if ($good) { $ok++; $pts += $w; }
        $det[] = [$q['text'], strtoupper(implode(',', $ans)) ?: '—', $good]; }
    $scor = $max ? (int)round($pts * 100 / $max) : 100; $pass = $scor >= (int)$i['prag_promovare'];
    update('instruiri', (int)$i['id'], ['scor' => $scor, 'status' => $pass ? 'promovat' : 'picat', 'data_test' => date('Y-m-d H:i')]);
    $_SESSION['test_res_' . $i['id']] = ['nr' => count($Q), 'puncte' => $pts, 'max' => $max, 'raspunsuri' => $det]; unset($_SESSION['test_' . $i['id']]);
    flash($pass ? "Felicitări! Ați răspuns corect la $ok din " . count($Q) . " întrebări ($scor%)." : "Ați răspuns corect la $ok din " . count($Q) . " ($scor%). Nu ați atins pragul – reluați testul.", $pass ? 'ok' : 'err');
    redirect(url('take', ['id' => $i['id']]));
}
function r_sign(): void {
    $i = my_ins((int)$_GET['id']);
    if ($i['status'] !== 'promovat') redirect(url('take', ['id' => $i['id']]));
    $sig = $_POST['sig'] ?? '';
    if (!str_starts_with($sig, 'data:image/png;base64,') || strlen($sig) > 600000 || strlen($sig) < 1500 || empty($_POST['decl'])) { flash('Desenați semnătura și bifați declarația.', 'err'); redirect(url('take', ['id' => $i['id']])); }
    $per = (int)($i['program_id'] ? val("SELECT periodicitate_luni FROM program WHERE id=?", [$i['program_id']]) : 0) ?: (int)(val("SELECT g.periodicitate_luni FROM angajati a JOIN grupe g ON g.id=a.grupa_id WHERE a.id=?", [$i['angajat_id']]) ?: 12);
    update('instruiri', (int)$i['id'], ['status' => 'semnat', 'semnatura' => $sig, 'data_semnare' => date('Y-m-d H:i'), 'urmatoarea' => add_months(today(), $per)]);
    $a = row("SELECT * FROM angajati WHERE id=?", [$i['angajat_id']]); $c = row("SELECT * FROM cursuri WHERE id=?", [$i['curs_id']]);
    $su = ($c['domeniu'] ?? '') === 'SU' || $c['tip'] === 'SU / PSI'; $pfx = $su ? 'SU' : 'SSM';
    $cod = $pfx . '-FIS-' . (in_array($c['tip'], ['Introductiv-generală', 'La locul de muncă']) ? 'ANG' : ($c['tip'] === 'Suplimentară' && !$su ? 'SUP' : 'PER'));
    $instr = $i['program_id'] ? (string)val("SELECT instructor FROM program WHERE id=?", [$i['program_id']]) : '';
    $now = date('Y-m-d H:i'); $res = $_SESSION['test_res_' . $i['id']] ?? [];
    $did = doc_generate($cod, (int)$a['firma_id'], null, (int)$a['id'], today(), $instr, (int)$i['id']); update('documente', $did, ['semn_ang' => $sig, 'semn_ang_data' => $now]); doc_refresh_status($did);
    if (!empty($c['emite_fisa16'])) { $tid = doc_generate($pfx . '-TEST', (int)$a['firma_id'], null, (int)$a['id'], today(), $instr, (int)$i['id'], ['instruire' => $res]); update('documente', $tid, ['semn_ang' => $sig, 'semn_ang_data' => $now]); doc_refresh_status($tid); }
    notify_sign_pending([(int)val("SELECT clm_id FROM documente WHERE id=?", [$did])]);
    flash('Fișa a fost semnată. Urmează semnătura conducătorului locului de muncă.');
    redirect(url('doc', ['id' => $did]));
}

function r_offline(): void {
    require_role('superadmin', 'inspector'); $a = load_scoped(ent('angajati'), (int)$_GET['id']);
    $i = row("SELECT i.*, c.prag_promovare FROM instruiri i JOIN cursuri c ON c.id=i.curs_id WHERE i.id=? AND i.angajat_id=? AND i.status<>'semnat'", [(int)($_POST['ins'] ?? 0), $a['id']]);
    $sc = max(0, min(100, (int)($_POST['scor'] ?? 0)));
    if ($i) { update('instruiri', (int)$i['id'], ['scor' => $sc, 'status' => $sc >= (int)$i['prag_promovare'] ? 'promovat' : 'picat', 'data_test' => date('Y-m-d H:i')]); flash('Rezultat înregistrat. Dacă e promovat, angajatul semnează fișa în portal.'); }
    redirect(url('angajat', ['id' => $a['id']]));
}

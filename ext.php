<?php
/* ===== Extensii BENIAL: documente cu flux de semnare, șabloane, echipamente, audit, mesaje, chat, tichete ===== */
require_once __DIR__ . '/templates.php';

function has_col(string $t, string $c): bool { try { db()->query("SELECT $c FROM $t WHERE 1=0"); return true; } catch (Throwable $e) { return false; } }
function migrate_ext(): void {
    $id = match (drv()) { 'pgsql' => 'SERIAL PRIMARY KEY', 'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY', default => 'INTEGER PRIMARY KEY AUTOINCREMENT' };
    $T = drv() === 'mysql' ? 'LONGTEXT' : 'TEXT';
    $V = fn($n = 190) => "VARCHAR($n)";
    $cols = [
        'firme' => ['domeniu' => $V(), 'tva' => $V(10), 'iban' => $V(40), 'banca' => $V(), 'firma_mama' => $V(), 'localitate' => $V(), 'judet' => $V(60), 'mod_lucru' => $V(10)],
        'puncte' => ['clm_id' => 'INT NULL', 'verificator' => $V(), 'localitate' => $V(), 'judet' => $V(60)],
        'angajati' => ['data_nasterii' => $V(10), 'username' => $V(100), 'password_hash' => $V(255), 'timp_lucru' => $V(40), 'marca' => $V(40), 'adresa' => $V(255), 'loc_munca' => $V(), 'categorie_pers' => $V(20), 'echipa' => $V(40), 'este_clm' => 'INT DEFAULT 0', 'resp_ssm' => 'INT DEFAULT 0', 'meserie_aut' => $V(255), 'aut_expira' => $V(10), 'medical_ultim' => $V(10), 'medical_aviz' => $V(40), 'puncte_extra' => $V(255)],
        'cursuri' => ['domeniu' => $V(10), 'firma_id' => 'INT NULL', 'grupa_id' => 'INT NULL', 'categorie_id' => 'INT NULL', 'tip_personal' => $V(20), 'acces' => $V(20), 'imagini' => $T, 'atasamente' => $T, 'mod_test' => $V(10), 'emite_fisa16' => 'INT DEFAULT 0', 'dificultate' => $V(20), 'timp_test_min' => 'INT NULL', 'nr_intrebari' => 'INT NULL'],
        'intrebari' => ['opt_d' => $V(255), 'puncte' => 'INT NULL'],
        'program' => ['tip_instruire' => $V(40), 'durata_ore' => $V(10), 'luni_instruire' => $V(60), 'ora' => $V(10), 'luna_test_anual' => 'INT NULL', 'test_la_angajare' => 'INT DEFAULT 0'],
        'documente' => ['sablon_cod' => $V(20), 'status' => $V(20), 'punct_id' => 'INT NULL', 'data_doc' => $V(10), 'continut' => $T, 'semn_ang' => $T, 'semn_ang_data' => $V(20), 'semn_clm' => $T, 'semn_clm_data' => $V(20), 'clm_id' => 'INT NULL', 'instructor' => $V(), 'semn_instr' => $T, 'semn_instr_data' => $V(20)],
    ];
    foreach ($cols as $t => $cs) foreach ($cs as $c => $def) if (!has_col($t, $c)) db()->exec("ALTER TABLE $t ADD COLUMN $c $def");
    if (drv() === 'mysql') { try { db()->exec("ALTER TABLE intrebari MODIFY corect VARCHAR(10)"); } catch (Throwable $e) {} }
    elseif (drv() === 'pgsql') { try { db()->exec("ALTER TABLE intrebari ALTER COLUMN corect TYPE VARCHAR(10)"); } catch (Throwable $e) {} }
    $tables = [
        "categorii (id $id, parent_id INT NULL, nume VARCHAR(190), domeniu VARCHAR(10), tip VARCHAR(40), limba VARCHAR(10))",
        "sabloane (id $id, cod VARCHAR(20), nume VARCHAR(190), domeniu VARCHAR(10), tip VARCHAR(20), instr VARCHAR(120), firma_id INT NULL, continut $T, implicit INT DEFAULT 0)",
        "echipamente (id $id, firma_id INT, punct_id INT NULL, categorie VARCHAR(20), denumire VARCHAR(190), tip VARCHAR(120), serie VARCHAR(120), data_verificare VARCHAR(10), periodicitate_luni INT, urmatoarea VARCHAR(10), verificat_de VARCHAR(190), observatii $T)",
        "audituri (id $id, firma_id INT, punct_id INT NULL, data VARCHAR(10), domeniu VARCHAR(10), auditor VARCHAR(190), concluzii $T)",
        "neconformitati (id $id, audit_id INT, firma_id INT, descriere $T, masura $T, responsabil VARCHAR(190), termen VARCHAR(10), status VARCHAR(20))",
        "mesaje (id $id, from_k VARCHAR(20), to_k VARCHAR(20), subiect VARCHAR(190), corp $T, citit INT DEFAULT 0, created_at VARCHAR(20))",
        "chat (id $id, camera VARCHAR(20), autor_k VARCHAR(20), autor VARCHAR(190), text $T, created_at VARCHAR(20))",
        "tichete (id $id, autor_k VARCHAR(20), autor VARCHAR(190), firma_id INT NULL, subiect VARCHAR(190), prioritate VARCHAR(10), status VARCHAR(20), created_at VARCHAR(20))",
        "tichete_msg (id $id, tichet_id INT, autor_k VARCHAR(20), autor VARCHAR(190), text $T, created_at VARCHAR(20))",
    ];
    foreach ($tables as $t) db()->exec("CREATE TABLE IF NOT EXISTS $t");
    if (!(int)val("SELECT COUNT(*) FROM sabloane")) foreach (default_templates() as [$cod, $nume, $dom, $tip, $instr, $txt]) insert('sabloane', ['cod' => $cod, 'nume' => $nume, 'domeniu' => $dom, 'tip' => $tip, 'instr' => $instr, 'continut' => $txt, 'implicit' => 1]);
}

/* ---------- identitate pentru mesaje / chat ---------- */
function me_k(): string { $u = user(); return $u['role'] === 'angajat' ? 'a:' . $u['angajat_id'] : 'u:' . $u['id']; }
function k_name(string $k): string {
    [$t, $i] = explode(':', $k) + ['', 0];
    if ($t === 'a') { $a = row("SELECT nume, prenume FROM angajati WHERE id=?", [(int)$i]); return $a ? $a['nume'] . ' ' . $a['prenume'] . ' (angajat)' : '—'; }
    $u = row("SELECT name, role FROM users WHERE id=?", [(int)$i]); return $u ? $u['name'] : '—';
}
function my_firma(): ?int { $u = user(); return $u['role'] === 'angajat' ? (int)$u['firma_id'] : (role() === 'client' ? (int)$u['firma_id'] : null); }
function contacts(): array {
    $o = []; $f = my_firma();
    foreach (rows("SELECT id, name, role, firma_id FROM users ORDER BY name") as $u) if (is_staff() || in_array($u['role'], ['superadmin', 'inspector']) || ((int)$u['firma_id'] === $f && $f)) $o['u:' . $u['id']] = $u['name'];
    if (role() !== 'angajat') foreach (rows("SELECT id, nume, prenume FROM angajati WHERE activ=1" . ($f ? ' AND firma_id=' . $f : '') . " ORDER BY nume") as $a) $o['a:' . $a['id']] = $a['nume'] . ' ' . $a['prenume'] . ' (angajat)';
    unset($o[me_k()]); return $o;
}
function bell_count(): int {
    if (!user()) return 0; $n = (int)val("SELECT COUNT(*) FROM mesaje WHERE to_k=? AND citit=0", [me_k()]);
    if (role() === 'angajat') $n += count(docs_to_sign());
    elseif (role() === 'superadmin') $n += (int)val("SELECT COUNT(*) FROM tichete WHERE status='deschis'");
    return $n;
}

/* ---------- documente ---------- */
function sablon(string $cod, ?int $firma = null): ?array {
    return row("SELECT * FROM sabloane WHERE cod=? AND (firma_id=? OR firma_id IS NULL) ORDER BY firma_id DESC", [$cod, $firma]) ?: row("SELECT * FROM sabloane WHERE cod=?", [$cod]);
}
function doc_status_label(array $d): string {
    return ['de_semnat' => '<span class="b b-yellow">de semnat</span>', 'semnat_angajat' => '<span class="b b-orange">așteaptă CLM</span>', 'semnat_clm' => '<span class="b b-orange">așteaptă angajatul</span>', 'finalizat' => '<span class="b b-green">finalizat</span>'][$d['status'] ?? ''] ?? '<span class="b b-green">semnat</span>';
}
function doc_needs_ang(array $d): bool { $s = sablon((string)$d['sablon_cod']); return $s && $s['tip'] !== 'tematica' && $d['angajat_id']; }
function doc_refresh_status(int $id): void {
    $d = row("SELECT * FROM documente WHERE id=?", [$id]);
    $ang = !doc_needs_ang($d) || $d['semn_ang']; $clm = (bool)$d['semn_clm'];
    $st = $ang && $clm ? 'finalizat' : ($ang && doc_needs_ang($d) ? 'semnat_angajat' : ($clm ? 'semnat_clm' : 'de_semnat'));
    update('documente', $id, ['status' => $st]);
}
function doc_generate(string $cod, int $firma, ?int $punct, ?int $ang, string $data, string $instructor, ?int $instruire = null, array $extra = []): int {
    $s = sablon($cod, $firma); if (!$s) throw new RuntimeException("Șablon inexistent: $cod");
    if (!$punct && $ang) $punct = (int)val("SELECT punct_id FROM angajati WHERE id=?", [$ang]) ?: null;
    $clm = $punct ? ((int)val("SELECT clm_id FROM puncte WHERE id=?", [$punct]) ?: null) : null;
    $snap = ['sablon' => $s['nume'], 'instr' => $s['instr'], 'continut' => $s['continut']] + $extra;
    if ($instruire) { $i = row("SELECT i.*, c.titlu, c.durata_min, c.prag_promovare FROM instruiri i JOIN cursuri c ON c.id=i.curs_id WHERE i.id=?", [$instruire]); if ($i) $snap['instruire'] = ['titlu' => $i['titlu'], 'scor' => $i['scor'], 'durata_min' => $i['durata_min'], 'prag' => $i['prag_promovare'], 'data_test' => $i['data_test']] + ($extra['instruire'] ?? []); }
    $id = insert('documente', ['firma_id' => $firma, 'angajat_id' => $ang, 'instruire_id' => $instruire, 'punct_id' => $punct, 'tip' => $s['tip'] === 'tematica' ? 'Tematică' : ($s['tip'] === 'test' ? 'Rezultat testare' : 'Fișă individuală'), 'titlu' => $s['nume'], 'sablon_cod' => $cod, 'data_doc' => $data, 'continut' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'clm_id' => $clm, 'instructor' => $instructor, 'status' => 'de_semnat', 'created_at' => date('Y-m-d H:i')]);
    doc_refresh_status($id); return $id;
}
/** documentele pe care angajatul logat trebuie să le semneze (ca lucrător sau ca CLM) */
function docs_to_sign(): array {
    if (role() !== 'angajat') return []; $a = (int)user()['angajat_id'];
    $out = [];
    foreach (rows("SELECT * FROM documente WHERE angajat_id=? AND semn_ang IS NULL AND sablon_cod IS NOT NULL", [$a]) as $d) if (doc_needs_ang($d)) $out[$d['id']] = $d + ['ca' => 'angajat'];
    foreach (rows("SELECT * FROM documente WHERE clm_id=? AND semn_clm IS NULL AND sablon_cod IS NOT NULL", [$a]) as $d) $out[$d['id']] ??= $d + ['ca' => 'CLM'];
    return $out;
}
function doc_access(array $d): bool {
    if (is_staff()) return true;
    if (role() === 'client') return (int)$d['firma_id'] === scope_firma();
    if (role() === 'angajat') { $a = (int)user()['angajat_id']; return (int)$d['angajat_id'] === $a || (int)$d['clm_id'] === $a; }
    return false;
}
function sig_img(?string $s): string { return $s && str_starts_with($s, 'data:image/png;base64,') ? '<img class="sig" src="' . h($s) . '" alt="semnătură">' : '<span class="muted small">nesemnat</span>'; }
function lege(string $dom): string { return $dom === 'SU' ? 'Legea nr. 307/2006 privind apărarea împotriva incendiilor, Legea nr. 481/2004 privind protecția civilă, OMAI nr. 163/2007 (Norme generale PSI) și OMAI nr. 712/2005 (instruirea salariaților în domeniul situațiilor de urgență)' : 'Legea securității și sănătății în muncă nr. 319/2006 și Normele metodologice aprobate prin HG nr. 1425/2006'; }
function render_doc(array $d): string {
    $s = sablon((string)$d['sablon_cod'], (int)$d['firma_id']) ?? ['tip' => 'fisa', 'domeniu' => 'SSM'];
    $c = json_decode((string)$d['continut'], true) ?: [];
    $f = row("SELECT * FROM firme WHERE id=?", [$d['firma_id']]); $a = $d['angajat_id'] ? row("SELECT * FROM angajati WHERE id=?", [$d['angajat_id']]) : null;
    $p = $d['punct_id'] ? row("SELECT * FROM puncte WHERE id=?", [$d['punct_id']]) : null;
    $clm = $d['clm_id'] ? row("SELECT nume, prenume, functie FROM angajati WHERE id=?", [$d['clm_id']]) : null;
    $clmn = $clm ? h($clm['nume'] . ' ' . $clm['prenume']) : '<span class="muted">(punctul de lucru nu are CLM desemnat)</span>';
    $dom = $s['domeniu']; $tipdoc = $s['tip'];
    $cnp = role() === 'angajat' && $a && (int)$a['id'] !== (int)user()['angajat_id'] ? mask_cnp($a['cnp'] ?? '') : h($a['cnp'] ?? '');
    $head = '<div class="dochead"><div><b>' . h($f['denumire']) . '</b><br>CUI ' . h($f['cui']) . ' · ' . h($f['reg_com']) . '<br>' . h($f['adresa']) . ($p ? '<br>Punct de lucru: ' . h($p['nume'] . ' – ' . $p['adresa']) : '') . '</div><div class="right">Nr. ' . (int)$d['id'] . ' / ' . fmt_date($d['data_doc']) . '<br><span class="mark">B</span> <b>BENIAL CONSULTING</b></div></div>';
    $themes = []; foreach (preg_split('/\r?\n/', (string)($c['continut'] ?? '')) as $l) if (trim($l) !== '') { $x = array_map('trim', explode('|', $l)); $themes[] = [$x[0], $x[1] ?? '']; }
    $total = array_sum(array_map(fn($t) => (float)str_replace(',', '.', $t[1]), $themes));
    $sigs = fn($cols) => '<table class="doctbl sigs"><tr>' . implode('', array_map(fn($x) => '<th>' . $x[0] . '</th>', $cols)) . '</tr><tr>' . implode('', array_map(fn($x) => '<td>' . $x[1] . '<div class="sigline">' . $x[2] . '</div></td>', $cols)) . '</tr></table>';
    $ang = $a ? [['Nume și prenume', h($a['nume'] . ' ' . $a['prenume']), 'CNP', $cnp], ['Data nașterii', fmt_date($a['data_nasterii'] ?? null), 'Marca', h($a['marca'] ?? '')], ['Funcția / ocupația (COR)', h($a['functie'] . ($a['cor'] ? ' / ' . $a['cor'] : '')), 'Data angajării', fmt_date($a['data_angajarii'])], ['Locul de muncă', h(($a['loc_munca'] ?? '') ?: ($p['nume'] ?? '')), 'Timp de lucru', h($a['timp_lucru'] ?? '')]] : [];
    $angt = $ang ? '<table class="doctbl">' . implode('', array_map(fn($r) => '<tr><th>' . $r[0] . '</th><td>' . $r[1] . '</td><th>' . $r[2] . '</th><td>' . $r[3] . '</td></tr>', $ang)) . '</table>' : '';
    $h = $head;
    if ($tipdoc === 'tematica') {
        $tr = ''; foreach ($themes as $n => $t) $tr .= '<tr><td class="c">' . ($n + 1) . '</td><td>' . h($t[0]) . '</td><td class="c">' . h($t[1]) . '</td></tr>';
        $h .= '<div class="aprob">Aprobat,<br>Administrator: <b>' . h($f['administrator']) . '</b><div class="sigline">semnătura și ștampila</div></div>
        <h2 class="center">' . h(mb_strtoupper($c['sablon'] ?? $d['titlu'])) . '</h2><p class="center small">întocmită în conformitate cu ' . h(lege($dom)) . '</p>
        <table class="doctbl"><tr><th style="width:40px">Nr.</th><th>Tema</th><th style="width:90px">Durata (ore)</th></tr>' . $tr . '<tr><th></th><th class="r">Total</th><th class="c">' . rtrim(rtrim(number_format($total, 1, ',', ''), '0'), ',') . '</th></tr></table>
        <p class="small">Tematica se aplică lucrătorilor ' . ($p ? 'din punctul de lucru „' . h($p['nume']) . '”' : 'firmei') . ' și se actualizează ori de câte ori se modifică riscurile, echipamentele sau legislația. Durata instruirii nu poate fi mai mică decât cea prevăzută de normele în vigoare.</p>'
        . $sigs([['Întocmit (instructor)', h($d['instructor']), sig_img($d['semn_instr'])], ['Verificat – conducătorul locului de muncă', $clmn, sig_img($d['semn_clm'])]]);
    } elseif ($tipdoc === 'test') {
        $i = $c['instruire'] ?? []; $scor = $i['scor'] ?? null;
        $h .= '<h2 class="center">' . h(mb_strtoupper($c['sablon'] ?? $d['titlu'])) . '</h2>' . $angt . '
        <table class="doctbl"><tr><th>Instruirea / cursul</th><th>Data testării</th><th>Nr. întrebări</th><th>Punctaj obținut</th><th>Procent</th><th>Prag promovare</th><th>Rezultat</th></tr>
        <tr><td>' . h($i['titlu'] ?? '—') . '</td><td>' . h(!empty($i['data_test']) ? date('d.m.Y H:i', strtotime($i['data_test'])) : '—') . '</td><td class="c">' . h($i['nr'] ?? '—') . '</td><td class="c">' . h(isset($i['puncte']) ? $i['puncte'] . ' / ' . $i['max'] : '—') . '</td><td class="c">' . h($scor !== null ? $scor . '%' : '—') . '</td><td class="c">' . h(($i['prag'] ?? '') . '%') . '</td><td class="c"><b>' . ($scor !== null && $scor >= (int)($i['prag'] ?? 0) ? 'ADMIS' : 'RESPINS') . '</b></td></tr></table>'
        . (!empty($i['raspunsuri']) ? '<h3>Detaliu răspunsuri</h3><table class="doctbl"><tr><th>Nr.</th><th>Întrebarea</th><th>Răspuns dat</th><th>Corect</th></tr>' . implode('', array_map(fn($r, $n) => '<tr><td class="c">' . ($n + 1) . '</td><td>' . h($r[0]) . '</td><td class="c">' . h($r[1]) . '</td><td class="c">' . ($r[2] ? '✔' : '✘') . '</td></tr>', $i['raspunsuri'], array_keys($i['raspunsuri']))) . '</table>' : '')
        . '<p class="small">Testarea s-a desfășurat potrivit ' . h(lege($dom)) . '.</p>' . $sigs([['Lucrătorul testat', $a ? h($a['nume'] . ' ' . $a['prenume']) : '', sig_img($d['semn_ang'])], ['Evaluator / instructor', h($d['instructor']), sig_img($d['semn_instr'])], ['Conducătorul locului de muncă', $clmn, sig_img($d['semn_clm'])]]);
    } else {
        $i = $c['instruire'] ?? null; $pf = $dom === 'SU' ? 'SU' : 'SSM'; $cod = (string)$d['sablon_cod'];
        $parts = str_contains($cod, 'ANG') ? [[$pf . '-TEM-IIG', $dom === 'SU' ? 'Instructaj introductiv general' : 'Instruire introductiv-generală'], [$pf . '-TEM-ILM', $dom === 'SU' ? 'Instructaj specific locului de muncă' : 'Instruire la locul de muncă']] : (str_contains($cod, 'LP') ? [[null, 'Instructaj specific pentru lucrări periculoase']] : [[$pf . '-TEM-IP', str_contains($cod, 'SUP') ? 'Instruire periodică suplimentară' : ($dom === 'SU' ? 'Instructaj periodic' : 'Instruire periodică')]]);
        $rowsx = ''; $mats = '';
        foreach ($parts as $n => [$tc, $lbl]) {
            $tem = $tc ? sablon($tc, (int)$d['firma_id']) : null; $tl = []; $ore = 0;
            foreach (array_filter(preg_split('/\r?\n/', (string)($tem['continut'] ?? ''))) as $l) { $x = array_map('trim', explode('|', $l)); $tl[] = $x[0]; $ore += (float)str_replace(',', '.', $x[1] ?? '0'); }
            if ($i && count($parts) === 1) { $tl = array_merge([$i['titlu']], $tl); if ((int)$i['durata_min']) $ore = round((int)$i['durata_min'] / 60, 1); }
            $ref = 'Anexa ' . ($n + 1);
            $rowsx .= '<tr><td>' . h($lbl) . '</td><td>' . fmt_date($d['data_doc']) . '</td><td class="c">' . ($ore ? rtrim(rtrim(number_format($ore, 1, ',', ''), '0'), ',') : '') . '</td><td>' . ($tl ? 'conform tematicii – ' . $ref : '') . '</td><td>' . sig_img($d['semn_ang']) . '</td><td>' . h($d['instructor']) . '<br>' . sig_img($d['semn_instr']) . '</td><td>' . $clmn . '<br>' . sig_img($d['semn_clm']) . '</td></tr>';
            if ($tl) $mats .= '<h3>' . $ref . ' – materialul predat: ' . h($lbl) . '</h3><ol class="small">' . implode('', array_map(fn($t) => '<li>' . h($t) . '</li>', $tl)) . '</ol>';
        }
        $nota = trim((string)($c['continut'] ?? ''));
        $h .= '<h2 class="center">' . ($dom === 'SU' ? 'FIȘĂ INDIVIDUALĂ DE INSTRUIRE ÎN DOMENIUL SITUAȚIILOR DE URGENȚĂ' : 'FIȘĂ INDIVIDUALĂ DE INSTRUCTAJ PRIVIND SECURITATEA ȘI SĂNĂTATEA ÎN MUNCĂ') . '<br><small>' . h($c['instr'] ?? '') . '</small></h2>' . $angt
        . ($nota ? '<p class="small">' . nl2br(h($nota)) . '</p>' : '') . '
        <table class="doctbl fix"><tr><th>Felul instruirii</th><th>Data</th><th>Durata (ore)</th><th>Materialul predat</th><th>Semnătura lucrătorului instruit</th><th>Instruirea a fost efectuată de</th><th>Verificat – CLM</th></tr>' . $rowsx . '</table>'
        . (str_contains($d['sablon_cod'], 'ANG') ? '<p><b>Admis la lucru:</b> lucrătorul a fost verificat și și-a însușit cunoștințele necesare desfășurării activității în condiții de ' . ($dom === 'SU' ? 'apărare împotriva incendiilor' : 'securitate') . '.</p>' : '')
        . $mats . '<p class="small">Subsemnatul declar că am fost instruit, am înțeles și mă oblig să respect prevederile prezentate. Fișă întocmită potrivit ' . h(lege($dom)) . '.</p>';
    }
    $meta = []; if ($d['semn_ang_data']) $meta[] = 'semnat de lucrător la ' . date('d.m.Y H:i', strtotime($d['semn_ang_data'])); if ($d['semn_clm_data']) $meta[] = 'semnat de CLM la ' . date('d.m.Y H:i', strtotime($d['semn_clm_data'])); if ($d['semn_instr_data']) $meta[] = 'semnat de instructor la ' . date('d.m.Y H:i', strtotime($d['semn_instr_data']));
    return '<div class="sheet">' . $h . '<p class="small muted">Document generat electronic de platforma BENIAL CONSULTING · ID ' . (int)$d['id'] . ($meta ? ' · ' . implode(' · ', $meta) : '') . '</p></div>';
}
function doc_page(array $docs, string $title): void {
    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title><link rel="stylesheet" href="style.css"></head><body class="doc">
    <div class="noprint docbar"><button class="btn" onclick="window.print()">Salvează ca PDF / Tipărește</button> <a class="btn ghost" href="javascript:history.back()">← Înapoi</a> <span class="muted small">În fereastra de tipărire alegeți „Salvare ca PDF”, format A4.</span></div>';
    foreach ($docs as $d) echo render_doc($d);
    if (count($docs) === 1) { $d = $docs[0]; $fl = up_list(up_files('documente', (int)$d['id']), role() !== 'angajat', url('doc', ['id' => $d['id']]));
        echo '<div class="noprint sheet-extra"><h3>Anexe / fișiere atașate</h3>' . ($fl ?: '<p class="muted">Nicio anexă.</p>') . (role() !== 'angajat' ? '<form method="post" enctype="multipart/form-data" action="' . url('doc_upload', ['id' => $d['id']]) . '">' . csrf_field() . up_input() . '<button class="btn sm">Încarcă</button></form>' : '') . '</div><script src="app.js"></script>'; }
    echo '</body></html>';
}
function r_doc(): void {
    $d = row("SELECT * FROM documente WHERE id=?", [(int)$_GET['id']]);
    if (!$d || !doc_access($d)) { http_response_code(404); page('Negăsit', '<div class="card">Documentul nu există sau nu aveți acces.</div>'); return; }
    if (!$d['sablon_cod']) redirect(url('fisa', ['id' => $d['instruire_id']]));
    doc_page([$d], $d['titlu']);
}
function r_docs_print(): void {
    require_role('superadmin', 'inspector', 'client');
    $ids = array_map('intval', (array)($_POST['ids'] ?? [])); $D = [];
    foreach ($ids as $id) { $d = row("SELECT * FROM documente WHERE id=? AND sablon_cod IS NOT NULL", [$id]); if ($d && doc_access($d)) $D[] = $d; }
    if (!$D) { flash('Selectați cel puțin un document generat din șablon.', 'err'); redirect(url('documente')); }
    doc_page($D, count($D) . ' documente');
}
function r_documente(): void {
    require_role('superadmin', 'inspector', 'client');
    $sf = scope_firma() ?? ((int)($_GET['firma'] ?? 0) ?: null);
    $w = []; $args = [];
    if ($sf) { $w[] = 'd.firma_id=?'; $args[] = $sf; }
    if ($v = (int)($_GET['punct'] ?? 0)) { $w[] = 'd.punct_id=?'; $args[] = $v; }
    if ($v = $_GET['cod'] ?? '') { $w[] = 'd.sablon_cod=?'; $args[] = $v; }
    if ($v = $_GET['st'] ?? '') { $w[] = 'd.status=?'; $args[] = $v; }
    if ($v = trim($_GET['q'] ?? '')) { $w[] = '(LOWER(a.nume) LIKE ? OR LOWER(a.prenume) LIKE ?)'; $args[] = '%' . mb_strtolower($v) . '%'; $args[] = '%' . mb_strtolower($v) . '%'; }
    $D = rows("SELECT d.*, a.nume, a.prenume, f.denumire, p.nume AS punct FROM documente d LEFT JOIN angajati a ON a.id=d.angajat_id JOIN firme f ON f.id=d.firma_id LEFT JOIN puncte p ON p.id=d.punct_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY d.id DESC", $args);
    $t = ''; foreach ($D as $d) $t .= '<tr><td><input type="checkbox" name="ids[]" value="' . (int)$d['id'] . '" form="bulk"></td><td data-l="Data">' . h(fmt_date($d['data_doc'] ?: $d['created_at'])) . '</td><td data-l="Document">' . h($d['titlu']) . '</td><td data-l="Angajat">' . h($d['nume'] ? $d['nume'] . ' ' . $d['prenume'] : '—') . '</td><td data-l="Firmă / punct">' . h($d['denumire'] . ($d['punct'] ? ' / ' . $d['punct'] : '')) . '</td><td data-l="Stare">' . ($d['sablon_cod'] ? doc_status_label($d) : '<span class="b b-green">semnat</span>') . '</td><td class="act"><a class="btn sm" href="' . url('doc', ['id' => $d['id']]) . '">Deschide / PDF</a></td></tr>';
    if (!$t) $t = '<tr><td colspan="9" class="empty">Nu există documente. Folosiți „Generează documente”.</td></tr>';
    $sab = []; foreach (rows("SELECT cod, nume FROM sabloane WHERE firma_id IS NULL ORDER BY cod") as $s) $sab[$s['cod']] = $s['cod'] . ' – ' . $s['nume'];
    $flt = '<form class="tools" method="get"><input type="hidden" name="r" value="documente"><input name="q" placeholder="Angajat…" value="' . h($_GET['q'] ?? '') . '">' . (scope_firma() ? '' : select_html('firma', opts("SELECT id, denumire AS l FROM firme", false), $_GET['firma'] ?? '', 'Toate firmele'))
        . select_html('punct', opts("SELECT id, nume AS l FROM puncte" . ($sf ? ' WHERE firma_id=' . $sf : ''), false), $_GET['punct'] ?? '', 'Toate punctele') . select_html('cod', $sab, $_GET['cod'] ?? '', 'Toate tipurile')
        . select_html('st', ['de_semnat' => 'De semnat', 'semnat_angajat' => 'Așteaptă CLM', 'semnat_clm' => 'Așteaptă angajatul', 'finalizat' => 'Finalizate'], $_GET['st'] ?? '', 'Orice stare') . '<button class="btn ghost">Filtrează</button></form>';
    $bulk = '<form id="bulk" method="post" class="tools">' . csrf_field() . '<button class="btn" formaction="' . url('genereaza') . '" formmethod="get" form="none" onclick="location.href=\'' . url('genereaza') . '\';return false">+ Generează documente</button><button class="btn ghost" formaction="' . url('docs_print') . '" formtarget="_blank">Salvează selecția ca PDF</button>' . (is_staff() ? '<button class="btn ghost" formaction="' . url('semneaza_instr') . '">Semnează selecția (instructor)</button><button class="btn sm danger" formaction="' . url('docs_delete') . '" onclick="return confirm(\'Ștergeți documentele selectate nefinalizate?\')">Șterge selecția</button>' : '') . '<label class="chk small"><input type="checkbox" onclick="document.querySelectorAll(\'input[name=&quot;ids[]&quot;]\').forEach(c=>c.checked=this.checked)"> selectează tot</label></form>';
    page('Documente', '<p class="muted">Flux: documentul se generează → îl semnează <b>angajatul</b> și <b>conducătorul locului de muncă (CLM)</b> din portalul lor (individual sau în bloc) → devine <b>finalizat</b> și se salvează ca PDF.</p>' . $flt . $bulk . '<div class="card p0"><table class="tbl"><thead><tr><th></th><th>Data</th><th>Document</th><th>Angajat</th><th>Firmă / punct</th><th>Stare</th><th></th></tr></thead><tbody>' . $t . '</tbody></table></div><p class="muted">' . count($D) . ' documente</p>');
}
function r_docs_delete(): void {
    require_role('superadmin', 'inspector'); $n = 0;
    foreach (array_map('intval', (array)($_POST['ids'] ?? [])) as $id) { $k = q("DELETE FROM documente WHERE id=? AND sablon_cod IS NOT NULL AND (status IS NULL OR status<>'finalizat')", [$id])->rowCount(); if ($k) up_delete_for('documente', $id); $n += $k; }
    flash("$n documente șterse (cele finalizate se păstrează)."); redirect(url('documente'));
}
function r_genereaza(bool $post): void {
    require_role('superadmin', 'inspector', 'client');
    $firme = opts("SELECT id, denumire AS l FROM firme" . (scope_firma() ? ' WHERE id=' . scope_firma() : ''), false);
    if ($post) {
        $firma = scope_firma() ?? (int)($_POST['firma_id'] ?? 0); $punct = (int)($_POST['punct_id'] ?? 0) ?: null; $grupa = (int)($_POST['grupa_id'] ?? 0) ?: null;
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['data'] ?? '') ? $_POST['data'] : today(); $instr = trim($_POST['instructor'] ?? '') ?: (user()['name'] ?? '');
        $coduri = array_filter((array)($_POST['coduri'] ?? []), fn($c) => sablon($c));
        if (!isset($firme[$firma]) || !$coduri) { flash('Alegeți firma și cel puțin un tip de document.', 'err'); redirect(url('genereaza')); }
        $wa = 'firma_id=? AND activ=1'; $aa = [$firma]; if ($punct) { $wa .= ' AND punct_id=?'; $aa[] = $punct; } if ($grupa) { $wa .= ' AND grupa_id=?'; $aa[] = $grupa; }
        $ang = array_column(rows("SELECT id FROM angajati WHERE $wa ORDER BY nume", $aa), 'id');
        $pcts = $punct ? [$punct] : (array_column(rows("SELECT id FROM puncte WHERE firma_id=?", [$firma]), 'id') ?: [null]);
        $n = 0;
        foreach ($coduri as $cod) { $s = sablon($cod, $firma);
            if ($s['tip'] === 'tematica') foreach ($pcts as $p) { doc_generate($cod, $firma, $p ? (int)$p : null, null, $data, $instr); $n++; }
            else foreach ($ang as $a) { doc_generate($cod, $firma, null, (int)$a, $data, $instr); $n++; } }
        notify_sign_pending(array_merge($ang, array_column(rows("SELECT DISTINCT clm_id FROM documente WHERE firma_id=? AND clm_id IS NOT NULL AND semn_clm IS NULL", [$firma]), 'clm_id')));
        flash("Au fost generate $n documente. Ele apar în portalul angajaților și al CLM pentru semnare."); redirect(url('documente', scope_firma() ? [] : ['firma' => $firma]));
    }
    $ck = ''; foreach (rows("SELECT cod, nume, domeniu, tip FROM sabloane WHERE firma_id IS NULL ORDER BY domeniu, cod") as $s) $ck .= '<label class="chk"><input type="checkbox" name="coduri[]" value="' . h($s['cod']) . '"> <b>' . h($s['domeniu']) . '</b> · ' . h($s['nume']) . ' <span class="b b-gray">' . h($s['tip']) . '</span></label>';
    page('Generează documente', '<form method="post" class="card form">' . csrf_field() . '<div class="grid">' . (scope_firma() ? '' : '<label>Firmă <i class="req">*</i>' . select_html('firma_id', $firme, $_GET['firma'] ?? '', '— alegeți —', true) . '</label>')
        . '<label>Punct de lucru' . select_html('punct_id', opts("SELECT p.id, " . cc('f.denumire', 'p.nume') . " AS l FROM puncte p JOIN firme f ON f.id=p.firma_id" . fsc(), false), '', 'Toate punctele') . '</label>'
        . '<label>Grupă de instruire' . select_html('grupa_id', opts("SELECT g.id, " . cc('f.denumire', 'g.nume') . " AS l FROM grupe g JOIN firme f ON f.id=g.firma_id" . fsc(), false), '', 'Toți angajații') . '</label>'
        . '<label>Data documentelor<input type="date" name="data" value="' . today() . '"></label><label>Instructor (nume)<input name="instructor" value="' . h(user()['name']) . '"></label></div>
        <h3>Tipuri de documente</h3><p class="muted small">Tematicile se generează câte una pe punct de lucru; fișele și rezultatele testărilor câte una pentru fiecare angajat activ care corespunde filtrelor.</p><div class="checks">' . $ck . '</div><div class="savebar"><a class="btn ghost" href="' . url('documente') . '">Renunță</a><button class="btn">Generează</button></div></form>');
}
/* semnare în bloc: angajat / CLM în portal, instructor de către staff */
function sig_ok(string $s): bool { return str_starts_with($s, 'data:image/png;base64,') && strlen($s) > 1500 && strlen($s) < 600000; }
function sig_pad(string $action, string $hidden = '', string $btn = 'Semnează'): string {
    return '<canvas id="pad" class="pad-canvas"></canvas><div class="tools"><button type="button" class="btn ghost" id="clear">Șterge semnătura</button></div><form method="post" action="' . h($action) . '" id="signform">' . csrf_field() . $hidden . '<input type="hidden" name="sig" id="sig"><label class="chk"><input type="checkbox" required name="decl" value="1"> Confirm că am citit documentele și semnez olograf în chenar.</label><div class="savebar"><button class="btn">' . h($btn) . '</button></div></form>';
}
function r_semneaza(bool $post): void {
    require_role('angajat'); $T = docs_to_sign(); $me = (int)user()['angajat_id'];
    if ($post) {
        $sig = $_POST['sig'] ?? ''; $ids = array_map('intval', explode(',', $_POST['ids'] ?? ''));
        if (!sig_ok($sig) || empty($_POST['decl'])) { flash('Desenați semnătura și bifați confirmarea.', 'err'); redirect(url('semneaza')); }
        $n = 0; foreach ($ids as $id) { if (!isset($T[$id])) continue; $d = $T[$id];
            if ((int)$d['angajat_id'] === $me && !$d['semn_ang'] && doc_needs_ang($d)) update('documente', $id, ['semn_ang' => $sig, 'semn_ang_data' => date('Y-m-d H:i')]);
            if ((int)$d['clm_id'] === $me && !$d['semn_clm']) update('documente', $id, ['semn_clm' => $sig, 'semn_clm_data' => date('Y-m-d H:i')]);
            doc_refresh_status($id); $n++; $notif[] = (int)$d['clm_id']; }
        notify_sign_pending($notif ?? []);
        flash("Ați semnat $n documente."); redirect(url('portal'));
    }
    $sel = array_map('intval', (array)($_GET['ids'] ?? array_keys($T))); $sel = array_values(array_intersect($sel, array_keys($T)));
    if (!$sel) { page('Documente de semnat', '<div class="card">Nu aveți documente de semnat. 👍</div><a class="btn ghost" href="' . url('portal') . '">← Înapoi</a>'); return; }
    $li = ''; foreach ($sel as $id) { $d = $T[$id]; $an = $d['angajat_id'] ? row("SELECT nume, prenume FROM angajati WHERE id=?", [$d['angajat_id']]) : null; $li .= '<li><a href="' . url('doc', ['id' => $id]) . '" target="_blank">' . h($d['titlu']) . '</a> ' . ($an ? '– ' . h($an['nume'] . ' ' . $an['prenume']) : '') . ' <span class="b b-gray">ca ' . $d['ca'] . '</span></li>'; }
    page('Semnează documente (' . count($sel) . ')', '<div class="card"><p>Deschideți și citiți documentele, apoi semnați o singură dată pentru toate:</p><ul>' . $li . '</ul>' . sig_pad(url('semneaza'), '<input type="hidden" name="ids" value="' . implode(',', $sel) . '">', 'Semnează ' . count($sel) . ' documente') . '</div>');
}
function r_semneaza_instr(bool $post): void {
    require_role('superadmin', 'inspector');
    if (isset($_POST['sig'])) {
        $sig = $_POST['sig']; if (!sig_ok($sig) || empty($_POST['decl'])) { flash('Desenați semnătura.', 'err'); redirect(url('documente')); }
        $n = 0; foreach (array_map('intval', explode(',', $_POST['idlist'] ?? '')) as $id) $n += q("UPDATE documente SET semn_instr=?, semn_instr_data=? WHERE id=? AND sablon_cod IS NOT NULL", [$sig, date('Y-m-d H:i'), $id])->rowCount();
        flash("Semnătura instructorului a fost aplicată pe $n documente."); redirect(url('documente'));
    }
    $ids = array_map('intval', (array)($_POST['ids'] ?? [])); if (!$ids) { flash('Selectați documentele.', 'err'); redirect(url('documente')); }
    page('Semnează ca instructor (' . count($ids) . ' documente)', '<div class="card">' . sig_pad(url('semneaza_instr'), '<input type="hidden" name="idlist" value="' . implode(',', $ids) . '">', 'Aplică semnătura') . '</div>');
}

/* ---------- mesaje, chat, tichete ---------- */
function r_mesaje(bool $post): void {
    if (!user()) redirect(url('login')); $me = me_k(); $C = contacts();
    if ($post) { $to = $_POST['to'] ?? ''; $sub = trim($_POST['subiect'] ?? ''); $b = trim($_POST['corp'] ?? '');
        if (!isset($C[$to]) || $b === '') { flash('Alegeți destinatarul și scrieți mesajul.', 'err'); redirect(url('mesaje', ['nou' => 1])); }
        $mid = insert('mesaje', ['from_k' => $me, 'to_k' => $to, 'subiect' => $sub ?: '(fără subiect)', 'corp' => $b, 'citit' => 0, 'created_at' => date('Y-m-d H:i')]); up_flash(up_save('mesaje', $mid, my_firma())); notify_message($mid); flash('Mesaj trimis.'); redirect(url('mesaje', ['box' => 'trimise'])); }
    if ($id = (int)($_GET['id'] ?? 0)) { $m = row("SELECT * FROM mesaje WHERE id=? AND (to_k=? OR from_k=?)", [$id, $me, $me]); if (!$m) redirect(url('mesaje'));
        if ($m['to_k'] === $me) update('mesaje', $id, ['citit' => 1]);
        page(h($m['subiect']), '<div class="card"><p class="muted">De la <b>' . h(k_name($m['from_k'])) . '</b> către <b>' . h(k_name($m['to_k'])) . '</b> · ' . h($m['created_at']) . '</p><div class="txt">' . nl2br(h($m['corp'])) . '</div>' . up_list(up_files('mesaje', $id)) . '</div><div class="tools"><a class="btn" href="' . url('mesaje', ['nou' => 1, 'to' => $m['from_k'] === $me ? $m['to_k'] : $m['from_k'], 're' => 'Re: ' . $m['subiect']]) . '">Răspunde</a><a class="btn ghost" href="' . url('mesaje') . '">← Mesaje</a></div>'); return; }
    if (!empty($_GET['nou'])) { page('Mesaj nou', '<form method="post" class="card form" enctype="multipart/form-data">' . csrf_field() . '<div class="grid"><label>Către <i class="req">*</i>' . select_html('to', $C, $_GET['to'] ?? '', '— alegeți —', true) . '</label><label>Subiect<input name="subiect" value="' . h($_GET['re'] ?? '') . '"></label><label class="full">Mesaj <i class="req">*</i><textarea name="corp" rows="6" required></textarea></label></div>' . up_input() . '<div class="savebar"><a class="btn ghost" href="' . url('mesaje') . '">Renunță</a><button class="btn">Trimite</button></div></form>'); return; }
    $sent = ($_GET['box'] ?? '') === 'trimise';
    $M = rows("SELECT * FROM mesaje WHERE " . ($sent ? 'from_k' : 'to_k') . "=? ORDER BY id DESC", [$me]); $t = '';
    foreach ($M as $m) $t .= '<tr' . (!$m['citit'] && !$sent ? ' class="unread"' : '') . '><td data-l="Data">' . h($m['created_at']) . '</td><td data-l="' . ($sent ? 'Către' : 'De la') . '">' . h(k_name($sent ? $m['to_k'] : $m['from_k'])) . '</td><td data-l="Subiect"><a href="' . url('mesaje', ['id' => $m['id']]) . '">' . h($m['subiect']) . '</a>' . (!$m['citit'] && !$sent ? ' <span class="b b-red">nou</span>' : '') . '</td></tr>';
    page('Mesaje', '<div class="tools"><a class="btn" href="' . url('mesaje', ['nou' => 1]) . '">✉️ Mesaj nou</a><a class="btn ' . ($sent ? 'ghost' : '') . '" href="' . url('mesaje') . '">Primite</a><a class="btn ' . ($sent ? '' : 'ghost') . '" href="' . url('mesaje', ['box' => 'trimise']) . '">Trimise</a></div><div class="card p0"><table class="tbl"><thead><tr><th>Data</th><th>' . ($sent ? 'Către' : 'De la') . '</th><th>Subiect</th></tr></thead><tbody>' . ($t ?: '<tr><td colspan="3" class="empty">Niciun mesaj.</td></tr>') . '</tbody></table></div>');
}
function chat_rooms(): array {
    $r = []; if (is_staff()) { $r['staff'] = 'Echipa BENIAL (intern)'; foreach (rows("SELECT id, denumire FROM firme ORDER BY denumire") as $f) $r['f' . $f['id']] = $f['denumire']; }
    elseif ($f = my_firma()) $r['f' . $f] = (string)val("SELECT denumire FROM firme WHERE id=?", [$f]);
    return $r;
}
function r_chat(bool $post): void {
    if (!user()) redirect(url('login')); $R = chat_rooms(); $cam = $_GET['c'] ?? array_key_first($R) ?? '';
    if (!isset($R[$cam])) { page('Chat', '<div class="card">Nu aveți acces la nicio cameră de chat.</div>'); return; }
    if ($post) { $t = trim($_POST['text'] ?? ''); if ($t !== '') insert('chat', ['camera' => $cam, 'autor_k' => me_k(), 'autor' => user()['name'], 'text' => mb_substr($t, 0, 2000), 'created_at' => date('Y-m-d H:i')]);
        if (!empty($_POST['ajax'])) exit('ok'); redirect(url('chat', ['c' => $cam])); }
    $M = array_reverse(rows("SELECT * FROM chat WHERE camera=? ORDER BY id DESC LIMIT 100", [$cam]));
    if (!empty($_GET['feed'])) { header('Content-Type: application/json'); echo json_encode(array_map(fn($m) => ['id' => (int)$m['id'], 'a' => $m['autor'], 't' => $m['text'], 'd' => $m['created_at'], 'me' => $m['autor_k'] === me_k()], $M)); exit; }
    $tabs = ''; foreach ($R as $k => $l) $tabs .= '<a class="btn sm ' . ($k === $cam ? '' : 'ghost') . '" href="' . url('chat', ['c' => $k]) . '">' . h($l) . '</a>';
    page('Chat intern', '<div class="tools">' . $tabs . '</div><div class="card chatbox" id="chat" data-feed="' . h(url('chat', ['c' => $cam, 'feed' => 1])) . '"></div><form method="post" class="tools" id="chatform" action="' . h(url('chat', ['c' => $cam])) . '">' . csrf_field() . '<input name="text" placeholder="Scrieți un mesaj…" autocomplete="off" required style="flex:1"><button class="btn">Trimite</button></form>');
}
function r_tichete(bool $post): void {
    if (!user()) redirect(url('login')); $admin = role() === 'superadmin'; $me = me_k();
    if ($id = (int)($_GET['id'] ?? 0)) {
        $t = row("SELECT * FROM tichete WHERE id=?", [$id]); if (!$t || (!$admin && $t['autor_k'] !== $me)) redirect(url('tichete'));
        if ($post) { if (!empty($_POST['status']) && $admin) update('tichete', $id, ['status' => $_POST['status'] === 'rezolvat' ? 'rezolvat' : 'deschis']);
            if (($x = trim($_POST['text'] ?? '')) !== '') { $tm = insert('tichete_msg', ['tichet_id' => $id, 'autor_k' => $me, 'autor' => user()['name'], 'text' => $x, 'created_at' => date('Y-m-d H:i')]); if (!$admin) update('tichete', $id, ['status' => 'deschis']); up_flash(up_save('tichete_msg', $tm, $t['firma_id'] ? (int)$t['firma_id'] : null)); notify_ticket($id, $tm, false); }
            redirect(url('tichete', ['id' => $id])); }
        $h = ''; foreach (rows("SELECT * FROM tichete_msg WHERE tichet_id=? ORDER BY id", [$id]) as $m) $h .= '<div class="card"><p class="muted small"><b>' . h($m['autor']) . '</b> · ' . h($m['created_at']) . '</p>' . nl2br(h($m['text'])) . up_list(up_files('tichete_msg', (int)$m['id'])) . '</div>';
        page('Tichet #' . $id . ': ' . $t['subiect'], '<p>' . ($t['status'] === 'rezolvat' ? '<span class="b b-green">rezolvat</span>' : '<span class="b b-orange">deschis</span>') . ' · prioritate ' . h($t['prioritate']) . ' · ' . h($t['autor']) . '</p>' . $h . '<form method="post" class="card form" enctype="multipart/form-data">' . csrf_field() . '<label class="full">Răspuns<textarea name="text" rows="4"></textarea></label>' . up_input() . ($admin ? '<label>Stare' . select_html('status', ['deschis' => 'Deschis', 'rezolvat' => 'Rezolvat'], $t['status']) . '</label>' : '') . '<div class="savebar"><a class="btn ghost" href="' . url('tichete') . '">← Tichete</a><button class="btn">Trimite</button></div></form>'); return;
    }
    if ($post) { $s = trim($_POST['subiect'] ?? ''); $x = trim($_POST['text'] ?? '');
        if ($s === '' || $x === '') { flash('Completați subiectul și descrierea.', 'err'); redirect(url('tichete')); }
        $tid = insert('tichete', ['autor_k' => $me, 'autor' => user()['name'], 'firma_id' => my_firma(), 'subiect' => $s, 'prioritate' => in_array($_POST['prioritate'] ?? '', ['mică', 'normală', 'urgentă']) ? $_POST['prioritate'] : 'normală', 'status' => 'deschis', 'created_at' => date('Y-m-d H:i')]);
        $tm = insert('tichete_msg', ['tichet_id' => $tid, 'autor_k' => $me, 'autor' => user()['name'], 'text' => $x, 'created_at' => date('Y-m-d H:i')]); up_flash(up_save('tichete_msg', $tm, my_firma())); notify_ticket($tid, $tm, true); flash('Tichet deschis.'); redirect(url('tichete', ['id' => $tid])); }
    $T = $admin ? rows("SELECT * FROM tichete ORDER BY status, id DESC") : rows("SELECT * FROM tichete WHERE autor_k=? ORDER BY id DESC", [$me]); $t = '';
    foreach ($T as $x) $t .= '<tr><td data-l="#">' . (int)$x['id'] . '</td><td data-l="Subiect"><a href="' . url('tichete', ['id' => $x['id']]) . '">' . h($x['subiect']) . '</a></td><td data-l="Autor">' . h($x['autor']) . '</td><td data-l="Prioritate">' . h($x['prioritate']) . '</td><td data-l="Stare">' . ($x['status'] === 'rezolvat' ? '<span class="b b-green">rezolvat</span>' : '<span class="b b-orange">deschis</span>') . '</td><td data-l="Data">' . h($x['created_at']) . '</td></tr>';
    page('Tichete suport', '<div class="card p0"><table class="tbl"><thead><tr><th>#</th><th>Subiect</th><th>Autor</th><th>Prioritate</th><th>Stare</th><th>Data</th></tr></thead><tbody>' . ($t ?: '<tr><td colspan="6" class="empty">Niciun tichet.</td></tr>') . '</tbody></table></div>
    <form method="post" class="card form" enctype="multipart/form-data">' . csrf_field() . '<h2>Tichet nou</h2><div class="grid"><label>Subiect <i class="req">*</i><input name="subiect" required></label><label>Prioritate' . select_html('prioritate', ['mică' => 'mică', 'normală' => 'normală', 'urgentă' => 'urgentă'], 'normală') . '</label><label class="full">Descriere <i class="req">*</i><textarea name="text" rows="4" required></textarea></label></div>' . up_input() . '<div class="savebar"><button class="btn">Deschide tichet</button></div></form>');
}

/* ---------- rapoarte suplimentare ---------- */
function luni_ro(): array { return [1 => 'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie']; }
function report_ext(string $tip, ?int $sf, ?int $pct): ?array {
    $wa = []; if ($sf) $wa[] = 'a.firma_id=' . $sf; if ($pct) $wa[] = 'a.punct_id=' . $pct; $W = $wa ? ' AND ' . implode(' AND ', $wa) : '';
    $we = []; if ($sf) $we[] = 'e.firma_id=' . $sf; if ($pct) $we[] = 'e.punct_id=' . $pct; $WE = $we ? ' AND ' . implode(' AND ', $we) : '';
    $fs = $sf ? ' AND f.id=' . $sf : '';
    switch ($tip) {
        case 'medicina': return [['Firmă', 'Angajat', 'Funcție', 'Ultimul examen', 'Aviz', 'Valabil până la', 'Zile rămase'], array_map(fn($r) => [$r['denumire'], $r['nume'] . ' ' . $r['prenume'], $r['functie'], fmt_date($r['medical_ultim']), $r['medical_aviz'], fmt_date($r['medical_expira']), days_until($r['medical_expira']) ?? '—'], rows("SELECT a.*, f.denumire FROM angajati a JOIN firme f ON f.id=a.firma_id WHERE a.activ=1$W ORDER BY a.medical_expira"))];
        case 'echipamente': case 'stingere': return [['Firmă', 'Punct', 'Denumire', 'Tip', 'Serie', 'Ultima verificare', 'Periodicitate (luni)', 'Următoarea verificare', 'Zile rămase'], array_map(fn($r) => [$r['denumire_f'], $r['punct'], $r['denumire'], $r['tip'], $r['serie'], fmt_date($r['data_verificare']), $r['periodicitate_luni'], fmt_date($r['urmatoarea']), days_until($r['urmatoarea']) ?? '—'], rows("SELECT e.*, f.denumire AS denumire_f, p.nume AS punct FROM echipamente e JOIN firme f ON f.id=e.firma_id LEFT JOIN puncte p ON p.id=e.punct_id WHERE e.categorie=?$WE ORDER BY e.urmatoarea", [$tip === 'stingere' ? 'stingere' : 'echipament']))];
        case 'meserii': return [['Firmă', 'Angajat', 'Funcție', 'Meserie / autorizare', 'Valabilă până la'], array_map(fn($r) => [$r['denumire'], $r['nume'] . ' ' . $r['prenume'], $r['functie'], $r['meserie_aut'], fmt_date($r['aut_expira'])], rows("SELECT a.*, f.denumire FROM angajati a JOIN firme f ON f.id=a.firma_id WHERE a.activ=1 AND a.meserie_aut IS NOT NULL AND a.meserie_aut<>''$W ORDER BY f.denumire, a.nume"))];
        case 'echipe': return [['Firmă', 'Punct', 'Angajat', 'Funcție', 'Echipă', 'CLM', 'Responsabil SSM/SU'], array_map(fn($r) => [$r['denumire'], $r['punct'], $r['nume'] . ' ' . $r['prenume'], $r['functie'], $r['echipa'], $r['este_clm'] ? 'Da' : '', $r['resp_ssm'] ? 'Da' : ''], rows("SELECT a.*, f.denumire, p.nume AS punct FROM angajati a JOIN firme f ON f.id=a.firma_id LEFT JOIN puncte p ON p.id=a.punct_id WHERE a.activ=1 AND ((a.echipa IS NOT NULL AND a.echipa<>'' AND a.echipa<>'Non-intervenție') OR a.este_clm=1 OR a.resp_ssm=1)$W ORDER BY f.denumire, a.echipa"))];
        case 'istoric': return [['Firmă', 'Angajat', 'Curs', 'Tip', 'Scor', 'Data semnării', 'Următoarea'], array_map(fn($r) => [$r['denumire'], $r['nume'] . ' ' . $r['prenume'], $r['titlu'], $r['tip'], $r['scor'] . '%', fmt_date($r['data_semnare']), fmt_date($r['urmatoarea'])], rows("SELECT i.*, a.nume, a.prenume, f.denumire, c.titlu, c.tip FROM instruiri i JOIN angajati a ON a.id=i.angajat_id JOIN firme f ON f.id=a.firma_id JOIN cursuri c ON c.id=i.curs_id WHERE i.status='semnat'$W ORDER BY i.data_semnare DESC"))];
        case 'grafic': $L = luni_ro(); $y = (int)date('Y'); $out = [];
            foreach (rows("SELECT a.nume, a.prenume, f.denumire, i.urmatoarea, c.titlu FROM instruiri i JOIN angajati a ON a.id=i.angajat_id JOIN firme f ON f.id=a.firma_id JOIN cursuri c ON c.id=i.curs_id WHERE i.status='semnat'$W ORDER BY a.nume") as $r) { $m = $r['urmatoarea'] ? (int)date('n', strtotime($r['urmatoarea'])) : 0; $yy = $r['urmatoarea'] ? (int)date('Y', strtotime($r['urmatoarea'])) : 0;
                $line = [$r['denumire'], $r['nume'] . ' ' . $r['prenume'], $r['titlu']]; foreach ($L as $k => $_) $line[] = ($yy === $y && $m === $k) ? '●' : ''; $out[] = $line; }
            return [array_merge(['Firmă', 'Angajat', 'Curs'], array_map(fn($x) => mb_substr($x, 0, 3), array_values($L))), $out];
        case 'plan_anual': case 'plan_lunar': $L = luni_ro(); $luna = (int)($_GET['luna'] ?? date('n')); $out = [];
            foreach (rows("SELECT pr.*, f.denumire, g.nume AS grupa, c.titlu, (SELECT COUNT(*) FROM angajati a WHERE a.grupa_id=pr.grupa_id AND a.activ=1) AS nr FROM program pr JOIN firme f ON f.id=pr.firma_id JOIN grupe g ON g.id=pr.grupa_id JOIN cursuri c ON c.id=pr.curs_id WHERE 1=1$fs ORDER BY f.denumire") as $r) {
                $ml = array_filter(array_map('intval', preg_split('/[,; ]+/', (string)$r['luni_instruire'])));
                if (!$ml) { $st = (int)date('n', strtotime($r['data_start'])); $per = max(1, (int)$r['periodicitate_luni']); for ($m = $st; $m <= 12; $m += $per) $ml[] = $m; }
                if ($tip === 'plan_lunar') { if (in_array($luna, $ml)) $out[] = [$r['denumire'], $r['grupa'], $r['titlu'], $r['tip_instruire'], $L[$luna], $r['ora'], $r['durata_ore'], $r['nr'], $r['instructor']]; }
                else { $line = [$r['denumire'], $r['grupa'], $r['titlu'], $r['tip_instruire']]; foreach ($L as $k => $_) $line[] = in_array($k, $ml) ? '●' : ''; $line[] = $r['luna_test_anual'] ? $L[(int)$r['luna_test_anual']] : ''; $out[] = $line; } }
            return $tip === 'plan_lunar' ? [['Firmă', 'Grupă', 'Curs', 'Tip instruire', 'Luna', 'Ora', 'Durata (ore)', 'Nr. angajați', 'Instructor'], $out] : [array_merge(['Firmă', 'Grupă', 'Curs', 'Tip'], array_map(fn($x) => mb_substr($x, 0, 3), array_values($L)), ['Test anual']), $out];
        case 'audit': return [['Firmă', 'Punct', 'Data', 'Domeniu', 'Auditor', 'Concluzii', 'Neconformități deschise'], array_map(fn($r) => [$r['denumire'], $r['punct'], fmt_date($r['data']), $r['domeniu'], $r['auditor'], $r['concluzii'], $r['nc']], rows("SELECT au.*, f.denumire, p.nume AS punct, (SELECT COUNT(*) FROM neconformitati n WHERE n.audit_id=au.id AND n.status<>'rezolvată') AS nc FROM audituri au JOIN firme f ON f.id=au.firma_id LEFT JOIN puncte p ON p.id=au.punct_id WHERE 1=1" . ($sf ? ' AND au.firma_id=' . $sf : '') . ($pct ? ' AND au.punct_id=' . $pct : '') . " ORDER BY au.data DESC"))];
        case 'neconf': return [['Firmă', 'Audit', 'Neconformitate', 'Măsură', 'Responsabil', 'Termen', 'Stare'], array_map(fn($r) => [$r['denumire'], fmt_date($r['data']) . ' ' . $r['domeniu'], $r['descriere'], $r['masura'], $r['responsabil'], fmt_date($r['termen']), $r['status']], rows("SELECT n.*, f.denumire, au.data, au.domeniu FROM neconformitati n JOIN firme f ON f.id=n.firma_id LEFT JOIN audituri au ON au.id=n.audit_id WHERE 1=1" . ($sf ? ' AND n.firma_id=' . $sf : '') . " ORDER BY n.termen"))];
    }
    return null;
}

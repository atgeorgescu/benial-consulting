<?php
function migrate(): void {
    $id = match (drv()) { 'pgsql' => 'SERIAL PRIMARY KEY', 'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY', default => 'INTEGER PRIMARY KEY AUTOINCREMENT' };
    $T = drv() === 'mysql' ? 'LONGTEXT' : 'TEXT';
    $tables = [
        "users (id $id, email VARCHAR(190) UNIQUE, name VARCHAR(190), password_hash VARCHAR(255), role VARCHAR(20), firma_id INT NULL, created_at VARCHAR(20))",
        "firme (id $id, denumire VARCHAR(190), cui VARCHAR(20), reg_com VARCHAR(40), caen VARCHAR(10), adresa VARCHAR(255), administrator VARCHAR(190), telefon VARCHAR(40), email VARCHAR(190), contract_nr VARCHAR(40), contract_data VARCHAR(10), servicii VARCHAR(190), note $T)",
        "puncte (id $id, firma_id INT, nume VARCHAR(190), adresa VARCHAR(255), conducator VARCHAR(190), riscuri $T)",
        "grupe (id $id, firma_id INT, nume VARCHAR(190), tip VARCHAR(10), periodicitate_luni INT, descriere $T)",
        "angajati (id $id, firma_id INT, punct_id INT NULL, grupa_id INT NULL, nume VARCHAR(100), prenume VARCHAR(100), cnp VARCHAR(13), functie VARCHAR(150), cor VARCHAR(10), data_angajarii VARCHAR(10), telefon VARCHAR(40), email VARCHAR(190), medical_expira VARCHAR(10), cod_acces VARCHAR(20), activ INT DEFAULT 1)",
        "cursuri (id $id, titlu VARCHAR(190), tip VARCHAR(40), durata_min INT, prag_promovare INT DEFAULT 70, descriere $T)",
        "lectii (id $id, curs_id INT, ordine INT, titlu VARCHAR(190), continut $T, video_url VARCHAR(255))",
        "intrebari (id $id, curs_id INT, text $T, opt_a VARCHAR(255), opt_b VARCHAR(255), opt_c VARCHAR(255), corect VARCHAR(1))",
        "program (id $id, firma_id INT, grupa_id INT, curs_id INT, periodicitate_luni INT, data_start VARCHAR(10), instructor VARCHAR(190))",
        "instruiri (id $id, angajat_id INT, curs_id INT, program_id INT NULL, status VARCHAR(20), scor INT NULL, data_test VARCHAR(20), semnatura $T, data_semnare VARCHAR(20), urmatoarea VARCHAR(10), created_at VARCHAR(20))",
        "documente (id $id, firma_id INT, angajat_id INT, instruire_id INT, tip VARCHAR(60), titlu VARCHAR(255), created_at VARCHAR(20))",
    ];
    foreach ($tables as $t) db()->exec("CREATE TABLE IF NOT EXISTS $t");
}
function fake_cnp(string $base12): string {
    $k = '279146358279'; $s = 0;
    for ($i = 0; $i < 12; $i++) $s += (int)$base12[$i] * (int)$k[$i];
    $r = $s % 11; return $base12 . ($r == 10 ? 1 : $r);
}
function seed(): void {
    $now = date('Y-m-d H:i');
    $pw = getenv('ADMIN_PASSWORD') ?: 'admin-local';
    $adm = row("SELECT id FROM users WHERE email='admin'");
    if (!$adm) insert('users', ['email' => 'admin', 'name' => 'Super Admin', 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => 'superadmin', 'created_at' => $now]);
    elseif (getenv('ADMIN_PASSWORD')) q("UPDATE users SET password_hash=? WHERE email='admin'", [password_hash($pw, PASSWORD_DEFAULT)]);
    if (getenv('NO_DEMO_SEED') || val("SELECT COUNT(*) FROM firme")) return;
    $f = insert('firme', ['denumire' => 'DEMO CONSTRUCT SRL (date fictive)', 'cui' => 'RO00000000', 'reg_com' => 'J29/0000/2026', 'caen' => '4120', 'adresa' => 'Str. Exemplului nr. 1, Ploiești, Prahova', 'administrator' => 'Ion Exemplu', 'telefon' => '0700 000 000', 'email' => 'demo@example.com', 'contract_nr' => 'DEMO-1', 'contract_data' => today(), 'servicii' => 'SSM, PSI', 'note' => 'Firmă demonstrativă, date fictive.']);
    $p = insert('puncte', ['firma_id' => $f, 'nume' => 'Sediu social', 'adresa' => 'Str. Exemplului nr. 1, Ploiești', 'conducator' => 'Ion Exemplu', 'riscuri' => 'Lucru la înălțime, manipulare manuală']);
    $g = insert('grupe', ['firma_id' => $f, 'nume' => 'Muncitori construcții', 'tip' => 'SSM', 'periodicitate_luni' => 6, 'descriere' => 'Instruire periodică la 6 luni']);
    $g2 = insert('grupe', ['firma_id' => $f, 'nume' => 'Personal administrativ', 'tip' => 'SSM', 'periodicitate_luni' => 12, 'descriere' => 'Instruire periodică anuală']);
    $c = insert('cursuri', ['titlu' => 'Instruire periodică SSM – noțiuni de bază', 'tip' => 'Periodică', 'durata_min' => 30, 'prag_promovare' => 70, 'descriere' => 'Curs demonstrativ: obligații, EIP, prim ajutor.']);
    $L = [
        ['Obligațiile lucrătorului', "Fiecare lucrător trebuie să își desfășoare activitatea astfel încât să nu se expună pe sine sau pe alții la pericole.\n\n• Utilizează corect echipamentele de muncă.\n• Poartă echipamentul individual de protecție (EIP).\n• Comunică imediat orice pericol conducătorului locului de muncă.", ''],
        ['Echipamentul individual de protecție', "Casca, încălțămintea de protecție, mănușile și vesta reflectorizantă se poartă pe toată durata lucrului în zonele marcate.\nEIP-ul deteriorat se predă și se înlocuiește.", 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
        ['Prim ajutor și urgențe', "În caz de accident: asigurați zona, sunați la 112, acordați primul ajutor doar dacă sunteți instruit, anunțați conducătorul locului de muncă.", ''],
    ];
    foreach ($L as $i => [$t, $ct, $v]) insert('lectii', ['curs_id' => $c, 'ordine' => $i + 1, 'titlu' => $t, 'continut' => $ct, 'video_url' => $v]);
    $Q = [
        ['Ce faceți dacă observați un pericol iminent?', 'Continui lucrul', 'Anunț imediat conducătorul locului de muncă', 'Aștept pauza', 'b'],
        ['Când se poartă casca de protecție?', 'Pe toată durata lucrului în zonele marcate', 'Doar dimineața', 'Doar când vine inspectorul', 'a'],
        ['Ce număr sunați în caz de urgență?', '911', '112', '0800', 'b'],
        ['Ce faceți cu EIP-ul deteriorat?', 'Îl reparați singur', 'Îl folosiți în continuare', 'Îl predați și cereți înlocuirea', 'c'],
        ['Cine trebuie să respecte regulile SSM?', 'Toți lucrătorii', 'Doar șefii', 'Doar angajații noi', 'a'],
    ];
    foreach ($Q as [$t, $a, $b, $cc, $k]) insert('intrebari', ['curs_id' => $c, 'text' => $t, 'opt_a' => $a, 'opt_b' => $b, 'opt_c' => $cc, 'corect' => $k]);
    $pr = insert('program', ['firma_id' => $f, 'grupa_id' => $g, 'curs_id' => $c, 'periodicitate_luni' => 6, 'data_start' => today(), 'instructor' => 'Inspector SSM Demo']);
    insert('program', ['firma_id' => $f, 'grupa_id' => $g2, 'curs_id' => $c, 'periodicitate_luni' => 12, 'data_start' => today(), 'instructor' => 'Inspector SSM Demo']);
    $E = [
        ['Popescu', 'Andrei', '1900101', 'Zidar', '711204', $g, 5, 'DEMO2026'],
        ['Ionescu', 'Maria', '2920315', 'Economist', '263102', $g2, 12, gen_code()],
        ['Georgescu', 'Vlad', '1950720', 'Dulgher', '711501', $g, 25, gen_code()],
        ['Dumitrescu', 'Elena', '2880909', 'Contabil', '331101', $g2, -3, gen_code()],
        ['Marin', 'Cosmin', '1991212', 'Fierar betonist', '711402', $g, 200, gen_code()],
    ];
    foreach ($E as $i => [$n, $pn, $d, $fn, $cor, $gr, $med, $cod]) {
        $a = insert('angajati', ['firma_id' => $f, 'punct_id' => $p, 'grupa_id' => $gr, 'nume' => $n, 'prenume' => $pn, 'cnp' => fake_cnp($d . '29' . sprintf('%03d', 900 + $i)), 'functie' => $fn, 'cor' => $cor, 'data_angajarii' => '2024-03-01', 'telefon' => '0700 000 00' . $i, 'email' => '', 'medical_expira' => date('Y-m-d', strtotime("+$med days")), 'cod_acces' => $cod, 'activ' => 1]);
        if ($i == 4) insert('instruiri', ['angajat_id' => $a, 'curs_id' => $c, 'program_id' => $pr, 'status' => 'semnat', 'scor' => 100, 'data_test' => date('Y-m-d H:i', strtotime('-170 days')), 'data_semnare' => date('Y-m-d H:i', strtotime('-170 days')), 'urmatoarea' => date('Y-m-d', strtotime('+10 days')), 'created_at' => $now]);
    }
    foreach (rows("SELECT id FROM program") as $pp) allocate_program((int)$pp['id']);
}
/** creează instruiri „alocat” pentru angajații grupei care nu au una deschisă */
function allocate_program(int $pid): int {
    $p = row("SELECT * FROM program WHERE id=?", [$pid]); if (!$p) return 0; $n = 0;
    foreach (rows("SELECT id FROM angajati WHERE grupa_id=? AND activ=1", [$p['grupa_id']]) as $a) {
        $open = val("SELECT COUNT(*) FROM instruiri WHERE angajat_id=? AND curs_id=? AND (status IN ('alocat','promovat','picat') OR (status='semnat' AND urmatoarea > ?))", [$a['id'], $p['curs_id'], date('Y-m-d', strtotime('+30 days'))]);
        if (!$open) { $iid = insert('instruiri', ['angajat_id' => $a['id'], 'curs_id' => $p['curs_id'], 'program_id' => $pid, 'status' => 'alocat', 'created_at' => date('Y-m-d H:i')]); $n++; if (function_exists('notify_test')) notify_test($iid); }
    }
    return $n;
}

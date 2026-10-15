<?php
function opts(string $sql, bool $scoped = true): array {
    $sf = scope_firma();
    if ($scoped && $sf) { $sql .= (str_contains($sql, 'WHERE') ? ' AND' : ' WHERE') . ' firma_id=' . $sf; }
    $o = [];
    foreach (rows($sql . ' ORDER BY 2') as $r) $o[$r['id']] = $r['l'];
    return $o;
}
function cc(string $a, string $b): string { return drv() === 'mysql' ? "CONCAT($a,' – ',$b)" : "$a || ' – ' || $b"; }
function fsc(): string { return scope_firma() ? ' WHERE f.id=' . scope_firma() : ''; }
function entities(): array {
    $staff = ['superadmin', 'inspector'];
    $all = ['superadmin', 'inspector', 'client'];
    $firme = fn() => opts("SELECT id, denumire AS l FROM firme" . (scope_firma() ? ' WHERE id=' . scope_firma() : ''), false);
    return [
        'firme' => ['title' => 'Firme (clienți)', 'one' => 'firmă', 'scope' => 'id', 'read' => $staff, 'write' => $staff, 'order' => 'denumire',
            'list' => ['denumire', 'cui', 'reg_com', 'telefon', 'servicii'],
            'fields' => [
                'denumire' => ['Denumire', 'text', 1], 'cui' => ['CUI', 'text', 1], 'reg_com' => ['Nr. Reg. Com.', 'text'], 'caen' => ['Cod CAEN principal', 'text'],
                'adresa' => ['Adresă sediu', 'text'], 'administrator' => ['Administrator', 'text'], 'telefon' => ['Telefon', 'text'], 'email' => ['E-mail', 'email'],
                'contract_nr' => ['Nr. contract', 'text'], 'contract_data' => ['Data contract', 'date'], 'servicii' => ['Servicii contractate', 'text', 0, 'ex. SSM, PSI, Medicina muncii'], 'note' => ['Notițe', 'textarea'],
            ]],
        'puncte' => ['title' => 'Puncte de lucru', 'one' => 'punct de lucru', 'scope' => 'firma_id', 'read' => $all, 'write' => $all, 'order' => 'nume',
            'list' => ['nume', 'firma_id', 'adresa', 'conducator'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'nume' => ['Nume', 'text', 1], 'adresa' => ['Adresă', 'text'], 'conducator' => ['Conducător loc de muncă', 'text'], 'riscuri' => ['Riscuri principale', 'textarea']]],
        'grupe' => ['title' => 'Grupe de instruire', 'one' => 'grupă', 'scope' => 'firma_id', 'read' => $staff, 'write' => $staff, 'order' => 'nume',
            'list' => ['nume', 'firma_id', 'tip', 'periodicitate_luni'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'nume' => ['Nume grupă', 'text', 1], 'tip' => ['Tip', 'select', 1, fn() => ['SSM' => 'SSM', 'SU' => 'SU / PSI']], 'periodicitate_luni' => ['Periodicitate (luni)', 'number', 1, '1–12'], 'descriere' => ['Descriere', 'textarea']]],
        'angajati' => ['title' => 'Angajați', 'one' => 'angajat', 'scope' => 'firma_id', 'read' => $all, 'write' => $all, 'order' => 'nume, prenume',
            'list' => ['nume', 'prenume', 'cnp', 'functie', 'firma_id', 'grupa_id', 'medical_expira'],
            'fields' => [
                'firma_id' => ['Firmă', 'select', 1, $firme], 'nume' => ['Nume', 'text', 1], 'prenume' => ['Prenume', 'text', 1], 'cnp' => ['CNP', 'cnp', 1, '13 cifre, se validează cifra de control'],
                'functie' => ['Funcție', 'text', 1], 'cor' => ['Cod COR', 'text'], 'data_angajarii' => ['Data angajării', 'date'],
                'punct_id' => ['Punct de lucru', 'select', 0, fn() => opts("SELECT p.id, " . cc('f.denumire', 'p.nume') . " AS l FROM puncte p JOIN firme f ON f.id=p.firma_id" . fsc(), false)],
                'grupa_id' => ['Grupă de instruire', 'select', 0, fn() => opts("SELECT g.id, " . cc('f.denumire', 'g.nume') . " AS l FROM grupe g JOIN firme f ON f.id=g.firma_id" . fsc(), false)],
                'telefon' => ['Telefon', 'text'], 'email' => ['E-mail', 'email'], 'medical_expira' => ['Aviz medicina muncii valabil până la', 'date'], 'activ' => ['Activ', 'check'],
            ]],
        'cursuri' => ['title' => 'Cursuri & teste', 'one' => 'curs', 'scope' => null, 'read' => $staff, 'write' => $staff, 'order' => 'titlu',
            'list' => ['titlu', 'tip', 'durata_min', 'prag_promovare'],
            'fields' => ['titlu' => ['Titlu', 'text', 1], 'tip' => ['Tip instruire', 'select', 1, fn() => ['Introductiv-generală' => 'Introductiv-generală', 'La locul de muncă' => 'La locul de muncă', 'Periodică' => 'Periodică', 'Suplimentară' => 'Suplimentară', 'SU / PSI' => 'SU / PSI']], 'durata_min' => ['Durată (minute)', 'number', 1], 'prag_promovare' => ['Prag promovare test (%)', 'number', 1], 'descriere' => ['Descriere', 'textarea']]],
        'lectii' => ['title' => 'Lecții', 'one' => 'lecție', 'scope' => null, 'parent' => 'curs_id', 'read' => $staff, 'write' => $staff, 'order' => 'ordine',
            'list' => ['ordine', 'titlu', 'video_url'],
            'fields' => ['curs_id' => ['Curs', 'select', 1, fn() => opts("SELECT id, titlu AS l FROM cursuri", false)], 'ordine' => ['Ordine', 'number', 1], 'titlu' => ['Titlu', 'text', 1], 'continut' => ['Conținut (text)', 'textarea', 1], 'video_url' => ['Link video (YouTube embed / mp4)', 'url']]],
        'intrebari' => ['title' => 'Întrebări test', 'one' => 'întrebare', 'scope' => null, 'parent' => 'curs_id', 'read' => $staff, 'write' => $staff, 'order' => 'id',
            'list' => ['text', 'corect'],
            'fields' => ['curs_id' => ['Curs', 'select', 1, fn() => opts("SELECT id, titlu AS l FROM cursuri", false)], 'text' => ['Întrebare', 'textarea', 1], 'opt_a' => ['Varianta A', 'text', 1], 'opt_b' => ['Varianta B', 'text', 1], 'opt_c' => ['Varianta C', 'text', 1], 'corect' => ['Răspuns corect', 'select', 1, fn() => ['a' => 'A', 'b' => 'B', 'c' => 'C']]]],
        'program' => ['title' => 'Program de instruire', 'one' => 'program', 'scope' => 'firma_id', 'read' => $staff, 'write' => $staff, 'order' => 'data_start',
            'list' => ['firma_id', 'grupa_id', 'curs_id', 'periodicitate_luni', 'data_start', 'instructor'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'grupa_id' => ['Grupă', 'select', 1, fn() => opts("SELECT g.id, " . cc('f.denumire', 'g.nume') . " AS l FROM grupe g JOIN firme f ON f.id=g.firma_id" . fsc(), false)], 'curs_id' => ['Curs', 'select', 1, fn() => opts("SELECT id, titlu AS l FROM cursuri", false)], 'periodicitate_luni' => ['Periodicitate (luni)', 'number', 1], 'data_start' => ['Data începerii', 'date', 1], 'instructor' => ['Instructor (nume)', 'text', 1]]],
        'users' => ['title' => 'Utilizatori', 'one' => 'utilizator', 'scope' => null, 'read' => ['superadmin'], 'write' => ['superadmin'], 'order' => 'name',
            'list' => ['name', 'email', 'role', 'firma_id'],
            'fields' => ['name' => ['Nume', 'text', 1], 'email' => ['Utilizator / e-mail', 'text', 1], 'role' => ['Rol', 'select', 1, fn() => ['superadmin' => 'Super-admin', 'inspector' => 'Inspector SSM', 'client' => 'Administrator client']], 'firma_id' => ['Firmă (doar pentru administrator client)', 'select', 0, $firme], 'password' => ['Parolă nouă', 'password', 0, 'Lăsați gol pentru a păstra parola; minim 8 caractere']]],
    ];
}
function ent(string $e): array { $E = entities(); if (!isset($E[$e])) { http_response_code(404); exit('Necunoscut'); } return $E[$e] + ['key' => $e]; }

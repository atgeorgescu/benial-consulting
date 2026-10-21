<?php
const TIPURI_INSTR = ['Introductiv-generală' => 'Introductiv-generală', 'La locul de muncă' => 'La locul de muncă', 'Periodică' => 'Periodică', 'Suplimentară' => 'Suplimentară', 'SU / PSI' => 'SU / PSI', 'Alte' => 'Alte'];
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
                'domeniu' => ['Domeniu de activitate', 'text'], 'tva' => ['Plătitor TVA', 'select', 0, fn() => ['Da' => 'Da', 'Nu' => 'Nu']], 'firma_mama' => ['Firmă-mamă (grup)', 'text'], 'localitate' => ['Localitate', 'text'], 'judet' => ['Județ', 'text'], 'iban' => ['IBAN', 'text'], 'banca' => ['Banca', 'text'], 'mod_lucru' => ['Mod de lucru', 'select', 0, fn() => ['online' => 'Online (angajații folosesc portalul)', 'offline' => 'Offline (pe hârtie)']],
                'contract_nr' => ['Nr. contract', 'text'], 'contract_data' => ['Data contract', 'date'], 'servicii' => ['Servicii contractate', 'text', 0, 'ex. SSM, PSI, Medicina muncii'], 'note' => ['Notițe', 'textarea'],
            ]],
        'puncte' => ['title' => 'Puncte de lucru', 'one' => 'punct de lucru', 'scope' => 'firma_id', 'read' => $all, 'write' => $all, 'order' => 'nume',
            'list' => ['nume', 'firma_id', 'adresa', 'clm_id'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'nume' => ['Nume', 'text', 1], 'adresa' => ['Adresă', 'text'], 'conducator' => ['Conducător loc de muncă (text liber)', 'text'], 'clm_id' => ['CLM – semnează documentele', 'select', 0, fn() => opts("SELECT a.id, " . cc('a.nume', 'a.prenume') . " AS l FROM angajati a WHERE a.este_clm=1" . (scope_firma() ? ' AND a.firma_id=' . scope_firma() : ''), false)], 'verificator' => ['Verificator', 'text'], 'localitate' => ['Localitate', 'text'], 'judet' => ['Județ', 'text'], 'riscuri' => ['Riscuri principale', 'textarea']]],
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
                'puncte_extra' => ['Alte puncte de lucru', 'multi', 0, fn() => opts("SELECT p.id, " . cc('f.denumire', 'p.nume') . " AS l FROM puncte p JOIN firme f ON f.id=p.firma_id" . fsc(), false)],
                'loc_munca' => ['Loc de muncă', 'text'], 'marca' => ['Marca', 'text'], 'timp_lucru' => ['Timp de lucru', 'text', 0, 'ex. 8 ore/zi'], 'data_nasterii' => ['Data nașterii', 'date'], 'adresa' => ['Adresă domiciliu', 'text'],
                'telefon' => ['Telefon', 'text'], 'email' => ['E-mail', 'email'],
                'categorie_pers' => ['Categorie personal', 'select', 0, fn() => ['TESA' => 'TESA', 'Productiv' => 'Productiv']],
                'echipa' => ['Echipă situații de urgență', 'select', 0, fn() => ['Non-intervenție' => 'Non-intervenție', 'Echipă intervenție' => 'Echipă intervenție', 'Echipă evacuare' => 'Echipă evacuare', 'Prim ajutor' => 'Prim ajutor']],
                'este_clm' => ['Este conducător loc de muncă (CLM)', 'check'], 'resp_ssm' => ['Responsabil SSM / SU', 'check'],
                'meserie_aut' => ['Meserie autorizată (ISCIR, electrician ANRE etc.)', 'text'], 'aut_expira' => ['Autorizație valabilă până la', 'date'],
                'medical_ultim' => ['Data ultimului examen medical', 'date'], 'medical_aviz' => ['Aviz medical', 'select', 0, fn() => ['Apt' => 'Apt', 'Apt condiționat' => 'Apt condiționat', 'Inapt temporar' => 'Inapt temporar', 'Inapt' => 'Inapt']],
                'medical_expira' => ['Aviz medicina muncii valabil până la', 'date'],
                'username' => ['Utilizator portal (opțional)', 'text', 0, 'pentru autentificare cu parolă, în plus față de codul de acces'], 'password' => ['Parolă portal', 'password', 0, 'Lăsați gol pentru a păstra; minim 8 caractere'],
                'email_optout' => ['Fără notificări pe e-mail', 'check'], 'activ' => ['Activ în platformă', 'check'],
            ]],
        'cursuri' => ['title' => 'Cursuri & teste', 'one' => 'curs', 'scope' => null, 'read' => $staff, 'write' => $staff, 'order' => 'titlu',
            'list' => ['titlu', 'domeniu', 'tip', 'durata_min', 'prag_promovare', 'emite_fisa16'],
            'fields' => ['titlu' => ['Titlu', 'text', 1], 'domeniu' => ['Domeniu', 'select', 0, fn() => ['SSM' => 'SSM', 'SU' => 'SU / PSI']], 'tip' => ['Tip instruire', 'select', 1, fn() => TIPURI_INSTR],
                'firma_id' => ['Firmă (gol = toate)', 'select', 0, $firme], 'grupa_id' => ['Grupă (opțional)', 'select', 0, fn() => opts("SELECT g.id, " . cc('f.denumire', 'g.nume') . " AS l FROM grupe g JOIN firme f ON f.id=g.firma_id" . fsc(), false)],
                'categorie_id' => ['Categorie tematică', 'select', 0, fn() => opts("SELECT id, nume AS l FROM categorii", false)], 'tip_personal' => ['Tip personal', 'select', 0, fn() => ['Toți' => 'Toți', 'TESA' => 'TESA', 'Productiv' => 'Productiv', 'Intervenție' => 'Echipă intervenție']],
                'acces' => ['Nivel de acces', 'select', 0, fn() => ['angajati' => 'Angajați (portal)', 'staff' => 'Doar inspectori']], 'durata_min' => ['Durată (minute)', 'number', 1],
                'mod_test' => ['Test', 'select', 0, fn() => ['online' => 'Online (în portal)', 'offline' => 'Offline (pe hârtie, rezultat introdus de inspector)']], 'emite_fisa16' => ['Emite fișa 1.6 (rezultatul testării)', 'check'],
                'dificultate' => ['Dificultate test', 'select', 0, fn() => ['ușor' => 'ușor', 'mediu' => 'mediu', 'dificil' => 'dificil']], 'prag_promovare' => ['Prag promovare test (%)', 'number', 1], 'timp_test_min' => ['Timp total test (minute, 0 = nelimitat)', 'number'], 'nr_intrebari' => ['Nr. întrebări extrase (0 = toate)', 'number'],
                'imagini' => ['Imagini (URL-uri, câte unul pe linie)', 'textarea'], 'atasamente' => ['Atașamente (URL-uri, câte unul pe linie)', 'textarea'], 'descriere' => ['Descriere', 'textarea']]],
        'lectii' => ['title' => 'Lecții', 'one' => 'lecție', 'scope' => null, 'parent' => 'curs_id', 'read' => $staff, 'write' => $staff, 'order' => 'ordine',
            'list' => ['ordine', 'titlu', 'video_url'],
            'fields' => ['curs_id' => ['Curs', 'select', 1, fn() => opts("SELECT id, titlu AS l FROM cursuri", false)], 'ordine' => ['Ordine', 'number', 1], 'titlu' => ['Titlu', 'text', 1], 'continut' => ['Conținut (text)', 'textarea', 1], 'video_url' => ['Link video (YouTube embed / mp4)', 'url']]],
        'intrebari' => ['title' => 'Întrebări test', 'one' => 'întrebare', 'scope' => null, 'parent' => 'curs_id', 'read' => $staff, 'write' => $staff, 'order' => 'id',
            'list' => ['text', 'corect'],
            'fields' => ['curs_id' => ['Curs', 'select', 1, fn() => opts("SELECT id, titlu AS l FROM cursuri", false)], 'text' => ['Întrebare', 'textarea', 1], 'opt_a' => ['Varianta A', 'text', 1], 'opt_b' => ['Varianta B', 'text', 1], 'opt_c' => ['Varianta C', 'text'], 'opt_d' => ['Varianta D', 'text'], 'corect' => ['Răspuns(uri) corect(e)', 'multi', 1, fn() => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D']], 'puncte' => ['Puncte', 'number', 0, 'implicit 1']]],
        'program' => ['title' => 'Program de instruire', 'one' => 'program', 'scope' => 'firma_id', 'read' => $staff, 'write' => $staff, 'order' => 'data_start',
            'list' => ['firma_id', 'grupa_id', 'curs_id', 'tip_instruire', 'periodicitate_luni', 'luni_instruire', 'instructor'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'grupa_id' => ['Grupă', 'select', 1, fn() => opts("SELECT g.id, " . cc('f.denumire', 'g.nume') . " AS l FROM grupe g JOIN firme f ON f.id=g.firma_id" . fsc(), false)], 'curs_id' => ['Curs', 'select', 1, fn() => opts("SELECT id, titlu AS l FROM cursuri", false)], 'periodicitate_luni' => ['Periodicitate (luni)', 'number', 1], 'tip_instruire' => ['Tip instruire', 'select', 0, fn() => TIPURI_INSTR + ['Colectivă' => 'Colectivă']], 'data_start' => ['Data începerii', 'date', 1], 'ora' => ['Ora', 'text', 0, 'ex. 09:00'], 'durata_ore' => ['Durata (ore)', 'text'],
                'luni_instruire' => ['Lunile de instruire', 'text', 0, 'ex. 3,9 (gol = calculat din periodicitate)'], 'luna_test_anual' => ['Luna testului anual', 'select', 0, fn() => luni_ro()], 'test_la_angajare' => ['Test la angajare', 'check'], 'instructor' => ['Instructor (nume)', 'text', 1]]],
        'categorii' => ['title' => 'Categorii tematici', 'one' => 'categorie', 'scope' => null, 'read' => $staff, 'write' => $staff, 'order' => 'domeniu, nume',
            'list' => ['nume', 'parent_id', 'domeniu', 'tip', 'limba'],
            'fields' => ['nume' => ['Nume', 'text', 1], 'parent_id' => ['Categorie părinte', 'select', 0, fn() => opts("SELECT id, nume AS l FROM categorii", false)], 'domeniu' => ['Domeniu', 'select', 1, fn() => ['SSM' => 'SSM', 'SU' => 'PSI / SU']], 'tip' => ['Tip', 'select', 0, fn() => ['General' => 'General', 'TESA' => 'TESA', 'Intervenție' => 'Intervenție', 'Non-intervenție' => 'Non-intervenție', 'Spot' => 'Spot', 'Colectivă' => 'Colectivă', 'Suplimentară' => 'Suplimentară']], 'limba' => ['Limba', 'select', 0, fn() => ['ro' => 'Română', 'en' => 'Engleză', 'hu' => 'Maghiară', 'uk' => 'Ucraineană']]]],
        'sabloane' => ['title' => 'Șabloane documente', 'one' => 'șablon', 'scope' => null, 'read' => $staff, 'write' => $staff, 'order' => 'domeniu, cod',
            'list' => ['cod', 'nume', 'domeniu', 'tip', 'firma_id', 'implicit'],
            'fields' => ['cod' => ['Cod', 'text', 1, 'ex. SSM-TEM-IIG; un șablon cu același cod și o firmă aleasă înlocuiește șablonul implicit pentru acea firmă'], 'nume' => ['Nume', 'text', 1], 'domeniu' => ['Categorie', 'select', 1, fn() => ['SSM' => 'SSM', 'SU' => 'PSI / SU']], 'tip' => ['Tip', 'select', 1, fn() => ['tematica' => 'Tematică', 'fisa' => 'Fișă', 'test' => 'Rezultat testare']], 'instr' => ['Felul instruirii (apare pe document)', 'text'], 'firma_id' => ['Firmă (gol = implicit pentru toate)', 'select', 0, $firme], 'continut' => ['Conținut (tematici: „Temă | ore” pe fiecare linie; fișe: notă)', 'textarea'], 'implicit' => ['Șablon implicit', 'check']]],
        'echipamente' => ['title' => 'Echipamente și mijloace de stingere', 'one' => 'echipament', 'scope' => 'firma_id', 'read' => $all, 'write' => $all, 'order' => 'urmatoarea',
            'list' => ['denumire', 'categorie', 'firma_id', 'punct_id', 'serie', 'urmatoarea'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'punct_id' => ['Punct de lucru', 'select', 0, fn() => opts("SELECT p.id, " . cc('f.denumire', 'p.nume') . " AS l FROM puncte p JOIN firme f ON f.id=p.firma_id" . fsc(), false)], 'categorie' => ['Categorie', 'select', 1, fn() => ['echipament' => 'Echipament de muncă', 'stingere' => 'Mijloc de stingere (stingător, hidrant…)']], 'denumire' => ['Denumire', 'text', 1], 'tip' => ['Tip / model', 'text', 0, 'ex. stingător P6, motostivuitor, macara ISCIR'], 'serie' => ['Serie / nr. inventar', 'text'], 'data_verificare' => ['Data ultimei verificări', 'date', 1], 'periodicitate_luni' => ['Periodicitate verificare (luni)', 'number', 1], 'urmatoarea' => ['Următoarea verificare (calculată dacă e gol)', 'date'], 'verificat_de' => ['Verificat de', 'text'], 'observatii' => ['Observații', 'textarea']]],
        'audituri' => ['title' => 'Audituri SSM / PSI', 'one' => 'audit', 'scope' => 'firma_id', 'read' => $all, 'write' => $staff, 'order' => 'data DESC',
            'list' => ['data', 'firma_id', 'punct_id', 'domeniu', 'auditor'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'punct_id' => ['Punct de lucru', 'select', 0, fn() => opts("SELECT p.id, " . cc('f.denumire', 'p.nume') . " AS l FROM puncte p JOIN firme f ON f.id=p.firma_id" . fsc(), false)], 'data' => ['Data', 'date', 1], 'domeniu' => ['Domeniu', 'select', 1, fn() => ['SSM' => 'SSM', 'PSI' => 'PSI', 'SSM-PSI' => 'SSM-PSI']], 'auditor' => ['Auditor', 'text', 1], 'concluzii' => ['Concluzii', 'textarea']]],
        'neconformitati' => ['title' => 'Neconformități audit', 'one' => 'neconformitate', 'scope' => 'firma_id', 'read' => $all, 'write' => $all, 'order' => 'termen',
            'list' => ['descriere', 'firma_id', 'responsabil', 'termen', 'status'],
            'fields' => ['firma_id' => ['Firmă', 'select', 1, $firme], 'audit_id' => ['Audit', 'select', 0, fn() => opts("SELECT au.id, " . cc('f.denumire', 'au.data') . " AS l FROM audituri au JOIN firme f ON f.id=au.firma_id" . (scope_firma() ? ' WHERE au.firma_id=' . scope_firma() : ''), false)], 'descriere' => ['Neconformitate', 'textarea', 1], 'masura' => ['Măsura de remediere', 'textarea'], 'responsabil' => ['Responsabil', 'text'], 'termen' => ['Termen', 'date'], 'status' => ['Stare', 'select', 1, fn() => ['deschisă' => 'deschisă', 'în lucru' => 'în lucru', 'rezolvată' => 'rezolvată']]]],
        'users' => ['title' => 'Utilizatori', 'one' => 'utilizator', 'scope' => null, 'read' => ['superadmin'], 'write' => ['superadmin'], 'order' => 'name',
            'list' => ['name', 'email', 'role', 'firma_id'],
            'fields' => ['name' => ['Nume', 'text', 1], 'email' => ['Utilizator / e-mail', 'text', 1], 'role' => ['Rol', 'select', 1, fn() => ['superadmin' => 'Super-admin', 'inspector' => 'Inspector SSM', 'client' => 'Administrator client']], 'firma_id' => ['Firmă (doar pentru administrator client)', 'select', 0, $firme], 'password' => ['Parolă nouă', 'password', 0, 'Lăsați gol pentru a păstra parola; minim 8 caractere'], 'notif_email' => ['E-mail pentru notificări', 'email'], 'email_optout' => ['Fără notificări pe e-mail', 'check']]],
    ];
}
function ent(string $e): array { $E = entities(); if (!isset($E[$e])) { http_response_code(404); exit('Necunoscut'); } return $E[$e] + ['key' => $e]; }

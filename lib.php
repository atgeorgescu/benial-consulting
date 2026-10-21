<?php
declare(strict_types=1);
if (is_file(__DIR__ . '/env.local.php')) require __DIR__ . '/env.local.php';
session_start();
date_default_timezone_set('Europe/Bucharest');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $url = getenv('DATABASE_URL') ?: '';
    if ($url) {
        $p = parse_url($url);
        $scheme = $p['scheme'] ?? 'postgres';
        $drv = str_starts_with($scheme, 'mysql') ? 'mysql' : 'pgsql';
        $port = $p['port'] ?? ($drv === 'mysql' ? 3306 : 5432);
        $dsn = "$drv:host={$p['host']};port=$port;dbname=" . ltrim($p['path'], '/');
        if ($drv === 'mysql') $dsn .= ';charset=utf8mb4';
        $pdo = new PDO($dsn, urldecode($p['user'] ?? ''), urldecode($p['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($drv === 'pgsql' && ($sch = getenv('DB_SCHEMA')) && preg_match('/^[a-z_][a-z0-9_]*$/', $sch)) { $pdo->exec("CREATE SCHEMA IF NOT EXISTS $sch"); $pdo->exec("SET search_path TO $sch"); }
    } else {
        $pdo = new PDO('sqlite:' . (getenv('SQLITE_PATH') ?: __DIR__ . '/data.sqlite'));
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function drv(): string { return db()->getAttribute(PDO::ATTR_DRIVER_NAME); }
function q(string $sql, array $a = []): PDOStatement { $s = db()->prepare($sql); $s->execute($a); return $s; }
function rows(string $sql, array $a = []): array { return q($sql, $a)->fetchAll(); }
function row(string $sql, array $a = []): ?array { $r = q($sql, $a)->fetch(); return $r ?: null; }
function val(string $sql, array $a = []) { $r = q($sql, $a)->fetchColumn(); return $r === false ? null : $r; }
function insert(string $t, array $d): int {
    $c = array_keys($d);
    q("INSERT INTO $t (" . implode(',', $c) . ") VALUES (" . implode(',', array_fill(0, count($c), '?')) . ")", array_values($d));
    return (int)db()->lastInsertId();
}
function update(string $t, int $id, array $d): void {
    $set = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
    q("UPDATE $t SET $set WHERE id=?", [...array_values($d), $id]);
}
function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function url(string $r, array $p = []): string { return '?' . http_build_query(['r' => $r] + $p); }
function redirect(string $u): never { header("Location: $u"); exit; }
function flash(string $m, string $t = 'ok'): void { $_SESSION['flash'][] = [$t, $m]; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }
function check_csrf(): void { if (($_POST['_csrf'] ?? '') !== csrf()) { http_response_code(400); exit('Sesiune expirată. Reîncărcați pagina.'); } }
function user(): ?array { return $_SESSION['user'] ?? null; }
function role(): string { return user()['role'] ?? ''; }
function is_staff(): bool { return in_array(role(), ['superadmin', 'inspector']); }
function require_role(string ...$r): void { if (!user()) redirect(url('login')); if (!in_array(role(), $r)) { http_response_code(403); page('Acces interzis', '<div class="card"><p>Nu aveți drepturi pentru această pagină.</p></div>'); exit; } }
/** firma_id la care e limitat utilizatorul curent (null = toate) */
function scope_firma(): ?int { return role() === 'client' ? (int)user()['firma_id'] : null; }
function today(): string { return date('Y-m-d'); }
function add_months(string $d, int $m): string { return date('Y-m-d', strtotime("$d +$m months")); }
function days_until(?string $d): ?int { if (!$d) return null; return (int)floor((strtotime($d) - strtotime(today())) / 86400); }
function fmt_date(?string $d): string { return $d ? date('d.m.Y', strtotime($d)) : '—'; }
function mask_cnp(?string $c): string { return $c ? substr($c, 0, 1) . str_repeat('•', 8) . substr($c, -4) : '—'; }
function valid_cnp(string $c): bool {
    if (!preg_match('/^[1-9]\d{12}$/', $c)) return false;
    $k = '279146358279'; $s = 0;
    for ($i = 0; $i < 12; $i++) $s += (int)$c[$i] * (int)$k[$i];
    $r = $s % 11; $r = $r == 10 ? 1 : $r;
    return $r == (int)$c[12];
}
function badge_days(?int $d): string {
    if ($d === null) return '<span class="b b-gray">fără termen</span>';
    if ($d < 0) return '<span class="b b-red">expirat de ' . (-$d) . ' zile</span>';
    if ($d <= 7) return '<span class="b b-red">' . $d . ' zile</span>';
    if ($d <= 15) return '<span class="b b-orange">' . $d . ' zile</span>';
    if ($d <= 30) return '<span class="b b-yellow">' . $d . ' zile</span>';
    return '<span class="b b-green">' . $d . ' zile</span>';
}
function gen_code(int $n = 8): string { $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = ''; for ($i = 0; $i < $n; $i++) $s .= $a[random_int(0, strlen($a) - 1)]; return $s; }

function nav(): string {
    $u = user(); if (!$u) return '';
    $cur = $_GET['r'] ?? 'dashboard';
    $items = match ($u['role']) {
        'angajat' => ['portal' => 'Instruirile mele', 'semneaza' => 'De semnat', 'mesaje' => 'Mesaje', 'chat' => 'Chat', 'tichete' => 'Suport', 'preferinte' => 'Preferințe'],
        'client' => ['dashboard' => 'Centru de termene', 'list&e=angajati' => 'Angajați', 'list&e=puncte' => 'Puncte de lucru', 'list&e=echipamente' => 'Echipamente', 'import' => 'Import', 'documente' => 'Documente', 'rapoarte' => 'Rapoarte', 'mesaje' => 'Mesaje', 'chat' => 'Chat', 'tichete' => 'Suport', 'preferinte' => 'Preferințe'],
        default => ['dashboard' => 'Centru de termene', 'list&e=firme' => 'Firme', 'list&e=puncte' => 'Puncte de lucru', 'list&e=angajati' => 'Angajați', 'list&e=grupe' => 'Grupe', 'list&e=program' => 'Program instruire', 'list&e=categorii' => 'Categorii', 'list&e=cursuri' => 'Cursuri & teste', 'list&e=sabloane' => 'Șabloane', 'documente' => 'Documente', 'list&e=echipamente' => 'Echipamente', 'list&e=audituri' => 'Audit', 'list&e=neconformitati' => 'Neconformități', 'rapoarte' => 'Rapoarte', 'mesaje' => 'Mesaje', 'chat' => 'Chat', 'tichete' => 'Tichete', 'preferinte' => 'Preferințe'] + ($u['role'] === 'superadmin' ? ['list&e=users' => 'Utilizatori', 'setari' => 'Setări e-mail', 'ai_setari' => 'Setări AI'] : []),
    };
    $h = '';
    foreach ($items as $k => $l) {
        $active = (explode('&', $k)[0] === $cur && (!str_contains($k, 'e=') || str_contains($k, 'e=' . ($_GET['e'] ?? '')))) ? ' class="on"' : '';
        $h .= "<a href=\"?r=$k\"$active>" . h($l) . "</a>";
    }
    return $h;
}
function page(string $title, string $body): void {
    $u = user();
    $fl = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) $fl .= "<div class=\"flash $t\">" . h($m) . "</div>";
    unset($_SESSION['flash']);
    $roles = ['superadmin' => 'Super-admin', 'inspector' => 'Inspector SSM', 'client' => 'Administrator client', 'angajat' => 'Angajat'];
    $bc = $u ? bell_count() : 0;
    $userbox = $u ? '<div class="who"><a class="bell" href="' . url($u['role'] === 'angajat' && docs_to_sign() ? 'semneaza' : 'mesaje') . '" title="Notificări">🔔' . ($bc ? '<i>' . $bc . '</i>' : '') . '</a><span>' . h($u['name']) . ' · ' . $roles[$u['role']] . '</span><form method="post" action="' . url('logout') . '">' . csrf_field() . '<button class="btn sm ghost">Ieșire</button></form></div>' : '';
    echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . ' · BENIAL CONSULTING</title><link rel="stylesheet" href="style.css?v=' . @filemtime(__DIR__ . '/public/style.css') . '"><link rel="manifest" href="manifest.webmanifest"><meta name="theme-color" content="#0f172a"><link rel="icon" href="icons/icon-192.png"><link rel="apple-touch-icon" href="icons/apple-touch-icon.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="BENIAL"></head><body>';
    echo '<header class="top"><a class="logo" href="?"><span class="mark">B</span><span><b>BENIAL</b> CONSULTING<small>SSM · PSI · Situații de urgență</small></span></a>' . $userbox . '</header>';
    if ($u) echo '<nav class="nav">' . nav() . '</nav>';
    echo '<main><h1>' . h($title) . '</h1>' . $fl . $body . '</main><footer>© ' . date('Y') . ' BENIAL CONSULTING · prototip de test</footer>' . (function_exists('ai_widget') ? ai_widget() : '') . '<script src="app.js"></script><script src="pwa.js"></script></body></html>';
}

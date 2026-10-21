<?php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../schema.php';
require __DIR__ . '/../entities.php';
require __DIR__ . '/../views.php';
require __DIR__ . '/../ext.php';
migrate(); migrate_ext();
$flag = sys_get_temp_dir() . '/benial_seeded_' . md5((string)getenv('DATABASE_URL') . __DIR__); if (!file_exists($flag)) { seed(); @touch($flag); }
$r = $_GET['r'] ?? (user() ? (role() === 'angajat' ? 'portal' : 'dashboard') : 'login');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && $r !== 'login') check_csrf();
if (!user() && !in_array($r, ['login'])) redirect(url('login'));
try {
    match ($r) {
        'login' => r_login($post), 'logout' => (function () { session_destroy(); redirect(url('login')); })(),
        'dashboard' => r_dashboard(),
        'list' => r_list(ent($_GET['e'] ?? '')), 'form' => r_form(ent($_GET['e'] ?? '')),
        'save' => r_save(ent($_GET['e'] ?? '')), 'delete' => r_delete(ent($_GET['e'] ?? '')),
        'angajat' => r_angajat(), 'regen' => r_regen(), 'alloc' => r_alloc(), 'curs' => r_curs(),
        'import' => r_import($post), 'import_template' => r_import_template(),
        'documente' => r_documente(), 'fisa' => r_fisa(), 'rapoarte' => r_rapoarte(),
        'doc' => r_doc(), 'docs_print' => r_docs_print(), 'docs_delete' => r_docs_delete(), 'genereaza' => r_genereaza($post),
        'semneaza' => r_semneaza($post), 'semneaza_instr' => r_semneaza_instr($post), 'offline' => r_offline(),
        'mesaje' => r_mesaje($post), 'chat' => r_chat($post), 'tichete' => r_tichete($post),
        'portal' => r_portal(), 'take' => r_take(), 'submit_test' => r_submit_test(), 'sign' => r_sign(),
        default => page('Pagină inexistentă', '<div class="card">Pagina nu există.</div>'),
    };
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    page('Eroare', '<div class="card"><p>A apărut o eroare. Încercați din nou.</p>' . (getenv('APP_DEBUG') ? '<pre>' . h($e) . '</pre>' : '') . '</div>');
}

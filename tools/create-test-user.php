<?php
/**
 * Strumento di sviluppo — crea utenza e personaggio di test.
 * Accessibile solo se CONFIG['debug'] = true.
 */
require_once __DIR__ . '/../autoload.php';

if (!defined('CONFIG') || !(bool) CONFIG['debug']) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>Accesso negato</title></head>'
        . '<body style="font-family:sans-serif;padding:40px"><h2>403 — Solo in modalità debug</h2>'
        . '<p>Questo strumento è disponibile solo quando <code>CONFIG[\'debug\'] = true</code>.</p></body></html>';
    exit;
}

$msg     = '';
$msgType = 'success';
$lastEmail = '';
$lastCharName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $characterName   = trim($_POST['character_name'] ?? '');
    $gender          = (int) ($_POST['gender'] ?? 1);
    $isAdmin         = isset($_POST['is_administrator']) ? 1 : 0;
    $isMaster        = isset($_POST['is_master']) ? 1 : 0;
    $isModerator     = isset($_POST['is_moderator']) ? 1 : 0;

    $lastEmail    = htmlspecialchars($email);
    $lastCharName = htmlspecialchars($characterName);

    do {
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $msg = 'Email non valida.'; $msgType = 'danger'; break;
        }
        if (strlen($password) < 6) {
            $msg = 'La password deve essere di almeno 6 caratteri.'; $msgType = 'danger'; break;
        }
        if ($password !== $passwordConfirm) {
            $msg = 'Le password non coincidono.'; $msgType = 'danger'; break;
        }
        if ($characterName === '') {
            $msg = 'Il nome del personaggio è obbligatorio.'; $msgType = 'danger'; break;
        }
        if (strlen($characterName) > 25) {
            $msg = 'Il nome del personaggio non può superare 25 caratteri.'; $msgType = 'danger'; break;
        }
        if (!defined('DB') || empty(DB['mysql']) || empty(DB['crypt_key'])) {
            $msg = 'Configurazione DB non disponibile (configs/db.php mancante o incompleto).'; $msgType = 'danger'; break;
        }

        $dbCfg    = DB['mysql'];
        $cryptKey = (string) DB['crypt_key'];

        $mysqli = @new \mysqli(
            (string) ($dbCfg['host']    ?? 'localhost'),
            (string) ($dbCfg['user']    ?? ''),
            (string) ($dbCfg['pwd']     ?? ''),
            (string) ($dbCfg['db_name'] ?? '')
        );

        if ($mysqli->connect_errno) {
            $msg = 'Connessione DB fallita: ' . htmlspecialchars($mysqli->connect_error);
            $msgType = 'danger';
            break;
        }

        $mysqli->set_charset((string) ($dbCfg['charset'] ?? 'utf8mb4'));

        $safeEmail    = $mysqli->real_escape_string($email);
        $safeKey      = $mysqli->real_escape_string($cryptKey);
        $safePassword = $mysqli->real_escape_string(password_hash($password, PASSWORD_BCRYPT));
        $safeName     = $mysqli->real_escape_string($characterName);

        if (!$mysqli->query(
            "INSERT INTO `users`
                (`email`, `password`, `gender`, `is_administrator`, `is_superuser`, `is_moderator`, `is_master`, `date_actived`, `date_created`, `session_version`)
             VALUES
                (AES_ENCRYPT('{$safeEmail}', '{$safeKey}'), '{$safePassword}', {$gender}, {$isAdmin}, 0, {$isModerator}, {$isMaster}, NOW(), NOW(), 1)"
        )) {
            $msg = 'Creazione utente fallita: ' . htmlspecialchars($mysqli->error);
            $msgType = 'danger';
            $mysqli->close();
            break;
        }

        $userId = (int) $mysqli->insert_id;

        if (!$mysqli->query(
            "INSERT INTO `characters` (`user_id`, `name`, `gender`, `socialstatus_id`)
             VALUES ({$userId}, '{$safeName}', {$gender}, 1)"
        )) {
            $msg = 'Creazione personaggio fallita (utente ID ' . $userId . ' creato): ' . htmlspecialchars($mysqli->error);
            $msgType = 'warning';
            $mysqli->close();
            break;
        }

        $mysqli->close();

        $lastEmail    = '';
        $lastCharName = '';
        $msg = 'Utenza e personaggio creati. ID utente: <strong>' . $userId . '</strong>';
        $msgType = 'success';
    } while (false);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crea utenza di test</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .dev-badge { font-size: .7rem; letter-spacing: .05em; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">

            <div class="d-flex align-items-center gap-2 mb-4">
                <h4 class="mb-0">Crea utenza di test</h4>
                <span class="badge text-bg-warning dev-badge">DEV ONLY</span>
            </div>

            <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= $msgType ?> mb-3"><?= $msg ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row g-3">

                            <div class="col-12">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?= $lastEmail ?>" placeholder="test@example.com" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password"
                                       placeholder="min 6 caratteri" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Conferma password</label>
                                <input type="password" class="form-control" name="password_confirm" required>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Nome personaggio</label>
                                <input type="text" class="form-control" name="character_name"
                                       value="<?= $lastCharName ?>" maxlength="25"
                                       placeholder="max 25 caratteri" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Genere</label>
                                <select class="form-select" name="gender">
                                    <option value="1">Maschile</option>
                                    <option value="2">Femminile</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label d-block">Ruoli</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_administrator" id="chk-admin" value="1">
                                        <label class="form-check-label" for="chk-admin">Amministratore</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_master" id="chk-master" value="1">
                                        <label class="form-check-label" for="chk-master">Master</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_moderator" id="chk-mod" value="1">
                                        <label class="form-check-label" for="chk-mod">Moderatore</label>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary">Crea utenza</button>
                        </div>
                    </form>
                </div>
            </div>

            <p class="text-muted small mt-3 text-center">
                Questo file (<code>tools/create-test-user.php</code>) è visibile solo con <code>CONFIG['debug'] = true</code>.
            </p>

        </div>
    </div>
</div>
</body>
</html>

<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Settings;
use App\Services\Branding;
use App\Services\EasybillClient;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $isAdmin = Auth::isAdmin();
        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        $data = [
            'pageTitle' => 'Einstellungen',
            'isAdmin' => $isAdmin,
            'user' => Auth::user(),
        ];
        if ($isAdmin) {
            $data += [
                'apiKey' => (string) env('EASYBILL_API_KEY', ''),
                'webhookSecret' => (string) env('WEBHOOK_SECRET', ''),
                'cronSecret' => (string) env('CRON_SECRET', ''),
                'appUrl' => $appUrl,
                'envWritable' => is_writable(Env::path()),
                'usingMock' => env('EASYBILL_BASE_URL') !== null,
                'lastSync' => Settings::get('easybill_last_sync'),
                'lastCron' => Settings::get('cron_last_run'),
                'lastCronStatus' => Settings::get('cron_last_status'),
                'log' => Database::all('SELECT * FROM sync_log ORDER BY id DESC LIMIT 25'),
                'users' => Database::all('SELECT id, name, email, role, active, last_login_at, created_at FROM users ORDER BY active DESC, name'),
            ];
        }
        $this->view('settings/index', $data);
    }

    /** Logo und Anzeigename (Einstellungen → Erscheinungsbild). */
    public function branding(): void
    {
        $name = trim((string) ($_POST['brand_name'] ?? ''));
        if (mb_strlen($name) > 40) {
            flash('error', 'Der Name darf höchstens 40 Zeichen lang sein.');
            redirect('/einstellungen#erscheinungsbild');
        }
        Settings::set('brand_name', $name);
        try {
            if (!empty($_POST['remove_logo'])) {
                Branding::removeLogo();
            } elseif (($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                Branding::storeUpload($_FILES['logo']);
            }
            flash('success', 'Erscheinungsbild gespeichert.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/einstellungen#erscheinungsbild');
    }

    public function password(): void
    {
        $user = Database::one('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!$user || !password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
            flash('error', 'Das aktuelle Passwort ist falsch.');
        } elseif (mb_strlen($new) < 10) {
            flash('error', 'Das neue Passwort muss mindestens 10 Zeichen lang sein.');
        } elseif ($new !== (string) ($_POST['new_password_confirm'] ?? '')) {
            flash('error', 'Die neuen Passwörter stimmen nicht überein.');
        } else {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Passwort geändert.');
        }
        redirect('/einstellungen');
    }

    /** easybill-Zugang: Werte werden ausschließlich in die .env geschrieben. */
    public function easybill(): void
    {
        $changes = [];
        $key = trim((string) ($_POST['api_key'] ?? ''));
        if (!empty($_POST['remove_api_key'])) {
            $changes['EASYBILL_API_KEY'] = '';
        } elseif ($key !== '') {
            if (!preg_match('/^[A-Za-z0-9_\-.]{16,200}$/', $key)) {
                flash('error', 'Der API-Key hat ein ungültiges Format.');
                redirect('/einstellungen#easybill');
            }
            $changes['EASYBILL_API_KEY'] = $key;
        }
        $secret = trim((string) ($_POST['webhook_secret'] ?? ''));
        if (!empty($_POST['regenerate_webhook'])) {
            $changes['WEBHOOK_SECRET'] = bin2hex(random_bytes(24));
        } elseif ($secret !== '' && $secret !== env('WEBHOOK_SECRET')) {
            if (!preg_match('/^[A-Za-z0-9_\-]{24,128}$/', $secret)) {
                flash('error', 'Das Webhook-Secret muss 24–128 Zeichen (Buchstaben, Ziffern, _ und -) lang sein.');
                redirect('/einstellungen#easybill');
            }
            $changes['WEBHOOK_SECRET'] = $secret;
        }
        if (!empty($_POST['regenerate_cron'])) {
            $changes['CRON_SECRET'] = bin2hex(random_bytes(24));
        }
        if (!$changes) {
            flash('info', 'Keine Änderungen.');
            redirect('/einstellungen#easybill');
        }
        try {
            Env::update($changes);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage() . ' – bitte die Werte im Dateimanager direkt in der .env eintragen.');
            redirect('/einstellungen#easybill');
        }
        $msg = 'Gespeichert: ' . implode(', ', array_keys($changes)) . '.';
        if (isset($changes['WEBHOOK_SECRET'])) {
            $msg .= ' Webhook-URL in easybill aktualisieren!';
        }
        if (isset($changes['CRON_SECRET'])) {
            $msg .= ' Cronjob-URL im Hosting-Panel aktualisieren!';
        }
        flash('success', $msg);
        redirect('/einstellungen#easybill');
    }

    public function testConnection(): void
    {
        $client = EasybillClient::fromEnv();
        if (!$client) {
            flash('error', 'Kein API-Key hinterlegt.');
        } else {
            try {
                $res = $client->ping();
                flash('success', 'Verbindung zu easybill erfolgreich (' . (int) ($res['total'] ?? 0) . ' Kunden im Konto).');
            } catch (\Throwable $e) {
                flash('error', 'Verbindung fehlgeschlagen: ' . $e->getMessage());
            }
        }
        redirect('/einstellungen#easybill');
    }

    public function storeUser(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $error = match (true) {
            $name === '' => 'Bitte einen Namen angeben.',
            !filter_var($email, FILTER_VALIDATE_EMAIL) => 'Bitte eine gültige E-Mail-Adresse angeben.',
            (bool) Database::value('SELECT id FROM users WHERE email = ?', [$email]) => 'Diese E-Mail-Adresse wird bereits verwendet.',
            mb_strlen($password) < 10 => 'Das Passwort muss mindestens 10 Zeichen lang sein.',
            default => null,
        };
        if ($error) {
            flash('error', $error);
            redirect('/einstellungen#benutzer');
        }
        Database::insert('users', ['name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role]);
        flash('success', 'Benutzer „' . $name . '“ angelegt.');
        redirect('/einstellungen#benutzer');
    }

    public function updateUser(int $id): void
    {
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$id]) ?? $this->notFound('Benutzer');
        $action = (string) ($_POST['action'] ?? '');
        $isSelf = $id === Auth::id();
        $admins = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");

        switch ($action) {
            case 'toggle':
                if ($isSelf) {
                    flash('error', 'Du kannst dich nicht selbst deaktivieren.');
                    break;
                }
                if ($user['active'] && $user['role'] === 'admin' && $admins <= 1) {
                    flash('error', 'Der letzte Administrator kann nicht deaktiviert werden.');
                    break;
                }
                Database::run('UPDATE users SET active = 1 - active WHERE id = ?', [$id]);
                flash('success', 'Benutzer ' . ($user['active'] ? 'deaktiviert' : 'aktiviert') . '.');
                break;
            case 'role':
                $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
                if ($user['role'] === 'admin' && $role === 'user' && $admins <= 1) {
                    flash('error', 'Es muss mindestens ein Administrator bleiben.');
                    break;
                }
                Database::run('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
                flash('success', 'Rolle geändert.');
                break;
            case 'password':
                $pw = (string) ($_POST['password'] ?? '');
                if (mb_strlen($pw) < 10) {
                    flash('error', 'Das Passwort muss mindestens 10 Zeichen lang sein.');
                    break;
                }
                Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                flash('success', 'Passwort für „' . $user['name'] . '“ neu gesetzt.');
                break;
            default:
                flash('error', 'Unbekannte Aktion.');
        }
        redirect('/einstellungen#benutzer');
    }

    public function destroyUser(int $id): void
    {
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$id]) ?? $this->notFound('Benutzer');
        if ($id === Auth::id()) {
            flash('error', 'Du kannst dich nicht selbst löschen.');
        } elseif ($user['role'] === 'admin' && (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
            flash('error', 'Der letzte Administrator kann nicht gelöscht werden.');
        } else {
            Database::run('DELETE FROM users WHERE id = ?', [$id]);
            flash('success', 'Benutzer „' . $user['name'] . '“ gelöscht.');
        }
        redirect('/einstellungen#benutzer');
    }
}

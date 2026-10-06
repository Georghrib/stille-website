<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Settings;
use App\Core\View;

final class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        View::render('auth/login', ['pageTitle' => 'Anmelden', 'email' => old('email')], 'layout/guest');
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        // Fehlversuche pro IP (in app_settings) – unabhängig vom Session-Cookie
        $key = 'login_fail_' . hash('sha256', \App\Core\Request::ip());
        $att = json_decode((string) Settings::get($key, ''), true) ?: ['n' => 0, 't' => 0];
        if (time() - $att['t'] >= self::LOCK_SECONDS) {
            $att = ['n' => 0, 't' => 0];
        }
        if ($att['n'] >= self::MAX_ATTEMPTS) {
            flash('error', 'Zu viele Fehlversuche. Bitte in einigen Minuten erneut versuchen.');
            redirect('/login');
        }

        if ($email === '' || $password === '' || !Auth::attempt($email, $password)) {
            Settings::set($key, json_encode(['n' => $att['n'] + 1, 't' => time()]));
            usleep(400_000);
            $_SESSION['_old'] = ['email' => $email];
            flash('error', 'E-Mail-Adresse oder Passwort ist falsch.');
            redirect('/login');
        }

        if ($att['n'] > 0) {
            Settings::set($key, null);
        }
        $target = $_SESSION['intended'] ?? '/';
        unset($_SESSION['intended']);
        redirect(is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : '/');
    }

    public function logout(): void
    {
        Auth::logout();
        \App\Core\Session::start();
        flash('success', 'Du wurdest abgemeldet.');
        redirect('/login');
    }
}

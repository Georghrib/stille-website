<?php
/** @var App\Core\Router $router */

use App\Controllers\AuthController;
use App\Controllers\ContractController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\EasybillController;
use App\Controllers\NoteController;
use App\Controllers\RevenueController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Controllers\TaskController;

// Anmeldung
$router->get('/login', [AuthController::class, 'showLogin'], 'guest');
$router->post('/login', [AuthController::class, 'login'], 'guest');
$router->post('/logout', [AuthController::class, 'logout']);

// Dashboard & Suche
$router->get('/', [DashboardController::class, 'index']);
$router->get('/suche', [SearchController::class, 'index']);

// Kunden
$router->get('/kunden', [CustomerController::class, 'index']);
$router->get('/kunden/neu', [CustomerController::class, 'create']);
$router->post('/kunden', [CustomerController::class, 'store']);
$router->get('/kunden/{id}', [CustomerController::class, 'show']);
$router->get('/kunden/{id}/bearbeiten', [CustomerController::class, 'edit']);
$router->post('/kunden/{id}', [CustomerController::class, 'update']);
$router->post('/kunden/{id}/loeschen', [CustomerController::class, 'destroy']);

// Notizen
$router->post('/kunden/{id}/notizen', [NoteController::class, 'store']);
$router->post('/notizen/{id}/loeschen', [NoteController::class, 'destroy']);

// Verträge
$router->get('/vertraege', [ContractController::class, 'index']);
$router->get('/vertraege/neu', [ContractController::class, 'create']);
$router->post('/vertraege', [ContractController::class, 'store']);
$router->get('/vertraege/{id}', [ContractController::class, 'show']);
$router->get('/vertraege/{id}/bearbeiten', [ContractController::class, 'edit']);
$router->post('/vertraege/{id}', [ContractController::class, 'update']);
$router->post('/vertraege/{id}/kuendigen', [ContractController::class, 'cancel']);
$router->post('/vertraege/{id}/loeschen', [ContractController::class, 'destroy']);

// Aus easybill
$router->get('/easybill', [EasybillController::class, 'index']);
$router->post('/easybill/abgleich', [EasybillController::class, 'sync']);
$router->get('/easybill/{id}', [EasybillController::class, 'show']);
$router->get('/easybill/{id}/pdf', [EasybillController::class, 'pdf']);
$router->post('/easybill/{id}/uebernehmen', [EasybillController::class, 'accept']);
$router->post('/easybill/{id}/umstellen', [EasybillController::class, 'convert']);
$router->post('/easybill/{id}/vorschlag-verwerfen', [EasybillController::class, 'dismissSuggestion']);
$router->post('/easybill/{id}/zuruecksetzen', [EasybillController::class, 'reset']);

// Aufgaben & Termine
$router->get('/aufgaben', [TaskController::class, 'index']);
$router->post('/aufgaben', [TaskController::class, 'store']);
$router->post('/aufgaben/{id}/erledigt', [TaskController::class, 'toggle']);
$router->post('/aufgaben/{id}/loeschen', [TaskController::class, 'destroy']);

// Umsatz
$router->get('/umsatz', [RevenueController::class, 'index']);
$router->get('/umsatz/export', [RevenueController::class, 'export']);

// Einstellungen
$router->get('/einstellungen', [SettingsController::class, 'index']);
$router->post('/einstellungen/passwort', [SettingsController::class, 'password']);
$router->post('/einstellungen/easybill', [SettingsController::class, 'easybill'], 'admin');
$router->post('/einstellungen/easybill/test', [SettingsController::class, 'testConnection'], 'admin');
$router->post('/einstellungen/benutzer', [SettingsController::class, 'storeUser'], 'admin');
$router->post('/einstellungen/benutzer/{id}', [SettingsController::class, 'updateUser'], 'admin');
$router->post('/einstellungen/benutzer/{id}/loeschen', [SettingsController::class, 'destroyUser'], 'admin');

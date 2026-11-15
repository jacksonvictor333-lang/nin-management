<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ApiSettingsController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataQualityController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationLogController;

// =====================================
// AUTHENTICATION (wageni tu)
// =====================================

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// =====================================
// UTHIBITISHO WA KADI (umma, kwa QR code)
// =====================================

Route::get('/verify/card/{person}', [CardVerificationController::class, 'show'])
    ->middleware(['signed:relative', 'throttle:30,1'])
    ->name('card.verify');

// =====================================
// SEHEMU ZINAZOHITAJI KUINGIA
// =====================================

Route::middleware(['auth', 'auth.session', 'active'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route('dashboard'));

    // ---------- Kila mtumiaji (pamoja na Viewer) ----------

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/quick', [SearchController::class, 'quick'])
        ->middleware('throttle:60,1')
        ->name('search.quick');

    Route::get('/verifications', [VerificationLogController::class, 'index'])->name('verifications.index');

    Route::get('/data-quality', [DataQualityController::class, 'index'])->name('data-quality.index');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    // JSON kwa dropdown za maeneo kwenye fomu
    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('/regions/{regionId}/districts', [LocationController::class, 'districts'])->name('districts');
        Route::get('/districts/{districtId}/wards', [LocationController::class, 'wards'])->name('wards');
        Route::get('/wards/{wardId}/villages', [LocationController::class, 'villages'])->name('villages');
    });

    Route::get('/geo', [GeoController::class, 'index'])->name('geo.index');

    // IMPORTANT: Lookup route must come before {person}
    Route::get('/people/lookup/{nin}', [PersonController::class, 'lookup'])
        ->middleware('throttle:30,1')
        ->name('people.lookup');

    Route::get('/people', [PersonController::class, 'index'])->name('people.index');

    // ---------- Administrator + Registrar: kusajili na kuhariri ----------

    Route::middleware('role:Administrator,Registrar')->group(function () {
        Route::get('/people/create', [PersonController::class, 'create'])->name('people.create');
        Route::post('/people', [PersonController::class, 'store'])->name('people.store');
        Route::get('/people/import', [ImportController::class, 'index'])->name('people.import');
        Route::get('/people/import/template', [ImportController::class, 'template'])->name('people.import.template');
        Route::post('/people/import', [ImportController::class, 'preview'])->middleware('throttle:20,1')->name('people.import.preview');
        Route::post('/people/import/confirm', [ImportController::class, 'confirm'])->name('people.import.confirm');

        Route::get('/people/duplicates/check', [PersonController::class, 'duplicates'])
            ->middleware('throttle:60,1')
            ->name('people.duplicates');

        Route::get('/people/{person}/edit', [PersonController::class, 'edit'])->name('people.edit');
        Route::put('/people/{person}', [PersonController::class, 'update'])->name('people.update');

        Route::patch('/people/{person}/photo', [PersonController::class, 'updatePhoto'])->name('people.photo.update');
        Route::delete('/people/{person}/photo', [PersonController::class, 'destroyPhoto'])->name('people.photo.destroy');

        Route::get('/people/{person}/card', [CardController::class, 'show'])->name('cards.show');
        Route::get('/cards/print', [CardController::class, 'bulk'])->name('cards.print');

        Route::patch('/people/{person}/signature', [PersonController::class, 'updateSignature'])->name('people.signature.update');
        Route::delete('/people/{person}/signature', [PersonController::class, 'destroySignature'])->name('people.signature.destroy');
    });

    Route::get('/people/{person}', [PersonController::class, 'show'])->name('people.show');

    // ---------- Administrator pekee ----------

    Route::middleware('role:Administrator')->group(function () {
        Route::post('/data-quality/fix/regions', [DataQualityController::class, 'fixRegions'])->name('data-quality.fix-regions');
        Route::post('/data-quality/fix/names', [DataQualityController::class, 'fixNames'])->name('data-quality.fix-names');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::post('/geo/wards/{ward}/villages', [GeoController::class, 'storeVillage'])->name('geo.villages.store');
        Route::delete('/geo/villages/{village}', [GeoController::class, 'destroyVillage'])->name('geo.villages.destroy');

        // Archive (soft delete): kuhifadhi na kurudisha rekodi.
        Route::delete('/people/{person}', [ArchiveController::class, 'destroy'])->name('people.destroy');
        Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
        Route::patch('/archive/{id}/restore', [ArchiveController::class, 'restore'])->whereNumber('id')->name('archive.restore');

        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:5,1')->name('backups.store');
        Route::get('/backups/{name}', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{name}', [BackupController::class, 'destroy'])->name('backups.destroy');

        Route::get('/settings/api', [ApiSettingsController::class, 'index'])->name('settings.api');
        Route::post('/settings/api/rotate', [ApiSettingsController::class, 'rotate'])->name('settings.api.rotate');
    });
});

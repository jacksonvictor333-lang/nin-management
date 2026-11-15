<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Unda (au sasisha) akaunti ya msimamizi wa mfumo.
 * Matumizi: php artisan nin:create-admin
 */
Artisan::command('nin:create-admin {email?} {--name=Administrator}', function () {
    $email = $this->argument('email') ?: $this->ask('Email');

    $validator = \Illuminate\Support\Facades\Validator::make(
        ['email' => $email],
        ['email' => ['required', 'email', 'max:255']]
    );

    if ($validator->fails()) {
        $this->error($validator->errors()->first('email'));

        return 1;
    }

    $password = $this->secret('Password (min 8 characters)');

    if (! is_string($password) || mb_strlen($password) < 8) {
        $this->error('Password must be at least 8 characters.');

        return 1;
    }

    if ($password !== $this->secret('Confirm password')) {
        $this->error('Passwords do not match.');

        return 1;
    }

    $user = \App\Models\User::updateOrCreate(
        ['email' => $email],
        ['name' => $this->option('name'), 'password' => $password]
    );

    $this->info("Admin account ready: {$user->email}");

    return 0;
})->purpose('Create or update an administrator account');

/*
 * Backup ya database na picha/sahihi (storage/app/backups/*.tar.gz).
 * Inajiendesha kila siku saa 7:00 usiku ikiwa scheduler imewashwa:
 *   Windows Task Scheduler -> kila dakika: php artisan schedule:run
 */
Artisan::command('nin:backup', function () {
    $this->info('Creating backup…');
    $backup = app(\App\Support\BackupManager::class)->create('console');
    $this->info(sprintf('Backup ready: %s (%s KB)', $backup['name'], number_format($backup['size'] / 1024)));

    return 0;
})->purpose('Back up the database, photos and signatures');

\Illuminate\Support\Facades\Schedule::command('nin:backup')->dailyAt('01:00')->withoutOverlapping();

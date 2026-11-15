<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\NinVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiSettingsController extends Controller
{
    public function index()
    {
        $key = (string) config('services.nin.api_key');

        $masked = $key === ''
            ? null
            : str_repeat('•', max(strlen($key) - 4, 8)) . substr($key, -4);

        $since = now()->subDay();

        $stats = [
            'today' => NinVerification::where('created_at', '>=', $since)->count(),
            'passed' => NinVerification::where('created_at', '>=', $since)->where('status', 'passed')->count(),
            'failed' => NinVerification::where('created_at', '>=', $since)->where('status', 'failed')->count(),
            'last' => NinVerification::latest('id')->value('created_at'),
        ];

        $baseUrl = rtrim((string) (config('services.nin.base_url') ?: config('app.url')), '/');

        $lastRotation = ActivityLog::where('action', 'api.key_rotated')->latest('id')->first();

        return view('settings.api', compact('masked', 'stats', 'baseUrl', 'lastRotation'));
    }

    /**
     * Tengeneza API key mpya na uiandike kwenye .env.
     * Inahitaji password ya mtumiaji ili kuthibitisha.
     */
    public function rotate(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $envPath = base_path('.env');

        if (! is_writable($envPath)) {
            return back()->with('error', 'The .env file is not writable. Update NIN_API_KEY manually.');
        }

        $newKey = 'nin_' . Str::random(44);
        $env = file_get_contents($envPath);
        $line = 'NIN_API_KEY="' . $newKey . '"';

        $env = preg_match('/^NIN_API_KEY=.*$/m', $env)
            ? preg_replace('/^NIN_API_KEY=.*$/m', $line, $env)
            : rtrim($env) . PHP_EOL . $line . PHP_EOL;

        file_put_contents($envPath, $env, LOCK_EX);

        if (app()->configurationIsCached()) {
            Artisan::call('config:cache');
        }

        ActivityLog::record('api.key_rotated', 'Rotated the NIN verification API key');

        // Key mpya inaonyeshwa MARA MOJA tu.
        return back()->with('new_api_key', $newKey)
            ->with('success', 'New API key generated. Update every connected system now: the old key no longer works.');
    }
}

@extends('layouts.app')

@section('title', 'API Settings')
@section('page_title', 'API Settings')

@php
    $endpoints = [
        ['POST', '/api/nin/verification/start', 'Start a check with a 20-digit NIN; returns a session token and the first question.', '{ "nin": "…20 digits…" }'],
        ['POST', '/api/nin/verification/answer', 'Submit an answer; returns the next question or the final result.', '{ "session_token": "…", "answer": "…" }'],
        ['POST', '/api/nin/verification/person', 'After a pass, returns the person\'s details (token valid 10 minutes).', '{ "session_token": "…" }'],
    ];
@endphp

@push('styles')
<style>
    .key-box { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-radius: 14px; background: #0f172a; color: #e2e8f0; }
    .key-box code { font-family: ui-monospace, Menlo, monospace; font-size: 14px; letter-spacing: .5px; flex: 1; word-break: break-all; }
    .key-box .ui-btn { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.2); color: #fff; }
    .new-key { border: 2px solid #16a34a; border-radius: 16px; padding: 18px; background: #f0fdf4; }
    .new-key h4 { margin: 0 0 4px; color: #14532d; font-size: 15px; }
    .new-key p { margin: 0 0 12px; font-size: 13px; color: #166534; }
    .ep { border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; }
    .ep + .ep { margin-top: 10px; }
    .ep-top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ep-top .m { font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 6px; background: #dbeafe; color: #1d4ed8; }
    .ep p { margin: 8px 0; font-size: 13px; color: var(--text); }
    .ep pre { margin: 0; background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 9px 12px; font-size: 12px; overflow-x: auto; }
    .rules { margin: 0; padding-left: 18px; font-size: 13px; color: var(--text); line-height: 1.8; }
    .danger-zone { border: 1px solid #fecaca; background: #fff; border-radius: 18px; padding: 20px 22px; }
    .danger-zone h3 { margin: 0 0 4px; font-size: 15px; color: #991b1b; }
    .danger-zone p { margin: 0 0 14px; font-size: 13px; color: var(--muted); }
    .danger-zone form { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-start; }
    .danger-zone .ui-field { flex: 1; min-width: 220px; }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>API settings</h2>
            <p>The verification API lets other systems confirm a person's identity with knowledge-based questions.</p>
        </div>
        <div class="ui-head-actions">
            <a href="{{ route('verifications.index') }}" class="ui-btn"><i class="bi bi-shield-check"></i> View verifications</a>
        </div>
    </div>

    @if (session('new_api_key'))
        <section class="new-key">
            <h4><i class="bi bi-key-fill"></i> Your new API key</h4>
            <p>Copy it now. For security it will <b>not be shown again</b>. Update every connected system with this key.</p>
            <div class="key-box">
                <code id="newKey">{{ session('new_api_key') }}</code>
                <button type="button" class="ui-btn sm" onclick="navigator.clipboard.writeText(document.getElementById('newKey').textContent).then(() => this.innerHTML = '<i class=\'bi bi-check2\'></i> Copied')"><i class="bi bi-copy"></i> Copy</button>
            </div>
        </section>
    @endif

    <div class="ui-stats">
        <div class="ui-stat"><span class="ico b"><i class="bi bi-arrow-repeat"></i></span><div><small>Checks (24 h)</small><b>{{ number_format($stats['today']) }}</b></div></div>
        <div class="ui-stat"><span class="ico g"><i class="bi bi-patch-check"></i></span><div><small>Passed (24 h)</small><b>{{ number_format($stats['passed']) }}</b></div></div>
        <div class="ui-stat"><span class="ico r"><i class="bi bi-x-octagon"></i></span><div><small>Failed (24 h)</small><b>{{ number_format($stats['failed']) }}</b></div></div>
        <div class="ui-stat"><span class="ico"><i class="bi bi-clock"></i></span><div><small>Last request</small><b style="font-size:16px">{{ $stats['last'] ? \Carbon\Carbon::parse($stats['last'])->diffForHumans() : 'Never' }}</b></div></div>
    </div>

    <div class="ui-grid-3">
        <div style="display:flex;flex-direction:column;gap:18px;min-width:0">
            <section class="ui-card">
                <div class="ui-card-head"><span class="ico"><i class="bi bi-key"></i></span><div><h3>Authentication</h3><p>Send the key in the <code>X-NIN-API-KEY</code> header on every request.</p></div></div>
                <div class="key-box">
                    <i class="bi bi-lock-fill" style="color:#4ade80"></i>
                    <code>{{ $masked ?? 'No API key configured: the API rejects every request.' }}</code>
                </div>
                <p class="ui-help" style="margin:10px 0 0">
                    Base URL: <span class="ui-mono">{{ $baseUrl }}</span>
                    @if ($lastRotation) · Last rotated {{ $lastRotation->created_at->diffForHumans() }} by {{ $lastRotation->user?->name ?? 'unknown' }} @endif
                </p>
            </section>

            <section class="ui-card">
                <div class="ui-card-head"><span class="ico"><i class="bi bi-braces"></i></span><div><h3>Endpoints</h3><p>All requests: JSON body, <code>Accept: application/json</code>, max 10 per minute.</p></div></div>
                @foreach ($endpoints as [$method, $path, $desc, $body])
                    <div class="ep">
                        <div class="ep-top"><span class="m">{{ $method }}</span><span class="ui-mono">{{ $path }}</span></div>
                        <p>{{ $desc }}</p>
                        <pre>{{ $body }}</pre>
                    </div>
                @endforeach
            </section>
        </div>

        <div style="display:flex;flex-direction:column;gap:18px">
            <section class="ui-card">
                <div class="ui-card-head"><span class="ico"><i class="bi bi-list-ol"></i></span><div><h3>Verification rules</h3><p>How a check is decided</p></div></div>
                <ul class="rules">
                    <li>Only <b>Active</b> records can be verified.</li>
                    <li>Stage 1: 3 questions. <b>3/3</b> passes; <b>0–1</b> fails.</li>
                    <li>2/3 continues to stage 2 (2 more questions); <b>4/5</b> passes.</li>
                    <li>Sessions expire after <b>10 minutes</b>.</li>
                    <li><b>3 failures in 1 hour</b> lock that NIN for an hour.</li>
                </ul>
            </section>

            <section class="danger-zone">
                <h3><i class="bi bi-exclamation-triangle"></i> Rotate API key</h3>
                <p>Generates a new key and invalidates the current one immediately. Connected systems stop working until they are updated.</p>
                <form method="POST" action="{{ route('settings.api.rotate') }}" onsubmit="return confirm('Rotate the API key now? The old key stops working immediately.')">
                    @csrf
                    <div class="ui-field">
                        <input class="ui-input {{ $errors->has('password') ? 'is-invalid' : '' }}" type="password" name="password" placeholder="Confirm with your password" autocomplete="current-password" required>
                        @error('password')<span class="ui-err">{{ $message }}</span>@enderror
                    </div>
                    <button class="ui-btn danger" style="height:42px"><i class="bi bi-arrow-repeat"></i> Rotate key</button>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection

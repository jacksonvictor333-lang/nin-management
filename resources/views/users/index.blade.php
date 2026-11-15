@extends('layouts.app')

@section('title', 'Users')
@section('page_title', 'Users')

@php
    $roleInfo = [
        'Administrator' => ['violet', 'Full access, including users and API settings'],
        'Registrar' => ['ok', 'Register persons and submit them for approval'],
        'Approver' => ['info', 'Review, approve, return or reject registrations'],
        'Viewer' => ['', 'View records and reports only'],
    ];
    $me = auth()->user();

    // Fungua dialog husika tena baada ya kosa la validation.
    $openDialog = $errors->getBag('create')->any() ? 'createUser' : null;
    foreach ($users as $u) {
        if ($errors->getBag('edit' . $u->id)->any()) { $openDialog = 'editUser' . $u->id; }
    }
@endphp

@push('styles')
<style>
    .roles { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    .role-card { border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; background: #fff; }
    .role-card b { display: block; color: var(--ink); font-size: 14px; margin: 6px 0 2px; }
    .role-card small { color: var(--muted); font-size: 12.5px; }
    .u-avatar { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-weight: 800; font-size: 14px; background: #dcfce7; color: #15803d; flex-shrink: 0; }
    .u-avatar.off { background: #f1f5f9; color: #94a3b8; }
    tr.inactive td { opacity: .6; }
    .row-actions { display: flex; gap: 6px; justify-content: flex-end; }
    @media (max-width: 800px) { .roles { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="ui-page">

    <div class="ui-head">
        <div>
            <h2>Users</h2>
            <p>People who can sign in to the NIN Management System.</p>
        </div>
        <div class="ui-head-actions">
            <button type="button" class="ui-btn primary" onclick="document.getElementById('createUser').showModal()">
                <i class="bi bi-person-plus"></i> Add user
            </button>
        </div>
    </div>

    <div class="roles">
        @foreach ($roleInfo as $role => [$cls, $desc])
            <div class="role-card">
                <span class="ui-pill {{ $cls }}">{{ $users->where('role', $role)->count() }} {{ Str::plural('user', $users->where('role', $role)->count()) }}</span>
                <b>{{ $role }}</b>
                <small>{{ $desc }}</small>
            </div>
        @endforeach
    </div>

    <section class="ui-card flush">
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last sign-in</th><th>Last activity</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr class="{{ $u->is_active ? '' : 'inactive' }}">
                            <td>
                                <div class="ui-who">
                                    <span class="u-avatar {{ $u->is_active ? '' : 'off' }}">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                                    <span>
                                        <b>{{ $u->name }} @if ($u->is($me))<span class="ui-pill info" style="font-size:10.5px;padding:1px 7px">You</span>@endif</b>
                                        <small>{{ $u->email }}</small>
                                    </span>
                                </div>
                            </td>
                            <td><span class="ui-pill {{ $roleInfo[$u->role][0] ?? '' }}">{{ $u->role }}</span></td>
                            <td>
                                <span class="ui-pill {{ $u->is_active ? 'ok' : 'bad' }}">
                                    <i class="bi bi-{{ $u->is_active ? 'check-circle-fill' : 'slash-circle' }}"></i>
                                    {{ $u->is_active ? 'Active' : 'Deactivated' }}
                                </span>
                            </td>
                            <td title="{{ $u->last_login_at?->format('d M Y H:i') }}">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td>{{ isset($lastActions[$u->id]) ? \Carbon\Carbon::parse($lastActions[$u->id])->diffForHumans() : '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="ui-btn sm icon" title="Edit" onclick="document.getElementById('editUser{{ $u->id }}').showModal()"><i class="bi bi-pencil"></i></button>
                                    @unless ($u->is($me))
                                        <form method="POST" action="{{ route('users.toggle', $u) }}">
                                            @csrf @method('PATCH')
                                            <button class="ui-btn sm icon" title="{{ $u->is_active ? 'Deactivate' : 'Activate' }}"><i class="bi bi-{{ $u->is_active ? 'pause-circle' : 'play-circle' }}"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Delete {{ addslashes($u->name) }}? Their past activity stays in the log.')">
                                            @csrf @method('DELETE')
                                            <button class="ui-btn sm icon danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>

{{-- ================= DIALOG: ADD USER ================= --}}
<dialog class="ui-dialog" id="createUser">
    <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="ui-dialog-head">
            <h3><i class="bi bi-person-plus" style="color:var(--primary)"></i> Add user</h3>
            <button type="button" class="ui-btn sm icon" onclick="this.closest('dialog').close()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ui-dialog-body">
            @php $b = $errors->getBag('create'); @endphp
            <div class="ui-form-grid">
                <div class="ui-field full">
                    <label>Full name</label>
                    <input class="ui-input {{ $b->has('name') ? 'is-invalid' : '' }}" name="name" value="{{ $b->any() ? old('name') : '' }}" required>
                    @if ($b->has('name'))<span class="ui-err">{{ $b->first('name') }}</span>@endif
                </div>
                <div class="ui-field">
                    <label>Email</label>
                    <input class="ui-input {{ $b->has('email') ? 'is-invalid' : '' }}" type="email" name="email" value="{{ $b->any() ? old('email') : '' }}" required>
                    @if ($b->has('email'))<span class="ui-err">{{ $b->first('email') }}</span>@endif
                </div>
                <div class="ui-field">
                    <label>Role</label>
                    <select class="ui-select" name="role">
                        @foreach ($roles as $r)<option @selected(($b->any() ? old('role') : 'Registrar') === $r)>{{ $r }}</option>@endforeach
                    </select>
                </div>
                <div class="ui-field">
                    <label>Password</label>
                    <input class="ui-input {{ $b->has('password') ? 'is-invalid' : '' }}" type="password" name="password" autocomplete="new-password" required>
                    @if ($b->has('password'))<span class="ui-err">{{ $b->first('password') }}</span>@endif
                </div>
                <div class="ui-field">
                    <label>Confirm password</label>
                    <input class="ui-input" type="password" name="password_confirmation" autocomplete="new-password" required>
                </div>
                <span class="ui-help full">At least 8 characters, with letters and numbers.</span>
            </div>
        </div>
        <div class="ui-dialog-foot">
            <button type="button" class="ui-btn" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="ui-btn primary"><i class="bi bi-check2"></i> Create user</button>
        </div>
    </form>
</dialog>

{{-- ================= DIALOGS: EDIT USER ================= --}}
@foreach ($users as $u)
    @php $b = $errors->getBag('edit' . $u->id); @endphp
    <dialog class="ui-dialog" id="editUser{{ $u->id }}">
        <form method="POST" action="{{ route('users.update', $u) }}">
            @csrf @method('PUT')
            <div class="ui-dialog-head">
                <h3><i class="bi bi-pencil" style="color:var(--primary)"></i> Edit {{ $u->name }}</h3>
                <button type="button" class="ui-btn sm icon" onclick="this.closest('dialog').close()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="ui-dialog-body">
                <div class="ui-form-grid">
                    <div class="ui-field full">
                        <label>Full name</label>
                        <input class="ui-input {{ $b->has('name') ? 'is-invalid' : '' }}" name="name" value="{{ $b->any() ? old('name') : $u->name }}" required>
                        @if ($b->has('name'))<span class="ui-err">{{ $b->first('name') }}</span>@endif
                    </div>
                    <div class="ui-field">
                        <label>Email</label>
                        <input class="ui-input {{ $b->has('email') ? 'is-invalid' : '' }}" type="email" name="email" value="{{ $b->any() ? old('email') : $u->email }}" required>
                        @if ($b->has('email'))<span class="ui-err">{{ $b->first('email') }}</span>@endif
                    </div>
                    <div class="ui-field">
                        <label>Role</label>
                        <select class="ui-select {{ $b->has('role') ? 'is-invalid' : '' }}" name="role">
                            @foreach ($roles as $r)<option @selected(($b->any() ? old('role') : $u->role) === $r)>{{ $r }}</option>@endforeach
                        </select>
                        @if ($b->has('role'))<span class="ui-err">{{ $b->first('role') }}</span>@endif
                    </div>
                    <div class="ui-field">
                        <label>New password <span style="color:var(--muted);font-weight:500">(optional)</span></label>
                        <input class="ui-input {{ $b->has('password') ? 'is-invalid' : '' }}" type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep">
                        @if ($b->has('password'))<span class="ui-err">{{ $b->first('password') }}</span>@endif
                    </div>
                    <div class="ui-field">
                        <label>Confirm new password</label>
                        <input class="ui-input" type="password" name="password_confirmation" autocomplete="new-password">
                    </div>
                </div>
            </div>
            <div class="ui-dialog-foot">
                <button type="button" class="ui-btn" onclick="this.closest('dialog').close()">Cancel</button>
                <button class="ui-btn primary"><i class="bi bi-check2"></i> Save</button>
            </div>
        </form>
    </dialog>
@endforeach
@endsection

@push('scripts')
@if ($openDialog)
<script>document.addEventListener('DOMContentLoaded', () => document.getElementById(@json($openDialog)).showModal());</script>
@endif
@endpush

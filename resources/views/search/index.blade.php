@extends('layouts.app')

@section('title', 'Search NIN')
@section('page_title', 'Search NIN')

@push('styles')
<style>
    .sx-hero {
        background:
            radial-gradient(circle at 85% -30%, rgba(255,255,255,.2), transparent 45%),
            linear-gradient(120deg, #14532d, #15803d 55%, #16a34a);
        border-radius: 20px; padding: 34px 30px; color: #fff; text-align: center;
    }
    .sx-hero h2 { margin: 0 0 6px; font-size: 24px; font-weight: 800; }
    .sx-hero p { margin: 0 0 22px; opacity: .85; font-size: 14px; }
    .sx-form { display: flex; gap: 10px; max-width: 640px; margin: 0 auto; }
    .sx-form .box { position: relative; flex: 1; }
    .sx-form .box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 18px; }
    .sx-form input {
        width: 100%; height: 54px; border-radius: 14px; border: 0; padding: 0 16px 0 46px;
        font: inherit; font-size: 16px; color: #0f172a; outline: none; letter-spacing: .3px;
    }
    .sx-form input:focus { box-shadow: 0 0 0 4px rgba(134,239,172,.6); }
    .sx-form button { height: 54px; padding: 0 24px; border-radius: 14px; border: 0; background: #052e16; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
    .sx-tips { margin-top: 12px; font-size: 12.5px; opacity: .8; }

    .sx-exact { display: grid; grid-template-columns: auto 1fr auto; gap: 22px; align-items: center; }
    .sx-exact .name { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 6px; }
    .sx-exact .tags { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
    .sx-kv { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .sx-kv div { background: #f8fafc; border-radius: 12px; padding: 10px 12px; }
    .sx-kv small { display: block; font-size: 11.5px; color: #94a3b8; }
    .sx-kv b { font-size: 13.5px; color: #0f172a; }
    .sx-found { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #15803d; margin-bottom: 8px; }

    @media (max-width: 900px) {
        .sx-exact { grid-template-columns: 1fr; text-align: left; }
        .sx-kv { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sx-form { flex-direction: column; }
    }
</style>
@endpush

@section('content')
<div class="ui-page">

    <section class="sx-hero">
        <h2><i class="bi bi-fingerprint"></i> Find a person</h2>
        <p>Enter a full 20-digit NIN for an exact match, or search by name, phone or part of a NIN.</p>
        <form class="sx-form" method="GET" action="{{ route('search') }}">
            <div class="box">
                <i class="bi bi-search"></i>
                <input type="search" name="q" id="search" value="{{ $q }}" placeholder="e.g. 19990107442422833333 or Amina Juma" autofocus autocomplete="off">
            </div>
            <button type="submit">Search</button>
        </form>
        <div class="sx-tips">Spaces and dashes in a NIN are ignored.</div>
    </section>

    @if ($q !== '')
        @if ($exact)
            <section class="ui-card">
                <div class="sx-found"><i class="bi bi-patch-check-fill"></i> Exact NIN match</div>
                <div class="sx-exact">
                    <x-avatar :person="$exact" :size="104" :radius="26" />
                    <div>
                        <h3 class="name">{{ $exact->full_name }}</h3>
                        <div class="tags">
                            <span class="ui-pill ui-mono">{{ $exact->nin }}</span>
                            <span class="ui-pill {{ $exact->status === 'Active' ? 'ok' : 'bad' }}">{{ $exact->status ?? 'Active' }}</span>
                            <span class="ui-pill">{{ $exact->gender }}</span>
                        </div>
                        <div class="sx-kv">
                            <div><small>Date of birth</small><b>{{ $exact->date_of_birth?->format('d M Y') ?? '—' }}</b></div>
                            <div><small>Age</small><b>{{ $exact->date_of_birth ? $exact->date_of_birth->age . ' years' : '—' }}</b></div>
                            <div><small>Lives in</small><b>{{ $exact->region ?: '—' }}</b></div>
                            <div><small>Phone</small><b>{{ $exact->phone ?: '—' }}</b></div>
                        </div>
                    </div>
                    <a href="{{ route('people.show', $exact) }}" class="ui-btn primary">Open profile <i class="bi bi-arrow-right"></i></a>
                </div>
            </section>
        @else
            <section class="ui-card flush">
                <div class="ui-card-head">
                    <span class="ico"><i class="bi bi-list-ul"></i></span>
                    <div>
                        <h3>{{ $results->count() }} {{ Str::plural('result', $results->count()) }} for "{{ $q }}"</h3>
                        <p>{{ $results->count() >= 30 ? 'Showing the first 30. Refine your search for fewer results.' : 'Matching names, phone numbers and NINs' }}</p>
                    </div>
                </div>

                @if ($results->isEmpty())
                    <div class="ui-empty"><i class="bi bi-person-x"></i>No person matches "{{ $q }}".</div>
                @else
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead><tr><th>Person</th><th>NIN</th><th>Gender</th><th>Region</th><th>Phone</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($results as $p)
                                    <tr>
                                        <td>
                                            <div class="ui-who">
                                                <x-avatar :person="$p" :size="36" />
                                                <span><b>{{ $p->full_name }}</b><small>{{ $p->date_of_birth?->format('d M Y') }}</small></span>
                                            </div>
                                        </td>
                                        <td><span class="ui-mono">{{ $p->nin }}</span></td>
                                        <td>{{ $p->gender }}</td>
                                        <td>{{ $p->region ?: '—' }}</td>
                                        <td>{{ $p->phone ?: '—' }}</td>
                                        <td class="num"><a href="{{ route('people.show', $p) }}" class="ui-btn sm">View</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @endif
</div>
@endsection

@extends('layouts.app')

@section('title', $heading)

@section('content')
    <h1 class="page-title">{{ $heading }}</h1>

    <div class="card">
        <div class="card__body">
            <p><strong>{{ $user->name }}</strong> · <span dir="ltr">{{ \App\Support\Digits::toPersian($user->mobile) }}</span></p>
            <p>{{ __('users.temporary_password') }}:</p>
            <p><code class="temp-password" dir="ltr">{{ $password }}</code></p>
            <p class="flash flash--warning" role="note">{{ __('users.password_once') }}</p>
        </div>
    </div>

    <p><a href="{{ route('admin.users.index') }}">{{ __('users.to_list') }}</a></p>
@endsection

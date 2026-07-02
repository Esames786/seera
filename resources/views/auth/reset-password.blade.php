@extends('layouts.auth')

@section('title', 'Reset Password')
@section('heading', 'Reset Password')
@section('subheading', 'Choose a new password for your account')

@section('content')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token ?? request('token') }}"/>

        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" class="input" placeholder="you@example.com" value="{{ old('email', $email ?? request('email')) }}" required/>

        <div style="height:12px"></div>

        <label for="password">New Password</label>
        <input id="password" name="password" type="password" class="input" placeholder="••••••••" required/>

        <div style="height:12px"></div>

        <label for="password_confirmation">Confirm New Password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="input" placeholder="••••••••" required/>

        <div style="height:16px"></div>

        <button type="submit" class="btn primary block">Reset Password</button>
    </form>

    <div style="text-align:center;margin-top:18px" class="small">
        <a class="auth-link" href="{{ route('login') }}">Back to Login</a>
    </div>
@endsection

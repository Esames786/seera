@extends('layouts.auth')

@section('title', 'Login')
@section('heading', 'Login')
@section('subheading', 'Sign in to access your admin workspace')

@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" class="input" placeholder="admin@example.com" value="{{ old('email') }}" required autofocus/>

        <div style="height:12px"></div>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" class="input" placeholder="••••••••" required/>

        <div class="auth-actions">
            <label style="display:flex;gap:7px;align-items:center;margin:0;font-weight:400">
                <input type="checkbox" name="remember" value="1"/> Remember me
            </label>
            <a class="auth-link" href="{{ route('password.request') }}">Forgot password?</a>
        </div>

        <button type="submit" class="btn primary block">Sign In</button>
    </form>

    <div style="text-align:center;margin-top:18px" class="small">
        Need an account? <a class="auth-link" href="#">Contact Super Admin</a>
    </div>
@endsection

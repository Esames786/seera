@extends('layouts.auth')

@section('title', 'Forgot Password')
@section('heading', 'Forgot Password')
@section('subheading', 'Enter your email address and we will send you a reset link')

@section('content')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" class="input" placeholder="you@example.com" value="{{ old('email') }}" required autofocus/>

        <div style="height:16px"></div>

        <button type="submit" class="btn primary block">Send Reset Link</button>
    </form>

    <div style="text-align:center;margin-top:18px" class="small">
        Remembered your password? <a class="auth-link" href="{{ route('login') }}">Back to Login</a>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'Set Your Password')
@section('breadcrumb', 'Security / Set Your Password')

@section('content')
    <x-admin.page-header title="Welcome to Seera ERP" description="Your account was created with a shared default password. Choose your own to continue."/>

    <div class="modal-overlay open" style="position:fixed">
        <div class="modal-card" style="max-width:520px">
            <div class="modal-head">
                <span>Set Your Password</span>
            </div>

            <form method="POST" action="{{ route('admin.password.change.update') }}">
                @csrf

                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert">{{ $errors->first() }}</div>
                    @endif

                    <p class="small" style="margin-top:0">
                        Signed in as <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }}).
                        You cannot use the panel until this is done.
                    </p>

                    <div style="margin-bottom:12px">
                        <label for="current_password">Current Password *</label>
                        <input id="current_password" name="current_password" type="password" class="input" required autofocus autocomplete="current-password"/>
                    </div>

                    <div style="margin-bottom:12px">
                        <label for="password">New Password *</label>
                        <input id="password" name="password" type="password" class="input" required autocomplete="new-password"/>
                        <div class="small">At least 8 characters, and different from your current password.</div>
                    </div>

                    <div>
                        <label for="password_confirmation">Confirm New Password *</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password"/>
                    </div>
                </div>

                <div class="modal-foot">
                    <button type="submit" class="btn primary">Save Password and Continue</button>
                </div>
            </form>

            <div class="modal-foot" style="border-top:0;padding-top:0">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn outline">Sign out instead</button>
                </form>
            </div>
        </div>
    </div>
@endsection

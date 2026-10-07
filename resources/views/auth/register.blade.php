<x-layout :title="__('Create account')" main-class="auth">
    <div class="auth-pitch">
        <h1>{{ __('Save every tax scenario.') }}</h1>
        <p>{{ __('An account keeps your calculations in one place. Anything you entered before signing up stays in your browser and comes back after.') }}</p>
        <ul class="auth-points">
            <li><i>{{ \App\Support\Money::digits('1') }}</i><div><b>{{ __('Free and private') }}</b>{{ __('Your data stays on this server. Nothing is shared.') }}</div></li>
            <li><i>{{ \App\Support\Money::digits('2') }}</i><div><b>{{ __('Compare offers and years') }}</b>{{ __('Save an increment, a job offer or next year’s plan and put them side by side.') }}</div></li>
        </ul>
    </div>

    <div class="panel auth-card">
        <h2>{{ __('Create account') }}</h2>
        <p>{{ __('Takes less than a minute.') }}</p>
        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf
            <div class="field">
                <label class="label" for="name">{{ __('Name') }}</label>
                <input id="name" name="name" class="input" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="email">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" autocomplete="email" required>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password">{{ __('Password') }} <small>{{ __('8+ characters, letters and numbers') }}</small></label>
                <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password_confirmation">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">{{ __('Create account') }}</button>
            </div>
        </form>
        <p class="auth-foot">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a></p>
    </div>
</x-layout>

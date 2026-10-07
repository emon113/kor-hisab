<x-layout :title="__('Sign in')" main-class="auth">
    <div class="auth-pitch">
        <h1>{{ __('Your tax, worked out and kept.') }}</h1>
        <p>{{ __('Sign in to reopen saved calculations, compare years and keep your own salary split.') }}</p>
        <ul class="auth-points">
            <li><i>{{ \App\Support\Money::digits('1') }}</i><div><b>{{ __('Live calculation') }}</b>{{ __('Slabs, rebate and minimum tax under the Finance Act 2026.') }}</div></li>
            <li><i>{{ \App\Support\Money::digits('2') }}</i><div><b>{{ __('Know your next move') }}</b>{{ __('How much to invest, when the next slab starts, what a raise really adds.') }}</div></li>
            <li><i>{{ \App\Support\Money::digits('3') }}</i><div><b>{{ __('Saved and comparable') }}</b>{{ __('Keep each scenario and see the difference between any two.') }}</div></li>
        </ul>
    </div>

    <div class="panel auth-card">
        <h2>{{ __('Sign in') }}</h2>
        <p>{{ __('Welcome back.') }}</p>
        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <div class="field">
                <label class="label" for="email">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="check" style="margin-top:14px"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('Keep me signed in on this device') }}</label>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">{{ __('Sign in') }}</button>
            </div>
        </form>
        <p class="auth-foot">{{ __('New here?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a></p>
    </div>
</x-layout>

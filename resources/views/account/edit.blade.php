<x-layout :title="__('Account')" main-class="page page-narrow">
    <div class="page-head">
        <div>
            <h1>{{ __('Account') }}</h1>
            <p>{{ __('Update your details or change your password.') }}</p>
        </div>
    </div>

    <div class="split split-even">
        <form method="POST" action="{{ route('account.update') }}" class="panel auth-card">
            @csrf @method('PUT')
            <h2>{{ __('Profile') }}</h2>
            <p>{{ __('Shown in the account menu.') }}</p>
            <div class="field">
                <label class="label" for="name">{{ __('Name') }}</label>
                <input id="name" name="name" class="input" value="{{ old('name', $user->name) }}" required>
                @error('name', 'profile') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="email">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required>
                @error('email', 'profile') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">{{ __('Save profile') }}</button></div>
        </form>

        <form method="POST" action="{{ route('account.password') }}" class="panel auth-card">
            @csrf @method('PUT')
            <h2>{{ __('Password') }}</h2>
            <p>{{ __('Use 8 or more characters with letters and numbers.') }}</p>
            <div class="field">
                <label class="label" for="current_password">{{ __('Current password') }}</label>
                <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password" required>
                @error('current_password', 'password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                @error('password', 'password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">{{ __('Change password') }}</button></div>
        </form>
    </div>
</x-layout>

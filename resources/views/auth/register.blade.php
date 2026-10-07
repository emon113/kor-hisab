<x-layout title="Create account" main-class="auth">
    <div class="auth-pitch">
        <h1>Save every tax scenario.</h1>
        <p>An account keeps your calculations in one place. Anything you entered before signing up stays in your browser and comes back after.</p>
        <ul class="auth-points">
            <li><i>1</i><div><b>Free and private</b>Your data stays on this server. Nothing is shared.</div></li>
            <li><i>2</i><div><b>Compare offers and years</b>Save an increment, a job offer or next year’s plan and put them side by side.</div></li>
        </ul>
    </div>

    <div class="panel auth-card">
        <h2>Create account</h2>
        <p>Takes less than a minute.</p>
        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf
            <div class="field">
                <label class="label" for="name">Name</label>
                <input id="name" name="name" class="input" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" autocomplete="email" required>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password">Password <small>8+ characters, letters and numbers</small></label>
                <input id="password" name="password" type="password" class="input" autocomplete="new-password" required>
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </div>
        </form>
        <p class="auth-foot">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
</x-layout>

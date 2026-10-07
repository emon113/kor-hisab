<x-layout title="Sign in" main-class="auth">
    <div class="auth-pitch">
        <h1>Your tax, worked out and kept.</h1>
        <p>Sign in to reopen saved calculations, compare years and keep your own salary split.</p>
        <ul class="auth-points">
            <li><i>1</i><div><b>Live calculation</b>Slabs, rebate and minimum tax under the Finance Act 2026.</div></li>
            <li><i>2</i><div><b>Know your next move</b>How much to invest, when the next slab starts, what a raise really adds.</div></li>
            <li><i>3</i><div><b>Saved and comparable</b>Keep each scenario and see the difference between any two.</div></li>
        </ul>
    </div>

    <div class="panel auth-card">
        <h2>Sign in</h2>
        <p>Welcome back.</p>
        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <div class="field">
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label class="label" for="password">Password</label>
                <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="check" style="margin-top:14px"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in on this device</label>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">Sign in</button>
            </div>
        </form>
        <p class="auth-foot">New here? <a href="{{ route('register') }}">Create an account</a></p>
    </div>
</x-layout>

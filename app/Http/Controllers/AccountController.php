<?php

namespace App\Http\Controllers;

use App\Support\TaxProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $rules = config('tax');

        return view('account.edit', [
            'user' => $request->user(),
            'profile' => TaxProfile::for($request->user())->all(),
            'categories' => collect($rules['categories'])->map(fn ($label, $key) => ['key' => $key, 'label' => __($label)])->values(),
            'thresholds' => collect($rules['years'][$rules['default_year']]['thresholds'])->all(),
        ]);
    }

    /** Tax and salary profile: the defaults every calculator starts from. */
    public function tax(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('tax', TaxProfile::taxRules());
        $data['new_taxpayer'] = $request->boolean('new_taxpayer');
        $this->savePreferences($request, $data);

        return redirect()->to(route('account').'#tax')->with('status', __('Tax profile saved. Every calculator now starts from it.'));
    }

    /** Language, digits and number style, kept with the account and applied on every device. */
    public function display(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('display', TaxProfile::displayRules());
        $user = $this->savePreferences($request, $data);
        $profile = TaxProfile::for($user);

        return redirect()->to(route('account').'#display')
            ->withCookies($profile->displayCookies())
            ->with('sync_display', $profile->displaySync())
            ->with('status', __('Display settings saved.', [], $data['locale']));
    }

    private function savePreferences(Request $request, array $values)
    {
        $user = $request->user();
        $user->update(['preferences' => array_replace($user->preferences ?? [], $values)]);

        return $user;
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('status', __('Profile updated.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', __('Password changed.'));
    }
}

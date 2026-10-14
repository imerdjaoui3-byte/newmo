<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleLoginController extends Controller
{
    /**
     * Send the user to Google to sign in and grant read access to their Gmail.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->scopes([Mailbox::GMAIL_READONLY_SCOPE])
            ->with(['access_type' => 'offline', 'include_granted_scopes' => 'true'])
            ->redirect();
    }

    /**
     * Sign the user in with their Google account and store their Gmail access, creating a pending account for new emails.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled or failed. Please try again.']);
        }

        if (! ($googleUser->user['email_verified'] ?? false)) {
            return redirect()->route('login')->withErrors(['email' => 'Your Google email address is not verified.']);
        }

        if (! in_array(Mailbox::GMAIL_READONLY_SCOPE, $googleUser->approvedScopes, true)) {
            return redirect()->route('login')->withErrors(['email' => 'Allow the app to read your Gmail on Google\'s consent screen to sign in with Google.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update(['google_id' => $googleUser->getId()]);
        } else {
            $user = User::forceCreate([
                'name' => $googleUser->getName() ?: $googleUser->getEmail(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Str::password(32),
                'rank' => null,
                'email_verified_at' => now(),
            ]);
        }

        $user->storeGmailAccess($googleUser);

        if ($user->isPending()) {
            return redirect()->route('login')->with('status', 'Thanks for signing up! An admin needs to approve your account before you can sign in.');
        }

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->flash('promptNotifications', true);

        return redirect()->intended(route('dashboard'));
    }
}

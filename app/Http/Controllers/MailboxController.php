<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class MailboxController extends Controller
{
    /**
     * Show the user's connected inbox and its latest messages.
     */
    public function show(Request $request): View
    {
        $mailbox = $request->user()->mailbox;
        $messages = [];
        $error = null;

        if ($mailbox) {
            try {
                $messages = $mailbox->recentMessages();
            } catch (RequestException $exception) {
                report($exception);
                $error = 'Could not read the inbox. Google may have revoked access — try reconnecting it.';
            }
        }

        return view('mailbox.show', compact('mailbox', 'messages', 'error'));
    }

    /**
     * Send the user to Google to grant (or re-grant) read access to their inbox.
     */
    public function connect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl(config('services.google.mailbox_redirect'))
            ->scopes([Mailbox::GMAIL_READONLY_SCOPE])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Store the inbox tokens Google sends back for the user.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->redirectUrl(config('services.google.mailbox_redirect'))->user();
        } catch (Throwable) {
            return redirect()->route('mailbox.show')->withErrors(['mailbox' => 'Connecting the inbox was cancelled or failed.']);
        }

        if (! in_array(Mailbox::GMAIL_READONLY_SCOPE, $googleUser->approvedScopes, true)) {
            return redirect()->route('mailbox.show')->withErrors(['mailbox' => 'Tick the "Read your email" box on Google\'s consent screen to connect the inbox.']);
        }

        $request->user()->storeGmailAccess($googleUser);

        return redirect()->route('mailbox.show')->with('status', 'Inbox connected successfully.');
    }

    /**
     * Revoke the app's access to the user's inbox and forget its tokens.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $mailbox = $request->user()->mailbox;

        if ($mailbox) {
            Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
                'token' => $mailbox->refresh_token ?? $mailbox->access_token,
            ]);

            $mailbox->delete();
        }

        return redirect()->route('mailbox.show')->with('status', 'Inbox disconnected.');
    }
}

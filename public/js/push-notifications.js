// Subscribes this browser to Web Push notifications and sends the subscription to the server.
// The app may live under a sub-path (e.g. /mo), so URLs and the VAPID key come from the layout.
const { subscriptionUrl, serviceWorkerUrl, vapidPublicKey } = document.querySelector('script[data-subscription-url]').dataset;

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');

  return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
}

function isSameKey(subscription, applicationServerKey) {
  const existingKey = subscription.options?.applicationServerKey;

  return existingKey && new Uint8Array(existingKey).every((byte, i) => byte === applicationServerKey[i]);
}

async function subscribe() {
  try {
    const registration = await navigator.serviceWorker.register(serviceWorkerUrl);
    await navigator.serviceWorker.ready;

    const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);
    let subscription = await registration.pushManager.getSubscription();

    // A subscription made with another key (e.g. the old Firebase setup) can't receive our pushes.
    if (subscription && !isSameKey(subscription, applicationServerKey)) {
      await subscription.unsubscribe();
      subscription = null;
    }

    subscription ??= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey });

    const contentEncoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0];
    const response = await fetch(subscriptionUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({ ...subscription.toJSON(), contentEncoding }),
    });

    if (!response.ok) {
      throw new Error(`Saving the push subscription failed with status ${response.status}`);
    }
  } catch (error) {
    console.error('Error subscribing to push notifications:', error);

    if (error.name === 'AbortError') {
      await explainBlockedPushService();
    }
  }
}

// The browser's push service refused to subscribe (Brave ships with it switched off). Say so once per session,
// otherwise notifications silently never arrive.
async function explainBlockedPushService() {
  try {
    if (sessionStorage.getItem('pushServiceWarningShown') === '1') {
      return;
    }
    sessionStorage.setItem('pushServiceWarningShown', '1');
  } catch {
    // Without storage the warning may repeat, which is still better than silence.
  }

  const isBrave = await navigator.brave?.isBrave?.().catch(() => false);
  const text = isBrave
    ? 'Brave blocks push notifications by default. Open brave://settings/privacy, turn on "Use Google services for push messaging", then restart Brave.'
    : "This browser's push service refused to register, so notifications can't be delivered here. Check that notifications are allowed for this site and that you're not in a private window.";

  if (window.Swal) {
    window.Swal.fire({ icon: 'warning', title: 'Notifications are blocked', text });
  } else {
    alert(text);
  }
}

function isPromptDismissed() {
  try {
    return sessionStorage.getItem('notificationPromptDismissed') === '1';
  } catch {
    return false;
  }
}

function showPrompt() {
  const prompt = document.getElementById('notificationPrompt');

  // The server only renders the prompt when it's relevant; it's pointless once the user has answered the browser,
  // and "Not now" hides it for the rest of this browser session.
  if (!prompt || Notification.permission !== 'default' || isPromptDismissed()) {
    return;
  }

  prompt.classList.remove('d-none');

  prompt.querySelector('[data-enable-notifications]').addEventListener('click', async () => {
    prompt.remove();

    if (await Notification.requestPermission() === 'granted') {
      await subscribe();
    }
  });

  prompt.querySelector('[data-dismiss-notifications]').addEventListener('click', () => {
    prompt.remove();

    try {
      sessionStorage.setItem('notificationPromptDismissed', '1');
    } catch {
      // Storage can be unavailable (e.g. private mode); the prompt then simply returns on the next page.
    }
  });
}

if ('serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && vapidPublicKey) {
  if (Notification.permission === 'granted') {
    subscribe();
  } else {
    showPrompt();
  }
}

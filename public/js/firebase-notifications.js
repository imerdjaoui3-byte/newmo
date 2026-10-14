import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js";
import { getMessaging, getToken, onMessage } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging.js";

const firebaseConfig = {
  apiKey: "AIzaSyAEXGaxzqYmNPx4lv6h0xamdKNiT8e4Gao",
  authDomain: "momo-442f9.firebaseapp.com",
  projectId: "momo-442f9",
  storageBucket: "momo-442f9.firebasestorage.app",
  messagingSenderId: "801873527863",
  appId: "1:801873527863:web:5e9a1420c2af0e0498abb2"
};

// The app may live under a sub-path (e.g. /mo), so URLs come from the layout instead of being hard-coded.
const { deviceTokenUrl, serviceWorkerUrl } = document.querySelector('script[data-device-token-url]').dataset;

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

async function registerDevice() {
  try {
    const serviceWorkerRegistration = await navigator.serviceWorker.register(serviceWorkerUrl);

    const token = await getToken(messaging, {
      vapidKey: 'BIAF6bY3ywM5H8KKOHUNjXKwxyz0NCH1uxbv4eqtBJEsy8DnTmJQCOt6Sxl45XZ7Fkwz8_4c1s_eLnzUtt97Y7w',
      serviceWorkerRegistration,
    });

    if (!token) {
      return;
    }

    const response = await fetch(deviceTokenUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({ token }),
    });

    if (!response.ok) {
      throw new Error(`Saving the device token failed with status ${response.status}`);
    }
  } catch (error) {
    console.error('Error registering for push notifications:', error);
  }
}

function showLoginPrompt() {
  const prompt = document.getElementById('notificationPrompt');

  // The prompt is only rendered right after login, and is pointless once the user has answered the browser.
  if (!prompt || Notification.permission !== 'default') {
    return;
  }

  prompt.classList.remove('d-none');

  prompt.querySelector('[data-enable-notifications]').addEventListener('click', async () => {
    prompt.remove();

    if (await Notification.requestPermission() === 'granted') {
      await registerDevice();
    }
  });

  prompt.querySelector('[data-dismiss-notifications]').addEventListener('click', () => prompt.remove());
}

// Firebase only auto-displays notifications while the app is in the background, so show foreground ones ourselves.
onMessage(messaging, async ({ notification, fcmOptions }) => {
  if (!notification || Notification.permission !== 'granted') {
    return;
  }

  const registration = await navigator.serviceWorker.getRegistration(serviceWorkerUrl);
  registration?.showNotification(notification.title, {
    body: notification.body,
    icon: notification.icon,
    data: { link: fcmOptions?.link },
  });
});

if ('serviceWorker' in navigator && 'Notification' in window) {
  if (Notification.permission === 'granted') {
    registerDevice();
  } else {
    showLoginPrompt();
  }
}

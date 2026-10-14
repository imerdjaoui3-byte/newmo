importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "AIzaSyAEXGaxzqYmNPx4lv6h0xamdKNiT8e4Gao",
  authDomain: "momo-442f9.firebaseapp.com",
  projectId: "momo-442f9",
  storageBucket: "momo-442f9.firebasestorage.app",
  messagingSenderId: "801873527863",
  appId: "1:801873527863:web:5e9a1420c2af0e0498abb2"
});

// Background messages carry a `notification` payload, which the Firebase SDK displays on its own
// (including the click-through link), so no onBackgroundMessage handler is needed.
firebase.messaging();

// Notifications the page shows itself while open (see firebase-notifications.js) open their link when clicked.
self.addEventListener('notificationclick', (event) => {
  const link = event.notification.data?.link;

  if (!link) {
    return;
  }

  event.notification.close();
  event.waitUntil(clients.openWindow(link));
});

// Shows Web Push notifications sent by the server (see App\Notifications) and opens their link when clicked.

self.addEventListener('push', (event) => {
  if (!event.data) {
    return;
  }

  const { title, ...options } = event.data.json();

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const url = event.notification.data?.url;

  if (!url) {
    return;
  }

  // Focus an already-open app tab when there is one, instead of opening another.
  event.waitUntil((async () => {
    const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    const existing = windows.find((client) => client.url.startsWith(url));

    return existing ? existing.focus() : clients.openWindow(url);
  })());
});

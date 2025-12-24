self.addEventListener('push', function(event) {
    var payload = {};
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            payload = { title: event.data.text() };
        }
    }

    var title = payload.title || 'Nueva publicación';
    var options = {
        body: payload.body || '',
        icon: payload.icon || '/nammu.png',
        badge: payload.badge || '/nammu.png',
        data: {
            url: payload.url || '/'
        }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var url = (event.notification && event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
            return null;
        })
    );
});

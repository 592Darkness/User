// Import the Firebase SDK scripts (needed for the service worker)
importScripts("https://www.gstatic.com/firebasejs/9.17.1/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/9.17.1/firebase-messaging-compat.js");

// Initialize the Firebase app in the service worker
// Use the same config as in your main app
const firebaseConfig = {
    apiKey: "AIzaSyBWObo083OCr_rP7im_TWrQky2iHE0A380",
    authDomain: "salaam-rides.firebaseapp.com",
    projectId: "salaam-rides",
    storageBucket: "salaam-rides.firebasestorage.app",
    messagingSenderId: "808445698259",
    appId: "1:808445698259:web:952f7deaa37f4ddcd53426",
    measurementId: "G-0BY49BNXXH"
  };

firebase.initializeApp(firebaseConfig);

// Retrieve an instance of Firebase Messaging so that it can handle background messages.
const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage((payload) => {
  console.log(
    "[firebase-messaging-sw.js] Received background message ",
    payload
  );

  // Customize notification here
  const notificationTitle = payload.notification?.title || 'New Notification'; // Use optional chaining and provide default
  const notificationOptions = {
    body: payload.notification?.body || '',
    icon: '/assets/images/notification-icon.png', // Optional: Path to an icon
    // You can also use data from payload.data
    data: payload.data // Pass data along so click events can use it
  };

  // Show the notification
  self.registration.showNotification(notificationTitle, notificationOptions);


  // Optional: Handle notification click
  self.addEventListener('notificationclick', (event) => {
    console.log('Notification clicked: ', event.notification);
    event.notification.close(); // Close the notification

    // Example: Open a specific URL based on data in the notification
    const urlToOpen = event.notification.data?.url || '/'; // Get URL from data payload or default to home

    event.waitUntil(
      clients.matchAll({ type: 'window' }).then((windowClients) => {
        // Check if there is already a window/tab open with the target URL
        for (var i = 0; i < windowClients.length; i++) {
          var client = windowClients[i];
          if (client.url === urlToOpen && 'focus' in client) {
            return client.focus();
          }
        }
        // If not, open a new window/tab.
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
    );
  });

});
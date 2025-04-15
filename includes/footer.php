<footer class="bg-gray-800 border-t border-gray-700/50 py-8<?php echo ($currentPage == 'account-dashboard') ? ' mt-auto' : ''; ?>">
        <div class="container mx-auto px-4 md:flex md:justify-between md:items-center">
            <div class="text-center md:text-left mb-6 md:mb-0">
                <p class="text-gray-400">&copy; <span id="current-year"><?php echo date('Y'); ?></span> Salaam Rides. All Rights Reserved.</p>
                <p class="text-sm mt-2 text-gray-500">Serving Georgetown, Linden, Berbice, and all across Guyana.</p>
            </div>
            <div class="flex flex-wrap justify-center md:justify-end space-x-4">
                <a href="#" class="hover:text-primary-400 transition duration-300 text-gray-400">Privacy Policy</a>
                <span class="text-gray-600">|</span>
                <a href="#" class="hover:text-primary-400 transition duration-300 text-gray-400">Terms of Service</a>
                <span class="text-gray-600">|</span>
                <a href="#" class="hover:text-primary-400 transition duration-300 text-gray-400">Contact Us</a>
            </div>
        </div>
        <div class="mt-6 text-center text-xs text-gray-600">
            <p>For emergencies, please call our 24/7 hotline: +592-123-4567</p>
        </div>
    </footer>

    <div id="confirmation-message" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-green-600 text-white text-sm font-medium py-3 px-6 rounded-lg shadow-lg z-50 flex items-center space-x-2 opacity-0 transition-all duration-300">
        <span class="lucide hidden" id="confirmation-icon" aria-hidden="true">&#xe96c;</span>
        <span id="confirmation-text">
            <?php 
            $flashMessage = getFlashMessage();
            if ($flashMessage) {
                echo htmlspecialchars($flashMessage['message']);
                $messageType = $flashMessage['type'] == 'error' ? 'true' : 'false';
                echo '<script>document.addEventListener("DOMContentLoaded", function() { showConfirmation("' . htmlspecialchars($flashMessage['message']) . '", ' . $messageType . '); });</script>';
            }
            ?>
        </span>
        <button id="close-notification" class="ml-2 text-white hover:text-white/80" aria-label="Close notification">
            <span class="lucide text-sm" aria-hidden="true">&#xea76;</span>
        </button>
    </div>

    <div id="loading-overlay" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="flex flex-col items-center">
            <div class="spinner-border animate-spin inline-block w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
            <p class="text-xl text-white">Loading...</p>
        </div>
    </div>

    <div id="offline-alert" class="fixed bottom-0 inset-x-0 bg-red-600 text-white p-3 text-center transform translate-y-full transition-transform duration-300 z-50">
        <p class="flex items-center justify-center">
            <span class="lucide mr-2" aria-hidden="true">&#xea0e;</span>
            <span>You are currently offline. Some features may be unavailable.</span>
        </p>
    </div>
    <script src="https://www.gstatic.com/firebasejs/9.17.1/firebase-app-compat.js"></script> <script src="https://www.gstatic.com/firebasejs/9.17.1/firebase-messaging-compat.js"></script>


<script
const firebaseConfig = {
    apiKey: "AIzaSyBWObo083OCr_rP7im_TWrQky2iHE0A380",
    authDomain: "salaam-rides.firebaseapp.com",
    projectId: "salaam-rides",
    storageBucket: "salaam-rides.firebasestorage.app",
    messagingSenderId: "808445698259",
    appId: "1:808445698259:web:952f7deaa37f4ddcd53426",
    measurementId: "G-0BY49BNXXH"
};

// --- Initialize Firebase ---
try {
    firebase.initializeApp(firebaseConfig);
    console.log("Firebase Initialized");

    const messaging = firebase.messaging();

    // --- Request Permission and Get Token ---
    async function requestNotificationPermission() {
        try {
            console.log('Requesting notification permission...');
            // Check if permission already granted
            if (Notification.permission === 'granted') {
                 console.log('Notification permission already granted.');
                 await getTokenAndSendToServer(messaging);
                 return;
            }
            if (Notification.permission === 'denied') {
                 console.log('Notification permission was previously denied.');
                 // Optionally guide user on how to re-enable in browser settings
                 alert('Notification permission is blocked. Please enable it in your browser settings if you wish to receive ride updates.');
                 return;
            }

            // Request permission
            const permission = await Notification.requestPermission();

            if (permission === 'granted') {
                console.log('Notification permission granted.');
                await getTokenAndSendToServer(messaging);
            } else {
                console.log('Unable to get permission to notify.');
                // Optionally inform the user
                // alert('You will not receive real-time ride updates unless you grant notification permission.');
            }
        } catch (error) {
            console.error('Error requesting notification permission: ', error);
        }
    }

  
    async function getTokenAndSendToServer(messagingInstance) {
        try {
            // You need the VAPID key from Firebase Console > Project Settings > Cloud Messaging > Web configuration
            const vapidKey = "BBB23G-GqnxUdDBOZtqocxZQdCi6L4ANtdwo-gCLvyEigGPlJHxuThSz6cEWxcLHuskFJXBjJ7P8niEFJwRlvfQ";

            const currentToken = await messagingInstance.getToken({ vapidKey: vapidKey });

            if (currentToken) {
                console.log('FCM Token:', currentToken);
                sendTokenToServer(currentToken);
            } else {
                console.log('No registration token available. Request permission to generate one.');
                // This might happen if permission wasn't granted or revoked.
                // You might want to re-request permission here, but be careful not to annoy the user.
                 await requestNotificationPermission(); // Try requesting again
            }
        } catch (err) {
            console.error('An error occurred while retrieving token. ', err);
            // Handle specific errors like 'messaging/notifications-blocked'
             if (err.code === 'messaging/notifications-blocked') {
                 console.warn('Notifications are blocked by the browser or user settings.');
                 // Optionally inform the user
             }
        }
    }

    // --- Send Token to Your PHP Backend ---
    function sendTokenToServer(token) {
        // IMPORTANT: Only send the token if it's different from the one potentially stored locally,
        // or if you don't store it locally. This prevents unnecessary updates.
        // You could store the sent token in localStorage and compare.
        const storedToken = localStorage.getItem('fcm_token_sent');
        if (token === storedToken) {
            console.log("Token already sent to server.");
            return;
        }

        console.log('Sending token to server...');
        // Replace with the actual URL to your PHP endpoint for storing the token
        const endpoint = '/api/store-fcm-token.php'; // <--- ADJUST URL IF NEEDED

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ token: token }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('Token successfully sent to server.');
                localStorage.setItem('fcm_token_sent', token); // Store sent token
            } else {
                console.error('Failed to send token to server:', data.message);
                // Optionally clear the stored token so it tries again later
                // localStorage.removeItem('fcm_token_sent');
            }
        })
        .catch((error) => {
            console.error('Error sending token to server:', error);
        });
    }

    // --- Handle Foreground Messages ---
    // Listen for messages when the web page is active
    messaging.onMessage((payload) => {
        console.log('Message received in foreground. ', payload);

        // Customize how you display the notification in the foreground
        // For example, show a custom toast notification within your page
        const notificationTitle = payload.notification.title;
        const notificationOptions = {
            body: payload.notification.body,
            icon: '/assets/images/notification-icon.png' // Optional: Add an icon path
            // You can also access data payload: payload.data
        };

        // Example: Use the browser's Notification API (will only work if page is active)
         // new Notification(notificationTitle, notificationOptions); // Less common for foreground

         // Better: Use a custom in-app notification UI element (e.g., a toast)
         showInAppNotification(notificationTitle, notificationOptions.body, payload.data);

    });

    // --- Example In-App Notification Function ---
    function showInAppNotification(title, body, data) {
        // Implement your logic to show a notification banner/toast/modal
        // inside your web page. This requires HTML/CSS for the notification element.
        console.log(`In-App Notification: ${title} - ${body}`, data);
        // Example: Find a div with id="in-app-notification" and update its content
        const notificationElement = document.getElementById('in-app-notification');
        if (notificationElement) {
             notificationElement.innerHTML = `<strong>${title}</strong><p>${body}</p>`;
             notificationElement.style.display = 'block'; // Make it visible
             // Add a way to dismiss it
             setTimeout(() => { notificationElement.style.display = 'none'; }, 5000); // Hide after 5s
        } else {
            // Fallback if the element doesn't exist
            alert(`${title}\n${body}`);
        }
    }


    // --- Initial Check ---
    // Check permission status on page load
    if ('Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window) {
       requestNotificationPermission(); // Ask for permission immediately, or trigger this on a user action (e.g., button click)
    } else {
        console.warn('Push messaging is not supported in this browser.');
    }


} catch (e) {
    console.error("Error initializing Firebase or Messaging: ", e);
    // Handle initialization error (e.g., invalid config)
}
></script>

    <script src="<?php echo asset('js/' . ($currentPage == 'account-dashboard' ? 'dashboard.js' : 'script.js')); ?>"></script>
</body>
</html>
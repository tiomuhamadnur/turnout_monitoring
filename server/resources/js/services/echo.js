import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import api from './api';

// laravel-echo with the "reverb" broadcaster uses the pusher protocol on the
// wire, so pusher-js still needs to be visible on window.
window.Pusher = Pusher;

let echoInstance = null;

/**
 * Lazily create and return a single Echo instance for the whole SPA.
 *
 * The broadcast auth endpoint (/broadcasting/auth) is Sanctum-session
 * protected, so we delegate the auth POST to the already-configured
 * axios client — that way credentialed cookies + the XSRF token flow
 * automatically.
 */
export function getEcho() {
    if (echoInstance) return echoInstance;

    const key    = import.meta.env.VITE_REVERB_APP_KEY || 'turnout';
    const host   = import.meta.env.VITE_REVERB_HOST    || window.location.hostname;
    const port   = Number(import.meta.env.VITE_REVERB_PORT || 8080);
    const scheme = import.meta.env.VITE_REVERB_SCHEME  || 'http';

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                api.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                .then(({ data }) => callback(null, data))
                .catch((err) => callback(err, null));
            },
        }),
    });

    return echoInstance;
}

export function disconnectEcho() {
    if (echoInstance) {
        echoInstance.disconnect();
        echoInstance = null;
    }
}

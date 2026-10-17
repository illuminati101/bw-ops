import L from 'leaflet';
import Pusher from 'pusher-js';

window.L = L;

// Control-room realtime: rebroadcast server events as a window event that admin pages listen for.
document.addEventListener('DOMContentLoaded', () => {
    const rt = window.__realtime;
    if (!rt || !rt.key) return;
    try {
        const pusher = new Pusher(rt.key, {
            wsHost: rt.host, wsPort: rt.port, wssPort: rt.port, forceTLS: rt.scheme === 'https',
            enabledTransports: ['ws', 'wss'], cluster: 'mt1', authEndpoint: '/broadcasting/auth',
            auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } },
        });
        const ch = pusher.subscribe('private-control-room');
        const emit = (name, data) => window.dispatchEvent(new CustomEvent('bw-realtime', { detail: { name, data } }));
        ['booking.updated', 'operator.location', 'alert'].forEach((name) => ch.bind(name, (data) => emit(name, data)));
        window.bwRealtime = pusher;
    } catch (e) {
        console.debug('Realtime unavailable, polling instead', e);
    }
});

import L from 'leaflet';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.L = L;
window.Pusher = Pusher;

const rt = window.__realtime;
if (rt && rt.key) {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: rt.key,
            wsHost: rt.host,
            wsPort: rt.port,
            wssPort: rt.port,
            forceTLS: rt.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        });
    } catch (e) {
        console.debug('Realtime unavailable; pages refresh on a timer instead.');
    }
}

// Mobile navigation
document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-nav-toggle]');
    if (t) {
        document.documentElement.classList.toggle('nav-open');
        t.setAttribute('aria-expanded', document.documentElement.classList.contains('nav-open'));
    }
});

// Live trip map used by the booking page and shared trip links.
// Usage: const m = bwTrackMap(el); m.update({ pickup, dropoff, stops, operators: [{lat, lng, label}] })
window.bwTrackMap = function (el, data) {
    const map = L.map(el, { zoomControl: false, attributionControl: true, scrollWheelZoom: false }).setView([-33.9249, 18.4241], 12);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer(window.__tiles || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    const layer = L.layerGroup().addTo(map);
    const icon = (cls, label) => L.divIcon({ className: '', html: `<div class="pin ${cls}">${label ?? ''}</div>`, iconSize: [34, 34], iconAnchor: [17, 17] });
    let fitted = false;
    const api = {
        map,
        update(d) {
            if (!d) return;
            layer.clearLayers();
            const pts = [];
            const add = (p, cls, label) => { if (p && p.lat != null) { L.marker([p.lat, p.lng], { icon: icon(cls, label), keyboard: false }).addTo(layer); pts.push([p.lat, p.lng]); } };
            add(d.pickup, 'pin-me', 'A');
            (d.stops || []).forEach((s, i) => add(s, 'pin-me', i + 1));
            add(d.dropoff, 'pin-me', 'B');
            const route = pts.slice();
            if (route.length > 1) L.polyline(route, { color: '#c9a86a', weight: 2, opacity: .55, dashArray: '4 6' }).addTo(layer);
            (d.operators || []).forEach((o) => add(o, '', o.label));
            if (pts.length && (!fitted || (d.operators || []).length)) {
                pts.length === 1 ? map.setView(pts[0], 15) : map.fitBounds(pts, { padding: [48, 48], maxZoom: 16 });
                fitted = true;
            }
        },
    };
    api.update(data);
    setTimeout(() => map.invalidateSize(), 50);
    return api;
};

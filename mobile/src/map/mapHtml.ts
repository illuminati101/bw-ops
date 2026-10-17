import { LEAFLET_CSS, LEAFLET_JS } from './leafletAssets';

export type MapPoint = { lat: number; lng: number; label?: string | number; kind?: 'place' | 'operator' | 'me' };
export type MapData = { points: MapPoint[]; route?: boolean; follow?: boolean };

/** The Leaflet page shown inside the map WebView (and the browser preview's iframe). */
export const mapHtml = (tiles: string, interactive: boolean, c: { lat: number; lng: number }) => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<style>${LEAFLET_CSS}
html,body,#m{margin:0;height:100%;background:#121211}
.leaflet-tile-pane{filter:invert(1) hue-rotate(180deg) brightness(.92) contrast(.82) saturate(.35)}
.leaflet-control-attribution{background:rgba(7,7,7,.6)!important;color:#8f8a80!important;font:10px sans-serif}
.leaflet-control-attribution a{color:#b9b3a7!important}
.pin{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font:700 11px -apple-system,Roboto,sans-serif;border:2px solid #0a0907}
.place{background:#ede8dd;color:#0a0907;box-shadow:0 0 0 5px rgba(237,232,221,.15)}
.operator{background:#c9a86a;color:#0a0907;box-shadow:0 0 0 6px rgba(201,168,106,.25)}
.me{width:16px;height:16px;background:#5aa9ff;border:3px solid #fff;box-shadow:0 0 0 6px rgba(90,169,255,.25)}
</style></head><body><div id="m"></div><script>${LEAFLET_JS}</script><script>
var map=L.map('m',{zoomControl:false,attributionControl:true,dragging:${interactive},touchZoom:${interactive},doubleClickZoom:${interactive},scrollWheelZoom:false}).setView([${c.lat},${c.lng}],12);
L.tileLayer(${JSON.stringify(tiles)},{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
var layer=L.layerGroup().addTo(map), fitted=false;
function icon(k,l){var cls=k==='operator'?'operator':k==='me'?'me':'place';var s=k==='me'?16:30;return L.divIcon({className:'',html:'<div class="pin '+cls+'">'+(l==null?'':l)+'</div>',iconSize:[s,s],iconAnchor:[s/2,s/2]});}
function update(d){layer.clearLayers();var pts=[],route=[];(d.points||[]).forEach(function(p){if(p.lat==null)return;L.marker([p.lat,p.lng],{icon:icon(p.kind,p.label),keyboard:false}).addTo(layer);pts.push([p.lat,p.lng]);if(p.kind==='place')route.push([p.lat,p.lng]);});
if(d.route&&route.length>1)L.polyline(route,{color:'#c9a86a',weight:2,opacity:.6,dashArray:'4 6'}).addTo(layer);
if(pts.length&&(!fitted||d.follow)){pts.length===1?map.setView(pts[0],15):map.fitBounds(pts,{padding:[40,40],maxZoom:16});fitted=true;}}
function onMsg(e){try{var m=JSON.parse(e.data);if(m.type==='update')update(m.data);}catch(x){}}
document.addEventListener('message',onMsg);window.addEventListener('message',onMsg);
window.ReactNativeWebView&&window.ReactNativeWebView.postMessage('ready');
</script></body></html>`;

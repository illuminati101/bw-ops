"""Generates the placeholder art for the website (no stock photos available offline).
Cinematic, abstract, on-brand: near-black, warm white and champagne gold. Swap for real photos in Admin > Pages."""
import numpy as np, math, random, sys, os
from PIL import Image, ImageFilter, ImageDraw

OUT = sys.argv[1]
os.makedirs(OUT, exist_ok=True)
GOLD = np.array([201, 168, 106], float)
WARM = np.array([245, 238, 225], float)
rng = np.random.default_rng(7)

def canvas(w, h, top=(10, 10, 14), bottom=(4, 4, 6)):
    t = np.linspace(0, 1, h)[:, None, None]
    return (np.array(top, float) * (1 - t) + np.array(bottom, float) * t) * np.ones((h, w, 3))

def add_glow(img, x, y, r, color, strength):
    h, w, _ = img.shape
    x0, x1 = max(0, int(x - r * 3)), min(w, int(x + r * 3))
    y0, y1 = max(0, int(y - r * 3)), min(h, int(y + r * 3))
    if x0 >= x1 or y0 >= y1:
        return
    yy, xx = np.mgrid[y0:y1, x0:x1]
    d2 = ((xx - x) ** 2 + (yy - y) ** 2) / (r * r)
    img[y0:y1, x0:x1] += np.exp(-d2)[..., None] * color * strength

def bokeh(img, n, ymin, ymax, rmin, rmax, palette, strength=(0.15, 0.6)):
    h, w, _ = img.shape
    for _ in range(n):
        add_glow(img, rng.uniform(0, w), rng.uniform(ymin * h, ymax * h), rng.uniform(rmin, rmax), palette[rng.integers(len(palette))], rng.uniform(*strength))

def finish(img, name, vignette=0.55, grain=6):
    h, w, _ = img.shape
    yy, xx = np.mgrid[0:h, 0:w]
    d = np.sqrt(((xx - w / 2) / (w / 2)) ** 2 + ((yy - h / 2) / (h / 2)) ** 2)
    img *= (1 - vignette * np.clip(d - 0.35, 0, 1))[..., None]
    img += rng.normal(0, grain, img.shape)
    Image.fromarray(np.clip(img, 0, 255).astype(np.uint8)).save(os.path.join(OUT, name), quality=86, optimize=True, progressive=True)

def light_trails(img, n, y_center, spread, color, width=2.5):
    h, w, _ = img.shape
    layer = Image.new('L', (w, h), 0)
    dr = ImageDraw.Draw(layer)
    for i in range(n):
        amp = rng.uniform(15, 45); phase = rng.uniform(0, 6); yb = y_center * h + rng.uniform(-spread, spread) * h
        slope = rng.uniform(-50, 50); freq = rng.uniform(1.6, 2.6)
        pts = [(x, yb + amp * math.sin(x / w * freq + phase) + (x / w) * slope) for x in range(-20, w + 20, 6)]
        dr.line(pts, fill=int(rng.uniform(90, 255)), width=int(width + rng.uniform(0, 2)))
    blur = np.array(layer.filter(ImageFilter.GaussianBlur(6)), float) / 255
    sharp = np.array(layer.filter(ImageFilter.GaussianBlur(1)), float) / 255
    img += (blur * 0.8 + sharp * 0.6)[..., None] * color

def table_mountain(w, h, base_y):
    """Silhouette from the city side: Devil's Peak, the flat Table, Kloof Nek, Lion's Head, Signal Hill."""
    pts = [(0, .10), (.05, .24), (.10, .55), (.145, .86), (.165, .82), (.195, .76), (.222, .92), (.24, .955), (.40, .962), (.58, .968),
           (.598, .952), (.622, .66), (.645, .44), (.67, .35), (.70, .37), (.733, .49), (.758, .62), (.772, .635), (.788, .57),
           (.815, .34), (.86, .25), (.93, .20), (1.0, .12)]
    xs = np.linspace(0, 1, w)
    prof = np.interp(xs, [p[0] for p in pts], [p[1] for p in pts])
    k = np.exp(-np.linspace(-2, 2, 25) ** 2); k /= k.sum()
    prof = np.convolve(np.pad(prof, 12, mode='edge'), k, mode='valid')
    prof += 0.0015 * np.sin(xs * 260) * (prof > 0.9)  # a little texture along the top of the Table
    return base_y - prof * h * 0.40

W, H = 2400, 1350

# 1. Hero: night drive, light trails and bokeh
img = canvas(W, H, (14, 13, 16), (3, 3, 4))
bokeh(img, 60, 0.15, 0.7, 12, 60, [GOLD, WARM, GOLD * 0.8], (0.05, 0.35))
light_trails(img, 14, 0.66, 0.06, GOLD * 0.9)
light_trails(img, 8, 0.70, 0.04, WARM * 0.7, 1.5)
bokeh(img, 18, 0.55, 0.85, 40, 120, [GOLD], (0.05, 0.18))
finish(img, 'hero-night-drive.jpg', 0.65)

# 2. About: Table Mountain at dusk with city lights
img = canvas(W, H, (26, 30, 44), (9, 9, 12))
add_glow(img, W * 0.4, H * 0.62, 700, np.array([120, 90, 60.]), 0.35)
ridge = table_mountain(W, H, H * 0.80)
yy = np.arange(H)[:, None]
mask = yy > ridge[None, :]
img[mask] = img[mask] * 0.15 + np.array([6, 6, 8.]) * 0.85
for _ in range(700):  # city lights, denser towards the bottom of the frame
    x = rng.uniform(0, W); y = H * (0.80 + 0.2 * rng.beta(2.2, 1.2))
    add_glow(img, x, y, rng.uniform(1.2, 3.5), GOLD if rng.random() < 0.75 else WARM, rng.uniform(0.25, 0.9))
bokeh(img, 25, 0.8, 1.0, 20, 70, [GOLD], (0.05, 0.2))
finish(img, 'about-skyline.jpg', 0.5)

# 3. Control room: abstract night map with glowing positions
img = canvas(W, H, (9, 10, 12), (5, 5, 7))
layer = Image.new('L', (W, H), 0); dr = ImageDraw.Draw(layer)
for _ in range(70):
    x0, y0 = rng.uniform(0, W), rng.uniform(0, H); ang = rng.uniform(0, math.pi); L = rng.uniform(300, 1600)
    dr.line([(x0, y0), (x0 + L * math.cos(ang), y0 + L * math.sin(ang))], fill=int(rng.uniform(25, 70)), width=int(rng.uniform(1, 4)))
for r in range(150, 1400, 170):
    dr.ellipse([W * 0.62 - r, H * 0.48 - r, W * 0.62 + r, H * 0.48 + r], outline=22, width=1)
img += (np.array(layer, float) / 255)[..., None] * WARM * 0.5
for _ in range(14):
    add_glow(img, rng.uniform(W * 0.2, W * 0.95), rng.uniform(H * 0.15, H * 0.9), rng.uniform(6, 12), GOLD, 1.2)
    
finish(img, 'control-room.jpg', 0.6)

# 4-7. Variants for services / briefing / careers / corporate
for name, seed, tint in [('services-sedan.jpg', 11, GOLD), ('operator-briefing.jpg', 12, WARM), ('careers.jpg', 13, GOLD), ('corporate.jpg', 14, WARM)]:
    rng = np.random.default_rng(seed)
    img = canvas(W, H, (13, 12, 15), (4, 4, 5))
    bokeh(img, 45, 0.1, 0.9, 15, 90, [tint, GOLD * 0.7], (0.04, 0.3))
    light_trails(img, 6, rng.uniform(0.5, 0.8), 0.08, tint * 0.6, 1.5)
    finish(img, name, 0.6)
print('art written to', OUT)

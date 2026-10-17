// Usage: node shoot.js <baseUrl> <outDir> <path1> [path2 ...]   (logs into /admin first if ADMIN=1)
const { chromium } = require('playwright');
(async () => {
  const [base, out, ...paths] = process.argv.slice(2);
  const browser = await chromium.launch({ executablePath: process.env.CHROME || undefined });
  const ctx = await browser.newContext({ viewport: { width: Number(process.env.W || 1440), height: Number(process.env.H || 900) }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
  if (process.env.ADMIN) {
    await page.goto(base + '/admin/login');
    await page.fill('input[type=email]', process.env.EMAIL || 'admin@blackandwhiteops.com');
    await page.fill('input[type=password]', process.env.PASS || 'ChangeMe-2026!');
    await page.click('button[type=submit]');
    await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 15000 }).catch(() => {});
  }
  if (process.env.CLIENT) {
    await page.goto(base + '/account/login', { waitUntil: 'networkidle' });
    await page.fill('#phone', process.env.CLIENT);
    await page.click('button:has-text("Send code")');
    const strong = page.locator('strong.tracking-\\[\\.3em\\]');
    await strong.waitFor({ timeout: 10000 });
    await page.fill('#code', (await strong.innerText()).trim());
    await page.click('button:has-text("Continue")');
    await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 15000 }).catch(() => errors.push('client login did not redirect'));
  }
  for (const p of paths) {
    const res = await page.goto(base + p, { waitUntil: 'networkidle', timeout: 30000 }).catch(e => { errors.push(p + ' ' + e.message); return null; });
    const status = res ? res.status() : 'ERR';
    await page.waitForTimeout(Number(process.env.WAIT || 400));
    const name = p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home';
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: !!process.env.FULL });
    const title = await page.title();
    const bodyText = (await page.innerText('body').catch(() => '')).slice(0, 300).replace(/\s+/g, ' ');
    console.log(`${status} ${p} | ${title} | ${bodyText.slice(0, 160)}`);
  }
  if (errors.length) console.log('ERRORS:\n' + errors.slice(0, 15).join('\n'));
  await browser.close();
})();

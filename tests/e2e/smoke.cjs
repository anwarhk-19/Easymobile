const { chromium } = require('playwright');
/**
 * Browser smoke test: visits every customer, merchant, technician and admin screen against seeded data
 * and fails on any JS error or 5xx API response.
 * Usage: php artisan migrate:fresh --seed && php artisan serve --port=8011 & npm run build && npm run e2e
 */
const fs = require('fs');
const OUT = process.argv[2] || 'storage/e2e-screenshots';
const B = process.env.E2E_BASE_URL || 'http://127.0.0.1:8011';
fs.mkdirSync(OUT, { recursive: true });
(async () => {
  const browser = await chromium.launch();
  const errors = [];
  async function session(name, setup) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await ctx.newPage();
    page.on('console', (m) => m.type() === 'error' && errors.push(`[${name}] console: ${m.text()} @ ${page.url()}`));
    page.on('pageerror', (e) => errors.push(`[${name}] pageerror: ${e.message} @ ${page.url()}`));
    page.on('response', (r) => { if (r.url().includes('/api/') && r.status() >= 500) errors.push(`[${name}] ${r.status()} ${r.url()}`); });
    if (setup) await setup(page);
    return page;
  }
  async function login(page, phone) {
    const r = await page.request.post(B + '/api/v1/auth/otp/send', { data: { phone }, headers: { Accept: 'application/json' } });
    const code = (await r.json()).debug_code;
    const v = await page.request.post(B + '/api/v1/auth/otp/verify', { data: { phone, code }, headers: { Accept: 'application/json' } });
    const tok = (await v.json()).token;
    await page.goto(B + '/');
    await page.evaluate((t) => localStorage.setItem('bmh_token', t), tok);
  }
  async function visit(page, name, paths) {
    for (const p of paths) {
      await page.goto(B + p);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(250);
      const file = `${OUT}/${name}${p.replace(/[\/?=&]/g, '_') || '_home'}.png`;
      await page.screenshot({ path: file, fullPage: false });
    }
  }
  const pub = await session('public');
  await visit(pub, 'public', ['/', '/search?q=charger', '/search?q=nokia%203310', '/shops', '/shops/al-noor-mobiles', '/repairs', '/technicians/imran-mobile-repairs', '/login', '/staff']);
  await pub.goto(B + '/search?category=chargers'); await pub.waitForLoadState('networkidle');
  await pub.locator('a[href^="/products/"]').first().click(); await pub.waitForLoadState('networkidle');
  await pub.screenshot({ path: `${OUT}/public_product.png` });

  const cust = await session('customer', (p) => login(p, '9900000002'));
  await visit(cust, 'customer', ['/account', '/orders', '/enquiries', '/my-repairs', '/repairs/new', '/support', '/support/new', '/notifications', '/orders/5', '/orders/2']);

  const merch = await session('merchant', (p) => login(p, '9845000101'));
  await visit(merch, 'merchant', ['/merchant', '/merchant/products', '/merchant/products/new', '/merchant/products/1', '/merchant/orders', '/merchant/orders/5', '/merchant/enquiries', '/merchant/reports', '/merchant/settlements', '/merchant/shop']);

  const c1 = await session('customer1', (p) => login(p, '9900000001'));
  await visit(c1, 'c1', ['/repairs/1', '/orders/1']);
  const tech = await session('technician', (p) => login(p, '9845000202'));
  await visit(tech, 'tech', ['/technician', '/technician/repairs', '/technician/repairs/2', '/technician/reports', '/technician/profile']);

  const adm = await session('admin', async (p) => {
    const r = await p.request.post(B + '/api/v1/auth/staff/login', { data: { email: 'admin@bhatkalmobilehub.in', password: 'ChangeMe@2026' }, headers: { Accept: 'application/json' } });
    const tok = (await r.json()).token; await p.goto(B + '/'); await p.evaluate((t) => localStorage.setItem('bmh_token', t), tok);
  });
  await adm.setViewportSize({ width: 1366, height: 900 });
  await visit(adm, 'admin', ['/admin', '/admin/shops', '/admin/shops/7', '/admin/technicians', '/admin/technicians/1', '/admin/users', '/admin/users/1', '/admin/products', '/admin/orders', '/admin/orders/1', '/admin/repairs', '/admin/repairs/1',
    '/admin/tickets', '/admin/tickets/1', '/admin/reviews', '/admin/refunds', '/admin/settlements', '/admin/commissions', '/admin/catalogue', '/admin/templates', '/admin/reports', '/admin/searches', '/admin/logs', '/admin/settings']);
  console.log(errors.length ? errors.join('\n') : 'NO ERRORS');
  await browser.close();
  process.exit(errors.length ? 1 : 0);
})();

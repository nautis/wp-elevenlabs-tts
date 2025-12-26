/**
 * Quick test to verify the Film Watch DB admin menu structure
 */

const puppeteer = require('puppeteer');

const WP_ADMIN_URL = 'https://tellingtime.com/wp-admin/';
const WP_USERNAME = process.env.WP_USERNAME || '';
const WP_PASSWORD = process.env.WP_PASSWORD || '';

async function runTests() {
    console.log('Launching browser...');
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: false,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1400,900'],
        defaultViewport: { width: 1400, height: 900 }
    });

    try {
        const page = await browser.newPage();

        // Login to WordPress admin
        console.log('Logging in...');
        await page.goto(WP_ADMIN_URL, { waitUntil: 'networkidle2', timeout: 30000 });

        const isLoginPage = await page.$('#loginform');
        if (isLoginPage) {
            await page.type('#user_login', WP_USERNAME);
            await page.type('#user_pass', WP_PASSWORD);
            await Promise.all([
                page.click('#wp-submit'),
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 })
            ]);
        }
        console.log('Logged in successfully!\n');

        // Test each submenu page
        const pages = [
            { slug: 'film-watch-database', name: 'Add New Entry', expected: 'Add New Entry' },
            { slug: 'fwd-manage-records', name: 'Manage Records', expected: 'Manage Records' },
            { slug: 'fwd-ai-parser', name: 'AI Parser Settings', expected: 'AI-Powered Parser Settings' },
            { slug: 'fwd-shortcodes', name: 'Shortcode Usage', expected: 'Shortcode Usage' },
            { slug: 'fwd-maintenance', name: 'Database Maintenance', expected: 'Database Maintenance' }
        ];

        for (const pageInfo of pages) {
            console.log(`Testing: ${pageInfo.name}...`);
            await page.goto(`${WP_ADMIN_URL}admin.php?page=${pageInfo.slug}`, {
                waitUntil: 'networkidle2',
                timeout: 30000
            });

            // Check for PHP errors
            const bodyText = await page.evaluate(() => document.body.innerText);
            if (bodyText.includes('Fatal error') || bodyText.includes('Parse error')) {
                console.log(`  ERROR: PHP error detected on ${pageInfo.name}`);
                console.log(bodyText.substring(0, 500));
                continue;
            }

            // Check page title
            const pageTitle = await page.title();
            const h1Text = await page.$eval('h1', el => el.textContent).catch(() => 'No h1');

            if (h1Text.includes(pageInfo.expected)) {
                console.log(`  PASS: ${pageInfo.name} loaded correctly`);
                console.log(`        Title: ${pageTitle}`);
            } else {
                console.log(`  FAIL: Expected "${pageInfo.expected}" but got "${h1Text}"`);
            }
        }

        console.log('\n=== Menu Structure Test Complete ===\n');

        // Take a screenshot of the menu expanded
        await page.goto(`${WP_ADMIN_URL}admin.php?page=film-watch-database`, { waitUntil: 'networkidle2' });
        await page.hover('#menu-top-level-menu a[href*="film-watch-database"]');
        await new Promise(r => setTimeout(r, 500));
        await page.screenshot({ path: '/tmp/fwd-menu-expanded.png' });
        console.log('Screenshot saved to /tmp/fwd-menu-expanded.png');

        await new Promise(r => setTimeout(r, 3000));

    } catch (error) {
        console.error('TEST FAILED:', error.message);
    } finally {
        await browser.close();
    }
}

runTests();

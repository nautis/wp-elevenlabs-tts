/**
 * TMDB Autocomplete Test
 * Tests the movie and actor search autocomplete in the admin
 */

const puppeteer = require('puppeteer');

// WordPress admin credentials - these should be set via environment variables in production
const WP_ADMIN_URL = process.env.WP_ADMIN_URL || 'https://tellingtime.com/wp-admin/';
const WP_USERNAME = process.env.WP_USERNAME || '';
const WP_PASSWORD = process.env.WP_PASSWORD || '';
const PLUGIN_PAGE = 'options-general.php?page=film-watch-database';

async function runTests() {
    console.log('Launching browser...');
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: false, // Show browser for debugging
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--window-size=1400,900'
        ],
        defaultViewport: {
            width: 1400,
            height: 900
        }
    });

    console.log('Browser launched successfully!\n');

    try {
        const page = await browser.newPage();

        // Enable console logging from the page
        page.on('console', msg => {
            if (msg.type() === 'error') {
                console.log('PAGE ERROR:', msg.text());
            } else if (msg.text().includes('fwd') || msg.text().includes('TMDB') || msg.text().includes('autocomplete')) {
                console.log('PAGE LOG:', msg.text());
            }
        });

        // Check if credentials are provided
        if (!WP_USERNAME || !WP_PASSWORD) {
            console.log('⚠️  WordPress credentials not provided.');
            console.log('Set WP_USERNAME and WP_PASSWORD environment variables to run admin tests.');
            console.log('');
            console.log('Testing public-facing autocomplete API instead...');

            // Test the AJAX endpoint directly (will fail without auth, but shows the endpoint works)
            await page.goto('https://tellingtime.com/wp-admin/admin-ajax.php?action=fwd_tmdb_search_movies&term=matrix', {
                waitUntil: 'networkidle2',
                timeout: 30000
            });

            const responseText = await page.evaluate(() => document.body.textContent);
            console.log('AJAX Response:', responseText.substring(0, 200));

            if (responseText.includes('nonce') || responseText.includes('Unauthorized') || responseText === '0') {
                console.log('✓ AJAX endpoint exists and requires authentication (expected behavior)');
            } else if (responseText.includes('"id"') && responseText.includes('"title"')) {
                console.log('✓ AJAX endpoint returned movie data');
            }

            console.log('\nTo run full admin tests, use:');
            console.log('WP_USERNAME=your_username WP_PASSWORD=your_password node tests/tmdb-autocomplete.test.js');

            await browser.close();
            return;
        }

        // Login to WordPress admin
        console.log('Test 1: Logging into WordPress admin...');
        await page.goto(WP_ADMIN_URL, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Check if we're on the login page
        const isLoginPage = await page.$('#loginform');
        if (isLoginPage) {
            console.log('  Filling login form...');

            // Fill login form
            await page.type('#user_login', WP_USERNAME);
            await page.type('#user_pass', WP_PASSWORD);

            // Check "Remember Me"
            const rememberMe = await page.$('#rememberme');
            if (rememberMe) {
                await page.click('#rememberme');
            }

            // Click submit and wait for navigation
            await Promise.all([
                page.click('#wp-submit'),
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 })
            ]);

            // Check if login was successful
            const currentUrl = page.url();
            if (currentUrl.includes('wp-login.php')) {
                // Still on login page - login failed
                const errorMsg = await page.$eval('#login_error', el => el.textContent).catch(() => 'Unknown error');
                throw new Error('Login failed: ' + errorMsg);
            }

            console.log('✓ Logged in successfully');
            console.log('  Current URL:', currentUrl);
        } else {
            console.log('  Already logged in');
        }
        console.log('');

        // Navigate to Film Watch Database settings
        console.log('Test 2: Navigating to Film Watch Database settings...');
        await page.goto(WP_ADMIN_URL + PLUGIN_PAGE, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Check for PHP errors first
        const bodyText = await page.evaluate(() => document.body.innerText);
        if (bodyText.includes('Fatal error') || bodyText.includes('Parse error') || bodyText.includes('Warning:')) {
            console.log('PHP ERROR detected on page:');
            console.log(bodyText.substring(0, 500));
            await page.screenshot({ path: '/tmp/fwd-test-error.png' });
            throw new Error('PHP error on plugin page');
        }

        // Wait for page to load - try different selectors
        try {
            await page.waitForSelector('.fwd-admin-tabs', { timeout: 10000 });
            console.log('✓ Plugin settings page loaded\n');
        } catch (e) {
            // Take screenshot for debugging
            await page.screenshot({ path: '/tmp/fwd-test-page.png' });
            console.log('Screenshot saved to /tmp/fwd-test-page.png');

            // Log what's on the page
            const pageTitle = await page.title();
            console.log('Page title:', pageTitle);

            const h1 = await page.$eval('h1', el => el.textContent).catch(() => 'No h1 found');
            console.log('H1:', h1);

            throw e;
        }

        // Click on TMDB-Powered tab (it's inside "Add New Entry" tab, using fwd-tab-btn class)
        console.log('Test 3: Clicking TMDB-Powered tab...');
        await page.click('.fwd-tab-btn[data-tab="tmdb"]');
        await new Promise(r => setTimeout(r, 500));

        // Check if TMDB section is visible
        const tmdbSection = await page.$('#fwd-tab-tmdb');
        const isVisible = await tmdbSection.evaluate(el => el.style.display !== 'none');

        if (isVisible) {
            console.log('✓ TMDB-Powered tab is visible\n');
        } else {
            console.log('✗ TMDB-Powered tab is not visible');
        }

        // Test movie autocomplete
        console.log('Test 4: Testing movie autocomplete...');
        const movieInput = await page.$('#fwd-movie-autocomplete');

        if (movieInput) {
            // Clear and type search term
            await movieInput.click({ clickCount: 3 });
            await movieInput.type('The Matrix');

            // Wait for autocomplete dropdown to appear
            await new Promise(r => setTimeout(r, 1000));

            // Check if autocomplete menu appeared
            const autocompleteMenu = await page.$('.ui-autocomplete');
            if (autocompleteMenu) {
                const menuVisible = await autocompleteMenu.evaluate(el => {
                    const style = window.getComputedStyle(el);
                    return style.display !== 'none' && el.children.length > 0;
                });

                if (menuVisible) {
                    console.log('✓ Movie autocomplete dropdown appeared');

                    // Count items
                    const itemCount = await page.$$eval('.ui-autocomplete li', items => items.length);
                    console.log(`  Found ${itemCount} movie results`);

                    // Click first result
                    await page.click('.ui-autocomplete li:first-child');
                    await new Promise(r => setTimeout(r, 500));

                    // Check if hidden fields were populated
                    const movieId = await page.$eval('#fwd-movie-id', el => el.value);
                    const movieTitle = await page.$eval('#fwd-movie-title', el => el.value);
                    const movieYear = await page.$eval('#fwd-movie-year', el => el.value);

                    console.log(`  Selected: ${movieTitle} (${movieYear}) - TMDB ID: ${movieId}`);

                    if (movieId && movieTitle && movieYear) {
                        console.log('✓ Movie data populated correctly\n');
                    } else {
                        console.log('✗ Movie data not fully populated\n');
                    }

                    // Check for poster preview
                    const posterPreview = await page.$('#fwd-movie-poster-preview img');
                    if (posterPreview) {
                        console.log('✓ Poster preview displayed\n');
                    }

                    // Check for cast list
                    await new Promise(r => setTimeout(r, 1500)); // Wait for cast to load
                    const castList = await page.$('#fwd-movie-cast-list');
                    if (castList) {
                        const castCount = await page.$$eval('#fwd-movie-cast-list .button', btns => btns.length);
                        console.log(`✓ Cast list loaded with ${castCount} members\n`);
                    }
                } else {
                    console.log('✗ Movie autocomplete dropdown did not appear');
                }
            } else {
                console.log('✗ Autocomplete menu element not found');
            }
        } else {
            console.log('✗ Movie autocomplete input not found');
        }

        // Test actor autocomplete
        console.log('Test 5: Testing actor autocomplete...');
        const actorInput = await page.$('#fwd-actor-autocomplete');

        if (actorInput) {
            // Clear and type search term
            await actorInput.click({ clickCount: 3 });
            await actorInput.type('Keanu Reeves');

            // Wait for autocomplete dropdown
            await new Promise(r => setTimeout(r, 1000));

            const actorAutocomplete = await page.$('.ui-autocomplete');
            if (actorAutocomplete) {
                const actorMenuVisible = await actorAutocomplete.evaluate(el => {
                    const style = window.getComputedStyle(el);
                    return style.display !== 'none' && el.children.length > 0;
                });

                if (actorMenuVisible) {
                    console.log('✓ Actor autocomplete dropdown appeared');

                    const actorItemCount = await page.$$eval('.ui-autocomplete li', items => items.length);
                    console.log(`  Found ${actorItemCount} actor results`);

                    // Click first result
                    await page.click('.ui-autocomplete li:first-child');
                    await new Promise(r => setTimeout(r, 500));

                    // Check if hidden fields were populated
                    const actorId = await page.$eval('#fwd-actor-id', el => el.value);
                    const actorName = await page.$eval('#fwd-actor-name', el => el.value);

                    console.log(`  Selected: ${actorName} - TMDB ID: ${actorId}`);

                    if (actorId && actorName) {
                        console.log('✓ Actor data populated correctly\n');
                    }
                } else {
                    console.log('✗ Actor autocomplete dropdown did not appear');
                }
            }
        }

        // Final summary
        console.log('='.repeat(50));
        console.log('TMDB Autocomplete Test Complete');
        console.log('='.repeat(50));

        // Keep browser open for inspection
        console.log('\nBrowser will stay open for 10 seconds for inspection...');
        await new Promise(r => setTimeout(r, 10000));

    } catch (error) {
        console.error('\n❌ TEST FAILED:');
        console.error(error.message);
        console.error(error.stack);
    } finally {
        await browser.close();
    }
}

runTests().then(() => {
    console.log('\nTests complete.');
    process.exit(0);
}).catch(error => {
    console.error('\nFatal error:', error);
    process.exit(1);
});

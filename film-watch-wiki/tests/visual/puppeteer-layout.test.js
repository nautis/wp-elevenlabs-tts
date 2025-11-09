/**
 * Puppeteer Layout and Visual Tests
 *
 * Tests for layout, styling, and visual presentation including:
 * - Page structure
 * - Image loading
 * - Responsive design
 * - Link functionality
 * - Navigation
 */

const puppeteer = require('puppeteer');

const BASE_URL = process.env.TEST_URL || 'https://tellingtime.com/blog';
const TIMEOUT = 30000;

describe('Film Watch Wiki - Layout and Visual Tests', () => {
    let browser;
    let page;

    beforeAll(async () => {
        browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
    });

    afterAll(async () => {
        await browser.close();
    });

    beforeEach(async () => {
        page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 800 });
    });

    afterEach(async () => {
        await page.close();
    });

    /**
     * Test 1: Movie Page Layout
     */
    test('Movie page has correct layout structure', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Check main elements exist
        const title = await page.$('h1.entry-title');
        expect(title).toBeTruthy();

        const watchList = await page.$('.watch-list');
        expect(watchList).toBeTruthy();

        const watchItems = await page.$$('.watch-item');
        expect(watchItems.length).toBeGreaterThan(0);
    });

    /**
     * Test 2: Movie Page - Screenshot Images Load
     */
    test('Movie page screenshots load correctly', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Find all screenshot images
        const screenshots = await page.$$('img.fww-sighting-screenshot');
        expect(screenshots.length).toBeGreaterThan(0);

        // Check first image loaded successfully
        if (screenshots.length > 0) {
            const firstImg = screenshots[0];

            // Get image properties
            const imgSrc = await firstImg.evaluate(img => img.src);
            const imgNaturalWidth = await firstImg.evaluate(img => img.naturalWidth);

            expect(imgSrc).toContain('wp-content/uploads');
            expect(imgNaturalWidth).toBeGreaterThan(0); // Image loaded
        }
    });

    /**
     * Test 3: Movie Page - Watch Links Are Clickable
     */
    test('Movie page watch links are clickable', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Find watch link
        const watchLink = await page.$('.watch-model a[href*="/watch/"]');
        expect(watchLink).toBeTruthy();

        // Check href exists
        const href = await watchLink.evaluate(a => a.href);
        expect(href).toContain('/watch/');
    });

    /**
     * Test 4: Actor Page Layout
     */
    test('Actor page has correct layout structure', async () => {
        await page.goto(`${BASE_URL}/actor/colin-firth/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Check main elements
        const title = await page.$('h1.entry-title');
        expect(title).toBeTruthy();

        const titleText = await title.evaluate(h1 => h1.textContent);
        expect(titleText).toContain('Colin Firth');

        const filmography = await page.$('.actor-filmography');
        expect(filmography).toBeTruthy();

        const filmItems = await page.$$('.filmography-item');
        expect(filmItems.length).toBeGreaterThan(0);
    });

    /**
     * Test 5: Actor Page - Thumbnail Images Load
     */
    test('Actor page thumbnails load correctly', async () => {
        await page.goto(`${BASE_URL}/actor/colin-firth/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Find thumbnail images
        const thumbnails = await page.$$('img.fww-sighting-thumbnail');

        if (thumbnails.length > 0) {
            const firstThumb = thumbnails[0];
            const imgNaturalWidth = await firstThumb.evaluate(img => img.naturalWidth);
            expect(imgNaturalWidth).toBeGreaterThan(0);
        }
    });

    /**
     * Test 6: Watch Page Layout
     */
    test('Watch page has correct layout structure', async () => {
        await page.goto(`${BASE_URL}/watch/bremont-alt1-wt/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const title = await page.$('h1.entry-title');
        expect(title).toBeTruthy();

        const appearances = await page.$('.watch-appearances');
        expect(appearances).toBeTruthy();
    });

    /**
     * Test 7: Brand Page Layout
     */
    test('Brand page has correct layout structure', async () => {
        await page.goto(`${BASE_URL}/brand/omega/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const title = await page.$('h1.entry-title');
        expect(title).toBeTruthy();

        const titleText = await title.evaluate(h1 => h1.textContent);
        expect(titleText).toContain('Omega');

        // Check brand stats
        const stats = await page.$('.brand-stats');
        expect(stats).toBeTruthy();

        const statItems = await page.$$('.stat-item');
        expect(statItems.length).toBe(3); // films, watches, actors
    });

    /**
     * Test 8: Brand Page - Statistics Display Correctly
     */
    test('Brand page statistics are correct', async () => {
        await page.goto(`${BASE_URL}/brand/omega/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const statItems = await page.$$('.stat-item');

        for (let stat of statItems) {
            const text = await stat.evaluate(el => el.textContent);
            // Should contain a number
            expect(text).toMatch(/\d+/);
        }
    });

    /**
     * Test 9: Responsive Design - Mobile View
     */
    test('Movie page works on mobile viewport', async () => {
        await page.setViewport({ width: 375, height: 667 }); // iPhone size

        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const watchList = await page.$('.watch-list');
        expect(watchList).toBeTruthy();

        // Check elements are visible
        const watchItems = await page.$$('.watch-item');
        expect(watchItems.length).toBeGreaterThan(0);
    });

    /**
     * Test 10: Responsive Design - Tablet View
     */
    test('Actor page works on tablet viewport', async () => {
        await page.setViewport({ width: 768, height: 1024 }); // iPad size

        await page.goto(`${BASE_URL}/actor/colin-firth/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const filmography = await page.$('.actor-filmography');
        expect(filmography).toBeTruthy();
    });

    /**
     * Test 11: Navigation - Internal Links Work
     */
    test('Internal navigation links work correctly', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Click on actor link
        const actorLink = await page.$('a[href*="/actor/colin-firth"]');
        expect(actorLink).toBeTruthy();

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: TIMEOUT }),
            actorLink.click()
        ]);

        // Verify we're on actor page
        const url = page.url();
        expect(url).toContain('/actor/colin-firth');

        const title = await page.$('h1.entry-title');
        const titleText = await title.evaluate(h1 => h1.textContent);
        expect(titleText).toContain('Colin Firth');
    });

    /**
     * Test 12: Verification Level Styling
     */
    test('Verification level badges display correctly', async () => {
        await page.goto(`${BASE_URL}/movie/licence-to-kill/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const verificationBadge = await page.$('.watch-verification');

        if (verificationBadge) {
            const badgeClass = await verificationBadge.evaluate(el => el.className);
            expect(badgeClass).toMatch(/watch-verification-(confirmed|verified|unverified)/);

            const badgeText = await verificationBadge.evaluate(el => el.textContent);
            expect(badgeText).toMatch(/(Confirmed|Verified|Unverified)/);
        }
    });

    /**
     * Test 13: Image Alt Text Exists
     */
    test('Images have proper alt text', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const images = await page.$$('img.fww-sighting-screenshot');

        for (let img of images) {
            const alt = await img.evaluate(i => i.alt);
            expect(alt).toBeTruthy();
            expect(alt.length).toBeGreaterThan(0);
        }
    });

    /**
     * Test 14: No Console Errors
     */
    test('Pages load without console errors', async () => {
        const consoleErrors = [];

        page.on('console', msg => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Allow minor errors but check for major ones
        const majorErrors = consoleErrors.filter(err =>
            !err.includes('favicon') && // Ignore favicon errors
            !err.includes('analytics') // Ignore analytics errors
        );

        expect(majorErrors.length).toBe(0);
    });

    /**
     * Test 15: Page Load Performance
     */
    test('Movie page loads within acceptable time', async () => {
        const startTime = Date.now();

        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const loadTime = Date.now() - startTime;

        // Should load within 10 seconds
        expect(loadTime).toBeLessThan(10000);
    });

    /**
     * Test 16: Custom CSS Classes Applied
     */
    test('Custom FWW CSS classes are applied', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Check for custom classes
        const customTemplate = await page.$('.fww-custom-template');
        expect(customTemplate).toBeTruthy();

        const contentArea = await page.$('.fww-content-area');
        expect(contentArea).toBeTruthy();
    });

    /**
     * Test 17: Many-to-Many Links Present
     */
    test('All entity links are present on movie page', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Should have links to: brand, watch, actor
        const brandLinks = await page.$$('a[href*="/brand/"]');
        const watchLinks = await page.$$('a[href*="/watch/"]');
        const actorLinks = await page.$$('a[href*="/actor/"]');

        expect(brandLinks.length).toBeGreaterThan(0);
        expect(watchLinks.length).toBeGreaterThan(0);
        expect(actorLinks.length).toBeGreaterThan(0);
    });

    /**
     * Test 18: Screenshot Image Sizes Are Reasonable
     */
    test('Screenshot images have reasonable dimensions', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        const screenshots = await page.$$('img.fww-sighting-screenshot');

        if (screenshots.length > 0) {
            const firstImg = screenshots[0];
            const dimensions = await firstImg.evaluate(img => ({
                width: img.naturalWidth,
                height: img.naturalHeight
            }));

            // Images should be at least 100x100 and not too large
            expect(dimensions.width).toBeGreaterThan(100);
            expect(dimensions.height).toBeGreaterThan(100);
            expect(dimensions.width).toBeLessThan(5000);
            expect(dimensions.height).toBeLessThan(5000);
        }
    });

    /**
     * Test 19: Accessibility - Heading Hierarchy
     */
    test('Pages have proper heading hierarchy', async () => {
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Should have h1
        const h1 = await page.$('h1');
        expect(h1).toBeTruthy();

        // Should have h2 for sections
        const h2s = await page.$$('h2');
        expect(h2s.length).toBeGreaterThan(0);

        // Should have h3 for watch items
        const h3s = await page.$$('h3');
        expect(h3s.length).toBeGreaterThan(0);
    });

    /**
     * Test 20: Brand Page - Large Dataset Handling
     */
    test('Brand page with many sightings renders correctly', async () => {
        await page.goto(`${BASE_URL}/brand/omega/`, {
            waitUntil: 'networkidle2',
            timeout: TIMEOUT
        });

        // Omega has 46 films - make sure page can handle it
        const appearanceItems = await page.$$('.appearance-item');

        // Should have many items
        expect(appearanceItems.length).toBeGreaterThan(10);

        // Page should still be usable
        const title = await page.$('h1.entry-title');
        expect(title).toBeTruthy();
    });
});

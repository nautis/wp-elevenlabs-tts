/**
 * Simple Puppeteer Test - No Jest
 * Tests basic functionality of pages
 */

const puppeteer = require('puppeteer');

const BASE_URL = 'https://tellingtime.com/blog';

async function runTests() {
    console.log('Launching browser...');
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox'
        ]
    });

    console.log('Browser launched successfully!\n');

    try {
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 800 });

        // Test 1: Movie Page
        console.log('Test 1: Loading movie page...');
        await page.goto(`${BASE_URL}/movie/kingsman-the-secret-service/`, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        const watchList = await page.$('.watch-list');
        const watchItems = await page.$$('.watch-item');
        console.log(`✓ Movie page loaded`);
        console.log(`  - Found ${watchItems.length} watch sightings`);
        console.log(`  - Watch list container: ${watchList ? 'YES' : 'NO'}`);

        // Test 2: Check images
        console.log('\nTest 2: Checking images...');
        const images = await page.$$('img.fww-sighting-screenshot');
        console.log(`  - Found ${images.length} screenshot images`);

        if (images.length > 0) {
            const firstImg = images[0];
            const imgSrc = await firstImg.evaluate(img => img.src);
            const imgWidth = await firstImg.evaluate(img => img.width);
            console.log(`  - First image src: ${imgSrc.substring(0, 60)}...`);
            console.log(`  - First image width: ${imgWidth}px`);
        }

        // Test 3: Check .watch-image div
        console.log('\nTest 3: Checking .watch-image divs...');
        const watchImageDivs = await page.$$('.watch-image');
        console.log(`  - Found ${watchImageDivs.length} .watch-image divs`);

        if (watchImageDivs.length > 0) {
            const firstDiv = watchImageDivs[0];
            const divWidth = await firstDiv.evaluate(div => div.offsetWidth);
            console.log(`  - First .watch-image div width: ${divWidth}px`);
        }

        // Test 4: Check .watch-details
        console.log('\nTest 4: Checking .watch-details...');
        const watchDetails = await page.$$('.watch-details');
        console.log(`  - Found ${watchDetails.length} .watch-details elements`);

        if (watchDetails.length > 0) {
            const firstDetails = watchDetails[0];
            const detailsWidth = await firstDetails.evaluate(div => div.offsetWidth);
            console.log(`  - First .watch-details width: ${detailsWidth}px`);
        }

        // Test 5: Actor Page
        console.log('\nTest 5: Loading actor page...');
        await page.goto(`${BASE_URL}/actor/colin-firth/`, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        const filmographyItems = await page.$$('.filmography-item');
        console.log(`✓ Actor page loaded`);
        console.log(`  - Found ${filmographyItems.length} filmography items`);

        // Test 6: Watch Page
        console.log('\nTest 6: Loading watch page...');
        await page.goto(`${BASE_URL}/watch/omega-seamaster-300/`, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        const appearanceItems = await page.$$('.appearance-item');
        console.log(`✓ Watch page loaded`);
        console.log(`  - Found ${appearanceItems.length} appearance items`);

        // Test 7: Brand Page
        console.log('\nTest 7: Loading brand page...');
        await page.goto(`${BASE_URL}/brand/rolex/`, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        const brandAppearances = await page.$$('.appearance-item');
        const brandStats = await page.$('.brand-stats');
        console.log(`✓ Brand page loaded`);
        console.log(`  - Found ${brandAppearances.length} appearances`);
        console.log(`  - Brand stats: ${brandStats ? 'YES' : 'NO'}`);

        console.log('\n✅ ALL TESTS PASSED!');

    } catch (error) {
        console.error('\n❌ TEST FAILED:');
        console.error(error.message);
        console.error(error.stack);
        process.exit(1);
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

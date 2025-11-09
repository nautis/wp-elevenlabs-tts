/**
 * Verify Layout Dimensions
 */

const puppeteer = require('puppeteer');

async function verifyLayout() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-cache']
    });

    const page = await browser.newPage();

    // Disable cache to get fresh content
    await page.setCacheEnabled(false);

    await page.goto('https://tellingtime.com/blog/movie/kingsman-the-secret-service/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    console.log('\n=== LAYOUT VERIFICATION ===\n');

    // Check .watch-image divs
    const watchImageDivs = await page.$$('.watch-image');
    console.log(`✓ Found ${watchImageDivs.length} .watch-image divs`);

    if (watchImageDivs.length > 0) {
        const firstImageDiv = watchImageDivs[0];
        const imageWidth = await firstImageDiv.evaluate(div => div.offsetWidth);
        const imageHeight = await firstImageDiv.evaluate(div => div.offsetHeight);
        console.log(`  - First .watch-image: ${imageWidth}px wide × ${imageHeight}px tall`);
    }

    // Check .watch-details
    const watchDetails = await page.$$('.watch-details');
    console.log(`\n✓ Found ${watchDetails.length} .watch-details elements`);

    if (watchDetails.length > 0) {
        const firstDetails = watchDetails[0];
        const detailsWidth = await firstDetails.evaluate(div => div.offsetWidth);
        const detailsHeight = await firstDetails.evaluate(div => div.offsetHeight);
        console.log(`  - First .watch-details: ${detailsWidth}px wide × ${detailsHeight}px tall`);
    }

    // Check overall .watch-item container
    const watchItems = await page.$$('.watch-item');
    console.log(`\n✓ Found ${watchItems.length} .watch-item containers`);

    if (watchItems.length > 0) {
        const firstItem = watchItems[0];
        const itemWidth = await firstItem.evaluate(div => div.offsetWidth);
        console.log(`  - First .watch-item: ${itemWidth}px wide`);
    }

    // Verify layout is NOT broken (details should be > 100px)
    if (watchDetails.length > 0) {
        const detailsWidth = await watchDetails[0].evaluate(div => div.offsetWidth);
        if (detailsWidth > 100) {
            console.log(`\n✅ LAYOUT IS CORRECT! (.watch-details is ${detailsWidth}px, not 20px)`);
        } else {
            console.log(`\n❌ LAYOUT STILL BROKEN! (.watch-details is only ${detailsWidth}px)`);
        }
    }

    await browser.close();
}

verifyLayout();

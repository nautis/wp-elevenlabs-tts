const puppeteer = require('puppeteer');

async function checkComputedStyles() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 2400, height: 1200 });
    await page.setCacheEnabled(false);

    console.log('Loading War, Inc. page...\n');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Check computed styles for article
    const article = await page.$('article');
    if (article) {
        const styles = await article.evaluate(el => {
            const computed = window.getComputedStyle(el);
            return {
                maxWidth: computed.maxWidth,
                width: computed.width,
                offsetWidth: el.offsetWidth,
                classes: el.className
            };
        });
        console.log('ARTICLE ELEMENT:');
        console.log(`  Classes: ${styles.classes}`);
        console.log(`  Computed max-width: ${styles.maxWidth}`);
        console.log(`  Computed width: ${styles.width}`);
        console.log(`  Actual offsetWidth: ${styles.offsetWidth}px\n`);
    }

    // Check body classes
    const bodyClasses = await page.evaluate(() => {
        return document.body.className;
    });
    console.log('BODY CLASSES:');
    console.log(`  ${bodyClasses}\n`);

    // Check if our CSS file is loaded
    const cssLoaded = await page.evaluate(() => {
        const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        return links.find(link => link.href.includes('frontend.css'));
    });
    console.log('FRONTEND.CSS LOADED:');
    console.log(`  ${cssLoaded ? 'YES - ' + cssLoaded.href : 'NO'}\n`);

    // Check main element
    const main = await page.$('#main');
    if (main) {
        const styles = await main.evaluate(el => {
            const computed = window.getComputedStyle(el);
            return {
                maxWidth: computed.maxWidth,
                width: computed.width,
                offsetWidth: el.offsetWidth
            };
        });
        console.log('MAIN ELEMENT (#main):');
        console.log(`  Computed max-width: ${styles.maxWidth}`);
        console.log(`  Computed width: ${styles.width}`);
        console.log(`  Actual offsetWidth: ${styles.offsetWidth}px\n`);
    }

    await browser.close();
}

checkComputedStyles();

/**
 * Check Console Errors
 */

const puppeteer = require('puppeteer');

async function checkErrors() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    const consoleMessages = [];

    page.on('console', msg => {
        consoleMessages.push({
            type: msg.type(),
            text: msg.text()
        });
    });

    await page.goto('https://tellingtime.com/blog/movie/kingsman-the-secret-service/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    console.log('\nConsole Messages:');
    console.log('=================');
    consoleMessages.forEach(msg => {
        console.log(`[${msg.type.toUpperCase()}] ${msg.text}`);
    });

    const errors = consoleMessages.filter(m => m.type === 'error');
    console.log(`\nTotal errors: ${errors.length}`);

    await browser.close();
}

checkErrors();

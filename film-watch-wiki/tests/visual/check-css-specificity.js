const puppeteer = require('puppeteer');

async function checkCSSSpecificity() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 2400, height: 1200 });
    await page.setCacheEnabled(false);

    console.log('Loading page...\n');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Check what CSS is setting the article width
    const cssInfo = await page.evaluate(() => {
        const article = document.querySelector('article');
        if (!article) return null;

        const styles = window.getComputedStyle(article);

        // Get all matching CSS rules for the article
        const allRules = [];
        for (let sheet of document.styleSheets) {
            try {
                for (let rule of sheet.cssRules || sheet.rules || []) {
                    if (rule.selectorText && article.matches(rule.selectorText)) {
                        const hasWidth = rule.style.width || rule.style.maxWidth;
                        if (hasWidth) {
                            allRules.push({
                                selector: rule.selectorText,
                                width: rule.style.width || '',
                                maxWidth: rule.style.maxWidth || '',
                                href: sheet.href || 'inline'
                            });
                        }
                    }
                }
            } catch (e) {
                // CORS or other issues, skip
            }
        }

        return {
            computedWidth: styles.width,
            computedMaxWidth: styles.maxWidth,
            matchingRules: allRules
        };
    });

    console.log('COMPUTED STYLES:');
    console.log(`  width: ${cssInfo.computedWidth}`);
    console.log(`  max-width: ${cssInfo.computedMaxWidth}\n`);

    console.log('MATCHING CSS RULES WITH WIDTH/MAX-WIDTH:');
    cssInfo.matchingRules.forEach((rule, index) => {
        console.log(`\n  ${index + 1}. ${rule.selector}`);
        if (rule.width) console.log(`     width: ${rule.width}`);
        if (rule.maxWidth) console.log(`     max-width: ${rule.maxWidth}`);
        const shortHref = rule.href.replace('https://tellingtime.com/', '');
        console.log(`     from: ${shortHref.substring(0, 60)}`);
    });

    await browser.close();
}

checkCSSSpecificity();

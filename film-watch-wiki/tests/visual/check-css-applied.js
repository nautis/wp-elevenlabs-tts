const puppeteer = require('puppeteer');

async function checkCSSApplied() {
    const browser = await puppeteer.launch({
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: false, // Keep browser open so we can see
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    await page.setCacheEnabled(false);

    console.log('Loading page...\n');
    await page.goto('https://tellingtime.com/blog/movie/war-inc/', {
        waitUntil: 'networkidle2',
        timeout: 30000
    });

    // Check all CSS rules affecting the poster
    const cssDetails = await page.evaluate(() => {
        const img = document.querySelector('.fww-movie-poster');
        if (!img) return null;

        const computed = window.getComputedStyle(img);

        // Get all matching CSS rules
        const matchingRules = [];
        for (let sheet of document.styleSheets) {
            try {
                for (let rule of sheet.cssRules || []) {
                    if (rule.selectorText && img.matches(rule.selectorText)) {
                        const width = rule.style.width;
                        const maxWidth = rule.style.maxWidth;
                        const minWidth = rule.style.minWidth;
                        if (width || maxWidth || minWidth) {
                            matchingRules.push({
                                selector: rule.selectorText,
                                width: width || '',
                                maxWidth: maxWidth || '',
                                minWidth: minWidth || '',
                                sheet: sheet.href || 'inline'
                            });
                        }
                    }
                }
            } catch (e) {
                // CORS
            }
        }

        return {
            offsetWidth: img.offsetWidth,
            offsetHeight: img.offsetHeight,
            computedWidth: computed.width,
            computedHeight: computed.height,
            computedMaxWidth: computed.maxWidth,
            computedMinWidth: computed.minWidth,
            matchingRules: matchingRules
        };
    });

    console.log('POSTER CSS ANALYSIS:');
    console.log('====================');
    console.log(`Rendered size: ${cssDetails.offsetWidth}px × ${cssDetails.offsetHeight}px`);
    console.log(`\nComputed values:`);
    console.log(`  width: ${cssDetails.computedWidth}`);
    console.log(`  min-width: ${cssDetails.computedMinWidth}`);
    console.log(`  max-width: ${cssDetails.computedMaxWidth}`);

    console.log(`\nMatching CSS rules:`);
    cssDetails.matchingRules.forEach((rule, i) => {
        console.log(`\n${i + 1}. ${rule.selector}`);
        if (rule.width) console.log(`   width: ${rule.width}`);
        if (rule.minWidth) console.log(`   min-width: ${rule.minWidth}`);
        if (rule.maxWidth) console.log(`   max-width: ${rule.maxWidth}`);
        const shortSheet = rule.sheet.replace('https://tellingtime.com/', '');
        console.log(`   from: ${shortSheet.substring(0, 70)}`);
    });

    console.log('\n\nBrowser will stay open for 30 seconds so you can inspect...');
    await new Promise(resolve => setTimeout(resolve, 30000));

    await browser.close();
}

checkCSSApplied();

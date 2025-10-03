// getUnrepliedReviews.js

const puppeteer = require('puppeteer');
const fs = require('fs');

const REVIEWS_URL = 'https://business.google.com/u/2/reviews';

(async () => {
    const browser = await puppeteer.launch({
        headless: false,
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

    // Load cookies
    const cookies = JSON.parse(fs.readFileSync('cookies.json', 'utf8'));
    await page.setCookie(...cookies);

    // Navigate to Google reviews page directly
    await page.goto(REVIEWS_URL, { waitUntil: 'networkidle2' });

    let allUnrepliedReviews = [];
    let currentPage = 1;

    // Function to extract unreplied reviews
    const extractUnrepliedReviews = async () => {
        return await page.evaluate(() => {
            const reviews = Array.from(document.querySelectorAll('.GYpYWe'));
            const unreplied = [];

            reviews.forEach(review => {
                const replyButton = review.querySelector('.VfPpkd-vQzf8d');
                if (replyButton && replyButton.innerText.toLowerCase() === 'reply') {
                    const reviewerName = review.querySelector('.z2S9Hc a')?.innerText || 'Unknown';
                    const reviewTime = review.querySelector('.Wxf3Bf')?.innerText || 'Unknown';
                    const fullTextElement = review.querySelector('.oiQd1c');
                    const reviewText = fullTextElement?.innerText || 'No review text';
                    const rating = review.querySelectorAll('.DPvwYc.L12a3c.z3FsAc').length;

                    unreplied.push({
                        reviewerName,
                        reviewTime,
                        reviewText,
                        rating
                    });
                }
            });

            return unreplied;
        });
    };

    // Navigate through pages and stop when unreplied reviews are found
    while (true) {
        console.log(`Extracting reviews from page ${currentPage}...`);
        let unrepliedReviews = await extractUnrepliedReviews();

        if (unrepliedReviews.length > 0) {
            console.log(`Found ${unrepliedReviews.length} unreplied reviews on page ${currentPage}.`);
            allUnrepliedReviews.push(...unrepliedReviews);
            break; // Stop when we find unreplied reviews
        } else {
            console.log(`No unreplied reviews found on page ${currentPage}.`);
        }

        // Check if there's a "Next" button and navigate to the next page
        const nextButtonSelector = 'button[aria-label="Next"]';
        const nextButtonExists = await page.evaluate(selector => {
            const nextButton = document.querySelector(selector);
            return nextButton && !nextButton.disabled;
        }, nextButtonSelector);

        if (nextButtonExists) {
            console.log(`Navigating to the next page (${currentPage + 1})...`);
            await page.click(nextButtonSelector);
            await new Promise(resolve => setTimeout(resolve, 3000));
            currentPage++;
        } else {
            console.log('No "Next" button found or it is disabled. Ending script.');
            break;
        }
    }

    console.log('✅ All unreplied reviews:', allUnrepliedReviews);
})();

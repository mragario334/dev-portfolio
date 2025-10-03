// countReviewsByLocation.js
// Count Google reviews per location (within the last N days), handling pagination.

const puppeteer = require('puppeteer');
const fs = require('fs');

const REVIEWS_URL = 'https://business.google.com/u/2/reviews';
const TIME_WINDOW_DAYS = 3;

// Address mapping
const ADDRESS_MAP = [
  { match: '301 North Peters Street',         location: 'French Quarter' },
  { match: '6215 South Miro Street',          location: 'Uptown' },
  { match: '2004 Metairie Rd',                location: 'Metairie Road' },
  { match: '2220 Logan Boulevard North',      location: 'Logan Landings' },
  { match: '4255 Tamiami Trail North',        location: 'Park Shore Plaza' },
  { match: '411-1 North Carrollton Avenue',   location: 'Mid-City' },
];

const counts = {
  'Metairie Road':   { count: 0, stars: 0 },
  'French Quarter':  { count: 0, stars: 0 },
  'Uptown':          { count: 0, stars: 0 },
  'Mid-City':        { count: 0, stars: 0 },
  'Logan Landings':  { count: 0, stars: 0 },
  'Park Shore Plaza':{ count: 0, stars: 0 },
  _unknown:          { count: 0, stars: 0 },
};

function parseGoogleReviewTime(text) {
  if (!text) return null;
  const s = text.trim().toLowerCase();
  const now = new Date();

  let m = s.match(/(\d+)\s*min/);
  if (m) return new Date(now.getTime() - parseInt(m[1],10) * 60 * 1000);

  m = s.match(/(\d+)\s*hour/);
  if (m) return new Date(now.getTime() - parseInt(m[1],10) * 60 * 60 * 1000);

  if (s.includes('yesterday')) {
    return new Date(now.getTime() - 1 * 24 * 60 * 60 * 1000);
  }

  m = s.match(/(\d+)\s*day/);
  if (m) return new Date(now.getTime() - parseInt(m[1],10) * 24 * 60 * 60 * 1000);

  m = s.match(/(\d+)\s*week/);
  if (m) return new Date(now.getTime() - parseInt(m[1],10) * 7 * 24 * 60 * 60 * 1000);

  const parsed = Date.parse(text);
  if (!Number.isNaN(parsed)) return new Date(parsed);

  return null;
}

function withinLastNDays(date, n) {
  if (!date) return false;
  const now = new Date();
  return (now - date) <= n * 24 * 60 * 60 * 1000;
}

function normalize(str) {
  return (str || '').replace(/\s+/g, ' ').trim();
}

function mapAddressToLocation(addressText) {
  const addr = normalize(addressText);
  for (const { match, location } of ADDRESS_MAP) {
    if (addr.includes(match)) return location;
  }
  return null;
}

// Extract reviews from one page
async function extractReviews(page) {
  return page.evaluate(() => {
    const blocks = Array.from(document.querySelectorAll('.DsOcnf'));
    const results = [];
    blocks.forEach(block => {
      const addrText = block.querySelector('.ijHgsc')?.textContent.trim() || '';
      const reviews = Array.from(block.querySelectorAll('.GYpYWe')).map(card => {
        const timeText = card.querySelector('.Wxf3Bf')?.textContent.trim() || '';
        // Count filled stars for this review
        const rating = card.querySelectorAll('.DPvwYc.L12a3c.z3FsAc').length; // 0-5
        return { timeText, addrText, rating };
      });
      results.push(...reviews);
    });
    return results;
  });
}

// MAIN
(async () => {
  const browser = await puppeteer.launch({
    headless: false,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const page = await browser.newPage();
  const cookies = JSON.parse(fs.readFileSync('cookies.json', 'utf8'));
  await page.setCookie(...cookies);

  await page.goto(REVIEWS_URL, { waitUntil: 'networkidle2' });

  let currentPage = 1;
  let totalWithinWindow = 0;

  while (true) {
    console.log(`Extracting reviews from page ${currentPage}...`);

    // ✅ call the correct extractor
    const reviews = await extractReviews(page);

    let lastReviewDate = null;

    for (const { timeText, addrText, rating } of reviews) {
      const when = parseGoogleReviewTime(timeText);
      if (!when) continue;

      if (withinLastNDays(when, TIME_WINDOW_DAYS)) {
        const loc = mapAddressToLocation(addrText);
        const bucket = (loc && counts[loc]) ? counts[loc] : counts._unknown;
        bucket.count += 1;

        // Defensive: fall back to 0 if selector misses for some reason
        const stars = Number.isFinite(rating) && rating >= 0 ? rating : 0;
        bucket.stars += stars;

        totalWithinWindow++;
      }

      lastReviewDate = when;
    }

    // --- Early stopping logic ---
    if (lastReviewDate && !withinLastNDays(lastReviewDate, TIME_WINDOW_DAYS)) {
      console.log(`Stopping early: last review on page ${currentPage} is older than ${TIME_WINDOW_DAYS} days.`);
      break;
    }

    // Otherwise, check for "Next"
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
      console.log('No "Next" button found or it is disabled. Done.');
      break;
    }
  }

  console.log('\n===== REVIEW COUNTS & AVERAGES (last', TIME_WINDOW_DAYS, 'day(s)) =====');
  for (const loc of Object.keys(counts)) {
    const { count, stars } = counts[loc];
    if (loc === '_unknown') {
      if (count > 0) {
        const avg = (stars / count).toFixed(2);
        console.log(`(Unmapped address): ${count} reviews, avg ${avg} stars`);
      }
      continue;
    }
    if (count > 0) {
      const avg = (stars / count).toFixed(2);
      console.log(`${loc}: ${count} reviews, avg ${avg} stars`);
    } else {
      console.log(`${loc}: 0 reviews`);
    }
  }
  console.log('TOTAL within window:', totalWithinWindow);

  await browser.close();
})();

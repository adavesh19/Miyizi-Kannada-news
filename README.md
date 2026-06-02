# MIYIZE Kannada News

Fast Kannada news website starter for Hostinger-style PHP hosting.
# MIYIZE Kannada News - V4 (Node.js Edition)

This is the fully automated, high-performance **MIYIZE Kannada News** portal. It features a premium 3D Glassmorphism UI, a real-time auto-writing engine (generating 10 paragraphs per article), mid-article AdSense injection, and a secure Admin Dashboard.

## Features

- **No Database Required**: Uses flat-file JSON for blazing fast speeds.
- **Auto-Writing Engine**: Generates 8-10 original paragraphs per article using templates.
- **Auto-Refresh**: Fetches new news automatically in the background when users visit (no cron required).
- **Auto-Cleanup**: Automatically deletes articles older than 7 days to save server space.
- **Admin Panel**: Secure dashboard at `/admin` to edit, publish, and audit SEO.

---

## 🚀 How to Deploy on Hostinger Premium

Since this is a high-tech dynamic application, it runs on **Node.js**, not standard PHP. Hostinger Premium supports Node.js seamlessly.

1. **Upload Files**: Upload the entire project folder to Hostinger.
2. **Node.js Setup**: 
   - In your Hostinger panel, search for **Node.js** or **App Server**.
   - Set the Application Startup File to `local-server.js`.
   - Ensure the Node.js version is **18.x or higher**.
3. **Set Environment Variables (Tags)** (in Hostinger or Vercel Settings):
   - `MIYIZE_ADMIN_PASS`: Set this to a strong password for your `/admin` panel (default is `miyize2024`).
   - `MIYIZE_GEMINI_KEY` or `GEMINI_API_KEY`: Google Gemini API key (from Google AI Studio). **AI Agent Details**: When this key is provided, our agent intercepts RSS items. Even if an RSS feed only gives a title and a very short 1-4 line summary, the agent will use its general knowledge to write a full, long, and rich news article (400 to 800 words, 5-8 paragraphs) dynamically without adding any meta-explanations.
   - `MIYIZE_GOOGLE_ANALYTICS_ID`: Google Analytics Measurement ID (e.g. `G-V293C5B3RY`). This will automatically inject the `gtag.js` script to track site metrics, user flow, and trends.
   - `PORT`: Hostinger usually handles this, but the app defaults to 8080 if not set.
4. **Google Trends**: The agent will automatically fetch data from Google Trends India daily feeds and populate the **ಟ್ರೆಂಡಿಂಗ್** (Trends) category.
5. **Start the App**: Click **Start App** in Hostinger. The app will immediately begin fetching real-time news and will run continuously!

## 💰 Monetization (AdSense)

Once your Google AdSense is approved:
1. Open `local-server.js`.
2. Find the `adSlot()` function (around line 542).
3. Replace the placeholder HTML with your actual `<script>` tags from Google AdSense. 
4. Ads will automatically appear perfectly spaced in the middle of all articles!

## Run Locally

If PHP is not installed, run the local Node preview server:

```bash
node local-server.js
```

Then open the URL printed in the terminal, usually `http://127.0.0.1:8080`.

On Windows you can also double-click `start-local.bat`.

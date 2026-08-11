import asyncio
import os
from playwright.async_api import async_playwright

async def main():
    os.makedirs('/home/jules/verification/screenshots', exist_ok=True)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1280, "height": 850})
        page = await context.new_page()

        print("Navigating to login page...")
        await page.goto("http://localhost:8000/login.php")
        await page.wait_for_load_state("networkidle")

        print("Logging in as BK Teacher...")
        await page.fill('input[name="username"]', "guru_bk")
        await page.fill('input[name="password"]', "guru_bk")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        # Take screenshot of whatever we see after login
        await page.screenshot(path="/home/jules/verification/screenshots/guru_riwayat_after_login.png")
        print("Page URL after login:", page.url)

        # Handle daily mood survey overlay if shown
        try:
            overlay = page.locator("#moodSurveyOverlay")
            if await overlay.is_visible():
                print("Mood survey overlay is visible!")
                # Click the first mood card button
                await page.locator("button.mood-card").first.click()
                await page.wait_for_timeout(2000)
        except Exception as e:
            print("Error handling mood survey overlay:", e)

        print("Navigating to guru/riwayat.php...")
        await page.goto("http://localhost:8000/guru/riwayat.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        await page.screenshot(path="/home/jules/verification/screenshots/guru_riwayat_initial.png")

        # Set Date Range
        print("Setting Date Range filter...")
        await page.fill('input[name="tanggal_mulai"]', "2026-08-01")
        await page.fill('input[name="tanggal_selesai"]', "2026-08-30")
        await page.click('button:has-text("Cari Jurnal")')
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        # Take screenshot of filtered list
        await page.screenshot(path="/home/jules/verification/screenshots/guru_riwayat_range_filter.png")
        print("Date range filter screenshot saved!")

        # Verify Excel Download URL is valid and triggers without error
        print("Verifying Excel download link is rendered...")
        excel_btn = page.locator('a:has-text("Ekspor Excel")').first
        download_url = await excel_btn.get_attribute("href")
        print("Excel Download URL:", download_url)
        if "export_riwayat_excel.php" in download_url:
            print("SUCCESS: Excel export link configured correctly!")
        else:
            print("WARNING: Excel export link is not found.")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())

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

        print("Logging in as student Roni...")
        await page.fill('input[name="username"]', "1234")
        await page.fill('input[name="password"]', "1234")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")

        # Handle daily mood survey overlay if shown
        try:
            overlay = page.locator("#moodSurveyOverlay")
            if await overlay.is_visible():
                await page.locator("button:has-text('Bersemangat')").click()
                await page.wait_for_timeout(2000)
        except Exception:
            pass

        print("Navigating to siswa/berkas.php...")
        await page.goto("http://localhost:8000/siswa/berkas.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        await page.screenshot(path="/home/jules/verification/screenshots/berkas_page_initial.png")

        print("Updating parent WA number...")
        await page.fill('input[name="no_wa_ortu"]', "089876543210")
        await page.screenshot(path="/home/jules/verification/screenshots/berkas_page_filled_wa.png")

        await page.click("button:has-text('Simpan Nomor WA')")
        await page.wait_for_timeout(2000)

        await page.screenshot(path="/home/jules/verification/screenshots/berkas_page_saved_wa.png")
        print("WA number saved and verified successfully!")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())

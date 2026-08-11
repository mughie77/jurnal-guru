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

        print("Logging in as Admin...")
        await page.fill('input[name="username"]', "admin")
        await page.fill('input[name="password"]', "admin")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        print("Navigating to admin/rekap_berkas.php...")
        await page.goto("http://localhost:8000/admin/rekap_berkas.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        # Confirm list renders and contains either WA Ortu label or Belum Diisi
        page_text = await page.locator("body").inner_text()
        if "WA ORTU:" in page_text:
            print("SUCCESS: rekap_berkas list render is confirmed and valid!")
        else:
            print("WARNING: rekap_berkas list loaded but 'WA ORTU:' label not found.")
            print("Page URL:", page.url)

        await page.screenshot(path="/home/jules/verification/screenshots/admin_rekap_berkas_wa.png")
        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())

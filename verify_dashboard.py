import asyncio
import os
from playwright.async_api import async_playwright

async def main():
    os.makedirs('/home/jules/verification/screenshots', exist_ok=True)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1280, "height": 900})
        page = await context.new_page()

        print("Logging in as Admin...")
        await page.goto("http://localhost:8000/login.php")
        await page.fill('input[name="username"]', "admin")
        await page.fill('input[name="password"]', "admin")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")

        # Navigate to Admin dashboard
        print("Navigating to admin/index.php...")
        await page.goto("http://localhost:8000/admin/index.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(3000) # Wait for Chart.js rendering

        # Take screenshot of the new dashboard
        print("Capturing dashboard screenshot...")
        await page.screenshot(path="/home/jules/verification/screenshots/admin_dashboard_new.png")

        await browser.close()
        print("Dashboard screenshot captured successfully.")

if __name__ == "__main__":
    asyncio.run(main())

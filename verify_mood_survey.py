import asyncio
import os
from playwright.async_api import async_playwright

async def main():
    os.makedirs('/home/jules/verification/screenshots', exist_ok=True)

    async with async_playwright() as p:
        # Launch browser headless
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1280, "height": 800})
        page = await context.new_page()

        # 1. Access login page
        print("Navigating to login page...")
        await page.goto("http://localhost:8000/login.php")
        await page.wait_for_load_state("networkidle")

        # 2. Log in as Student Roni Wijaya
        print("Logging in as student Roni...")
        await page.fill('input[name="username"]', "1234")
        await page.fill('input[name="password"]', "1234")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")

        # Confirm overlay is visible
        print("Verifying mood survey overlay on student dashboard...")
        overlay = page.locator("#moodSurveyOverlay")
        await overlay.wait_for(state="visible", timeout=10000)

        # Take screenshot of the forced overlay popup
        print("Capturing screenshot of mood survey pop-up...")
        await page.screenshot(path="/home/jules/verification/screenshots/mood_survey_visible.png")

        # Select "Bersemangat" card (instant submit!)
        print("Selecting Bersemangat mood (instant submit)...")
        # Locating the mood button with Bersemangat text
        bersemangat_btn = page.locator("button:has-text('Bersemangat')")
        await bersemangat_btn.click()

        # Wait for SweetAlert2 popup which is instantly shown
        swal = page.locator(".swal2-popup")
        await swal.wait_for(state="visible", timeout=5000)
        print("SweetAlert2 popup visible.")
        await page.screenshot(path="/home/jules/verification/screenshots/mood_survey_success_swal.png")

        # Close SweetAlert (it dismisses automatically or can be clicked, wait for overlay to vanish)
        await page.wait_for_timeout(3000)
        await overlay.wait_for(state="hidden", timeout=5000)
        print("Overlay successfully removed after completion!")

        # Take dashboard after survey removal
        await page.screenshot(path="/home/jules/verification/screenshots/dashboard_after_mood_survey.png")

        # 3. Log out and Log in as Admin to review analytics
        print("Logging out student...")
        await page.goto("http://localhost:8000/logout.php")
        await page.wait_for_load_state("networkidle")

        print("Logging in as Admin...")
        await page.goto("http://localhost:8000/login.php")
        await page.fill('input[name="username"]', "admin")
        await page.fill('input[name="password"]', "admin") # standard admin password
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")

        # Navigate to rekap_mood
        print("Accessing admin/rekap_mood.php...")
        await page.goto("http://localhost:8000/admin/rekap_mood.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(3000) # Wait for Chart.js rendering

        # Verify the submitted mood row in log list
        print("Confirming Roni Wijaya's entry is logged in the admin table...")
        table_content = await page.locator("body").inner_text()
        if "Roni Wijaya" in table_content:
            print("SUCCESS: Roni Wijaya's mood record is verified in the admin dashboard!")
        else:
            print("WARNING: Roni Wijaya's record not found in logs list!")

        # Take analytics dashboard screenshot
        await page.screenshot(path="/home/jules/verification/screenshots/admin_mood_analytics.png")

        await browser.close()
        print("All automated verification steps finished successfully.")

if __name__ == "__main__":
    asyncio.run(main())

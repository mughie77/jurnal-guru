import asyncio
from playwright.async_api import async_playwright
import os

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page()

        # Navigate to the standalone verification page
        await page.goto("http://localhost:8000/verify_card_standalone.php")
        await page.wait_for_timeout(2000) # Give time for barcode JS to run

        # Take a screenshot of the card
        card = await page.query_selector("#printableCard")
        if card:
            # Create verification directory if it doesn't exist
            os.makedirs("/home/jules/verification", exist_ok=True)
            await card.screenshot(path="/home/jules/verification/id_card_v3.png")
            print("Screenshot saved to /home/jules/verification/id_card_v3.png")
        else:
            print("Card not found")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())

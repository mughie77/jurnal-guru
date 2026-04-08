import asyncio
import os
import subprocess
import time
from playwright.async_api import async_playwright

async def main():
    server_process = subprocess.Popen(['php', '-S', 'localhost:8081'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    time.sleep(2)

    try:
        async with async_playwright() as p:
            browser = await p.chromium.launch()
            page = await browser.new_page(viewport={'width': 800, 'height': 600})
            await page.goto('http://localhost:8081/siswa/kartu_verify_final.php')
            await page.wait_for_selector('.card-id')
            await page.screenshot(path='verification_final_updated.png')
            print("Screenshot saved to verification_final_updated.png")
            await browser.close()
    finally:
        server_process.terminate()

if __name__ == "__main__":
    asyncio.run(main())

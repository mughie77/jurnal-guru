from playwright.sync_api import sync_playwright

def verify_v3():
    with sync_playwright() as p:
        browser = p.chromium.launch()
        page = browser.new_page()
        page.goto("http://localhost:8000/verification/verify_card_standalone.php")
        page.wait_for_selector(".card-id")
        # Ensure styles are loaded
        page.wait_for_timeout(1000)
        page.screenshot(path="/app/verification/id_card_v12.png")
        browser.close()

if __name__ == "__main__":
    verify_v3()

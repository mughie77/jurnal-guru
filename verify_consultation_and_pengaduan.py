import asyncio
import os
from playwright.async_api import async_playwright

async def main():
    os.makedirs('/home/jules/verification/screenshots', exist_ok=True)

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1280, "height": 850})
        page = await context.new_page()

        # ===================================================
        # 1. Student Login
        # ===================================================
        print("Navigating to login page...")
        await page.goto("http://localhost:8000/login.php")
        await page.wait_for_load_state("networkidle")

        print("Logging in as student Roni...")
        await page.fill('input[name="username"]', "1234")
        await page.fill('input[name="password"]', "1234")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")

        # Handle daily mood survey overlay if shown
        print("Checking for mood survey overlay...")
        try:
            overlay = page.locator("#moodSurveyOverlay")
            if await overlay.is_visible():
                print("Mood survey overlay visible, completing it...")
                bersemangat_btn = page.locator("button:has-text('Bersemangat')")
                await bersemangat_btn.click()
                await page.wait_for_timeout(3000)
                print("Mood survey completed.")
        except Exception as e:
            print("No mood survey overlay displayed.")

        # Capture Student Dashboard
        print("Capturing Student Dashboard screenshot...")
        await page.screenshot(path="/home/jules/verification/screenshots/siswa_dashboard.png")

        # ===================================================
        # 2. Submit Pengaduan (Complaint)
        # ===================================================
        print("Navigating to siswa/pengaduan.php...")
        await page.goto("http://localhost:8000/siswa/pengaduan.php")
        await page.wait_for_load_state("networkidle")

        # Mock Geolocation values for testing
        print("Filling pengaduan form...")
        await page.fill('textarea[name="keterangan"]', "Ada kejadian perundungan di koridor kelas X IPA.")

        # Inject mock coordinates directly into hidden fields for CLI testing
        await page.evaluate("document.getElementById('pengaduanLat').value = '-7.9135';")
        await page.evaluate("document.getElementById('pengaduanLng').value = '113.8217';")
        await page.evaluate("document.getElementById('pengaduanAccuracy').value = '15';")

        # Capture Pengaduan page
        await page.screenshot(path="/home/jules/verification/screenshots/siswa_pengaduan_page.png")

        print("Submitting pengaduan...")
        await page.click('#submitPengaduanBtn')
        await page.wait_for_timeout(2000)

        # Capture SweetAlert2
        await page.screenshot(path="/home/jules/verification/screenshots/siswa_pengaduan_success.png")

        # ===================================================
        # 3. Start Consultation BK
        # ===================================================
        print("Navigating to siswa/konsultasi.php...")
        await page.goto("http://localhost:8000/siswa/konsultasi.php")
        await page.wait_for_load_state("networkidle")

        print("Opening New Consultation Modal...")
        await page.click("button:has-text('Konsultasi Baru')")
        await page.wait_for_timeout(1000)

        print("Filling New Consultation Modal Form...")
        await page.evaluate("document.querySelector('select[name=\"guru_id\"]').value = '1';")
        await page.evaluate("document.querySelector('input[name=\"subjek\"]').value = 'Sulit Konsentrasi Belajar';")
        await page.evaluate("document.querySelector('textarea[name=\"pesan\"]').value = 'Halo Pak Budiono, akhir-akhir ini saya merasa sulit berkonsentrasi saat belajar mandiri di rumah.';")
        await page.wait_for_timeout(500)

        await page.screenshot(path="/home/jules/verification/screenshots/siswa_konsultasi_modal.png")

        print("Submitting consultation form...")
        await page.evaluate("document.querySelector('#newConsultationModal form').submit();")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        print("Siswa consultation thread opened. Sending follow-up message...")
        await page.fill('textarea[name="pesan"]', "Mohon sarannya ya Pak, terima kasih banyak.")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        await page.screenshot(path="/home/jules/verification/screenshots/siswa_konsultasi_thread.png")

        # ===================================================
        # 4. View Kontak BK & Wali
        # ===================================================
        print("Navigating to siswa/kontak.php...")
        await page.goto("http://localhost:8000/siswa/kontak.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)
        await page.screenshot(path="/home/jules/verification/screenshots/siswa_kontak_directory.png")

        # ===================================================
        # 5. Logout Student
        # ===================================================
        print("Logging out student...")
        await page.goto("http://localhost:8000/logout.php")
        await page.wait_for_load_state("networkidle")

        # ===================================================
        # 6. BK Teacher Login
        # ===================================================
        print("Logging in as BK Teacher Budiono...")
        await page.goto("http://localhost:8000/login.php")
        await page.fill('input[name="username"]', "guru_bk")
        await page.fill('input[name="password"]', "guru_bk")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")

        # Handle daily mood survey overlay if shown for BK Teacher
        print("Checking for mood survey overlay for BK Teacher...")
        try:
            overlay = page.locator("#moodSurveyOverlay")
            if await overlay.is_visible():
                print("Mood survey overlay visible for BK Teacher, completing it...")
                bersemangat_btn = page.locator("button:has-text('Bersemangat')")
                await bersemangat_btn.click()
                await page.wait_for_timeout(3000)
                print("Mood survey completed for BK Teacher.")
        except Exception as e:
            print("No mood survey overlay displayed.")

        print("Capturing BK Teacher Dashboard...")
        await page.screenshot(path="/home/jules/verification/screenshots/guru_bk_dashboard.png")

        # ===================================================
        # 7. Reply to Consultation
        # ===================================================
        print("Navigating to guru/konsultasi.php...")
        await page.goto("http://localhost:8000/guru/konsultasi.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        # Open active chat thread
        print("Opening student consultation thread...")
        await page.click("a:has-text('Sulit Konsentrasi Belajar')")
        await page.wait_for_timeout(1500)
        await page.screenshot(path="/home/jules/verification/screenshots/guru_bk_reply_interface.png")

        print("Replying to Roni...")
        await page.fill('textarea[name="pesan"]', "Halo Roni, bapak sangat senang kamu mau bercerita. Coba atur waktu belajarmu dengan teknik Pomodoro, istirahat 5 menit setiap belajar 25 menit. Besok kita mengobrol di ruang BK ya.")
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(2000)

        await page.screenshot(path="/home/jules/verification/screenshots/guru_bk_reply_sent.png")

        # ===================================================
        # 8. View Student Complaints (Rekap Pengaduan)
        # ===================================================
        print("Navigating to guru/rekap_pengaduan.php...")
        await page.goto("http://localhost:8000/guru/rekap_pengaduan.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)
        await page.screenshot(path="/home/jules/verification/screenshots/guru_bk_complaints_rekap.png")

        # Confirm the submitted complaint exists in list
        print("Confirming Roni's complaint is listed...")
        page_text = await page.locator("body").inner_text()
        if "Ada kejadian perundungan di koridor kelas X IPA." in page_text:
            print("SUCCESS: Roni's complaint is verified in Guru BK Rekap Pengaduan!")
        else:
            print("WARNING: Roni's complaint not found in list!")

        # ===================================================
        # 9. Clean up / Close browser
        # ===================================================
        await browser.close()
        print("All verification steps completed and screenshots captured successfully!")

if __name__ == "__main__":
    asyncio.run(main())

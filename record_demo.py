"""
Medis - Automated Feature Demonstration Video Generator
Uses Playwright and Chrome to record an end-to-end walkthrough
"""
import os
import sys
import time
import shutil
import subprocess
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost/Medis"
VIDEO_DIR = r"C:\xampp\htdocs\Medis\videos"
RAW_VIDEO_DIR = os.path.join(VIDEO_DIR, "raw")
OUTPUT_MP4 = os.path.join(VIDEO_DIR, "Medis_System_Walkthrough.mp4")

os.makedirs(RAW_VIDEO_DIR, exist_ok=True)

def inject_overlay(page, chapter_title, subtext=""):
    """Injects a stylish chapter pill and simulated cursor"""
    import json
    title_json = json.dumps(chapter_title)
    subtext_json = json.dumps(subtext)
    js = f"""
    (() => {{
        const oldBanner = document.getElementById('demo-banner');
        if (oldBanner) oldBanner.remove();

        const banner = document.createElement('div');
        banner.id = 'demo-banner';
        banner.style.position = 'fixed';
        banner.style.top = '16px';
        banner.style.right = '24px';
        banner.style.backgroundColor = '#1e3a5f';
        banner.style.color = '#ffffff';
        banner.style.padding = '10px 18px';
        banner.style.borderRadius = '30px';
        banner.style.fontFamily = 'Segoe UI, system-ui, sans-serif';
        banner.style.fontSize = '13px';
        banner.style.fontWeight = '600';
        banner.style.boxShadow = '0 6px 20px rgba(0,0,0,0.25)';
        banner.style.border = '1px solid rgba(255,255,255,0.2)';
        banner.style.zIndex = '999999';
        banner.style.display = 'flex';
        banner.style.alignItems = 'center';
        banner.style.gap = '10px';

        const titleText = {title_json};
        const subText = {subtext_json};

        banner.innerHTML = `
            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#22c55e;"></span>
            <div>
                <div>${{titleText}}</div>
                ${{subText ? `<div style="font-size:11px; font-weight:normal; opacity:0.8;">${{subText}}</div>` : ''}}
            </div>
        `;
        document.body.appendChild(banner);

        if (!document.getElementById('demo-cursor')) {{
            const cur = document.createElement('div');
            cur.id = 'demo-cursor';
            cur.style.position = 'fixed';
            cur.style.width = '16px';
            cur.style.height = '16px';
            cur.style.borderRadius = '50%';
            cur.style.backgroundColor = 'rgba(239, 68, 68, 0.85)';
            cur.style.border = '2px solid white';
            cur.style.pointerEvents = 'none';
            cur.style.zIndex = '1000000';
            cur.style.boxShadow = '0 2px 8px rgba(0,0,0,0.4)';
            cur.style.transition = 'transform 0.08s ease';
            cur.style.left = '-100px';
            cur.style.top = '-100px';
            document.body.appendChild(cur);

            window.addEventListener('mousemove', e => {{
                cur.style.left = (e.clientX - 8) + 'px';
                cur.style.top = (e.clientY - 8) + 'px';
            }});
        }}
    }})();
    """
    try:
        page.evaluate(js)
    except Exception:
        pass

def smooth_scroll(page, start_y, end_y, steps=10, delay=0.03):
    for i in range(1, steps + 1):
        y = start_y + (end_y - start_y) * (i / steps)
        page.evaluate(f"window.scrollTo({{top: {y}, behavior: 'instant'}})")
        page.wait_for_timeout(int(delay * 1000))

def run():
    print("Starting browser for video recording...")
    sys.stdout.flush()

    with sync_playwright() as p:
        browser = p.chromium.launch(
            executable_path=r"C:\Program Files\Google\Chrome\Application\chrome.exe",
            headless=True
        )

        context = browser.new_context(
            viewport={"width": 1280, "height": 720},
            record_video_dir=RAW_VIDEO_DIR,
            record_video_size={"width": 1280, "height": 720}
        )

        page = context.new_page()

        # =========================================================================
        # 1. LOGIN SCREEN DEMO
        # =========================================================================
        print("Recording: Login Screen")
        sys.stdout.flush()
        page.goto(f"{BASE_URL}/login.php")
        page.wait_for_timeout(1000)
        inject_overlay(page, "Medis Pharmacy Management System", "Full-Stack Project Demo (PHP, MySQL, Vanilla JS)")
        page.wait_for_timeout(2000)

        # Move mouse over demo credentials and click Admin
        page.mouse.move(640, 480)
        page.wait_for_timeout(600)
        admin_pill = page.locator(".demo-pill", has_text="Admin").first
        if admin_pill.count() > 0:
            box = admin_pill.bounding_box()
            page.mouse.move(box["x"] + box["width"]/2, box["y"] + box["height"]/2)
            page.wait_for_timeout(400)
            admin_pill.click()
            page.wait_for_timeout(800)

        # Click Sign In button
        sign_in_btn = page.locator("button[type='submit']").first
        box = sign_in_btn.bounding_box()
        page.mouse.move(box["x"] + box["width"]/2, box["y"] + box["height"]/2)
        page.wait_for_timeout(400)
        sign_in_btn.click()
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(1200)

        # =========================================================================
        # 2. DASHBOARD & INTERACTIVE ANALYTICS
        # =========================================================================
        print("Recording: Dashboard Overview")
        sys.stdout.flush()
        inject_overlay(page, "Executive Dashboard", "Live Metrics, Chart.js Visualizations & Alerts")
        page.wait_for_timeout(1600)

        # Hover stats cards
        page.mouse.move(320, 200)
        page.wait_for_timeout(500)
        page.mouse.move(580, 200)
        page.wait_for_timeout(500)

        # Smooth scroll down to view Chart.js graphs
        smooth_scroll(page, 0, 360, steps=12, delay=0.03)
        page.wait_for_timeout(1800)

        # Smooth scroll down to Recent Transactions & Low Stock Alerts
        smooth_scroll(page, 360, 680, steps=12, delay=0.03)
        page.wait_for_timeout(1800)

        # Scroll back up to filter by branch
        smooth_scroll(page, 680, 0, steps=12, delay=0.03)
        page.wait_for_timeout(600)

        # Filter by North Branch
        branch_select = page.locator("#branchFilter")
        if branch_select.count() > 0:
            branch_select.select_option(value="2")
            page.wait_for_load_state("networkidle")
            inject_overlay(page, "Multi-Branch Filtering", "Dynamic Query Scoping for North Branch (Islamabad)")
            page.wait_for_timeout(1800)

            # Switch back to All Branches
            page.locator("#branchFilter").select_option(value="all")
            page.wait_for_load_state("networkidle")
            page.wait_for_timeout(1000)

        # =========================================================================
        # 3. MEDICINE CATALOG & MANAGEMENT
        # =========================================================================
        print("Recording: Medicine Catalog")
        sys.stdout.flush()
        page.goto(f"{BASE_URL}/modules/medicines/index.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Medicine Catalog Management", "Formulas, Categories, Barcodes & Safety Limits")
        page.wait_for_timeout(1400)

        # Live search for medicine
        search_input = page.locator("input[name='search']").first
        search_input.click()
        search_input.type("Panadol", delay=80)
        page.wait_for_timeout(600)
        page.keyboard.press("Enter")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Search & Dynamic Filter", "Instant lookup by Brand, Formula or Barcode")
        page.wait_for_timeout(1500)

        # View Panadol details
        page.goto(f"{BASE_URL}/modules/medicines/view.php?id=2")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Product Detail & Batch Tracking", "Usable vs Expired Stock across Branches")
        page.wait_for_timeout(1500)
        smooth_scroll(page, 0, 320, steps=10, delay=0.03)
        page.wait_for_timeout(1500)

        # =========================================================================
        # 4. INVENTORY & BATCH AUDIT
        # =========================================================================
        print("Recording: Inventory Overview")
        sys.stdout.flush()
        page.goto(f"{BASE_URL}/modules/inventory/index.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Inventory & Batch Expiry Tracking", "FIFO Prioritization & Deficit Monitoring")
        page.wait_for_timeout(1600)

        # Switch to Low Stock Tab
        low_tab = page.locator("a[href*='tab=low_stock']").first
        if low_tab.count() > 0:
            low_tab.click()
            page.wait_for_load_state("networkidle")
            inject_overlay(page, "Low Stock Warning Alerts", "Automated safety threshold buffer check")
            page.wait_for_timeout(1600)

        # Switch to Expiring Tab
        exp_tab = page.locator("a[href*='tab=expiring']").first
        if exp_tab.count() > 0:
            exp_tab.click()
            page.wait_for_load_state("networkidle")
            inject_overlay(page, "Critical Expiry Monitoring", "Automated alert for batches <= 30 days")
            page.wait_for_timeout(1600)

        # =========================================================================
        # 5. POINT OF SALE (POS) & INSTANT CHECKOUT
        # =========================================================================
        print("Recording: Point of Sale (POS)")
        sys.stdout.flush()
        page.goto(f"{BASE_URL}/modules/pos/index.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Point of Sale (POS) Terminal", "Fast Cashier Interface & Barcode Checkout")
        page.wait_for_timeout(1600)

        # Search and add Panadol
        med_search = page.locator("#medSearchInput")
        med_search.click()
        med_search.type("Panadol", delay=90)
        page.wait_for_timeout(600)
        page.locator(".search-result-item").first.wait_for(state="visible", timeout=4000)
        page.locator(".search-result-item").first.click()
        page.wait_for_timeout(800)

        # Search and add Brufen
        med_search.click()
        med_search.type("Brufen", delay=90)
        page.wait_for_timeout(600)
        page.locator(".search-result-item").first.wait_for(state="visible", timeout=4000)
        page.locator(".search-result-item").first.click()
        page.wait_for_timeout(800)

        # Barcode scan demo (Disprin 75mg)
        barcode_in = page.locator("#barcodeInput")
        barcode_in.click()
        barcode_in.type("PKR-DSP-075", delay=70)
        page.wait_for_timeout(400)
        page.keyboard.press("Enter")
        page.wait_for_timeout(1000)

        # Increase quantity of Panadol
        page.locator(".btn-qty", has_text="+").first.wait_for(state="visible", timeout=3000)
        page.locator(".btn-qty", has_text="+").first.click()
        page.wait_for_timeout(400)
        page.locator(".btn-qty", has_text="+").first.click()
        page.wait_for_timeout(600)

        # Enter a discount
        discount_input = page.locator("#discountInput")
        discount_input.click()
        discount_input.fill("10%")
        page.keyboard.press("Tab")
        page.wait_for_timeout(800)

        # Enter amount paid in cash
        amount_paid = page.locator("#amountPaidInput")
        amount_paid.click()
        amount_paid.fill("500")
        page.keyboard.press("Tab")
        inject_overlay(page, "Automated Totals & Change Calculation", "Discounts, Subtotals & Tendered Change")
        page.wait_for_timeout(1500)

        # Complete sale
        complete_btn = page.locator("#btnCompleteSale")
        box = complete_btn.bounding_box()
        page.mouse.move(box["x"] + box["width"]/2, box["y"] + box["height"]/2)
        page.wait_for_timeout(500)

        try:
            with context.expect_page(timeout=4000) as popup_info:
                complete_btn.click()
            receipt_page = popup_info.value
            receipt_page.wait_for_load_state("networkidle")
            receipt_page.wait_for_timeout(2500)
            receipt_page.close()
        except Exception:
            complete_btn.click()
            page.wait_for_timeout(1500)

        inject_overlay(page, "Sale Completed & Stock Deducted", "FIFO Batch Deduction Committed to MySQL")
        page.wait_for_timeout(1600)

        # =========================================================================
        # 6. SALES AUDIT HISTORY
        # =========================================================================
        print("Recording: Sales History")
        sys.stdout.flush()
        page.goto(f"{BASE_URL}/modules/sales/index.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Sales Transaction Audit Log", "Immutable Invoice Records & Cashier History")
        page.wait_for_timeout(1600)

        # Click the top invoice view details
        view_first = page.locator("a[title='View Sale Details']").first
        if view_first.count() > 0:
            view_first.click()
            page.wait_for_load_state("networkidle")
            inject_overlay(page, "Detailed Sales Invoice Breakdown", "Line-item prices, assigned batches & cashier audit")
            page.wait_for_timeout(1600)

        # =========================================================================
        # 7. BUSINESS INTELLIGENCE & CHART.JS REPORTS
        # =========================================================================
        print("Recording: Reports Hub & Visualizations")
        sys.stdout.flush()
        page.goto(f"{BASE_URL}/modules/reports/index.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Executive Reports & Analytics Hub", "7 Specialized Business Intelligence Modules")
        page.wait_for_timeout(1500)
        smooth_scroll(page, 0, 350, steps=10, delay=0.03)
        page.wait_for_timeout(1000)

        # Daily sales report
        page.goto(f"{BASE_URL}/modules/reports/daily_sales.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Daily Sales Breakdown", "Intraday Hourly Turnover Bar Chart (Chart.js)")
        page.wait_for_timeout(1800)

        # Branch comparison report
        page.goto(f"{BASE_URL}/modules/reports/branch_sales.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Branch Revenue Benchmarking", "Cross-Store Comparison & Market Share Doughnut")
        page.wait_for_timeout(2000)

        # Top medicines report
        page.goto(f"{BASE_URL}/modules/reports/top_medicines.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Top-Selling Medicines Ranking", "Turnover & Volume League Table")
        page.wait_for_timeout(1800)

        # =========================================================================
        # 8. MULTI-BRANCH & USER ROLES
        # =========================================================================
        print("Recording: Multi-Branch & Store Management")
        sys.stdout.flush()
        page.goto(f"{BASE_URL}/modules/branches/index.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Multi-Branch Store Setup", "Store Locations, Managers & Regional Stock")
        page.wait_for_timeout(1600)

        page.goto(f"{BASE_URL}/modules/users/index.php")
        page.wait_for_load_state("networkidle")
        inject_overlay(page, "Role-Based Access Control", "Admin, Branch Managers & Cashier Access Control")
        page.wait_for_timeout(1600)

        # Final title banner
        inject_overlay(page, "Medis — Complete Pharmacy & POS System", "Built with PHP, MySQL PDO, Vanilla JS & Chart.js")
        page.wait_for_timeout(2500)

        print("Finished script walkthrough. Closing page and context...")
        sys.stdout.flush()
        page.close()
        context.close()
        browser.close()

    print("Browser closed. Converting video with FFmpeg...")
    sys.stdout.flush()

    # Convert webm to mp4 using ffmpeg
    webms = [os.path.join(RAW_VIDEO_DIR, f) for f in os.listdir(RAW_VIDEO_DIR) if f.endswith(".webm")]
    if webms:
        latest_webm = max(webms, key=os.path.getctime)
        print(f"Converting {latest_webm} to MP4...")
        cmd = [
            r"C:\Users\ltc\ffmpeg\ffmpeg.exe",
            "-y",
            "-i", latest_webm,
            "-c:v", "libx264",
            "-pix_fmt", "yuv420p",
            "-crf", "22",
            "-preset", "fast",
            OUTPUT_MP4
        ]
        subprocess.run(cmd, check=True)
        print(f"SUCCESS: Video exported to {OUTPUT_MP4}")
        file_size_mb = os.path.getsize(OUTPUT_MP4) / (1024 * 1024)
        print(f"File size: {file_size_mb:.2f} MB")
    else:
        print("ERROR: No webm files found in raw directory.")

if __name__ == "__main__":
    run()

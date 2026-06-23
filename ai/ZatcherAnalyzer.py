#!/usr/bin/env python3
"""
╔══════════════════════════════════════════════════════════════╗
║           ZATCHER - SCAM INTELLIGENCE ANALYZER               ║
║            By Group CS361: Internet & Web Tech             ║
║           Powered by LADINA via Gemini AI                    ║
╚══════════════════════════════════════════════════════════════╝

Cross-platform scam intelligence extractor.
Works on Windows, Linux and Mac.
Requirements: 
    Ensure Python is installed on your system. I recommend python 3.14 

Setup:
    pip install pytesseract pillow pdf2image pdfplumber google-genai
    
    Windows: install Tesseract from https://github.com/UB-Mannheim/tesseract/wiki
    Linux:   sudo apt install tesseract-ocr poppler-utils -y
    Mac:     brew install tesseract poppler

Usage:
    Set GEMINI_API_KEY environment variable first in the terminal:
    
    Linux/Mac:  export GEMINI_API_KEY="your-key-here"
    Windows:    set GEMINI_API_KEY=your-key-here

    To run the program you can use the following command format:

    python ZatcherAnalyzer.py screenshot.jpg
    python ZatcherAnalyzer.py report.pdf
    python ZatcherAnalyzer.py message.txt
    python ZatcherAnalyzer.py --folder ./screenshots/
"""

import os
import re
import sys
import time
import json
import argparse
from datetime import datetime
from pathlib import Path

# ─── Gemini setup ─────────────────────────────────────────────
try:
    from google import genai
except ImportError:
    print("[!] Missing dependency. Run: pip install google-genai")
    sys.exit(1)

API_KEY = os.getenv("GEMINI_API_KEY")
if not API_KEY:
    print("╔══════════════════════════════════════════════════════╗")
    print("║  ERROR: GEMINI_API_KEY environment variable not set  ║")
    print("║                                                      ║")
    print("║  Linux/Mac:  export GEMINI_API_KEY='your-key'       ║")
    print("║  Windows:    set GEMINI_API_KEY=your-key            ║")
    print("║                                                      ║")
    print("║  Get your key at: https://aistudio.google.com       ║")
    print("╚══════════════════════════════════════════════════════╝")
    sys.exit(1)

client = genai.Client(api_key=API_KEY)

# ─── Zambia-specific patterns ─────────────────────────────────
ZAMBIAN_PLATFORMS     = ["zamtel", "airtel", "mtn", "mobile money", "momo", "zesco"]
ZAMBIAN_PHONE_PATTERN = r'\b(0[679]\d{8}|260[679]\d{8}|\+260[679]\d{8})\b'
AMOUNT_PATTERN        = r'\b(?:K|ZMW|kwacha)?\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)\b'
SCAM_TECHNIQUES       = [
    "lottery win", "prize", "you have won", "congratulations",
    "send money first", "activation fee", "processing fee",
    "investment", "double your money", "agent", "blessings",
    "emergency", "accident", "stranded", "borrow", "loan",
    "verification", "pin", "otp", "code", "account blocked",
    "sim swap", "upgrade", "promotion", "winner"
]

# ─── Default schema ───────────────────────────────────────────
EMPTY_RESULT = {
    "scammer_phones": [],
    "scammer_names": [],
    "victim_phones": [],
    "platform": "Unknown",
    "amount_requested": None,
    "amount_lost": None,
    "scam_technique": "",
    "language_used": "English",
    "urgency_level": "medium",
    "threat_present": False,
    "impersonation": None,
    "key_phrases": [],
    "confidence_score": 0.0,
    "analyst_note": "Analysis unavailable"
}


# ─── OCR: image → text ────────────────────────────────────────
def extract_text_from_image(path: str) -> str:
    try:
        import pytesseract
        from PIL import Image
        return pytesseract.image_to_string(Image.open(path), lang='eng').strip()
    except ImportError:
        print("[!] Install: pip install pytesseract pillow")
        print("[!] Also install Tesseract OCR for your OS")
        return ""
    except Exception as e:
        print(f"[!] Image OCR error: {e}")
        return ""


# ─── PDF: pdf → text ──────────────────────────────────────────
def extract_text_from_pdf(path: str) -> str:
    # Try pdfplumber first (text PDFs)
    try:
        import pdfplumber
        text = ""
        with pdfplumber.open(path) as pdf:
            for page in pdf.pages:
                t = page.extract_text()
                if t:
                    text += t + "\n"
        if text.strip():
            return text.strip()
    except ImportError:
        pass
    except Exception as e:
        print(f"[!] pdfplumber error: {e}")

    # Fallback: OCR for scanned PDFs
    try:
        from pdf2image import convert_from_path
        import pytesseract
        print("[*] Scanned PDF detected — using OCR fallback...")
        images = convert_from_path(path)
        return "\n".join(pytesseract.image_to_string(img, lang='eng') for img in images)
    except ImportError:
        print("[!] Install: pip install pdf2image")
        print("[!] Linux: sudo apt install poppler-utils")
        print("[!] Windows: install poppler and add to PATH")
        return ""
    except Exception as e:
        print(f"[!] PDF OCR error: {e}")
        return ""


# ─── Regex pre-scan ───────────────────────────────────────────
def regex_preextract(text: str) -> dict:
    phones   = list(set(re.findall(ZAMBIAN_PHONE_PATTERN, text)))
    amounts  = list(set(re.findall(AMOUNT_PATTERN, text, re.IGNORECASE)))
    tl       = text.lower()
    platform = next((p.title() for p in ZAMBIAN_PLATFORMS if p in tl), None)
    techs    = [t for t in SCAM_TECHNIQUES if t.lower() in tl][:5]
    return {
        "phones_found":    phones,
        "amounts_found":   amounts[:5],
        "platform_hint":   platform,
        "technique_hints": techs
    }


# ─── Gemini analysis ──────────────────────────────────────────
def analyze_with_gemini(text: str, pre: dict, retries: int = 3) -> dict:
    prompt = f"""You are LADINA, scam intelligence analyst for Project Zatcher, Operation Spectre.
Analyze this suspected mobile money scam from Zambia (Zamtel, Airtel, MTN networks).

PRE-EXTRACTED DATA:
- Phone numbers found: {pre['phones_found']}
- Amounts found: {pre['amounts_found']}
- Platform hint: {pre['platform_hint']}
- Technique hints: {pre['technique_hints']}

SCAM MESSAGE / DOCUMENT TEXT:
\"\"\"{text[:2500]}\"\"\"

Return ONLY a valid JSON object with EXACTLY these fields:
{{
  "scammer_phones": ["phone numbers belonging to the scammer"],
  "scammer_names": ["names or aliases the scammer used"],
  "victim_phones": ["phone numbers belonging to the victim if visible"],
  "platform": "Zamtel or Airtel or MTN or Unknown",
  "amount_requested": "amount in Kwacha as string or null",
  "amount_lost": "amount victim sent as string or null",
  "scam_technique": "one-line description of the scam method",
  "language_used": "English or Bemba or Nyanja or mixed",
  "urgency_level": "low or medium or high",
  "threat_present": true or false,
  "impersonation": "who the scammer pretended to be or null",
  "key_phrases": ["up to 3 suspicious phrases from the message"],
  "confidence_score": 0.0 to 1.0,
  "analyst_note": "LADINA's intelligence summary of this scam"
}}"""

    for attempt in range(retries):
        try:
            response = client.models.generate_content(
                model="gemini-2.5-flash",
                contents=prompt,
                config={"response_mime_type": "application/json"}
            )
            raw = response.text.strip()
            raw = raw.replace("```json", "").replace("```", "").strip()
            result = json.loads(raw)
            result["_engine"] = "gemini-2.5-flash"
            return result

        except Exception as e:
            err = str(e)
            # DNS failure = no internet, don't retry
            if "Name resolution" in err or "Errno -3" in err or "Errno 11001" in err:
                print("[!] No internet connection. Please connect and retry.")
                return {**EMPTY_RESULT, "analyst_note": "No internet — Gemini unavailable"}
            # 503 = server busy, retry with backoff
            if "503" in err or "UNAVAILABLE" in err:
                wait = 2 ** attempt
                print(f"[!] Gemini busy (attempt {attempt+1}/{retries}). Retrying in {wait}s...")
                time.sleep(wait)
                continue
            # Other errors
            print(f"[!] Gemini error (attempt {attempt+1}/{retries}): {e}")
            time.sleep(2 ** attempt)

    return {**EMPTY_RESULT, "analyst_note": "Gemini analysis failed after all retries"}


# ─── Build final report ───────────────────────────────────────
def build_report(file_path: str, raw_text: str, ai: dict, pre: dict) -> dict:
    return {
        "meta": {
            "source_file":        os.path.basename(file_path),
            "analysis_timestamp": datetime.now().isoformat(),
            "operation":          "Project Zatcher | Operation Spectre",
            "analyst":            "LADINA",
            "engine":             ai.get("_engine", "unknown"),
            "text_length":        len(raw_text)
        },
        "raw_text_preview": raw_text[:500] + ("..." if len(raw_text) > 500 else ""),
        "pre_extraction":   pre,
        "ai_intelligence":  ai,
        "mysql_ready": {
            "scammer_phone":    ai.get("scammer_phones", [None])[0] if ai.get("scammer_phones") else None,
            "scammer_alias":    ", ".join(ai.get("scammer_names", [])),
            "victim_phone":     ai.get("victim_phones", [None])[0] if ai.get("victim_phones") else None,
            "platform":         ai.get("platform", pre.get("platform_hint", "Unknown")),
            "amount_requested": ai.get("amount_requested"),
            "amount_lost":      ai.get("amount_lost"),
            "scam_technique":   ai.get("scam_technique", ""),
            "urgency_level":    ai.get("urgency_level", "medium"),
            "threat_present":   ai.get("threat_present", False),
            "confidence_score": ai.get("confidence_score", 0.0),
            "analyst_note":     ai.get("analyst_note", ""),
            "status":           "analysed"
        }
    }


# ─── Analyze single file ──────────────────────────────────────
def analyze_file(file_path: str, output_json: bool = True) -> dict:
    path = Path(file_path)
    if not path.exists():
        print(f"[!] File not found: {file_path}")
        return {}

    ext = path.suffix.lower()
    print(f"\n[LADINA] Analyzing: {path.name}")

    # Extract text
    print("[LADINA] Extracting text...")
    if ext in ['.jpg', '.jpeg', '.png', '.bmp', '.tiff', '.webp']:
        raw_text = extract_text_from_image(file_path)
    elif ext == '.pdf':
        raw_text = extract_text_from_pdf(file_path)
    elif ext == '.txt':
        raw_text = path.read_text(encoding='utf-8', errors='ignore')
    else:
        print(f"[!] Unsupported format: {ext}")
        print("[*] Supported: .jpg .jpeg .png .pdf .txt")
        return {}

    if not raw_text.strip():
        print("[!] No text could be extracted.")
        return {}

    print(f"[LADINA] Extracted {len(raw_text)} characters.")

    # Pre-scan
    pre = regex_preextract(raw_text)
    print(f"[LADINA] Pre-scan: {len(pre['phones_found'])} phones | platform: {pre['platform_hint']}")

    # Gemini analysis
    print("[LADINA] Sending to Gemini for deep analysis...")
    ai = analyze_with_gemini(raw_text, pre)

    # Build report
    report = build_report(file_path, raw_text, ai, pre)

    # Save JSON
    if output_json:
        out = Path(path.stem + "_zatcher_intel.json")
        out.write_text(json.dumps(report, indent=2))
        print(f"[LADINA] Report saved: {out}")

    # Print summary
    m = report["mysql_ready"]
    print("\n" + "="*60)
    print("ZATCHER INTELLIGENCE SUMMARY")
    print("="*60)
    print(f"Engine           : {report['meta']['engine']}")
    print(f"Scammer Phone    : {m['scammer_phone'] or 'N/A'}")
    print(f"Scammer Alias    : {m['scammer_alias'] or 'N/A'}")
    print(f"Victim Phone     : {m['victim_phone'] or 'N/A'}")
    print(f"Platform         : {m['platform']}")
    print(f"Amount Requested : {m['amount_requested'] or 'N/A'}")
    print(f"Amount Lost      : {m['amount_lost'] or 'N/A'}")
    print(f"Technique        : {m['scam_technique'] or 'N/A'}")
    print(f"Urgency          : {m['urgency_level']}")
    print(f"Threat Present   : {m['threat_present']}")
    print(f"Confidence       : {m['confidence_score']}")
    print(f"LADINA Note      : {m['analyst_note'] or 'N/A'}")
    print("="*60)

    return report


# ─── Batch mode ───────────────────────────────────────────────
def analyze_folder(folder_path: str):
    folder = Path(folder_path)
    supported = ['.jpg', '.jpeg', '.png', '.pdf', '.txt']
    files = [f for f in folder.iterdir() if f.suffix.lower() in supported]

    if not files:
        print(f"[!] No supported files found in: {folder_path}")
        return

    print(f"[LADINA] {len(files)} files queued.")
    reports = []

    for i, f in enumerate(files, 1):
        print(f"\n── [{i}/{len(files)}] {f.name}")
        r = analyze_file(str(f), output_json=False)
        if r:
            reports.append(r)
        # Small delay between Gemini calls to avoid rate limits
        if i < len(files):
            time.sleep(1)

    out = folder / "zatcher_batch_report.json"
    out.write_text(json.dumps(reports, indent=2))
    print(f"\n[LADINA] Batch complete. {len(reports)} reports → {out}")


# ─── CLI ──────────────────────────────────────────────────────
if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="ZATCHER Intelligence Analyzer | Operation Spectre",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python ZatcherAnalyzer.py scam_screenshot.jpg
  python ZatcherAnalyzer.py victim_report.pdf
  python ZatcherAnalyzer.py scam_message.txt
  python ZatcherAnalyzer.py --folder ./screenshots/
        """
    )
    parser.add_argument("file", nargs="?", help="Image, PDF or text file to analyze")
    parser.add_argument("--folder", help="Batch analyze all files in a folder")
    parser.add_argument("--no-json", action="store_true", help="Skip JSON output file")
    args = parser.parse_args()

    print("""
╔══════════════════════════════════════════════════════════════╗
║           ZATCHER INTELLIGENCE ANALYZER v3.0                ║
║           Operation Spectre | Agent Benjamin                 ║
║           LADINA via Gemini — Online and Ready               ║
╚══════════════════════════════════════════════════════════════╝""")

    if args.folder:
        analyze_folder(args.folder)
    elif args.file:
        analyze_file(args.file, output_json=not args.no_json)
    else:
        parser.print_help()

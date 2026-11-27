OCR for registration (Tesseract.js)
-----------------------------------
registration pages load Tesseract.js from the internet (cdn.jsdelivr.net), so the computer needs internet
when you scan an ID. The first scan downloads the English language data (about 10 MB), the next scans are faster.
face-api.min.js and the files in public/models are the same ones used by the face login.

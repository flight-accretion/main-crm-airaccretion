from pathlib import Path
import re

from pypdf import PdfReader


pdf_path = Path(__file__).parent / ".wave1-qa-runtime" / "invoice.pdf"
reader = PdfReader(pdf_path)
text = "\n".join(page.extract_text() or "" for page in reader.pages)
checks = [
    r"Gross service amount:\s*Rs\.18,000\.00",
    r"Less discount:\s*- Rs\.1,000\.00",
    r"Final payable:\s*Rs\.17,000\.00",
    r"Amount\s*:\s*Rs\.8,500",
    r"Outstanding balance:\s*Rs\.8,500\.00",
]
for pattern in checks:
    assert re.search(pattern, text), f"Missing PDF value matching {pattern}"
print(f"Invoice PDF passed {len(checks)} numerical checks across {len(reader.pages)} page(s)")

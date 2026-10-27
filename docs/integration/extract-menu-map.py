"""Reproduce the 151 sidebar entries from audit pages 3-12; no invented routes."""
import csv
import re
from pathlib import Path

root = Path(__file__).resolve().parent
text = (root / 'addonan-extracted.txt').read_text(encoding='utf-8')
pages = re.split(r'(?m)^PAGE \d+\n', text)[3:13]
rows = []
for page_no, page in enumerate(pages, 3):
    lines = [line.strip() for line in page.splitlines() if line.strip()]
    # Sequential IDs distinguish menu rows from page numbers.
    start = 0
    while start < len(lines):
        expected = str(len(rows) + 1)
        try:
            i = lines.index(expected, max(start, 3))
        except ValueError:
            break
        tail = lines[i + 1:]
        target_index = next((j for j, line in enumerate(tail) if
            line == 'Jangkar (#)' or line.startswith('::') or
            re.fullmatch(r'[a-z0-9][a-z0-9?&=/_-]*', line)), None)
        if target_index is None:
            raise ValueError(f'Missing destination {expected}')
        label = ' '.join(tail[:target_index])
        target = tail[target_index]
        consumed = i + 2 + target_index
        if target.endswith('produk') and consumed < len(lines) and lines[consumed] == 'si-scrap-waste':
            target += lines[consumed]
            consumed += 1
        rows.append([expected, page_no, label, target, '', 'not-assessed',
            'Audit observation only; ERP implementation and acceptance not established'])
        start = consumed
assert len(rows) == 151, len(rows)
assert [int(row[0]) for row in rows] == list(range(1, 152))
with (root / 'addonan-menu-map.csv').open('w', encoding='utf-8', newline='') as handle:
    writer = csv.writer(handle)
    writer.writerow(['audit_id', 'pdf_page', 'source_menu', 'source_target', 'erp_route', 'implementation_status', 'evidence'])
    writer.writerows(rows)
print(f'Extracted {len(rows)} entries; target ERP route mapping remains unverified.')
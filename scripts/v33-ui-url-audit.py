"""Inventory source UI transport/navigation references; does not rewrite or activate UI."""
import csv
import hashlib
import pathlib
import re
import sys

root, output = map(pathlib.Path, sys.argv[1:3])
patterns = re.compile(r'fetch\s*\(|\bapi\s*\(|/api/|/signin|/signout|window\.open|location\.|\.href\s*=|window\.print|/translations\.json|/workspace\.|/reference-forms|/mgi-logo')
files = [root / 'public/workspace.js', root / 'app/chatgpt-auth.ts', root / 'app/workspace.tsx']
with output.open('w', encoding='utf-8', newline='') as handle:
    writer = csv.writer(handle)
    writer.writerow(['source_path', 'source_sha256', 'line', 'reference_excerpt', 'adapter_status'])
    count = 0
    for path in files:
        digest = hashlib.sha256(path.read_bytes()).hexdigest()
        for number, line in enumerate(path.read_text(encoding='utf-8').splitlines(), 1):
            for match in patterns.finditer(line):
                writer.writerow([path.relative_to(root), digest, number, line[max(0, match.start()-70):match.end()+210], 'requires-review-not-adapted'])
                count += 1
print(f'{count} URL/transport/navigation references inventoried; no completeness or acceptance claim.')
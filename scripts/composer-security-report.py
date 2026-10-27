"""Build advisory evidence from saved Composer JSON and before/after lockfiles."""
import csv
import json
import pathlib
import sys

audit_path, before_path, after_path, output_dir = map(pathlib.Path, sys.argv[1:5])
load = lambda p: json.loads(p.read_text(encoding='utf-8-sig'))
audit, before, after = load(audit_path), load(before_path), load(after_path)
old = {p['name']: p for p in before['packages'] + before['packages-dev']}
new = {p['name']: p for p in after['packages'] + after['packages-dev']}
prod = {p['name'] for p in before['packages']}
usage = {
    'guzzlehttp/guzzle': 'Outbound Laravel HTTP clients / external integrations; redirect and untrusted-URL reachability requires endpoint review.',
    'guzzlehttp/psr7': 'HTTP URI/header/message parsing used by HTTP clients; external request/response boundary.',
    'laravel/framework': 'All HTTP/auth/session/debug/Eloquent paths; TokenGuard usage not established; framework exposure confirmed.',
    'league/commonmark': 'Markdown rendering transitive dependency; direct untrusted Markdown endpoint reachability not established.',
    'league/flysystem': 'Private/public filesystem abstraction; uploads/downloads and configured storage adapters.',
    'maatwebsite/excel': 'Existing CSV/XLSX import/export routes; untrusted workbook boundary reachable.',
    'phpoffice/phpspreadsheet': 'Workbook parsing under Excel imports; uploaded file boundary reachable.',
    'symfony/http-foundation': 'Every HTTP request/response; headers, cookies and uploaded file handling.',
    'symfony/yaml': 'YAML parser transitive dependency; untrusted runtime YAML endpoint not established.',
}
seen = set()
with (output_dir / 'composer-advisory-triage.csv').open('w', encoding='utf-8', newline='') as handle:
    writer = csv.writer(handle)
    writer.writerow(['advisory_id', 'package', 'installed_before', 'severity', 'dependency_scope', 'affected_versions', 'selected_fixed_version', 'title', 'cve', 'reference', 'usage_assessment', 'verification'])
    for package, findings in audit['advisories'].items():
        for finding in (findings.values() if isinstance(findings, dict) else findings):
            key = finding['advisoryId']
            if key in seen:
                continue
            seen.add(key)
            writer.writerow([key, package, old[package]['version'], finding.get('severity') or 'unknown',
                             'production' if package in prod else 'development', finding['affectedVersions'],
                             new[package]['version'], finding['title'], finding.get('cve', ''), finding['link'],
                             usage.get(package, 'Requires review'), 'Selected version has no advisory in post-update Composer audit; not a universal exploit assessment.'])
print(f'{len(seen)} unique advisories; targeted version evidence generated')
"""Build traceability from the read-only Addonan audit, never runtime menus.

Usage: python scripts/integration-menu-map.py <extracted-pdf.txt> <routes.json>
Routes must come from `php artisan route:list --json` in an isolated environment.
No data or runtime configuration is modified.
"""
import csv
import json
import pathlib
import re
import sys


def extract(text):
    lines = text.splitlines()
    # The first occurrence is the table of contents, not the actual menu table.
    start = max(i for i, line in enumerate(lines) if line.strip() == '2. Struktur menu hierarkis')
    stop = next(i for i in range(start + 1, len(lines)) if lines[i].strip() == '3. Analisis modul dan fitur')
    section = lines[start + 1:stop]
    section = [line.strip() for line in section if line.strip()]
    entries = []
    expected = 1
    group = ''
    i = 0
    while i < len(section):
        if section[i] == 'No.' and i:
            group = section[i - 1]
        if section[i] != str(expected):
            i += 1
            continue
        start = i + 1
        end = start
        while end < len(section):
            value = section[end]
            if value == 'Jangkar (#)' or value == ':: IMPORT DATA & UPLOAD ::' or re.fullmatch(r'[a-z][a-z0-9/-]*(?:\?[a-z_]+=[a-z_]+)?', value):
                break
            end += 1
        if end == len(section):
            raise ValueError(f'Missing destination for {expected}')
        destination = section[end]
        if destination.endswith('produk') and section[end + 1] == 'si-scrap-waste':
            destination += section[end + 1]
        entries.append((expected, group, ' '.join(section[start:end]), destination))
        expected += 1
        i = end + 1
        if expected == 152:
            break
    if len(entries) != 151:
        raise ValueError(f'Expected exactly 151 sidebar entries, got {len(entries)}')
    return entries


def main():
    source, routes_path = map(pathlib.Path, sys.argv[1:3])
    entries = extract(source.read_text(encoding='utf-8'))
    routes = json.loads(routes_path.read_text(encoding='utf-8-sig'))
    names = {row['name'] for row in routes if row.get('name')}
    # Candidates identify reuse opportunities, not acceptance or functional parity.
    candidates = {
        'chart-of-account': 'accounts.index', 'general-entry': 'jurnal.create',
        'general-ledger': 'buku-besar.index', 'profit-and-loss': 'laba-rugi.index',
        'balance-sheet': 'neraca.index', 'goods-received-bbm': 'grn.index',
        'purchase-order-general': 'purchase-orders.index',
        'purchase-order-by-ipr': 'purchase-orders.index',
        'ipr-internal-purchase-request': 'purchase-requisitions.index',
        'payment-request': 'payment.index', 'rate-currency': 'currency.index',
    }
    seen = {}
    output = source.parent / 'menu-map.csv'
    with output.open('w', encoding='utf-8', newline='') as file:
        writer = csv.writer(file)
        writer.writerow(['source_system', 'source_reference', 'source_id', 'source_group', 'source_menu', 'source_destination', 'alias_of', 'target_route', 'status', 'implementation', 'permission', 'test_evidence', 'presentation_driver', 'notes'])
        for number, group, label, destination in entries:
            # A heading/anchor is not a shared business process or a route alias.
            navigable = destination not in ('Jangkar (#)', ':: IMPORT DATA & UPLOAD ::')
            alias = seen.get(destination, '') if navigable else ''
            if navigable:
                seen.setdefault(destination, number)
            target = candidates.get(destination, '')
            if number == 18:
                writer.writerow(['Addonan', 'Audit PDF section 2', number, group, label, destination, alias,
                                 'filament.erp.resources.divisions.index', 'implemented-pending-acceptance',
                                 'app/Filament/Resources/DivisionResource.php; app/Services/DivisionService.php',
                                 'erp.panel.access; division.view/create/update; DivisionPolicy',
                                 'tests/Feature/ErpPanelAccessTest.php; browser/reactive HTTP acceptance pending', 'filament',
                                 'Existing shared single-MGI master reused; deletion disabled; legacy writer unchanged.'])
                continue
            verified = target in names
            writer.writerow(['Addonan', 'Audit PDF section 2', number, group, label, destination, alias,
                              target,
                              'heading' if not navigable else ('reuse-candidate' if verified else ('mapped-not-implemented' if target else 'unmapped')),
                              '', '', '', '',
                             'Alias retained; no runtime redirect created. Functional acceptance pending.' if alias else 'Audit observation is not proof of ERP implementation.'])
    print(f'{output}: {len(entries)} entries; {len(seen)} destinations; no acceptance claimed')


if __name__ == '__main__':
    main()
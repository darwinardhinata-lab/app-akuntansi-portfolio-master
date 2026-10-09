const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const view = fs.readFileSync(path.join(__dirname, '../../resources/views/sales_order/index.blade.php'), 'utf8');
const start = view.indexOf('        function renderPivotMatrix()');
const end = view.indexOf('        // Render otomatis', start);
const table = { innerHTML: '' };
const elements = { pivotRow: { value: 'pelanggan' }, pivotCol: { value: 'lokasi' },
    pivotVal: { value: 'total_omset' }, pivotResultTable: table };
const payload = '<img src=x onerror="alert(1)"> & \' </script><script>alert(2)</script>';
const context = vm.createContext({ document: { getElementById: id => elements[id] },
    rawData: [{ pelanggan: payload, lokasi: payload, total_omset: 10 },
        { pelanggan: payload, lokasi: payload, total_omset: 20 },
        { pelanggan: '__proto__', lokasi: '__proto__', total_omset: 7 }],
    alert: () => { throw new Error('Unexpected alert'); } });
assert.ok(start > 0 && end > start);
vm.runInContext(view.slice(start, end), context);
vm.runInContext('renderPivotMatrix()', context);
assert.ok(!table.innerHTML.includes('<img'));
assert.ok(!table.innerHTML.includes('<script>'));
assert.ok(table.innerHTML.includes('&lt;img'));
assert.ok(table.innerHTML.includes('Rp 37'));
assert.ok(!table.innerHTML.includes('NaN'));
assert.ok(view.includes('const rawData = {{ Illuminate\\Support\\Js::from($analyticData ?? []) }};'));
elements.pivotCol.value = 'bulan';
elements.pivotVal.value = 'total_qty';
context.rawData = [{ pelanggan: 'constructor', bulan: null, total_qty: 2 },
    { pelanggan: 'constructor', bulan: null, total_qty: 3 },
    { pelanggan: null, bulan: '<svg onload=alert(3)>', total_qty: 4 }];
vm.runInContext('renderPivotMatrix()', context);
assert.ok(!table.innerHTML.includes('<svg'));
assert.ok(table.innerHTML.includes('&lt;svg'));
assert.ok(table.innerHTML.includes('5 Pcs'));
assert.ok(table.innerHTML.includes('9 Pcs'));
assert.equal(vm.runInContext('formatColumnHeader("2026-10", "bulan")', context), 'Okt 2026');
console.log('Sales Order pivot security and aggregation checks passed.');
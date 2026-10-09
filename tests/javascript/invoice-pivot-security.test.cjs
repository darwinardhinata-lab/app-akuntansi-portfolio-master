const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Execute the actual view functions, with only the DOM selectors stubbed.
const view = fs.readFileSync(path.join(__dirname, '../../resources/views/sales_invoice/index.blade.php'), 'utf8');
// Keep the HTML script boundary safe as well as the later innerHTML boundary.
assert.ok(view.includes('const rawData = {{ Illuminate\\Support\\Js::from($analyticData) }};'));
const start = view.indexOf('    function escapePivotText(');
const end = view.indexOf('    // Jalankan render otomatis', start);
assert.ok(start > 0 && end > start);
const table = { innerHTML: '' };
const elements = {
    pivotRow: { value: 'store_name' }, pivotCol: { value: 'channel' },
    pivotVal: { value: 'total_omset' }, pivotResultTable: table,
};
const payload = '<img src=x onerror="alert(1)"> & \' </script><script>alert(2)</script>';
const context = vm.createContext({
    document: { getElementById: id => elements[id] },
    rawData: [
        { store_name: payload, channel: payload, total_omset: 10 },
        { store_name: payload, channel: payload, total_omset: 20 },
        { store_name: '__proto__', channel: '__proto__', total_omset: 7 },
    ],
    alert: () => { throw new Error('Unexpected alert'); },
});
vm.runInContext(view.slice(start, end), context);
vm.runInContext('renderPivotMatrix()', context);
assert.ok(!table.innerHTML.includes('<img'));
assert.ok(!table.innerHTML.includes('<script>'));
assert.ok(table.innerHTML.includes('&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; &#39;'));
assert.ok(table.innerHTML.includes('Rp 30'));
assert.ok(table.innerHTML.includes('Rp 37'));
assert.ok(!table.innerHTML.includes('NaN'));
assert.equal(vm.runInContext('escapePivotText(null)', context), '');
assert.equal(vm.runInContext('formatColumnHeader(null, "bulan")', context), null);
assert.equal(vm.runInContext('formatColumnHeader("2026-10", "bulan")', context), 'Okt 2026');
elements.pivotRow.value = 'channel';
elements.pivotCol.value = 'store_name';
elements.pivotVal.value = 'total_qty';
context.rawData = [
    { channel: 'constructor', store_name: 'constructor', total_qty: 2 },
    { channel: 'constructor', store_name: 'constructor', total_qty: 3 },
    { channel: null, store_name: '<svg onload=alert(3)>', total_qty: 4 },
];
vm.runInContext('renderPivotMatrix()', context);
assert.ok(!table.innerHTML.includes('<svg'));
assert.ok(table.innerHTML.includes('&lt;svg onload=alert(3)&gt;'));
assert.ok(table.innerHTML.includes('5 Pcs'));
assert.ok(table.innerHTML.includes('9 Pcs'));
assert.ok(!table.innerHTML.includes('NaN'));
console.log('Invoice pivot security and aggregation checks passed.');
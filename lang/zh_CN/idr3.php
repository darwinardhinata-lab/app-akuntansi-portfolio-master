<?php
return [
    'title' => 'IDR3 总账变动记录', 'current' => '截至 :date 的资金状况（雅加达时间）',
    'explanation' => '可用余额 = 总账余额 − 未入账扣款 − 有效预留。下表仅列出总账变动。所有金额均以印尼盾计。',
    'ledger' => '总账余额', 'unposted_actual_debits' => '未入账扣款', 'reserved' => '有效预留', 'available' => '可用余额',
    'period' => '总账期间', 'start' => '起始日期', 'end' => '截止日期', 'apply' => '应用期间',
    'cutoff' => '记录始于 :date。起止日期均计入期间（雅加达时间）；此前的变动计入期初余额。',
    'period_error' => '请选择从切换日期至今日（雅加达时间）的有效期间。',
    'opening' => '期初余额', 'net' => '净变动', 'closing' => '期末余额',
    'page_opening' => '本页期初余额', 'page_closing' => '本页期末余额',
    'date' => '日期', 'journal' => '凭证编号', 'description' => '摘要', 'debit' => '借方', 'credit' => '贷方', 'balance' => '逐笔余额',
    'request' => '查看申请', 'empty' => '本期间无总账变动。',
    'previous' => '上一页', 'next' => '下一页', 'page' => '第 :page 页，共 :last 页',
];
<?php

// FIX: multicurrency — katalog Mandarin lengkap untuk label dan kesalahan modul.
return [
    // FIX: multicurrency — label formulir jurnal dan saldo awal valas.
    'override_reason' => '汇率覆盖原因',
    'equivalent' => '本位币等值（预览）',
    'title' => '货币主数据', 'rates' => '汇率', 'code' => '代码',
    'name' => '名称', 'symbol' => '符号', 'decimals' => '小数位', 'show' => '显示',
    'active' => '启用', 'base' => '本位币', 'save' => '保存', 'delete' => '删除',
    'date' => '开始日期', 'until' => '有效截止日期', 'rate' => '每单位外币汇率',
    'type' => '类型', 'source' => '来源', 'created' => '创建人 / 日期',
    'updated' => '修改人 / 时间', 'already_used' => '已使用',
    'from' => '起始日期', 'to' => '结束日期', 'filter' => '筛选',
    'import' => '导入 CSV', 'add' => '新增汇率', 'manual' => '手动',
    'spot' => '即期', 'tax' => '税务', 'average' => '平均',
    'quoted_unit' => '报价单位数量', 'quoted_rate' => '原始报价',
    'quoted_help' => '保存时服务器计算每单位汇率：原始报价 ÷ 单位数量。',
    'kmk_help' => '输入KMK原始报价、单位数量和有效期。每100日元的报价请填写100单位，服务器自动换算。',
    'bi_help' => '输入每单位外币的印尼央行汇率。日元报价以100日元为单位，请除以100。买入、卖出或中间价政策须经财务批准；暂不支持自动同步。',
    'csv_help' => 'CSV：currency_code,rate_date,rate,type；可选 source,valid_until。TAX必须填写valid_until。小数使用点，不使用千位分隔符。最多1000行。',
    'saved' => '货币或汇率已保存。', 'missing' => '没有可用的有效汇率。',
    'invalid' => '货币、汇率或来源与类型组合无效。',
    'duplicate' => '汇率重复或税务汇率有效期重叠。',
    'used' => '已使用的汇率不能修改或删除。',
    'range' => '日期必须按顺序排列，且范围不得超过三个日历月。',
    'csv_invalid' => 'CSV结构或内容无效。', 'empty' => '暂无数据。',
];
<?php
$data = DB::select("SELECT account_code, SUM(amount) as total, SUM(CASE WHEN position='DEBET' THEN amount ELSE 0 END) as debet, SUM(CASE WHEN position='KREDIT' THEN amount ELSE 0 END) as kredit FROM journal_details WHERE account_code IN ('77004', '88004') GROUP BY account_code");
echo json_encode($data, JSON_PRETTY_PRINT);

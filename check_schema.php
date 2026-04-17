<?php
require 'app/Config/config.php';
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    $detailTables = ['CaLamViec','PhanCongCa','BangLuong','LichSuTraLuong','CauHinhLuong','LichHen_Notification','PhieuKham'];
    foreach ($detailTables as $tbl) {
        echo "--- $tbl ---" . PHP_EOL;
        $cols = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='$tbl' ORDER BY ORDINAL_POSITION")->fetchAll();
        if (empty($cols)) { echo "(TABLE NOT FOUND)" . PHP_EOL . PHP_EOL; continue; }
        foreach ($cols as $c) {
            $len = $c['CHARACTER_MAXIMUM_LENGTH'] ? ':' . $c['CHARACTER_MAXIMUM_LENGTH'] : '';
            $null = $c['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
            $def = $c['COLUMN_DEFAULT'] ? ' DEF=' . $c['COLUMN_DEFAULT'] : '';
            echo "  {$c['COLUMN_NAME']} ({$c['DATA_TYPE']}{$len}) {$null}{$def}" . PHP_EOL;
        }
        // Show sample data count
        $cnt = $pdo->query("SELECT COUNT(*) as c FROM $tbl")->fetch();
        echo "  >> Row count: {$cnt['c']}" . PHP_EOL . PHP_EOL;
    }

    // PhieuKham TrangThai explanation check
    echo "--- PhieuKham TrangThai values ---" . PHP_EOL;
    $st = $pdo->query("SELECT TrangThai, COUNT(*) as cnt FROM PhieuKham WHERE IsDeleted=0 GROUP BY TrangThai")->fetchAll();
    foreach ($st as $s) echo "  TrangThai={$s['TrangThai']} count={$s['cnt']}" . PHP_EOL;

    echo PHP_EOL . "--- LichHen TrangThai values ---" . PHP_EOL;
    $lh = $pdo->query("SELECT TrangThai, COUNT(*) as cnt FROM LichHen GROUP BY TrangThai")->fetchAll();
    foreach ($lh as $l) echo "  TrangThai={$l['TrangThai']} count={$l['cnt']}" . PHP_EOL;

    echo PHP_EOL . "--- NguoiDung by VaiTro ---" . PHP_EOL;
    $nd = $pdo->query("SELECT MaVaiTro, COUNT(*) as cnt FROM NguoiDung WHERE IsDeleted=0 GROUP BY MaVaiTro")->fetchAll();
    foreach ($nd as $n) echo "  MaVaiTro={$n['MaVaiTro']} count={$n['cnt']}" . PHP_EOL;

    // Check PhieuKham has ThoiGianBatDau/ThoiGianKetThuc
    echo PHP_EOL . "--- PhieuKham time columns ---" . PHP_EOL;
    $timeCols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='PhieuKham' AND COLUMN_NAME LIKE '%ThoiGian%'")->fetchAll();
    foreach ($timeCols as $tc) echo "  {$tc['COLUMN_NAME']}" . PHP_EOL;
    if (empty($timeCols)) echo "  (no ThoiGian columns)" . PHP_EOL;

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}

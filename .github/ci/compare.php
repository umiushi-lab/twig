<?php
// テストの結果(JUnit の XML)を、この版と元のバージョンで突き合わせ、この版で新しく落ちたテストだけを出す。
// 部品ごとに結果のファイルを分けているときは、同じ名前どうし(head → base)を比べる。
// 結果が読めない(時間切れで途中までしか書かれていない)ときは「比べられなかった」と出し、
// この版だけ読めないときは失敗にする(この版で固まるようになった、を見逃さないため)。
// Compares JUnit XML results of this fork ("head") with the upstream code ("base") and fails only on tests that fail
// here but pass upstream. A result missing or unreadable only on the fork side is a failure too.
// usage: php compare.php "junit-head*.xml" "junit-base*.xml"
function failed($f)
{
    $x = is_file($f) ? @simplexml_load_file($f) : false;
    if (!$x) {
        return null;
    }
    $out = array();
    foreach ($x->xpath('//testcase') as $t) {
        if ($t->failure || $t->error) {
            $class = (string) $t['class'];
            if ($class === '') {
                // data provider の行はクラスが空なので、入れ物の名前(クラス::メソッド)を使う
                $p = $t->xpath('..');
                $class = $p ? preg_replace('/::.*$/', '', (string) $p[0]['name']) : '';
            }
            $out[$class.'::'.(string) $t['name']] = true;
        }
    }

    return $out;
}
$heads = glob($argv[1]);
if (!$heads) {
    fwrite(STDERR, "no result files: {$argv[1]}\n");
    exit(2);
}
$new = $same = array();
$bad = 0;
foreach ($heads as $hf) {
    $bf = preg_replace('/junit-head/', 'junit-base', $hf, 1);
    $h = failed($hf);
    $b = failed($bf);
    if ($h === null && $b === null) {
        echo "not compared (no readable result on either side): $hf\n";
        continue;
    }
    if ($h === null) {
        echo "FAILED: no readable result with the backported code, but the upstream code has one: $hf\n";
        $bad = 1;
        continue;
    }
    if ($b === null) {
        echo "not compared (no readable result with the upstream code): $hf\n";
        continue;
    }
    $new += array_diff_key($h, $b);
    $same += array_intersect_key($h, $b);
}
echo 'failing with the original code too (not counted): '.count($same)."\n";
foreach (array_keys($same) as $n) {
    echo "  = $n\n";
}
echo 'new failures with the backported code: '.count($new)."\n";
foreach (array_keys($new) as $n) {
    echo "  + $n\n";
}
exit(($new || $bad) ? 1 : 0);

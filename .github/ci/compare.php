<?php
// テストの結果(JUnit の XML)を 2 つ比べ、この版で新しく落ちたテストだけを出す。
// 左: この版(修正を移したもの)。右: 元のバージョンのライブラリで、同じテストを回したもの。
// Compares two sets of JUnit XML files and fails only on tests that fail with the backported code
// but pass with the original upstream code (so environment-related failures that already existed upstream are not counted).
// usage: php compare.php "head-*.xml" "base-*.xml"
function failed($glob)
{
    $out = array();
    $files = glob($glob);
    if (!$files) {
        fwrite(STDERR, "no result files: $glob\n");
        exit(2);
    }
    foreach ($files as $f) {
        $x = @simplexml_load_file($f);
        if (!$x) {
            fwrite(STDERR, "unreadable: $f\n");
            exit(2);
        }
        foreach ($x->xpath('//testcase') as $t) {
            if ($t->failure || $t->error) {
                $out[(string) $t['class'].'::'.(string) $t['name']] = true;
            }
        }
    }

    return $out;
}
$head = failed($argv[1]);
$base = failed($argv[2]);
$new = array_diff_key($head, $base);
$same = array_intersect_key($head, $base);
echo 'failing with the original code too (not counted): '.count($same)."\n";
foreach (array_keys($same) as $n) {
    echo "  = $n\n";
}
echo 'new failures with the backported code: '.count($new)."\n";
foreach (array_keys($new) as $n) {
    echo "  + $n\n";
}
exit($new ? 1 : 0);

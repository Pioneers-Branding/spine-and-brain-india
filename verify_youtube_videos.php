<?php
/**
 * verify_youtube_videos.php  — validates the injected video sections on ALL pages.
 * Read-only. Usage: php verify_youtube_videos.php
 */
chdir(__DIR__);

$knownIds = ['mOv1ZCC1UY8','OTEn7-NDqsw','KBrY8kbD0p4','ki888H99xzM','TCogn2SY3ys','X2yWKJKHs2A',
    'ClE0JRMY1iE','N-CP6NHzaNQ','--B0mtbHavg','4owaxuzEbCc','wJTbhjE8ol0','TSbPYLI1ZKk','YaqEacpw-kE','LvqwN1WwQ14'];
$knownIds = array_flip($knownIds);

$files = array_merge(glob('*.php'), glob('blog/*.php'));
$errors = [];
$checked = 0; $withSection = 0; $withSchema = 0; $locNoSchema = 0;
$idCount = []; $bucketVideoUse = [];

foreach ($files as $file) {
    $base = basename($file);
    if ($base === 'add_youtube_videos.php' || $base === 'verify_youtube_videos.php') continue;
    $c = file_get_contents($file);

    $startN = substr_count($c, '<!-- SBI YouTube Videos Section -->');
    $endN   = substr_count($c, '<!-- End SBI YouTube Videos Section -->');
    if ($startN === 0) continue; // page intentionally not targeted
    $checked++;

    if ($startN !== 1 || $endN !== 1)
        $errors[] = "$file: expected 1 section block, got start=$startN end=$endN";

    if (substr_count($c, '<section class="sbi-vid"') !== 1)
        $errors[] = "$file: expected exactly 1 <section class=sbi-vid>";

    // Section must sit before the footer include
    $secPos = strpos($c, '<!-- SBI YouTube Videos Section -->');
    if (preg_match('/<\?php\s+include\s+[^;]*footer\.php[^;]*;\s*\?>/i', $c, $m, PREG_OFFSET_CAPTURE)) {
        if ($secPos > $m[0][1]) $errors[] = "$file: section appears AFTER footer include";
    } else {
        $errors[] = "$file: no footer include found";
    }

    // Video ids
    preg_match_all('/data-ytid="([^"]+)"/', $c, $vm);
    $n = count($vm[1]);
    if ($n < 1 || $n > 3) $errors[] = "$file: has $n video cards (expected 1-3)";
    foreach ($vm[1] as $id) {
        if (!isset($knownIds[$id])) $errors[] = "$file: unknown video id '$id'";
        $idCount[$id] = ($idCount[$id] ?? 0) + 1;
    }
    // thumb count must equal id count
    if (substr_count($c, 'class="sbi-vid-thumb"') !== $n)
        $errors[] = "$file: thumb count != data-ytid count";

    // Schema rule: location pages (…-in-…, not blog) must have NO VideoObject; base + blog must have it
    $isBlog = (strpos($file, 'blog/') === 0 || strpos($file, 'blog\\') === 0);
    $slug = preg_replace('/\.php$/', '', $base);
    $isLocation = (!$isBlog && strpos($slug, '-in-') !== false);
    $voN = substr_count($c, '"@type":"VideoObject"');

    if ($isLocation) {
        if ($voN !== 0) $errors[] = "$file: location page should have 0 VideoObject, has $voN";
        else $locNoSchema++;
    } else {
        if ($voN !== $n) $errors[] = "$file: base/blog VideoObject count $voN != video count $n";
        else $withSchema++;
        // Validate JSON-LD inside the section is parseable
        if (preg_match('/<!-- SBI YouTube Videos Section -->.*?(<script type="application\/ld\+json">)(.*?)(<\/script>).*?<!-- End SBI YouTube Videos Section -->/is', $c, $jm)) {
            $decoded = json_decode(trim($jm[2]));
            if ($decoded === null) $errors[] = "$file: invalid JSON-LD in video schema";
        }
    }
    $withSection++;
}

echo "Pages with a video section : $withSection\n";
echo "  - with VideoObject schema : $withSchema (base + blog)\n";
echo "  - location pages, no schema: $locNoSchema\n";
echo "Distinct videos used        : " . count($idCount) . " of 14\n";
arsort($idCount);
foreach ($idCount as $id => $ct) echo "    $id : $ct pages\n";
echo "\nERRORS: " . count($errors) . "\n";
foreach (array_slice($errors, 0, 40) as $e) echo "  - $e\n";
if (count($errors) > 40) echo "  ... and " . (count($errors) - 40) . " more\n";
echo (count($errors) === 0 ? "\nALL CHECKS PASSED.\n" : "\nVALIDATION FAILED.\n");

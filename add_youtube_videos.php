<?php
/**
 * add_youtube_videos.php
 * -----------------------------------------------------------------------------
 * Injects a relevant "Video Library" section (YouTube) into every service page,
 * every service+location page and every blog post.
 *
 *  - Videos are pulled from the Spine and Brain India YouTube channel
 *    (https://www.youtube.com/channel/UCbz9yM8ctTflvFKerm_klAg).
 *  - Each page gets 1-3 topically RELEVANT videos, chosen from the page slug.
 *  - Location pages (…-in-<city>) prioritise city-specific patient testimonials.
 *  - Uses a lightweight click-to-load facade (thumbnail -> nocookie iframe) so
 *    Core Web Vitals are not hurt by embedding YouTube on thousands of pages.
 *  - Shared CSS/JS is added ONCE to footer.php; only the per-page section is
 *    injected into each page (small, idempotent diffs).
 *  - VideoObject schema is added on canonical pages (base services + blog) only,
 *    to avoid mass-duplicated schema across the 4,800+ location pages.
 *
 * Idempotent: re-running removes the previous block and re-injects a fresh one.
 *
 * Usage:
 *   php add_youtube_videos.php           (DRY RUN - prints a report, writes nothing)
 *   php add_youtube_videos.php --run     (writes changes)
 * -----------------------------------------------------------------------------
 */

$DRY = !in_array('--run', $argv);
chdir(__DIR__);

/* ---------------------------------------------------------------------------
 * 1. Video master list  (id => title / upload date / description)
 * ------------------------------------------------------------------------- */
$V = [
    'mOv1ZCC1UY8' => ['title' => "Brain Tumour Surgery Recovery — Patient Testimonial", 'date' => '2025-09-17', 'desc' => "A patient shares their recovery journey after brain tumour surgery with neurosurgeon Dr. Arun Saroha."],
    'OTEn7-NDqsw' => ['title' => "Frequent Headache: Reasons You Should Not Ignore", 'date' => '2025-09-12', 'desc' => "Dr. Arun Saroha explains common reasons behind frequent headaches and when to seek help."],
    'KBrY8kbD0p4' => ['title' => "Back Pain Treatment — Patient Testimonial", 'date' => '2025-09-11', 'desc' => "A patient shares their experience of successful back pain treatment with Dr. Arun Saroha."],
    'ki888H99xzM' => ['title' => "Is Coffee Good for Your Brain?", 'date' => '2025-08-13', 'desc' => "Dr. Arun Saroha discusses caffeine and its effects on brain health."],
    'TCogn2SY3ys' => ['title' => "When Should You See a Neurologist?", 'date' => '2025-08-01', 'desc' => "Dr. Arun Saroha explains the warning signs that mean you should consult a neurologist."],
    'X2yWKJKHs2A' => ['title' => "Scoliosis Treatment — Patient Testimonial", 'date' => '2025-07-29', 'desc' => "A scoliosis patient shares their treatment journey with Dr. Arun Saroha."],
    'ClE0JRMY1iE' => ['title' => "Disc Problem & Herniated Disc Treatment", 'date' => '2025-07-23', 'desc' => "Dr. Arun Saroha explains herniated (slipped) disc problems and treatment options."],
    'N-CP6NHzaNQ' => ['title' => "How to Boost Your Memory", 'date' => '2025-07-16', 'desc' => "Dr. Arun Saroha shares practical tips to improve memory and brain health."],
    '--B0mtbHavg' => ['title' => "Top Nutrition Tips for a Sharp Mind", 'date' => '2024-08-21', 'desc' => "Dr. Arun Saroha's nutrition tips to keep your brain sharp and healthy."],
    '4owaxuzEbCc' => ['title' => "Essential Tips for Stroke Prevention", 'date' => '2024-08-17', 'desc' => "Dr. Arun Saroha shares essential lifestyle tips to help prevent stroke."],
    'wJTbhjE8ol0' => ['title' => "Top Spine Pain Relief Tips", 'date' => '2024-08-09', 'desc' => "Dr. Arun Saroha shares expert tips for relieving spine and back pain."],
    'TSbPYLI1ZKk' => ['title' => "Tips for a Smooth Spine Surgery Recovery", 'date' => '2024-08-05', 'desc' => "Dr. Arun Saroha's top tips for a smooth recovery after spine surgery."],
    'YaqEacpw-kE' => ['title' => "Understanding a Complex Neurological Condition", 'date' => '2024-02-19', 'desc' => "Dr. Arun Saroha explains the intricacies of a complex neurological condition."],
    'LvqwN1WwQ14' => ['title' => "Disc Replacement Surgery — Overview", 'date' => '2024-02-09', 'desc' => "An overview of artificial disc replacement surgery by Dr. Arun Saroha."],
];

/* ---------------------------------------------------------------------------
 * 2. Topic buckets -> ordered video ids + human label
 * ------------------------------------------------------------------------- */
$B = [
    'spine'        => ['label' => 'spine health and surgery',              'ids' => ['wJTbhjE8ol0', 'TSbPYLI1ZKk', 'ClE0JRMY1iE']],
    'disc'         => ['label' => 'disc problems and disc replacement',    'ids' => ['ClE0JRMY1iE', 'LvqwN1WwQ14', 'wJTbhjE8ol0']],
    'discrepl'     => ['label' => 'artificial disc replacement surgery',   'ids' => ['LvqwN1WwQ14', 'ClE0JRMY1iE', 'TSbPYLI1ZKk']],
    'backpain'     => ['label' => 'back pain relief and treatment',        'ids' => ['KBrY8kbD0p4', 'wJTbhjE8ol0', 'ClE0JRMY1iE']],
    'headache'     => ['label' => 'headaches and migraine',                'ids' => ['OTEn7-NDqsw', 'TCogn2SY3ys', 'YaqEacpw-kE']],
    'braintumor'   => ['label' => 'brain tumour diagnosis and surgery',    'ids' => ['mOv1ZCC1UY8', 'N-CP6NHzaNQ', '--B0mtbHavg']],
    'stroke'       => ['label' => 'stroke, brain bleed and prevention',    'ids' => ['4owaxuzEbCc', 'mOv1ZCC1UY8', '--B0mtbHavg']],
    'neuro'        => ['label' => 'neurological conditions and brain care', 'ids' => ['YaqEacpw-kE', 'TCogn2SY3ys', 'ki888H99xzM']],
    'scoliosis'    => ['label' => 'scoliosis correction and spine care',   'ids' => ['X2yWKJKHs2A', 'wJTbhjE8ol0', 'TSbPYLI1ZKk']],
    'neurosurgeon' => ['label' => 'brain and spine neurosurgery',          'ids' => ['mOv1ZCC1UY8', 'X2yWKJKHs2A', 'YaqEacpw-kE']],
    'brainhealth'  => ['label' => 'brain health, memory and nutrition',    'ids' => ['N-CP6NHzaNQ', '--B0mtbHavg', 'ki888H99xzM']],
    'nerve'        => ['label' => 'nerve conditions and spine care',       'ids' => ['YaqEacpw-kE', 'wJTbhjE8ol0', 'TSbPYLI1ZKk']],
];

/* ---------------------------------------------------------------------------
 * 3. Page slug prefix -> topic bucket
 * ------------------------------------------------------------------------- */
$prefixBucket = [
    // 29 service prefixes used by the …-in-<city> pages
    'atlanto-axial-dislocations'          => 'spine',
    'back-pain-treatment'                 => 'backpain',
    'best-neurosurgeon'                   => 'neurosurgeon',
    'best-treatment-cervical-spondylosis' => 'spine',
    'blood-clot-brain-treatment'          => 'stroke',
    'brachial-plexus-treatment'           => 'nerve',
    'brain-aneurysm-treatment'            => 'stroke',
    'brain-tumor-surgery'                 => 'braintumor',
    'cervical-spine-surgery'              => 'spine',
    'cluster-headache-treatment'          => 'headache',
    'degenerative-disc-disease-treatment' => 'disc',
    'disc-replacement-surgery'            => 'discrepl',
    'endoscopic-spine-tumor-surgery'      => 'spine',
    'glioblastoma-treatment'              => 'braintumor',
    'headache-treatment'                  => 'headache',
    'herniated-disc-surgery'              => 'disc',
    'hydrocephalus-treatment'             => 'neuro',
    'meningioma-surgery'                  => 'braintumor',
    'microvascular-decompression-surgery' => 'neuro',
    'migraine-treatment'                  => 'headache',
    'nerve-graft-surgery'                 => 'nerve',
    'neurologist'                         => 'neuro',
    'neurosurgeon'                        => 'neurosurgeon',
    'pituitary-adenoma-treatment'         => 'braintumor',
    'scoliosis-treatment'                 => 'scoliosis',
    'sinus-headache-treatment'            => 'headache',
    'spinal-stenosis-surgery'             => 'spine',
    'spinal-tuberculosis-treatment'       => 'spine',
    'tension-headache-treatment'          => 'headache',
    // base condition / service pages
    'acoustic-neuroma'                    => 'braintumor',
    "alzheimer's-disease"                 => 'brainhealth',
    'aneurysm-surgery'                    => 'stroke',
    'arachnoid-cyst'                      => 'neuro',
    'arnold-chiari-malformation'          => 'neuro',
    'arteriovenous-fistula'               => 'stroke',
    'astrocytoma'                         => 'braintumor',
    'av-malformation'                     => 'stroke',
    'brain-stem-glioma'                   => 'braintumor',
    'csf-leak'                            => 'headache',
    'epilepsy'                            => 'neuro',
    'head-injury'                         => 'neuro',
    'hydrocephalus'                       => 'neuro',
    'medulloblastoma'                     => 'braintumor',
    'meningioma'                          => 'braintumor',
    'nerve-root-compression'              => 'disc',
    'optic-nerve-tumor'                   => 'braintumor',
    "parkinson's"                         => 'brainhealth',
    'pituitary-tumor'                     => 'braintumor',
    'spina-bifida'                        => 'spine',
    'spinal-cord-injury'                  => 'spine',
    'spinal-degeneration'                 => 'disc',
    'spinal-herniation'                   => 'disc',
    'spinal-tumor'                        => 'spine',
    'stroke'                              => 'stroke',
    'cyberknife-vs-gamma-knife-vs-surgery' => 'braintumor',
    'open-vs-minimally-invasive-brain-surgery' => 'neuro',
];

/* Location pages: prioritise city-specific testimonials where we have them. */
$cityBoost = [
    'gurgaon'  => ['mOv1ZCC1UY8', 'X2yWKJKHs2A'],
    'gurugram' => ['mOv1ZCC1UY8', 'X2yWKJKHs2A'],
    'dwarka'   => ['KBrY8kbD0p4'],
    'delhi'    => ['KBrY8kbD0p4', 'mOv1ZCC1UY8'],
];

/* Pages we must never touch (partials, forms, utilities, non-service pages). */
$excludeExact = [
    'header.php', 'footer.php', 'service-sidebar.php', 'cta-form.php', 'single-line-form.php',
    'important-links.php', 'mail.php', 'thankyou.php', 'test.php', 'test_cleaning.php', 'test_output.php',
    'index.php', 'index-2.php', 'about.php', 'contact.php', 'gallery.php', 'international.php',
    'blog.php', 'patients-reviews.php', 'foreigner-reviews.php',
    'add_youtube_videos.php',
];
$excludePrefixes = ['generate_', 'add_', 'test_', 'update_', 'remove_'];

/* ---------------------------------------------------------------------------
 * Blog slug -> bucket (keyword scan, most specific first)
 * ------------------------------------------------------------------------- */
function sbi_blog_bucket($slug)
{
    $s = strtolower($slug);
    $rules = [
        'scoliosis' => 'scoliosis',
        'disc-replacement' => 'discrepl', 'artificial-disc' => 'discrepl',
        'slipped-disc' => 'disc', 'slip-disc' => 'disc', 'herniat' => 'disc', 'degenerative-disc' => 'disc',
        'brain-tumor' => 'braintumor', 'brain-tumour' => 'braintumor', 'tumor' => 'braintumor', 'tumour' => 'braintumor', 'glioma' => 'braintumor',
        'stroke' => 'stroke', 'blood-clot' => 'stroke', 'brain-bleed' => 'stroke', 'brain-hemorrhage' => 'stroke', 'hemorrhage' => 'stroke', 'haemorrhage' => 'stroke', 'aneurysm' => 'stroke', 'brain-dead' => 'stroke', 'oxygen' => 'stroke',
        'migraine' => 'headache', 'headache' => 'headache', 'sinus' => 'headache',
        'endoscopic-spine' => 'spine', 'minimally-invasive-spine' => 'spine', 'cervical' => 'spine', 'neck-pain' => 'spine', 'neck' => 'spine', 'spine-surgery' => 'spine', 'spinal' => 'spine', 'spine' => 'spine',
        'back-pain' => 'backpain', 'back' => 'backpain',
        'memory' => 'brainhealth', 'intelligence' => 'brainhealth', 'brain-power' => 'brainhealth', 'brain-of-a-child' => 'brainhealth', 'reflex' => 'brainhealth', 'respiration' => 'brainhealth', 'blood-pressure' => 'brainhealth', 'voluntary' => 'brainhealth', 'most-developed' => 'brainhealth', 'protected' => 'brainhealth', 'protection' => 'brainhealth',
        'neurosurg' => 'neurosurgeon',
        'neurolog' => 'neuro',
    ];
    foreach ($rules as $kw => $b) {
        if (strpos($s, $kw) !== false) return $b;
    }
    return 'neuro';
}

/* Choose up to 3 video ids for a page.
 * Topical video always leads; on a location page we insert ONE city-specific
 * patient testimonial as the 2nd card (if not already present). This keeps the
 * page topically relevant first, with a local-proof testimonial second. */
function sbi_pick_ids($bucket, $city, $B, $cityBoost)
{
    $ids = $B[$bucket]['ids'];
    if ($city !== '' && isset($cityBoost[$city])) {
        foreach ($cityBoost[$city] as $bid) {
            if (!in_array($bid, $ids, true)) {
                array_splice($ids, 1, 0, $bid); // insert as the 2nd video
                break;
            }
        }
    }
    return array_slice($ids, 0, 3);
}

/* Build the visible <section> (+ optional VideoObject schema). */
function sbi_build_section($ids, $V, $heading, $subtitle, $withSchema)
{
    $cards = '';
    foreach ($ids as $id) {
        if (!isset($V[$id])) continue;
        $t = htmlspecialchars($V[$id]['title'], ENT_QUOTES);
        $thumb = 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
        $cards .= '
        <div class="sbi-vid-card">
          <div class="sbi-vid-frame">
            <button type="button" class="sbi-vid-thumb" data-ytid="' . $id . '" data-title="' . $t . '" style="background-image:url(\'' . $thumb . '\')" aria-label="Play video: ' . $t . '"><span class="sbi-vid-play"></span></button>
          </div>
          <p class="sbi-vid-title">' . $t . '</p>
        </div>';
    }

    $schema = '';
    if ($withSchema) {
        $items = [];
        foreach ($ids as $id) {
            if (!isset($V[$id])) continue;
            $v = $V[$id];
            $items[] = '{'
                . '"@type":"VideoObject",'
                . '"name":' . json_encode($v['title'], JSON_UNESCAPED_SLASHES) . ','
                . '"description":' . json_encode($v['desc'], JSON_UNESCAPED_SLASHES) . ','
                . '"thumbnailUrl":"https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg",'
                . '"uploadDate":"' . $v['date'] . '",'
                . '"contentUrl":"https://www.youtube.com/watch?v=' . $id . '",'
                . '"embedUrl":"https://www.youtube-nocookie.com/embed/' . $id . '",'
                . '"publisher":{"@type":"Organization","name":"Spine and Brain India","logo":{"@type":"ImageObject","url":"https://spineandbrainindia.com/assets/img/logo/logoSSB%207777777_11zon.webp"}}'
                . '}';
        }
        $schema = "\n" . '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[' . implode(',', $items) . ']}</script>';
    }

    return '<!-- SBI YouTube Videos Section -->
<section class="sbi-vid" aria-label="Videos by Dr. Arun Saroha">
  <div class="sbi-vid-wrap">
    <div class="sbi-vid-head">
      <span class="sbi-vid-eyebrow"><svg width="18" height="18" viewBox="0 0 24 24" fill="#dc2626" aria-hidden="true"><path d="M23 12s0-3.9-.5-5.8a3 3 0 0 0-2.1-2.1C18.5 3.6 12 3.6 12 3.6s-6.5 0-8.4.5A3 3 0 0 0 1.5 6.2C1 8.1 1 12 1 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.5 8.4.5 8.4.5s6.5 0 8.4-.5a3 3 0 0 0 2.1-2.1C23 15.9 23 12 23 12zM9.8 15.5v-7l6.2 3.5-6.2 3.5z"/></svg> Video Library</span>
      <h2>' . $heading . '</h2>
      <p>' . $subtitle . '</p>
    </div>
    <div class="sbi-vid-grid">' . $cards . '
    </div>
  </div>
</section>' . $schema . '
<!-- End SBI YouTube Videos Section -->
';
}

/* Shared CSS + JS facade handler (added once to footer.php). */
function sbi_assets_block()
{
    $css = <<<'CSS'
<style id="sbi-vid-css">
.sbi-vid{background:#f1f5f9;padding:56px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
.sbi-vid *{box-sizing:border-box}
.sbi-vid .sbi-vid-wrap{max-width:1140px;margin:0 auto;padding:0 15px}
.sbi-vid-head{text-align:center;margin:0 auto 34px}
.sbi-vid-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#dc2626;margin-bottom:10px}
.sbi-vid-head h2{font-size:30px;line-height:1.25;font-weight:800;color:#0f172a;margin:0 0 12px}
.sbi-vid-head p{font-size:16px;line-height:1.6;color:#475569;max-width:760px;margin:0 auto}
.sbi-vid-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}
.sbi-vid-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,.06)}
.sbi-vid-frame{position:relative;width:100%;padding-top:56.25%;background:#000}
.sbi-vid-frame iframe,.sbi-vid-thumb{position:absolute;top:0;left:0;width:100%;height:100%;border:0}
.sbi-vid-thumb{cursor:pointer;background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center;padding:0}
.sbi-vid-thumb::after{content:"";position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,.18);transition:background .2s}
.sbi-vid-thumb:hover::after{background:rgba(15,23,42,.05)}
.sbi-vid-play{position:relative;z-index:1;width:68px;height:48px;background:#dc2626;border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(220,38,38,.45);transition:transform .2s}
.sbi-vid-thumb:hover .sbi-vid-play{transform:scale(1.08)}
.sbi-vid-play::before{content:"";border-style:solid;border-width:11px 0 11px 18px;border-color:transparent transparent transparent #fff;margin-left:4px}
.sbi-vid-title{font-size:15px;font-weight:600;line-height:1.45;color:#1e293b;margin:0;padding:16px 18px}
@media (max-width:575px){.sbi-vid{padding:40px 0}.sbi-vid-head h2{font-size:24px}}
</style>
CSS;

    $js = <<<'JS'
<script id="sbi-vid-js">
(function(){
  if(window.__sbiVidInit){return;}window.__sbiVidInit=1;
  document.addEventListener('click',function(e){
    var b=(e.target&&e.target.closest)?e.target.closest('.sbi-vid-thumb'):null;
    if(!b){return;}
    var id=b.getAttribute('data-ytid');if(!id){return;}
    var f=b.closest('.sbi-vid-frame');if(!f){return;}
    var ifr=document.createElement('iframe');
    ifr.setAttribute('src','https://www.youtube-nocookie.com/embed/'+id+'?autoplay=1&rel=0');
    ifr.setAttribute('title',b.getAttribute('data-title')||'YouTube video');
    ifr.setAttribute('allow','accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    ifr.setAttribute('allowfullscreen','');
    ifr.setAttribute('loading','lazy');
    f.innerHTML='';f.appendChild(ifr);
  },false);
})();
</script>
JS;

    return "\n<!-- SBI Video Assets -->\n" . $css . "\n" . $js . "\n<!-- End SBI Video Assets -->\n";
}

/* ---------------------------------------------------------------------------
 * 4. Ensure footer.php carries the shared CSS/JS (once, idempotent).
 * ------------------------------------------------------------------------- */
$footerFile = 'footer.php';
$footerNote = '';
if (is_file($footerFile)) {
    $fc = file_get_contents($footerFile);
    $fc = preg_replace('/\s*<!-- SBI Video Assets -->.*?<!-- End SBI Video Assets -->\s*/is', "\n", $fc);
    if (stripos($fc, '</body>') !== false) {
        $fc = preg_replace('/<\/body>/i', sbi_assets_block() . "\n    </body>", $fc, 1);
        $footerNote = 'footer.php assets: inserted before </body>';
    } else {
        $fc .= sbi_assets_block();
        $footerNote = 'footer.php assets: appended (no </body> found)';
    }
    if (!$DRY) file_put_contents($footerFile, $fc);
} else {
    $footerNote = 'footer.php NOT FOUND';
}

/* ---------------------------------------------------------------------------
 * 5. Build the target file list  (root service/location pages + blog posts)
 * ------------------------------------------------------------------------- */
$rootFiles = glob('*.php');
$blogFiles = glob('blog/*.php');
$files = array_merge($rootFiles, $blogFiles);

$footerRe = '/<\?php\s+include\s+[^;]*footer\.php[^;]*;\s*\?>/i';
$blockRe  = '/<!-- SBI YouTube Videos Section -->.*?<!-- End SBI YouTube Videos Section -->\s*/is';

$stats = ['injected' => 0, 'skipped_excluded' => 0, 'skipped_no_anchor' => 0, 'unknown_prefix' => 0];
$unknownList = [];
$samples = [];

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $base = basename($file);
    $isBlog = (strpos($file, 'blog/') === 0 || strpos($file, 'blog\\') === 0);

    // Exclusions (root partials/utilities; blog listing index)
    $skip = false;
    if (!$isBlog) {
        if (in_array($base, $excludeExact, true)) $skip = true;
        foreach ($excludePrefixes as $p) if (strpos($base, $p) === 0) $skip = true;
    } else {
        if ($base === 'index.php') $skip = true;
    }
    if ($skip) { $stats['skipped_excluded']++; continue; }

    $content = file_get_contents($file);
    if (!preg_match($footerRe, $content)) { $stats['skipped_no_anchor']++; continue; }

    $slug = preg_replace('/\.php$/', '', $base);

    // Determine treatment / location / bucket
    $city = '';
    if ($isBlog) {
        $bucket = sbi_blog_bucket($slug);
        $treatmentTitle = '';
    } else {
        if (strpos($slug, '-in-') !== false) {
            $parts    = explode('-in-', $slug, 2);
            $prefix   = $parts[0];
            $city     = strtolower($parts[1]);
        } else {
            $prefix = $slug;
        }
        if (isset($prefixBucket[$prefix])) {
            $bucket = $prefixBucket[$prefix];
        } else {
            $bucket = 'neurosurgeon';
            $stats['unknown_prefix']++;
            if (!in_array($prefix, $unknownList, true)) $unknownList[] = $prefix;
        }
        $treatmentTitle = ucwords(str_replace('-', ' ', $prefix));
    }

    $ids   = sbi_pick_ids($bucket, $city, $B, $cityBoost);
    $label = $B[$bucket]['label'];

    // Headings / copy
    if ($isBlog) {
        $heading  = 'Related Videos by Dr. Arun Saroha';
        $subtitle = 'Watch Dr. Arun Saroha, one of India&rsquo;s leading brain &amp; spine surgeons, share expert insights on ' . htmlspecialchars($label) . ' &mdash; related to this article.';
        $withSchema = true;
    } elseif ($city !== '') {
        $locationTitle = ucwords(str_replace('-', ' ', $city));
        $heading  = htmlspecialchars($treatmentTitle) . ' in ' . htmlspecialchars($locationTitle) . ': Watch Expert Videos';
        $subtitle = 'Dr. Arun Saroha shares patient testimonials and expert guidance on ' . htmlspecialchars($label) . ' for patients in ' . htmlspecialchars($locationTitle) . ' and across India.';
        $withSchema = false;
    } else {
        $heading  = 'Watch: ' . htmlspecialchars($treatmentTitle) . ' Videos by Dr. Arun Saroha';
        $subtitle = 'Dr. Arun Saroha, one of India&rsquo;s leading brain &amp; spine surgeons, shares patient testimonials and expert insights on ' . htmlspecialchars($label) . '.';
        $withSchema = true;
    }

    $section = sbi_build_section($ids, $V, $heading, $subtitle, $withSchema);

    // Remove any previous block, then inject before the (first) footer include.
    $content = preg_replace($blockRe, '', $content);
    $content = preg_replace($footerRe, $section . "\n$0", $content, 1);

    if (!$DRY) file_put_contents($file, $content);
    $stats['injected']++;

    if (count($samples) < 6) {
        $samples[$file] = ['bucket' => $bucket, 'ids' => $ids, 'schema' => $withSchema, 'heading' => html_entity_decode($heading)];
    }
}

/* ---------------------------------------------------------------------------
 * 6. Report
 * ------------------------------------------------------------------------- */
echo ($DRY ? "=== DRY RUN (no files written) ===\n" : "=== LIVE RUN ===\n");
echo $footerNote . "\n";
echo "Root php files scanned: " . count($rootFiles) . "\n";
echo "Blog php files scanned: " . count($blogFiles) . "\n";
echo "Injected:            " . $stats['injected'] . "\n";
echo "Skipped (excluded):  " . $stats['skipped_excluded'] . "\n";
echo "Skipped (no footer anchor): " . $stats['skipped_no_anchor'] . "\n";
echo "Unknown prefixes (fell back to 'neurosurgeon'): " . $stats['unknown_prefix'] . "\n";
if ($unknownList) echo "  -> " . implode(', ', $unknownList) . "\n";
echo "\n--- Sample selections ---\n";
foreach ($samples as $f => $s) {
    echo sprintf("%-52s bucket=%-12s schema=%s\n   ids=%s\n   H: %s\n", $f, $s['bucket'], $s['schema'] ? 'Y' : 'N', implode(',', $s['ids']), $s['heading']);
}
echo "\nDone.\n";

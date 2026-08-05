<?php
$files = glob(__DIR__ . "/*.php");
$excludeFiles = [
  'header.php',
  'footer.php',
  'head.php',
  'script.php',
  'service-sidebar.php',
  'cta-form.php',
  'submit.php',
  'submit1.php',
  'test_output.php',
  'test_regex.php',
  'test.php',
  'google-script.php',
  'db.php',
  'db_connect.php',
  'config.php',
  'admin.php',
  'login.php'
];

$count = 0;
$skipped_excluded = 0;
$skipped_no_target = 0;

foreach ($files as $file) {
  if (!is_file($file))
    continue;
  $basename = basename($file);
  if (
    in_array($basename, $excludeFiles) ||
    strpos($basename, 'generate_') === 0 ||
    strpos($basename, 'test_') === 0 ||
    strpos($basename, 'add_') === 0 ||
    strpos($basename, 'remove_') === 0
  ) {
    $skipped_excluded++;
    continue;
  }

  $content = file_get_contents($file);

  // First, cleanup existing unified block if we generated it
  if (strpos($content, 'Unified SEO Schema and Tags') !== false) {
    $content = preg_replace('/<!-- Unified SEO Schema and Tags -->.*?<!-- End Unified SEO Schema and Tags -->\s*/is', '', $content);
  }

  // Find injection point
  $injection_target = '';
  if (strpos($content, '</head>') !== false) {
    $injection_target = '</head>';
  } else if (preg_match('/<\?php\s*include\s*[\'"]header\.php[\'"]\s*;\s*\?>/is', $content, $m)) {
    $injection_target = $m[0];
  } else {
    $skipped_no_target++;
    continue; // Cannot process, no target
  }

  $slug = str_replace('.php', '', $basename);

  // Extract title
  preg_match('/<title>(.*?)<\/title>/is', $content, $t_match);
  $title = isset($t_match[1]) ? trim($t_match[1]) : "Spine and Brain India";

  // Extract description
  preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']/is', $content, $d_match);
  $description = isset($d_match[1]) ? trim($d_match[1]) : "Specialized neurosurgical care in India by Dr. Arun Saroha.";

  // Extract location & treatment
  if (strpos($slug, '-in-') !== false) {
    $parts = explode('-in-', $slug);
    $treatmentRaw = $parts[0];
    $locationRaw = $parts[1];
  } else {
    $treatmentRaw = str_replace(['best-neurosurgeon', '-'], ['Neurosurgery', ' '], $slug);
    $locationRaw = 'India'; // Default fallback
  }

  $treatment = ucwords(str_replace('-', ' ', $treatmentRaw));
  $location = ucwords(str_replace('-', ' ', $locationRaw));

  // Clean old Open Graph tags
  $content = preg_replace('/<meta[^>]+property=["\']og:[^>]+>/is', '', $content);

  // Clean old Geo tags
  $content = preg_replace('/<meta[^>]+name=["\']geo\.[^>]+>/is', '', $content);
  $content = preg_replace('/<meta[^>]+name=["\']ICBM["\'][^>]+>/is', '', $content);

  // Clean old MedicalClinic schema only
  $content = preg_replace('/<script type=["\']application\/ld\+json["\']>\s*\{.*?"@type"\s*:\s*["\']MedicalClinic["\'].*?\}\s*<\/script>/is', '', $content);

  // Also remove generic comments
  $content = preg_replace('/<!--\s*Open\s*Graph\s*Tags\s*-->/is', '', $content);
  $content = preg_replace('/<!--\s*Schema\s*Markup\s*-->/is', '', $content);

  // Build unified SEO
  $unified_seo = '
<!-- Unified SEO Schema and Tags -->
<meta name="geo.region" content="IN" />
<meta name="geo.placename" content="' . htmlspecialchars($location, ENT_QUOTES) . '" />
<meta name="geo.position" content="20.5937;78.9629" />
<meta name="ICBM" content="20.5937, 78.9629" />

<meta property="og:type" content="website" />
<meta property="og:url" content="https://spineandbrainindia.com/' . htmlspecialchars($slug, ENT_QUOTES) . '" />
<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '" />
<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES) . '" />
<meta property="og:image" content="https://spineandbrainindia.com/assets/images/pag-top-bg_11zon.webp" />

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Physician",
      "name": "Dr. Arun Saroha",
      "url": "https://spineandbrainindia.com/dr-arun-saroha.php",
      "image": "https://spineandbrainindia.com/assets/images/resources2/drarundef_11zon.webp",
      "description": "Dr. Arun Saroha is a leading neurosurgeon in India with over 26+ years of experience.",
      "medicalSpecialty": "Neurosurgery",
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.9",
        "reviewCount": "1045"
      },
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "' . htmlspecialchars($location, ENT_QUOTES) . '",
        "addressCountry": "IN"
      }
    },
    {
      "@type": "MedicalClinic",
      "name": "Spine and Brain India - ' . htmlspecialchars($location, ENT_QUOTES) . '",
      "url": "https://spineandbrainindia.com/' . htmlspecialchars($slug, ENT_QUOTES) . '",
      "image": "https://spineandbrainindia.com/assets/img/logo/logoSSB%207777777_11zon.webp",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "' . htmlspecialchars($location, ENT_QUOTES) . '",
        "addressCountry": "IN"
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.8",
        "reviewCount": "850"
      },
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": "20.5937",
        "longitude": "78.9629"
      }
    },
    {
      "@type": "MedicalProcedure",
      "name": "' . htmlspecialchars($treatment, ENT_QUOTES) . '",
      "provider": {
        "@id": "https://spineandbrainindia.com/dr-arun-saroha.php"
      }
    }
  ]
}
</script>
<!-- End Unified SEO Schema and Tags -->
';

  // Inject at the injection point
  $content = str_replace($injection_target, $unified_seo . "\n" . $injection_target, $content);

  file_put_contents($file, $content);
  $count++;
}

echo "Total PHP files successfully processed: $count\n";
echo "Skipped no injection target (no </head> and no header.php include): $skipped_no_target\n";
echo "Skipped excluded: $skipped_excluded\n";

?>
<?php
// Levantamento de widgets Elementor por plugin pago, só em conteúdo publicado.
// Uso: php scripts/inventario-widgets.php
$mysqli = new mysqli('127.0.0.1', 'riocargas', 'riocargas_local', 'riocargas');
$mysqli->set_charset('utf8mb4');

$res = $mysqli->query("
  SELECT pm.post_id, p.post_type, p.post_status, p.post_name, p.post_title, pm.meta_value
  FROM wpg8_postmeta pm
  JOIN wpg8_posts p ON p.ID = pm.post_id
  WHERE pm.meta_key = '_elementor_data'
    AND p.post_status IN ('publish','private','draft')
    AND pm.meta_value LIKE '%\"widgetType\\\":\\\"%'
");

$padroes = array(
  'master-addons' => '/"widgetType":"(ma-[^"]+|maad-[^"]+)"/',
  'powerpack'     => '/"widgetType":"(pp-[^"]+)"/',
  'unlimited'     => '/"widgetType":"(ucaddon_[^"]+)"/',
  'elementor-pro' => '/"widgetType":"(animated-headline|site-logo|theme-site-logo|posts|portfolio|nav-menu|search-form|login|form|gallery|call-to-action|media-carousel|slides|flip-box|price-table|price-list|countdown|share-buttons|blockquote|review|testimonial-carousel|table-of-contents|author-box|post-info|post-title|post-excerpt|post-featured-image|post-comments|post-navigation|sitemap|breadcrumbs|loop-grid|loop-item|woocommerce-[^"]+)"/',
  'wpforms'       => '/"widgetType":"(wpforms[^"]*)"/',
);

$porWidget = array(); // widget => [post_id => info]
while ($row = $res->fetch_assoc()) {
  foreach ($padroes as $plugin => $re) {
    if (preg_match_all($re, $row['meta_value'], $m)) {
      foreach (array_unique($m[1]) as $w) {
        $porWidget[$plugin][$w][$row['post_id']] = "{$row['post_type']}|{$row['post_status']}|{$row['post_name']}|{$row['post_title']}";
      }
    }
  }
}

foreach ($porWidget as $plugin => $widgets) {
  echo "\n=== $plugin ===\n";
  foreach ($widgets as $w => $posts) {
    echo "  $w  (" . count($posts) . " posts)\n";
    foreach ($posts as $pid => $info) { echo "    - #$pid  $info\n"; }
  }
}

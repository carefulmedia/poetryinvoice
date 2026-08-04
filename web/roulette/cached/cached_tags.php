<?php

require_once __DIR__ . '/cache-path.php';

$lang = $_GET['lang'] ?? 'en';
$c = $_GET['c'] ?? 's';
ini_set('default_socket_timeout', 5);

$nodes = roulette_load_cache('cached_tags_' . $lang . '.json');
if (!$nodes) {
  http_response_code(503);
  echo '<!-- Roulette tags cache missing. -->';
  exit;
}

$number = count($nodes);
$third = floor($number / 3);
$twoThird = floor($number / 1.5);
$i = 0;
$j = 1;
echo '<div class="circleContainer col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="tagMachine1" type="tag" number="1" class="slotMachine ">';
foreach ($nodes as $node) {
  if ($i < $third) {
    if ($i == 0) {
      $j = 1;
    }
    echo '<div class="slot slot' . $j . '" name="' . $node->tagId . '"  title="' . $node->name . '" ><img class="img-responsive" src="' . $node->image . '" /></div>';

    if ($i == $third - 1) {
      echo '</div></div>';
    }
    $j++;
  }
  elseif ($i < $twoThird) {
    if ($i == $third) {
      echo '<div class="circleContainer col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="tagMachine2" class="slotMachine "  type="tag" number="2" >';
      $j = 1;
    }
    echo '<div class="slot slot' . $j . '" name="' . $node->tagId . '"  title="' . $node->name . '"  ><img class="img-responsive" src="' . $node->image . '" /></div>';
    if ($i == $twoThird - 1) {
      echo '</div></div>';
    }
    $j++;
  }
  else {
    if ($i == $twoThird) {
      echo '<div class="circleContainer col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="tagMachine3" class="slotMachine "  type="tag" number="3" >';
      $j = 1;
    }
    echo '<div class="slot slot' . $j . '" name="' . $node->tagId . '"  title="' . $node->name . '"  ><img class="img-responsive" src="' . $node->image . '" /></div>';

    $j++;
  }
  $i++;
}
echo '</div></div>';

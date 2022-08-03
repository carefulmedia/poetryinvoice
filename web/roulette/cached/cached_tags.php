  <?php
    // copy file content into a string var
	$lang = $_GET['lang'];
	$c = $_GET['c'];
echo($lang);
    $json_file = file_get_contents(__DIR__ . '/roulette/cached/data/cached_tags_'.$lang.'.json');
    // convert the string to a json object
    $jfo = json_decode($json_file);

    // copy the posts array to a php var
    $nodes = $jfo->nodes;
    // listing posts
//	echo '<pre>'; print_r($nodes); echo '</pre>';
	$number = sizeof($nodes);
	$third = floor($number / 3); 
	$twoThird =floor($number / 1.5); 
		 $i=0; $j=1;
     echo '<div class="circleContainer col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="tagMachine1" type="tag" number="1" class="slotMachine ">';
	foreach ($nodes as $node) {      

	 if ($i < $third  ) {
	 	if ($i == 0) {$j=1;	}
	 	echo  '<div class="slot slot'.$j.'" name="' . $node->node->tagId  . '"  title="' . $node->node->name  . '" ><img class="img-responsive" src="'. $node->node->image  .'" /></div>' ;

	 	if ($i == $third - 1) {echo '</div></div>'	;}
		$j++;
		}
	 elseif  ($i < $twoThird ) {
	 	if ($i == $third) {echo '<div class="circleContainer col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="tagMachine2" class="slotMachine "  type="tag" number="2" >';$j=1;	}
	 	echo  '<div class="slot slot'.$j.'" name="' . $node->node->tagId  . '"  title="' . $node->node->name  . '"  ><img class="img-responsive" src="'. $node->node->image  .'" /></div>' ;
	 	if ($i == $twoThird - 1) {echo '</div></div>'	;	}	 
		$j++;
		 } 
     else {
	 	if ($i == $twoThird) {echo '<div class="circleContainer col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="tagMachine3" class="slotMachine "  type="tag" number="3" >';	$j=1;}
	 	echo  '<div class="slot slot'.$j.'" name="' . $node->node->tagId  . '"  title="' . $node->node->name  . '"  ><img class="img-responsive" src="'. $node->node->image  .'" /></div>' ;

		$j++;
	 	 }
	$i++;
}
	echo '</div></div>';

    ?>



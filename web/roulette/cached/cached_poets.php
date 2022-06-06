     <?php
    // copy file content into a string var
	$lang = $_GET['lang'];
	$c = $_GET['c'];
    $json_file = file_get_contents('https://www.poetryinvoice.com/roulette/cached/cached_poets_'.$lang. '_' . $c .'.json');
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
     echo '<div class="circleContainer poetMachines col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="poetMachine1" class="slotMachine "   type="poet" number="1" >';
	foreach ($nodes as $node) {      

	 if ($i < $third  ) {
	 	if ($i == 0) {$j=1;	}
	 	echo  '<div class="slot slot'.$j.'" name="' . $node->node->Nid. '"  title="' . $node->node->title  . '" ><img class="img-responsive"  src="'. $node->node->image  .'" /></div>' ;
	 	if ($i == $third - 1) {echo '</div></div>'	;}
		$j++;
		}
	 elseif  ($i < $twoThird ) {
	 	if ($i == $third) {echo '<div class="circleContainer poetMachines col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="poetMachine2" class="slotMachine "   type="poet" number="2" >';$j=1;	}
	 	echo  '<div class="slot slot'.$j.'" name="' . $node->node->Nid. '"  title="' . $node->node->title  . '"  ><img class="img-responsive" src="'. $node->node->image  .'" /></div>' ;
	 	if ($i == $twoThird - 1) {echo '</div></div>'	;	}	 
		$j++;
		 } 
     else {
	 	if ($i == $twoThird) {echo '<div class="circleContainer poetMachines col-xs-4  col-sm-4 col-md-4"><div class="circleDummy"></div><div id="poetMachine3" class="slotMachine "   type="poet" number="3" >';	$j=1;}
	 	echo  '<div class="slot slot'.$j.'" name="' . $node->node->Nid. '"  title="' . $node->node->title  . '"  ><img class="img-responsive" src="'. $node->node->image  .'" /></div>' ;

		$j++;
	 	 }
	$i++;
}
	echo '</div></div>';

    ?>
 


<?php
if ($_SERVER['HTTP_HOST'] == 'www.poetryinvoice.com'){
	$lang = 'en';
	}
else {
	$lang = 'fr';
}
include 'vars.php';
// $header = file_get_contents('https://www.poetryinvoice.com/roulette/'. $lang .'/header.php');
// echo $header;
if(empty($c)){
$c = $_POST['c'];
}
?>
<div class="container-fluid">
 

  <div class="row logo">
    <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12">
    
      <div id="logo-title"><a href="../"> <?php echo '<img src="images/icons/roulette-logo_' . $lang  . '_' . $c . '.png"'; ?> class="img-responsive" /></a>  </div>
    </div>
  </div>
</div>
<div id="main" class="container" >
  <div class="row interactive">
    <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12">
      <div id="choices"  >
        <div clas="row">
          <div  class="col-xs-4 col-sm-4 col-md-4 choice1" id="poetMachineButtonInit" onClick="ga('send', 'pageview', '/virtual/roulette-en/poet/initial');" > <img src="images/icons/roulette-theme-poet.svg" class="img-responsive" />
            <h2 class="choice-title"><?php echo $vars['Poets'][$lang]; ?></h2>
          </div>
          <div  class="col-xs-4  col-sm-4 col-md-4 choice1" id="moodMachineButtonInit" onClick="ga('send', 'pageview', '/virtual/roulette-en/mood/initial');"> <img src="images/icons/roulette-theme-mood.svg" class="img-responsive" />
            <h2 class="choice-title"><?php echo $vars['Moods'][$lang]; ?></h2>
          </div>
          <div  class="col-xs-4  col-sm-4 col-md-4 choice1" id="tagMachineButtonInit" " onClick="ga('send', 'pageview', '/virtual/roulette-en/tag/initial');"> <img src="images/icons/roulette-theme-icon2.svg" class="img-responsive slotMachineButton1" />
            <h2 class="choice-title"><?php echo $vars['Tags'][$lang]; ?></h2>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div id="toggle2">&nbsp;
    <div class="row interactive">
    	<div class=" col-sm-6 col-sm-offset-2 col-xs-10 " id="loading"><img src="images/icons/loading.gif" /></div> 
      <div class=" col-sm-6 col-sm-offset-2 col-xs-10 " id="poem"></div>
      <div id="back2" class="col-xs-2"><img src="images/icons/right.png" height="60" width="40" /></div>
      </div>
      <div class="row" id="poemFooter">
 
            <div  class="poemButton" ><a href="" id="visitPoem" onClick="ga('send', 'pageview',  $(this).attr('href').replace('https://www.poetryinvoice.com','/virtual/roulette-en'));"><?php echo $vars['More'][$lang]; ?></a></div>

      </div>
    
  </div>
  <div id="tag-slider" class="slider">
    <div class="row">
      <div  class=" col-sm-8 col-sm-offset-2 col-xs-12 slider-up "><img src="images/icons/down.png" id="tagUp"  height="40" width="60"/></div>
    </div>
    <div class="row interactive">
      <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12 machineContainer">
        <div class="row">
          <?php

$tags = file_get_contents('https://www.poetryinvoice.com/roulette/cached/cached_tags.php?lang='.$lang.'&c='.$c);
echo $tags;
?>
        </div>
        <div class="row nameRow">
          <div id="tagMachine1Result" type="tag" number="1" class="col-xs-4 machineResult">&nbsp;</div>
          <div id="tagMachine2Result"  type="tag" number="2" class="col-xs-4 machineResult">&nbsp;</div>
          <div id="tagMachine3Result"  type="tag" number="3" class="col-xs-4 machineResult">&nbsp;</div>
        </div>
      </div>
    </div>
    <div class="row buttonRow " id="spinFooter">
      <button  class="slotMachineButton tagMachineButton1   "  ontouchend="this.onclick=fix" onClick="ga('send', 'pageview', '/virtual/roulette-en/tag/spin-again');" id="tagMachineButton1"><?php echo $vars['Spin'][$lang]; ?></button>
    </div>
  </div>

<script>
			$(document).ready(function() {
				var tagMachine1 = $("#tagMachine1").slotMachine({
					active	: 0,
					delay	: 100
				});
				
				var tagMachine2 = $("#tagMachine2").slotMachine({
					active	: 1,
					delay	: 50
				});
				
				var tagMachine3 = $("#tagMachine3").slotMachine({
					active	: 2,
					delay	: 50
				});
				
				function onComplete(active){

			switch(this.element[0].id){

				case 'tagMachine1':
					var tagIndex1 = this.active ;
					tagName =  $("#tagMachine1 .slotMachineContainer div:eq( "+ tagIndex1 +")").attr( "title");
					tagId = $("#tagMachine1 .slotMachineContainer div:eq( "+ tagIndex1 +")").attr( "name");
					$("#tagMachine1Result").text(tagName);
					$("#tagMachine1Result").attr( "name", tagId );
					break;
				case 'tagMachine2':
					var tagIndex2 = this.active;
					tagName = $("#tagMachine2 .slotMachineContainer div:eq( "+ tagIndex2 +")").attr( "title");
					tagId = $("#tagMachine2 .slotMachineContainer div:eq( "+ tagIndex2 +")").attr( "name");
					$("#tagMachine2Result").text(tagName);
					$("#tagMachine2Result").attr( "name", tagId );

					break;
				case 'tagMachine3':
					var tagIndex3 = this.active;
					tagName =  $("#tagMachine3 .slotMachineContainer div:eq( "+ tagIndex3 +")").attr( "title");
					tagId = $("#tagMachine3 .slotMachineContainer div:eq( "+ tagIndex3 +")").attr( "name");
					$("#tagMachine3Result").text(tagName);
					$("#tagMachine3Result").attr( "name", tagId );

					break;
			}
				}
				
				$("#tagMachineButtonInit").click(function(){
					$( "#tag-slider" ).show( "slide" , { direction: "down" }, 500 );
					setTimeout(function(){
					tagMachine1.shuffle(10, onComplete);
					}, 200);					
					setTimeout(function(){
						tagMachine2.shuffle(10, onComplete);
					}, 200);
					
					setTimeout(function(){
						tagMachine3.shuffle(10, onComplete);
					}, 500);

				})
				$("#tagMachineButton1").click(function(){
					
					tagMachine1.shuffle(10, onComplete);
					
					setTimeout(function(){
						tagMachine2.shuffle(10, onComplete);
					}, 300);
					
					setTimeout(function(){
						tagMachine3.shuffle(10, onComplete);
					}, 500);
					
				})
			});
		</script>
<script>
			$( window ).resize(function() {
				var tagMachine1 = $("#tagMachine1").slotMachine({
					active	: 0,
					delay	: 1
				});
				
				var tagMachine2 = $("#tagMachine2").slotMachine({
					active	: 1,
					delay	: 1
				});
				
				var tagMachine3 = $("#tagMachine3").slotMachine({
					active	: 2,
					delay	: 1
				});
					setTimeout(function(){
					tagMachine1.shuffle(3, onComplete);
					}, 20);					
					setTimeout(function(){
						tagMachine2.shuffle(3, onComplete);
					}, 20);
					
					setTimeout(function(){
						tagMachine3.shuffle(3, onComplete);
					}, 20);
				
				function onComplete(active){

			switch(this.element[0].id){

				case 'tagMachine1':
					var tagIndex1 = this.active ;
					tagName =  $("#tagMachine1 .slotMachineContainer div:eq( "+ tagIndex1 +")").attr( "title");
					tagId = $("#tagMachine1 .slotMachineContainer div:eq( "+ tagIndex1 +")").attr( "name");
					$("#tagMachine1Result").text(tagName);
					$("#tagMachine1Result").attr( "name", tagId );
					break;
				case 'tagMachine2':
					var tagIndex2 = this.active;
					tagName = $("#tagMachine2 .slotMachineContainer div:eq( "+ tagIndex2 +")").attr( "title");
					tagId = $("#tagMachine2 .slotMachineContainer div:eq( "+ tagIndex2 +")").attr( "name");
					$("#tagMachine2Result").text(tagName);
					$("#tagMachine2Result").attr( "name", tagId );

					break;
				case 'tagMachine3':
					var tagIndex3 = this.active;
					tagName =  $("#tagMachine3 .slotMachineContainer div:eq( "+ tagIndex3 +")").attr( "title");
					tagId = $("#tagMachine3 .slotMachineContainer div:eq( "+ tagIndex3 +")").attr( "name");
					$("#tagMachine3Result").text(tagName);
					$("#tagMachine3Result").attr( "name", tagId );

					break;
			}
				}
				
				$("#tagMachineButtonInit").click(function(){
					$( "#tag-slider" ).show( "slide" , { direction: "down" }, 500 );
					setTimeout(function(){
					tagMachine1.shuffle(1, onComplete);
					}, 1);					
					setTimeout(function(){
						tagMachine2.shuffle(1, onComplete);
					}, 1);
					
					setTimeout(function(){
						tagMachine3.shuffle(1, onComplete);
					}, 1);

				})
				$("#tagMachineButton1").click(function(){
					
					setTimeout(function(){
						tagMachine1.shuffle(1, onComplete);
					}, 1);
					setTimeout(function(){
						tagMachine2.shuffle(1, onComplete);
					}, 1);
					
					setTimeout(function(){
						tagMachine3.shuffle(1, onComplete);
					}, 1);
					
				})
			});
		</script>
  <div id="mood-slider" class="slider">
    <div class="row">
      <div  class=" col-sm-8 col-sm-offset-2 col-xs-12 slider-up "><img src="images/icons/down.png" id="moodUp"  height="40" width="60"/></div>
    </div>
    <div class="row interactive">
      <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12 machineContainer">
        <div class="row">
          <?php

$moods = file_get_contents('https://www.poetryinvoice.com/roulette/cached/cached_moods.php?lang='.$lang.'&c='.$c);
echo $moods;
?>
        </div>
        <div class="row nameRow">
          <div id="moodMachine1Result"  number="1" class="col-xs-4 machineResult" type="mood">&nbsp;</div>
          <div id="moodMachine2Result"  number="2" class="col-xs-4 machineResult" type="mood">&nbsp;</div>
          <div id="moodMachine3Result"  number="3" class="col-xs-4 machineResult" type="mood">&nbsp;</div>
        </div>
      </div>
    </div>
    <div class="row buttonRow"  id="spinFooter">
      <button  class="slotMachineButton moodMachineButton1   " onClick="ga('send', 'pageview', '/virtual/roulette-en/mood/spin-again');" id="moodMachineButton1"><?php echo $vars['Spin'][$lang]; ?></button>
    </div>
  </div>
<script>
			$(document).ready(function() {
				var moodMachine1 = $("#moodMachine1").slotMachine({
					active	: 0,
					delay	: 100
				});
				
				var moodMachine2 = $("#moodMachine2").slotMachine({
					active	: 1,
					delay	: 50
				});
				
				var moodMachine3 = $("#moodMachine3").slotMachine({
					active	: 2,
					delay	: 50
				});
				
				function onComplete(active){

			switch(this.element[0].id){

				case 'moodMachine1':
					var moodIndex1 = this.active ;
					moodName =  $("#moodMachine1 .slotMachineContainer div:eq( "+ moodIndex1 +")").attr( "title");
					moodId = $("#moodMachine1 .slotMachineContainer div:eq( "+ moodIndex1 +")").attr( "name");
					$("#moodMachine1Result").text(moodName);
					$("#moodMachine1Result").attr( "name", moodId );
					break;
				case 'moodMachine2':
					var moodIndex2 = this.active;
					moodName = $("#moodMachine2 .slotMachineContainer div:eq( "+ moodIndex2 +")").attr( "title");
					moodId = $("#moodMachine2 .slotMachineContainer div:eq( "+ moodIndex2 +")").attr( "name");
					$("#moodMachine2Result").text(moodName);
					$("#moodMachine2Result").attr( "name", moodId );

					break;
				case 'moodMachine3':
					var moodIndex3 = this.active;
					moodName =  $("#moodMachine3 .slotMachineContainer div:eq( "+ moodIndex3 +")").attr( "title");
					moodId = $("#moodMachine3 .slotMachineContainer div:eq( "+ moodIndex3 +")").attr( "name");
					$("#moodMachine3Result").text(moodName);
					$("#moodMachine3Result").attr( "name", moodId );

					break;
			}
				}
				
				$("#moodMachineButtonInit").click(function(){
					$( "#mood-slider" ).show( "slide" , { direction: "down" }, 500 );
					setTimeout(function(){
					moodMachine1.shuffle(3, onComplete);
					}, 200);					
					setTimeout(function(){
						moodMachine2.shuffle(3, onComplete);
					}, 200);
					
					setTimeout(function(){
						moodMachine3.shuffle(3, onComplete);
					}, 500);

				})
				$("#moodMachineButton1").click(function(){
					
					moodMachine1.shuffle(3, onComplete);
					
					setTimeout(function(){
						moodMachine2.shuffle(3, onComplete);
					}, 300);
					
					setTimeout(function(){
						moodMachine3.shuffle(3, onComplete);
					}, 500);
					
				})
			});
		</script>
 <script>
			$( window ).resize(function() {
				var moodMachine1 = $("#moodMachine1").slotMachine({
					active	: 0,
					delay	: 1
				});
				
				var moodMachine2 = $("#moodMachine2").slotMachine({
					active	: 1,
					delay	: 1
				});
				
				var moodMachine3 = $("#moodMachine3").slotMachine({
					active	: 2,
					delay	: 1
				});
					setTimeout(function(){
					moodMachine1.shuffle(1, onComplete);
					}, 1);					
					setTimeout(function(){
						moodMachine2.shuffle(1, onComplete);
					}, 1);
					
					setTimeout(function(){
						moodMachine3.shuffle(1, onComplete);
					}, 1);
				
				function onComplete(active){

			switch(this.element[0].id){

				case 'moodMachine1':
					var moodIndex1 = this.active ;
					moodName =  $("#moodMachine1 .slotMachineContainer div:eq( "+ moodIndex1 +")").attr( "title");
					moodId = $("#moodMachine1 .slotMachineContainer div:eq( "+ moodIndex1 +")").attr( "name");
					$("#moodMachine1Result").text(moodName);
					$("#moodMachine1Result").attr( "name", moodId );
					break;
				case 'moodMachine2':
					var moodIndex2 = this.active;
					moodName = $("#moodMachine2 .slotMachineContainer div:eq( "+ moodIndex2 +")").attr( "title");
					moodId = $("#moodMachine2 .slotMachineContainer div:eq( "+ moodIndex2 +")").attr( "name");
					$("#moodMachine2Result").text(moodName);
					$("#moodMachine2Result").attr( "name", moodId );

					break;
				case 'moodMachine3':
					var moodIndex3 = this.active;
					moodName =  $("#moodMachine3 .slotMachineContainer div:eq( "+ moodIndex3 +")").attr( "title");
					moodId = $("#moodMachine3 .slotMachineContainer div:eq( "+ moodIndex3 +")").attr( "name");
					$("#moodMachine3Result").text(moodName);
					$("#moodMachine3Result").attr( "name", moodId );

					break;
			}
				}
				
				$("#moodMachineButtonInit").click(function(){
					$( "#mood-slider" ).show( "slide" , { direction: "down" }, 500 );
					setTimeout(function(){
					moodMachine1.shuffle(1, onComplete);
					}, 1);					
					setTimeout(function(){
						moodMachine2.shuffle(1, onComplete);
					}, 1);
					
					setTimeout(function(){
						moodMachine3.shuffle(1, onComplete);
					}, 1);

				})
				$("#moodMachineButton1").click(function(){
					
					setTimeout(function(){
						moodMachine1.shuffle(1, onComplete);
					}, 1);
					setTimeout(function(){
						moodMachine2.shuffle(1, onComplete);
					}, 1);
					
					setTimeout(function(){
						moodMachine3.shuffle(1, onComplete);
					}, 1);
					
				})
			});
		</script>
  <div id="poet-slider" class="slider">
    <div class="row">
      <div  class=" col-sm-8 col-sm-offset-2 col-xs-12 slider-up "><img src="images/icons/down.png" id="poetUp"  height="40" width="60"/></div>
    </div>
    <div class="row interactive">
      <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12 machineContainer">
        <div class="row">
          <?php

$poets = file_get_contents('https://www.poetryinvoice.com/roulette/cached/cached_poets.php?lang='.$lang.'&c='.$c);
echo $poets;
?>
        </div>
        <div class="row nameRow">
          <div id="poetMachine1Result"  number="1" class="col-xs-4 machineResult" type="poet">&nbsp;</div>
          <div id="poetMachine2Result"  number="2" class="col-xs-4 machineResult" type="poet">&nbsp;</div>
          <div id="poetMachine3Result"  number="3" class="col-xs-4 machineResult" type="poet">&nbsp;</div>
        </div>
      </div>
    </div>
    <div class="row buttonRow"  id="spinFooter">
      <button  class="slotMachineButton poetMachineButton   " onClick="ga('send', 'pageview', '/virtual/roulette-en/poet/spin-again');" id="poetMachineButton"><?php echo $vars['Spin'][$lang]; ?></button>
    </div>
  </div>
<script> 			$(document).ready(function(){


		var poetMachine1 = $("#poetMachine1").slotMachine({
			active	: 0,
			delay	: 50
		});
				
		var poetMachine2 = $("#poetMachine2").slotMachine({
			active	: 1,
			delay	: 50
		});
				
		var poetMachine3 = $("#poetMachine3").slotMachine({
			active	: 2,
			delay	: 50
		});

		function onComplete(active){

			switch(this.element[0].id){

				case 'poetMachine1':
					var poetIndex1 = this.active ;
					poetName = $("#poetMachine1 .slotMachineContainer div:eq( "+ poetIndex1 +")").attr( "title");
					poetId = $("#poetMachine1 .slotMachineContainer div:eq( "+ poetIndex1 +")").attr( "name");
					$("#poetMachine1Result").text(poetName);
					$("#poetMachine1Result").attr( "name", poetId );
					break;
				case 'poetMachine2':
					var poetIndex2 = this.active ;
					poetName = $("#poetMachine2 .slotMachineContainer div:eq( "+ poetIndex2 +")").attr( "title");
					poetId = $("#poetMachine2 .slotMachineContainer div:eq( "+ poetIndex2 +")").attr( "name");
					$("#poetMachine2Result").text(poetName);
					$("#poetMachine2Result").attr( "name", poetId );
					break;
				case 'poetMachine3':
					var poetIndex3 = this.active ;
					poetName = $("#poetMachine3 .slotMachineContainer div:eq( "+ poetIndex3 +")").attr( "title");
					poetId = $("#poetMachine3 .slotMachineContainer div:eq( "+ poetIndex3 +")").attr( "name");
					$("#poetMachine3Result").text(poetName);
					$("#poetMachine3Result").attr( "name", poetId );
					break;
			}
		}
				$("#poetMachineButtonInit").click(function(){
					$( "#poet-slider" ).show( "slide" , { direction: "down" }, 500 );
					setTimeout(function(){
					poetMachine1.shuffle(10, onComplete);
					}, 200);					
					setTimeout(function(){
						poetMachine2.shuffle(10, onComplete);
					}, 200);
					
					setTimeout(function(){
						poetMachine3.shuffle(10, onComplete);
					}, 500);

				})
					
				$("#poetMachineButton").click(function(){
					
					setTimeout(function(){
						poetMachine1.shuffle(10, onComplete);
					}, 200);	
					setTimeout(function(){
						poetMachine2.shuffle(10, onComplete);
					}, 300);
					
					setTimeout(function(){
						poetMachine3.shuffle(10, onComplete);
					}, 500);
					
				})
 
				

				


			
				});
		</script>
<script> 			$( window ).resize(function(){


		var poetMachine1 = $("#poetMachine1").slotMachine({
			active	: 0,
			delay	: 1
		});
				
		var poetMachine2 = $("#poetMachine2").slotMachine({
			active	: 1,
			delay	: 1
		});
				
		var poetMachine3 = $("#poetMachine3").slotMachine({
			active	: 2,
			delay	: 1
		});
					setTimeout(function(){
					poetMachine1.shuffle(3, onComplete);
					}, 20);					
					setTimeout(function(){
						poetMachine2.shuffle(3, onComplete);
					}, 20);
					
					setTimeout(function(){
						poetMachine3.shuffle(3, onComplete);
					}, 50);

		function onComplete(active){

			switch(this.element[0].id){

				case 'poetMachine1':
					var poetIndex1 = this.active ;
					poetName = $("#poetMachine1 .slotMachineContainer div:eq( "+ poetIndex1 +")").attr( "title");
					poetId = $("#poetMachine1 .slotMachineContainer div:eq( "+ poetIndex1 +")").attr( "name");
					$("#poetMachine1Result").text(poetName);
					$("#poetMachine1Result").attr( "name", poetId );
					break;
				case 'poetMachine2':
					var poetIndex2 = this.active ;
					poetName = $("#poetMachine2 .slotMachineContainer div:eq( "+ poetIndex2 +")").attr( "title");
					poetId = $("#poetMachine2 .slotMachineContainer div:eq( "+ poetIndex2 +")").attr( "name");
					$("#poetMachine2Result").text(poetName);
					$("#poetMachine2Result").attr( "name", poetId );
					break;
				case 'poetMachine3':
					var poetIndex3 = this.active ;
					poetName = $("#poetMachine3 .slotMachineContainer div:eq( "+ poetIndex3 +")").attr( "title");
					poetId = $("#poetMachine3 .slotMachineContainer div:eq( "+ poetIndex3 +")").attr( "name");
					$("#poetMachine3Result").text(poetName);
					$("#poetMachine3Result").attr( "name", poetId );
					break;
			}
		}
				$("#poetMachineButtonInit").click(function(){
					$( "#poet-slider" ).show( "slide" , { direction: "down" }, 500 );
					setTimeout(function(){
					poetMachine1.shuffle(1, onComplete);
					}, 1);					
					setTimeout(function(){
						poetMachine2.shuffle(1, onComplete);
					}, 1);
					
					setTimeout(function(){
						poetMachine3.shuffle(1, onComplete);
					}, 1);

				})
					
				$("#poetMachineButton").click(function(){
					

					setTimeout(function(){
					poetMachine1.shuffle(1, onComplete);
					}, 1);					

					setTimeout(function(){
						poetMachine2.shuffle(1, onComplete);
					}, 30);
					
					setTimeout(function(){
						poetMachine3.shuffle(1, onComplete);
					}, 1);
					
				})
 
				

				


			
				});
		</script>
</div>
</div>
</div>

<script>
$( document ).ready(function() {

  $( ".slider" ).hide();
});

$( "#tagUp" ).click(function() {
  $( "#tag-slider" ).hide( "slide" , { direction: "down" }, 500 );
});
$( "#moodUp" ).click(function() {
  $( "#mood-slider" ).hide( "slide" , { direction: "down" }, 500 );
});
$( "#poetUp" ).click(function() {
  $( "#poet-slider" ).hide( "slide" , { direction: "down" }, 500 );
});
$( ".slotMachine, .machineResult" ).click(function() {
  $( "#toggle2" ).show( "slide" , { direction: "left" }, 500 );
  var tagId;
  var c = "<?php echo $c; ?>";													 
  tagId = "../poem-roulette/" + $(this).attr('type') + "s/" + c + "/";
  tagId += $( "#" +  $(this).attr('type')  + "Machine" + $(this).attr("number") + "Result" ).attr("name");
  var gaPage;
  gaPage = tagId.replace('../','virtual/');
  ga('send', 'pageview', gaPage);
  $.getJSON( tagId  , function(data) {
		var test = data[0].content;	
       $("#poem").hide().html(test).fadeIn('slow');
  	$( "#loading" ).hide();
	   var poemPath = $( "#poemPath" ).html();
	$("#visitPoem").attr( "href", poemPath );
  });
	$("#poemFooter").fadeIn('slow');


});




$( "#back2" ).click(function() {
	$("#poemFooter").fadeOut('fast');
	$("#poem").fadeOut('fast');
 	$( "#toggle2" ).hide( "slide" , { direction: "left" }, 500 );
});


  // Check if the device supports touch events
  if('ontouchstart' in document.documentElement) {
    // Loop through each stylesheet
    for(var sheetI = document.styleSheets.length - 1; sheetI >= 0; sheetI--) {
      var sheet = document.styleSheets[sheetI];
      // Verify if cssRules exists in sheet
      if(sheet.cssRules) {
        // Loop through each rule in sheet
        for(var ruleI = sheet.cssRules.length - 1; ruleI >= 0; ruleI--) {
          var rule = sheet.cssRules[ruleI];
          // Verify rule has selector text
          if(rule.selectorText) {
            // Replace hover psuedo-class with active psuedo-class
            rule.selectorText = rule.selectorText.replace(":hover", ":active");
          }
        }
      }
    }
  }

</script>

</div>



</body>
</html>


<?php
  if ($_SERVER['HTTP_HOST'] == 'lesvoixdelapoesie.ca') {
    $lang = 'fr';
  } else {
    $lang = 'en';
  }
  $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
  $base_url .= "://{$_SERVER['HTTP_HOST']}";
?>
<?php
  include_once __DIR__ . '/vars.php';
// $header = file_get_contents('https://www.poetryinvoice.com/roulette/'. $lang .'/header.php');
// echo $header;
  if (empty($c)) {
    $c = $_POST['c'];
  }
// $tags = file_get_contents('https://www.poetryinvoice.com/roulette/cached/cached_tags.php?lang='.$lang.'&c='.$c);
// echo $tags;
?>
<div class="container-fluid">
  <div id="topBar" class="row">
    <div class="dropdown">
     <div class="admin-menu-icon admin-menu-toolbar-category expandable"><a href="../"><span>Home</span></a></div>
      <button id="favouritesButton" class="btn dropdown-toggle" type="button">
      </span>
      </button>
      <div id="favouritesDropdown" class="dropdown-menu">
        <h4><?php echo $vars['favourites'][$lang]; ?> </h4><hr>
        <ul id="favouritePoems"></ul>
      </div>
      <div class="language-switcher-locale-url">
    <span><?php echo $vars['language'][$lang]; ?></span>

  </div>
    </div>

  <div class="row logo">
    <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12">

      <div id="logo-title"><a href="../"> <?php echo '<img src="/roulette/images/icons/roulette-logo_' . $lang  . '_' . $c . '.png"'; ?> class="img-responsive" /></a>  </div>
    </div>
  </div>
</div>
*/?>
<div id="main" class="container" >
  <div class="row interactive">
    <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12">
      <div id="choices"  >
        <div clas="row">
          <div  class="col-xs-4 col-sm-4 col-md-4 choice1" id="poetMachineButtonInit" onClick="ga('send', 'pageview', '/virtual/roulette-en/poet/initial');" > <img src="/roulette/images/icons/roulette-theme-poet.svg" class="img-responsive" />
            <h2 class="choice-title"><?php echo $vars['Poets'][$lang]; ?></h2>
          </div>
          <div  class="col-xs-4  col-sm-4 col-md-4 choice1" id="moodMachineButtonInit" onClick="ga('send', 'pageview', '/virtual/roulette-en/mood/initial');"> <img src="/roulette/images/icons/roulette-theme-mood.svg" class="img-responsive" />
            <h2 class="choice-title"><?php echo $vars['Moods'][$lang]; ?></h2>
          </div>
          <div  class="col-xs-4  col-sm-4 col-md-4 choice1" id="tagMachineButtonInit" " onClick="ga('send', 'pageview', '/virtual/roulette-en/tag/initial');"> <img src="/roulette/images/icons/roulette-theme-icon2.svg" class="img-responsive slotMachineButton1" />
            <h2 class="choice-title"><?php echo $vars['Tags'][$lang]; ?></h2>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div id="toggle2">&nbsp;
    <div class="row interactive">
      <div class=" col-sm-6 col-sm-offset-2 col-xs-10 " id="loading"><img src="/roulette/images/icons/loading.gif" /></div>
    <div class=" col-sm-6 col-sm-offset-2 col-xs-10 " id="poem"></div>
    <div class="sticky">
    <div id="back2" class="col-xs-2">
      <img src="/roulette/images/icons/right.png" height="60" width="40" /></div>
    </div>
    </div>
  </div>
  <div id="tag-slider" class="slider">
    <div class="row">
      <div  class=" col-sm-8 col-sm-offset-2 col-xs-12 slider-up "><img src="/roulette/images/icons/down.png" id="tagUp"  height="40" width="60"/></div>
    </div>
    <div class="row interactive">
      <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12 machineContainer">
        <div class="row">
          <?php
            $tags = file_get_contents("{$base_url}/roulette/cached/cached_tags.php?lang={$lang}&c={$c}");
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
          active  : 0,
          delay : 100
        });

        var tagMachine2 = $("#tagMachine2").slotMachine({
          active  : 1,
          delay : 50
        });

        var tagMachine3 = $("#tagMachine3").slotMachine({
          active  : 2,
          delay : 50
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
          active  : 0,
          delay : 1
        });

        var tagMachine2 = $("#tagMachine2").slotMachine({
          active  : 1,
          delay : 1
        });

        var tagMachine3 = $("#tagMachine3").slotMachine({
          active  : 2,
          delay : 1
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
      <div  class=" col-sm-8 col-sm-offset-2 col-xs-12 slider-up "><img src="/roulette/images/icons/down.png" id="moodUp"  height="40" width="60"/></div>
    </div>
    <div class="row interactive">
      <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12 machineContainer">
        <div class="row">
          <?php
            $moods = file_get_contents("{$base_url}/roulette/cached/cached_moods.php?lang={$lang}&c={$c}");
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
          active  : 0,
          delay : 100
        });

        var moodMachine2 = $("#moodMachine2").slotMachine({
          active  : 1,
          delay : 50
        });

        var moodMachine3 = $("#moodMachine3").slotMachine({
          active  : 2,
          delay : 50
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

          active  : 0,
          delay : 1
        });

        var moodMachine2 = $("#moodMachine2").slotMachine({
          active  : 1,
          delay : 1
        });

        var moodMachine3 = $("#moodMachine3").slotMachine({
          active  : 2,
          delay : 1
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
      <div  class=" col-sm-8 col-sm-offset-2 col-xs-12 slider-up "><img src="/roulette/images/icons/down.png" id="poetUp"  height="40" width="60"/></div>
    </div>
    <div class="row interactive">
      <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-12 machineContainer">
        <div class="row">
          <?php
            $poets = file_get_contents("{$base_url}/roulette/cached/cached_poets.php?lang={$lang}&c={$c}");
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
<script>      $(document).ready(function(){


    var poetMachine1 = $("#poetMachine1").slotMachine({
      active  : 0,
      delay : 50
    });

    var poetMachine2 = $("#poetMachine2").slotMachine({
      active  : 1,
      delay : 50
    });

    var poetMachine3 = $("#poetMachine3").slotMachine({
      active  : 2,
      delay : 50
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
<script>      $( window ).resize(function(){


    var poetMachine1 = $("#poetMachine1").slotMachine({
      active  : 0,
      delay : 1
    });

    var poetMachine2 = $("#poetMachine2").slotMachine({
      active  : 1,
      delay : 1
    });

    var poetMachine3 = $("#poetMachine3").slotMachine({
      active  : 2,
      delay : 1
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
// key for getting favPoems in session storage
const favPoemKey = "favPoems";
// Remove poem from favourites in session storage
removeFavPoem = (poemId) => {
  if (favPoemKey in sessionStorage) {
    let favPoems = JSON.parse(sessionStorage.getItem(favPoemKey));
    favPoems = favPoems.filter((favPoem) => {
    return favPoem.poemId !== poemId
  });
  sessionStorage.setItem(favPoemKey, JSON.stringify(favPoems));
  }
}
// Add poem to favourites in session storage
storeFavPoem = (poemId) => {
  let title = $("#" + poemId + " h1:first").text();
  let poet = $("#" + poemId + " h4:first").text()
  let poemPath = $("#visitPoem" + poemId).attr( "href");
  let favPoems = (favPoemKey in sessionStorage) ? JSON.parse(sessionStorage.getItem(favPoemKey)) : [];
    var flag = 0;
    for (poem of favPoems){
      if (poem.poemId == poemId) {
      flag = 1;
      }
    }
    if (flag == 0) {
      favPoems.push({
        title: title,
        poet: poet,
        poemPath: poemPath,
        poemId: poemId
      });
      sessionStorage.setItem(favPoemKey, JSON.stringify(favPoems));
    }

}
// Highlight My Favourites Heart
highlightFavourite = (poemId) =>  {
  var heart="outline";
    var poemNumber = "";
  let favPoems = (favPoemKey in sessionStorage) ? JSON.parse(sessionStorage.getItem(favPoemKey)) : [];
  if (favPoems != 0){
    heart="full";
    poemNumber = "<span class='fav-number'>" + favPoems.length + "</span>";
  }
  $("#favouritesButton").hide().html("<img src='images/icons/heart-" + heart + ".png' style= 'height: 20px; filter: grayscale(100%) brightness(2000%);' /> " + poemNumber).fadeIn('slow');
}

$( document ).ready(function() {
  // Push history and hide slide if browser back button is clicked
    $(window).on('popstate', function(event) {
  hideSlide();
    });
    $( ".slider" ).hide();
  highlightFavourite();
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
  if (history && history.pushState) {
  history.pushState('forward', null, './#poem');
  }
  $( "#toggle2" ).show( "slide" , { direction: "left" }, 500 );
  var tagId;
  var c = "<?php echo $c; ?>";
  tagId = "../poem-roulette/" + $(this).attr('type') + "s/" + c + "/";
  tagId += $( "#" +  $(this).attr('type')  + "Machine" + $(this).attr("number") + "Result" ).attr("name");
 // var gaPage;
 // gaPage = tagId.replace('../','virtual/');
 // ga('send', 'pageview', gaPage);

appendLikeButton = (poemId) => {
  $(".verse").last().wrap("<div class='flex-container'/>");
  var heart = "outline";
    if (favPoemKey in sessionStorage) {
      let favPoems = JSON.parse(sessionStorage.getItem(favPoemKey));
      favPoems = favPoems.filter((favPoem) => {
        if  (favPoem.poemId == poemId) {
        heart = "full";
        }
      });
    }

  $(".verse").last().after("<img src='images/icons/heart-" + heart + ".png' class='heart' data-index='" + poemId + "'/></div>");
  $(".heart").last().wrap("<div class='heart-container'/>");


}

appendPoemButton = (poemId, poemPath) => {
  $("#poem").append("<br><div  class='poemButton horizontal-center' ><a href='' target='_blank' id='visitPoem" + poemId + "' onClick='ga('send', 'pageview',  $(this).attr('href').replace('https://www.poetryinvoice.com','/virtual/roulette-en'));'><?php echo $vars['More'][$lang]; ?></a></div></br></br>");
  $("#visitPoem" + poemId).attr( "href", ""+poemPath+"" );

}
  $.ajax({
    dataType: "json",
    url: tagId,
    timeout: 5000,
    success: function(poems) {
      poemIndex = 0;
      var poem = poems[poemIndex].content;
      var poemId = $(poem).filter('h1').attr("data-id");
      var poemPath = $(poem).filter('#poemPath').html();
      $("#poem").hide().html("<div id='"+ poemId+"'>" + poem).fadeIn('slow');
      $( "#loading" ).hide();
      appendLikeButton(poemId);
      appendPoemButton(poemId, poemPath);
      $("#poem").append("</div>");
    }
  });
});

// Method for hiding slide
hideSlide = function() {
  $("#poem").fadeOut('fast');
  $( "#toggle2" ).hide( "slide" , { direction: "left" }, 500 );
}

$( "#back2" ).click(function() {
  if (history && history.back) {
    history.back();
  }
  hideSlide();
});


$(window).on("scroll", function() {
  var scrollHeight = $(document).height();
  var scrollPosition = $(window).height() + $(window).scrollTop();
  if ((scrollHeight - scrollPosition) / scrollHeight === 0 && poems) {
    poemIndex++;
    if (poemIndex <= poems.length){
      if (poemIndex < poems.length) {
        $("#poem").append("<hr class='thick-line'>");
      }

      let poem = poems[poemIndex].content;
        var poemId = $(poem).filter('h1').attr("data-id");
        var poemPath = $(poem).filter('#poemPath').html();
      $("#poem").append("<div id='"+ poemId +"'>" + poem);
      appendLikeButton(poemId);
      appendPoemButton(poemId, poemPath);
      $("#poem").append("</div>");
    }
  }
});

$(document).on('click', '.heart', (event) => {
  const favPoemKey = "favPoems";
  let target = event.currentTarget;
  let poemId = $(target).attr("data-index");
  if ($(target).attr("src") === 'images/icons/heart-outline.png'){
    $(target).attr("src","/roulette/images/icons/heart-full.png");
    storeFavPoem(poemId);
  } else {
    $(target).attr("src","/roulette/images/icons/heart-outline.png");
    removeFavPoem(poemId);
  }
  highlightFavourite(poemId);
});
$(document).click(function(event) {
  var $target = $(event.target);
  if(!$target.closest('.dropdown').length &&
  $('#favouritesDropdown').is(":visible")) {
    $('#favouritesDropdown').hide();
  }
});
$(document).on('click', '#favouritesButton', () => {
  const favPoemKey = "favPoems";
            var lang = "en";

  if (window.location.href.indexOf("lesvoixdelapoesie") > -1) {
              lang = "fr";
  }
  if ($("#favouritesDropdown").is(":hidden")){
    let favPoems = (favPoemKey in sessionStorage) ? JSON.parse(sessionStorage.getItem(favPoemKey)) : [];
              if (favPoems == 0){
                if (lang == "fr"){
                var emptyMessage = ("<li>Trouvez un poème que vous aimez.</li><li>Ensuite, cliquez sur le cœur à côté du poème pour l&rsquo;ajouter à vos favoris.</li><li>Pour voir vos favoris, cliquez sur le cœur dans la barre de navigation.</li>");

              }
              else {
                var emptyMessage = ("<li>Find a poem you like.</li><li>Click its heart to add it to My Favourites.</li><li>To view My Favourites, click this heart.</li>");
              }
              jQuery("#favouritePoems").html(emptyMessage);

            } else {
      $("#favouritePoems").empty();
      for (poem of favPoems){
        $("#favouritePoems").append("<li>" +
          "<button type='button' class='poem-delete' data-id= '" + poem.poemId + "'><span>&times;</span></button>" +
          "<a href='" + poem.poemPath + "' target='_blank'>" + poem.title + " - " + poem.poet + "</a>" +
          "</li>");
      }
    }
      $("#favouritesDropdown").slideDown("fast");

  } else {
    $("#favouritesDropdown").slideUp("fast");
  }
});

$(document).on('click', '.poem-delete', (event) => {
  let target = event.currentTarget;
  let poemId = $(target).attr("data-id");
  removeFavPoem(poemId);
  $(target).parent().remove();
  highlightFavourite();
        $("#favouritesDropdown").show();

});


</script>
</div>
</body>
</html>


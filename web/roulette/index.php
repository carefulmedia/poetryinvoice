
<?php
if ($_SERVER['HTTP_HOST'] == 'lesvoixdelapoesie.ca'){
	$lang = 'fr';
	}
else {
	$lang = 'en';
}
include '//app/web/roulette/vars.php';

$c = $_GET['c'] ?? NULL;


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Cache-control" content="no-cache">
<meta http-equiv="Expires" content="-1">
<link rel="shortcut icon" href="https://www.poetryinvoice.com/sites/all/themes/piv_lvp/images/piv-logo.png" type="image/png" />
<title><?php echo $vars['Page_title'][$lang]; ?></title>
<!-- Latest compiled and minified CSS -->
<!-- Optional theme -->

<!-- Google Analytics -->
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){	
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
ga('create', '<?php echo $vars['Ga'][$lang]; ?>', 'auto');
ga('send', 'pageview');
</script>
<!-- End Google Analytics -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">
<link rel="stylesheet" type="text/css" href= "/roulette/css/roulette.css?rnd=23333878382428" />
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" type="text/css" media="screen" />
<script type="text/javascript" src="//code.jquery.com/jquery-2.1.1.min.js"></script>
<script type="text/javascript" src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
<script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
	<script type="text/javascript" src="/roulette/js/jquery.slotmachine-min.js"></script>

</head>
<body>
            <div id="element_to_pop_up">
				   <div class="col-xs-6 col-sm-6 col-md-6 choice1" id="junior" onclick="ga('send', 'pageview', '/virtual/roulette/junior');"> <img src="/sites/default/files/theme-youngpoets.png" class="img-responsive">
            <?php echo $vars['Junior'][$lang]; ?>
          </div>
					   <div class="col-xs-6 col-sm-6 col-md-6 choice1" id="senior" onclick="ga('send', 'pageview', '/virtual/roulette-en/poet/initial');"> <img src="/sites/default/files/theme-schooldaze.png" class="img-responsive">
           <?php echo $vars['Senior'][$lang]; ?>
          </div>

            </div>
 <div id='d1' ></div>
<?php
if(empty($c)) {
?>

<?php
}
else {
include '/roulette/load.php';

}
?>

<script>
$(document).ready(function() {
$("#junior").click(function(){

var c = "j";

$('#element_to_pop_up').bPopup().close();
    $.ajax({
      url: "/roulette/load.php",
      data: "c="+c,
      type: "post",
      success: function(data){
            $('#d1').html(data);
      }
    });
})
	$("#senior").click(function(){

var c = "s";
$('#element_to_pop_up').bPopup().close();
    $.ajax({
      url: "/roulette/load.php",
      data: "c="+c,
      type: "post",
      success: function(data){
            $('#d1').html(data);
      }
    });
})
})

</script>
	<script type="text/javascript" src="/roulette/js/jquery.bpopup.min.js"></script>


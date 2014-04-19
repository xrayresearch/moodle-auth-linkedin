<?php

/**
 * Date: Aug 24, 2013
 * programmer: Shani Mahadeva <satyashani@gmail.com>
 * Description:
 * */
global $CFG;
$auth = new auth_plugin_linkedin();
$url = $auth->getlinkedinLoginUrl();
$imgsignin = $CFG->wwwroot."/auth/linkedin/images/lighter.png";
?>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<a class='linkedin-login-button' id="linkedinlogin" href="<?php echo $url;?>" style="display: none;float:left;width:100%;">
	<img src="<?php echo $imgsignin;?>" />
</a>
<script>
	$(document).ready(function(){
		$("input#loginbtn").after($("a#linkedinlogin"));
		$("a#linkedinlogin").show();
	})
</script>
</body>
</html>
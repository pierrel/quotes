<?php

// the facebook client library
include_once '../client/facebook.php';

// some basic library functions
include_once 'lib.php';

// this defines some of your basic setup
include_once 'config.php';

$facebook = new Facebook($api_key, $secret);
$facebook->require_frame();
$user = $facebook->require_login();
$callbackurl = 'http://apps.facebook.com/nicoles-quotes/';
$source_type_array = array('movie', 'character', 'historical figure', 'book', 'author', 'comedian', 'actor', 'song', 'TV show');

if(isset($_GET['id'])) {
	echo render_guess_page();
	exit;
}
if (isset($_POST['guess'])) {
	echo render_guess_submit_page();
	exit;
}

if (isset($_POST['to'])) {
  $status = send_quote($_POST['quote'], $_POST['to'], $_POST['source'], $_POST['source_type'], $user);
}
//check to see if user is nicole, if not then exit
?>
<div style="padding: 10px;">
<h2>Hi <fb:name firstnameonly="true" uid="<?=$user?>" useyou="false"/>!</h2><br/>
<form method="post" action="<?php echo $callbackurl; ?>">
	<table border="0">
	<tr><td>&quot;<input name="quote" id="quote" type="text" size="50">&quot;</td></tr>
	<tr><td style="float:right;">-<input type="text" name="source" value="Source" id="source"></td></tr>
	</table>
	Hint:
	<select name="source_type">
		<option value="0">Movie</option>
		<option value="1">Character</option>
		<option value="2">Historical Figure</option>
		<option value="3">Book</option>
		<option value="4">Author</option>
		<option value="5">Comedian</option>
                <option value="6">Actor</option>
                <option value="7">Song</option>
                <option value="8">TV Show</option>
	</select>
<br>
Send to: <fb:friend-selector idname="to" /><br>
      <input value="Send" type="submit"/>
</form>
<hr/>
<div style="clear: both;"/>
</div>

<?php
if (isset($_POST['to'])) {
echo $status;
}
?>

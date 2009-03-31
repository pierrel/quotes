<?php

function get_db_conn() {
  $conn = mysql_connect($GLOBALS['db_ip'], $GLOBALS['db_user'], $GLOBALS['db_pass']);
  mysql_select_db($GLOBALS['db_name'], $conn);
  return $conn;
}

function render_guess_page() {
  global $facebook;
  global $source_type_array;
  
  $id = (int)$_REQUEST['id'];
  $conn = get_db_conn();
  $records = mysql_query('SELECT `quote`, `source_type`, `state`  FROM `quotes` WHERE `id`='.$id.';', $conn);
  $row = mysql_fetch_row($records);
  $quote = $row[0];
  $type_code = (int)$row[1];
  $state = $row[2];
  $type = $source_type_array[$type_code];
  if (!($state == 'pending')) {
    $form =  '<fb:error message="You have already tried answering this quote, no second chances!"</fb:error>';
  } else {
    $quote = htmlspecialchars($quote);
    $form = <<<EndHereDoc
      <h1>&quot;$quote&quot;</h1>
				<fb:editor action="./" id="guess_form">
				<fb:editor-text label="Name the $type" name="guess" />
				<input type="hidden" value="$id" name="id" />
				<fb:editor-buttonset>
				<fb:editor-button value="Submit" />
				<fb:editor-cancel />
				</fb:editor-buttonset>
				</fb:editor>
EndHereDoc;
  }
  return $form;
}

function render_guess_submit_page() {
  global $facebook;
  $conn = get_db_conn();
  $id = $_POST['id'];
  $records = mysql_query('SELECT `source`, `from_user`, `to_user`, `quote` FROM `quotes` WHERE `id`='.$id.';', $conn);
  $row = mysql_fetch_row($records);
  $source = $row[0];
  $from_uid = $row[1];
  $to_uid = $row[2];
  $quote = $row[3];
  $guess = $_POST['guess'];
  
  if (strtolower($source) == strtolower($guess)) {
    $result = '<fb:success><fb:message>Good Job!</fb:message><fb:name uid="'.$from_uid.'" firstnameonly="true" capitalize="true" /> will be so proud.</fb:success>';
    $facebook->api_client->notifications_send($from_uid, ' guessed the source of &quot;'.$quote.'&quot; correctly.');
    mysql_query('UPDATE `quotes` SET `state`=\'correct\' WHERE `id`='.$id.';', $conn);
  } else {
    $result = '<fb:explanation><fb:message>Wrong!</fb:message><fb:name uid="'.$from_uid.'" firstnameonly="true" capitalize="true" /> says it\'s '.$source.'.</fb:explanation>';
    $facebook->api_client->notifications_send($from_uid, ' guessed the source of &quot;'.$quote.'&quot; incorrectly as '.$guess.'.');
    mysql_query('UPDATE `quotes` SET `state`=\'wrong\' WHERE `id`='.$id.';', $conn);
  }
  update_profile_box($to_uid);
  return $result;
}

function guess_url($id) {
  global $callbackurl;

  return $callbackurl . '?id=' . $id;
} 

function send_quote($quote, $to, $source, $source_type, $from) {
  global $facebook;
  global $callbackurl;
  global $source_type_array;
  
  $success = 'Quote sent to <fb:name uid="' . $to . '" firstnameonly="true" capitalize="true"/> successfully.';
  $failure = 'An error occured. The quote may not have been sent. Sorry for the convenience.';
  
  $conn = get_db_conn();
  $result1 = mysql_query('INSERT INTO quotes SET `quote`=\''.addslashes($quote).'\', `source`=\''.$source.'\', `source_type`='.$source_type.', `from_user`='.$from.', `to_user`='.$to.';', $conn);
  $record = mysql_query('SELECT MAX(id) FROM quotes WHERE `quote`=\''.addslashes($quote).'\' AND `source`=\''.$source.'\' AND `source_type`='.$source_type.' AND `from_user`='.$from.' AND `to_user`='.$to.';', $conn);
  $row = mysql_fetch_row($record);
  $id = $row[0];
  if (!$result1 or !$record) {
    return $failure . ' id = '. $id;
    exit;
  }
  $guess_url = guess_url($id);
  //$prints = get_prints($to);
  try {
    // Set Profile FBML
    update_profile_box($to);
    
    // Send notification
    $facebook->api_client->notifications_send($to, notification_link($guess_url, $source_type, $quote));
    
    // Publish feed story
    $feed_title = '<fb:userlink uid="'.$from.'" shownetwork="false"/> sent <fb:name uid="'.$to.'"/> a quote.';
    $feed_body = '&quot;' . htmlspecialchars($quote) . '&quot;';
    $facebook->api_client->feed_publishActionOfUser($feed_title, $feed_body);
  } catch (Exception $e) {
    error_log($e->getMessage());
    return $failure;
  }
  return $success;
}

function update_profile_box($user) {
  global $facebook;
  $facebook->api_client->profile_setFBML(render_profile_box($user), $user);
}

function notification_link($guess_url, $source_type, $quote) {
  global $source_type_array;

  return ' sent you a quote. &quot;'.htmlspecialchars($quote).'&quot; <a href="'.$guess_url.'">Name that '.$source_type_array[(int)$source_type].'</a>.';
}

 function profile_guess_link($from, $guess_url, $source_type, $quote) {
   global $source_type_array;
   
   return '&quot;'.htmlspecialchars($quote).'.&quot; <fb:userlink uid="'.$from.'" shownetwork="false"/> wants you to <a href="'.$guess_url.'">Name that '.$source_type_array[(int)$source_type].'</a>.';
 }

 function render_profile_box($user) {
   //return ''; //must be removed!
   //$fbml = render_app_users_profile($user);
   $fbml = render_current_user_profile($user);
   return $fbml;
 }

function render_app_users_profile($user) {
  global $facebook;
  $hint_selection = hint_selection();
  $fbml = <<<EndHereDoc
    <fb:visible-to-app-users>
    Send <fb:name firstnameonly="true" uid="$user" \> a quote <br />
    <form method="post" action="$callbackurl">
    <table border="0">
    <tr>
    <td>
    &quot;<input name="quote" id="quote" type="text" size="50">&quot;
  </td>
    </tr>
    <tr>
      <td style="float:right;">
      -<input type="text" name="source" value="Source" id="source">
      </td>
    </tr>
    </table>
    Hint:$hint_selection
    <br>
    <input type="hidden" name="to" id="to" value=$user>
    <input value="Send" type="submit"/>
    </form>
    </fb:visible-to-app-users> 
EndHereDoc;
  return $fbml;
}

function render_current_user_profile($user) {
  $conn = get_db_conn();
  $sql = 'SELECT id, from_user, quote, source_type FROM quotes WHERE `to_user`=\''.$user.'\' AND `state`=\'pending\';';
  $pending_rows = mysql_query($sql, $conn);
  if (mysql_num_rows($pending_rows) == 0) {
    return '<fb:visible-to-user uid="'.$user.'">You\'ve answered all the quotes!</fb:visible-to-user>';
  }
  $fbml = '<fb:visible-to-user uid="'.$user.'"><ul>';
  while ($row = mysql_fetch_assoc($pending_rows)) {
    $fbml = $fbml .'<li>'. profile_guess_link($row['from_user'], guess_url($row['id']), $row['source_type'], $row['quote']) . '</li>';
    $fbml = $fbml . '<br />';
  }
  return $fbml . '</ul></fb:visible-to-user>';
}

function hint_selection() {
  global $source_type_array;
  $index = 0;
  $html = '<select name="source_type">';

  foreach ($source_type_array as $number => $name) {
    $html .= '<option value="'.$number.'">'.$name.'</option>';
  }
  return $html . '</select>';
}
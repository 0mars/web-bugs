<?php /* vim: set noet ts=4 sw=4: : */
$id = (int)$id;
if (!$id) {
  header("Location: /");
  exit;
}

require_once 'prepend.inc';
require_once 'cvs-auth.inc';

if (isset($save) && isset($pw)) { # non-developers don't have $user set
  setcookie("MAGIC_COOKIE",base64_encode("$user:$pw"),time()+3600*24*12,'/');
}
if (isset($MAGIC_COOKIE) && !isset($user) && !isset($pw)) {
  list($user,$pw) = explode(":", base64_decode($MAGIC_COOKIE));
}

$mail_bugs_to = "php-dev@lists.php.net";

commonHeader("Bug Reporting");

@mysql_connect("localhost","nobody","")
	or die("Unable to connect to SQL server.");
@mysql_select_db("php3");

# fetch the original bug into $original
$res = mysql_query("SELECT * FROM bugdb WHERE id=$id");
if ($res) $original = mysql_fetch_array($res);
if (!$res || !$original) {
  echo "<h1 class=\"error\">No such bug #$id!</h1>";
  commonFooter();
  exit;
}

# handle any updates, displaying errors if there were any
$success = 0;

if ($modify == "user") {
	if (!$original[passwd] || $original[passwd] != stripslashes($pw)) {
		echo "<h2 class=\"error\">The password you supplied was incorrect.</h2>\n";
	}
	elseif (incoming_details_are_valid()) {
		/* update bug record */
		$success = @mysql_query("UPDATE bugdb SET status='$status', bug_type='$bug_type', php_version='$php_version', php_os='$php_os', ts2=NOW(), email='$email' WHERE id=$id");
		
		/* add comment */
		if ($success && !empty($ncomment)) {
			$success = @mysql_query("INSERT INTO bugdb_comments (bug, email, ts, comment) VALUES ($id,'$email',NOW(),'$ncomment')");
		}
	}
	$from = $email;
}
elseif ($modify == "developer") {
	if (!verify_password($user,stripslashes($pw))) {
		echo "<h2 class=\"error\">The username or password you supplied was incorrect.</h2>\n";
	}
	elseif (incoming_details_are_valid()) {
		$success = @mysql_query("UPDATE bugdb SET sdesc='$sdesc',status='$status', bug_type='$bug_type', assign='$assign', dev_id='$user', php_version='$php_version', php_os='$php_os', ts2=NOW() WHERE id=$id");
		if ($success && !empty($ncomment)) {
			$success = @mysql_query("INSERT INTO bugdb_comments (bug, email, ts, comment) VALUES ($id,'$user@php.net',NOW(),'$ncomment')");
		}
	}
	$from = "$user@php.net";
}

if ($modify && $success) {
	# mail out the updated bug
	$text = "ID: $id\n";
	if ($modify == "developer") {
		$text .= "Updated by: $user\n";
	}
	else {
		$text .= "User updated by: $email\n";
	}

	$text.= "Reported By: $original[email]\n";

	if (stripslashes($sdesc) != $original[sdesc])
		$text .= "Old Summary: $original[sdesc]\n";

	if ($status!=$original[status])
		$text .= "Old Status: $original[status]\n";
	$text.= "Status: $status\n";

	if ($bug_type != $original[bug_type])
		$text .= "Old Bug Type: $original[bug_type]\n";
	$text.= "Bug Type: $bug_type\n";

	if ($php_os != $original[php_os])
		$text .= "Old Operating System: $original[php_os]\n";
	$text.= "Operating System: $php_os\n";

	if ($php_version != $original[php_version])
		$text .= "Old PHP Version: $original[php_version]\n";
	$text.= "PHP Version: $php_version\n";

	if ($assign != $original[assign])
		$text .= "Old Assigned To: $original[assign]\n";
	if ($assign || $original[assign])
		$text.= "Assigned To: $assign\n";

	if ($ncomment)
		$text .= "New Comment:\n\n".stripslashes($ncomment);

	$text.= get_old_comments($id);

	$user_text = "\n\nATTENTION! Do NOT reply to this email!\n";
	$user_text.= "To reply, use the web interface found at http://bugs.php.net/?id=$id&edit=2\n\n\n$text";

	$dev_text .= $text . "\n\nEdit this bug report at http://bugs.php.net/?id=$id&edit=1\n";

	list($mailto,$mailfrom) = get_bugtype_mail($bug_type);

	/* send mail if status was changed or there is a comment */
	if ($status != $original[status] || $ncomment != "") {
		@mail($original[email], "Bug #$id Updated: ".stripslashes($sdesc), $user_text, "From: Bug Database <$mailfrom>\nX-PHP-Bug: $id\nIn-Reply-To: <bug-$id@bugs.php.net>");
		@mail($mailto, "Bug #$id Updated: ".stripslashes($sdesc), $dev_text, "From: $from\nX-PHP-Bug: $id\nIn-Reply-To: <bug-$id@bugs.php.net>");
	}

	# display a happy success message
	echo "<h2>Bug #$id updated successfully.</h2>\n";

	unset($ncomment);
}
elseif ($modify && !$success) {
	echo "<h2 class=\"error\">Something went wrong updating the database.</h2>";
}

/* DISPLAY BUG */
if ($edit) {
	echo "<form method=\"POST\" action=\"$PHP_SELF\">\n";
	echo "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
	echo "<input type=\"hidden\" name=\"edit\" value=\"$edit\">\n";
}
if ($edit==1)
	echo '<input type="hidden" name="modify" value="developer">',"\n";
if ($edit==2)
	echo '<input type="hidden" name="modify" value="user">',"\n";
?>
<br>
<h1>Bug id #<?php echo $id?></h1>
<table border="0">
<tr>
<th align="right">Status:</th>
<?php if ($edit) {?>
<td><select name="status"><?php show_state_options($status,$edit,$original[status])?></select>
<?php if ($edit == 1) {?>
<b>Assign To:</b> <input type="text" name="assign" size="10" maxlength="16" value="<?php echo $assign ? htmlspecialchars(stripslashes($assign)) : htmlspecialchars($original[assign])?>"> <input type="submit" value="Submit Changes"></td>
 <small><a href="<?php echo "$PHP_SELF?id=$id";?>&amp;edit=2"><tt>User Modify</tt></a></small></td>
<?php } else { ?>
 <small><a href="<?php echo "$PHP_SELF?id=$id";?>&amp;edit=1"><tt>Dev Modify</tt></a></small></td>
<?php }?>
<?php } else { ?>
<td><?php echo $original[status]?></td>
<td><td><small><a href="<?php echo "$PHP_SELF?id=$id";?>&amp;edit=2"><tt>User Modify</tt></a> &nbsp; <a href="<?php echo "$PHP_SELF?id=$id";?>&amp;edit=1"><tt>Dev Modify</tt></a></small></td>
<?php }?>
</tr>
<tr>
<th align="right">From:</th>
<?php if ($edit) {?>
<td><input type="text" name="email" size="20" maxlength="40" value="<?php echo $email ? htmlspecialchars(stripslashes($email)) : htmlspecialchars($original[email])?>"></td>
<?php } else { ?>
<td><?php echo $original[email]?></td>
<?php }?>
</tr>
<tr>
<th align="right">Reported:</th>
<td><?php echo $original[ts1]?></td>
</tr>
<tr>
<th align="right">Type:</th>
<?php if ($edit) {?>
<td><select name="bug_type"><?php show_types($bug_type,0,$original[bug_type])?></select></td>
<?php } else { ?>
<td><?php echo $original[bug_type]?></td>
<?php }?>
</tr>
<tr>
<th align="right">OS:</th>
<?php if ($edit) {?>
<td><input type="text" name="php_os" size="20" maxlength="32" value="<?php echo $php_os ? htmlspecialchars(stripslashes($php_os)) : htmlspecialchars($original[php_os])?>"></td>
<?php } else { ?>
<td><?php echo $original[php_os]?></td>
<?php }?>
</tr>
<tr>
<th align="right">PHP Version:</th>
<?php if ($edit) {?>
<td><input type="text" name="php_version" size="20" maxlength="100" value="<?php echo $php_version ? htmlspecialchars(stripslashes($php_version)) : htmlspecialchars($original[php_version])?>"></td>
<?php } else { ?>
<td><?php echo $original[php_version]?></td>
<?php }?>
</tr>
<?php if ($original[assign] && !$edit) {?>
<tr>
<th align="right">Assigned To:</th>
<td><?php echo $original[assign]?></td>
</tr>
<?php }?>
<tr>
<th align="right">Summary:</th>
<?php if ($edit) {?>
<td><input type="text" name="sdesc" size="40" maxlength="80" value="<?php echo $sdesc ? htmlspecialchars(stripslashes($sdesc)) : htmlspecialchars($original[sdesc])?>"></td>
<?php } else { ?>
<td><?php echo $original[sdesc]?></td>
<?php }?>
</tr>
<?php if ($edit) {?>
<tr>
<th align="right">New Comment:</th>
</tr>
<tr>
<td colspan="2">
<textarea name="ncomment" wrap="physical" cols="60" rows="15"><?php echo htmlspecialchars(stripslashes($ncomment));?></textarea>
</td>
</tr>
<?php   if ($edit == 1) { /* developer */?>
<tr>
<th align="right">CVS Username:</th>
<td>
<input type="text" name="user" size="10" maxlength="20" value="<?php echo htmlspecialchars(stripslashes($user));?>">
</td>
</tr>
<?php   }?>
<tr>
<th align="right">Password:</th>
<td>
<input type="password" name="pw" size="10" maxlength="20" value="<?php echo htmlspecialchars(stripslashes($pw));?>">
<?php if ($edit == 2) {?>
[<a href="bug-pwd-finder.php">Lost your password?</a>]
<?php }?>
</td>
</tr>
<tr>
<th align="right">Remember me:</th>
<td>
<input type="checkbox" name="save">
(Check here to remember your password for next time.)
</td>
</tr>
<tr>
<td colspan="2">
&nbsp; &nbsp; &nbsp;
<input type="submit" value="Submit Changes">
</td>
</tr>
<?php }?>
</table>
<?php
if ($edit) echo "</form>\n";

echo hdelim();

/* ORIGINAL REPORT */
echo "<b><i>[$original[ts1]] $original[email]</i></b><br>\n";
echo "<blockquote><blockquote><pre>";
echo wordwrap(addlinks($original[ldesc]),90);
echo "</pre></blockquote></blockquote>\n";

/* COMMENTS */
$query = "SELECT * FROM bugdb_comments WHERE bug=$id ORDER BY ts";
if ($comresult = mysql_query($query)) {
	while ($com = mysql_fetch_array($comresult)) {
		echo "<b><i>[$com[ts]] $com[email]</i></b><br>\n";
		echo "<blockquote><blockquote><pre>";
		echo wordwrap(addlinks($com[comment]),90);
		echo "</pre></blockquote></blockquote>\n";
	}
}

commonFooter();

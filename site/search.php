<?php

// Obtain common includes
require_once '../include/prepend.php';

// Redirect early if a bug id is passed as search string
$search_for_id = (isset($_GET['search_for'])) ? (int) $_GET['search_for'] : 0;
if ($search_for_id) {
	redirect("bug.php?id={$search_for_id}");
}

// Authenticate (Disabled for now, searching does not require knowledge of user level)
//bugs_authenticate($user, $pw, $logged_in, $is_trusted_developer);

$newrequest = $_REQUEST;
if (isset($newrequest['PHPSESSID'])) {
	unset($newrequest['PHPSESSID']);
}
response_header(
	'Bugs :: Search',
	" <link rel='alternate'
			type='application/rdf+xml'
			title='RSS feed' href='rss/search.php?" . http_build_query($newrequest) . "' />");

// Include common query handler (used also by rss/search.php)
require "{$ROOT_DIR}/include/query.php";

if (isset($_GET['cmd']) && $_GET['cmd'] == 'display')
{
	if (!$res) {
		$errors[] = 'Invalid query';
	} else {
		// Selected packages to search in
		$package_name_string = '';
		if (count($package_name) > 0) {
			foreach ($package_name as $type_str) {
				$package_name_string.= '&amp;package_name[]=' . urlencode($type_str);
			}
		}

		// Selected packages NOT to search in
		$package_nname_string = '';
		if (count($package_nname) > 0) {
			foreach ($package_nname as $type_str) {
				$package_nname_string.= '&amp;package_nname[]=' . urlencode($type_str);
			}
		}

		$link_params =
				'&amp;search_for='  . urlencode($search_for) .
				'&amp;php_os='      . urlencode($php_os) .
				'&amp;author_email='. urlencode($author_email) .
				'&amp;bug_type='    . urlencode($bug_type) .
				"&amp;boolean=$boolean_search" .
				"&amp;bug_age=$bug_age" .
				"&amp;bug_updated=$bug_updated" .
				"&amp;order_by=$order_by" .
				"&amp;direction=$direction" .
				"&amp;limit=$limit" .
				'&amp;phpver=' . urlencode($phpver) .
				'&amp;assign=' . urlencode($assign);

		$link = "search.php?cmd=display{$package_name_string}{$package_nname_string}{$link_params}";
		$clean_link = "search.php?cmd=display{$link_params}";

		if (isset($_GET['showmenu'])) {
			$link .= '&amp;showmenu=1';
		}

		if (!$rows) {
			$errors[] = 'No bugs were found.';
			display_bug_error($errors, 'warnings', '');
		} else {
			display_bug_error($warnings, 'warnings', 'WARNING:');
			$link .= '&amp;status=' . urlencode($status);
			$package_count = count($package_name);
?>

<table border="0" cellspacing="2" width="100%">

<?php show_prev_next($begin, $rows, $total_rows, $link, $limit);?>

<?php if ($package_count === 1) { ?>
 <tr>
  <td class="search-prev_next" style="text-align: center;" colspan="10">
<?php
	$pck = htmlspecialchars($package_name[0]);
	$pck_url = urlencode($pck);
	echo "Bugs for {$pck}\n";
?>
  </td>
 </tr>
<?php } ?>

 <tr>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=id">ID#</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=ts1">Date</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=ts2">Last Modified</a></th>
<?php if ($package_count !== 1) { ?>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=package_name">Package</a></th>
<?php } ?>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=bug_type">Type</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=status">Status</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=php_version">PHP Version</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=php_os">OS</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=sdesc">Summary</a></th>
  <th class="results"><a href="<?php echo $link;?>&amp;reorder_by=assign">Assigned</a></th>
 </tr>
<?php

			while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
				echo ' <tr valign="top" class="' , $tla[$row['status']], '">' , "\n";

				// Bug ID
				echo '  <td align="center"><a href="bug.php?id=', $row['id'], '">', $row['id'], '</a>';
				echo '<br /><a href="bug.php?id=', $row['id'], '&amp;edit=1">(edit)</a></td>', "\n";

				// Date
				echo '  <td align="center">', format_date(strtotime($row['ts1'])), "</td>\n";

				// Last Modified
				$ts2 = strtotime($row['ts2']);
				echo '  <td align="center">' , ($ts2 ? format_date($ts2) : 'Not modified') , "</td>\n";

				// Package
				if ($package_count !== 1) {
					$pck = htmlspecialchars($row['package_name']);
					$pck_url = urlencode($pck);
					echo "<td><a href='{$clean_link}&amp;package_name[]={$pck_url}'>{$pck}</a></td>\n";
				}

				/// Bug type
				$type_idx = !empty($row['bug_type']) ? $row['bug_type'] : 'Bug';
				echo '  <td>', htmlspecialchars($bug_types[$type_idx]), '</td>', "\n";

				// Status
				echo '  <td>', htmlspecialchars($row['status']);
				if ($row['status'] == 'Feedback' && $row['unchanged'] > 0) {
					printf ("<br />%d day%s", $row['unchanged'], $row['unchanged'] > 1 ? 's' : '');
				}
				echo '</td>', "\n";

				/// PHP version
				echo '  <td>', htmlspecialchars($row['php_version']), '</td>';

				// OS
				echo '  <td>', $row['php_os'] ? htmlspecialchars($row['php_os']) : '&nbsp;', '</td>', "\n";

				// Short description
				echo '  <td>', $row['sdesc']  ? htmlspecialchars($row['sdesc']) : '&nbsp;', '</td>', "\n";

				// Assigned to
				echo '  <td>',  ($row['assign'] ? ("<a href=\"{$clean_link}&amp;assign=" . urlencode($row['assign']) . '">' . htmlspecialchars($row['assign']) . '</a>') : '&nbsp;'), '</td>';
				echo " </tr>\n";
			}

			show_prev_next($begin, $rows, $total_rows, $link, $limit);

			echo "</table>\n\n";
		}

		response_footer();
		exit;
	}
}

display_bug_error($errors);
display_bug_error($warnings, 'warnings', 'WARNING:');

?>
<form id="asearch" method="get" action="search.php">
<table id="primary" width="100%">
<tr valign="top">
  <th>Find bugs</th>
  <td style="white-space: nowrap">with all or any of the w<span class="accesskey">o</span>rds</td>
  <td style="white-space: nowrap"><input type="text" name="search_for" value="<?php echo htmlspecialchars($search_for, ENT_COMPAT, 'UTF-8'); ?>" size="20" maxlength="255" accesskey="o" /><br />
   <small>
<?php show_boolean_options($boolean_search) ?>
(<a href="search-howto.php" target="_new">?</a>)
   </small>
  </td>
  <td rowspan="3">
   <select name="limit"><?php show_limit_options($limit);?></select>
   &nbsp;
   <select name="order_by"><?php show_order_options($limit);?></select>
   <br />
   <small>
	<input type="radio" name="direction" value="ASC" <?php if($direction != "DESC") { echo('checked="checked"'); }?>/>Ascending
	&nbsp;
	<input type="radio" name="direction" value="DESC" <?php if($direction == "DESC") { echo('checked="checked"'); }?>/>Descending
   </small>
   <br /><br />
   <input type="hidden" name="cmd" value="display" />
   <label for="submit" accesskey="r">Sea<span class="accesskey">r</span>ch:</label>
   <input id="submit" type="submit" value="Search" />
  </td>
</tr>
<tr valign="top">
  <th>Status</th>
  <td style="white-space: nowrap">
   <label for="status" accesskey="n">Retur<span class="accesskey">n</span> bugs
   with <b>status</b></label>
  </td>
  <td><select id="status" name="status"><?php show_state_options($status);?></select></td>
</tr>
<tr valign="top">
  <th>Type</th>
  <td style="white-space: nowrap">
   <label for="bug_type">Return bugs with <b>type</b></label>
  </td>
  <td><select id="bug_type" name="bug_type"><?php show_type_options($bug_type, true);?></select></td>
</tr>
</table>

<table style="font-size: 100%;">
<tr valign="top">
  <th><label for="category" accesskey="c">Pa<span class="accesskey">c</span>kage</label></th>
  <td style="white-space: nowrap">Return bugs for these <b>packages</b></td>
  <td><select id="category" name="package_name[]" multiple="multiple" size="6"><?php show_package_options($package_name, 2);?></select></td>
</tr>
<tr valign="top">
  <th>&nbsp;</th>
  <td style="white-space: nowrap">Return bugs <b>NOT</b> for these <b>packages</b></td>
  <td><select name="package_nname[]" multiple="multiple" size="6"><?php show_package_options($package_nname, 2);?></select></td>
</tr>
<tr valign="top">
  <th>OS</th>
  <td style="white-space: nowrap">Return bugs with <b>operating system</b></td>
  <td>
    <input type="text" name="php_os" value="<?php echo htmlspecialchars($php_os, ENT_COMPAT, 'UTF-8'); ?>" />
    <input style="vertical-align:middle;" type="checkbox" name="php_os_not" value="1" <?php echo ($php_os_not == 'not') ? 'checked="checked"' : ''; ?>" /> NOT
  </td>
</tr>
<tr valign="top">
  <th>PHP Version</th>
  <td style="white-space: nowrap">Return bugs reported with <b>PHP version</b></td>
  <td><input type="text" name="phpver" value="<?php echo htmlspecialchars($phpver, ENT_COMPAT, 'UTF-8'); ?>" /></td>
</tr>
<tr valign="top">
  <th>Assigned</th>
  <td style="white-space: nowrap">Return bugs <b>assigned</b> to</td>
  <td><input type="text" name="assign" value="<?php echo htmlspecialchars($assign, ENT_COMPAT, 'UTF-8'); ?>" />
<?php
	if (!empty($auth_user->handle)) {
		$u = htmlspecialchars($auth_user->handle);
		echo "<input type=\"button\" value=\"set to $u\" onclick=\"form.assign.value='$u'\" />";
	}
?>
  </td>
</tr>

<tr valign="top">
  <th>Author e<span class="accesskey">m</span>ail</th>
  <td style="white-space: nowrap">Return bugs with author email</td>
  <td><input accesskey="m" type="text" name="author_email" value="<?php echo htmlspecialchars($author_email, ENT_COMPAT, 'UTF-8'); ?>" />
<?php
	if (!empty($auth_user->handle)) {
		$u = htmlspecialchars($auth_user->handle);
		echo "<input type=\"button\" value=\"set to $u\" onclick=\"form.author_email.value='$u'\" />";
	}
?>
  </td>
</tr>
<tr valign="top">
  <th>Date</th>
  <td style="white-space: nowrap">Return bugs submitted</td>
  <td><select name="bug_age"><?php show_byage_options($bug_age);?></select></td>
 </tr>
 <tr>
  <td>&nbsp;</td><td style="white-space: nowrap">Return bugs updated</td>
  <td><select name="bug_updated"><?php show_byage_options($bug_updated);?></select></td>
</tr>
</table>
</form>

<?php
response_footer();

function show_prev_next($begin, $rows, $total_rows, $link, $limit)
{
	echo "<!-- BEGIN PREV/NEXT -->\n";
	echo " <tr>\n";
	echo '  <td class="search-prev_next" colspan="11">' . "\n";

	if ($limit=='All') {
		echo "$total_rows Bugs</td></tr>\n";
		return;
	}

	echo '   <table border="0" cellspacing="0" cellpadding="0" width="100%">' . "\n";
	echo "	<tr>\n";
	echo '    <td class="search-prev">';
	if ($begin > 0) {
		echo '<a href="' . $link . '&amp;begin=';
		echo max(0, $begin - $limit);
		echo '">&laquo; Show Previous ' . $limit . ' Entries</a>';
	} else {
		echo '&nbsp;';
	}
	echo "</td>\n";

	echo '   <td class="search-showing">Showing ' . ($begin+1);
	echo '-' . ($begin+$rows) . ' of ' . $total_rows . "</td>\n";

	echo '   <td class="search-next">';
	if ($begin+$rows < $total_rows) {
		echo '<a href="' . $link . '&amp;begin=' . ($begin+$limit);
		echo '">Show Next ' . $limit . ' Entries &raquo;</a>';
	} else {
		echo '&nbsp;';
	}
	echo "</td>\n	</tr>\n   </table>\n  </td>\n </tr>\n";
	echo "<!-- END PREV/NEXT -->\n";
}

function show_order_options($current)
{
	global $order_options;

	foreach ($order_options as $k => $v) {
		echo '<option value="', $k, '"', ($v == $current ? ' selected="selected"' : ''), '>Sort by ', $v, "</option>\n";
	}
}

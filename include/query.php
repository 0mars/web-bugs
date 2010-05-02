<?php

$errors = array();
$warnings = array();
$order_options = array(
	''				=> 'relevance',
	'id'			=> 'ID',
	'ts1'			=> 'date',
	'ts2'			=> 'last modified',
	'package_name'	=> 'package',
	'bug_type'		=> 'bug_type',
	'status'		=> 'status',
	'php_version'	=> 'php_version',
	'php_os'		=> 'os',
	'sdesc'			=> 'summary',
	'assign'		=> 'assignment',
);

// Fetch pseudo packages
$pseudo_pkgs = get_pseudo_packages($site);

// Setup input variables..
$boolean_search = isset($_GET['boolean']) ? (int) $_GET['boolean'] : 0;
$status = !empty($_GET['status']) ? $_GET['status'] : 'Open';
$search_for = !empty($_GET['search_for']) ? $_GET['search_for'] : '';
$bug_type = (!empty($_GET['bug_type']) && $_GET['bug_type'] != 'All') ? $_GET['bug_type'] : '';
$bug_age = (int) (isset($_GET['bug_age']) ? $_GET['bug_age'] : 0);
$bug_updated = (int) (isset($_GET['bug_updated']) ? $_GET['bug_updated'] : 0);
$php_os = !empty($_GET['php_os']) ? $_GET['php_os'] : '';
$php_os_not = !empty($_GET['php_os_not']) ? 'not' : '';
$phpver = !empty($_GET['phpver']) ? $_GET['phpver'] : '';
$begin = (int) (!empty($_GET['begin']) ? $_GET['begin'] : 0);
$limit = (defined('MAX_BUGS_RETURN')) ? MAX_BUGS_RETURN : 30;
if (!empty($_GET['limit'])) {
	$limit = ($_GET['limit'] == 'All') ? 'All' : (($_GET['limit'] > 0) ? (int) $_GET['limit'] : $limit);
}
$direction = (!empty($_GET['direction']) && $_GET['direction'] != 'DESC') ? 'ASC' : 'DESC';
$order_by = (!empty($_GET['order_by']) && array_key_exists($_GET['order_by'], $order_options)) ? $_GET['order_by'] : 'id';
$reorder_by = (!empty($_GET['reorder_by']) && array_key_exists($_GET['reorder_by'], $order_options)) ? $_GET['reorder_by'] : '';
$assign = !empty($_GET['assign']) ? $_GET['assign'] : '';
$author_email = (!empty($_GET['author_email']) && is_valid_email($_GET['author_email'])) ? $_GET['author_email'] : '';
$package_name = (isset($_GET['package_name']) && is_array($_GET['package_name'])) ? $_GET['package_name'] : array();
$package_nname = (isset($_GET['package_nname']) && is_array($_GET['package_nname'])) ? $_GET['package_nname'] : array();

if (isset($_GET['cmd']) && $_GET['cmd'] == 'display')
{
	$query = '
		SELECT SQL_CALC_FOUND_ROWS 
		bugdb.*,
		TO_DAYS(NOW())-TO_DAYS(bugdb.ts2) AS unchanged,
		UNIX_TIMESTAMP(ts1) AS submitted,
		UNIX_TIMESTAMP(ts2) AS modified
		FROM bugdb
	';

	$where_clause = ' WHERE 1 = 1 ';

	if (!empty($package_name)) {
		$where_clause .= ' AND bugdb.package_name';
		if (count($package_name) > 1) {
			$where_clause .= " IN ('" . join("', '", escapeSQL($package_name)) . "')";
		} else {
			$where_clause .= ' = ' . $dbh->quote($package_name[0]);
		}
	}

	if (!empty($package_nname)) {
		$where_clause .= ' AND bugdb.package_name';
		if (count($package_nname) > 1) {
			$where_clause .= " NOT IN ('" . join("', '", escapeSQL($package_nname)) . "')";
		} else {
			$where_clause .= ' <> ' . $dbh->quote($package_nname[0]);
		}
	}

	// Ensure status is valid and tweak search clause to treat assigned, analyzed, critical and verified bugs as open
	switch ($status) {
		case 'All':
			break;
		case 'Closed':
		case 'Re-Opened':
		case 'Duplicate':
		case 'Critical':
		case 'Assigned':
		case 'Analyzed':
		case 'Verified':
		case 'Suspended':
		case 'Wont fix':
		case 'No Feedback':
		case 'Feedback':
		case 'Bogus':
			$where_clause .= "	AND bugdb.status='$status'";
			break;
		case 'Old Feedback':
			$where_clause .= "	AND bugdb.status='Feedback'
								AND TO_DAYS(NOW())-TO_DAYS(bugdb.ts2) > 60";
			break;
		case 'Fresh':
			$where_clause .= "	AND bugdb.status NOT IN ('Closed', 'Duplicate', 'Bogus')
								AND TO_DAYS(NOW())-TO_DAYS(bugdb.ts2) < 30";
			break;
		case 'Stale':
			$where_clause .= "	AND bugdb.status NOT IN ('Closed', 'Duplicate', 'Bogus')
								AND TO_DAYS(NOW())-TO_DAYS(bugdb.ts2) > 30";
			break;
		case 'Not Assigned':
			$where_clause .= " AND bugdb.status NOT IN ('Closed', 'Duplicate', 'Bogus', 'Assigned', 'Wont Fix', 'Suspended')";
			break;
		case 'OpenFeedback':
			$where_clause .= " AND bugdb.status IN ('Open', 'Re-Opened', 'Assigned','Analyzed', 'Critical', 'Verified', 'Feedback')";
			break;
		default:
		case 'Open':
			$where_clause .= " AND bugdb.status IN ('Open', 'Re-Opened', 'Assigned', 'Analyzed', 'Critical', 'Verified')";
	}

	if ($search_for != '') {
		list($sql_search, $ignored) = format_search_string($search_for, $boolean_search);
		$where_clause .= $sql_search;
		if (count($ignored) > 0 ) {
			$warnings[] = 'The following words were ignored: ' . implode(', ', array_unique($ignored));
		}
	}

	if ($bug_type != '') {
		if ($bug_type == 'Bugs') {
			$where_clause .= ' AND (bugdb.bug_type = "Bug" OR bugdb.bug_type="Documentation Problem")';
		} else {
			$where_clause .= ' AND bugdb.bug_type = ' . $dbh->quote($bug_type);
		}
	}

	if ($bug_age > 0) {
		$where_clause .= " AND bugdb.ts1 >= DATE_SUB(NOW(), INTERVAL $bug_age DAY)";
	}

	if ($bug_updated > 0) {
		$where_clause .= " AND bugdb.ts2 >= DATE_SUB(NOW(), INTERVAL $bug_updated DAY)";
	}

	if ($php_os != '') {
		$where_clause .= " AND bugdb.php_os {$php_os_not} LIKE '%" . $dbh->escape($php_os) . "%'";
	}

	if ($phpver != '') {
		$where_clause .= " AND bugdb.php_version LIKE '" . $dbh->escape($phpver) . "%'";
	}

	if ($assign != '') {
		$where_clause .= ' AND bugdb.assign = ' . $dbh->quote($assign);
	}

	if ($author_email != '') {
		$where_clause .= ' AND bugdb.email = ' . $dbh->quote($author_email);
	}

	$where_clause .= ' AND (1=1';

	if ($pseudo = array_intersect(array_keys($pseudo_pkgs), $package_name)) {
		$where_clause .= " OR bugdb.package_name";
		if (count($pseudo) > 1) {
			$where_clause .= " IN ('" . join("', '", escapeSQL($pseudo)) . "')";
		} else {
			$where_clause .= " = '" . implode('', escapeSQL($pseudo)) . "'";
		}
	} else {
		$where_clause .= " OR bugdb.package_name IN ('" . join("', '", escapeSQL(array_keys($pseudo_pkgs))) . "')";
	}

	$query .= "$where_clause )";

	if ($reorder_by != '') {
		if ($order_by == $reorder_by) {
			$direction = $direction == 'ASC' ? 'DESC' : 'ASC';
		} else {
			$direction = $reorder_by == 'ts2' ? 'DESC' : 'ASC';
			$order_by = $reorder_by;
		}
	}

	$query .= " ORDER BY $order_by $direction";

	// if status Feedback then sort also after last updated time.
	if ($status == 'Feedback') {
		$query .= ", bugdb.ts2 $direction";
	}

	if ($limit != 'All' && $limit > 0) {
		$query .= " LIMIT $begin, $limit";
	}

	if (stristr($query, ';')) {
		$errors[] = 'BAD HACKER!! No database cracking for you today!';
	} else {
		$res = $dbh->prepare($query)->execute();
		if (!PEAR::isError($res)) {
			$rows = $res->numRows();
			$total_rows = $dbh->prepare('SELECT FOUND_ROWS()')->execute()->fetchOne();
		} else {
			$error = MDB2::errorMessage($res);
			$errors[] = $error;
		}		
		if (defined('MAX_BUGS_RETURN') && $total_rows > $rows) {
			$warnings[] = 'The search was too general, only ' . MAX_BUGS_RETURN . ' bugs will be returned';
		}
	}
}

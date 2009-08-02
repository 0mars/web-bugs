<?php

class Bug_Accountrequest
{
    var $dbh;
    var $id;
    var $created_on;
    var $handle;
    var $salt;
    var $email;

    function __construct ($handle = false)
    {
        $this->dbh = &$GLOBALS['dbh'];
        if ($handle) {
            $this->handle = $handle;
        } else {
            $this->handle = isset($GLOBALS['auth_user']) ? $GLOBALS['auth_user']->handle : false;
        }
        $this->cleanOldRequests();
    }

    function pending()
    {
        if (!$this->handle) {
            return false;
        }
        $request = $this->dbh->prepare('
            SELECT handle
            FROM bug_account_request
            WHERE handle=?
        ')->execute(array($this->handle))->fetchOne();

        if ($request) {
            return true;
        }
        return false;
    }

    function sendEmail()
    {
        if (!$this->handle) {
            throw new Exception('Internal fault: user was not set when sending email, please report to pear-core@lists.php.net');
        }
        $salt = $this->dbh->prepare('
            SELECT salt
            FROM bug_account_request
            WHERE handle=?
        ')->execute(array($this->handle))->fetchOne();
        if (!$salt) {
            throw new Exception('No such handle ' . 
            $this->handle . ' found, cannot send confirmation email');
        }
        $email = $this->dbh->prepare('
            SELECT email
            FROM bug_account_request
            WHERE salt=?
        ')->execute(array($salt))->fetchOne();
        if (!$email) {
            throw new Exception('No such salt found, cannot send confirmation email');
        }
        $mailData = array(
            'salt' => $salt,
        );
        require_once 'Damblan/Mailer.php';
        $mailer = Damblan_Mailer::create('pearweb_account_request_bug', $mailData);
        $additionalHeaders['To'] = $email;
        PEAR::pushErrorHandling(PEAR_ERROR_RETURN);
        if (!DEVBOX) {
            $e = $mailer->send($additionalHeaders);
        }
        PEAR::popErrorHandling();
        if (!DEVBOX && PEAR::isError($e)) {
            throw new Exception('Cannot send confirmation email: ' . $e->getMessage());
        }
        return true;
    }

    function _makeSalt($handle)
    {
        list($usec, $sec) = explode(" ", microtime());
        return md5($handle . ((float)$usec + (float)$sec));
    }

    function find($salt)
    {
        if (!$salt) {
            return false;
        }
        $request = $this->dbh->prepare('
            SELECT id, created_on, salt, handle, email
            FROM bug_account_request
            WHERE salt=?
        ')->execute(array($salt))->fetchRow(MDB2_FETCHMODE_ASSOC);
        

        if (count($request) > 0) {
            foreach ($request as $field => $value) {
                $this->$field = $value;
            }
            return true;
        }
        return false;
    }

    /**
     * Adds a request in the DB
     *
     * @return string salt
     */
    function addRequest($email)
    {
        $salt = $this->_makeSalt($email);
        $handle = '#' . substr($salt, 0, 19);
        $created_on = gmdate('Y-m-d H:i:s');

        $test = $this->dbh->prepare('SELECT email from users where email=?')->execute(array($email))->fetchOne();
        if ($test === $email) {
            return PEAR::raiseError('Email is already in use for an existing account');
        }
        $test = $this->dbh->prepare('SELECT email from bug_account_request where email=?')->execute(array($email))->fetchOne();
        if ($test === $email) {
            // re-use existing request
            $salt = $this->dbh->prepare('SELECT salt FROM bug_account_request WHERE email=?')->execute(array($email))->fetchOne();
            $this->find($salt);
            return $salt;
        }
        $query = '
        insert into bug_account_request (created_on, handle, email, salt)
        values (?, ?, ?, ?)';

        $res = $this->dbh->prepare($query)->execute(array($created_on, $handle, $email, $salt));

        if (PEAR::isError($res)) {
            return $res;
        }
		$stmt = $this->dbh->prepare('SELECT handle FROM bug_account_request WHERE salt=?');
		$res = $stmt->execute(array($salt));
		
		if(!PEAR::isError($res))
        	$this->handle = $stmt->fetchOne();
        
        return $salt;
    }

    function deleteRequest()
    {
        $query = 'delete from bug_account_request where salt=?';

        return $this->dbh->prepare($query)->execute(array($this->salt));
    }

    function validateRequest($handle, $password, $password2, $name)
    {
        $errors = array();
        if (empty($handle) || !preg_match('/^[0-9a-z_]{2,20}\z/', $handle)) {
            $errors[] = 'Username is invalid.';
            $display_form = true;
        }

        if ($password == md5('') || empty($password)) {
            $errors[] = 'Password must not be empty';
        }
        if ($password !== $password2) {
            $errors[] = 'Passwords do not match';
        }

        include_once 'pear-database-user.php';
        if (user::exists($handle)) {
            $errors[] = 'User name "' . $handle .
                '" already exists, please choose another user name';
        }
        @list($firstname, $lastname) = explode(' ', $name, 2);
        // First- and lastname must be longer than 1 character
        if (strlen($firstname) == 1) {
            $errors[] = 'Your firstname appears to be too short.';
        }
        if (strlen($lastname) == 1) {
            $errors[] = 'Your lastname appears to be too short.';
        }

        // Firstname and lastname must start with an uppercase letter
        if (!preg_match("/^[A-Z]/", $firstname)) {
            $errors[] = 'Your firstname must begin with an uppercase letter';
        }
        if (!preg_match("/^[A-Z]/", $lastname)) {
            $errors[] = 'Your lastname must begin with an uppercase letter';
        }

        // No names with only uppercase letters
        if ($firstname === strtoupper($firstname)) {
            $errors[] = 'Your firstname must not consist of only uppercase letters.';
        }
        if ($lastname === strtoupper($lastname)) {
            $errors[] = 'Your lastname must not consist of only uppercase letters.';
        }
        return $errors;
    }

  
    /**
     * Produces an array of email addresses the report should go to
     *
     * @param string $package_name  the package's name
     *
     * @return array  an array of email addresses
     */
    function get_package_mail($package_name, $bug_id = false)
    {
        global $site, $bugEmail, $dbh;
        switch ($package_name) {
            case 'Bug System':
            case 'PEPr':
            case 'Web Site':
                $arr = $this->get_package_mail('pearweb');
                $arr[0] .= ',' . PEAR_WEBMASTER_EMAIL;
                return array($arr[0], PEAR_WEBMASTER_EMAIL);
            case 'Documentation':
                return array(PEAR_DOC_EMAIL, PEAR_DOC_EMAIL);
        }

        include_once 'pear-database-package.php';
        $maintainers = package::info($package_name, 'authors');

        $to = array();
        foreach ($maintainers as $data) {
            if (!$data['active']) {
                continue;
            }
            $to[] = $data['email'];
        }

        /* subscription */
        if ($bug_id) {
            $bug_id = (int)$bug_id;

            $assigned = $dbh->prepare("SELECT assign FROM bugdb WHERE id = ? ")->execute(array($bug_id))->fetchOne();
            if ($assigned) {
                $assigned = $dbh->prepare("SELECT email FROM users WHERE handle = ? ")->execute(array($assigned))->fetchOne();
                if ($assigned && !in_array($assigned, $to)) {
                    // assigned is not a maintainer
                    $to[] = $assigned;
                }
            }
            $bcc = $dbh->prepare("SELECT email FROM bugdb_subscribe WHERE bug_id = ? ")->execute(array($bug_id))->fetchOne();
            $bcc = array_diff($bcc, $to);
            $bcc = array_unique($bcc);
            return array(implode(', ', $to), $bugEmail, implode(', ', $bcc));
        }

        return array(implode(', ', $to), $bugEmail);
    }

    function sendBugCommentEmail($bug)
    {
    	global $bug_types, $siteBig, $site_url, $basedir;

        $ncomment = trim($bug['comment']);
        $text = array();
        $headers = array();

        /* Default addresses */
        list ($mailto, $mailfrom, $Bcc) = $this->get_package_mail($bug['package_name'], $bug['id']);

        $headers[] = array(" ID", $bug['id']);
        $headers[] = array(" Comment by", $this->handle);
        $from = "\"{$this->handle}\" <{$this->email}>";

        if ($f = $this->spam_protect($this->email, 'text')) {
            $headers[] = array(" Reported By", $f);
        }

        $fields = array (
            'sdesc'           => 'Summary',
            'status'          => 'Status',
            'bug_type'        => 'Type',
            'package_name'    => 'Package',
            'php_os'          => 'Operating System',
            'package_version' => 'Package Version',
            'php_version'     => 'PHP Version',
            'assign'          => 'Assigned To'
        );

        foreach ($fields as $name => $desc) {
            /* only fields that are set get added. */
            if ($f = $bug[$name]) {
                $headers[] = array(" $desc", $f);
            }
        }

        # make header output aligned
        $maxlength = 0;
        $actlength = 0;
        foreach ($headers as $v) {
            $actlength = strlen($v[0]) + 1;
            $maxlength = (($maxlength < $actlength) ? $actlength : $maxlength);
        }

        # align header content with headers (if a header contains
        # more than one line, wrap it intelligently)
        $header_text = "";
        $spaces = str_repeat(' ', $maxlength + 1);
        foreach ($headers as $v) {
            $hcontent = wordwrap($v[1], 72-$maxlength, "\n$spaces"); # wrap and indent
            $hcontent = rtrim($hcontent); # wordwrap may add spacer to last line
            $header_text .= str_pad($v[0] . ":", $maxlength) . " " . $hcontent . "\n";
        }

        if ($ncomment) {
            $text[] = " New Comment:\n\n".$ncomment;
        }

        $text[] = $this->get_old_comments($bug['id'], empty($ncomment));

        /* format mail so it looks nice, use 72 to make piners happy */
        $wrapped_text = wordwrap(join("\n",$text), 72);

        /* developer text with headers, previous messages, and edit link */
        $dev_text = 'Edit report at ' .
                    "http://{$site_url}{$basedir}/bug.php?id=$bug[id]&edit=1\n\n" .
                    $header_text .
                    $wrapped_text .
                    "\n-- \nEdit this bug report at " .
                    "http://{$site_url}{$basedir}/bug.php?id=$bug[id]&edit=1\n";

        $user_text = $dev_text;

        $subj = $bug_types[$bug['bug_type']];

        $new_status = $bug['status'];

        $subj .= " #{$bug['id']} [Com]: ";

        # the user gets sent mail with an envelope sender that ignores bounces
        if (DEVBOX == false) {
            @mail($bug['email'],
                  "[{$siteBig}-BUG] {$subj} {$bug['sdesc']}",
                  $user_text,
                  "From: {$siteBig} Bug Database <$mailfrom>\n".
                  "Bcc: $Bcc\n" .
                  "X-PHP-Bug: $bug[id]\n".
                  "In-Reply-To: <bug-$bug[id]@{$site_url}>",
                  "-fbounces-ignored@php.net");
            # but we go ahead and let the default sender get used for the list

            @mail($mailto,
                  "[{$siteBig}] " . $subj . $bug['sdesc'],
                  $dev_text,
                  "From: $from\n".
                  "X-PHP-Bug: {$bug['id']}\n".
                  "X-PHP-Type: {$bug['bug_type']}\n" .
                  "X-PHP-PackageVersion: " . $bug['package_version'] . "\n" .
                  "X-PHP-Version: "        . $bug['php_version'] . "\n" .
                  "X-PHP-Category: "       . $bug['package_name']    . "\n" .
                  "X-PHP-OS: "             . $bug['php_os']      . "\n" .
                  "X-PHP-Status: "         . $new_status . "\n" .
                  "In-Reply-To: <bug-{$bug['id']}@{$site_url}>",
                  "-f bounce-no-user@php.net");
        }
    }

    static function sendPatchEmail($patch)
    {
        require_once 'Damblan/Mailer.php';
        require_once 'Damblan/Bugs.php';
        $patchName = urlencode($patch['patch']);
        $mailData = array(
            'id'         => $patch['bugdb_id'],
            'url'        => 'http://' . PEAR_CHANNELNAME .
                            "/bugs/patch-display.php?bug=$patch[bugdb_id]&patch=$patchName&revision=$patch[revision]&display=1",

            'date'       => date('Y-m-d H:i:s'),
            'name'       => $patch['patch'],
            'package'    => $patch['package_name'],
            'summary'    => $GLOBALS['dbh']->prepare('SELECT sdesc from bugdb
                WHERE id=?')->execute(array($patch['bugdb_id']))->fetchOne(),
            'packageUrl' => 'http://' . PEAR_CHANNELNAME .
                            '/bugs/bug.php?id=' . $patch['bugdb_id'],
        );

        $additionalHeaders['To'] = Damblan_Bugs::getMaintainers($patch['package_name']);
        $mailer = Damblan_Mailer::create('Patch_Added', $mailData);
        $res = true;
        if (!DEVBOX) {
            $res = $mailer->send($additionalHeaders);
        }
    }

    /**
     * Produces a string containing the bug's prior comments
     *
     * @param int $bug_id  the bug's id number
     * @param int $all     should all existing comments be returned?
     *
     * @return string  the comments
     */
    function get_old_comments($bug_id, $all = 0)
    {
        $divider = str_repeat("-", 72);
        $max_message_length = 10 * 1024;
        $max_comments = 5;
        $output = ""; $count = 0;

        $res =& $this->dbh->prepare("SELECT ts, email, comment, handle FROM bugdb_comments WHERE bug= ? ORDER BY ts DESC")->execute(array($bug_id));

        # skip the most recent unless the caller wanted all comments
        if (!$all) {
            $row =& $res->fetchRow(MDB2_FETCHMODE_ORDERED);
            if (!$row) {
                return '';
            }
        }

        while (($row =& $res->fetchRow(MDB2_FETCHMODE_ORDERED)) &&
                strlen($output) < $max_message_length && $count++ < $max_comments) {
            $email = $row[3] ?
                $row[3] :
                $this->spam_protect($row[1], 'text');
            $output .= "[$row[0]] $email\n\n$row[2]\n\n$divider\n\n";
        }

        if (strlen($output) < $max_message_length && $count < $max_comments) {
            $res =& $this->dbh->prepare("SELECT ts1,email,ldesc,handle FROM bugdb WHERE id= ? ")->execute(array($bug_id));
            if (!$res) {
                return $output;
            }
            $row =& $res->fetchRow(MDB2_FETCHMODE_ORDERED);
            if (!$row) {
                return $output;
            }
            $email = $row[3] ?
                $row[3] :
                $this->spam_protect($row[1], 'text');
            return ("\n\nPrevious Comments:\n$divider\n\n" . $output . "[$row[0]] $email\n\n$row[2]\n\n$divider\n\n");
        } else {
            return ("\n\nPrevious Comments:\n$divider\n\n" . $output . "The remainder of the comments for this report are too long. To view\nthe rest of the comments, please view the bug report online at\n    http://pear.php.net/bugs/bug.php?id=$bug_id\n");
        }

        return '';
    }

    /**
     * Obfuscates email addresses to hinder spammer's spiders
     *
     * Turns "@" into character entities that get interpreted as "at" and
     * turns "." into character entities that get interpreted as "dot".
     *
     * @param string $txt     the email address to be obfuscated
     * @param string $format  how the output will be displayed ('html', 'text')
     *
     * @return string  the altered email address
     */
    function spam_protect($txt, $format = 'html')
    {
        if ($format == 'html') {
            $translate = array(
                '@' => ' &#x61;&#116; ',
                '.' => ' &#x64;&#111;&#x74; ',
            );
        } else {
            $translate = array(
                '@' => ' at ',
                '.' => ' dot ',
            );
        }
        return strtr($txt, $translate);
    }

    function sendBugEmail($buginfo)
    {
    	global $bug_types, $siteBig, $site_url, $basedir;
    	
        $report  = '';
        $report .= 'From:             ' . $this->handle . "\n";
        $report .= 'Operating system: ' . $buginfo['php_os'] . "\n";
        $report .= 'Package version:  ' . $buginfo['package_version'] . "\n";
        $report .= 'PHP version:      ' . $buginfo['php_version'] . "\n";
        $report .= 'Package:          ' . $buginfo['package_name'] . "\n";
        $report .= 'Bug Type:         ' . $buginfo['bug_type'] . "\n";
        $report .= 'Bug description:  ';

        $fdesc = $buginfo['ldesc'];
        $sdesc = $buginfo['sdesc'];

        $ascii_report  = "$report$sdesc\n\n" . wordwrap($fdesc);
        $ascii_report .= "\n-- \nEdit bug report at ";
        $ascii_report .= "http://{$site_url}{$basedir}/bug.php?id=$buginfo[id]&edit=1";

        list($mailto, $mailfrom) = $this->get_package_mail($buginfo['package_name']);

        $email = $this->email;
        $protected_email  = '"' . $this->spam_protect($email, 'text') . '"';
        $protected_email .= '<' . $mailfrom . '>';

        $extra_headers  = 'From: '           . $protected_email . "\n";
        $extra_headers .= 'X-PHP-BugTracker: PEARbug' . "\n";
        $extra_headers .= 'X-PHP-Bug: '      . $buginfo['id'] . "\n";
        $extra_headers .= 'X-PHP-Type: '     . $buginfo['bug_type'] . "\n";
        $extra_headers .= 'X-PHP-PackageVersion: '  . $buginfo['package_version'] . "\n";
        $extra_headers .= 'X-PHP-Version: '  . $buginfo['php_version'] . "\n";
        $extra_headers .= 'X-PHP-Category: ' . $buginfo['package_name'] . "\n";
        $extra_headers .= 'X-PHP-OS: '       . $buginfo['php_os'] . "\n";
        $extra_headers .= 'X-PHP-Status: Open' . "\n";
        $extra_headers .= 'Message-ID: <bug-' . $buginfo['id'] . '@pear.php.net>';

        $type = @$bug_types[$buginfo['bug_type']];

        if (DEVBOX == false) {
            // mail to package developers
            @mail($mailto, "[{$siteBig}] $buginfo[bug_type] #$buginfo[id] [NEW]: $sdesc",
                  $ascii_report . "1\n-- \n$dev_extra", $extra_headers,
                  '-f bounce-no-user@php.net');
            // mail to reporter
            @mail($email, "[{$siteBig}] $buginfo[bug_type] #$buginfo[id]: $sdesc",
                  $ascii_report . "2\n",
                  "From: {$siteBig} Bug Database <$mailfrom>\n" .
                  "X-PHP-Bug: $buginfo[id]\n" .
                  "Message-ID: <bug-$buginfo[id]@{$site_url}>",
                  '-f bounce-no-user@php.net');
        }
    }
    function listRequests()
    {
    }

    function cleanOldRequests()
    {
        $old = gmdate('Y-m-d H:i:s', time() - 604800);
        $findquery = 'select handle from bug_account_request where created_on < ?';
        $all = $this->dbh->prepare($findquery)->execute(array($old))->fetchAll(MDB2_FETCHMODE_DEFAULT);
        require_once "{$ROOT_DIR}/include/classes/bug_patchtracker.php";
        $p = new Bug_Patchtracker;
        // purge reserved usernames as well as their account requests
        if (is_array($all)) {
            foreach ($all as $data) {
                $this->dbh->prepare('
                    DELETE FROM users WHERE handle=?
                ')->execute(array($data[0]));
                $this->dbh->prepare('
                    DELETE FROM bugdb WHERE handle=?
                ')->execute(array($data[0]));
                $this->dbh->prepare('
                    DELETE FROM bugdb_comments WHERE handle=?
                ')->execute(array($data[0]));
                $patches = $this->dbh->prepare('SELECT * FROM bugdb_patchtracker
                    WHERE developer=?')->execute(array($data[0]))->fetchAll(MDB2_FETCHMODE_ASSOC);
                foreach ($patches as $patch) {
                    $p->detach($patch['bugdb_id'], $patch['patch'], $patch['revision']);
                }
            }
        }
        $query = 'delete from bug_account_request where created_on < ?';
        // purge out-of-date account requests
        return $this->dbh->prepare($query)->execute(array($old));
    }
}

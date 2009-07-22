<?php

require_once '../include/prepend.inc';

/* Input vars */
$bug_id = !empty($_GET['bug_id']) ? (int) $_GET['bug_id'] : 0;

// Authenticate
bugs_authenticate($user, $pw, $logged_in, $is_trusted_developer);
$canpatch = ($logged_in == 'developer');

if (empty($bug_id)) {
    response_header('Error :: no bug selected');
    display_bug_error('No bug id selected');
    response_footer();
    exit;
}

require "{$ROOT_DIR}/include/classes/bug_patchtracker.php";
$patchinfo = new Bug_Patchtracker;

if (PEAR::isError($buginfo = $patchinfo->getBugInfo($bug_id))) {
    response_header('Error :: invalid bug selected');
    display_bug_error("Invalid bug #{$bug_id} selected");
    response_footer();
    exit;
}
if (isset($_GET['patchname']) && isset($_GET['revision'])) {
    if ($_GET['revision'] == 'latest') {
        $revisions = $patchinfo->listRevisions($bug_id, $_GET['patchname']);
        if (isset($revisions[0])) {
            $_GET['revision'] = $revisions[0][0];
        }
    }
    if (!file_exists($path = $patchinfo->getPatchFullpath($bug_id, $_GET['patchname'], $_GET['revision']))) {
        response_header('Error :: no such patch/revision');
        display_bug_error('Invalid patch/revision specified');
        response_footer();
        exit;
    }
    if ($site != 'php' && $patchinfo->userNotRegistered($bug_id, $_GET['patchname'], $_GET['revision'])) {
        response_header('User has not confirmed identity');
        display_bug_error('The user who submitted this patch has not yet confirmed their email address.');
        echo '<p>If you submitted this patch, please check your email.</p>' .
            '<p><strong>If you do not have a confirmation message</strong>, <a href="resend-request-email.php?' .
            'handle=' . urlencode($patchinfo->getDeveloper($bug_id, $_GET['patchname'], $_GET['revision'])) . '">click here to re-send</a>' .
            'or write a message to <a href="mailto:pear-dev@lists.php.net">pear-dev@lists.php.net</a> asking for manual approval of your account.</p>';
        response_footer();
        exit;
    }
    if (isset($_GET['download'])) {
    	$tmp = filemtime($path);
        header('Last-modified: ' . date('D M d H:i:s Y', $tmp - date('Z', $tmp)) . ' UTC');
        header('Content-type: application/octet-stream');
        header("Content-disposition: attachment; filename=\"{$_GET['patchname']}.patch.txt\"");
        header('Content-length: '.filesize($path));
        readfile($path);
        exit;
    }
    $patchcontents = $patchinfo->getPatch($bug_id, $_GET['patchname'], $_GET['revision']);

    if (PEAR::isError($patchcontents)) {
        response_header('Error :: Cannot retrieve patch');
        display_bug_error('Internal error: Invalid patch/revision specified (is in database, but not in filesystem)');
        response_footer();
        exit;
    }
    $package = $buginfo['package_name'];
    $handle = $patchinfo->getDeveloper($bug_id, $_GET['patchname'], $_GET['revision']);
    $revision = $_GET['revision'];
    $patchname = $_GET['patchname'];
    response_header("Bug #{$bug_id} :: Patches");
    $obsoletedby = $patchinfo->getObsoletingPatches($bug_id, $_GET['patchname'], $_GET['revision']);
    $obsoletes = $patchinfo->getObsoletePatches($bug_id, $_GET['patchname'], $_GET['revision']);
    $patches = $patchinfo->listPatches($bug_id);
    include "{$ROOT_DIR}/templates/listpatches.php";
    $revisions = $patchinfo->listRevisions($bug_id, $_GET['patchname']);
    $revision = $_GET['revision'];
    if (isset($_GET['diff']) && $_GET['diff'] && isset($_GET['old']) && is_numeric($_GET['old'])) {
        $old = $patchinfo->getPatchFullpath($bug_id, $_GET['patchname'], $_GET['old']);
        $new = $path;
        if (!realpath($old) || !realpath($new)) {
            response_header('Error :: Cannot retrieve patch');
            display_bug_error('Internal error: Invalid patch revision specified for diff');
            response_footer();
            exit;
        }
        require_once "{$ROOT_DIR}/include/classes/bug_diff_renderer.php";
        assert_options(ASSERT_WARNING, 0);
        $d = new Text_Diff($orig = file($old), $now = file($new));
        $diff = new Bug_Diff_Renderer($d);
        include "{$ROOT_DIR}/templates/patchdiff.php";
        response_footer();
        exit;
    }
    include "{$ROOT_DIR}/templates/patchdisplay.php";
    response_footer();
    exit;
}
response_header("Bug #{$bug_id} :: Patches");
$patches = $patchinfo->listPatches($bug_id);
include "{$ROOT_DIR}/templates/listpatches.php";
response_footer();

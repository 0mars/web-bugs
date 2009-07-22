<?php /* vim: set noet ts=4 sw=4: : */

/* Generates an RSS/RDF feed for a set of bugs
 * based on search criteria as provided.
 *
 * Search code borrowed from /search.php (As of Revision: 1.82)
 * and accepts the same parameters.
 *
 * When changes are made to that API,
 * they should be reflected here for consistency
 *
 * borrowed from php-bugs-web, implementation by Sara Golemon <pollita@php.net>
 * ported by Gregory Beaver <cellog@php.net>
 */

/* Maximum number of bugs to return */
define ('MAX_BUGS_RETURN', 150);

/**
 * Obtain common includes
 */
require_once '../../include/prepend.inc';
require "{$ROOT_DIR}/include/query.php";

if (!$res) {
    die('Invalid query');
} else {
    $res  = $dbh->prepare($query)->execute();
    $rows = $res->numRows();
    $total_rows = $dbh->prepare('SELECT FOUND_ROWS()')->execute()->fetchOne();
}


header('Content-type: text/xml');

echo '<?xml version="1.0"?>
<?xml-stylesheet 
href="http://www.w3.org/2000/08/w3c-synd/style.css" type="text/css"
?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/"
xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
xmlns:admin="http://webns.net/mvcb/" xmlns:content="http://purl.org/rss/1.0/modules/content/">';
echo "\n  <channel rdf:about=\"http://{$site_url}{$basedir}/rss/search.php\">\n";
echo "    <title>{$siteBig} Bug Search Results</title>\n";
echo "    <link>http://{$site_url}{$basedir}/rss/search.php?" , htmlspecialchars(http_build_query($_GET)) , "</link>\n";
echo "    <description>Search Results</description>\n";
echo "    <dc:language>en-us</dc:language>\n";
echo "    <dc:creator>{$site}-webmaster@lists.php.net</dc:creator>\n";
echo "    <dc:publisher>{$site}-webmaster@lists.php.net</dc:publisher>\n";
echo "    <admin:generatorAgent rdf:resource=\"http://{$site_url}{$basedir}\"/>\n";
echo "    <sy:updatePeriod>hourly</sy:updatePeriod>\n";
echo "    <sy:updateFrequency>1</sy:updateFrequency>\n";
echo "    <sy:updateBase>2000-01-01T12:00+00:00</sy:updateBase>\n";
echo '    <items>
     <rdf:Seq>
';

if ($total_rows > 0) {
    $items = '';

    foreach ($res->fetchAll(MDB2_FETCHMODE_ASSOC) as $row) {
        $i++;

        $desc = "{$row['package_name']} ({$row['bug_type']})\nReported by ";
        if ($row['handle']) {
        	$desc .= "{$row['handle']}\n";
       	} else {
       		$desc .= substr($row['email'], 0, strpos($row['email'], '@')) . "@...\n";
		}
		$desc .= date(DATE_ATOM, $row['ts1a']) . "\n";
		$desc .= "PHP: {$row['php_version']}, OS: {$row['php_os']}, Package Version: {$row['package_version']}\n\n";
		$desc .= $row['ldesc'];
		$desc = '<pre>' . utf8_encode(htmlspecialchars($desc)) . '</pre>';

		echo "      <rdf:li rdf:resource=\"http://{$site_url}{$basedir}/{$row['id']}\" />\n";
        $items .= "  <item rdf:about=\"http://{$site_url}{$basedir}/{$row['id']}\">\n";
        $items .= '    <title>' . utf8_encode(htmlspecialchars("{$row['bug_type']} {$row['id']} [{$row['status']}] {$row['sdesc']}")) . "</title>\n";
        $items .= "    <link>http://{$site_url}{$basedir}/{$row['id']}</link>\n";
        $items .= '    <content:encoded><![CDATA[' .  $desc . "]]></content:encoded>\n";
        $items .= '    <description><![CDATA[' . $desc . "]]></description>\n";
        if (!$row['unchanged']) {
            $items .= '    <dc:date>' . date(DATE_ATOM, $row['ts1a']) . "</dc:date>\n";
        } else {
            $items .= '    <dc:date>' . date(DATE_ATOM, $row['ts2a']) . "</dc:date>\n";
        }
        $items .= '    <dc:creator>' . utf8_encode(htmlspecialchars(spam_protect($row['email']))) . "</dc:creator>\n";
        $items .= '    <dc:subject>' .
           utf8_encode(htmlspecialchars($row['package_name'])) . ' ' .
           utf8_encode(htmlspecialchars($row['bug_type'])) . "</dc:subject>\n";
        $items .= "  </item>\n";
    }
} else {
    $warnings[] = "No bugs matched your criteria";
}

echo <<< DATA
     </rdf:Seq>
    </items>
  </channel>

  <image rdf:about="http://{$site_url}{$basedir}/images/{$site}-logo.gif">
    <title>{$siteBig} Bugs</title>
    <url>http://{$site_url}{$basedir}/images/{$site}-logo.gif</url>
    <link>http://{$site_url}{$basedir}</link>
  </image>

{$items}
DATA;
?>
</rdf:RDF>
<?php
if (count($warnings) > 0) {
    echo "<!--\n\n";
    echo "The following warnings occured during your request:\n\n";
    foreach($warnings as $warning) {
        echo utf8_encode(htmlspecialchars('* ' . $warning)) . "\n";
    }
    echo "-->\n";
}

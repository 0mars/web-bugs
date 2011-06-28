<?php
echo '<?xml version="1.0"?>';

$desc = "{$bug['package_name']} {$bug['bug_type']}\nReported by ";
if ($bug['handle']) {
	$desc .= "{$bug['handle']}\n";
} else {
	$desc .= substr($bug['email'], 0, strpos($bug['email'], '@')) . "@...\n";
}
$desc .= date(DATE_RSS, $bug['submitted']) . "\n";
$desc .= "PHP: {$bug['php_version']}, OS: {$bug['php_os']}, Package Version: {$bug['package_version']}\n\n";
$desc .= $bug['ldesc'];
$desc = '<pre>' . clean($desc) . '</pre>';
 
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title><?php echo "{$bug['package_name']} Bug #{$bug['id']}"; ?></title>
		<link><?php echo $uri; ?></link>
		<description><?php echo clean("[{$bug['status']}] {$bug['sdesc']}"); ?></description>
		<pubDate><?php echo date('r', $bug['submitted']); ?></pubDate>
		<lastBuildDate><?php echo date('r', ($bug['modified']) ? $bug['modified'] : $bug['submitted']); ?></lastBuildDate>
		<atom:link href="<?php echo "https://{$site_url}{$basedir}/rss/bug.php?id={$bug['id']}&amp;format=rss2"; ?>" rel="self" type="application/rss+xml" />
		<item>
			<title><?php echo ($bug['handle']) ? clean($bug['handle']) : clean(substr($bug['email'], 0, strpos($bug['email'], '@'))), "@... [{$bug['ts1']}]"; ?></title>
			<description><![CDATA[ <?php echo $desc; ?> ]]></description>
			<pubDate><?php echo date('r', $bug['submitted']); ?></pubDate>
			<guid><?php echo $uri; ?></guid>
		</item>
<?php
	foreach ($comments as $comment) {
		if (empty($comment['registered'])) continue;
		$displayts = date(DATE_RSS, $comment['added']);
?>
			<item>
				<title><?php echo clean(($comment['handle']) ? $comment['handle'] . " [$displayts]" : substr($comment['email'], 0, strpos($comment['email'], '@')) . "@... [$displayts]"); ?></title>
				<description><![CDATA[ <?php echo '<pre>', clean($comment['comment']), '</pre>'; ?>]]></description>
				<pubDate><?php echo date('r', $comment['added']); ?></pubDate>		
				<guid><?php echo $uri, '#', $comment['added']; ?></guid>
			</item>
<?php } ?>
	</channel>
</rss>

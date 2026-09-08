<?php
// Register this plugin's config page under FPP's Content Setup menu.
//
// The $pages variable is scanned by FPP to add entries to the sidebar.
// Clicking the menu item loads config.php via FPP's plugin.php framework,
// which handles URL construction and asset serving for us.

$pages = array(
    'name' => 'Request Falcon',
    'type' => 'content',
    'page' => 'config.php',
);
?>

<li><a href="<?php echo preg_replace('/.*\/plugins\/(.*)/', 'plugin.php?_menu=content&plugin=$1&page=config.php', dirname(__FILE__)); ?>">Request Falcon</a></li>

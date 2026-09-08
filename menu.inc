<?php
// Register the Request Falcon config page in FPP's Content Setup menu.

$menuEntries = array(
    array(
        'text' => 'Request Falcon',
        'type' => 'content',
        'page' => 'config.php',
        'wrap' => 1,
    ),
);

// $plugin and $menu are supplied by FPP when it includes this file.
foreach ($menuEntries as $entry) {
    if ($entry['type'] != $menu) {
        continue;
    }
    if (preg_match('/^http.?:\/\//', $entry['page'])) {
        printf("<li><a href='%s' target='_blank'>%s</a></li>\n",
            $entry['page'], $entry['text']);
    } else {
        $nopage = '';
        if (isset($entry['wrap']) && $entry['wrap'] == 0) {
            $nopage = '&nopage=1';
        }
        printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n",
            $plugin, $entry['page'], $nopage, $entry['text']);
    }
}
?>

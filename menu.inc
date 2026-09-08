<?php
// Menu entry for the Request Falcon plugin.
//
// This file matches FPPMon's proven pattern. It defines a $menuEntries
// array, then iterates through it looking for entries matching the
// current menu section. wrap=1 means "render inside FPP's admin chrome"
// (header/menu/footer). wrap=0 would strip all chrome (useful for
// standalone widgets, not what we want here).

$menuEntries = array(
    array(
        'text' => 'Request Falcon',
        'type' => 'content',
        'page' => 'config.php',
        'wrap' => 1,
    ),
);

// $plugin and $menu are supplied by FPP when it includes this file.
// $plugin = the plugin's repoName; $menu = which menu section is
// being built. We only output entries matching the current section.
foreach ($menuEntries as $entry) {
    if ($entry['type'] != $menu) {
        continue;
    }
    if (preg_match('/^http.?:\/\//', $entry['page'])) {
        printf(
            "<li><a href='%s' target='_blank'>%s</a></li>\n",
            $entry['page'],
            $entry['text']
        );
    } else {
        $nopage = '';
        if (isset($entry['wrap']) && $entry['wrap'] == 0) {
            $nopage = '&nopage=1';
        }
        printf(
            "<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n",
            $plugin,
            $entry['page'],
            $nopage,
            $entry['text']
        );
    }
}
?>

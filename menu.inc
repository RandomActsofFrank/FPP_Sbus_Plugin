<?php
$menuEntries = array(
    Array(
        'text' => 'SBUS - Configuration',
        'type' => 'content',
        'page' => 'content.php',
        'wrap' => 1
    ),
    Array(
        'text' => 'SBUS - Status',
        'type' => 'status',
        'page' => 'status.php',
        'wrap' => 1
    ),
    Array(
        'text' => 'SBUS - Help',
        'type' => 'help',
        'page' => 'help/help.php',
        'wrap' => 1
    ),
    Array(
        'text' => 'SBUS - About',
        'type' => 'help',
        'page' => 'about.php',
        'wrap' => 1
    )
);

##############################################################################
# Display the menu entries for this plugin.
foreach ($menuEntries as $entry) {
    if ($entry['type'] != $menu)
        continue;

    if (preg_match('/^http.?:\/\//', $entry['page'])) {
        printf("<a href='%s'>%s</a>\n", $entry['page'], $entry['text']);
    } else {
        $nopage = '';
        if (isset($entry['wrap']) && ($entry['wrap'] == 0))
            $nopage = '&nopage=1';
        printf("<a href='plugin.php?plugin=%s&page=%s%s'>%s</a>\n",
            $plugin, $entry['page'], $nopage, $entry['text']);
    }
}
?>

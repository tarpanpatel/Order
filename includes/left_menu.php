<?php
// Includes/left_menu.php (Recursive logic)
function renderMenu($items, $parentId = 0) {
    foreach ($items as $item) {
        if ($item['parent_id'] == $parentId) {
            echo "<a href='{$item['url']}'>{$item['title']}</a>";
            // Check for children
            renderMenu($items, $item['id']);
        }
    }
}
?>
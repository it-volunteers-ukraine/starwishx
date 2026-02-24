<?php
$total_posts = $args['total_posts'] ?? 0;
$found_name = get_field('found_posts_name', 'options');
$select_title = get_field('sorting_select_title', 'options');
$sortby_list = get_field('sortby_list', 'options')[0];
$desc_name = $sortby_list['desc_name'] ?? 'DESC';
$asc_name = $sortby_list['asc_name'] ?? 'ASC';
$sortby = $sortby_list['sortby'] ?? 'date';
$sortby = isset($_GET['sortby']) ? $_GET['sortby'] : $sortby;
$order = $sortby_list['order'] ?? 'DESC';
$order = isset($_GET['order']) ? $_GET['order'] : $order;

// echo 'found_name: ' . $found_name . '<br>';
// echo 'select_title: ' . $select_title . '<br>';
// // echo 'sortby_list: ' . $sortby_list . '<br>';
// echo 'desc_name: ' . $desc_name . '<br>';
// echo 'asc_name: ' . $asc_name . '<br>';
// echo 'sortby_list[desc_name]: ' . $sortby_list['desc_name'] . '<br>';
// echo 'sortby_list: ';
// echo '<pre>'; // Обертываем в теги для форматирования
// echo var_dump($sortby_list);
// echo '</pre>';

?>

<div class="text-r block-filter">
    <p><?php echo $found_name . ": "; ?><?php echo $total_posts; ?></p>
    <div class="sortby filter ">
        <div class="sortby-title filter-title"><?php echo esc_html($select_title) . ': '; ?></div>
        <div class="custom-select btn-text-medium  sortby-select ">
            <form method='get'>
                <?php foreach ($_GET as $key => $value): ?>
                    <?php if ($key == 'search' ): ?>
                        <input type="hidden" name="<?= esc_attr($key) ?>" value="<?= esc_attr($value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <input type="hidden" name="sortby" value="<?= esc_attr($sortby) ?>">
                <select name="order" id="sort" onchange="this.form.submit()">
                    <option value="0">Спочатку Старі</option>
                    <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>><?php echo $desc_name; ?></option>
                    <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>><?php echo $asc_name; ?></option>
                </select>
            </form>
            <svg class="sort-icon ">
                <use href="<?php echo get_template_directory_uri(); ?>/assets/img/sprites.svg#icon-arrow_down"></use>
            </svg>
        </div>
    </div>
</div>
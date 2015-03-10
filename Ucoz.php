<?php
/**
 * @package Migrate_Ucoz
 * @version 1.1
 */

/*
Plugin Name: Migrate Ucoz
Plugin URI: https://github.com/distroid/WordPress-Importer
Description: Import content form Ucoz
Version: 1.1
Author: distroid
Author URI: https://github.com/distroid
*/


/**
 * Parse file content
 *
 * @param string $filename
 *
 * @return array
 */
function parse_file($filename = '')
{
    // http://forum.ucoz.ru/forum/33-29187-1 description file structures
    $filePath = WP_CONTENT_DIR . "/migrated/" . $filename;
    if (empty($filename) || !is_file($filePath)) {
        return array();
    }

    $data           = array();
    $textString     = '';
    $file_handle    = fopen($filePath, "r");

    while (!feof($file_handle)) {
        // text maybe has line breaks, so if the string ends with '\',
        // we are cut off him and string is connected to the next string.
        $line       = $textString.fgets($file_handle);
        $str        = trim($line);
        $textString = '';

        if (mb_substr($str, -1, 1, 'UTF-8') == '\\') {
            $textString = mb_substr($line, 0, strlen($line) - 1, 'UTF-8');
            continue;
        }
        if (!empty($line)){
            $data[] = explode('|', $line);
        }
    }

    return $data;
}


/**
 * Import articles and categories
 *
 * @return void
 */
function load_posts_and_post_categories()
{
    $raw_categories     = parse_file("pu_pu.txt");      // get a list of categories and subcategories for the migration of file
    $categories         = array();                      // a list of categories to add to WP
    $categoriesMapping  = array();                      // Complies categories WP and Ucoz

    // form a list of categories and grouping them according to parent category
    foreach ($raw_categories as $raw_categorie) {
        $categories[$raw_categorie[1]][] = array(
            'cat_name'      => $raw_categorie[5],   // category name
            'description'   => $raw_categorie[6],   // category description
            'nicename'      => $raw_categorie[10],  // routing
            'parent'        => $raw_categorie[1],   // parent category
            'id'            => $raw_categorie[0],   // id category in Ucoz
        );
    }

    // add a category to the system and form a list of the categories in Ucoz and WP
    foreach ($categories as $categories_list) {
        foreach ($categories_list as $categorie) {
            $parent = 0;
            if (!empty($categoriesMapping[$categorie['parent']])) {
                $parent = $categoriesMapping[$categorie['parent']];
            }
            $categorieData = array(
                'cat_name'              => $categorie['cat_name'],      // category name
                'category_description'  => $categorie['description'],   // category description
                'category_nicename'     => $categorie['nicename'],      // routing
            );
            $wpCategorieId                          = wp_insert_category($categorieData, true);
            $categoriesMapping[$categorie['id']]    = !empty($wpCategorieId) ? $wpCategorieId : get_term_by('name', $categorie['cat_name'], 'category')->term_id;
        }
    }

    // getting a list of articles from the file and adding them to the database WP
    $posts = parse_file("publ.txt");
    foreach ($posts as $post) {
        // get article category
        $postCategory   = isset($categoriesMapping[$post[2]]) ? array($categoriesMapping[$post[2]]) : array();
        $postData       = array(
            'post_title'    => isset($post[13]) ? $post[13] : '',                               // article title
            'post_content'  => isset($post[20]) ? $post[20] : '',                               // article content
            'post_date'     => isset($post[5]) ? date("Y-m-d H:i:s", $post[5]) : '',            // date created
            'post_status'   => 'publish',                                                       // posting after adding to DB
            'post_author'   => 1,                                                               // user author id
            'post_category' => $postCategory,                                                   // article categories
            'post_name'     => isset($post[count($post) - 2]) ? $post[count($post) - 2] : ''    // article rountig
        );
        // $post[count($post) - 2] -- receives the name of URL, with a long hack is needed
        // to ensure that the array can be of different lengths, the last change Index
        // the name is always last field
        wp_insert_post($postData);
    }
}


/**
 * Import news and categories
 *
 * @return void
 */
function load_news_and_news_categories()
{
    $categorieData = array(
        'cat_name'          => 'News',      // category name
        'category_nicename' => 'news',      // routing
    );

    $wpCategorieId  = wp_insert_category($categorieData);
    if (!is_integer($wpCategorieId)) {
        $wpCategorieId = get_term_by('name', $categorieData['cat_name'], 'category')->term_id;
    }

    // getting categories list from file and adding to WP
    $posts = parse_file("news.txt");
    foreach ($posts as $post) {
        $postData       = array(
            'post_title'    => isset($post[11]) ? $post[11] : '',                               // article title
            'post_content'  => isset($post[13]) ? $post[13] : '',                               // article content
            'post_date'     => isset($post[8]) ? date("Y-m-d H:i:s", $post[8]) : '',            // date created
            'post_status'   => 'publish',                                                       // posting after adding to DB
            'post_author'   => 1,                                                               // user author id
            'post_category' => array($wpCategorieId),                                           // article categories
            'post_name'     => isset($post[count($post) - 2]) ? $post[count($post) - 2] : ''    // article rountig
        );
        // $post[count($post) - 2] -- receives the name of URL, with a long hack is needed
        // to ensure that the array can be of different lengths, the last change Index
        // the name is always last field
        wp_insert_post($postData);
    }
}


/**
 * Migrate content in WP
 *
 * @return void
 */
function migrate_ucoz()
{
    // Import articles and categories
    // Import news and categories
    load_posts_and_post_categories();
    load_news_and_news_categories();
};

// run the migration script once, when you activate the plugin
register_activation_hook(__FILE__, 'migrate_ucoz');
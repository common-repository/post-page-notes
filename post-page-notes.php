<?php
/*
Plugin Name: Post &amp; Page Notes
Plugin URI: http://dev.raymonddesign.nl/wordpress-plugins/post-page-notes/
Description: Place your own text or message on every post or page. This is very useful when you want to place a header or footer on a post of page and you don&amp;#39;t want to edit the template. Now you can update the template while keeping your headers and footers.
Version: 0.2
Author: RaymondDesign
Author URI: http://www.raymonddesign.nl/
License: GNU GPL 2
*/

/*  Copyright 2010  RaymondDesign  (email : webmaster@raymonddesign.nl)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


error_reporting(E_ALL);
ini_set('diplay_errors', 1);
$wpdb->show_errors();

// We're going to use this vars in some functions
global $plugin_dbversion;
global $plugin_tables;

// Set some default vars
$plugin_name = 'Post &amp; Page Notes';
$plugin_nicename = 'ppnotes';
$plugin_version = '0.2';
$plugin_dbversion = '1.0';
$plugin_dir = basename(dirname(__FILE__));

// Create array with database names
$plugin_tables = array();
$plugin_tables['main'] = $wpdb->prefix.$plugin_nicename.'_notes';
$plugin_tables['data'] = $wpdb->prefix.$plugin_nicename.'_data';

// Install plugin after activation + load translations
register_activation_hook(__FILE__,'PPNotes_Install');
load_plugin_textdomain( 'post-page-notes', 'wp-content/plugins/' . $plugin_dir, $plugin_dir );



// Admin menu
function PPNotes_AdminLink()
{
	global $plugin_name;
    // Create link to admin page, menu: appearance
    add_theme_page($plugin_name, $plugin_name, 9, basename(__FILE__), 'PPNotes_AdminPage');
}



// Admin page
function PPNotes_AdminPage()
{
    global $wpdb, $plugin_name, $plugin_tables;
    
    $pagename = (isset($_GET['ppaction'])) ? $_GET['ppaction'] : '';
    switch($pagename){
        case 'new':
            $pagename = ' - '.__('Add New');
            $url = '&ppaction=new';
            break;
        case 'edit':
            $pagename = ' - '.__('Edit');
            $url = '&ppaction=edit&ppid='.$_GET['ppid'];
            break;
        default:
            $pagename = '';
            break;
    }
    
    $hide_overview = false;
    echo '<div class="wrap">
            <h2>'.$plugin_name.$pagename.'</h2>
            <div id="poststuff" class="metabox-holder">';
      
    if(isset($_GET['ppaction']))
    {
        if($_GET['ppaction'] == 'new' || $_GET['ppaction'] == 'edit')
        {
            $hide_overview = true;
            $nice_names = get_nicenames();
            
            if($_SERVER['REQUEST_METHOD'] == 'POST'){
                $_POST['draft'] = ($_POST['draft'] == 1) ? '1' : '0';
                if(!empty($_POST['name']) && isset($_POST['data']['show']) && isset($_POST['data']['pos'])){
                    if($_GET['ppaction'] == 'edit' && isset($_GET['ppid'])){
                        if(ctype_digit($_GET['ppid'])){
                            $id = $_GET['ppid'];
                            $wpdb->update($plugin_tables['main'], array('note_name' => $_POST['name'], 'note_text' => $_POST['content'], 'tags' => $_POST['tags'], 'draft' => $_POST['draft']), array('id' => $id), array('%s', '%s', '%s', '%s'), array('%d'));
                            $wpdb->query($wpdb->prepare("DELETE FROM ".$plugin_tables['data']." WHERE note_id = %d",$id));
                        }
                    }
                    else{
                        $wpdb->insert($plugin_tables['main'], array( 'note_name' => $_POST['name'], 'note_text' => $_POST['content'], 'tags' => $_POST['tags'], 'draft' => $_POST['draft'] ), array('%s', '%s', '%s', '%s'));
                        $id = $wpdb->insert_id;
                    }
                    $data_query = "INSERT INTO ".$plugin_tables['data']." (note_id, item_id, item_name) VALUES ";
                    $data_array = array();
                    $first = true;
                    foreach($_POST['data'] as $type => $items){
                        foreach($items as $item){
                            $sep = ($first == false) ? ',' : '';
                            $data_query .= $sep."(%d, %d, %s)";
                            $data_array[] = $id;
                            $data_array[] = $item;
                            $data_array[] = $type;
                            $first = false;
                        }
                    }
                    $wpdb->query($wpdb->prepare($data_query,$data_array));
                    
                    echo '<div id="message" class="updated fade"><p>'.__('The note is succesfully saved.').'</p></div>';
                    
                    $hide_overview = false;
                }
                else{
                    echo '<div id="message" class="error fade"><p><strong>'.__('At least, you need to fill in a name, where to show and the position of your note.').'</strong></p></div>';
                }
            }
            
            if($hide_overview == true)
            {
            
                $show_list = array();
                $pos_list = array();
                $cat_list = array();
                
                if($_GET['ppaction'] == 'edit' && isset($_GET['ppid']))
                {
                    if(ctype_digit($_GET['ppid']))
                    {
                        $note = $wpdb->get_row("SELECT id, note_name, note_text, tags, draft FROM ".$plugin_tables['main']." WHERE id = ".$wpdb->escape($_GET['ppid']));
                        $data = $wpdb->get_results("SELECT item_id, item_name FROM ".$plugin_tables['data']." WHERE note_id = ".$note->id);
                        $note->draft = ($note->draft == 1) ? ' selected="selected"' : '';
                        foreach($data as $item){
                            switch($item->item_name) {
                                case 'show':    $show_list[] = $item->item_id;  break;
                                case 'pos':     $pos_list[] = $item->item_id;   break;
                                case 'cat':     $cat_list[] = $item->item_id;   break;
                            }
                        }
                    }
                }
                else
                {
                    $note->draft = '';
                    $note->note_name = '';
                    $note->note_text = '';
                    $note->tags = '';
                }
                
                ShowTinyMCE();
                
                $select_list = $nice_names;
                foreach($select_list as $type => $items){
                    foreach($items as $id => $name){
                        if(!empty($name)){
                            switch($type){
                                case 'show':
                                    $checked = (in_array($id, $show_list)) ? ' checked="checked"' : '';
                                    break;
                                case 'pos':
                                    $checked = (in_array($id, $pos_list)) ? ' checked="checked"' : '';
                                    break;
                                case 'cat':
                                    $checked = (in_array($id, $cat_list)) ? ' checked="checked"' : '';
                                    break;
                            }
                            
                            $select_list[$type][$id] = '<label for="'.$type.'_'.$id.'"><input type="checkbox" name="data['.$type.'][]" id="'.$type.'_'.$id.'" value="'.$id.'"'.$checked.' /> '.$name.'</label>';
                        }else{
                            unset($select_list[$type][$id]);
                        }
                    }
                    $select_list[$type] = implode('<br />',$select_list[$type]);
                } 
    
                echo '<form method="post" action="'.$_SERVER["REQUEST_URI"].$url.'">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row"><strong>'.__('Name').'</strong><br /><small>'.__('Just for yourself, to recognize this note.').'</small></th>
                                <td><label for="name"><input type="text" name="name" value="'.$note->note_name.'" size="30" /></label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>'.__('Draft').'</strong><br /><small>'.__('When draft is set to yes, the note won&#39;t show up at your blog.').'</small></th>
                                <td><label for="draft"><select name="draft"><option value="0">No</option><option value="1"'.$note->draft.'>Yes</option></select></label>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>'.__('Show on').'</strong></th>
                                <td>'.$select_list['show'].'</td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>'.__('Position').'</strong><br /><small>'.__('The position on the post or page where you want to show the text.').'</small></th>
                                <td>'.$select_list['pos'].'</td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>'.__('Categories').'</strong><br /><small>'.__('This shows the text on every post or page in the given categories.').'</small></th>
                                <td>'.$select_list['cat'].'</td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>'.__('Tags').'</strong><br /><small>'.__('This shows the text on every post or page marked with the given tags.').'</small></th>
                                <td><input type="text" name="tags" value="'.$note->tags.'" size="30" autocomplete="off" /><br /><em>'.__('Separate with commas, no spaces').'</em></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><strong>'.__('Text').'</strong><br /><small>'.__('The text you want to show on the post or page.').'</small></th>
                                <td>';
                                the_editor($note->note_text);
                                echo '</td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="'.__('Save data').'" />
                        </p>
                    </form>';
            }
        }
        elseif($_GET['ppaction'] == 'del'){
            if(isset($_GET['ppid']))
            {
                if(ctype_digit($_GET['ppid']))
                {
                    $id = $_GET['ppid'];
                    $wpdb->query($wpdb->prepare("DELETE FROM ".$plugin_tables['main']." WHERE id = %d",$id));
                    $wpdb->query($wpdb->prepare("DELETE FROM ".$plugin_tables['data']." WHERE note_id = %d",$id));
                    echo '<div id="message" class="updated fade"><p>'.__('The note is succesfully deleted.').'</p></div>';
                }
            }
        }
    }
    if($hide_overview == false)
    {
        $nice_names = get_nicenames();
        
        $table_data = '';
        $notes = $wpdb->get_results("SELECT id, note_name, draft FROM ".$plugin_tables['main']." ORDER BY note_name");
        foreach($notes as $res)
        {
            $data = $wpdb->get_results("SELECT item_id, item_name FROM ".$plugin_tables['data']." WHERE note_id = ".$res->id);
            $items = array();
            foreach($data as $item){
                $items[$item->item_name][] = $nice_names[$item->item_name][$item->item_id];
            }
            foreach($items as $key => $value){
                $items[$key] = implode(', ', $items[$key]);
            }
            
            $res->draft = $nice_names['draft'][$res->draft];
            $table_data .= '<tr>
                                <td>'.$res->note_name.'
                                    <div class="row-actions"><span class="edit"><a title="'.__('Edit this note').'" href="'.$_SERVER["REQUEST_URI"].'&ppaction=edit&ppid='.$res->id.'">'.__('Edit').'</a> | </span><span class="delete"><a href="'.$_SERVER["REQUEST_URI"].'&ppaction=del&ppid='.$res->id.'" title="'.__('Delete this note').'">'.__('Delete').'</a></span></div>
                                </td>
                                <td>'.$items['show'].'</td>
                                <td>'.$items['pos'].'</td>
                                <td>'.$res->draft.'</td>
                            </tr>';
        }
        
        echo '<p><a href="'.$_SERVER["REQUEST_URI"].'&ppaction=new" class="button add-new-h2">'.__('Add New').'</a><a href="'.$_SERVER["REQUEST_URI"].'&ppaction=uninstall" class="button add-new-h2">'.__('Uninstall').'</a></p>
            <table class="widefat post fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th scope="col">'.__('Name').'</th>
                    	<th scope="col">'.__('Visible on').'</th>
                    	<th scope="col">'.__('Position').'</th>
                        <th scope="col">'.__('Draft').'</th>
                	</tr>
               	</thead>
                <tfoot>
                	<tr>
                        <th scope="col">'.__('Name').'</th>
                    	<th scope="col">'.__('Visible on').'</th>
                    	<th scope="col">'.__('Position').'</th>
                        <th scope="col">'.__('Draft').'</th>
                	</tr>
            	</tfoot>
                <tbody>
                    '.$table_data.'
                </tbody>
            </table>
            <br /><br />
            <div class="stuffbox">
                <h3>'.__('About').'</h3>
                <div class="inside">
                    <p>'.__('Plugin author').': <a href="http://www.raymonddesign.nl">RaymondDesign</a><br />
                    '.__('Plugin Homepage').': <a href="http://blog.raymonddesign.nl/post-page-notes/">RaymondDesign Blog</a><br /><br />
                    '.__('Thanks for using this plugin.').'<br />'.
                        sprintf(__('I like to get some feedback on this plugin, you can visit the %splugin page%s and write a comment.  Also when you have a question or suggestion you can visit that page.'),'<a href="http://blog.raymonddesign.nl/post-page-notes/">','</a>').'
                    </p>
                </div>
            </div>';
    }
    
echo ' </div>
    </div>';
}



// Install after activation
function PPNotes_Install()
{
    global $wpdb, $plugin_dbversion, $plugin_tables;
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Due some strange the prefix is lost in this function, so set it again
    $plugin_tables['main'] = $wpdb->prefix.$plugin_tables['main'];
    $plugin_tables['data'] = $wpdb->prefix.$plugin_tables['data'];
    
    // Check if database tables exists and create them if necessary
    if($wpdb->get_var("SHOW TABLES LIKE '".$plugin_tables['main']."'") != $plugin_tables['main']) {
        $sql = "CREATE TABLE ".$plugin_tables['main']." (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    note_name varchar(255) NOT NULL,
                    note_text text NOT NULL,
                    tags text,
                    draft enum('0','1') NOT NULL,
                    PRIMARY KEY  (id)
                )";
        
        dbDelta($sql);
    }
    if($wpdb->get_var("SHOW TABLES LIKE '".$plugin_tables['data']."'") != $plugin_tables['data']) {
        $sql = "CREATE TABLE ".$plugin_tables['data']." (
                  id int(11) NOT NULL AUTO_INCREMENT,
                  note_id int(11) NOT NULL,
                  item_id int(11) NOT NULL,
                  item_name enum('show','pos','cat') NOT NULL,
                  PRIMARY KEY  (id)
                )";
        dbDelta($sql);
    }
    
    // Check database version of current ppNotes
    $cur_dbversion = get_option('ppnotes_db_version');
    // If no databse version set, try to import data from old version
    if(empty($cur_dbversion)){
        $data = array('ppnote_header' => '', 'ppnote_header_show', 'ppnote_footer' => '', 'ppnote_footer_show');
        $data = get_option('ppnote_options');
        if(!empty($data['ppnote_header'])){
            $insert = "INSERT INTO ".$plugin_tables['main']." (note_name, note_text, draft) VALUES (%s, %s, '0')";
            $results = $wpdb->query($wpdb->prepare($insert,__('Former Header Note'),stripslashes($data['ppnote_header'])));
            switch($data['ppnote_header_show']){
                case 'post': $show = 1; break;
                case 'page': $show = 2; break;
                case 'postpage': $show = 1; break;
            }
            $insert = "INSERT INTO ".$plugin_tables['data']." (note_id, item_id, item_name) VALUES (%d, %d,'show'),(%d, 2, 'pos'),(%d, 0, 'cat')";
            $results = $wpdb->query($wpdb->prepare($insert, $wpdb->insert_id, $show, $wpdb->insert_id, $wpdb->insert_id));
        }
        if(!empty($data['ppnote_footer'])){
            $insert = "INSERT INTO ".$plugin_tables['main']." (note_name, note_text, draft) VALUES (%s, %s, '0')";
            $results = $wpdb->query($wpdb->prepare($insert, __('Former Footer Note'), stripslashes($data['ppnote_footer'])));
            switch($data['ppnote_footer_show']){
                case 'post': $show = 1; break;
                case 'page': $show = 2; break;
                case 'postpage': $show = 1; break;
            }
            $insert = "INSERT INTO ".$plugin_tables['data']." (note_id, item_id, item_name) VALUES (%d, %d,'show'),(%d, 3,'pos'),(%d, 0,'cat')";
            $results = $wpdb->query($wpdb->prepare($insert, $wpdb->insert_id, $show, $wpdb->insert_id, $wpdb->insert_id));
        }
        // Delete old version data, we don't need it anymore
        delete_option('ppnote_options');
    }
    // Set the current database version, we are up-to-date! :)
    update_option('ppnotes_db_version', $plugin_dbversion);
}



// Show the messages/notes
function PPNotes_WriteNotes($text)
{
    
    return $text;
	
}



// Some functions we use in this plugin
/**
 * Function get_nicenames
 * Return: array with numbers converted to names
 */
    function get_nicenames()
    {
        $nice_names = array(
                'show' => array('', __('Posts'), __('Pages'), __('Homepage'), __('Archives &amp; Search Results')),
                'pos' => array('', __('Above title'), __('Between title and content'), __('Below content')),
                'cat' => array(__('All')),
                'draft' => array(__('No'), __('Yes'))
        );
        
        $categories = get_categories('hide_empty=0'); 
        foreach ($categories as $cat)
        {
            $nice_names['cat'][$cat->term_id] = $cat->cat_name;
        }
        return $nice_names;
    }
    
/**
 * Function ShowTinyMCE
 * Loads the data to show to Wordpress TinyMCE editor
 * Return: true
 * Author: Anthony (http://blog.zen-dreams.com/en/2009/06/30/integrate-tinymce-into-your-wordpress-plugins/)
 */
    function ShowTinyMCE() {
        wp_enqueue_script( 'common' );
        wp_enqueue_script( 'jquery-color' );
        wp_print_scripts('editor');
        if (function_exists('add_thickbox')) add_thickbox();
        wp_print_scripts('media-upload');
        if (function_exists('wp_tiny_mce')) wp_tiny_mce();
        wp_admin_css();
        wp_enqueue_script('utils');
        do_action("admin_print_styles-post-php");
        do_action('admin_print_styles');
        return true;
    }
    

add_action('admin_menu', 'PPNotes_AdminLink');
add_action('the_content', 'PPNotes_WriteNotes',0);
//add_filter('admin_head','ShowTinyMCE'); //Enable only if you have problems with TinyMCE

?>
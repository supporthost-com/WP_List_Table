<?php

/*
Plugin Name:  SupportHost Admin Table
Description: It displays a table with custom data
Author: SupportHost
Author URI: https://supporthost.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: supporthost-admin-table
Version: 1.0
*/

// Loading WP_List_Table class file
// We need to load it as it's not automatically loaded by WordPress
if (!class_exists('WP_List_Table')) {
      require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

// Extending class
class Supporthost_List_Table extends WP_List_Table
{
    // Here we will add our code

    // define $table_data property
    private $table_data;

    // Get table data
    private function get_table_data( $search = '' ) {
        global $wpdb;

        $table = $wpdb->prefix . 'supporthost_custom_table';

        if ( !empty($search) ) {
            return $wpdb->get_results(
                "SELECT * from {$table} WHERE name Like '%{$search}%' OR description Like '%{$search}%' OR status Like '%{$search}%'",
                ARRAY_A
            );
        } else {
            return $wpdb->get_results(
                "SELECT * from {$table}",
                ARRAY_A
            );
        }
    }

    // Define table columns
    function get_columns()
    {
        $columns = array(
                'cb'            => '<input type="checkbox" />',
                'name'          => __('Name', 'supporthost-admin-table'),
                'description'         => __('Description', 'supporthost-admin-table'),
                'status'   => __('Status', 'supporthost-admin-table'),
                'order'        => __('Order', 'supporthost-admin-table')
        );
        return $columns;
    }

    // Bind table with columns, data and all
    function prepare_items()
    {
        //data
        if ( isset($_POST['s']) ) {
            $this->table_data = $this->get_table_data($_POST['s']);
        } else {
            $this->table_data = $this->get_table_data();
        }

        $columns = $this->get_columns();
        $hidden = ( is_array(get_user_meta( get_current_user_id(), 'managetoplevel_page_supporthost_list_tablecolumnshidden', true)) ) ? get_user_meta( get_current_user_id(), 'managetoplevel_page_supporthost_list_tablecolumnshidden', true) : array();
        $sortable = $this->get_sortable_columns();
        $primary  = 'name';
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

        usort($this->table_data, array(&$this, 'usort_reorder'));

        /* pagination */
        $per_page = $this->get_items_per_page('elements_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = count($this->table_data);

        $this->table_data = array_slice($this->table_data, (($current_page - 1) * $per_page), $per_page);

        $this->set_pagination_args(array(
                'total_items' => $total_items, // total number of items
                'per_page'    => $per_page, // items to show on a page
                'total_pages' => ceil( $total_items / $per_page ) // use ceil to round up
        ));
        
        $this->items = $this->table_data;
    }

    // set value for each column
    function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'id':
            case 'name':
            case 'description':
            case 'status':
            case 'order':
            default:
                return $item[$column_name];
        }
    }

    // Add a checkbox in the first column
    function column_cb($item)
    {
        return sprintf(
                '<input type="checkbox" name="element[]" value="%s" />',
                $item['id']
        );
    }

    // Define sortable column
    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'name'  => array('name', false),
            'status' => array('status', false),
            'order'   => array('order', true)
        );
        return $sortable_columns;
    }

    // Sorting function
    function usort_reorder($a, $b)
    {
        // If no sort, default to user_login
        $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'user_login';

        // If no order, default to asc
        $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';

        // Determine sort order
        $result = strcmp($a[$orderby], $b[$orderby]);

        // Send final sort direction to usort
        return ($order === 'asc') ? $result : -$result;
    }

    // Adding action links to column
    function column_name($item)
    {
        $actions = array(
                'edit'      => sprintf('<a href="?page=%s&action=%s&element=%s">' . __('Edit', 'supporthost-admin-table') . '</a>', $_REQUEST['page'], 'edit', $item['ID']),
                'delete'    => sprintf('<a href="?page=%s&action=%s&element=%s">' . __('Delete', 'supporthost-admin-table') . '</a>', $_REQUEST['page'], 'delete', $item['ID']),
        );

        return sprintf('%1$s %2$s', $item['name'], $this->row_actions($actions));
    }

    // To show bulk action dropdown
    function get_bulk_actions()
    {
            $actions = array(
                    'delete_all'    => __('Delete', 'supporthost-admin-table'),
                    'draft_all' => __('Move to Draft', 'supporthost-admin-table')
            );
            return $actions;
    }

}

// Adding menu
function my_add_menu_items() {
 
	global $supporthost_sample_page;
 
	// add settings page
	$supporthost_sample_page = add_menu_page(__('SupportHost List Table', 'supporthost-admin-table'), __('SupportHost List Table', 'supporthost-admin-table'), 'manage_options', 'supporthost_list_table', 'supporthost_list_init');
 
	add_action("load-$supporthost_sample_page", "supporthost_sample_screen_options");
}
add_action('admin_menu', 'my_add_menu_items');

// add screen options
function supporthost_sample_screen_options() {
 
	global $supporthost_sample_page;
    global $table;
 
	$screen = get_current_screen();
 
	// get out of here if we are not on our settings page
	if(!is_object($screen) || $screen->id != $supporthost_sample_page)
		return;
 
	$args = array(
		'label' => __('Elements per page', 'supporthost-admin-table'),
		'default' => 2,
		'option' => 'elements_per_page'
	);
	add_screen_option( 'per_page', $args );

    $table = new Supporthost_List_Table();

}

add_filter('set-screen-option', 'test_table_set_option', 10, 3);
function test_table_set_option($status, $option, $value) {
  return $value;
}


// Plugin menu callback function
function supporthost_list_init()
{
      // Creating an instance
      $table = new Supporthost_List_Table();

      echo '<div class="wrap"><h2>SupportHost Admin Table</h2>';
      echo '<form method="post">';
      // Prepare table
      $table->prepare_items();
      // Search form
      $table->search_box('search', 'search_id');
      // Display table
      $table->display();
      echo '</div></form>';
}

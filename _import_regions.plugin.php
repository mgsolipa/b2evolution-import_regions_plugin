<?php

if (!defined('EVO_MAIN_INIT'))
    die('Please, do not access this page directly.');

class import_regions_plugin extends Plugin {

    /**
     * Variables below MUST be overriden by plugin implementations,
     * either in the subclass declaration or in the subclass constructor.
     */
    var $name = 'Import Regional Data';
    var $code = 'impreg';
    var $priority = 50;
    var $version = '0.1.1-dev';
    var $author = '';
    var $help_url = '';
    var $group = '';
    var $number_of_installs = 1;

    /**
     * Init: This gets called after a plugin has been registered/instantiated.
     */
    function PluginInit(& $params) {
        $this->short_desc = $this->T_('Import regional data');
        $this->long_desc = $this->T_('Let you import regional data into the b2evolution\'s database.');
    }

    function is_valid($value) {
        if (isset($value) && trim($value) != "" && trim(strtolower($value)) != "null") {
            return true;
        } else {
            return false;
        }
    }

    function i_or_u_tables($f_arr_table, $t_arr_table, $search_arr, $type, $i_table_titles, $table_name) {
        global $DB, $Messages;
        // Make the Rgns Insert or Update sqls
        $u_arr_sql = array_intersect($f_arr_table, $t_arr_table);
        $i_arr_sql = array_diff($f_arr_table, $t_arr_table);

        if (count($u_arr_sql) > 0) {
            if ($type == "Regions") {
                foreach ($u_arr_sql as $v) {
                    $update_values[] = "rgn_ctry_ID = " . $search_arr['f_arr_rgn_ctry'][$v] . ", rgn_code = '" . $search_arr['f_arr_rgn_code'][$v] . "'
					WHERE rgn_name = '" . $v . "';";
                }
            } else if ($type == "Sub-regions") {
                foreach ($u_arr_sql as $v) {
                    $update_values[] = "subrg_rgn_ID = " . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$v]] . ", subrg_code = '" . $search_arr['f_arr_subrg_code'][$v] . "'
					WHERE subrg_name = '" . $v . "';";
                }
            } else if ($type == "Cities") {
                foreach ($u_arr_sql as $v) {
                    $update_values[] = "city_ctry_ID = " . $search_arr['f_arr_rgn_ctry'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] .
                            ", city_rgn_ID = " . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] .
                            ", city_subrg_ID = " . $search_arr['t_arr_subrg_name_id'][$search_arr['f_arr_city_subrg'][$v]] .
                            ", city_postcode = " . $search_arr['f_arr_city_postcode'][$v] .
                            "  WHERE city_name = '" . $v . "';";
                }
            }
        }
        if (count($i_arr_sql) > 0) {
            if ($type == "Regions") {
                foreach ($i_arr_sql as $v) {
                    $insert_values[] = "('" . $search_arr['f_arr_rgn_ctry'][$v] . "', '" . $search_arr['f_arr_rgn_code'][$v] . "','" . $v . "')";
                }
            } else if ($type == "Sub-regions") {
                foreach ($i_arr_sql as $v) {
                    $insert_values[] = "('" . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$v]] . "', '" . $search_arr['f_arr_subrg_code'][$v] . "','" . $v . "')";
                }
            } else if ($type == "Cities") {
                foreach ($i_arr_sql as $v) {
                    $insert_values[] = "('" . $search_arr['f_arr_rgn_ctry'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] . "'"
                            . ", " . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] . ""
                            . ", " . $search_arr['t_arr_subrg_name_id'][$search_arr['f_arr_city_subrg'][$v]] . ""
                            . ", " . $search_arr['f_arr_city_postcode'][$v] . ""
                            . ",'" . $v . "')";
                }
            }
        }
        $count_insert = isset($insert_values) ? count($insert_values) : 0;
        $count_update = isset($update_values) ? count($update_values) : 0;

        if ($count_insert > 0) {
            $insert_values = array_chunk($insert_values, 1000);
            foreach ($insert_values as $v) {
                $v = implode(', ', $v);
                $DB->query('INSERT INTO ' . $table_name . '
				( ' . $i_table_titles . ' )
				VALUES  ' . $v);
            }
        }

        if ($count_update > 0) {
            foreach ($update_values as $v) {
                $DB->query('UPDATE ' . $table_name . '
				SET ' . $v);
            }
        }

        $Messages->add(sprintf(T_('%s ' . $type . ' updated , %s ' . $type . ' added.'), $count_update, $count_insert), 'success');
    }

    function import_regional_data($file_name) {
        global $DB, $Messages;
        // Begin transaction
        $DB->begin();
        $r_ctry = $DB->get_results('
		SELECT ctry_ID, ctry_code
		  FROM T_regional__country;');
        // Fetch Countries code->id list
        $t_arr_ctry = array();
        foreach ($r_ctry as $v) {
            $t_arr_ctry[$v->ctry_code] = $v->ctry_ID;
        }
        unset($r_ctry);

        $f_arr_rgn = $f_arr_subrg = $f_arr_city = array();
        $f_arr_rgn_code = $f_arr_subrg_code = $f_arr_city_postcode = array();
        $f_arr_rgn_ctry = $f_arr_subrg_rgn = $f_arr_city_subrg = array();

        $file_handle = fopen($file_name, 'r');
        $ctry_ID = $ctry_code = NULL;
        $v_rgn = $v_rgn_code = $v_subrg = $v_subrg_code = $v_city = $v_postcode = NULL;
        $c = 0;
        while ($data = fgetcsv($file_handle, 1024, ",")) {
            $c++;
            // To see more clearly  
            $ctry_code = isset($data[0]) ? strtolower(trim($data[0], " \xA0")) : NULL;
            $ctry_ID = isset($t_arr_ctry[$ctry_code]) ? $t_arr_ctry[$ctry_code] : NULL;
            $v_rgn = isset($data[1]) ? trim($data[1], " \xA0") : NULL;
            $v_rgn_code = isset($data[2]) ? trim($data[2], " \xA0") : NULL;
            $v_subrg = isset($data[3]) ? trim($data[3], " \xA0") : NULL;
            $v_subrg_code = isset($data[4]) ? trim($data[4], " \xA0") : NULL;
            $v_city = isset($data[5]) ? trim($data[5], " \xA0") : NULL;
            $v_postcode = isset($data[6]) ? trim($data[6], " \xA0") : "";

            if ($c == 1) { // Skip first row with titles
                continue;
            }
            if (!$ctry_ID) {
                // Skip empty row
                $Messages->add(sprintf(T_('Warnning: No such country "%s" at line <b>%s</b>'), $ctry_code, $c));
                continue;
            }

            if (!in_array($v_rgn, $f_arr_rgn) && $this->is_valid($v_rgn)) {
                $f_arr_rgn[] = $v_rgn;
                $f_arr_rgn_code[$v_rgn] = $v_rgn_code;
                $f_arr_rgn_ctry[$v_rgn] = $ctry_ID;
            } 
            if (!in_array($v_subrg, $f_arr_subrg) && $this->is_valid($v_subrg) && $this->is_valid($v_rgn)) {
                $f_arr_subrg[] = $v_subrg;
                $f_arr_subrg_code[$v_subrg] = $v_subrg_code;
                $f_arr_subrg_rgn[$v_subrg] = $v_rgn;
            } elseif($this->is_valid($v_subrg) && !$this->is_valid($v_rgn)){
                $Messages->add(sprintf(T_('Warnning: Invalid Sub-region (NO Region) at line <b>%s</b>'), $c));
            }
            if (!in_array($v_city, $f_arr_city) && $this->is_valid($v_city) && $this->is_valid($v_subrg) && $this->is_valid($v_rgn)) {
                $f_arr_city[] = $v_city;
                $f_arr_city_postcode[$v_city] = $v_postcode;
                $f_arr_city_subrg[$v_city] = $v_subrg;
            } elseif($this->is_valid($v_city) && (!$this->is_valid($v_rgn) || !$this->is_valid($v_subrg))){
                $Messages->add(sprintf(T_('Warnning: Invalid City (NO Region or Sub-region) at line <b>%s</b>'), $c));
            }
        }

        // Fetch the rgn info from database
        $t_arr_rgn = array();
        $r_rgn = $DB->get_results('SELECT rgn_name FROM `T_regional__region`');
        foreach ($r_rgn as $v) {
            $t_arr_rgn[] = $v->rgn_name;
        }
        unset($r_rgn);

        $search_arr = array();
        $search_arr['f_arr_rgn_ctry'] = $f_arr_rgn_ctry;
        $search_arr['f_arr_rgn_code'] = $f_arr_rgn_code;
        $this->i_or_u_tables($f_arr_rgn, $t_arr_rgn, $search_arr, "Regions", "rgn_ctry_ID, rgn_code, rgn_name", "T_regional__region");

        // Fetch the rgn info from database twice
        $t_arr_rgn_name_id = array();
        $r_rgn = $DB->get_results('SELECT rgn_ID, rgn_name FROM `T_regional__region`');
        foreach ($r_rgn as $v) {
            $t_arr_rgn_name_id[$v->rgn_name] = $v->rgn_ID;
        }
        unset($r_rgn);

        // Fetch the subrg info from database
        $t_arr_subrg = array();
        $r_subrg = $DB->get_results('SELECT subrg_name FROM `T_regional__subregion`');
        foreach ($r_subrg as $v) {
            $t_arr_subrg[] = $v->subrg_name;
        }
        unset($r_subrg);

        $search_arr['t_arr_rgn_name_id'] = $t_arr_rgn_name_id;
        $search_arr['f_arr_subrg_rgn'] = $f_arr_subrg_rgn;
        $search_arr['f_arr_subrg_code'] = $f_arr_subrg_code;
        $this->i_or_u_tables($f_arr_subrg, $t_arr_subrg, $search_arr, "Sub-regions", "subrg_rgn_ID, subrg_code, subrg_name", "T_regional__subregion");

        // Fetch the subrg info from database
        $t_arr_subrg_name_id = array();
        $r_subrg = $DB->get_results('SELECT subrg_ID, subrg_name FROM `T_regional__subregion`');
        foreach ($r_subrg as $v) {
            $t_arr_subrg_name_id[$v->subrg_name] = $v->subrg_ID;
        }
        unset($r_subrg);

        // Fetch the city info from database
        $t_arr_city = array();
        $r_city = $DB->get_results('SELECT city_name FROM `T_regional__city`');
        foreach ($r_city as $v) {
            $t_arr_city[] = $v->city_name;
        }
        unset($r_city);

        $search_arr['t_arr_subrg_name_id'] = $t_arr_subrg_name_id;
        $search_arr['f_arr_city_subrg'] = $f_arr_city_subrg;
        $search_arr['f_arr_city_postcode'] = $f_arr_city_postcode;

        $this->i_or_u_tables($f_arr_city, $t_arr_city, $search_arr, "Cities", "city_ctry_ID, city_rgn_ID,city_subrg_ID,city_postcode,city_name", "T_regional__city");

        // Commit transaction
        $DB->commit();
    }

    /**
     * Event handler: Called when displaying the block in the "Tools" menu.
     *
     * @see Plugin::AdminToolPayload()
     */
    function AdminToolPayload() {
        $action = param_action();

        switch ($action) {
            default:
                $Form = new Form(NULL, 'import_regions', 'post', 'compact', 'multipart/form-data');
                $Form->begin_form('fform');
                echo T_('Please upload a CSV file with the following columns:');
                echo '<div style="padding:10px 0 10px 40px">';
                echo T_('1. Country code. Eg:au') . '<br />';
                echo T_('2. Region name. ') . '<br />';
                echo T_('3. Region code. ') . '<br />';
                echo T_('4. Sub-region name. ') . '<br />';
                echo T_('5. Sub-region code. ') . '<br />';
                echo T_('6. City name. ') . '<br />';
                echo T_('7. Postcode. ') . '<br />';
                echo '</div>';
                $Form->add_crumb('tools');
                $Form->hidden_ctrl(); // needed to pass the "ctrl=tools" param
                $Form->hiddens_by_key(get_memorized()); // needed to pass all other memorized params, especially "tab"
                $Form->hidden('action', 'import_regional_data');
                $Form->input_field(array('label' => T_('Source file'), 'note' => T_('CSV file to be imported'), 'name' => 'csv', 'type' => 'file'));
                $Form->input_field(array('value' => T_('Import regional data'), 'name' => 'submit', 'type' => 'submit'));
                $Form->end_form();
                break;
        }
    }

    /**
     * Event handler: Called when handling actions for the "Tools" menu.
     *
     * Use {@link $Messages} to add Messages for the user.
     *
     * @see Plugin::AdminToolAction()
     */
    function AdminToolAction() {
        $action = param_action();
        if (!empty($action)) {    // If form is submitted
            global $DB;
            global $Messages;
            global $Session;
            global $current_User;
            switch ($action) {
                case 'import_regional_data':
                    // Check that this action request is not a CSRF hacked request:
                    $Session->assert_received_crumb('tools');

                    // Check permission:
                    $current_User->check_perm('options', 'edit', true);
                    $csv = $_FILES['csv'];
                    if ($csv['size'] == 0) { // File is empty
                        $Messages->add(T_('Please select a CSV file to import.'), 'error');
                    } else if (!preg_match('/\.csv$/i', $csv['name'])) { // Extension is incorrect
                        $Messages->add(sprintf(T_('&laquo;%s&raquo; has an unrecognized extension.'), $csv['name']), 'error');
                    }
                    $this->import_regional_data($csv['tmp_name']);
                    break;
            }
        }
    }

}

?>

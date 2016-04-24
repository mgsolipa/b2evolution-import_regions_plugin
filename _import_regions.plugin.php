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
	var $version = '0.1-dev';
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

	/**
	 * If the value of CSV is valid
	 * 
	 * @param string $value  The value of the csv file
	 * @return boolean
	 */
	function is_valid($value) {
		if (isset($value) && trim($value) != "" && trim(strtolower($value)) != "null") {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Construct the Inset and Update SQL through the value of CSV 
	 * 
	 * @param array $f_arr_table  Values from CSV
	 * @param array $t_arr_table  Values from database
	 * @param array $search_arr  Relation map for changing from code to id (or get the parent info)
	 * @param string $type  Distinguish the Region Sub-region and City to construct corresponding SQL statement
	 * @param string  $i_table_fields  The table fields of Region or Sub-region or City
	 * @param string $table_name  The table name of Region or Sub-region or City
	 */
	function i_or_u_tables($f_arr_table, $t_arr_table, $search_arr, $type, $i_table_fields, $table_name) {
		global $DB, $Messages;
		// Construct Insert and Update array
		$u_arr_table = array_intersect($f_arr_table, $t_arr_table); //intersection values for update
		$i_arr_table = array_diff($f_arr_table, $t_arr_table); // difference set values for insert

		if (count($u_arr_table) > 0) {
			if ($type == "Regions") { // update the values of region
				foreach ($u_arr_table as $v) {
					$update_values[] = "rgn_ctry_ID = " . $search_arr['f_arr_rgn_ctry'][$v] . ", rgn_code = '" . addslashes($search_arr['f_arr_rgn_code'][$v]) . "'
					WHERE rgn_ID = " . $search_arr['t_arr_rgn_name_id'][$v] . ";";
				}
			} else if ($type == "Sub-regions") { // update the values of sub-region
				foreach ($u_arr_table as $v) {
					$update_values[] = "subrg_rgn_ID = " . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$v]] . ", subrg_code = '" . addslashes($search_arr['f_arr_subrg_code'][$v]) . "'
					WHERE subrg_ID = " . $search_arr['t_arr_subrg_name_id'][$v] . ";";
				}
			} else if ($type == "Cities") { // update the values of city
				foreach ($u_arr_table as $v) {
					$update_values[] = "city_ctry_ID = " . $search_arr['f_arr_rgn_ctry'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] . ", "
						. "  city_rgn_ID = " . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] . ", "
						. "  city_subrg_ID = " . $search_arr['t_arr_subrg_name_id'][$search_arr['f_arr_city_subrg'][$v]] . ", "
						. "  city_postcode = '" . addslashes($search_arr['f_arr_city_postcode'][$v]) . "' "
						. "  WHERE city_ID = " . $search_arr['t_arr_city_name_id'][$v] . ";";
				}
			}
		}
		if (count($i_arr_table) > 0) {
			if ($type == "Regions") { // insert the values of region
				foreach ($i_arr_table as $v) {
					$insert_values[] = "(" . $search_arr['f_arr_rgn_ctry'][$v] . ", '" . addslashes($search_arr['f_arr_rgn_code'][$v]) . "','" . addslashes($v) . "')";
				}
			} else if ($type == "Sub-regions") { // insert the values of sub-regions
				foreach ($i_arr_table as $v) {
					$insert_values[] = "(" . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$v]] . ", '" . addslashes($search_arr['f_arr_subrg_code'][$v]) . "','" . addslashes($v) . "')";
				}
			} else if ($type == "Cities") { // insert the values of city
				foreach ($i_arr_table as $v) {
					$insert_values[] = "(" . $search_arr['f_arr_rgn_ctry'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] . ""
						. ", " . $search_arr['t_arr_rgn_name_id'][$search_arr['f_arr_subrg_rgn'][$search_arr['f_arr_city_subrg'][$v]]] . ""
						. ", " . $search_arr['t_arr_subrg_name_id'][$search_arr['f_arr_city_subrg'][$v]] . ""
						. ", '" . addslashes($search_arr['f_arr_city_postcode'][$v]) . "'"
						. ",'" . addslashes($v) . "')";
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
				( ' . $i_table_fields . ' )
				VALUES  ' . $v);
			}
		}

		if ($count_update > 0) {
			foreach ($update_values as $v) {
				$DB->query('UPDATE ' . $table_name . '
				SET ' . $v);
			}
		}
		unset($insert_values);
		unset($update_values);

		$Messages->add(sprintf(T_('%s ' . $type . ' updated , %s ' . $type . ' added.'), $count_update, $count_insert), 'success');
	}

	/**
	 * The whole process of importing CSV file data
	 * 
	 * @global object $DB
	 * @global object $Messages
	 * @param string $file_name  CSV file location
	 */
	function import_regional_data($file_name, $separate_mark) {
		global $DB, $Messages;
		// Begin transaction
		$DB->begin();

		$r_ctry = $DB->get_results('SELECT ctry_ID, ctry_code
						FROM T_regional__country;');

		// Fetch Countries code->id mapping array
		$t_arr_ctry = array();
		foreach ($r_ctry as $v) {
			$t_arr_ctry[$v->ctry_code] = $v->ctry_ID;
		}
		unset($r_ctry);

		// Initialize storage and mapping array
		$f_arr_rgn = $f_arr_subrg = $f_arr_city = array();
		$f_arr_rgn_code = $f_arr_subrg_code = $f_arr_city_postcode = array();
		$f_arr_rgn_ctry = $f_arr_subrg_rgn = $f_arr_city_subrg = array();

		$file_handle = fopen($file_name, 'r');

		// Initialize some temp variables for getting and putting value
		$ctry_ID = $ctry_code = NULL;
		$v_rgn = $v_rgn_code = $v_subrg = $v_subrg_code = $v_city = $v_postcode = NULL;
		$c = 0;
		while ($data = fgetcsv($file_handle, 1024, $separate_mark)) {
			$c++;

			// Turn data[$i] to a temporary named variable as seeing more clearly  
			$ctry_code = isset($data[0]) ? strtolower(trim($data[0], " \xA0")) : NULL;
			$ctry_ID = isset($t_arr_ctry[$ctry_code]) ? $t_arr_ctry[$ctry_code] : NULL;
			$v_rgn = isset($data[1]) ? trim(stripslashes($data[1]), " \xA0") : NULL;
			$v_rgn_code = isset($data[2]) ? trim(stripslashes($data[2]), " \xA0") : NULL;
			$v_subrg = isset($data[3]) ? trim(stripslashes($data[3]), " \xA0") : NULL;
			$v_subrg_code = isset($data[4]) ? trim(stripslashes($data[4]), " \xA0") : NULL;
			$v_city = isset($data[5]) ? trim(stripslashes($data[5]), " \xA0") : NULL;
			$v_postcode = isset($data[6]) ? trim(stripslashes($data[6]), " \xA0") : "";

			if ($c == 1) { // Skip first row with titles
				continue;
			}
			if (!$ctry_ID) {
				// Skip empty row
				$Messages->add(sprintf(T_('Warnning: No such country <b>%s</b> at line <b>%s</b>'), $ctry_code, $c));
				continue;
			}

			// Storage regions and mapping with countries
			if (!in_array($v_rgn, $f_arr_rgn) && $this->is_valid($v_rgn)) {
				$f_arr_rgn[] = $v_rgn;
				$f_arr_rgn_code[$v_rgn] = $v_rgn_code;
				$f_arr_rgn_ctry[$v_rgn] = $ctry_ID;
			}
			//Storage sub-regions and mapping with regions
			if (!in_array($v_subrg, $f_arr_subrg) && $this->is_valid($v_subrg) && $this->is_valid($v_rgn)) {
				$f_arr_subrg[] = $v_subrg;
				$f_arr_subrg_code[$v_subrg] = $v_subrg_code;
				$f_arr_subrg_rgn[$v_subrg] = $v_rgn;
			} elseif ($this->is_valid($v_subrg) && !$this->is_valid($v_rgn)) { // Give warnning info for a existing sub-region but no related region
				$Messages->add(sprintf(T_('Warnning: Invalid Sub-region <b>%s</b> (NO Region) at line <b>%s</b>'), $v_subrg, $c));
			}
			//Storage cities and mapping with sub-regions
			if (!in_array($v_city, $f_arr_city) && $this->is_valid($v_city) && $this->is_valid($v_subrg) && $this->is_valid($v_rgn)) {
				$f_arr_city[] = $v_city;
				$f_arr_city_postcode[$v_city] = $v_postcode;
				$f_arr_city_subrg[$v_city] = $v_subrg;
			} elseif ($this->is_valid($v_city) && (!$this->is_valid($v_rgn) || !$this->is_valid($v_subrg))) { // Give warnning info for a existing sub-region but no related region or sub-region
				$Messages->add(sprintf(T_('Warnning: Invalid City <b>%s</b> (NO Region or Sub-region) at line <b>%s</b>'), $v_city, $c));
			}
		}

		// Fetch the regions info from database
		$t_arr_rgn = array();
		$r_rgn = $DB->get_results('SELECT rgn_ID, rgn_name FROM `T_regional__region`');
		foreach ($r_rgn as $v) {
			$t_arr_rgn[$v->rgn_ID] = $v->rgn_name;
		}
		unset($r_rgn);

		// Construct the mapping parameter and transmit it to the function i_or_u_tables() for insert or update regions
		$search_arr = array();
		$search_arr['f_arr_rgn_ctry'] = $f_arr_rgn_ctry;
		$search_arr['f_arr_rgn_code'] = $f_arr_rgn_code;
		$search_arr['t_arr_rgn_name_id'] = array_flip($t_arr_rgn);

		$this->i_or_u_tables($f_arr_rgn, $t_arr_rgn, $search_arr, "Regions", "rgn_ctry_ID, rgn_code, rgn_name", "T_regional__region");

		unset($t_arr_rgn);
		unset($f_arr_rgn_ctry);
		unset($f_arr_rgn_code);
		unset($f_arr_rgn);

		// Fetch the regions info from database twice for getting the new region_IDs which just inserted from previous step
		$t_arr_rgn_name_id = array();
		$r_rgn = $DB->get_results('SELECT rgn_ID, rgn_name FROM `T_regional__region`');
		foreach ($r_rgn as $v) {
			$t_arr_rgn_name_id[$v->rgn_name] = $v->rgn_ID;
		}
		unset($r_rgn);

		// Fetch the sub-regions info from database
		$t_arr_subrg = array();
		$r_subrg = $DB->get_results('SELECT subrg_ID, subrg_name FROM `T_regional__subregion`');
		foreach ($r_subrg as $v) {
			$t_arr_subrg[$v->subrg_ID] = $v->subrg_name;
		}
		unset($r_subrg);

		// Construct the mapping parameter and transmit it to the function i_or_u_tables() for insert or update sub-regions
		$search_arr['t_arr_rgn_name_id'] = $t_arr_rgn_name_id;
		$search_arr['f_arr_subrg_rgn'] = $f_arr_subrg_rgn;
		$search_arr['f_arr_subrg_code'] = $f_arr_subrg_code;
		$search_arr['t_arr_subrg_name_id'] = array_flip($t_arr_subrg);

		$this->i_or_u_tables($f_arr_subrg, $t_arr_subrg, $search_arr, "Sub-regions", "subrg_rgn_ID, subrg_code, subrg_name", "T_regional__subregion");

		unset($t_arr_rgn_name_id);
		unset($t_arr_subrg);
		unset($f_arr_subrg_rgn);
		unset($f_arr_subrg_code);
		unset($f_arr_subrg);


		// Fetch the sub-regions info from database twice for getting the new sub-region_IDs which just inserted from previous step
		$t_arr_subrg_name_id = array();
		$r_subrg = $DB->get_results('SELECT subrg_ID, subrg_name FROM `T_regional__subregion`');
		foreach ($r_subrg as $v) {
			$t_arr_subrg_name_id[$v->subrg_name] = $v->subrg_ID;
		}
		unset($r_subrg);

		// Fetch the cities info from database
		$t_arr_city = array();
		$r_city = $DB->get_results('SELECT city_ID, city_name FROM `T_regional__city`');
		foreach ($r_city as $v) {
			$t_arr_city[$v->city_ID] = $v->city_name;
		}
		unset($r_city);

		// Construct the mapping parameter and transmit it to the function i_or_u_tables() for insert or update cities
		$search_arr['t_arr_subrg_name_id'] = $t_arr_subrg_name_id;
		$search_arr['f_arr_city_subrg'] = $f_arr_city_subrg;
		$search_arr['f_arr_city_postcode'] = $f_arr_city_postcode;
		$search_arr['t_arr_city_name_id'] = array_flip($t_arr_city);

		$this->i_or_u_tables($f_arr_city, $t_arr_city, $search_arr, "Cities", "city_ctry_ID, city_rgn_ID,city_subrg_ID,city_postcode,city_name", "T_regional__city");

		unset($t_arr_subrg_name_id);
		unset($t_arr_city);
		unset($f_arr_city_subrg);
		unset($f_arr_city_postcode);
		unset($f_arr_city);

		// Last release the mapping tool 
		unset($search_arr);
		// Commit transaction
		$DB->commit();
	}

	/**
	 * Save as a csv file
	 * 
	 * @param string $file_name
	 * @param string $data
	 */
	function export_csv($file_name, $export_data) {
		header("Content-type:text/csv");
		header("Content-Disposition:attachment;filename=" . $file_name);
		header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
		header('Expires:0');
		header('Pragma:public');
		echo $export_data;
		exit;
	}

	/**
	 * Add double quotation marks for the value of CSV file which is going to be created
	 * 
	 * @param string $value
	 * @return string
	 */
	function add_quote($value) {
		return '"' . addslashes($value) . '"';
	}

	/**
	 * The whole process of exporting regional data from database
	 * 
	 * @global object $DB
	 * @global type $Message
	 * @param type $file_name
	 * @param type $separate_mark
	 */
	function export_regional_data($file_name, $separate_mark) {
		global $DB;

		// Begin transaction
		$DB->begin();

		$export_data = T_('Countries Code') . $separate_mark .
			T_('Regions') . $separate_mark .
			T_('Regions Code') . $separate_mark .
			T_('Sun-regions') . $separate_mark .
			T_('Sun-regions Code') . $separate_mark .
			T_('Cities') . $separate_mark .
			T_('Postcode') . "\n";

		// Fetch Countries code->id mapping array
		$r_ctry = $DB->get_results('SELECT ctry_ID, ctry_code
						FROM T_regional__country;');

		$t_arr_ctry_id_code = array();
		foreach ($r_ctry as $v) {
			$t_arr_ctry_id_code[$v->ctry_ID] = $v->ctry_code;
		}
		unset($r_ctry);

		// Fetch regions data from database and write them to CSV data string
		$r_rgn = $DB->get_results('SELECT rgn_ID, rgn_ctry_ID, rgn_code, rgn_name
						FROM T_regional__region;');

		$t_arr_rgn_id_code = $t_arr_rgn_id_name = $t_arr_rgn_id_ctry = array();
		foreach ($r_rgn as $v) {
			$t_arr_rgn_id_code[$v->rgn_ID] = $v->rgn_code;
			$t_arr_rgn_id_name[$v->rgn_ID] = $v->rgn_name;
			$t_arr_rgn_id_ctry[$v->rgn_ID] = $v->rgn_ctry_ID;
			// print the region info
			$export_data .= $t_arr_ctry_id_code[$v->rgn_ctry_ID] . $separate_mark .
				$this->add_quote($v->rgn_name) . $separate_mark .
				$this->add_quote($v->rgn_code) . "\n";
		}
		unset($r_rgn);

		// Fetch Sub-regions data from database and write them to CSV data string
		$r_subrg = $DB->get_results('SELECT subrg_ID, subrg_rgn_ID, subrg_code, subrg_name
						FROM T_regional__subregion;');

		$t_arr_usbrg_id_code = $t_arr_subrg_id_name = $t_arr_subrgID_rgnID = array();
		foreach ($r_subrg as $v) {
			$t_arr_usbrg_id_code [$v->subrg_ID] = $v->subrg_code;
			$t_arr_subrg_id_name [$v->subrg_ID] = $v->subrg_name;
			$t_arr_subrgID_rgnID [$v->subrg_ID] = $v->subrg_rgn_ID;
			// print the sub-region info
			$export_data .= $t_arr_ctry_id_code[$t_arr_rgn_id_ctry[$v->subrg_rgn_ID]] . $separate_mark .
				$this->add_quote($t_arr_rgn_id_name[$v->subrg_rgn_ID]) . $separate_mark .
				$this->add_quote($t_arr_rgn_id_code[$v->subrg_rgn_ID]) . $separate_mark .
				$this->add_quote($v->subrg_name) . $separate_mark .
				$this->add_quote($v->subrg_code) . "\n";
		}
		unset($r_subrg);

		// Fetch cities data from database and write them to CSV data string
		$r_city = $DB->get_results('SELECT city_ctry_ID, city_rgn_ID, city_subrg_ID, city_postcode, city_name
						FROM T_regional__city;');
		foreach ($r_city as $v) {
			// print the city info
			$export_data .= $t_arr_ctry_id_code[$v->city_ctry_ID] . $separate_mark .
				$this->add_quote($t_arr_rgn_id_name[$v->city_rgn_ID]) . $separate_mark .
				$this->add_quote($t_arr_rgn_id_code[$v->city_rgn_ID]) . $separate_mark .
				$this->add_quote($t_arr_subrg_id_name[$v->city_subrg_ID]) . $separate_mark .
				$this->add_quote($t_arr_usbrg_id_code[$v->city_subrg_ID]) . $separate_mark .
				$this->add_quote($v->city_name) . $separate_mark .
				$this->add_quote($v->city_postcode) . "\n";
		}

		unset($r_city);

		unset($t_arr_ctry_id_code);
		unset($t_arr_rgn_id_code);
		unset($t_arr_rgn_id_name);
		unset($t_arr_rgn_id_ctry);
		unset($t_arr_usbrg_id_code);
		unset($t_arr_subrg_id_name);
		unset($t_arr_subrgID_rgnID);

		$DB->commit();
		// Generate the data string to CSV file
		$this->export_csv($file_name, $export_data);
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
				$Form = new Form(NULL, 'import_regions', 'post', '', 'multipart/form-data');

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
				$Form->input_field(array('label' => T_('Separate mark of importing CSV file'), 'note' => T_('Only support "<b>,</b>" or "<b>;</b>"'), 'name' => 'separate', 'type' => 'text', 'value' => ',', 'size' => '1', 'required' => true));
				$Form->input_field(array('label' => T_('Source file'), 'note' => T_('CSV file to be imported'), 'name' => 'csv', 'type' => 'file', 'required' => true));
				$Form->input_field(array('value' => T_('Import regional data'), 'name' => 'submit', 'type' => 'submit' , 'required' => true));

				$Form->end_form();


				$Form2 = new Form(NULL, 'export_regions', 'post', '');

				$Form2->begin_form('fform2');
				$Form2->add_crumb('tools');
				$Form2->hidden_ctrl(); // needed to pass the "ctrl=tools" param
				$Form2->hiddens_by_key(get_memorized()); // needed to pass all other memorized params, especially "tab"
				$Form2->hidden('action', 'export_regional_data');
				echo "<hr>";
				$Form2->input_field(array('label' => T_('Export regional data as CSV file'), 'note' => T_('You can use this file as a import template or backup file!'), 'value' => T_('Export from database'), 'name' => 'submit', 'type' => 'submit'));

				$Form2->end_form();

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

					// Get seperate value
					$separate_mark = trim(param('separate', 'string', true));
					// Get the upload file info
					$csv = $_FILES['csv'];

					if (!preg_match('/^[,;]$/', $separate_mark)) {
						$Messages->add(T_('The separate only support "," and ";" .'), 'error');
						break;
					}

					if ($csv['size'] == 0) { // File is empty
						$Messages->add(T_('Please select a CSV file to import.'), 'error');
						break;
					} else if (!preg_match('/\.csv$/i', $csv['name'])) { // Extension is incorrect
						$Messages->add(sprintf(T_('&laquo;%s&raquo; has an unrecognized extension.'), $csv['name']), 'error');
						break;
					}

					$this->import_regional_data($csv['tmp_name'], $separate_mark);
					break;
				case 'export_regional_data':
					$export_file_name = "Export-regional-data-b2evolution-" . date('YmdHis') . ".csv";
					$this->export_regional_data($export_file_name, ",");
					break;
			}
		}
	}

}

?>

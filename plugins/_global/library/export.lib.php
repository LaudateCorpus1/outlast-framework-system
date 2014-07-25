<?php
/**
 * Library helps you export data from Mozajik models, database queries, or an array of standard PHP objects. Using PHPExcel will provide more features and better export results.
 * @author Aron Budinszky <aron@outlast.hu>
 * @version 3.0
 * @package Library
 **/


define("OFW_EXPORT_MAX_EXECUTION_TIME", 300);

define("OFW_EXPORT_ENCODING_DEFAULT", false);
define("OFW_EXPORT_ENCODING_EXCEL", true);


class zajlib_export extends zajLibExtension {

		/**
		 * Export model data as csv.
		 * @param array|zajlib_db_session|zajFetcher $fetcher A zajFetcher list of zajModel objects which need to be exported. It can also be an array of objects (such as a zajDb query result) or a multi-dimensional array.
		 * @param array|bool $fields A list of fields from the model which should be included in the export.
		 * @param string $file_name The name of the file which will be used during download.
		 * @param boolean|string $encoding The value can be OFW_EXPORT_ENCODING_DEFAULT (utf8), OFW_EXPORT_ENCODING_EXCEL (Excel-compatible UTF-16LE), or any custom-defined encoding string.
		 * @param bool|string $delimiter The separator for the CSV data. Defaults to comma, unless you set excel_encoding...then it defaults to semi-colon.
		 * @return void Print the csv.
		 */
		public function csv($fetcher, $fields = false, $file_name='export.csv', $encoding = false, $delimiter = false){
			// Show template
			$this->zajlib->config->load('export.conf.ini');
			// No more autoloading for OFW
				zajLib::me()->model_autoloading = false;
			// Try using PHPExcel if available
				@include_once($this->zajlib->config->variable->php_excel_path);
				if(!class_exists('PHPExcel', false) || $encoding){
					// Standard CSV export
						zajLib::me()->model_autoloading = true;
					// Standard CSV or Excel header?
						if($encoding){
							// Use excel encoding or custom encoding
							if($encoding === OFW_EXPORT_ENCODING_EXCEL) header("Content-Type: application/vnd.ms-excel; charset=UTF-16LE");
							else  header("Content-Type: application/vnd.ms-excel; charset=".$encoding);
							header("Content-Disposition: attachment; filename=\"$file_name\"");
							if(!$delimiter) $delimiter = ';';
						}
						else{
							header("Content-Type: text/csv; charset=UTF-8");
							header("Content-Disposition: attachment; filename=\"$file_name\"");
							if(!$delimiter) $delimiter = ',';
						}
						$output = fopen('php://output', 'w');
						$this->send_data($output, $fetcher, $fields, $encoding, $delimiter);
				}
				else{
					// Create the csv file with PHPExcel
						if(!$delimiter) $delimiter = ',';
						$workbook = new PHPExcel();
						$workbook->setActiveSheetIndex(0);

						zajLib::me()->model_autoloading = true;
						$this->send_data($workbook, $fetcher, $fields);

						zajLib::me()->model_autoloading = false;
						header('Content-Type: text/csv');
						header('Content-Disposition: attachment;filename="'.$file_name.'"');
						header('Cache-Control: max-age=0');

						$writer = PHPExcel_IOFactory::createWriter($workbook, 'CSV');
						$writer->setDelimiter($delimiter);
						//$writer->setEnclosure('\'.$delimiter);
						$writer->setLineEnding("\r\n");
						$writer->setSheetIndex(0);
						$writer->save('php://output');
				}
				exit;
		}

		/**
		 * Export model data as excel. It should be noted that CSV export is much less memory and processor intensive, so for large exports we recommend that.
		 * @param array|zajlib_db_session|zajFetcher $fetcher A zajFetcher list of zajModel objects which need to be exported. It can also be an array of objects (such as a zajDb query result) or a multi-dimensional array.
		 * @param array|bool $fields A list of fields from the model which should be included in the export.
		 * @param string $file_name The name of the file which will be used during download.
		 * @require Requires the Spreadsheet_Excel_Writer PEAR module.
		 * @return void Sends to download of excel file.
		 */
		public function xls($fetcher, $fields = false, $file_name='export.xlsx'){
			$this->zajlib->config->load('export.conf.ini');
			
			// No more autoloading for OFW
				zajLib::me()->model_autoloading = false;
			// Require it if it is available
				include_once($this->zajlib->config->variable->php_excel_path);
				if(!class_exists('PHPExcel', false)) $this->zajlib->error("PHPExcel not found!");
			// Create the excel file
				$workbook = new PHPExcel();
			    $workbook->setActiveSheetIndex(0);
				
			// Write output
				zajLib::me()->model_autoloading = true;
				$this->send_data($workbook, $fetcher, $fields);
			
			// Send output
				zajLib::me()->model_autoloading = false;
				// Redirect output to a client’s web browser (Excel2007)
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment;filename="'.$file_name.'"');
				header('Cache-Control: max-age=0');

				$writer = PHPExcel_IOFactory::createWriter($workbook, 'Excel2007');
				$writer->save('php://output');
			exit;
		}

		/**
		 * Write data to an output.
		 * @param resource|PHPExcel $output The output object or handle.
		 * @param zajFetcher|zajlib_db_session|array $fetcher A zajFetcher list of zajModel objects which need to be exported. It can also be an array of objects (such as a zajDb query result) or a multi-dimensional array.
		 * @param array $fields A list of fields from the model which should be included in the export.
		 * @param boolean|string $encoding The value can be OFW_EXPORT_ENCODING_DEFAULT (utf8), OFW_EXPORT_ENCODING_EXCEL (Excel-compatible UTF-16LE), or any custom-defined encoding string.
		 * @param bool|string $delimiter The separator for the CSV data. Defaults to comma, unless you set excel_encoding...then it defaults to semi-colon.
		 * @return integer Returns the number of rows written.
		 */
		private function send_data(&$output, $fetcher, $fields, $encoding=false, $delimiter=false){
			// If encoding is boolean true, it is excel-encoding
				if($encoding === OFW_EXPORT_ENCODING_EXCEL) $encoding = "UTF-16LE";
			// Set my time limit
				set_time_limit(OFW_EXPORT_MAX_EXECUTION_TIME);
			// Get fields of fetcher class if fields not passed
				if(is_a($fetcher, 'zajFetcher') && (!$fields && !is_array($fields))){
					$class_name = $fetcher->class_name;
					$my_fields = $class_name::__model();
					foreach($my_fields as $field=>$val) $fields[] = $field;
				}
			// Get fields of db object if fields not passed (the property names of the object)
				if(!is_a($fetcher, 'zajFetcher') && (!$fields && !is_array($fields))){
					// Get the first row and create $fields[] array from it
					// @todo Check to see if 0 rows in result set for each of these!
						if(is_a($fetcher, 'Iterator')) $my_fields = $fetcher->rewind();
						else $my_fields = reset($fetcher);
					// Make sure that it is an object or array
						if(!is_array($my_fields) && !is_object($my_fields)) return $this->zajlib->error("Tried exporting data but failed. Input data must be an array of objects or an object.");
					foreach($my_fields as $field=>$val) $fields[] = $field;
				}
			
			// Run through all of my rows
				$column_order = array();
				$linecount = 1;
				foreach($fetcher as $s){
					// Create row data
						$data = array();
					// Is this a model or an array?
						if(is_a($s, 'zajModel')) $model_mode = true;
						else $model_mode = false;
					// Add first default value (only if model_mode)
						if($model_mode){
							// Set as name
								$data['name'] = $s->name;
							// Convert encoding if it is set
								if($encoding) $data['name'] = mb_convert_encoding($data['name'], $encoding, 'UTF-8');
						}
						
					// Add my values for each field
						foreach($fields as $type => $field){
							// Set to value
								if($model_mode) $field_value = $s->data->$field;
								else{
									// Either an array or an object
									if(is_array($s)) $field_value = $s[$field];
									else $field_value = $s->$field;
								}
							
							// Relationship field support (for manytoone only)
								if(is_object($field_value) && is_a($field_value, 'zajModel')){
									$data[$field] = $field_value->name;
								}
							// Relationship field support (for manytomany and onetomany)
								elseif(is_object($field_value) && is_a($field_value, 'zajFetcher')){
									$data[$field] = $field_value->total.' items';
								}
							// See if field value is an array
								elseif(is_array($field_value) || (is_object($field_value) && is_a($field_value, 'stdClass'))){
									foreach($field_value as $key=>$value) $data[$field.'_'.$key] = $value;
								}								
							// Time or date field
								elseif(is_string($type) && $type == 'time' && is_numeric($field_value)) $data[$field] = date("D M j G:i:s T Y", $field_value);
								elseif(is_string($type) && $type == 'date' && is_numeric($field_value)) $data[$field] = date("D M j Y", $field_value);
							// Standard field
								else $data[$field] = $field_value;
							// Convert encoding if excel mode selected
								if($encoding) $data[$field] = mb_convert_encoding($data[$field], $encoding, 'UTF-8');
						}
					// Add default values (only if model_mode)
						if($model_mode){						
							$data['ordernum'] = $s->data->ordernum;
							$data['time_create'] = date("D M j G:i:s T Y", $s->data->time_create);
							$data['id'] = $s->data->id;
						}
					// If firstline, display fields
						if($linecount == 1){
							// Write XLS
								if(is_a($output, 'PHPExcel')){
									// Write names
										$col = 0;
										zajLib::me()->model_autoloading = false;
										foreach(array_keys($data) as $field_name){
											$output->getActiveSheet()->setCellValueByColumnAndRow($col++, 1, $field_name);
											$column_order[] = $field_name;
										}
										zajLib::me()->model_autoloading = true;
								}
							// Write standard CSV
								else{
									fputcsv($output, array_keys($data), $delimiter);
									foreach(array_keys($data) as $field_name) $column_order[] = $field_name;
								}
							$linecount++;
						}
					// Display values
						// Write XLS
						if(is_a($output, 'PHPExcel')){
							// Write values
								zajLib::me()->model_autoloading = false;
								foreach($data as $field_key => $field_val){
									// Get which column this is
										$r = array_keys($column_order, $field_key);
										$col = $r[0];
									// Check if col is defined, if not now is the time to create the new column
										if(count($r) == 0){
											$col = count($column_order);
											$column_order[] = $field_key;
											$output->getActiveSheet()->setCellValueByColumnAndRow($col, 1, $field_key);
										}
									// If field value is an object
										if(is_object($field_val) || is_array($field_val)) $field_val = json_encode($field_val);
									// Replace new lines
										//$field_val = str_ireplace("\n", " ", $field_val);
									// Now output
										$output->getActiveSheet()->setCellValueByColumnAndRow($col, $linecount, $field_val);
								}
								zajLib::me()->model_autoloading = true;
						}
						// Write standard CSV
						else fputcsv($output, $data, $delimiter);
					// Add to linecount
						$linecount++;
				}
		}
	
		/**
		 * Send an array of form responses to a Google Docs spreadsheet form. You must make the sheet publicly visible and create a form from it.
		 * @param string $formkey The form key. You can get this data from the form's public URL query string.
		 * @param array $responses An key/value array of responses which to record. You must use the same key as the name of the field in the form (use 'inspect element' feature in Chrome on the Google Docs form to check).
		 * @param string $ok_text This text is searched for when we try to determine if the save was successful. You only need to change this if a custom confirmation message is set in Google Docs.
		 * @return boolean Returns true if successful and false if fails. It also throws a warning if it failed.
		 **/
		public function gdocs_form($formkey, $responses, $ok_text = "Your response has been recorded."){
			// Build entries list
				$query = '';
				foreach($responses as $key=>$val) $query .= '&'.urlencode($key).'='.urlencode($val);
			// Send the data
				$response = file_get_contents("https://docs.google.com/a/zajmedia.com/spreadsheet/formResponse?formkey=".$formkey."&embedded=true&ifq&pageNumber=0&submit=Submit".$query);
			// If $ok_text is contained in the response then all is ok.
				if(strstr($response, $ok_text) !== false) return true;
				else{
					$this->zajlib->warning('Unable to save responses to Google Forms: '.$query);
					return false;
				}
		}

}
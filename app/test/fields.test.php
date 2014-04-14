<?php
/**
 * A standard unit test for Outlast Framework system stuff.
 **/
class OfwFieldsTest extends zajTest {
	// Field name verification
	public function system_fields_name_verify(){
		$result = $this->zajlib->db->verify_field("Field_with_strange_and_long_name88");
		zajTestAssert::isTrue($result > 0);
		$this->zajlib->db->verify_field("Invalid-fieldname");
		zajTestAssert::isFalse($result <= 0);

	}
	/**
	 * Test various fields.
	 **/
	public function system_fields_serialized(){
		// Create a field object
			$fieldobj = $this->system_fields_create("serialized");
		// Save
			$data = array("hu_HU", "sk_SK");
			$result = $fieldobj->save($data, $fieldobj);
			zajTestAssert::isString($result[0]);
			zajTestAssert::areIdentical($data, $result[1]);
		// Get
			$data = 'O:8:"stdClass":2:{i:0;s:5:"hu_HU";i:1;s:5:"sk_SK";}';
			$result = $fieldobj->get($data, $fieldobj);
			zajTestAssert::isObject($result);
	}

	public function system_fields_boolean(){
		// Create a field object
			$fieldobj = $this->system_fields_create("boolean");
		// Get data by default
			$result = (boolean) $fieldobj->get('', $fieldobj);
			zajTestAssert::isFalse($result);
		// Create a field object with true as default
			$fieldobj = $this->system_fields_create("boolean", true);
			$result = (boolean) $fieldobj->get('', $fieldobj);
			zajTestAssert::isTrue($result);
	}

	public function system_fields_locale(){
		// Create a field object
			$fieldobj = $this->system_fields_create("locale");
		// Save
			$data = "hu_HU";
			$result = $fieldobj->save($data, $fieldobj);
			zajTestAssert::isString($result[0]);
			zajTestAssert::areIdentical($data, $result[1]);
		// Get
			$result = $fieldobj->get($data, $fieldobj);
			zajTestAssert::isString($result);
	}
	public function system_fields_locales(){
		$type = "locales";
		// Create a field object
			$fieldobj = $this->system_fields_create($type);
		// Save
			$data = array("hu_HU", "sk_SK");
			$result = $fieldobj->save($data, $fieldobj);
			zajTestAssert::isString($result[0]);
			zajTestAssert::areIdentical($data, $result[1]);
		// Get
			$data = 'O:8:"stdClass":2:{i:0;s:5:"hu_HU";i:1;s:5:"sk_SK";}';
			$result = $fieldobj->get($data, $fieldobj);
			zajTestAssert::isObject($result);
		// Display!
			$this->system_field_view($fieldobj);
	}
	public function system_fields_email(){
		// Create a field object
			$fieldobj = $this->system_fields_create("email");
		// Test validation
			$res = $fieldobj->validation('asdf');
			zajTestAssert::isFalse($res);
			$res = $fieldobj->validation('ofw@example.com');
			zajTestAssert::isTrue($res);
	}

	/**
	 * Creates a field object for testing.
	 **/
	private function system_fields_create($type, $options = false){
		// Defaults to array
			if($options === false) $options = array();
		// Create the feeld
			$fieldobj = zajField::create($type.'_test_field', (object) array('type'=>$type,'options'=>$options, 'OfwTestModel'));
		// Database
			$db = $fieldobj->database();
			zajTestAssert::isArray($db);
			zajTestAssert::isArray($db[$type.'_test_field']);
			zajTestAssert::isString($db[$type.'_test_field']['field']);
		return $fieldobj;
	}
	
	/**
	 * Tries to display the field's default editor template.
	 * @param zajField $fieldobj
	 * @return bool
	 */
	private function system_field_view($fieldobj){
		// Get type
			$type = $fieldobj->type;
		// Fake an array of choices
			$this->zajlib->variable->field = (object) array('choices'=>array());
		// Get the compiled file
			$result = $this->zajlib->template->show("field/".$type.".field.html", true, true);
			zajTestAssert::isString($result);
		return true;
	}
}
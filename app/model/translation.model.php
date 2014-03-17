<?php
/**
 * Stores a translation for a field of a model object.
 **/

/**
 * Class Translation
 * @property string $modelname
 * @property string $parent
 * @property string $field
 * @property mixed $value
 * @property string $locale
 * @property zajTranslationData $data
 */
class Translation extends zajModel {
	
	/**
	 * Model definition
	 **/
	public static function __model(){
		// define custom database fields
			$f = (object) array();
			$f->modelname = zajDb::text();
			$f->parent = zajDb::text();
			$f->field = zajDb::text();
			$f->locale = zajDb::text();
			$f->value = zajDb::textarea();

		// do not modify the line below!
			$f = parent::__model(__CLASS__, $f); return $f;
	}

	/**
	 * Construction and required methods
	 **/
	public function __construct($id = ""){ parent::__construct($id, __CLASS__); return true; }
	public static function __callStatic($name, $arguments){ array_unshift($arguments, __CLASS__); return call_user_func_array(array('parent', $name), $arguments); }

	/**
	 * Caching the information.
	 **/
	public function __afterFetch(){
		$this->modelname = $this->data->modelname;
		$this->parent = $this->data->parent;
		$this->field = $this->data->field;
		$this->value = $this->data->value;
		$this->locale = $this->data->locale;
		// Original value
		// $f->original - this is a dynamic field that retrieves the data.
	}

	/**
	 * Retrieve the original data or just do a standard __get
	 **/
	public function __get($name){
		// If original value is being called for
			if($name == "original"){
				$modelname = $this->modelname;
				$fieldname = $this->field;
				return $modelname::fetch($this->parent)->data->$fieldname;
			}
		// Otherwise, standard __get()
			return parent::__get($name);
	}

	/**
	 * Get by the locale and properties.
	 * @param string $modelname The model name.
	 * @param string $parent The parent object's id.
	 * @param string $field The field that we require.
	 * @param bool|string $locale The locale value (4 letter version). This defaults to the current locale.
	 * @return Translation Returns the translation object.
	 * @todo Add manual key/value pair caching based on all of these parameters together.
	 */
	public static function fetch_by_properties($modelname, $parent, $field, $locale = false){
		// If locale is empty then set to current
			if(empty($locale)) $locale = zajLib::me()->lang->get();
		// Now fetch the translation object
			return Translation::fetch()->filter('modelname', $modelname)->filter('parent', $parent)->filter('field', $field)->filter('locale', $locale)->next();
	}

	/**
	 * Create by the locale and properties. If it already exists, then return existing. The item is not saved or cached automatically.
	 * @param string $modelname The model name.
	 * @param string $parent The parent object's id.
	 * @param string $field The field that we require.
	 * @param bool|string $locale The locale value (4 letter version). This defaults to the current locale.
	 * @return Translation Returns the already existing or newly created translation object.
	 */
	public static function create_by_properties($modelname, $parent, $field, $locale = false){
		// If locale is empty then set to current
			if(empty($locale)) $locale = zajLib::me()->lang->get();
		// First, check to see if it exists
			$tobj = Translation::fetch_by_properties($modelname, $parent, $field, $locale);
		// If false, then create a new one
			if(!is_object($tobj)){
				$tobj = Translation::create();
				$tobj->set('modelname', $modelname)->set('parent', $parent)->set('field', $field)->set('locale', $locale);
			}
		return $tobj;
	}

}
<?php
// Define my photo sizes if not already done!
if(empty($GLOBALS['photosizes'])) $GLOBALS['photosizes'] = array('thumb'=>50,'small'=>300,'normal'=>700,'large'=>2000,'full'=>true);

/**
 * A built-in model to store photos.
 *
 * This is a pointer to the data items in this model...
 * @property zajDataPhoto $data
 * And here are the cached fields...
 * @property string $status
 * @property string $class The class of the parent.
 * @property string $parent The id of the parent.
 * @property string $field The field name of the parent.
 * @property boolean $timepath If the new time-based path is used.
 * @property integer $time_create
 * @property string $extension
 * @property string $imagetype Can be IMAGETYPE_PNG, IMAGETYPE_GIF, or IMAGETYPE_JPG constant.
 * @method static Photo|zajFetcher fetch()
 **/
class Photo extends zajModel {

	/* If set to true, the file is not yet saved to the db. */
	public $temporary = false;
		
	///////////////////////////////////////////////////////////////
	// !Model design
	///////////////////////////////////////////////////////////////
	public static function __model(){	
		// define custom database fields
			$f = (object) array();
			$f->class = zajDb::text();
			$f->parent = zajDb::text();
			$f->field = zajDb::text();
			$f->name = zajDb::name();
			$f->imagetype = zajDb::integer();
			$f->original = zajDb::text();
			$f->description = zajDb::textbox();
			$f->filesizes = zajDb::json();
			$f->dimensions = zajDb::json();
			$f->timepath = zajDb::boolean();
			$f->status = zajDb::select(array("new","uploaded","saved","deleted"),"new");
		// do not modify the line below!
			$f = parent::__model(__CLASS__, $f); return $f;
	}
	///////////////////////////////////////////////////////////////
	// !Construction and other required methods
	///////////////////////////////////////////////////////////////
	public function __construct($id = ""){ parent::__construct($id, __CLASS__);	}
	public static function __callStatic($name, $arguments){ array_unshift($arguments, __CLASS__); return call_user_func_array(array('parent', $name), $arguments); }

	///////////////////////////////////////////////////////////////
	// !Magic methods
	///////////////////////////////////////////////////////////////
	public function __afterFetch(){
		// Set status and parents
			$this->status = $this->data->status;
			$this->class = $this->data->class;
			$this->parent = $this->data->parent;
			$this->field = $this->data->field;
			$this->timepath = $this->data->timepath;
			$this->time_create = $this->data->time_create;
		// See which file exists
			if(file_exists($this->zajlib->basepath.$this->get_file_path($this->id."-normal.png"))){
				$this->extension = 'png';
				$this->imagetype = IMAGETYPE_PNG;
			}
			elseif(file_exists($this->zajlib->basepath.$this->get_file_path($this->id."-normal.gif"))){
				$this->extension = 'gif';
				$this->imagetype = IMAGETYPE_GIF;
			}
			else{
				$this->extension = 'jpg';
				$this->imagetype = IMAGETYPE_JPEG;
			}
	}

	/**
	 * Returns the url based on size ($photo->small) or the relative url ($photo->rel_small)
	 **/
	public function __get($name){
		// Default the extension to jpg if not defined
			if(empty($this->extension)) $this->extension = 'jpg';
		// Figure out direct or relative file name
			$relname = str_ireplace('rel_', '', $name);
			if(!empty($GLOBALS['photosizes'][$name])) return $this->get_image($name);
			else{
				if(!empty($GLOBALS['photosizes'][$relname])) return $this->get_file_path($this->id."-$relname.".$this->extension);
				else return parent::__get($name);
			}
	}

	/**
	 * Helper function which returns the path based on the current settings.
	 * @param string $filename Can be thumb, small, normal, etc.
	 * @param bool $create_folders Create the subfolders if needed.
	 * @return string Returns the file path.
	 **/
	public function get_file_path($filename, $create_folders = false){
		// First, let's determine which function to use
			if($this->timepath) $path = $this->zajlib->file->get_time_path("data/Photo", $filename, $this->time_create, false);
			else $path = $this->zajlib->file->get_id_path("data/Photo", $filename, false);
		// Create folders if requested
			if($create_folders) $this->zajlib->file->create_path_for($path);
		// Now call and return!
			return $path;
	}

	///////////////////////////////////////////////////////////////
	// !Model methods
	///////////////////////////////////////////////////////////////

	/**
	 * This is an alias to set_image, because file also has one like it.
	 **/
	public function upload($filename = ""){ return $this->set_image($filename); }

	/**
	 * Resizes and saves the image. The status is always changed to saved and this method automatically saves changes to the database. Only call this when you are absolutely ready to commit the photo for public use.
	 * @param string $filename The name of the file within the cache upload folder.
	 * @return bool|Photo Returns the Photo object, false if error.
	 */
	public function set_image($filename = ""){
		// if filename is empty, use default tempoary name
			if(empty($filename)) $filename = $this->id.".tmp";
		// jail file
			if(strpos($filename, '..') !== false || strpos($filename, '/') !== false) $this->zajlib->error("invalid filename given when trying to save final image.");
		// set variables
			$file_path = $this->zajlib->basepath."cache/upload/".$filename;
			$image_data = getimagesize($file_path);
		// check for errors
			if(strpos($filename,"/") !== false) return $this->zajlib->error('uploaded photo cannot be saved: must specify relative path to cache/upload folder.');
			if(!file_exists($this->zajlib->basepath."cache/upload/".$filename)) return $this->zajlib->error("uploaded photo $filename does not exist!");
			if($image_data === false) return $this->zajlib->error('uploaded file is not a photo. you should always check this before calling set_image/upload!');
		// check image type of source
			$image_type = exif_imagetype($file_path);
		// select extension
			if($image_type == IMAGETYPE_PNG) $extension = 'png';
			elseif($image_type == IMAGETYPE_GIF) $extension = 'gif';
			else $extension = 'jpg';
		// now enable time-based folders
			$this->set('timepath', true);
			$this->timepath = true;
			$filesizes = $dimensions = array();
		// no errors, resize and save
			foreach($GLOBALS['photosizes'] as $key => $size){
				if($size !== false){
					// save resized images perserving extension
						$new_path = $this->zajlib->basepath.$this->get_file_path($this->id."-$key.".$extension, true);
					// resize it now!
						$this->zajlib->graphics->resize($file_path, $new_path, $size);
					// let's get the new file size
						$filesizes[$key] = @filesize($new_path);
						$my_image_data = @getimagesize($new_path);
						$dimensions[$key] = array('w'=>$my_image_data[0], 'h'=>$my_image_data[1]);

				}
			}
		// now remove the original or copy to full location
			if($GLOBALS['photosizes']['full']){
				$new_path = $this->zajlib->basepath.$this->get_file_path($this->id."-full.".$extension, true);
				copy($file_path, $new_path);
				// @todo Shouldn't this be move?
				$filesizes['full'] = @filesize($new_path);
				$my_image_data = @getimagesize($new_path);
				$dimensions['full'] = array('w'=>$my_image_data[0], 'h'=>$my_image_data[1]);
			}
			else unlink($file_path);
		// Remove temporary location flag
			$this->temporary = false;
			$this->set('dimensions', $dimensions);
			$this->set('filesizes', $filesizes);
			$this->set('status', 'saved');
			$this->save();
		return $this;
	}

	/**
	 * Returns an image url based on the requested size.
	 * @param string $size One of the standard photo sizes.
	 * @return string Image url.
	 */
	public function get_image($size = 'normal'){
		// Default the extension to jpg if not defined (backwards compatibility)
			if(empty($this->extension)) $this->extension = 'jpg';
		return $this->zajlib->baseurl.$this->get_file_path($this->id."-$size.".$this->extension);
	}

	/**
	 * Returns an image path based on the requested size.
	 * @param string $size One of the standard photo sizes.
	 * @return string Image path.
	 **/
	public function get_path($size = 'normal'){
		// Default the extension to jpg if not defined (backwards compatibility)
			if(empty($this->extension)) $this->extension = 'jpg';
		return $this->zajlib->basepath.$this->get_file_path($this->id."-$size.".$this->extension);
	}

	/**
	 * An alias of Photo->download($size, false), which will display the photo instead of forcing a download.
	 * @param string $size One of the standard photo sizes.
	 **/
	public function show($size = "normal"){
		$this->download($size, false);
	}

	/**
	 * Forces a download dialog for the browser.
	 * @param string $size One of the standard photo sizes.
	 * @param boolean $force_download If set to true (default), this will force a download for the user.
	 * @return void|boolean This will force a download and exit. May return false if it fails.
	 */
	public function download($size = "normal", $force_download = true){
		// Default the extension to jpg if not defined (backwards compatibility)
			if(empty($this->extension)) $this->extension = 'jpg';
		// look for bad characters in $size
			if(($size != "preview" && empty($GLOBALS['photosizes'][$size])) || substr_count($size, "..") > 0)  return $this->zajlib->error("File could not be found.");
			if(!$this->temporary && $size == "preview") $size = 'normal';
		// generate path
			$file_path = $this->zajlib->basepath.$this->get_file_path($this->id."-$size.".$this->extension);
		// if it is in preview mode (only if not yet finalized)
			$preview_path = $this->zajlib->basepath."cache/upload/".$this->id.".tmp";
			if($this->temporary && $size == "preview") $file_path = $preview_path;
		// final test, if file exists
			if(!file_exists($file_path)) return $this->zajlib->error("File could not be found.");
		// pass file thru to user
			if($force_download) header('Content-Disposition: attachment; filename="'.$this->data->name.'"');
		// create header
			switch ($this->extension){
				case 'png': header('Content-Type: image/png;'); break;
				case 'gif': header('Content-Type: image/gif;'); break;
				default: header('Content-Type: image/jpeg;'); break;
			}
		// open and pass through
			$f = fopen($file_path, "r");
				fpassthru($f);
			fclose($f);
		// now exit
		exit;
	}
	/**
	 * Overrides the global delete.
	 * @param bool $complete If set to true, the file will be deleted too and the full entry will be removed.
	 * @return bool Returns true if successful.
	 **/
	public function delete($complete = false){
		// Default the extension to jpg if not defined (backwards compatibility)
			if(empty($this->extension)) $this->extension = 'jpg';
		// remove photo files
			if($complete){
				foreach($GLOBALS['photosizes'] as $name=>$size){
					if($size) @unlink($this->zajlib->basepath.$this->get_file_path($this->id."-$name.".$this->extension));
				}
			}
		// call parent
			return parent::delete($complete);
	}


	///////////////////////////////////////////////////////////////
	// !Static methods
	///////////////////////////////////////////////////////////////
	// be careful when using the import function to check if filename or url is valid


	/**
	 * Creates a photo object from a file or url. Will return false if it is not an image or not found.
	 * @param string $urlORfilename The url or file name.
	 * @param zajModel|bool $parent My parent object. If not specified, none will be set.
	 * @param boolean $save_now_to_final_destination If set to true (the default) it will be saved in the final folder immediately. Otherwise it will stay in the tmp folder.
	 * @return Photo Returns the new photo object or false if none created.
	 **/
	public static function create_from_file($urlORfilename, $parent = false, $save_now_to_final_destination = true){
		// First check to see if it is a photo
			$image_data = @getimagesize($urlORfilename);
			if($image_data === false) return false;
		// Make sure uploads folder exists
			@mkdir(zajLib::me()->basepath."cache/upload/", 0777, true);
		// Create object
			/** @var Photo $pobj **/
			$pobj = Photo::create();
			$pobj->set('name', basename($urlORfilename));
		// Copy to tmp destination and set stuff
			copy($urlORfilename, zajLib::me()->basepath."cache/upload/".$pobj->id.".tmp");
			if($parent !== false) $pobj->set('parent', $parent);
			if($save_now_to_final_destination) $pobj->upload();
			else $pobj->temporary = true;
			$pobj->save();
		return $pobj;
	}
	/**
	 * Included for backwards-compatibility. Will be removed. Alias of create_from_file.
	 * @todo Remove from version release.
	 **/
	public static function import($urlORfilename){ return self::create_from_file($urlORfilename); }
	
	/**
	 * Creates a photo object from php://input stream.
	 * @param zajModel|bool $parent My parent object.
	 * @param boolean $save_now_to_final_destination If set to true (the default) it will be saved in the final folder immediately. Otherwise it will stay in the tmp folder.
	 * @param string|boolean $base64_data If specified, this will be used instead of input stream data.
	 * @return Photo|bool Returns the Photo object on success, false if not.
	 **/
	public static function create_from_stream($parent = false, $save_now_to_final_destination = true, $base64_data = false){
		// Create a Photo object
			/** @var Photo $pobj **/
			$pobj = Photo::create();
		// tmp folder
			$folder = zajLib::me()->basepath.'/cache/upload/';
			$filename = $pobj->id.'.tmp';
		// base64 data or stream?
			if($base64_data !== false) $photofile = base64_decode($base64_data);
			else $photofile = file_get_contents("php://input");
		// make temporary folder
			@mkdir($folder, 0777, true);
		// write to temporary file in upload folder
			@file_put_contents($folder.$filename, $photofile);
		// is photo an image
			$image_data = getimagesize($folder.$filename);
			if($image_data === false){
				// not image, delete file return false
				@unlink($folder.$filename);
				return false;
			}
		// Now set stuff
			$pobj->set('name', 'Upload');
			if($parent !== false) $pobj->set('parent', $parent);
			//$obj->set('status', 'saved'); (done by set_image)
			if($save_now_to_final_destination) $pobj->set_image($filename);
			else $pobj->temporary = true;
			$pobj->save();
			return $pobj;
	}
	
	/**
	 * Creates a photo object from base64 data.
	 * @param string $base64_data This is the photo file data, base64-encoded.
	 * @param zajModel|bool $parent My parent object.
	 * @param boolean $save_now_to_final_destination If set to true (the default) it will be saved in the final folder immediately. Otherwise it will stay in the tmp folder.
	 * @return Photo|bool Returns the Photo object on success, false if not.
	 **/
	public static function create_from_base64($base64_data, $parent = false, $save_now_to_final_destination = true){
		return self::create_from_stream($parent, $save_now_to_final_destination, $base64_data);
	}

	/**
	 * Creates a photo object from a standard upload HTML4
	 * @param string $field_name The name of the file input field.
	 * @param zajModel|bool $parent My parent object.
	 * @param boolean $save_now_to_final_destination If set to true (the default) it will be saved in the final folder immediately. Otherwise it will stay in the tmp folder.
	 * @return Photo|bool Returns the Photo object on success, false if not.
	 **/
	public static function create_from_upload($field_name, $parent = false, $save_now_to_final_destination = true){
		// File names
			$orig_name = $_FILES[$field_name]['name'];
			$tmp_name = $_FILES[$field_name]['tmp_name'];
		// If no file, return false
			if(empty($tmp_name)) return false;
		// Now create photo object and set me
			/** @var Photo $obj **/
			$obj = Photo::create();
		// Move uploaded file to tmp
			@mkdir(zajLib::me()->basepath.'cache/upload/');
			$new_name = zajLib::me()->basepath.'cache/upload/'.$obj->id.'.tmp';
		// Verify new name is jailed
			zajLib::me()->file->file_check($new_name);
			move_uploaded_file($tmp_name, $new_name);
		// Now set and save
			$obj->set('name', $orig_name);
			if($parent !== false) $obj->set('parent', $parent);
			//$obj->set('status', 'saved'); (done by set_image)
			if($save_now_to_final_destination) $obj->upload();
			else $obj->temporary = true;
			$obj->save();
			@unlink($tmp_name);
		return $obj;
	}
}
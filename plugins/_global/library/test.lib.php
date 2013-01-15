<?php
/**
 * Helper library for creating unit tests.
 * @author Aron Budinszky <aron@mozajik.org>
 * @version 3.0
 * @package Library
 **/

// Include Enhance tester class
	include_once($GLOBALS['zajlib']->basepath.'system/class/zajtest.class.php');	
		
class zajlib_test extends zajLibExtension {

	private static $paths = array(			// array - array of paths to search, order does not matter here!
		'local'=>'app/test/',
		'plugin_apps'=>true,				// boolean - set this to false if you don't want to check for app plugin views
		'system'=>'system/app/test/',
		'system_apps'=>true,				// boolean - when true, system apps will be loaded (don't change this unless you know what you're doing!)
	);
	private $filecount = 0;					// integer - the number of files loaded up

	private $is_running = false;			// boolean - this is true when the test is running

	/**
	 * Check if test is running.
	 **/
	public function is_running(){
		return $this->is_running;
	}

	/**
	 * Prepare a specific test for running by including it.
	 * @param string $path The include path is relative to basepath.
	 **/
	public function prepare($file){
		// Verify that the file is sandboxed within the project
			$file = $this->zajlib->file->file_check($file);
		// Now include it!
			include($this->zajlib->basepath.$file);
		// Add one to filecount
		return ++$this->filecount;
	}

	/**
	 * Prepare all tests for running.
	 **/
	public function prepare_all(){
		// collect all the path
			$allpaths = array();
			foreach(zajlib_test::$paths as $type=>$path){
				// if type is plugin_apps, then it is special!
					if($type == 'plugin_apps' && $path){
						// run through all of my registered plugin apps' views and return if one found!
							foreach($GLOBALS['zajlib']->loaded_plugins as $plugin_app){
								$path = $GLOBALS['zajlib']->basepath.'plugins/'.$plugin_app.'/test/';
								if(file_exists($path)) $allpaths[] = $path;
							}
					}
					elseif($type == 'system_apps' && $path){
						// run through all of my registered system apps' views and return if one found!
							foreach($GLOBALS['zajlib']->zajconf['system_apps'] as $plugin_app){
								$path = $GLOBALS['zajlib']->basepath.'system/plugins/'.$plugin_app.'/test/';
								if(file_exists($path)) $allpaths[] = $path;
							}
					}
					else{
						$path = $GLOBALS['zajlib']->basepath.$path.$source_file;
						if(file_exists($path)) $allpaths[] = $path;
					}
			}
		// Now get all files in each path
			$allfiles = array();
			foreach($allpaths as $path){
				foreach($this->zajlib->file->get_files_in_dir($path) as $file){
					$file = str_ireplace($this->zajlib->basepath, '', $file);
					$this->prepare($file);
				}
			}
	}

	/**
	 * Run the tests and return the count.
	 * @return integer The number of tests run including successful and unsuccessful ones.
	 **/
	public function run(){
		// Set is_running to true
			$this->is_running = true;
		// Get the EnhanceTestFramework object
			$this->zajlib->variable->test = \Enhance\Core::runTests("MOZAJIK");
			$this->zajlib->variable->test->filecount = $this->filecount;
			$this->zajlib->variable->test->testcount = count($this->zajlib->variable->test->Results)+count($this->zajlib->variable->test->Errors);
		// Return to originator!
			return $this->zajlib->variable->test->testcount;
	}
}

/**
 * Provide non-namespaced class name for unit testing.
 **/
class zajTest extends \Enhance\TestFixture{
	public $zajlib = '';
}
class zajTestAssert extends \Enhance\Assert{
}


?>
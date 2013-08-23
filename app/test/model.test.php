<?php
	/**
	 * A standard unit test for Outlast Framework models
	 * @todo Fix so that these tests do not fail if db is disabled.
	 **/
	class OfwModelTest extends zajTest {

		/* @var Photo $photo */
		public $photo;

		/**
		 * Set up stuff.
		 **/
		public function setUp(){
			// Disabled if mysql not enabled
				if(!$this->zajlib->zajconf['mysql_enabled']) return false;
			// Create a mock photo object
				$this->photo = Photo::create('mockid');
				$this->photo->set('name', 'mock!');
				$this->photo->set('field', 'mymockfield');
				$this->photo->save();
		}

		/**
		 * Verify that I could indeed save stuff
		 */
		public function system_verify_if_save_was_successful(){
			// Disabled if mysql not enabled
				if(!$this->zajlib->zajconf['mysql_enabled']) return false;
			// Fetch and test!
				$p = Photo::fetch('mockid');
				zajTestAssert::areIdentical('mock!', $p->name);
				zajTestAssert::areIdentical('mockid', $p->id);
		}


		/**
		 * Check the duplication feature
		 */
		public function system_check_duplocation_feature(){
			// Disabled if mysql not enabled
				if(!$this->zajlib->zajconf['mysql_enabled']) return false;
			// Let's try to duplicate the Photo object
				$p = $this->photo->duplicate('mock2');
				$p->save();
			// Now verify if it has the same info
				$p = Photo::fetch('mock2');
				zajTestAssert::areIdentical('mock!', $p->name);
				zajTestAssert::areIdentical('mock2', $p->id);
				zajTestAssert::areNotIdentical($this->photo->id, $p->id);
			// The order number of mock2 should be greater than that of mockid
				zajTestAssert::areNotIdentical($this->photo->data->ordernum, $p->data->ordernum);
		}


		/**
		 * Reset stuff, cleanup.
		 **/
		public function tearDown(){
			// Disabled if mysql not enabled
				if(!$this->zajlib->zajconf['mysql_enabled']) return false;
			// Remove permanently my mock photo
				$this->photo->delete(true);
			// Remove my mock2
				$m2 = Photo::fetch('mock2');
				if($m2->exists) $m2->delete(true);
		}

	}
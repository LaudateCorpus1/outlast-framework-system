<?php

    /**
     * A class for storing hierarchical categories.
     * @property string $friendlyurl
     * @property array $hierarchy An array list of Category objects above me starting with the top level.
     * @property array $revhierarchy An array list of Category objects above me starting with my parent.
     * @property string $parentcategoryid
     * @property string $parentcategoryname
     * @property integer $child_count The number of children this category has.
     * @property boolean $featured
     * @property CategoryData $data
     */
    class Category extends zajModel {

        // Change the default sorting behavior
        public static $fetch_order = 'ASC';
        public static $fetch_order_field = 'abc';

        /**
         * __model function. creates the database fields available for objects of this class.
         * @param bool|stdClass $f The field's object generated by the child class.
         * @return stdClass Returns an object containing the field settings as parameters.
         */
        public static function __model($f = false) {
            /////////////////////////////////////////
            // begin custom fields definition:
            if ($f === false) {
                $f = new stdClass();
            }

            $f->name = zajDb::name();
            $f->abc = zajDb::text();
            $f->photo = zajDb::photo();
            $f->description = zajDb::richtext();
            $f->featured = zajDb::boolean();
            $f->parentcategory = zajDb::category();
            $f->subcategories = zajDb::onetomany('Category', 'parentcategory');
            $f->friendlyurl = zajDb::friendly()->from('name');

            // do not modify the line below!
            return parent::__model($f);
        }

        ///////////////////////////////////////////////////////////////
        // !Custom methods
        ///////////////////////////////////////////////////////////////

        public function __afterFetch() {
            // Friendly url cache
            $this->friendlyurl = $this->data->friendlyurl;

            // Cache my parent if exists!
            if ($this->data->parentcategory) {
                $this->parentcategoryid = $this->data->parentcategory->id;
                $this->parentcategoryname = $this->data->parentcategory->name;
            }
            // The count
            $this->child_count = $this->recalc_counters();
            // Other fields
            $this->featured = $this->data->featured;
        }

        public function __afterDelete() {
            $this->recalc_counters();
        }

        /**
         * Calculates the number of children I and all of my ascendent parents have. Sets $this->count.
         * @return integer The count of children.
         **/
        public function recalc_counters() {
            // Recalculate my children
            $this->child_count = Category::fetch()->filter('parentcategory', $this->id)->total;
            // Recalculate for my parent (recursive)
            if (is_object($this->data->parentcategory)) {
                $this->data->parentcategory->recalc_counters();
                $this->data->parentcategory->cache();
            }

            return $this->child_count;
        }

        /**
         * Override delete so that all subcategories are also deleted.
         * @param boolean $permanent If set to true, it will permanently delete the item.
         * @return integer Returns the number of categories deleted.
         */
        public function delete($permanent = false) {
            /** @var Category $subcategory */
            // Delete subcategories
            $items_deleted = 1;
            foreach ($this->data->subcategories as $subcategory) {
                $items_deleted += $subcategory->delete($permanent);
            }
            // Delete me
            parent::delete($permanent);

            return $items_deleted;
        }

        /**
         * Get my hierarchy.
         */
        public function get_hierarchy() {
            // Generate hierarchy
            $hierarchy = [];
            $me = $this;
            while ($me = $me->data->parentcategory) {
                $hierarchy[] = $me;
            }

            return $hierarchy;
        }

        /**
         * Get magic properties.
         */
        public function __get($name) {
            switch ($name) {
                case 'hierarchy':
                    return $this->get_hierarchy();
                case 'revhierarchy':
                    return array_reverse($this->get_hierarchy());
                default:
                    return parent::__get($name);
            }
        }

        /**
         * Fetch a category object by friendly url.
         * @param string $friendlyurl The friendly url.
         * @return Category|boolean Returns false if failed, a Category object if not.
         **/
        public static function fetch_by_friendlyurl($friendlyurl) {
            if (zajLib::me()->lang->is_default_locale()) {
                return Category::fetch()->filter('friendlyurl', $friendlyurl)->next();
            } else {
                /** @var Translation $t */
                $t = Translation::fetch()->filter('modelname', 'Category')->filter('field',
                    'friendlyurl')->filter('locale', zajLib::me()->lang->get())->filter('value', $friendlyurl)->next();
                // If found return, if not, try default
                if ($t !== false) {
                    return Category::fetch($t->parent);
                } else {
                    return Category::fetch()->filter('friendlyurl', $friendlyurl)->next();
                }
            }
        }

        /**
         * Fetch top level categories.
         **/
        public static function fetch_top_level() {
            return Category::fetch()->filter('parentcategory', '');
        }

        /**
         * Add any parent categories recursively. For internal use only. Set category_auto_add_parents in category.conf.ini if you want this feature!
         * @param zajModel $object The zajModel object which to add the categories for.
         * @param string $fieldname The field name.
         * @return integer Return the number of categories added.
         * @ignore
         */
        public function add_parent_categories_recursively($object, $fieldname) {
            // Do I even have a parent?
            if ($this->data->parentcategory) {
                // Reload and add me if not yet connected
                $object->data->unload($fieldname);
                if (!$object->data->{$fieldname}->is_connected($this->data->parentcategory)) {
                    $object->data->{$fieldname}->add($this->data->parentcategory);
                }
                // Try running on parent too! (recursive!)
                $c = $this->data->parentcategory->add_parent_categories_recursively($object, $fieldname);

                return $c + 1;
            } else {
                return 0;
            }
        }

        /**
         * Remove any subcategories recursively. For internal use only. Set category_auto_remove_subs in category.conf.ini if you want this feature!
         * @param zajModel $object The zajModel object which to add the categories for.
         * @param string $fieldname The field name.
         * @return integer Return the number of categories added.
         * @todo Implement in categories.field.php (we need to know which categories were removed during save())
         * @ignore
         */
        public function remove_subcategories_recursively($object, $fieldname) {
            // Do I even have a subcategories?
            if ($this->data->subcategories->total > 0) {
                $count = 0;
                foreach ($this->data->subcategories as $subcat) {
                    // Reload and remove me if connected
                    $object->data->unload($fieldname);//?
                    if ($object->data->{$fieldname}->is_connected($subcat)) {
                        $object->data->{$fieldname}->remove($subcat);
                    }
                    // Try running on subcategories too! (recursive!)
                    $count += $subcat->remove_subcategories_recursively($object, $fieldname);
                }

                return $count;
            } else {
                return 0;
            }
        }

        /**
         * Categories are completely public by default.
         * @param zajFetcher $fetcher
         * @return zajFetcher
         */
        public static function __onSearch($fetcher) {
            return $fetcher;
        }
    }
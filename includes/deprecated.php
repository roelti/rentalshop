<?php
public function import_categories_safe( $new_categories ) {
		$args = array(
				'hide_empty'               => 0,
				'hierarchical'             => 1,
				'taxonomy'                 => 'product_cat',
			);
		$current_categories = get_categories($args);
		$current = $this->get_category_hierarchy( $args );
		//Debug::dump($new_categories);
		//Debug::dump($current);
		
	}

	private function compare_category_layer($left, $right) {
		
	}

	/**
	 * Checks if an item is in the array or a child array of the array, 1 level deep
	 */
	private function in_array_child($needle, $haystack) {
		foreach ( $hastack as $hay ) {
			if ( in_array($needle, $hay) ) {
				return true;
			}
		}
		return false;
	}

	/** 
	 *	Returns a hierarchical array.
	 *
	 *	@param array $args arguments as passed
	 *
	 *  @return array $output
	 */
	public function get_category_hierarchy( $args ) {
		$categories = get_categories($args); 
		foreach($categories as $category) {
			$categories_array[] = (array) $category; 
		}

		$tree = $this->buildTree($categories_array);
		return $tree;
	}

	function buildTree(array $elements, $parentId = 0, $parent_name = '') {
	    $branch = array();

	    foreach ($elements as $element) {
	        if ($element['parent'] == $parentId) {
	            $children = $this->buildTree($elements, $element['term_id']);
	            if ($children) {
	                $element['children'] = $children;
	            }
	            // WIP
	            //$element['name'] = $parent_name . $element['name'];
	            $branch[] = $element;
	        }
	    }

	    return $branch;
	}
		public function add_category($category, $parent = 0) {
		$id = intval($category["id"]);
		$children = $category["children"];
		
		$this->category_list[$id] = $category["naam"];
		$insertion = wp_insert_term($category["naam"], "product_cat", array('parent' => $parent, 'slug' => $category["naam"]));
		// If the term already exists, wp_insert_term throws an error with the id of the conflicting term
		if (is_wp_error($insertion)) {
			$term_id = $insertion->error_data["term_exists"];
		} else {
			$term_id = $insertion["term_id"];
		}
		if (! empty ( $children ) ) {
			foreach ($children as $child) {
				$this->add_category($child, $term_id);
			}
		}
	}
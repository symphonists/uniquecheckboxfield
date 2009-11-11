<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	class FieldUniqueCheckbox extends Field {
	/*-------------------------------------------------------------------------
		Field definition:
	-------------------------------------------------------------------------*/
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			
			$this->_name = 'Unique Checkbox';
		}
		
		public function createTable() {
			$field_id = $this->get('id');
			
			return $this->_engine->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`value` varchar(255) default NULL,
					`order` int(11) unsigned NOT NULL DEFAULT 0,
					PRIMARY KEY  (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `value` (`value`),
					KEY `order` (`order`)
				)
			");
		}
		
		public function isSortable() {
			return true;
		}
		
		public function canFilter() {
			return true;
		}

		public function allowDatasourceOutputGrouping() {
			return true;
		}
		
		public function allowDatasourceParamOutput() {
			return true;
		}
		
		public function findDefaults(&$fields) {
			$fields = array_merge(array(
				'default_state'		=> 'off',
				'unique_entries'	=> '1',
				'unique_steal'		=> 'on'
			), $fields);
		}
		
	/*-------------------------------------------------------------------------
		Display functions:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(&$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null) {
			$name = $this->get('element_name');
			$description = $this->get('description');
			$title = (empty($description) ? $this->get('label') : $description);
			
			if (!$data) {
				if (isset($_POST) and !empty($_POST)) $value = 'no';
				else if($this->get('default_state') == 'on') $value = 'yes';
				else $value = 'no';
				
			} else {
				$value = ($data['value'] == 'yes' ? 'yes' : 'no');
			}
			
			$input = Widget::Input(
				"fields{$fieldnamePrefix}[$name]{$fieldnamePostfix}", 'yes', 'checkbox',
				($value == 'yes' ? array('checked' => 'checked') : null)
			);
			
			$label = Widget::Label($input->generate() . " {$title}");
			
			if ($flagWithError != null) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
				
			} else {
				$wrapper->appendChild($label);
			}
		}
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper);
			
			$order = $this->get('sortorder');
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			// Long Description:
			$this->appendInput($div, 'Long Description <i>Optional</i>', 'description');
			
			// Unique Size:
			$this->appendInput($div, 'Number of checked entries', 'unique_entries');
			
			$wrapper->appendChild($div);
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			// Default State:
			$this->appendCheckbox($div, 'Checked by default', 'default_state');
			
			// Steal State:
			$this->appendCheckbox($div, 'Steal checked state from other entries', 'unique_steal');
			
			$wrapper->appendChild($div);
			
			$this->appendShowColumnCheckbox($wrapper);
		}
		
		public function appendInput($wrapper, $title, $name) {
			$order = $this->get('sortorder');
			$label = Widget::Label($title);
			$label->appendChild(Widget::Input(
				"fields[{$order}][{$name}]", $this->get($name)
			));
			
			$wrapper->appendChild($label);
		}
		
		public function appendCheckbox($wrapper, $title, $name) {
			$order = $this->get('sortorder');
			$label = Widget::Label();
			$input = Widget::Input(
				"fields[{$order}][{$name}]", 'on', 'checkbox'
			);
			
			if ($this->get($name) == 'on') $input->setAttribute('checked', 'checked');
			
			$label->setValue($input->generate() . " {$title}");
			$wrapper->appendChild($label);
		}
		
	/*-------------------------------------------------------------------------
		Data retrieval functions:
	-------------------------------------------------------------------------*/
		
		public function groupRecords($records) {
			if (!is_array($records) or empty($records)) return;
			
			$name = $this->get('element_name');
			$groups = array($name => array());
			
			foreach ($records as $r) {
				$data = $r->getData($this->get('id'));
				
				$value = $data['value'];
				
				if (!isset($groups[$name][$value])) {
					$groups[$name][$value] = array(
						'attr'		=> array(
							'value'		=> $value
						),
						'records'	=> array(),
						'groups'	=> array()
					);
				}
				
				$groups[$name][$value]['records'][] = $r;
			}
			
			return $groups;
		}
		
		public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC') {
			$field_id = $this->get('id');
			
			$joins .= "
				INNER JOIN
					`tbl_entries_data_{$field_id}` AS ed
					ON (e.id = ed.entry_id)
			";
			$sort = 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "ed.value {$order}");
		}
		
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');
			$value = $this->cleanValue($data[0]);
			
			$joins .= "
				LEFT JOIN
					`tbl_entries_data_{$field_id}` AS t{$field_id}
					ON (e.id = t{$field_id}.entry_id)
			";
			$where .= "
				AND t{$field_id}.value = '{$value}'
			";
			
			return true;
		}
		
		public function prepareTableValue($data, XMLElement $link = null) {
			if (empty($data) or !isset($data['value'])) {
				return ($this->get('default_state') == 'on' ? 'Yes' : 'No');
			}
			
			return parent::prepareTableValue(array('value' => ucfirst($data['value'])), $link);
		}
		
	/*-------------------------------------------------------------------------
		Data processing functions:
	-------------------------------------------------------------------------*/
		
		public function checkPostFieldData($data, &$message, $entry_id = null) {
			$field_id = $this->get('id');
			$entry_id = (integer)$entry_id;
			
			if ($data == 'yes') {
				$allowed = (integer)$this->get('unique_entries');
				$taken = (integer)$this->Database->fetchVar('taken', 0, "
					SELECT
						COUNT(f.id) AS `taken`
					FROM
						`tbl_entries_data_{$field_id}` AS f
					WHERE
						f.value = 'yes'
						AND f.entry_id != {$entry_id}
				");
				
				// Steal from another entry:
				if ($taken >= $allowed and $this->get('unique_steal') == 'on') {
					$this->Database->query("
						UPDATE
							`tbl_entries_data_{$field_id}`
						SET
							`value` = 'no'
						WHERE
							`value` = 'yes'
							AND `entry_id` != {$entry_id}
						ORDER BY
							`order` ASC
						LIMIT 1
					");
					
					$taken--;
				}
			
				if ($taken >= $allowed) {
					$message = "Uncheck another entry first.";
					
					return self::__INVALID_FIELDS__;
				}
			}
			
			return parent::checkPostFieldData($data, $message, $entry_id);
		}
		
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$status = self::__OK__;
			
			return array(
				'value' => ($data ? 'yes' : 'no'),
				'order'	=> time()
			);
		}
		
		public function commit() {
			if (!parent::commit()) return false;
			
			$id = $this->get('id');
			$handle = $this->handle();
			$state = $this->get('default_state');
			$description = $this->get('description');
			$entries = (integer)$this->get('unique_entries');
			$steal = $this->get('unique_steal');
			
			if ($id === false) return false;
			
			$fields = array(
				'field_id'			=> $id,
				'default_state'		=> ($state ? $state : 'off'),
				'description'		=> $description,
				'unique_entries'	=> ($entries > 0 ? $entries : 1),
				'unique_steal'		=> ($steal ? $steal : 'off')
			);
			
			$this->_engine->Database->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");
			
			return $this->_engine->Database->insert($fields, "tbl_fields_{$handle}");
		}
	}
	
?>
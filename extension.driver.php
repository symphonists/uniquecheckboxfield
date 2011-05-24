<?php
	
	class Extension_UniqueCheckboxField extends Extension {
		public function about() {
			return array(
				'name'			=> 'Field: Unique Checkbox',
				'version'		=> '1.1',
				'release-date'	=> '2008-09-27',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://pixelcarnage.com/',
					'email'			=> 'rowan@pixelcarnage.com'
				),
				'description' => 'Allows you to flag one or more entries as special.'
			);
		}
		
		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_uniquecheckbox`");
		}
		
		public function install() {
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_uniquecheckbox` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`default_state` enum('on', 'off') NOT NULL DEFAULT 'on',
					`description` varchar(255),
					`unique_entries` int(11) unsigned NOT NULL DEFAULT 1,
					`unique_steal` enum('on', 'off') NOT NULL DEFAULT 'on',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				)
			");
		}
	}
	
?>
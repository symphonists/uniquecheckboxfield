<?php

if (!defined('__IN_SYMPHONY__')) {
    die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
}

class FieldUniqueCheckbox extends FieldCheckbox
{
    public function __construct()
    {
        parent::__construct();
        $this->_name = __('Unique Checkbox');
    }

    /*-------------------------------------------------------------------------
        Setup:
    -------------------------------------------------------------------------*/

    public function createTable()
    {
        $field_id = $this->get('id');

        return Symphony::Database()->query("
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

    /*-------------------------------------------------------------------------
        Utilities:
    -------------------------------------------------------------------------*/

    public function appendInput($wrapper, $title, $name)
    {
        $order = $this->get('sortorder');
        $label = Widget::Label($title);
        $label->appendChild(Widget::Input(
            "fields[{$order}][{$name}]", $this->get($name)
        ));
        $label->setAttribute('class', 'column');

        $wrapper->appendChild($label);
    }

    public function appendCheckbox($wrapper, $title, $name)
    {
        $order = $this->get('sortorder');
        $label = Widget::Label();
        $label->setAttribute('class', 'column');
        $input = Widget::Input(
            "fields[{$order}][{$name}]", 'on', 'checkbox'
        );

        if ($this->get($name) == 'on') {
            $input->setAttribute('checked', 'checked');
        }

        $label->setValue(__("%s $title", array($input->generate())));
        $label->setAttribute('class', 'column');
        $wrapper->appendChild($label);
    }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

    public function findDefaults(array &$settings)
    {
        $settings = array_merge(array(
            'default_state'     => 'off',
            'unique_entries'    => '1',
            'unique_steal'      => 'on'
        ), $settings);
    }

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        Field::displaySettingsPanel($wrapper, $errors);

        $order = $this->get('sortorder');

        $div = new XMLElement('div');
        $div->setAttribute('class', 'two columns');

        // Unique Size:
        $this->appendInput($div, 'Number of checked entries', 'unique_entries');

        $wrapper->appendChild($div);

        $div = new XMLElement('div');
        $div->setAttribute('class', 'two columns');

        // Checkbox Default State
        $this->appendCheckbox($div, 'Checked by default', 'default_state');

        // Steal State:
        $this->appendCheckbox($div, 'Steal checked state from other entries', 'unique_steal');

        $wrapper->appendChild($div);

        // Requirements and table display
        $this->appendStatusFooter($wrapper);
    }

    public function commit()
    {
        if (!parent::commit()) {
            return false;
        }

        $id = $this->get('id');

        if ($id === false) {
            return false;
        }

        $state = $this->get('default_state');
        $entries = (integer)$this->get('unique_entries');
        $steal = $this->get('unique_steal');

        $fields = array(
            'field_id'          => $id,
            'default_state'     => ($state ? $state : 'off'),
            'unique_entries'    => ($entries > 0 ? $entries : 1),
            'unique_steal'      => ($steal ? $steal : 'off')
        );

        return FieldManager::saveSettings($id, $fields);
    }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $field_id = $this->get('id');
        $entry_id = (integer)$entry_id;

        if ($data == 'yes') {
            $allowed = (integer)$this->get('unique_entries');
            $taken = (integer)Symphony::Database()->fetchVar('taken', 0, "
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
                Symphony::Database()->query("
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

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $data = parent::processRawFieldData($data, $status, $message, $simulate, $entry_id);

        $data['order'] = time();

        return $data;
    }
}

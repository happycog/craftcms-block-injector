<?php
namespace happycog\blockinjector\models;

use happycog\blockinjector\Rule;

class Settings extends \craft\base\Model
{
    /** @var Rule[] */
    public $rules = [];

    /** @var bool */
    public $debug = false;

    public function fields()
    {
        $fields = parent::fields();

        // Don't save rules to project config, as they cannot be serialized
        unset($fields['rules']);

        return $fields;
    }

    public function extraFields()
    {
        return [
            'rules',
        ];
    }
}

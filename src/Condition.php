<?php
namespace happycog\blockinjector;

use happycog\blockinjector\Rule;

class Condition extends \craft\base\Component
{
    /** @var callable|bool */
    public $condition;

    /** @var callable */
    public $callback;

    /** @var callable|bool */
    public $default;

    /** @var bool */
    public $inverse = false;

    // TODO: validate to ensure condition, callback
    public function apply(Rule $rule): Rule
    {
        $condition = is_callable($this->condition) ? ($this->condition)($rule) : $this->condition;

        if ($this->inverse) {
            $condition = !$condition;
        }

        return $rule->getBlocks()->when(
            $condition,
            function ($blocks) use ($rule) {
                return ($this->callback)($rule);
            },
            function ($blocks) use ($rule) {
                return $this->default ? ($this->default)($rule) : $rule;
            },
        );
    }
}

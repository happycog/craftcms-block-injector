<?php
namespace happycog\blockinjector\events;

use yii\base\Event;

class InjectionEvent extends Event
{
    /**
     * @var Collection
     */
    public $blocks;
}

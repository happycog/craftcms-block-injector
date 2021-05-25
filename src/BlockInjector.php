<?php
namespace happycog\blockinjector;

use craft\elements\db\MatrixBlockQuery;
use craft\elements\MatrixBlock;
use craft\events\DefineBehaviorsEvent;
use happycog\blockinjector\behaviors\BlockBehavior;
use happycog\blockinjector\behaviors\InjectableBehavior;
use happycog\blockinjector\models\Settings;
use yii\base\Event;

class BlockInjector extends \craft\base\Plugin
{
    public function init()
    {
        parent::init();
        $this->registerBehaviors();
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    private function registerBehaviors()
    {
        Event::on(
            MatrixBlockQuery::class,
            MatrixBlockQuery::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $event->behaviors['injectable'] = InjectableBehavior::class;
            }
        );
    }
}

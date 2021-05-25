<?php

namespace happycog\blockinjector\behaviors;

use craft\elements\MatrixBlock;
use happycog\blockinjector\BlockInjector;
use happycog\blockinjector\events\InjectionEvent;
use happycog\blockinjector\Rule;
use Illuminate\Support\Collection;
use Stringy\Stringy;
use yii\base\Behavior;

class InjectableBehavior extends Behavior
{
    const EVENT_BEFORE_APPLY_RULES = 'beforeApplyRules';
    const EVENT_AFTER_APPLY_RULES = 'afterApplyRules';

    public function withInjections(): array
    {
        $blocks = (new Collection($this->owner->all()))
            ->pipe(function ($blocks) {
                if ($this->owner->hasEventHandlers(self::EVENT_BEFORE_APPLY_RULES)) {
                    $event = new InjectionEvent([
                        'blocks' => $blocks,
                    ]);
                    $this->owner->trigger(self::EVENT_BEFORE_APPLY_RULES, $event);
                    $blocks = $event->blocks;
                }

                return $blocks;
            })
            ->pipe(function ($blocks) {
                return $this->applyRules($blocks);
            })
            ->pipe(function ($blocks) {
                if ($this->owner->hasEventHandlers(self::EVENT_AFTER_APPLY_RULES)) {
                    $event = new InjectionEvent([
                        'blocks' => $blocks,
                    ]);
                    $this->owner->trigger(self::EVENT_AFTER_APPLY_RULES, $event);
                    $blocks = $event->blocks;
                }

                return $blocks;
            });

        return $blocks->all();
    }

    private function applyRules(Collection $blocks): Collection
    {
        return (new Collection(BlockInjector::getInstance()->getSettings()->rules))
            ->reduce(function (Collection $blocks, Rule $rule): Collection {
                return $rule->apply($blocks);
            }, $blocks);
    }
}

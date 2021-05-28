# Block Injector plugin for Craft CMS 3.x

Inject matrix blocks based on various rules.

## Extending with Events

- `\happycog\blockinjector\behaviors\InjectableBehavior::EVENT_BEFORE_APPLY_RULES`
  - Useful for modifying blocks before rules are applied. See "Splitting Blocks" example.
- `\happycog\blockinjector\behaviors\InjectableBehavior::EVENT_AFTER_APPLY_RULES`
  - Useful for any operations done after rules are applied. See "Debugging Block Types" example.
- `\happycog\blockinjector\Rule::EVENT_DEFINE_BEHAVIORS`, or any other events inherited from `\craft\base\Component`
  - Useful for adding your own rule methods, see "Adding Custom Rule Methods" example.

### Examples

#### `modules/Module.php`

```php
namespace modules;

use Craft;
use craft\elements\db\MatrixBlockQuery;
use craft\elements\MatrixBlock;
use craft\events\DefineBehaviorsEvent;
use craft\helpers\Stringy;
use happycog\blockinjector\behaviors\InjectableBehavior;
use happycog\blockinjector\events\InjectionEvent;
use happycog\blockinjector\Rule;
use Illuminate\Support\Collection;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        parent::init();

        // Adding Custom Rule Methods
        Event::on(
            Rule::class,
            Rule::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $event->behaviors = [
                    RuleBehavior::class
                ];
            }
        );

        // Debugging Block Types
        // Injection rules can get confusing. This dumps out all the block types so you can see where things are getting injected.
        Event::on(
            MatrixBlockQuery::class,
            InjectableBehavior::EVENT_AFTER_APPLY_RULES,
            function (InjectionEvent $event) {
                \Craft::dd($event->blocks->pluck('type.handle'));
            }
        );

        // Splitting Blocks
        // This splits blocks with a rich text field into their own blocks with a single paragraph, so you can perform injection rules on them.
        // Additionally, if a paragraph is less that 200 characters, it is combined with the preceding paragraph.

        Event::on(
            MatrixBlockQuery::class,
            InjectableBehavior::EVENT_BEFORE_APPLY_RULES,
            function (InjectionEvent $event) {
                $event->blocks = $event->blocks->flatMap(function ($block) {
                  if ($block->type->handle !== 'copy' || !$block->text) {
                      return new Collection([$block]);
                  }

                  $splitOn = "\n\n";
                  $paragraphs = new Collection(Stringy::create($block->text)->split($splitOn));
                  $tryAppend = false;

                  return $paragraphs->reduce(function ($blocks, Stringy $paragraph) use (&$tryAppend, $splitOn) {
                      if ($paragraph->length() < 200) {
                          if ($tryAppend) {
                              $lastBlock = $blocks->pop();

                              if ($lastBlock) {
                                  return $blocks->push($lastBlock->append($splitOn)->append($paragraph));
                              }
                          }

                          $tryAppend = true;
                      } else {
                          $tryAppend = false;
                      }

                      return $blocks->push($paragraph);
                  }, new Collection())
                  ->map(function ($text) use ($block): MatrixBlock {
                      $newBlock = clone $block;
                      $newBlock->setFieldValue('text', (string) $text);

                      return $newBlock;
                  });
                });
            }
        );
    }
}
```

#### `modules/RuleBehavior.php`

```php
<?php
namespace modules;

use craft\elements\MatrixBlock;
use yii\base\Behavior;

class RuleBehavior extends Behavior
{
    public function atParagraphInterval(int $interval)
    {
        return $this->owner->atInterval($interval, function (MatrixBlock $block) {
            return $block->type->handle === 'copy';
        });
    }

    public function containsType(string $typeHandle): bool
    {
        return $this->owner->blocks->contains(function ($block) use ($typeHandle) {
            return self::blockIsType($block, $typeHandle);
        });
    }

    public static function blockIsType(?MatrixBlock $block, string $type)
    {
        return $block && $block->type->handle === $type;
    }
}
```

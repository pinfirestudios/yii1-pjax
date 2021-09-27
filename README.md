# Yii 1 Pjax

Backport of [Yii 2's Pjax widget](https://github.com/yiisoft/yii2/blob/master/framework/widgets/Pjax.php) for Yii 1

Works the same as in Yii 2:
```php
use pinfirestudios\\yii1pjax\\Pjax;
...
$this->beginWidget(Pjax::class);
    ...
$this->endWiget();
```

This has only been tested for our specific use case, so there may be bugs.

# JS Libraries
As Composer 2 no longer supports the bower-asset-plugin that Yii2 ships with, you must manually include
the yii2-pjax JS library in your project.

```
npm install yii2-pjax
```

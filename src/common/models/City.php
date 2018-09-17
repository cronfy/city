<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 20.05.18
 * Time: 23:44
 */

namespace cronfy\city\common\models;

use cronfy\city\BaseModule;
use cronfy\library\common\models\ConcreteLibrary;
use Yii;

class City extends ConcreteLibrary
{
    public static function getRootPath()
    {
        /** @var BaseModule $module */
        // Ох, как ужасно. Мы не сможем ипользовать другое имя модуля.
        // Но нам нужно откуда-то взять sid, по которому мы будем искать корневой
        // элемент справочника в Library.
        // Чтобы отвязвться от имени модуля, нужно
        // 1. Создать свою модель City и реализовать в ней корректный getRootPath().
        // 2. Прописать в настрйках модуля city 'cityModel' => \my\city\Model::class,
        // 3. Реализовать везде в модуле поддержку 'cityModel' ¯\_(ツ)_/¯

        // это вариант по умолчанию, который предполагает, что модуль называется city
        $module = Yii::$app->getModule('city');
        return $module->libraryRootPath;
    }
}

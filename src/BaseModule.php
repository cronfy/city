<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 23.10.17
 * Time: 18:34
 */

namespace cronfy\city;

use cronfy\city\common\misc\CityRepository;
use cronfy\city\common\models\City;

class BaseModule extends \yii\base\Module
{

    public $libraryRootPath;
    public $cityModel = City::class;

    public function getControllerPath()
    {
        // Yii определяет путь к контроллеру через алиас по controllerNamespace.
        // Алиас создавать не хочется, так как это лишняя сущность, которая может
        // конфликтовать с другими алиасами (мы - модуль и не знаем, какие алиасы
        // уже используются в приложении). Поэтому определим путь к контроллерам
        // своим способом.
        $rc = new \ReflectionClass(get_class($this));
        return dirname($rc->getFileName()) . '/controllers';
    }

    protected $_cityRepository;

    /**
     * @return CityRepository
     */
    public function getCityRepository() {
        if (!$this->_cityRepository) {
            $this->_cityRepository = new CityRepository();
        }

        return $this->_cityRepository;
    }

}

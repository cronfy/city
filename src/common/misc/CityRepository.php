<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 13.08.18
 * Time: 17:28
 */

namespace cronfy\city\common\misc;

use cronfy\city\common\models\City;
use yii\base\BaseObject;

class CityRepository extends BaseObject
{

    protected $_byId = [];
    protected function getById($id) {
        if (!array_key_exists($id, $this->_byId)) {
            $this->_byId[$id] = City::findOne($id);
        }

        return $this->_byId[$id];
    }


    protected $_byNameVariants = [];

    /**
     * @param $name
     * @return City[]
     */
    public function findByName($name) {
        // е/ё - ищем по всем возможным вариантам
        $nameVariants = $this->getNameVariants($name);

        // ключ - $nameVariants, а не $name, потому что $name не нормализован по е/ё,
        // в отличие от $nameVariants
        sort($nameVariants);
        $key = md5(serialize($nameVariants));

        if (!array_key_exists($key, $this->_byNameVariants)) {
            $ids = [];

            $query = City::find()
                ->where(['name' => $nameVariants])
            ;

            $cities = $query->all();

            foreach ($cities as $city) {
                if (!array_key_exists($city->id, $this->_byId)) {
                    $this->_byId[$city->id] = $city;
                }

                $ids[] = $city->id;
            }
            $this->_byNameVariants[$key] = $ids;
        }

        $ids = $this->_byNameVariants[$key];

        $result = [];

        foreach ($ids as $id) {
            if ($city = $this->getById($id)) {
                $result[] = $city;
            }
        }

        return $result;
    }

    protected function getNameVariants($name) {
        // такая проблема, вернее, две:
        // 1. Город может быть написан как с Е, так и с Ё
        // 2. Mysql не поддерживает regexp по utf8.
        // Приходится изворачиваться.
        $variants = [''];
        for ($i = 0; $i < mb_strlen($name); $i++) {
            $char = mb_substr($name, $i, 1);
            if (in_array($char, ['е', "Е", "ё", "Ё"])) {
                $newVariants = $variants;
                foreach ($newVariants as &$variant) {
                    $variant .= 'е';
                }
                unset($variant);
                foreach ($variants as &$variant) {
                    $variant .= 'ё';
                }
                unset($variant);
                $variants = array_merge($variants, $newVariants);
            } else {
                foreach ($variants as &$variant) {
                    $variant .= $char;
                }
                unset($variant);
            }
        }

        return $variants;
    }
}
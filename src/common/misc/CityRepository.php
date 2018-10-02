<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 13.08.18
 * Time: 17:28
 */

namespace cronfy\city\common\misc;

use cronfy\city\BaseModule;
use cronfy\city\common\models\City;
use yii\base\BaseObject;

class CityRepository extends BaseObject
{
    /** @var BaseModule */
    public $module;

    protected $_byId = [];
    public function getById($id) {
        if (!array_key_exists($id, $this->_byId)) {
            $class = $this->module->cityModel;
            $this->_byId[$id] = $class::findOne($id);
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

    /**
     * Этот метод ищет точное совпадение по имени города, стране и геокоординатам.
     * Если не удалось всеми доступными способами определить, что найденный город является
     * искомым (например, выпало два возможных варианта), или не удалось найти город вообще
     * - возвращает null.
     * порядок lat, lng, как в Google Maps API
     * @param $name
     * @param $lat
     * @param $lng
     * @param null $countryIso
     * @return City|mixed|null
     */
    public function getByNameLatLng($name, $lat, $lng, $countryIso) {
        /** @var $cityVariants City[] */

        $cityVariants = $this->findByName($name);

        if (!$cityVariants) {
            return null;
        }



        // 1. фильтруем по стране
        foreach ($cityVariants as $k => $cityVariant) {
            if ($cityVariant->getGeoname()->getGeonameDTO()->country_code != $countryIso) {
                unset($cityVariants[$k]);
            }
        }




        foreach ($cityVariants as $k => $cityVariant) {
            if (!$this->isMatchesByLatLng($cityVariant, $lat, $lng)) {
                // на всякий случай проверяем все города, а не останавливаемся на
                // первом совпавшем - вдруг совпадений будет несколько, тогда нужно
                // будет вернуть null, а не город.
                unset($cityVariants[$k]);
                continue;
            }
        }

        // Если подошел один и только один город, возвращаем его, если нет - поиск не удался.
        return (count($cityVariants) === 1) ? array_shift($cityVariants) : null;
    }

    /**
     * @param $city City
     * @param $lat
     * @param $lng
     * @return bool
     */
    protected function isMatchesByLatLng($city, $lat, $lng) {
        $geonameDTO = $city->getGeoname()->getGeonameDTO();

        $currentLat = $geonameDTO->latitude;
        $currentLng = $geonameDTO->longitude;

        $distanceM = $this->vincentyGreatCircleDistance($lat, $lng, $currentLat, $currentLng);

        if ($distanceM < 10000) {
            // расстояние менее 10 км, значит это наш город
            return true;
        }

        return false;
    }

    /**
     * https://stackoverflow.com/a/10054282/1775065
     *
     * Calculates the great-circle distance between two points, with
     * the Vincenty formula.
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     * @return float Distance between points in [m] (same as earthRadius)
     */
    protected function vincentyGreatCircleDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000.0)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }

}
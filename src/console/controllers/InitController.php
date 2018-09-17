<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 20.05.18
 * Time: 23:54
 */

namespace cronfy\city\console\controllers;

use cronfy\city\console\Module;
use cronfy\geoname\BaseModule;
use cronfy\geoname\common\models\Geoname;
use cronfy\library\common\misc\LibraryHelper;
use cronfy\library\common\models\Library;
use modules\city\common\models\City;
use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;

/**
 * @property Module $module
 */
class InitController extends Controller
{
    public function actionHelp() {
        echo "Read my code";

        /*

        Для инициализации (делается один раз перед загрузкой первой страны):

        1. Инициализировать geonames:

        ./yii geoname/init/admin1-codes
        ./yii geoname/init/hierarchy


        Для загрузки новой страны:

        1. Узнать ее 2-символьный ISO-код http://download.geonames.org/export/dump/countryInfo.txt
           Например, Казахстан - KZ
        2. Подгрузить информацию geonames по этой стране
            ./yii geoname/init/country-geonames KZ
            ./yii geoname/init/alternate-names KZ
        3. Обновить локальную таблицу geonames в БД по стране
            ./yii geoname/import/geonames KZ
        4. Обновить таблицу городов сайта
            ./yii city/init/by-geonames

        Теперь можно выбрать город в выпадающем списке.

        Для подгрузки городов в CDEK:

        1. Скачать интеграцию CDEK, файл CDEK_city.zip
        2. Там взять соответствующий файл по стране
        3. Подгрузить его через
            ./yii cdek/init/cities /path/to/file.xls
        4. В бизнес-логике прописать, что по этой стране доставка через CDEK



         */
    }

    public function actionLibraryRoot()
    {
        $libraryRootPath = $this->module->libraryRootPath;
        if (count($libraryRootPath) > 1) {
            throw new \Exception("You need to implement own InitController to support libraryRootPath deeper than 1 sid");
        }

        $sid = $libraryRootPath[0];
        if ($library = Library::find()->andWhere(['pid' => null, 'sid' => $sid])->one()) {
            echo "Already exists\n";
            return;
        }

        $library = new Library([
            'name' => 'Города',
            'pid' => null,
            'sid' => $libraryRootPath[0],
        ]);

        $library->ensureSave();

        echo "OK";
    }

    public function actionDestroy()
    {
        $libraryRootPath = $this->module->libraryRootPath;
        $library = LibraryHelper::getByPath($libraryRootPath);
        Library::deleteAll(['pid' => $library->id]);
    }

    public function actionByGeonames()
    {
        /** @var BaseModule $geonameModule */
        $geonameModule = Yii::$app->getModule('geoname');

        foreach (Geoname::find()->andWhere(['type' => 'city'])->all() as $geoname) {
            /** @var Geoname $geoname */
            if (!$existingCities = City::find()->andWhere(['name' => $geonameModule::yeYoOptions($geoname->name)])->all()) {
                $city = null;
            } else {
                $city = null;
                foreach ($existingCities as $existingCity) {
                    if ($existingCity['data']['geonameId'] == $geoname->geonameId) {
                        $city = $existingCity;
                        break;
                    }
                }
            }

            if ($city !== null) {
                echo ".";
            } else {
                $city = new City();
                echo "+";
            }

            $city->setAttributes([
                'name' => $geoname->name,
                'is_active' => true,
            ]);

            $city->data['is_popular']      = $geoname->data['population'] > 450000;
            $city->data['is_very_popular'] = in_array($geoname->name, ['Москва', 'Санкт-Петербург']);
            $city->data['is_default']      = $geoname->name == 'Санкт-Петербург';

            if ($geoname->name == 'Санкт-Петербург') {
                $city->sid = 'spb';
            }

            $city->data['geonameId'] = $geoname->geonameId;
            $region = Geoname::findOne(['geonameId' => $geoname->data['regionGeonameId']]);
            $city->data['regionName'] = $region->name;
            $city->ensureSave();
        }
        echo "\n";
    }

    public function actionMarkCrimea() {
        echo "Marking Crimea cities...\n";
        // отметить города Крыма

        $sevastopolGeonameId = 694423;
        $crimeaGeonameId = 703883;

        /** @var Geoname[] $crimeaGeonames */
        $crimeaGeonames = Geoname::find()->andWhere([
            'and',
            ['type' => 'city'],
            ['like', 'data', "\"regionGeonameId\":$crimeaGeonameId"]
        ])
            ->all()
        ;

        $geonameIds = ArrayHelper::getColumn($crimeaGeonames, 'geonameId');
        $geonameIds[] = $sevastopolGeonameId;

        foreach ($geonameIds as $geonameId) {
            /** @var City $city */
            $city = City::find()->andWhere(
                ['like', 'data', "\"geonameId\":$geonameId"]
            )->one();

            $cityGeoname = $city->getGeoname();

            do {
                if ($cityGeoname->geonameId === $sevastopolGeonameId) {
                    break;
                }

                if ($cityGeoname->data['regionGeonameId'] === $crimeaGeonameId) {
                    break;
                }

                // попался левый город
                // это могло случиться, так как поиск по БД был по неточным параметрам
                echo " --- {$city->name}";
                continue 2;
            } while (false);

            $city->data['crimea'] = true;
            $city->ensureSave();

            echo "{$city->name}\n";
        }

        echo "Ok.\n";

    }
}

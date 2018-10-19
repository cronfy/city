<?php
/**
 * Created by PhpStorm.
 * User: cronfy
 * Date: 20.05.18
 * Time: 23:54
 */

namespace cronfy\city\console\controllers;

use cronfy\city\common\models\City;
use cronfy\city\console\Module;
use cronfy\geoname\common\misc\GeonameService;
use cronfy\geoname\common\models\Geoname;
use cronfy\geonameLink\common\misc\GeonameSelections;
use cronfy\geonameLink\common\misc\Service;
use cronfy\library\common\misc\LibraryHelper;
use cronfy\library\common\models\Library;
use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;

/**
 * @property Module $module
 */
class InitController extends Controller
{
    public $geonameSelections;

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

    /**
     * @return Service
     */
    protected function getGeonameLinkService() {
        return Yii::$app->getModule('geonameLink')->getService();
    }

    protected $_geonameSelections;

    /**
     * @return GeonameSelections
     * @throws \yii\base\InvalidConfigException
     */
    protected function getGeonameSelections() {
        if (!$this->_geonameSelections) {
            /** @var GeonameSelections $geonameSelections */
            $geonameSelections = Yii::createObject($this->geonameSelections);
            $geonameSelections->geonamesService = $this->getGeonamesService();
            $this->_geonameSelections = $geonameSelections;
        }

        return $this->_geonameSelections;
    }

    /**
     * @return GeonameService
     */
    protected function getGeonamesService() {
        return Yii::$app->getModule('geoname')->getGeonamesService();
    }

    public function actionByGeonames($update = false)
    {
        $update = $update === 'update';

        $geonamesService = $this->getGeonamesService();
        $geonameLinkService = $this->getGeonameLinkService();
        $ruNames = $geonameLinkService->getDataFromFile('ru-names');
        $selections = $this->getGeonameSelections();

        foreach ($selections->local() as $geonameDto) {
            if (!$existingCities = City::find()->andWhere(['like', 'data', 'geonameId":' . $geonameDto->geonameid])->all()) {
                $city = null;
            } else {
                if (count($existingCities) > 1) {
                    throw new \Exception("Too many cities with geonameid {$geonameDto->geonameid} found in city database");
                }
                $city = array_shift($existingCities);
            }

            if ($city !== null) {
                if (!$update) {
                    echo "=";
                    continue;
                }

                echo ".";
            } else {
                $city = new City();
                $city->is_active = true;
                $city->data['geonameId'] = $geonameDto->geonameid;
                echo "+";
            }

            if (!$ruName = $ruNames[$geonameDto->geonameid]) {
                D($geonameDto);
                throw new \Exception("Selected geoname {$geonameDto->geonameid}, but ruName is not known.");
            }

            $city->name = $ruName;

            $city->data['is_popular']      = $geonameDto->population > 450000;
            $city->data['is_very_popular'] = in_array($ruName, ['Москва', 'Санкт-Петербург']);
            $city->data['is_default']      = $ruName == 'Санкт-Петербург';

            if ($ruName == 'Санкт-Петербург') {
                $city->sid = 'spb';
            }

            $regionGeoname = $geonamesService->getRegionByGeoname($geonameDto);
            $regionRuName = $this->getGeonamesService()->getOfficialNameByGeoname($regionGeoname, 'ru');
            $city->data['regionName'] = $regionRuName ? $regionRuName->alternate_name : $regionGeoname->name;

//            if (!$city->isNewRecord && $city->dirtyAttributes) {
//                $old = [];
//                $dirty = [];
//                $oldA = $city->oldAttributes;
//                foreach ($city->dirtyAttributes as $name => $value) {
//                    $oldValue = @$oldA[$name];
//                    if ($name === 'data') {
//                        $newValue = (string) $value;
//                        if ($oldValue === $newValue) {
//                            continue;
//                        }
//
//                        $dirty[$name] = $newValue;
//                        $old[$name] = $oldValue;
//                    } else {
//                        $dirty[$name] = $value;
//                        $old[$name] = $oldValue;
//                    }
//                }
//                if ($dirty) {
//                    E([
//                        $old,
//                        $dirty,
//                    ]);
//                }
//            }

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

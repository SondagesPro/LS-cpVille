<?php

/**
 * cpVille Plugin for LimeSurvey
 * Allow user to enter part of postal code or town and get the insee code in survey
 * Permet aux répondants de saisir une partie du code postal ou de la ville en choix, et récupérer le code postal
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015-2021 Denis Chenu <http://sondages.pro>
 * @copyright 2015 Observatoire Régional de la Santé (ORS) - Nord-Pas-de-Calais <http://www.orsnpdc.org/>
 * @copyright 2016 Formations logiciels libres - 2i2l = 42 <http://2i2l.fr/>
 * @license GPL v3
 * @version 4.2.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */
class cpVille extends PluginBase
{
    protected $storage = 'DbStorage';

    protected static $description = 'Insee, code postaux et ville';
    protected static $name = 'cpVille';

    private $tableUpdated = false;

    protected $settings = array(
        'versionInfo' => array(
            'type' => 'info',
            'content' => '<div class= "alert alert-info">Data version is %s. Database version is %s</div>',
        ),
        'disableUpdateDb' => array(
            'type' => 'boolean',
            'label' => 'Keep current database when plugin is updated',
            'help' => 'If the plugin updates the database version, your current database will be reset to the new one. Activate this option if you want to keep your current database.',
            'default' => 1,
        ),
        /* Default auto */
        'answerLibel' => array(
            'type' => 'string',
            'label' => 'Code de la sous question de saisie automatisée (toute question de type multiple-texte avec cette sous question sera utilisée directement)',
            'default' => 'SaisieVille',
        ),
        'formatVisualisation' => array(
            'type' => 'select',
            'label' => 'Format des réponses affichées dans la liste',
            'options' => array(
                'Libel' => 'Libellé',
                'CpLibel' => '[Code postal] Libellé',
            ),
            'default' => 'CpLibel'
        ),
        'formatValeur' => array(
            'type' => 'select',
            'label' => 'Format de la réponse finale affichée',
            'options' => array(
                'Libel' => 'Libellé',
                'CpLibel' => '[Code postal] Libellé',
            ),
            'default' => 'Libel',
        ),
        'limitlist' => array(
            'type' => 'int',
            'label' => 'Nombre de réponses retournées (max)',
            'default' => 10
        ),
        'orderby' => array(
            'type' => 'select',
            'label' => 'Ordonner les réponses selon :',
            'options' => array(
                'pop' => 'Population',
                'nom' => 'Nom (alphabétique)',
            ),
            'default' => 'pop',
            'help' => "Uniquement pour la recherche par texte"
        ),
        'answerCp' => array(
            'type' => 'string',
            'label' => 'Code de la réponse pour le Code postal',
            'default' => 'CodePostal',
        ),
        'answerInsee' => array(
            'type' => 'string',
            'label' => 'Code de la réponse pour le code INSEE',
            'default' => 'Insee',
        ),
        'answerNom' => array(
            'type' => 'string',
            'label' => 'Code de la réponse pour le nom de la ville',
            'default' => 'Nom',
        ),
        'showCp' => array(
            'type' => 'boolean',
            'label' => 'Afficher la réponse code postal (en lecture seulement)',
            'default' => 0,
        ),
        'showInsee' => array(
            'type' => 'boolean',
            'label' => 'Afficher la réponse code insee (en lecture seulement)',
            'default' => 0,
        ),
        'otherColumns' => array(
            'type' => 'text',
            'label' => 'Autre colonnes à remonter automatiquement et à masquer.',
            'help' => 'Une valeur par ligne, ces colonnes seront masquées automatiquement. Les noms "label" et "value" sont reservés.',
            'default' => "cp\nnomsimple\nregion\ndepartement\npopulation\npopulationint\n",
        ),
        'showCopyright' => array(
            'type' => 'boolean',
            'label' => 'Ne pas afficher le copyright des données. Attention : assurez vous d’avoir les droits si vous décochez cette option.',
            'default' => 0,
        ),
    );

    /**
     * @var the csv file name to load
     */
    private $csvFileName = "insee_cp_ville.txt";

    /**
     * @const the csv file version number to load
     */
    public const csvFileVersion = 3;

    /**
     * @const the database version
     */
    public const dbVersion = 3;

    public function init()
    {
        $this->subscribe('beforeActivate');

        $this->subscribe('beforeQuestionRender');
        $this->subscribe('newDirectRequest');

        Yii::setPathOfAlias('cpVille', dirname(__FILE__));
        $this->createCpVillePackage();
    }

    /**
     * @see parent:getPluginSettings
     */
    public function getPluginSettings($getValues = true)
    {
        if (!Permission::model()->hasGlobalPermission('settings', 'read')) {
            throw new CHttpException(403);
        }
        if ($getValues) {
            if(!$this->get('disableUpdateDb', null, null, 1)) {
                $oPlugin = Plugin::model()->findByPk($this->getId());
                if ($oPlugin && $oPlugin->active) {
                    if (floatval($this->get('tableVersion', null, null, 0)) < self::csvFileVersion) {
                        $sTableName = self::tableName('insee_cp');
                        if (in_array($sTableName, Yii::app()->db->schema->getTableNames())) {
                            App()->getDb()->createCommand()->dropTable($sTableName);
                            Yii::app()->setFlashMessage(gT("The plugin's table was deleted in order to be updated"));
                            $this->_insertInseeCp();
                        }
                    }
                    $this->_checkAndUpdateTable();
                }
            }

        }
        $this->settings['versionInfo']['content'] = sprintf(
            $this->settings['versionInfo']['content'],
            $this->get('tableVersion', null, null, 0),
            $this->get('dbVersion', null, null, 0)
        );
        $settings = parent::getPluginSettings($getValues);
        if ($getValues) {
            $otherExtraColumns = $this->getExtraOtherColumns();
            $settings['otherColumns']['current'] = implode("\n",$otherExtraColumns);
        }
        return $settings;
    }
    public function beforeActivate()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $oEvent = $this->getEvent();
        $this->getEvent()->set('success', $this->_insertInseeCp());
    }

    private function _checkAndUpdateTable()
    {
        $pluginId = $this->getId();

        if ($this->get('dbVersion', null, null, 0) < 2) {
            try {
                $oTransaction = Yii::app()->getDb()->beginTransaction();
                $tableName = $this->tableName('insee_cp');
                if (!empty($this->api->getTable($this, 'insee_cp')->getTableSchema()->primaryKey)) {
                    Yii::app()->getDb()->createCommand()->dropPrimaryKey('inseecp_cp_insee', $tableName);
                    Yii::app()->getDb()->createCommand()->dropIndex('inseecp_nomsimple', $tableName);
                    Yii::app()->getDb()->createCommand()->dropIndex('inseecp_departement', $tableName);
                    Yii::app()->getDb()->createCommand()->dropIndex('inseecp_cp_departement', $tableName);
                    Yii::app()->getDb()->createCommand()->dropIndex('inseecp_nomsimple_departement', $tableName);
                    Yii::app()->getDb()->createCommand()->dropIndex('inseecp_cp_nomsimple', $tableName);
                }
                Yii::app()->getDb()->createCommand()->addColumn($tableName, 'id', 'pk');
                Yii::app()->getDb()->createCommand()->alterColumn($tableName, 'insee', 'string(5) NOT NULL');
                Yii::app()->getDb()->createCommand()->alterColumn($tableName, 'nom', 'text NOT NULL');
                Yii::app()->getDb()->createCommand()->alterColumn($tableName, 'cp', 'string(5) NOT NULL');
                Yii::app()->getDb()->createCommand()->alterColumn($tableName, 'nomsimple', 'string(50) NOT NULL');
                Yii::app()->getDb()->createCommand()->alterColumn($tableName, 'region', 'string(2) NOT NULL');
                Yii::app()->getDb()->createCommand()->alterColumn($tableName, 'departement', 'string(3) NOT NULL');
                Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_insee', $tableName, 'insee,cp');
                Yii::app()->getDb()->createCommand()->createIndex('inseecp_nomsimple', $tableName, 'nomsimple');
                Yii::app()->getDb()->createCommand()->createIndex('inseecp_departement', $tableName, 'departement');
                Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_departement', $tableName, 'cp,departement');
                Yii::app()->getDb()->createCommand()->createIndex('inseecp_nomsimple_departement', $tableName, 'nomsimple,departement');
                Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_nomsimple', $tableName, 'cp,nomsimple');
                $oTransaction->commit();
            } catch (Exception $e) {
                $oTransaction->rollback();
                Yii::app()->setFlashMessage("An error happened during update : <div>" . $e->getMessage() . "</div>", 'warning');
                return;
            }
            $this->set("dbVersion", 2);
            Yii::app()->setFlashMessage("Database version updated to 2", 'success');
        }
        if ($this->get('dbVersion', null, null, 0) < 3) {
            /* This one must be unique … */
            $tableName = $this->tableName('insee_cp');
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_insee_nomsimple', $tableName, 'insee,cp,nomsimple', true);
            $this->set("dbVersion", 3);
            Yii::app()->setFlashMessage("Database version updated to 3", 'success');
        }
    }
    /**
     * create and insert database
     * @return boolean : success
     */
    private function _insertInseeCp()
    {
        if (!$this->api->tableExists($this, 'insee_cp')) {
            if (!is_readable(dirname(__FILE__) . "/" . $this->csvFileName)) {
                $this->getEvent()->set('success', false);
                $this->getEvent()->set('message', 'Cannot read file :' . dirname(__FILE__) . "/" . $this->csvFileName . '.');
                return false;
            }
            $tableName = $this->tableName('insee_cp');
            $this->api->createTable($this, 'insee_cp', array(
                'id' => 'pk',
                'insee' => 'string(5) NOT NULL',
                'nom' => 'text NOT NULL',
                'cp' => 'string(5) NOT NULL',
                'nomsimple' => 'string(50)',
                'region' => 'string(2) NOT NULL',
                'departement' => 'string(3) NOT NULL',
                'population' => 'float',
                'populationint' => 'int',
            ));
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_insee_nomsimple', $tableName, 'insee,cp,nomsimple', true);
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_insee', $tableName, 'insee,cp');
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_nomsimple', $tableName, 'nomsimple');
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_departement', $tableName, 'departement');
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_departement', $tableName, 'cp,departement');
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_nomsimple_departement', $tableName, 'nomsimple,departement');
            Yii::app()->getDb()->createCommand()->createIndex('inseecp_cp_nomsimple', $tableName, 'cp,nomsimple');
            /* TODO : add index */
            if (!$this->_addDataToTable()) {
                App()->getDb()->createCommand()->dropTable($tableName);
                return false;
            }
            $this->tableUpdated = true;
            parent::saveSettings(array(
                'tableVersion' => self::csvFileVersion,
                'dbVersion' => self::dbVersion
            ));
        }
        if ($this->tableUpdated) {
            Yii::app()->db->schema->getTables();
            Yii::app()->db->schema->refresh();
            Yii::app()->setFlashMessage(gT("The plugin's table was created and updated"));
        }
    }

    /**
     * Add data to table
     * @return boolean : success
     */
    private function _addDataToTable()
    {
        $fHandle = fopen(dirname(__FILE__) . "/" . $this->csvFileName, 'r');
        $lineNum = 0;
        $tableName = $this->tableName('insee_cp');
        while (($aData = fgetcsv($fHandle)) !== false) {
            $lineNum++;
            if ($lineNum > 1) { // We don't do the header
                if (strlen($aData[0]) <= 5) {
                    try {
                        $insertResult = App()->db->createCommand()
                            ->insert(
                                $tableName,
                                array(
                                    'insee'         => str_pad($aData[0], 5, '0', STR_PAD_LEFT),
                                    'nom'           => $aData[1],
                                    'cp'            => str_pad($aData[2], 5, '0', STR_PAD_LEFT),
                                    'nomsimple'     => $aData[3],
                                    'region'        => $aData[4],
                                    'departement'   => $aData[5],
                                    'population'    => $aData[7],
                                    'populationint' => $aData[9]
                                )
                            );
                    } catch (Exception $ex) {
                        Yii::app()->setFlashMessage(
                            "Error happen update table for insee {$aData[0]} at line {$lineNum} with error <div>"
                            . $ex->getMessage()
                            . "</div> and data : <pre>" . print_r($aData, 1)
                            . "</pre>",
                            'error'
                        );
                        fclose($fHandle);
                        return false;
                    }
                } else {
                    fclose($fHandle);
                    Yii::app()->setFlashMessage("Invalid line for insee {$aData[0]} at line {$lineNum}");
                    return false;
                }
            }
        }
        fclose($fHandle);
        return true;
    }

    public function beforeQuestionRender()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $oEvent = $this->getEvent();
        
        if ($oEvent->get('type') == "Q") {
            $iQid = $oEvent->get('qid');
            $oSaisieSubQuestion = Question::model()->find('parent_qid=:qid and title=:title', array(':qid' => $iQid,':title' => $this->get('answerLibel', null, null, $this->settings['answerLibel']['default'])));
            if ($oSaisieSubQuestion) {
                $aOption = array(
                    'answerLibel' => $this->get('answerLibel', null, null, $this->settings['answerLibel']['default']),
                    'answerCp' => $this->get('answerCp', null, null, $this->settings['answerCp']['default']),
                    'answerInsee' => $this->get('answerInsee', null, null, $this->settings['answerInsee']['default']),
                    'answerNom' => $this->get('answerNom', null, null, $this->settings['answerNom']['default']),
                    'showCp' => intval($this->get('showCp', null, null, $this->settings['showCp']['default'])),
                    'showInsee' => intval($this->get('showInsee', null, null, $this->settings['showInsee']['default'])),
                    'otherColumns' => $this->getExtraOtherColumns(),
                    'placeholder' => $this->_translate("Enter some characters or the start of your zipcode."),
                );

                $sTipCopyright = 'Data : <a href= "https://www.data.gouv.fr/fr/datasets/base-officielle-des-codes-postaux/" target= "_blank">Base officielle des codes postaux</a> ©La Poste, <a href= "http://www.insee.fr/fr/bases-de-donnees/default.asp?page=recensements.htm" target= "_blank">Insee, Recensements de la population</a> ©Insee';
                $oEvent->set('class', $oEvent->get('class') . " saisieville saisieauto");
                if (!$this->get('showCopyright', null, null, $this->settings['showCopyright']['default'])) {
                    $oEvent->set('answers', $oEvent->get('answers') . "<p class='tip'><small>" . $sTipCopyright . "</small></p>");
                }
                if ($oEvent->get('man_message')) {
                    // If we don't have other sub question : we must update the mandatory tip, used default from LS ...
                    $aThisSubQ = array(
                        $this->get('answerLibel', null, null, $this->settings['answerLibel']['default']),
                        $this->get('answerCp', null, null, $this->settings['answerCp']['default']),
                        $this->get('answerInsee', null, null, $this->settings['answerInsee']['default']),
                        $this->get('answerNom', null, null, $this->settings['answerNom']['default']),
                    );
                    $oCriteria = new CDbCriteria();
                    $oCriteria->addNotInCondition('title', $aThisSubQ);
                    $oCriteria->compare('parent_qid', $iQid);
                    $iCountOtherQuestion = Question::model()->count($oCriteria);
                    if (!$iCountOtherQuestion) {
                        $man_message = "<strong><br /><span class='errormandatory'>" . gT('This question is mandatory') . ".  </span></strong>\n";
                        if (version_compare(App()->getConfig('versionnumber'), 3, ">= ") && version_compare(App()->getConfig('versionnumber'), 4, "<")) {
                            $man_message = Yii::app()->getController()->renderPartial('//survey/questions/question_help/mandatory_tip', array(
                                'sMandatoryText' => gT('This question is mandatory'),
                            ), true);
                        }
                        if (version_compare(App()->getConfig('versionnumber'), 4, ">= ")) {
                            $man_message = App()->twigRenderer->renderPartial(
                                '/survey/questions/question_help/mandatory_tip.twig',
                                array(
                                    'sMandatoryText' => gT('This question is mandatory'),
                                    'part' => 'initial',
                                    'qInfo' => array(),
                                )
                            );
                        }
                        $oEvent->set('man_message', $man_message);
                    }
                }

                App()->clientScript->registerPackage('cpVille');
                $aOption['jsonurl'] = $this->api->createUrl('plugins/direct', array('plugin' => get_class($this),'function' => 'auto'));
                $sScript = "autoCpVille({$oEvent->get('qid')}," . ls_json_encode($aOption) . ");";
                App()->clientScript->registerScript("autoCpVille{$iQid}", $sScript, CClientScript::POS_END);
            }
        }
    }

    public function newDirectRequest()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }

        $oEvent = $this->event;
        if ($oEvent->get('target') == "cpVille") {
            $this->actionAuto();
        }
    }

    private function actionAuto()
    {
        $iSurveyId = Yii::app()->session['LEMsid'];
        if (!$iSurveyId) {
            $this->displayJson(null);
        }
        Yii::app()->setLanguage(Yii::app()->session['LEMlang']);
        $sParametre = trim(strval(Yii::app()->request->getParam('term')));
        // Some update directly
        $sParametre = strtr($sParametre, array(
          "/" => " SUR ",
        ));
        /* get the collation according to db */
        $collate = "";
        switch (Yii::app()->db->driverName) {
            case 'mysql':
            case 'mysqli':
                $collate = " COLLATE utf8mb4_general_ci";
                if (Yii::app()->getConfig('DBVersion') < 257) {
                    $collate = " COLLATE utf8_general_ci";
                }
                break;
            case 'pgsql':
                // Not tested
                break;
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
                // Not tested
                break;
            default:
                // Unknow DB
                break;
        }
        $iLimit = (int)$this->get('limitliste');
        $iLimit = ($iLimit > 0) ? $iLimit : 10;
        $sOrderBy = $this->get('orderby');
        switch ($sOrderBy) {
            case 'nom':
                $sOrderBy = "nom asc";
                break;
            default:
                $sOrderBy = "population desc";
                break;
        }
        $extraOthersColumns = $this->getExtraOtherColumns();
        $aTowns = array();
        if ($sParametre !== "") {
            $aParametres = preg_split("/(’| |\'|=|-)/", $sParametre);
            if (count($aParametres) == 1 && ctype_digit($sParametre)) {
                $aTowns = Yii::app()->db->createCommand()
                    ->select('*')
                    ->from(self::tableName('insee_cp'))
                    ->where(
                        Yii::app()->db->quoteColumnName('cp') . " LIKE :cp",
                        array(':cp' => "{$sParametre}%")
                    )
                    ->order($sOrderBy)
                    ->limit($iLimit)
                    ->queryAll();
            } elseif (count($aParametres) == 1) {
                $sParametre = addcslashes(self::replaceSomeString($sParametre), '%_');
                $aTowns = Yii::app()->db->createCommand()
                    ->select('*')
                    ->from(self::tableName('insee_cp'))
                    ->where(
                        Yii::app()->db->quoteColumnName('nomsimple') . " {$collate} LIKE :nomsimple OR " . Yii::app()->db->quoteColumnName('nomsimple') . " {$collate} LIKE :nomsimplespace OR " . Yii::app()->db->quoteColumnName('cp') . " LIKE :cp",
                        array(':nomsimple' => "{$sParametre}%",':nomsimplespace' => "% {$sParametre}%",':cp' => "{$sParametre}%")
                    )
                    ->order($sOrderBy)
                    ->limit($iLimit)
                    ->queryAll();
            } else {
                $oTowns = Yii::app()->db->createCommand()
                    ->select('*')
                    ->from(self::tableName('insee_cp'))
                    ->where("1=1");
                $aParams = array();
                $count = 1;
                $dbColumn = Yii::app()->db->quoteColumnName('nomsimple');
                $dbCpColumn = Yii::app()->db->quoteColumnName('cp');
                foreach ($aParametres as $sParametre) {
                    $sParametre = trim($sParametre);
                    if (!empty($sParametre)) {
                        if (ctype_digit($sParametre)) {
                            $oTowns->andWhere("{$dbCpColumn} LIKE :cpstart{$count} OR {$dbColumn} {$collate} LIKE :start{$count} OR {$dbColumn} {$collate} LIKE :space{$count}");
                            $aParams[":cpstart{$count}"] = "{$sParametre}%";
                            $aParams[":start{$count}"] = "{$sParametre}%";
                            $aParams[":space{$count}"] = "% {$sParametre}%";
                        } else {
                            $sParametre = addcslashes(self::replaceSomeString($sParametre), '%_');
                            $oTowns->andWhere("{$dbColumn} {$collate} LIKE :start{$count} OR {$dbColumn} {$collate} LIKE :space{$count}");
                            $aParams[":start{$count}"] = "{$sParametre}%";
                            $aParams[":space{$count}"] = "% {$sParametre}%";
                        }
                        $count++;
                    }
                }
                $oTowns->order($sOrderBy);
                $oTowns->limit($iLimit);
                $oTowns->params = $aParams;
                $aTowns = $oTowns->queryAll();
            }
            $aReturnArray = array();
            foreach ($aTowns as $aTown) {
                switch ($this->get('formatVisualisation')) {
                    case 'Libel':
                        $sLabel = $aTown['nom'];
                        break;
                    case 'CpLibel':
                    default:
                        $sLabel = "[{$aTown['cp']}] {$aTown['nom']}";
                        break;
                }
                switch ($this->get('formatValeur')) {
                    case 'CpLibel':
                        $sValue = "[{$aTown['cp']}] {$aTown['nom']}";
                        break;
                    case 'Libel':
                    default:
                        $sValue = $aTown['nom'];
                        break;
                }
                $fixedLabelTown = array(
                    'label' => $sLabel,
                    'value' => $sValue,
                    $this->get('answerCp', null, null, $this->settings['answerCp']['default']) => $aTown["cp"],
                    $this->get('answerInsee', null, null, $this->settings['answerInsee']['default']) => $aTown["insee"],
                    $this->get('answerNom', null, null, $this->settings['answerNom']['default']) => $aTown["nom"],
                );
                $aTown = array_intersect_key($aTown, array_flip($extraOthersColumns));
                $addArray = array_replace(
                    $aTown,
                    $fixedLabelTown
                );
                $aReturnArray[] = $addArray;
            }
            if (!count($aReturnArray)) {
                $aReturnArray[] = array(
                    'label' => $this->_translate("No city with this name, please check it."),
                    'value' => "",
                    $this->get('answerCp', null, null, $this->settings['answerCp']['default']) => "",
                    $this->get('answerInsee', null, null, $this->settings['answerInsee']['default']) => "",
                    $this->get('answerNom', null, null, $this->settings['answerNom']['default']) => "",
                );
            }
            $this->displayJson($aReturnArray);
        }
        $this->displayJson(null);
    }

    private function displayJson($aArray)
    {
        Yii::import('application.helpers.viewHelper');
        viewHelper::disableHtmlLogging();
        header('Content-type: application/json');
        echo json_encode($aArray);
        Yii::app()->end();
    }

    public static function tableName($tableName)
    {
        return App()->getDb()->tablePrefix . "cpville_{$tableName}";
    }

    public static function replaceSomeString($string)
    {
        $aReplace = array(
          "STE" => "SAINTE",
          "ST" => "SAINT",
          "/" => "SUR",
        );
        if (array_key_exists(strtoupper($string), $aReplace)) {
            return $aReplace[strtoupper($string)];
        }
        return $string;
    }

    private function _translate($sToTranslate, $sEscapeMode = 'unescaped', $sLanguage = null)
    {
        if (method_exists($this, "gT")) {
            return $this->gT($sToTranslate, $sEscapeMode, $sLanguage);
        }
        return $sToTranslate;
    }

    /**
    * Add the packages needed
    * @return void
    */
    private function createCpVillePackage()
    {
        Yii::app()->clientScript->addPackage('cpVille', array(
            'basePath'    => 'cpVille.assets',
            'js'          => array(
                'cpville.js'
            ),
            'css'          => array(
                'cpville.css'
            ),
            'depends'      => array(
                'devbridge-autocomplete',
            ),
        ));
    }
    
    /**
     * get the default column to be returned
     */
    private function getExtraOtherColumns() {
        $extraColumns = $this->get('otherColumns', null, null, "cp\nnomsimple\nregion\ndepartement\npopulation\npopulationint\n");
        $extraColumns = preg_split('/\r\n|\r|\n/', $extraColumns,-1, PREG_SPLIT_NO_EMPTY);
        $extraColumns = array_map('sanitize_paranoid_string', $extraColumns);
        $extraColumns = array_filter($extraColumns);
        return $extraColumns;
    }
}

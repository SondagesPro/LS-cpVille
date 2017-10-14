<?php
/**
 * cpVille Plugin for LimeSurvey
 * Allow user to enter part of postal code or town and get the insee code in survey
 * Permet aux répondants de saisir une partie du code postal ou de la ville en choix, et récupérer le code postal
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015 Denis Chenu <http://sondages.pro>
 * @copyright 2015 Observatoire Régional de la Santé (ORS) - Nord-Pas-de-Calais <http://www.orsnpdc.org/>
 * @copyright 2016 Formations logiciels libres - 2i2l = 42 <http://2i2l.fr/>
 * @license GPL v3
 * @version 2.1.1
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
class cpVille extends \ls\pluginmanager\PluginBase {
    protected $storage = 'DbStorage';

    static protected $description = 'Insee, code postaux et ville';
    static protected $name = 'cpVille';

    private $tableUpdated=false;
    protected $settings = array(
        /* Default auto */
        'answerLibel' => array(
            'type' => 'string',
            'label' => 'Code de la sous question de saisie automatisée (toutes questions de type multiple-texte avec cette sous question sera utilisée directement)',
            'default' => 'SaisieVille',
        ),
        'formatVisualisation'=>array(
            'type'=>'select',
            'label' => 'Format des réponses affichée dans la liste',
            'options'=>array(
                'Libel'=>'Libellé',
                'CpLibel'=>'[Code postal] Libellé',
            ),
            'default' => 'CpLibel'
        ),
        'formatValeur'=>array(
            'type'=>'select',
            'label' => 'Format de la réponse finale affichée',
            'options'=>array(
                'Libel'=>'Libellé',
                'CpLibel'=>'[Code postal] Libellé',
            ),
            'default' => 'Libel',
        ),
        'limitlist'=>array(
            'type'=>'int',
            'label' => 'Nombre de réponse retournée (max)',
            'default' => 10
        ),
        'orderby'=>array(
            'type'=>'select',
            'label' => 'Ordonnées les réponse selon :',
            'options'=>array(
                'pop'=>'Population',
                'nom'=>'Nom (aphabétique)',
            ),
            'default' => 'pop',
            'help' => "Uniquement pour la recheche par texte"
        ),
        'answerCp' => array(
            'type' => 'string',
            'label' => 'Code de la réponse de code Code postal',
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
        'showCopyright' => array(
            'type' => 'boolean',
            'label' => 'Ne pas afficher le copyright des données, attention : assurez d’avoir les droits si vous décochez cette option.',
            'default' => 0,
        ),
    );

    /**
     * @var the csv file name to load
     */
    private $csvFileName="insee_cp_ville.csv";
    /**
     * @var the csv file version number to load
     */
    private $csvFileVersion=2;

    public function init() {

        $this->subscribe('beforeActivate');
#        $this->subscribe('beforeSurveySettings');
#        $this->subscribe('newSurveySettings');

        $this->subscribe('beforeQuestionRender');
        $this->subscribe('newDirectRequest');
    }

    /**
     * @see parent:getPluginSettings
     */
    public function getPluginSettings($getValues=true)
    {
        if($getValues){
            if(floatval($this->get('tableVersion',null,null,0)) < $this->csvFileVersion){
                $sTableName=self::tableName('insee_cp');
                App()->getDb()->createCommand()->dropTable($sTableName);
                Yii::app()->setFlashMessage(gT("Table for plugin was deleted to be updated"));
                $this->insertInseeCp();
            }
        }
        return parent::getPluginSettings($getValues);

    }
    public function beforeActivate()
    {
        $oEvent = $this->getEvent();
        $this->insertInseeCp();
    }

    private function insertInseeCp()
    {
        if (!$this->api->tableExists($this, 'insee_cp'))
        {
            if(!is_readable(dirname(__FILE__) . "/" . $this->csvFileName))
            {
                $this->getEvent()->set('success', false);
                $this->getEvent()->set('message', 'Can not read file :'.dirname(__FILE__) . "/" . $this->csvFileName.'.');
                return;
            }
            $tableName=$this->tableName('insee_cp');
            $this->api->createTable($this, 'insee_cp', array(
                'insee'=>'string(5)',
                'nom'=>'text',
                'cp'=>'string(5)',
                'nomsimple'=>'text',
                'region'=>'string(2)',
                'departement'=>'string(2)',
                'population'=>'float',
                'populationint'=>'int',
            ));
            /* TODO : add index */
            $this->addDataToTable();
            $this->tableUpdated=true;
            parent::saveSettings(array('tableVersion'=>$this->csvFileVersion));
        }
        if($this->tableUpdated)
        {
            Yii::app()->db->schema->getTables();
            Yii::app()->db->schema->refresh();
            Yii::app()->setFlashMessage(gT("Table for plugin was created and updated"));
        }
    }
    private function addDataToTable()
    {
      $fHandle = fopen(dirname(__FILE__) . "/" . $this->csvFileName ,'r');
      $lineNum=0;
      $tableName=$this->tableName('insee_cp');
      while ( ($aData = fgetcsv($fHandle) ) !== FALSE )
      {
          $lineNum++;
          if($lineNum > 1) // We don't do the header
          {
            if(strlen($aData[0])<=5)
            {
              $insertResult=Yii::app()->db->createCommand()
                ->insert($tableName,
                array(
                    'insee'         => str_pad($aData[0], 5, '0', STR_PAD_LEFT),
                    'nom'           => $aData[1],
                    'cp'            => str_pad($aData[2], 5, '0', STR_PAD_LEFT),
                    'nomsimple'     => $aData[3],
                    'region'        => $aData[4],
                    'departement'   => $aData[5],
                    'population'    => $aData[6],
                    'populationint' => $aData[7]
                )
                );
                if(!$insertResult)
                {
                    Yii::app()->setFlashMessage("Error happen update table for insee {$aData[0]} at line {$lineNum}");
                }
            }
            else
            {
              Yii::app()->setFlashMessage("Invalid line for insee {$aData[0]} at line {$lineNum}");
            }
          }
      }
      //Yii::app()->setFlashMessage("{$lineNum} in {$this->csvFileName}",'success');
      fclose($fHandle);
    }
    public function beforeQuestionRender()
    {
        $oEvent=$this->getEvent();
        if($oEvent->get('type')=="Q")
        {
            $iQid=$oEvent->get('qid');
            $oSaisieSubQuestion=Question::model()->find('parent_qid=:qid and title=:title',array(':qid'=>$iQid,':title'=>$this->get('answerLibel',null,null,$this->settings['answerLibel']['default'])));
            if($oSaisieSubQuestion)
            {
                $aOption = array(
                    'answerLibel' => $this->get('answerLibel',null,null,$this->settings['answerLibel']['default']),
                    'answerCp' => $this->get('answerCp',null,null,$this->settings['answerCp']['default']),
                    'answerInsee' => $this->get('answerInsee',null,null,$this->settings['answerInsee']['default']),
                    'answerNom' => $this->get('answerNom',null,null,$this->settings['answerNom']['default']),
                    'showCp' => intval($this->get('showCp',null,null,$this->settings['showCp']['default'])),
                    'showInsee' => intval($this->get('showInsee',null,null,$this->settings['showInsee']['default'])),
                );

                $sTipCopyright='Data : <a href="https://www.data.gouv.fr/fr/datasets/base-officielle-des-codes-postaux/" target="_blank">Base officielle des codes postaux</a> ©La Poste, <a href="http://www.insee.fr/fr/bases-de-donnees/default.asp?page=recensements.htm" target="_blank">Insee, Recensements de la population</a> ©Insee';
                $oEvent->set('class',$oEvent->get('class')." saisieville saisieauto");
                if(!$this->get('showCopyright',null,null,$this->settings['showCopyright']['default']))
                  $oEvent->set('answers',$oEvent->get('answers')."<p class='tip'><small>".$sTipCopyright."</small></p>");
                if($oEvent->get('man_message'))
                {
                  // If we don't have other sub question : we must update the mandatory tip, used default from LS ...
                  $aThisSubQ=array(
                    $this->get('answerLibel',null,null,$this->settings['answerLibel']['default']),
                    $this->get('answerCp',null,null,$this->settings['answerCp']['default']),
                    $this->get('answerInsee',null,null,$this->settings['answerInsee']['default']),
                    $this->get('answerNom',null,null,$this->settings['answerNom']['default']),
                  );
                  $oCriteria=new CDbCriteria();
                  $oCriteria->addNotInCondition('title',$aThisSubQ);
                  $oCriteria->compare('parent_qid',$iQid);
                  $iCountOtherQuestion=Question::model()->count($oCriteria);
                  if(!$iCountOtherQuestion) {
                    $oEvent->set('man_message',"<strong><br /><span class='errormandatory'>".gT('This question is mandatory').".  </span></strong>\n");
                  }
                }
                $assetUrl=Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/');
                Yii::app()->clientScript->registerScriptFile($assetUrl.'/cpville.js');
                Yii::app()->clientScript->registerCssFile($assetUrl.'/cpville.css');
                $aOption['jsonurl']=$this->api->createUrl('plugins/direct', array('plugin' => get_class($this),'function' => 'auto'));
                $sScript="autoCpVille({$oEvent->get('qid')},".ls_json_encode($aOption).");";
                Yii::app()->clientScript->registerScript("autoCpVille{$iQid}",$sScript,CClientScript::POS_END);
            }
        }

    }

    public function newDirectRequest()
    {
        $oEvent = $this->event;
        $sAction=$oEvent->get('function');
        if ($oEvent->get('target') == "cpVille")
        {
            if($sAction=='auto')
                $this->actionAuto();
            else
                throw new CHttpException(404,'Unknow action');
        }
    }

    private function actionAuto()
    {
        $iSurveyId=Yii::app()->session['LEMsid'];
        $sParametre=trim(Yii::app()->request->getParam('term'));
        // Some update directly
        $sParametre=strtr($sParametre,array(
          "/"=>" SUR ",
        ));
        /* get the collation according to db */
        switch (App()->getDb()->charset) {
            case 'utf8':
                $collate = " COLLATE utf8_general_ci";
                break;
            case 'utf8mb4':
                $collate = " COLLATE utf8mb4_general_ci";
                break;
            default:
                $collate = "";
                break;
        }
        $iLimit=(int)$this->get('limitliste');
        $iLimit=($iLimit>0) ? $iLimit : 10;
        $sOrderBy=$this->get('orderby');
        switch ($sOrderBy) {
          case 'nom':
            $sOrderBy="nom asc";
            break;
          default:
            $sOrderBy="population desc";
            break;
        }
        $aTowns=array();
        if($sParametre)
        {
            $aParametres=preg_split("/(’| |\'|=|-)/", $sParametre);
            if(count($aParametres)==1 && ctype_digit($sParametre) && strlen($sParametre)==5)
            {
                $aTowns = Yii::app()->db->createCommand()
                    ->select('*')
                    ->from(self::tableName('insee_cp'))
                    ->where(
                        Yii::app()->db->quoteColumnName('cp')." LIKE :cp",
                        array(':cp'=>"{$sParametre}"))
                    ->order("nom asc")
                    ->queryAll();
            }
            elseif(count($aParametres)==1)
            {
                $sParametre = addcslashes(self::replaceSomeString($sParametre), '%_');
                $aTowns = Yii::app()->db->createCommand()
                    ->select('*')
                    ->from(self::tableName('insee_cp'))
                    ->where(
                        Yii::app()->db->quoteColumnName('nomsimple')." {$collate} LIKE :nomsimple OR ".Yii::app()->db->quoteColumnName('nomsimple')." {$collate} LIKE :nomsimplespace OR ".Yii::app()->db->quoteColumnName('cp')." LIKE :cp",
                        array(':nomsimple'=>"{$sParametre}%",':nomsimplespace'=>"% {$sParametre}%",':cp'=>"{$sParametre}%"))
                    ->order($sOrderBy)
                    ->limit($iLimit)
                    ->queryAll();
            }
            else
            {
                $oTowns = Yii::app()->db->createCommand()
                    ->select('*')
                    ->from(self::tableName('insee_cp'))
                    ->where("1=1");
                    $aParams=array();
                    $count=1;
                    $dbColumn=Yii::app()->db->quoteColumnName('nomsimple');
                    $dbCpColumn=Yii::app()->db->quoteColumnName('cp');
                    foreach($aParametres as $sParametre)
                    {
                        $sParametre=trim($sParametre);
                        if(!empty($sParametre))
                        {
                          if(ctype_digit($sParametre))
                          {
                            $oTowns->andWhere("{$dbCpColumn} LIKE :cpstart{$count} OR {$dbColumn} {$collate} LIKE :start{$count} OR {$dbColumn} {$collate} LIKE :space{$count}");
                            $aParams[":cpstart{$count}"]="{$sParametre}%";
                            $aParams[":start{$count}"]="{$sParametre}%";
                            $aParams[":space{$count}"]="% {$sParametre}%";
                          }
                          else
                          {
                            $sParametre = addcslashes(self::replaceSomeString($sParametre), '%_');
                            $oTowns->andWhere("{$dbColumn} {$collate} LIKE :start{$count} OR {$dbColumn} {$collate} LIKE :space{$count}");
                            $aParams[":start{$count}"]="{$sParametre}%";
                            $aParams[":space{$count}"]="% {$sParametre}%";
                          }
                          $count++;
                        }
                    }
                    $oTowns->order($sOrderBy);
                    $oTowns->limit($iLimit);
                    $oTowns->params=$aParams;
                    $aTowns=$oTowns->queryAll();
            }
            $aReturnArray=array();
            foreach($aTowns as $aTown)
            {
                switch($this->get('formatVisualisation'))
                {
                    case 'Libel':
                        $sLabel=$aTown['nom'];
                        break;
                    case 'CpLibel':
                    default:
                        $sLabel="[{$aTown['cp']}] {$aTown['nom']}";
                        break;
                }
                switch($this->get('formatValeur'))
                {
                    case 'CpLibel':
                        $sValue="[{$aTown['cp']}] {$aTown['nom']}";
                        break;
                    case 'Libel':
                    default:
                        $sValue=$aTown['nom'];
                        break;
                }
                $addArray=array_replace(
                    $aTown,
                    array(
                        'label'=>$sLabel,
                        'value'=>$sValue,
                        $this->get('answerCp',null,null,$this->settings['answerCp']['default'])=>$aTown["cp"],
                        $this->get('answerInsee',null,null,$this->settings['answerInsee']['default'])=>$aTown["insee"],
                        $this->get('answerNom',null,null,$this->settings['answerNom']['default'])=>$aTown["nom"],
                    )
                );
                $aReturnArray[]=$addArray;
            }
            if(!count($aReturnArray))
            {
                $aReturnArray[]=array(
                    'label'=>"-",
                    'value'=>"",
                    $this->get('answerCp',null,null,$this->settings['answerCp']['default'])=>"",
                    $this->get('answerInsee',null,null,$this->settings['answerInsee']['default'])=>"",
                    $this->get('answerNom',null,null,$this->settings['answerNom']['default'])=>"",
                );
            }
            $this->displayJson($aReturnArray);
        }
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
        return App()->getDb()->tablePrefix."cpville_{$tableName}";
    }
    public static function replaceSomeString($string)
    {
        $aReplace=array(
          "STE"=>"SAINTE",
          "ST"=>"SAINT",
          "/"=>"SUR",
        );
        if(array_key_exists(strtoupper($string), $aReplace))
        {
          return $aReplace[strtoupper($string)];
        }
        return $string;
    }
}

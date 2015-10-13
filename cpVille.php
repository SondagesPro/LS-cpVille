<?php
/**
 * cpVille Plugin for LimeSurvey
 * Allow user to enter part of postal code or town and get the insee code in survey
 * Permet aux répondants de saisir une partie du code postal ou de la ville en choix, et récupérer le code postal
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2015 Denis Chenu <http://sondages.pro>
 * @copyright 2015 Observatoire Régional de la Santé (ORS) - Nord-Pas-de-Calais <http://www.orsnpdc.org/>
 * @license GPL v3
 * @version 1.0
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
class cpVille extends PluginBase {
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

    private $csvFileName="insee_cp_ville.csv";

    public function __construct(PluginManager $manager, $id) {
        parent::__construct($manager, $id);

        $this->subscribe('beforeActivate');
#        $this->subscribe('beforeSurveySettings');
#        $this->subscribe('newSurveySettings');

        $this->subscribe('beforeQuestionRender');
        $this->subscribe('newDirectRequest');
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
            // 2.06 150729
            $this->api->createTable($this, 'insee_cp', array(
                'insee'=>'string(5)',
                'nom'=>'text',
                'cp'=>'string(5)',
                'nomsimple'=>'text',
                'region'=>'string(2)',
                'departement'=>'string(2)',
                'menages'=>'float',
                'population'=>'float',
                'menagesint'=>'int',
                'populationint'=>'int',
            ));
            /* TODO : add index */
            $this->addDataToTable();
            $this->tableUpdated=true;
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
                    'menages'       => $aData[6],
                    'population'    => $aData[7],
                    'menagesint'    => $aData[8],
                    'populationint' => $aData[9]
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
                //$assetUrl=Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/');
                Yii::app()->clientScript->registerScriptFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/cpville.js'));
                Yii::app()->clientScript->registerCssFile(Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/cpville.css'));
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
                        Yii::app()->db->quoteColumnName('nomsimple')."  COLLATE utf8_general_ci LIKE :nomsimple OR ".Yii::app()->db->quoteColumnName('nomsimple')." COLLATE utf8_general_ci LIKE :nomsimplespace OR ".Yii::app()->db->quoteColumnName('cp')." LIKE :cp",
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
                            $oTowns->andWhere("{$dbCpColumn} LIKE :cpstart{$count} OR {$dbColumn} COLLATE utf8_general_ci LIKE :start{$count} OR {$dbColumn} COLLATE utf8_general_ci LIKE :space{$count}");
                            $aParams[":cpstart{$count}"]="{$sParametre}%";
                            $aParams[":start{$count}"]="{$sParametre}%";
                            $aParams[":space{$count}"]="% {$sParametre}%";
                          }
                          else
                          {
                            $sParametre = addcslashes(self::replaceSomeString($sParametre), '%_');
                            $oTowns->andWhere("{$dbColumn} COLLATE utf8_general_ci LIKE :start{$count} OR {$dbColumn} COLLATE utf8_general_ci LIKE :space{$count}");
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

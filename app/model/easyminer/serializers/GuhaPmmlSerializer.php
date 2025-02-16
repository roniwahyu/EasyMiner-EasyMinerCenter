<?php

namespace EasyMinerCenter\Model\EasyMiner\Serializers;

use EasyMinerCenter\Model\Data\Facades\DatabasesFacade;
use EasyMinerCenter\Model\EasyMiner\Entities\Attribute;
use EasyMinerCenter\Model\EasyMiner\Entities\Cedent;
use EasyMinerCenter\Model\EasyMiner\Entities\DatasourceColumn;
use EasyMinerCenter\Model\EasyMiner\Entities\Miner;
use EasyMinerCenter\Model\EasyMiner\Entities\Preprocessing;
use EasyMinerCenter\Model\EasyMiner\Entities\RuleAttribute;
use EasyMinerCenter\Model\EasyMiner\Entities\Task;
use Nette\Utils\Strings;

/**
 * Class GuhaPmmlSerializer - serializer umožňující sestavit GUHA PMML dokument z dat zadané úlohy...
 * @package EasyMinerCenter\Model\EasyMiner\Serializers
 */
class GuhaPmmlSerializer {
  /** @var  \SimpleXMLElement $pmml */
  private $pmml;
  /** @var  Task $task */
  private $task;
  /** @var  Miner $miner */
  private $miner;
  /** @var string $appVersion */
  public $appVersion='';

  const PMML_XMLNS='http://www.dmg.org/PMML-4_0';

  /** @var  DatabasesFacade $databasesFacade */
  private $databasesFacade;

  /** @var  \SimpleXMLElement $BBAsWorkXml */
  private $BBAsWorkXml;
  /** @var  \SimpleXMLElement $DBAsWorkXml */
  private $DBAsWorkXml;
  /** @var  \SimpleXMLElement $associationRulesWorkXml */
  private $associationRulesWorkXml;
  /** @var int[] $serializedCedents  pole s indexy dílčích cedentů, které už byly serializovány */
  private $serializedCedentsArr;
  /** @var int[] $serializedRuleAttributesArr  pole s indexy dílčích ruleAttributes, které už byly serializovány */
  private $serializedRuleAttributesArr;
  /** @var  array $connectivesArr pole s překladem spojek pro serializaci */
  private $connectivesArr;
  private $dataTypesTransformationArr=[
    'int'=>'integer',
    'float'=>'float',
    'string'=>'string'
  ];
  /**
   * @return \SimpleXMLElement
   */
  public function getPmml(){
    return $this->pmml;
  }

  /**
   * @param Task $task
   * @param \SimpleXMLElement|null $pmml
   * @param DatabasesFacade|null $databasesFacade
   * @param string $appVersion=''
   */
  public function __construct(Task $task, $pmml = null, $databasesFacade=null, $appVersion=''){
    if ($task instanceof Task){
      $this->task=$task;
      $this->miner=$task->miner;
    }
    $this->appVersion=$appVersion;
    if (!empty($pmml)){
      if ($pmml instanceof \SimpleXMLElement){
        $this->pmml=$pmml;
      }elseif(is_string($pmml)){
        $this->pmml=simplexml_load_string($pmml);
      }
    }
    if (!$pmml instanceof \SimpleXMLElement){
      $this->prepareBlankPmml();
    }

    $this->appendTaskInfo();

    $this->databasesFacade=$databasesFacade;

    $connectivesArr=Cedent::getConnectives();
    foreach($connectivesArr as $connective){
      $this->connectivesArr[$connective]=Strings::firstUpper($connective);
    }
  }

  /**
   * Funkce připojující informace o připojení k databázi do PMML souboru
   */
  public function appendMetabaseInfo() {
    /** @var \SimpleXMLElement $headerXml */
    $headerXml=$this->pmml->Header;
    $dbConnection=$this->miner->metasource->getDbConnection();//TODO přesunutí ve struktuře PMML - kvůli validitě
    $this->addExtensionElement($headerXml,'database-type',$dbConnection->type);
    $this->addExtensionElement($headerXml,'database-server',$dbConnection->dbServer);
    $this->addExtensionElement($headerXml,'database-name',$dbConnection->dbName);
    $this->addExtensionElement($headerXml,'database-user',$dbConnection->dbUsername);
    $this->addExtensionElement($headerXml,'database-password',$dbConnection->dbPassword);
  }

  /**
   * Funkce pro nastavení základních informací o úloze, ke které je vytvářena serializace
   */
  private function appendTaskInfo() {
    /** @var \SimpleXMLElement $headerXml */
    $headerXml=$this->pmml->Header;
    if ($this->task->type==Miner::TYPE_LM){
      //lispminer
      $this->setExtensionElement($headerXml,'subsystem','4ft-Miner');
      $this->setExtensionElement($headerXml,'module','LMConnect');
    }elseif($this->task->type==Miner::TYPE_R){
      //R
      $this->setExtensionElement($headerXml,'subsystem','R');
      $this->setExtensionElement($headerXml,'module','Apriori-R');
    }else{
      //other
      $this->setExtensionElement($headerXml,'subsystem',$this->task->type);
      $this->setExtensionElement($headerXml,'module',$this->task->type);
    }
    //základní informace o autorovi a timestamp
    $this->setExtensionElement($headerXml,'author',(!empty($this->miner->user)?$this->miner->user->name:''));
    $headerXml->Timestamp=date('Y-m-d H:i:s').' GMT '.str_replace(['+','-'],['+ ','- '],date('P'));
    $applicationXml=$headerXml->Application;
    $applicationXml['name']='EasyMiner';
    $applicationXml['version']=$this->appVersion;
  }

  /**
   * Funkce připravující prázdný PMML dokument
   */
  private function prepareBlankPmml(){
    $this->pmml = simplexml_load_string('<'.'?xml version="1.0" encoding="UTF-8"?>
      <'.'?oxygen SCHSchema="http://easyminer.eu/schemas/GUHARestr0_1.sch"?>
      <PMML xmlns="'.self::PMML_XMLNS.'" version="4.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:pmml="http://www.dmg.org/PMML-4_0" xsi:schemaLocation="http://www.dmg.org/PMML-4_0 http://easyminer.eu/schemas/PMML4.0+GUHA0.1.xsd">
        <Header copyright="Copyright (c) KIZI UEP, '.date('Y').'">
          <Extension name="author" value=""/>
          <Extension name="subsystem" value=""/>
          <Extension name="module" value=""/>
          <Extension name="format" value="4ftMiner.Task"/>
          <Extension name="dataset" value="" />
          <Application name="EasyMiner" version=""/>
          <Timestamp></Timestamp>
        </Header>
        <DataDictionary/>
        <TransformationDictionary/>
        <guha:AssociationModel xmlns:guha="http://keg.vse.cz/ns/GUHA0.1rev1" xmlns="" />
      </PMML>');
    /** @var \SimpleXMLElement $header */
    $header=$this->pmml->Header;
    $datasetExtension=null;
    foreach($header->Extension as $extension){
      if ($extension['name']=='dataset'){
        $datasetExtension=$extension;
        break;
      }
    }
    if (!empty($datasetExtension)){
      $datasetExtension['value']=$this->miner->metasource->attributesTable;
    }else{
      $this->addExtensionElement($header,'dataset',$this->miner->metasource->attributesTable);
    }
  }


  /**
   * Funkce pro přidání tagu <Extension name="..." value="..." />
   *
   * @param \SimpleXMLElement &$parentSimpleXmlElement
   * @param string $extensionName
   * @param string $extensionValue
   * @param string|null $extensionExtender
   */
  private function addExtensionElement(\SimpleXMLElement &$parentSimpleXmlElement,$extensionName,$extensionValue,$extensionExtender=null, $groupExtensions=true){
    if ($groupExtensions && count($parentSimpleXmlElement->Extension)>0){
      $siblinkElement = $parentSimpleXmlElement->Extension[0];
      $siblinkElementDom=dom_import_simplexml($siblinkElement);
      //připravení elementu pro připojení
      $extensionElement=new \SimpleXMLElement('<Extension />');
      $extensionElement->addAttribute('name',$extensionName);
      $extensionElement->addAttribute('value',$extensionValue);
      if ($extensionExtender!==null){
        $extensionElement->addAttribute('extender',$extensionExtender);
      }
      $extensionElementDom = $siblinkElementDom->ownerDocument->importNode(dom_import_simplexml($extensionElement), true);
      $siblinkElementDom->parentNode->insertBefore($extensionElementDom, $siblinkElementDom);
    }else{
      $extensionElement=$parentSimpleXmlElement->addChild('Extension');
      $extensionElement->addAttribute('name',$extensionName);
      $extensionElement->addAttribute('value',$extensionValue);
      if ($extensionExtender!==null){
        $extensionElement->addAttribute('extender',$extensionExtender);
      }
    }
  }

  /**
   * @param \SimpleXMLElement $parentSimpleXmlElement
   * @param string $extensionName
   * @param string $extensionValue
   * @param string|null $extensionExtender = null
   * @param bool $groupExtensions = true
   */
  private function setExtensionElement(\SimpleXMLElement &$parentSimpleXmlElement,$extensionName,$extensionValue,$extensionExtender=null, $groupExtensions=true){
    if ($extensionElement=$this->getExtensionElement($parentSimpleXmlElement,$extensionName)){
      //existuje příslušný element Extension
      $extensionElement['name']=$extensionName;
      $extensionElement['value']=$extensionValue;
      if ($extensionExtender!=''){
        $extensionElement['extender']=$extensionExtender;
      }else{
        unset($extensionExtender['extender']);
      }
    }else{
      $this->addExtensionElement($parentSimpleXmlElement,$extensionName,$extensionValue,$extensionExtender,$groupExtensions);
    }
  }

  /**
   * Funkce vracející konkrétní extension
   * @param \SimpleXMLElement $parentSimpleXmlElement
   * @param string $extensionName
   * @return \SimpleXMLElement|null
   */
  private function getExtensionElement(\SimpleXMLElement &$parentSimpleXmlElement, $extensionName){
    if (count($parentSimpleXmlElement->Extension)>0){
      foreach($parentSimpleXmlElement->Extension as $extension){
        if (@$extension['name']==$extensionName){
          return $extension;
        }
      }
    }
    return null;
  }

  /**
   * Funkce pro připojení informací o nastavení úlohy
   */
  public function appendTaskSettings(){
    $taskSettingsSerializer=new TaskSettingsSerializer($this->pmml,$this->miner->type);
    $this->pmml=$taskSettingsSerializer->settingsFromJson($this->task->taskSettingsJson);
  }

  public function appendDataDictionary($includeFrequencies=true){
    $datasource=$this->miner->datasource;
    if (empty($datasource->datasourceColumns)){
      return;
    }
    /** @var \SimpleXMLElement $dataDictionaryXml */
    $dataDictionaryXml=$this->pmml->DataDictionary;
    if (!empty($dataDictionaryXml[0]['numberOfFields'])){
      $dataDictionaryXml[0]['numberOfFields']=count($datasource->datasourceColumns);
    }else{
      $dataDictionaryXml->addAttribute('numberOfFields',count($datasource->datasourceColumns));
    }
    //připojení jednotlivých data fields
    foreach($datasource->datasourceColumns as $datasourceColumn){
      $dataFieldXml=$dataDictionaryXml->addChild('DataField');
      $dataFieldXml->addAttribute('name',$datasourceColumn->name);
      $dataFieldXml->addAttribute('dataType',$this->dataTypesTransformationArr[$datasourceColumn->type]);
      if ($datasourceColumn->type==DatasourceColumn::TYPE_STRING){
        $dataFieldXml->addAttribute('optype','categorical');
      }else{
        $dataFieldXml->addAttribute('optype','continuous');
      }

      if ($includeFrequencies){
        $valuesStatistics=$this->databasesFacade->getColumnValuesStatistic($datasource->dbTable,$datasourceColumn->name);
        if ($datasourceColumn->type=DatasourceColumn::TYPE_STRING && !empty($valuesStatistics->valuesArr)){
          //výčet hodnot
          foreach($valuesStatistics->valuesArr as $value=>$count){
            $valueXml=$dataFieldXml->addChild('Value');
            $valueXml->addAttribute('value',$value);
            $this->addExtensionElement($valueXml,'Frequency',$count,$value);
          }
        }elseif(isset($valuesStatistics->minValue) && isset($valuesStatistics->maxValue)){
          //interval
          if ($valuesStatistics->minValue<=$valuesStatistics->maxValue){
            $this->addExtensionElement($dataFieldXml,'Avg',$valuesStatistics->avgValue,'Avg');
            $intervalXml=$dataFieldXml->addChild('Interval');
            $intervalXml->addAttribute('closure','closedClosed');
            $intervalXml->addAttribute('leftMargin',$valuesStatistics->minValue);
            $intervalXml->addAttribute('rightMargin',$valuesStatistics->maxValue);
          }
        }
      }

      //XXX TODO serializace hodnot...
    }
  }

  /**
   * Funkce pro serializaci TransformationDictionary
   * @param bool $includeFrequencies = true
   */
  public function appendTransformationDictionary($includeFrequencies=true){
    $metasource=$this->miner->metasource;
    if (empty($metasource->attributes)){return;}
    /** @var \SimpleXMLElement $transformationDictionaryXml */
    $transformationDictionaryXml=$this->pmml->TransformationDictionary;
    foreach($metasource->attributes as $attribute){
      if (empty($attribute->preprocessing)){continue;}
      $derivedFieldXml=$transformationDictionaryXml->addChild('DerivedField');
      $derivedFieldXml->addAttribute('name',$attribute->name);
      $derivedFieldXml->addAttribute('dataType',$this->dataTypesTransformationArr[$attribute->type]);
      if ($attribute->type==Attribute::TYPE_STRING){
        $derivedFieldXml->addAttribute('optype','categorical');
      }else {
        $derivedFieldXml->addAttribute('optype', 'continuous');
      }
      $datasourceColumn=$attribute->datasourceColumn;

      //serializace preprocessingu
      $preprocessing=$attribute->preprocessing;
      if ($preprocessing->specialType==Preprocessing::SPECIALTYPE_EACHONE){
        //serializace eachOne
        $mapValuesXml=$derivedFieldXml->addChild('MapValues');
        $mapValuesXml->addAttribute('outputColumn','field');
        $fieldColumnPairXml=$mapValuesXml->addChild('FieldColumnPair');
        $fieldColumnPairXml->addAttribute('column','column');
        $fieldColumnPairXml->addAttribute('field',$datasourceColumn->name);
        $inlineTableXml=$mapValuesXml->addChild('InlineTable');
        //frekvence
        $valuesStatistics=$this->databasesFacade->getColumnValuesStatistic($metasource->attributesTable,$attribute->name);
        if (!empty($valuesStatistics->valuesArr)){
          if ($includeFrequencies){
            foreach($valuesStatistics->valuesArr as $value=>$count){
              $this->addExtensionElement($inlineTableXml,'Frequency',$count,$value);
            }
          }
          foreach($valuesStatistics->valuesArr as $value=>$count){
            $rowXml=$inlineTableXml->addChild('row');
            $rowXml->addChild('column',$value);
            $rowXml->addChild('field',$value);
          }
        }
        continue;
      }
      if (empty($preprocessing->valuesBins)){continue;}
      $valuesBins=$preprocessing->valuesBins;
      if (!empty($valuesBins[0]->intervals)){
        //serializace discretizace pomocí intervalů
        $discretizeXml=$derivedFieldXml->addChild('Discretize');
        $discretizeXml->addAttribute('field',$datasourceColumn->name);
        //frekvence
        $valuesStatistics=$this->databasesFacade->getColumnValuesStatistic($metasource->attributesTable,$attribute->name);
        if (!empty($valuesStatistics->valuesArr) && $includeFrequencies){
          foreach($valuesStatistics->valuesArr as $value=>$count){
            $this->addExtensionElement($discretizeXml,'Frequency',$count,$value);
          }
        }
        foreach($valuesBins as $valuesBin){
          if (!empty($valuesBin->intervals)) {
            foreach ($valuesBin->intervals as $interval){
              if (!isset($valuesStatistics->valuesArr[$valuesBin->name])){continue;}//vynecháme neobsazené hodnoty
              $discretizeBinXml = $discretizeXml->addChild('DiscretizeBin');
              $discretizeBinXml->addAttribute('binValue', $valuesBin->name);
              $intervalXml=$discretizeBinXml->addChild('Interval');
              $closure=$interval->leftClosure.Strings::firstUpper($interval->rightClosure);
              $intervalXml->addAttribute('closure',$closure);
              $intervalXml->addAttribute('leftMargin',$interval->leftMargin);
              $intervalXml->addAttribute('rightMargin',$interval->rightMargin);
            }
          }
        }
      }elseif(!empty($valuesBins[0]->values)){
        //serializace discretizace pomocí výčtů hodnot
        $mapValuesXml=$derivedFieldXml->addChild('MapValues');
        $mapValuesXml->addAttribute('outputColumn','field');
        $fieldColumnPairXml=$mapValuesXml->addChild('FieldColumnPair');
        $fieldColumnPairXml->addAttribute('column','column');
        $fieldColumnPairXml->addAttribute('field',$datasourceColumn->name);
        $inlineTableXml=$mapValuesXml->addChild('InlineTable');
        //frekvence
        $valuesStatistics=$this->databasesFacade->getColumnValuesStatistic($metasource->attributesTable,$attribute->name);
        if (!empty($valuesStatistics->valuesArr) && $includeFrequencies){
          foreach($valuesStatistics->valuesArr as $value=>$count){
            $this->addExtensionElement($inlineTableXml,'Frequency',$count,$value);
          }
        }
        foreach($valuesBins as $valuesBin){
          if (!empty($valuesBin->values)){
            if (!isset($valuesStatistics->valuesArr[$valuesBin->name])){continue;}//vynecháme neobsazené hodnoty
            foreach ($valuesBin->values as $value){
              $rowXml=$inlineTableXml->addChild('row');
              $rowXml->addChild('column',$value->value);
              $rowXml->addChild('field',$valuesBin->name);
            }
          }
        }
      }
    }
  }

  public function appendRules(){
    if (empty($this->task->rules)){return;}
    /** @var \SimpleXMLElement $guhaAssociationModelXml */
    $guhaAssociationModelXml=$this->pmml->children('guha',true)[0];
    if (count($guhaAssociationModelXml->AssociationRules)>0){
      /** @var \SimpleXMLElement $associationRulesXml */
      $associationRulesXml=$guhaAssociationModelXml->AssociationRules;
    }else{
      /** @var \SimpleXMLElement $associationRulesXml */
      $associationRulesXml=$guhaAssociationModelXml->addChild('AssociationRules',null,'');
    }
    $this->associationRulesWorkXml=new \SimpleXMLElement('<AssociationRules xmlns="" />');
    $this->BBAsWorkXml=new \SimpleXMLElement('<BBAs xmlns="" />');
    $this->DBAsWorkXml=new \SimpleXMLElement('<DBAs xmlns="" />');
    $this->serializedCedentsArr=[];
    $this->serializedRuleAttributesArr=[];

    foreach($this->task->rules as $rule){
      //přidání konkrétního pravidla do XML
      $associationRuleXml=$this->associationRulesWorkXml->addChild('AssociationRule',null,'');
      $associationRuleXml->addAttribute('id',$rule->ruleId);
      if ($rule->inRuleClipboard){
        $this->addExtensionElement($associationRuleXml,'mark','interesting');
      }
      $antecedent=$rule->antecedent;
      $consequent=$rule->consequent;
      if (!empty($antecedent)){
        $associationRuleXml->addAttribute('antecedent','cdnt_'.$antecedent->cedentId);
      }
      if (!empty($consequent)){
        $associationRuleXml->addAttribute('consequent','cdnt_'.$consequent->cedentId);
      }
      $associationRuleXml->addChild('Text');
      $associationRuleXml->Text=$rule->text;
      //IMValues
      $IMValueConfidence=$associationRuleXml->addChild('IMValue',$rule->support);
      $IMValueConfidence->addAttribute('name','BASE');
      $IMValueConfidence->addAttribute('type','%All');
      $IMValueConfidence=$associationRuleXml->addChild('IMValue',$rule->confidence);
      $IMValueConfidence->addAttribute('name','CONF');
      $IMValueConfidence->addAttribute('type','%All');
      //TODO reference na zadání měr zajímavosti?
      //FourFtTable
      $fourFtTableXml=$associationRuleXml->addChild('FourFtTable');
      $fourFtTableXml->addAttribute('a',$rule->a);
      $fourFtTableXml->addAttribute('b',$rule->b);
      $fourFtTableXml->addAttribute('c',$rule->c);
      $fourFtTableXml->addAttribute('d',$rule->d);
      //serializace dílčích cedentů
      if (!empty($rule->antecedent)){
        $this->serializeCedent($rule->antecedent);
      }
      if (!empty($rule->consequent)){
        $this->serializeCedent($rule->consequent);
      }
    }
    //sloučení XML dokumentů
    //TODO přesun do samostatné funkce?
    $associationRulesDom=dom_import_simplexml($associationRulesXml);
    if (count($this->BBAsWorkXml->children())>0){
      foreach($this->BBAsWorkXml->children() as $xmlItem){
        $insertDom=$associationRulesDom->ownerDocument->importNode(dom_import_simplexml($xmlItem),true);
        $associationRulesDom->appendChild($insertDom);
      }
    }
    if (count($this->DBAsWorkXml->children())>0){
      foreach($this->DBAsWorkXml->children() as $xmlItem){
        $insertDom=$associationRulesDom->ownerDocument->importNode(dom_import_simplexml($xmlItem),true);
        $associationRulesDom->appendChild($insertDom);
      }
    }
    if (count($this->associationRulesWorkXml->children())>0){
      foreach($this->associationRulesWorkXml->children() as $xmlItem){
        $insertDom=$associationRulesDom->ownerDocument->importNode(dom_import_simplexml($xmlItem),true);
        $associationRulesDom->appendChild($insertDom);
      }
    }


  }

  /**
   * @param Cedent $cedent
   */
  private function serializeCedent(Cedent $cedent){
    //pokud už byl cedent serializován, tak ho ignorujeme
    if(in_array($cedent->cedentId,$this->serializedCedentsArr)){return;}
    //serializace samotného dílčího cedentu
    $DBAXML=$this->DBAsWorkXml->addChild('DBA');
    $DBAXML->addAttribute('id','cdnt_'.$cedent->cedentId);
    $DBAXML->addAttribute('connective',$this->connectivesArr[$cedent->connective]);
    if (!empty($cedent->cedents)){
      //serializace dílčích cedentů
      foreach($cedent->cedents as $subCedent){
        $DBAXML->addChild('BARef','cdnt_'.$subCedent->cedentId);
        $this->serializeCedent($subCedent);
      }
    }
    if (!empty($cedent->ruleAttributes)){
      //serializace ruleAttributes
      $DBAAttributesXML=$this->DBAsWorkXml->addChild('DBA');
      $DBAAttributesXML->addAttribute('id','cdnt_'.$cedent->cedentId.'_attr');
      $DBAAttributesXML->addAttribute('connective',$this->connectivesArr[Cedent::CONNECTIVE_CONJUNCTION]);
      $DBAAttributesXML->addAttribute('literal','false');
      $DBAXML->addChild('BARef','cdnt_'.$cedent->cedentId.'_attr');
      foreach($cedent->ruleAttributes as $ruleAttribute) {
        $DBAAttributesXML->addChild('BARef','dba_'.$ruleAttribute->ruleAttributeId);
        $this->serializeRuleAttribute($ruleAttribute);
      }
    }
    $this->serializedCedentsArr[]=$cedent->cedentId;
  }

  private function serializeRuleAttribute(RuleAttribute $ruleAttribute){
    if (in_array($ruleAttribute->ruleAttributeId,$this->serializedRuleAttributesArr)){return;}
    //vytvoření příslušného BBA
    $BBAXML=$this->BBAsWorkXml->addChild('BBA');
    $BBAXML->addAttribute('id','bba_'.$ruleAttribute->ruleAttributeId);
    $BBAXML->addAttribute('literal','false');
    $BBAXML->addChild('FieldRef');
    $BBAXML->FieldRef=$ruleAttribute->attribute->name;
    $BBAXML->addChild('CatRef');
    $BBAXML->CatRef=(empty($ruleAttribute->valuesBin)?$ruleAttribute->value->value:$ruleAttribute->valuesBin->name);//TODO atributy s více hodnotami!
    //
    $DBAXML=$this->DBAsWorkXml->addChild('DBA');
    $DBAXML->addAttribute('id','dba_'.$ruleAttribute->ruleAttributeId);
    $DBAXML->addAttribute('connective',$this->connectivesArr[Cedent::CONNECTIVE_CONJUNCTION]);
    $DBAXML->addAttribute('literal','true');
    $DBAXML->addChild('BARef','bba_'.$ruleAttribute->ruleAttributeId);
    //
    $this->serializedRuleAttributesArr[]=$ruleAttribute->ruleAttributeId;
  }
}
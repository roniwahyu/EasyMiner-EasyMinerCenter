<?php

namespace EasyMinerCenter\Model\EasyMiner\Entities;

use EasyMinerCenter\Model\EasyMiner\Authorizators\IOwnerResource;
use LeanMapper\Entity;
use Nette;
use Nette\Utils\Json;

/**
 * Class Miner
 * @package EasyMinerCenter\Model\EasyMiner\Entities
 *
 * @property int|null $minerId = null
 * @property User|null $user = null m:hasOne
 * @property string $name = ''
 * @property string $type m:Enum('lm','r')
 * @property Datasource|null $datasource m:hasOne(datasource_id:) - zdroj původních dat v DB
 * @property Metasource|null $metasource m:hasOne(metasource_id:) - zdroj předzpracovaných dat v DB
 * @property RuleSet|null $ruleSet m:hasOne(rule_set_id:)
 * @property-read string $attributesTable
 * @property \DateTime|null $created = null
 * @property \DateTime|null $lastOpened = null
 * @property string $config
 * @property-read Task[] $tasks m:belongsToMany
 */
class Miner extends Entity implements IOwnerResource{
  const TYPE_LM='lm';
  const TYPE_R='r';
  const DEFAULT_TYPE='r';

  /**
   * Funkce vracející přehled jednotlivých podporovaných typů minerů
   * @return array
   */
  public static function getTypes(){
    return array(
      self::TYPE_LM=>'LISp-Miner',
      self::TYPE_R=>'R',
    );
  }

  /**
   * Funkce vracející název tabulky s atributy
   * @return string
   */
  public function getAttributesTableName() {
    return 'ATR' . $this->minerId . '_' . $this->datasource->dbTable;
  }

  /**
   * Funkce vracející název tabulky s atributy
   * @return string
   */
  public function getRulesTableName(){
    return 'RULES'.$this->minerId.'_'.$this->datasource->dbTable;
  }

  /**
   * Funkce vracející název tabulky s atributy
   * @return string
   */
  public function getBBATableName(){
    return 'BBA'.$this->minerId.'_'.$this->datasource->dbTable;
  }

  /**
   * @return array
   */
  public function getConfig(){
    try{
      $arr=Nette\Utils\Json::decode($this->row->config,Json::FORCE_ARRAY);
    }catch (\Exception $e){
      $arr=[];
    }
    return $arr;
  }

  /**
   * @param array $config
   * @throws Nette\Utils\JsonException
   */
  public function setConfig($config){
    if (is_array($config)||is_object($config)){
      $this->row->config=Json::encode($config);
    }
  }

  /**
   * Funkce vracející externí konfiguraci
   * @return array
   */
  public function getExternalConfig(){
    $config=$this->getConfig();
    if (!empty($config['ext'])){
      return $config['ext'];
    }else{
      return [];
    }
  }

  /**
   * Funkce pro nastavení externí konfigurace
   * @param array $externalConfig
   * @return array
   */
  public function setExternalConfig($externalConfig){
    $config=$this->getConfig();
    $config['ext']=$externalConfig;
    $this->setConfig($config);
  }

  /**
   * Funkce vracející data mineru v podobě pole
   * @return array
   */
  public function getDataArr(){
    $rowData=$this->getRowData();
    return [
      'id'=>$this->minerId,
      'name'=>$this->name,
      'type'=>$this->type,
      'datasourceId'=>$rowData['datasource_id'],
      'metasourceId'=>$rowData['metasource_id'],
      'ruleSetId'=>$rowData['rule_set_id'],
      'config'=>$this->getConfig(),
      'created'=>$this->created,
      'lastOpened'=>$this->lastOpened
    ];
  }

  /**
   * Funkce vracející ID vlastníka (uživatele)
   * @return int
   */
  function getUserId() {
    if (!empty($this->user)){
      return $this->user->userId;
    }else{
      return null;
    }
  }

  /**
   * Returns a string identifier of the Resource.
   * @return string
   */
  function getResourceId() {
    return 'ENTITY:Miner';
  }
}
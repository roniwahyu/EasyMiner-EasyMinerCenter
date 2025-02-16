<?php

namespace EasyMinerCenter\Model\EasyMiner\Entities;


use LeanMapper\Entity;

/**
 * Class Rule
 * @package EasyMinerCenter\Model\EasyMiner\Entities
 * @property int $ruleId
 * @property Task $task m:hasOne
 * @property-read RuleSetRuleRelation[] $ruleSetRuleRelations m:belongsToMany
 * @property string $text
 * @property string $pmmlRuleId = ''
 * @property Cedent|null $antecedent m:hasOne(antecedent)
 * @property Cedent|null $consequent m:hasOne(consequent)
 * @property int|null $a
 * @property int|null $b
 * @property int|null $c
 * @property int|null $d
 * @property float|null $confidence = null
 * @property float|null $support = null
 * @property float|null $lift = null
 * @property bool $inRuleClipboard
 * @property-read array $basicDataArr
 */
class Rule extends Entity{

  public function getBasicDataArr() {
    return ['id'=>$this->ruleId,'text'=>$this->text,'a'=>$this->a,'b'=>$this->b,'c'=>$this->c,'d'=>$this->d,'selected'=>($this->inRuleClipboard?'1':'0')];
  }

  /**
   * Funkce vracející relaci tohoto pravidla ke konkrétnímu rule setu
   * @param $ruleSet
   * @return RuleSetRuleRelation
   */
  public function getRuleSetRelation($ruleSet){
    if ($ruleSet instanceof RuleSet){
      $ruleSet=$ruleSet->ruleSetId;
    }
    if (!empty($this->ruleSetRuleRelations)){
      foreach($this->ruleSetRuleRelations as $ruleSetRuleRelation){
        if ($ruleSetRuleRelation->ruleSet->ruleSetId==$ruleSet){
          return $ruleSetRuleRelation;
        }
      }
    }
  }

  public function getRuleHeadDataArr(){
    return [
      'text'=>$this->text,
      'task_id'=>$this->task->taskId,
      'pmml_rule_id'=>$this->pmmlRuleId,
      'a'=>$this->a,
      'b'=>$this->b,
      'c'=>$this->c,
      'd'=>$this->d
    ];
  }

}